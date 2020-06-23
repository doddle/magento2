<?php
declare(strict_types=1);

namespace Doddle\Returns\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\AbstractModel;
use Doddle\Returns\Api\Data\OrderQueueInterface;
use Doddle\Returns\Helper\Data as DataHelper;

class OrderQueue extends AbstractDb
{
    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(DataHelper::DB_TABLE_ORDER_QUEUE, OrderQueueInterface::ID);
    }

    /**
     * @param AbstractModel $object
     * @return AbstractDb
     */
    protected function _beforeSave(AbstractModel $object)
    {
        if ($object->hasDataChanges()) {
            $object->unsetData(OrderQueueInterface::UPDATED_AT);
        }

        return parent::_beforeSave($object);
    }
}
