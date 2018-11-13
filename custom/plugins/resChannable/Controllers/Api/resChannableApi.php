<?php

class Shopware_Controllers_Api_resChannableApi extends Shopware_Controllers_Api_Rest
{

    private $allowedFncs = array('getarticles');

    /**
     * @var \resChannable\Components\Api\Resource\ResChannableArticle
     */
    protected $channableArticleResource = null;

    /**
     * @var \Shopware\Components\Api\Resource\Article
     */
    protected $articleResource = null;

    /**
     * @var \Shopware\Components\Api\Resource\Media
     */
    protected $mediaResource = null;

    /**
     * @var \Shopware\Components\Api\Resource\Translation
     */
    protected $translationResource = null;

    /**
     * @var \Shopware\Models\Shop\Shop
     */
    protected $shop = null;

    protected $sSYSTEM = null;

    protected $config = null;

    /**
     * @var sAdmin
     */
    protected $admin = null;

    /**
     * @var sExport
     */
    protected $export = null;

    /**
     * @var Shopware_Components_Modules
     */
    protected $moduleManager = null;

    private $paymentMethods = null;

    private $pluginConfig = null;

    public function init()
    {
        $repository = Shopware()->Models()->getRepository('Shopware\Models\Shop\Shop');
        $this->shop = $repository->getActiveDefault();
        $this->shop->registerResources(Shopware()->Container());
        $this->admin = Shopware()->Modules()->Admin();
        $this->export = Shopware()->Modules()->Export();

        $this->setContainer(Shopware()->Container());

        $this->pluginConfig = $this->container->get('shopware.plugin.cached_config_reader')->getByPluginName('resChannable', $this->shop);

        $this->channableArticleResource = \Shopware\Components\Api\Manager::getResource('ResChannableArticle');
        $this->articleResource = \Shopware\Components\Api\Manager::getResource('Article');
        $this->mediaResource = \Shopware\Components\Api\Manager::getResource('Media');
        $this->translationResource = \Shopware\Components\Api\Manager::getResource('Translation');

        $this->sSYSTEM = Shopware()->System();

        $this->config = Shopware()->Config();

        $this->moduleManager = $this->container->get('Modules');

        $this->loadPaymentMethods();
    }

    public function indexAction()
    {
        $fnc = $this->Request()->getParam('fnc');

        if ( !in_array($fnc,$this->allowedFncs)) {

            throw new Shopware\Components\Api\Exception\ValidationException('Function not found');

        }

        $result = array();

        switch ($fnc) {

            case 'getarticles':

                $articleList = $this->getArticleList();

                $result['articles'] = $articleList;

                break;

        }

        $this->View()->assign($result);
        $this->View()->assign('success', true);
    }

    private function getArticleList()
    {
        $articleIdList = $this->getArticleIdList();

        $result = array();

        for ($i = 0; $i < sizeof($articleIdList); $i++) {

            $channableArticle = $articleIdList[$i];

            # fix because of the "transfer all articles" flag
            if ($channableArticle['detail']) {
                $detail = $channableArticle['detail'];
            } else {
                $detail = $channableArticle;
            }

            $article = $detail['article'];
            $articleId = $detail['articleId'];
            $item = array();

            $item['id'] = $detail['id'];
            $item['groupId'] = $detail['articleId'];
            $item['articleNumber'] = $detail['number'];
            $item['active'] = $detail['active'];
            $item['name'] = $article['name'];
            $item['additionalText'] = $detail['additionalText'];
            $item['supplier'] = $article['supplier']['name'];
            $item['supplierNumber'] = $detail['supplierNumber'];
            $item['ean'] = $detail['ean'];
            $item['description'] = $article['description'];
            $item['descriptionLong'] = $article['descriptionLong'];

            $item['releaseDate'] = $detail['releaseDate'];

            $item['images'] = $this->getArticleImagePaths($detail['images']);

            # Links
            $links = $this->getArticleLinks($articleId,$article['name'],$detail['number']);
            $item['seoUrl'] = $links['seoUrl'];
            $item['url'] = $links['url'];
            $item['rewriteUrl'] = $links['rewrite'];

            # Only show stock if instock exceeds minpurchase
            if ( $detail['inStock'] >= $detail['minPurchase']) {
                $item['stock'] = $detail['inStock'];
            } else {
                $item['stock'] = 0;
            }

            # Price
            $item['priceNetto'] = $detail['prices'][0]['price'];
            $item['priceBrutto'] = round($detail['prices'][0]['price'] * (($article['tax']['tax'] + 100) / 100),2);
            $item['pseudoPriceNetto'] = $detail['prices'][0]['pseudoPrice'];
            $item['pseudoPriceBrutto'] = round($detail['prices'][0]['pseudoPrice'] * (($article['tax']['tax'] + 100) / 100),2);
            $item['currency'] = $this->shop->getCurrency()->getCurrency();
            $item['taxRate'] = $article['tax']['tax'];

            # Delivery time text
            $item['shippingTime'] = $detail['shippingTime'];
            $item['shippingTimeText'] = $this->getShippingTimeText($detail);
            $item['shippingFree'] = $detail['shippingFree'];

            $item['weight'] = $detail['weight'];
            $item['width'] = $detail['width'];
            $item['height'] = $detail['height'];
            $item['length'] = $detail['len'];

            # Units
            $item['packUnit'] = $detail['packUnit'];
            $item['purchaseUnit'] = $detail['purchaseUnit'];
            $item['referenceUnit'] = $detail['referenceUnit'];
            if ( isset($detail['unit']) ) {
                $item['unit'] = $detail['unit']['unit'];
                $item['unitName'] = $detail['unit']['name'];
            }

            # Categories
            $item['categories'] = $this->getArticleCategories($articleId);

            # Shipping costs
            $item['shippingCosts'] = $this->getShippingCosts($detail);

            # Properties
            $item['properties'] = $this->getArticleProperties($article['propertyValues']);

            # Configuration
            $item['options'] = $this->getProductConfiguration($detail['configuratorOptions']);

            # Similar
            $item['similar'] = $this->channableArticleResource->getArticleSimilar($articleId);

            # Related
            $item['related'] = $this->channableArticleResource->getArticleRelated($articleId);

            # Translations
            $item['translations'] = $this->getTranslations($articleId);

            $result[] = $item;

        }

        return $result;
    }

