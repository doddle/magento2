<?php
declare(strict_types=1);

namespace Doddle\Returns\Model\Product\Attribute\Backend;

use Magento\Catalog\Model\Product\Attribute\Source\Boolean as BooleanSource;
use Magento\Framework\DataObject;
use Magento\Eav\Model\Entity\Attribute\Backend\AbstractBackend;

class Boolean extends AbstractBackend
{
    /**
     * Set attribute default value if value empty
     *
     * @param DataObject $object
     * @return Boolean
     */
    public function afterLoad($object): self
    {
        $attributeCode = $this->getAttribute()->getName();

        if ($object->getData($attributeCode) === null) {
            $object->setData($attributeCode, BooleanSource::VALUE_NO);
        }

        return parent::afterLoad($object);
    }
}
