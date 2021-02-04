<?php
declare(strict_types=1);

namespace Doddle\Returns\Observer;

use Doddle\Returns\Api\Data\OrderQueueInterface;
use Doddle\Returns\Api\Data\OrderQueueInterfaceFactory;
use Doddle\Returns\Helper\Data as DataHelper;
use Doddle\Returns\Model\OrderQueue;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderInterface;

class AfterSaveOrder implements ObserverInterface
{
    /** @var DataHelper */
    private $dataHelper;

    /** @var OrderQueueInterfaceFactory */
    private $orderQueueFactory;

    /**
     * @param OrderQueueInterfaceFactory $orderQueueFactory
     */
    public function __construct(
        DataHelper $dataHelper,
        OrderQueueInterfaceFactory $orderQueueFactory
    ) {
        $this->dataHelper = $dataHelper;
        $this->orderQueueFactory = $orderQueueFactory;
    }

    /**
     * Observer function called after order save (Order ID is not available at sales_order_place_after)
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        /** @var $order OrderInterface */
        $order = $observer->getEvent()->getOrder();

        if (!$order->getId()) {
            return;
        }

        // Only add to order queue if store config is enabled
        if ($this->dataHelper->getOrderSyncEnabled((int) $order->getStoreId()) == false) {
            return;
        }

        $this->queueOrder($order->getId());
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