    private function getArticleIdList()
    {
        $limit = $this->pluginConfig['apiPollLimit'];
        $offset = $this->Request()->getParam('offset');
        $sort = '';

        $this->View()->assign('offset', $offset);
        $this->View()->assign('limit', $limit);

        $filter = array();

        # only articles with images
        if ( $this->pluginConfig['apiOnlyArticlesWithImg'] ) {
            $filter[] = array(
                'property'   => 'images.id',
                'expression' => '>',
                'value'      => '0'
            );
        }

        # only active articles
        if ( $this->pluginConfig['apiOnlyActiveArticles'] ) {
            $filter[] = array(
                'property'   => 'article.active',
                'expression' => '=',
                'value'      => '1'
            );
        }

        # only articles with an ean
        if ( $this->pluginConfig['apiOnlyArticlesWithEan'] ) {
            $filter[] = array(
                'property'   => 'detail.ean',
                'expression' => '!=',
                'value'      => ''
            );
        }

        # check if all articles flag is set
        if ( $this->pluginConfig['apiAllArticles'] ) {
            $result = $this->channableArticleResource->getAllArticlesList($offset, $limit, $filter, $sort);
        } else {
            $result = $this->channableArticleResource->getList($offset, $limit, $filter, $sort);
        }

        return $result['data'];
    }

    private function getArticleImagePaths($articleImages)
    {
        $images = array();

        for ( $i = 0; $i < sizeof($articleImages); $i++ ) {

            try {

                if ($articleImages[$i]['mediaId']) {

                    $image = $this->mediaResource->getOne($articleImages[$i]['mediaId']);
                    $images[] = $image['path'];

                } elseif ( !empty($articleImages[$i]['parent']) && $articleImages[$i]['parent']['mediaId'] ) {

                    $image = $this->mediaResource->getOne($articleImages[$i]['parent']['mediaId']);
                    $images[] = $image['path'];

                }

            } catch ( \Exception $Exception ) {

            }

        }

        return $images;
    }

    /**
     * Helper function which selects all configured links
     * for the passed article id.
     *
     * @param $articleId
     *
     * @return array
     */
    protected function getArticleLinks($articleId,$name,$number)
    {
        $baseFile = $this->getBasePath();
        $detail = $baseFile . '?sViewport=detail&sArticle=' . $articleId . '&number='.$number;

        $rewrite = Shopware()->Modules()->Core()->sRewriteLink($detail, $name);

        $seoUrl = $baseFile . $this->channableArticleResource->getArticleSeoUrl($articleId) . '?number='.$number;

        $links = array('rewrite' => $rewrite,
                       'url'  => $detail,
                       'seoUrl' => $seoUrl);

        return $links;
    }

    private function getBasePath()
    {
        $url = $this->Request()->getBaseUrl() . '/';
        $uri = $this->Request()->getScheme() . '://' . $this->Request()->getHttpHost();
        $url = $uri . $url;

        return $url;
    }

