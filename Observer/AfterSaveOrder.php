<?php
declare(strict_types=1);

namespace Doddle\Returns\Observer;

use Magento\Framework\Event\ObserverInterface;
use Doddle\Returns\Api\Data\OrderQueueInterface;
use Doddle\Returns\Api\Data\OrderQueueInterfaceFactory;
use Doddle\Returns\Model\OrderQueue;

class AfterSaveOrder implements ObserverInterface
{
    /** @var OrderQueueInterfaceFactory */
    private $orderQueueFactory;

    /**
     * @param OrderQueueInterfaceFactory $orderQueueFactory
     */
    public function __construct(
        OrderQueueInterfaceFactory $orderQueueFactory
    ) {
        $this->orderQueueFactory = $orderQueueFactory;
    }

    /**
     * Observer function called after order save (Order ID is not available at sales_order_place_after)
     *
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var $product \Magento\Sales\Api\Data\OrderInterface */
        $order = $observer->getEvent()->getOrder();

        if ($order->getId()) {
            $this->queueOrder($order->getId());
        }
    }

    /**
     * Queue up the order if it is not already queued
     *
     * @param $orderId
     * @throws \Exception
     */
    private function queueOrder($orderId)
    {
        /** @var OrderQueue $orderQueue */
        $orderQueue = $this->orderQueueFactory->create();
        $orderQueue->load($orderId, OrderQueueInterface::ORDER_ID);

        if (!$orderQueue->getId()) {
            $orderQueue->setData([OrderQueueInterface::ORDER_ID => $orderId]);
            $orderQueue->save();
        }
    }
}
