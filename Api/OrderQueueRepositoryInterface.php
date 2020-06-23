<?php
namespace Doddle\Returns\Api;

use Doddle\Returns\Api\Data\OrderQueueInterface;

interface OrderQueueRepositoryInterface
{
    /**
     * @param \Doddle\Returns\Api\Data\OrderQueueInterface
     * @return \Doddle\Returns\Api\Data\OrderQueueInterface
     */
    public function save(OrderQueueInterface $orderQueue);
}
