<?php
namespace Doddle\Returns\Api\Data\Product;

interface VariationInterface
{
    public const ENTITY_ID        = 'entity_id';
    public const SKU              = 'sku';
    public const NAME             = 'name';
    public const IMAGE_URL        = 'image_url';
    public const STOCK            = 'stock';
    public const RETURNS_EXCLUDED = 'doddle_returns_excluded';
    public const ATTRIBUTES       = 'attributes';

    /**
     * Get entity ID
     *
     * @return int
     */
    public function getEntityId();

    /**
     * Set entity ID
     *
     * @param $entityId
     * @return int
     */
    public function setEntityId($entityId);

    /**
     * Get product SKU
     *
     * @return string
     */
    public function getSku();

    /**
     * Set product SKU
     *
     * @param $sku
     * @return string
     */
    public function setSku($sku);

    /**
     * Get product name
     *
     * @return string
     */
    public function getName();

    /**
     * Set product name
     *
     * @param $name
     * @return string
     */
    public function setName($name);

    /**
     * Get product image URL
     *
     * @return string
     */
    public function getImageUrl();

    /**
     * Set product image URL
     *
     * @param $imageUrl
     * @return string
     */
    public function setImageUrl($imageUrl);

    /**
     * Get product stock
     *
     * @return float|null
     */
    public function getStock();

    /**
     * Set product stock
     *
     * @param $stock
     * @return float|null
     */
    public function setStock($stock);

    /**
     * Get product attributes
     *
     * @return \Doddle\Returns\Api\Data\Product\VariationAttributeInterface[]
     */
    public function getAttributes();

    /**
     * Set product attributes
     *
     * @param \Doddle\Returns\Api\Data\Product\VariationAttributeInterface[] $attributes
     * @return \Doddle\Returns\Api\Data\Product\VariationAttributeInterface[]
     */
    public function setAttributes($attributes);

    /**
     * Get product returns excluded
     *
     * @return boolean
     */
    public function getReturnsExcluded();

    /**
     * Set product returns excluded
     *
     * @param $returnsExcluded
     * @return boolean
     */
    public function setReturnsExcluded($returnsExcluded);
}
