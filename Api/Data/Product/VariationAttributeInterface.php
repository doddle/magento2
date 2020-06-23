<?php
namespace Doddle\Returns\Api\Data\Product;

interface VariationAttributeInterface
{
    const LABEL       = 'label';
    const VALUE       = 'value';
    const VALUE_INDEX = 'value_index';

    /**
     * @return string
     */
    public function getLabel();

    /**
     * @return string
     */
    public function getValue();

    /**
     * @return int
     */
    public function getValueIndex();
}
