<?php
namespace Doddle\Returns\Api\Data\Product;

interface VariationAttributeInterface
{
    public const LABEL       = 'label';
    public const VALUE       = 'value';
    public const VALUE_INDEX = 'value_index';

    /**
     * Get attribute label
     *
     * @return string
     */
    public function getLabel();

    /**
     * Get attribute value
     *
     * @return string
     */
    public function getValue();

    /**
     * Get attribute value ID
     *
     * @return int
     */
    public function getValueIndex();
}
