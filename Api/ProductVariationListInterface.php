<?php
namespace Doddle\Returns\Api;

interface ProductVariationListInterface
{
    /**
     * Retrieve information about sibling variants of a configurable product's child sku
     *
     * @param string $sku
     * @return \Doddle\Returns\Api\Data\Product\VariationInterface[]
     */
    public function getItems($sku);
}
