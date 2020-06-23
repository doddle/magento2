<?php
declare(strict_types=1);

namespace Doddle\Returns\Model\Product;

use Magento\Framework\Model\AbstractModel;
use Doddle\Returns\Api\Data\Product\VariationAttributeInterface;

class VariationAttribute extends AbstractModel implements VariationAttributeInterface
{
    /**
     * {@inheritDoc}
     */
    public function getLabel()
    {
        return $this->getData(self::LABEL);
    }

    /**
     * {@inheritDoc}
     */
    public function getValue()
    {
        return $this->getData(self::VALUE);
    }

    /**
     * {@inheritDoc}
     */
    public function getValueIndex()
    {
        return $this->getData(self::VALUE_INDEX);
    }
}
