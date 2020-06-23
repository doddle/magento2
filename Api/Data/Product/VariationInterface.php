<?php
namespace Doddle\Returns\Api\Data\Product;

interface VariationInterface
{
    const ENTITY_ID        = 'entity_id';
    const SKU              = 'sku';
    const NAME             = 'name';
    const IMAGE_URL        = 'image_url';
    const STOCK            = 'stock';
    const RETURNS_EXCLUDED = 'doddle_returns_excluded';
    const ATTRIBUTES       = 'attributes';

    /**
     * @return int
     */
    public function getEntityId();

    /**
     * @param $entityId
     * @return int
     */
    public function setEntityId($entityId);

    /**
     * @return string
     */
    public function getSku();

    /**
     * @param $sku
     * @return string
     */
    public function setSku($sku);

    /**
     * @return string
     */
    public function getName();

    /**
     * @param $name
     * @return string
     */
    public function setName($name);

    /**
     * @return string
     */
    public function getImageUrl();

    /**
     * @param $imageUrl
     * @return string
     */
    public function setImageUrl($imageUrl);

    /**
     * @return float
     */
    public function getStock();

    /**
     * @param $stock
     * @return float
     */
    public function setStock($stock);

    /**
     * @return \Doddle\Returns\Api\Data\Product\VariationAttributeInterface[]
     */
    public function getAttributes();

    /**
     * @param \Doddle\Returns\Api\Data\Product\VariationAttributeInterface[] $attributes
     * @return \Doddle\Returns\Api\Data\Product\VariationAttributeInterface[]
     */
    public function setAttributes($attributes);

    /**
     * @return boolean
     */
    public function getReturnsExcluded();

    /**
     * @param $returnsExcluded
     * @return boolean
     */
    public function setReturnsExcluded($returnsExcluded);
}