    private function getShippingTimeText($detail)
    {

        if ( isset($detail['active']) && !$detail['active'] ) {

            $shippingTime = Shopware()->Snippets()->getNamespace('frontend/plugins/index/delivery_informations')->get(
                'DetailDataInfoNotAvailable'
            );

        } elseif ( $detail['releaseDate'] instanceOf \DateTime && $detail['releaseDate']->getTimestamp() > time() ) {

            $dateFormat = Shopware()->Snippets()->getNamespace('api/resChannable')->get(
                'dateFormat'
            );

            $shippingTime = Shopware()->Snippets()->getNamespace('frontend/plugins/index/delivery_informations')->get(
                'DetailDataInfoShipping'
            ) . ' ' . date($dateFormat);

            # Todo ESD, partial stock
            /*} elseif ( $detail['esd'] ) {
                /*<link itemprop="availability" href="http://schema.org/InStock" />
                <p class="delivery--information">
                    <span class="delivery--text delivery--text-available">
                        <i class="delivery--status-icon delivery--status-available"></i>
                        {s name="DetailDataInfoInstantDownload"}{/s}
                    </span>
                </p>
        } elseif {config name="instockinfo"} && $sArticle.modus == 0 && $sArticle.instock > 0 && $sArticle.quantity > $sArticle.instock}
            <link itemprop="availability" href="http://schema.org/LimitedAvailability" />
            <p class="delivery--information">
                <span class="delivery--text delivery--text-more-is-coming">
                    <i class="delivery--status-icon delivery--status-more-is-coming"></i>
                    {s name="DetailDataInfoPartialStock"}{/s}
                </span>
            </p>*/
        } elseif ( $detail['inStock'] >= $detail['minPurchase'] ) {

            $shippingTime = Shopware()->Snippets()->getNamespace('frontend/plugins/index/delivery_informations')->get(
                'DetailDataInfoInstock'
            );

        } elseif ( $detail['shippingTime'] ) {

            $shippingTime = Shopware()->Snippets()->getNamespace('frontend/plugins/index/delivery_informations')->get(
                'DetailDataShippingtime'
            ) . ' ' . $detail['shippingTime'] . ' ' . Shopware()->Snippets()->getNamespace('frontend/plugins/index/delivery_informations')->get(
                'DetailDataShippingDays'
            );

        } else {

            $shippingTime = Shopware()->Snippets()->getNamespace('frontend/plugins/index/delivery_informations')->get(
                'DetailDataNotAvailable'
            );

        }

        return $shippingTime;
    }

    private function getArticleCategories($articleId)
    {
        $categories = $this->channableArticleResource->getArticleCategories($articleId);

        $em = $this->getModelManager();
        $category = $em->getRepository('Shopware\Models\Category\Category');

        $categoryList = array();

        for ( $i = 0; $i < sizeof($categories); $i++ ) {

            $path = $category->getPathById($categories[$i]['id']);

            $categoryList[] = array_values($path);
        }

        return $categoryList;
    }

    public function getShippingCosts($detail)
    {
        $paymentMethods = $this->getPaymentMethods();

        $article = array('articleID' => $detail['articleId'],
                         'ordernumber' => $detail['number'],
                         'shippingfree' => $detail['shippingFree'],
                         'price' => $detail['prices'][0]['price'] * (($detail['article']['tax']['tax'] + 100) / 100),
                         'netprice' => $detail['prices'][0]['price'],
                         'esd' => 0
        );

        $this->export->sCurrency['factor'] = $this->shop->getCurrency()->getFactor();

        $payment = $paymentMethods[0]['id'];

        $country = 2;

        $shippingCosts = $this->export->sGetArticleShippingcost($article, $payment, $country);

        return $shippingCosts;
    }

    private function loadPaymentMethods()
    {
        $builder = Shopware()->Container()->get('dbal_connection')->createQueryBuilder();
        $builder->select([
            'id',
            'name'
        ]);
        $builder->from('s_core_paymentmeans', 'payments');
        $builder->where('payments.active = 1');

        $statement = $builder->execute();
        $this->paymentMethods = $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getPaymentMethods()
    {
        return $this->paymentMethods;
    }

    private function getArticleProperties($propertyValues)
    {
        $properties = array();

        for ( $i = 0; $i < sizeof($propertyValues); $i++) {

            $properties[$propertyValues[$i]['option']['name']][] = $propertyValues[$i]['value'];

        }

        return $properties;
    }

    private function getTranslations($articleId)
    {
        $builder = Shopware()->Container()->get('dbal_connection')->createQueryBuilder();
        $builder->select([
            'translations.languageID','locales.language','locales.locale','translations.name',
            'translations.description','translations.description_long as descriptionLong'
        ]);
        $builder->from('s_articles_translations', 'translations');
        $builder->innerJoin('translations','s_core_shops','shops','translations.languageID = shops.id');
        $builder->innerJoin('shops','s_core_locales','locales','shops.locale_id = locales.id');
        $builder->where('translations.articleID = :articleId');
        $builder->setParameter('articleId',$articleId);

        $statement = $builder->execute();
        $languages = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return $languages;
    }

    private function getProductConfiguration($configuratorOptions)
    {
        $options = array();

        for ( $i = 0; $i < sizeof($configuratorOptions); $i++) {

            $options[$configuratorOptions[$i]['group']['name']] = $configuratorOptions[$i]['name'];

        }

        return $options;
    }

}