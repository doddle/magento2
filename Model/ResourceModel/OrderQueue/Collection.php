<?php
namespace Doddle\Returns\Model\ResourceModel\OrderQueue;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Doddle\Returns\Model\OrderQueue;
use Doddle\Returns\Model\ResourceModel\OrderQueue as OrderQueueResource;

class Collection extends AbstractCollection
{
    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(OrderQueue::class, OrderQueueResource::class);
    }
}
