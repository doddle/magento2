<?php
declare(strict_types=1);

namespace Doddle\Returns\Model\Product\Attribute\Backend;

use Magento\Catalog\Model\Product\Attribute\Source\Boolean as BooleanSource;

class Boolean extends \Magento\Eav\Model\Entity\Attribute\Backend\AbstractBackend
{
    /**
     * Set attribute default value if value empty
     *
     * @param \Magento\Framework\DataObject $object
     * @return \Magento\Eav\Model\Entity\Attribute\Backend\AbstractBackend
     */
    public function afterLoad($object)
    {
        $attributeCode = $this->getAttribute()->getName();

        if ($object->getData($attributeCode) === null) {
            $object->setData($attributeCode, BooleanSource::VALUE_NO);
        }

        return parent::afterLoad($object);
    }
}
