<?php
declare(strict_types=1);

namespace Doddle\Returns\Observer;

use Doddle\Returns\Api\Data\OrderQueueInterface;
use Doddle\Returns\Api\Data\OrderQueueInterfaceFactory;
use Doddle\Returns\Api\OrderQueueRepositoryInterface;
use Doddle\Returns\Helper\Data as DataHelper;
use Doddle\Returns\Model\OrderQueue;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;

class AfterSaveOrder implements ObserverInterface
{
    /** @var DataHelper */
    private $dataHelper;

    /** @var OrderQueueInterfaceFactory */
    private $orderQueueFactory;

    /** @var OrderQueueRepositoryInterface */
    private $orderQueueRepository;

    /**
     * @param DataHelper $dataHelper
     * @param OrderQueueInterfaceFactory $orderQueueFactory
     * @param OrderQueueRepositoryInterface $orderQueueRepository
     */
    public function __construct(
        DataHelper $dataHelper,
        OrderQueueInterfaceFactory $orderQueueFactory,
        OrderQueueRepositoryInterface $orderQueueRepository
    ) {
        $this->dataHelper = $dataHelper;
        $this->orderQueueFactory = $orderQueueFactory;
        $this->orderQueueRepository = $orderQueueRepository;
    }

    /**
     * Observer function called after order save (Order ID is not available at sales_order_place_after)
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer): void
    {
        /** @var $order OrderInterface */
        $order = $observer->getEvent()->getOrder();

        if ($this->validateOrderForQueue($order) === false) {
            // Skip invalid orders
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
    private function queueOrder($orderId): void
    {
        /** @var OrderQueue $orderQueue */
        $orderQueue = $this->orderQueueFactory->create();
        $orderQueue->load($orderId, OrderQueueInterface::ORDER_ID);

        if (!$orderQueue->getId()) {
            $orderQueue->setData([OrderQueueInterface::ORDER_ID => $orderId]);
            $this->orderQueueRepository->save($orderQueue);
        }
    }

    /**
     * Validate orders to prevent invalid orders being queued
     *
     * @param OrderInterface $order
     * @return bool
     */
    private function validateOrderForQueue(OrderInterface $order): bool
    {
        // Don't push orders that haven't been persisted to Db
        if (!$order->getId()) {
            return false;
        }

        // Don't push cancelled orders
        if ($order->getState() == Order::STATE_CANCELED) {
            return false;
        }

        // Orders with no line items in Magento will not be pushed to the Doddle Purchases API
        if (!$order->getAllItems()) {
            return false;
        }

        // Only push order if sync store config is enabled
        if ($this->dataHelper->getOrderSyncEnabled((int) $order->getStoreId()) == false) {
            return false;
        }

        return true;
    }
}
