<?php
namespace Doddle\Returns\Api;

use Doddle\Returns\Api\Data\OrderQueueInterface;

interface OrderQueueRepositoryInterface
{
    /**
     * Save order queue entity
     *
     * @param \Doddle\Returns\Api\Data\OrderQueueInterface $orderQueue
     * @return \Doddle\Returns\Api\Data\OrderQueueInterface
     */
    public function save(OrderQueueInterface $orderQueue);
}
