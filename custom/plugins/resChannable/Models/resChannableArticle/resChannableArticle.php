<?php

namespace resChannable\Models\resChannableArticle;

use Shopware\Components\Model\ModelEntity;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class article
 * @package resChannable
 *
 * @ORM\Entity(repositoryClass="Repository")
 * @ORM\Table(name="reschannable_articles")
 */
class resChannableArticle extends ModelEntity
{

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @var int
     *
     * @ORM\Column(name="detailID", type="integer", nullable=true)
     */
    protected $detailID;

    /**
     * OWNING SIDE
     *
     * @ORM\OneToOne(targetEntity="Shopware\Models\Article\Detail")
     * @ORM\JoinColumn(name="detailID", referencedColumnName="id")
     */
    protected $detail;

    /**
     * @return int
     */
    public function getDetailID()
    {
        return $this->detailID;
    }

    /**
     * @param int $detailID
     * @return resChannableArticle
     */
    public function setDetailID($detailID)
    {
        $this->detailID = $detailID;
        return $this;
    }
}
