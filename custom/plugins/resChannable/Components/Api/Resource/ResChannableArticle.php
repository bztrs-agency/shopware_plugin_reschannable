<?php

namespace resChannable\Components\Api\Resource;

use Shopware\Components\Api\Resource\Resource;
use Shopware\Components\Model\QueryBuilder;

class ResChannableArticle extends Resource
{

    /**
     * @param $offset
     * @param $limit
     * @param $filter
     * @param $sort
     *
     * @return array
     */
    public function getAllArticlesList($offset, $limit, $filter, $sort)
    {
        $this->checkPrivilege('read');

        $builder = $this->getAllArticlesBaseQuery();
        $builder = $this->addQueryLimit($builder, $offset, $limit);

        if (!empty($filter)) {
            $builder->addFilter($filter);
        }
        if (!empty($sort)) {
            $builder->addOrderBy($sort);
        }

        $query = $builder->getQuery();

        $query->setHydrationMode($this->getResultMode());

        $paginator = $this->getManager()->createPaginator($query);
        $totalResult = $paginator->count();
        $articles = $paginator->getIterator()->getArrayCopy();

        return ['data' => $articles, 'total' => $totalResult];
    }


    /**
     * @param $offset
     * @param $limit
     * @param $filter
     * @param $sort
     *
     * @return array
     */
    public function getList($offset, $limit, $filter, $sort)
    {
        $this->checkPrivilege('read');

        $builder = $this->getBaseQuery();
        $builder = $this->addQueryLimit($builder, $offset, $limit);

        if (!empty($filter)) {
            $builder->addFilter($filter);
        }
        if (!empty($sort)) {
            $builder->addOrderBy($sort);
        }

        $query = $builder->getQuery();

        $query->setHydrationMode($this->getResultMode());

        $paginator = $this->getManager()->createPaginator($query);
        $totalResult = $paginator->count();
        $articles = $paginator->getIterator()->getArrayCopy();

        return ['data' => $articles, 'total' => $totalResult];
    }

    /**
     * @param QueryBuilder $builder
     * @param              $offset
     * @param null         $limit
     *
     * @return QueryBuilder
     */
    protected function addQueryLimit(QueryBuilder $builder, $offset, $limit = null)
    {
        $builder->setFirstResult($offset)
            ->setMaxResults($limit);

        return $builder;
    }

    /**
     * @return \Doctrine\ORM\QueryBuilder|QueryBuilder
     */
    protected function getAllArticlesBaseQuery()
    {
        $builder = $this->getManager()->createQueryBuilder();

        $builder->select([
            'detail',
            'article',
            'detailPrices',
            'tax',
            'propertyValues',
            'propertyOption',
            'configuratorOptions',
            'configuratorGroups',
            'supplier',
            'priceCustomerGroup',
            'detailAttribute',
            'propertyGroup',
            'customerGroups',
            'detailUnit',
            'similar',
            'related',
            'images',
            'imageParent',
            'imageAttribute',
            'imageMapping',
            'mappingRule',
            'ruleOption'
        ])
            ->from('Shopware\Models\Article\Detail', 'detail')
            ->join('detail.article', 'article')
            ->leftJoin('detail.prices', 'detailPrices')
            ->leftJoin('detail.images', 'images')
            ->leftJoin('images.parent', 'imageParent')
            ->leftJoin('imageParent.attribute', 'imageAttribute')
            ->leftJoin('images.mappings', 'imageMapping')
            ->leftJoin('imageMapping.rules', 'mappingRule')
            ->leftJoin('mappingRule.option', 'ruleOption')
            ->leftJoin('detailPrices.customerGroup', 'priceCustomerGroup')
            ->leftJoin('detail.configuratorOptions', 'configuratorOptions')
            ->leftJoin('configuratorOptions.group', 'configuratorGroups')
            ->leftJoin('article.tax', 'tax')
            ->leftJoin('article.propertyValues', 'propertyValues')
            ->leftJoin('propertyValues.option', 'propertyOption')
            ->leftJoin('article.supplier', 'supplier')
            ->leftJoin('detail.attribute', 'detailAttribute')
            ->leftJoin('article.propertyGroup', 'propertyGroup')
            ->leftJoin('article.customerGroups', 'customerGroups')
            ->leftJoin('detail.unit', 'detailUnit')
            ->leftJoin('article.similar', 'similar')
            ->leftJoin('article.related', 'related');

        return $builder;
    }

