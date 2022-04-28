<?php
declare(strict_types=1);

namespace Doddle\Returns\Model\Product;

use Magento\Framework\Model\AbstractModel;
use Doddle\Returns\Api\Data\Product\VariationInterface;

class Variation extends AbstractModel implements VariationInterface
{
    /**
     * @inheritDoc
     */
    public function getSku()
    {
        return $this->getData(self::SKU);
    }

    /**
     * @inheritDoc
     */
    public function setSku($sku)
    {
        return $this->setData(self::SKU, $sku);
    }

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return $this->getData(self::NAME);
    }

    /**
     * @inheritDoc
     */
    public function setName($name)
    {
        return $this->setData(self::NAME, $name);
    }

    /**
     * @inheritDoc
     */
    public function getImageUrl()
    {
        return $this->getData(self::IMAGE_URL);
    }

    /**
     * @inheritDoc
     */
    public function setImageUrl($imageUrl)
    {
        return $this->setData(self::IMAGE_URL, $imageUrl);
    }

    /**
     * @inheritDoc
     */
    public function getStock()
    {
        return $this->getData(self::STOCK);
    }

    /**
     * @inheritDoc
     */
    public function setStock($stock)
    {
        return $this->setData(self::STOCK, $stock);
    }

    /**
     * @inheritDoc
     */
    public function getAttributes()
    {
        return $this->getData(self::ATTRIBUTES);
    }

    /**
     * @inheritDoc
     */
    public function setAttributes($attributes)
    {
        return $this->setData(self::ATTRIBUTES, $attributes);
    }

    /**
     * @inheritDoc
     */
    public function getReturnsExcluded()
    {
        return $this->getData(self::RETURNS_EXCLUDED);
    }

    /**
     * @inheritDoc
     */
    public function setReturnsExcluded($returnsExcluded)
    {
        return $this->setData(self::RETURNS_EXCLUDED, $returnsExcluded);
    }
}
