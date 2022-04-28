<?php
declare(strict_types=1);

namespace Doddle\Returns\Model;

use Magento\Wishlist\Model\WishlistFactory;
use Doddle\Returns\Api\OrderQueueRepositoryInterface;
use Doddle\Returns\Api\Data\OrderQueueInterface;

class OrderQueueRepository implements OrderQueueRepositoryInterface
{
    /**
     * @inheritdoc
     */
    public function save(OrderQueueInterface $orderQueue): OrderQueueInterface
    {
        $orderQueue->save($orderQueue);
        return $orderQueue;
    }
}