    /**
     * @return \Doctrine\ORM\QueryBuilder|QueryBuilder
     */
    protected function getBaseQuery()
    {
        $builder = $this->getManager()->createQueryBuilder();

        $builder->select([
            'ChannableArticle',
            'article',
            'detail',
            'detailPrices',
            'tax',
            'propertyValues',
            'propertyOption',
            'configuratorOptions',
            'configuratorGroups',
            'supplier',
            'priceCustomerGroup',
            'detailAttribute',
            'propertyGroup',
            'customerGroups',
            'detailUnit',
            'similar',
            'related',
            'images',
            'imageParent',
            'imageAttribute',
            'imageMapping',
            'mappingRule',
            'ruleOption'
        ])
            ->from('resChannable\Models\resChannableArticle\resChannableArticle', 'ChannableArticle')
            ->join('ChannableArticle.detail', 'detail')
            ->join('detail.article', 'article')
            ->leftJoin('detail.prices', 'detailPrices')
            ->leftJoin('detail.images', 'images')
            ->leftJoin('images.parent', 'imageParent')
            ->leftJoin('imageParent.attribute', 'imageAttribute')
            ->leftJoin('images.mappings', 'imageMapping')
            ->leftJoin('imageMapping.rules', 'mappingRule')
            ->leftJoin('mappingRule.option', 'ruleOption')
            ->leftJoin('detailPrices.customerGroup', 'priceCustomerGroup')
            ->leftJoin('article.tax', 'tax')
            ->leftJoin('article.propertyValues', 'propertyValues')
            ->leftJoin('propertyValues.option', 'propertyOption')
            ->leftJoin('article.supplier', 'supplier')
            ->leftJoin('detail.attribute', 'detailAttribute')
            ->leftJoin('detail.configuratorOptions', 'configuratorOptions')
            ->leftJoin('configuratorOptions.group', 'configuratorGroups')
            ->leftJoin('article.propertyGroup', 'propertyGroup')
            ->leftJoin('article.customerGroups', 'customerGroups')
            ->leftJoin('detail.unit', 'detailUnit')
            ->leftJoin('article.similar', 'similar')
            ->leftJoin('article.related', 'related');

        return $builder;
    }

    /**
     * Helper function to prevent duplicate source code
     * to get the full query builder result for the current resource result mode
     * using the query paginator.
     *
     * @param QueryBuilder $builder
     *
     * @return array
     */
    private function getFullResult(QueryBuilder $builder)
    {
        $query = $builder->getQuery();
        $query->setHydrationMode($this->getResultMode());
        $paginator = $this->getManager()->createPaginator($query);

        return $paginator->getIterator()->getArrayCopy();
    }

    /**
     * Helper function to prevent duplicate source code
     * to get a single row of the query builder result for the current resource result mode
     * using the query paginator.
     *
     * @param QueryBuilder $builder
     *
     * @return array
     */
    private function getSingleResult(QueryBuilder $builder)
    {
        $query = $builder->getQuery();
        $query->setHydrationMode($this->getResultMode());
        $paginator = $this->getManager()->createPaginator($query);

        return $paginator->getIterator()->current();
    }

    /**
     * Helper function which selects all categories of the passed
     * article id.
     * This function returns only the directly assigned categories.
     * To prevent a big data, this function selects only the category name and id.
     *
     * @param $articleId
     *
     * @return array
     */
    public function getArticleCategories($articleId)
    {
        $builder = $this->getManager()->createQueryBuilder();
        $builder->select(['categories.id', 'categories.name'])
            ->from('Shopware\Models\Category\Category', 'categories')
            ->innerJoin('categories.articles', 'articles')
            ->where('articles.id = :articleId')
            ->setParameter('articleId', $articleId);

        return $this->getFullResult($builder);
    }

    public function getArticleSeoUrl($articleId)
    {
        $connection = Shopware()->Container()->get('dbal_connection');

        $url = $connection->fetchColumn("SELECT path FROM `s_core_rewrite_urls` WHERE main=1 AND subshopID=1 AND org_path=?", ['sViewport=detail&sArticle='.$articleId]);

        return $url;
    }

    /**
     * Helper function which selects all similar articles
     * of the passed article id.
     *
     * @param $articleId
     *
     * @return mixed
     */
    public function getArticleSimilar($articleId)
    {
        $builder = $this->getManager()->createQueryBuilder();
        $builder->select(['article', 'PARTIAL similar.{id, name}'])
            ->from('Shopware\Models\Article\Article', 'article')
            ->innerJoin('article.similar', 'similar')
            ->where('article.id = :articleId')
            ->setParameter('articleId', $articleId);

        $article = $this->getSingleResult($builder);

        return $article['similar'];
    }

    /**
     * Helper function which selects all accessory articles
     * of the passed article id.
     *
     * @param $articleId
     *
     * @return mixed
     */
    public function getArticleRelated($articleId)
    {
        $builder = $this->getManager()->createQueryBuilder();
        $builder->select(['article', 'PARTIAL related.{id, name}'])
            ->from('Shopware\Models\Article\Article', 'article')
            ->innerJoin('article.related', 'related')
            ->where('article.id = :articleId')
            ->setParameter('articleId', $articleId);

        $article = $this->getSingleResult($builder);

        return $article['related'];
    }

}
