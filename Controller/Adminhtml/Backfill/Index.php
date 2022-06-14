<?php
namespace Doddle\Returns\Controller\Adminhtml\Backfill;

use Doddle\Returns\Api\Data\OrderQueueInterface;
use Doddle\Returns\Api\Data\OrderQueueInterfaceFactory;
use Doddle\Returns\Api\OrderQueueRepositoryInterface;
use Doddle\Returns\Helper\Data as DataHelper;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Doddle_Returns::backfill';

    /** @var DataHelper */
    private $dataHelper;

    /** @var OrderQueueInterfaceFactory */
    private $orderQueueFactory;

    /** @var OrderQueueRepositoryInterface */
    private $orderQueueRepository;

    /** @var DateTime */
    private $dateTime;

    /** @var OrderCollectionFactory */
    private $orderCollectionFactory;

    /**
     * Index constructor.
     *
     * @param Context $context
     * @param DataHelper $dataHelper
     * @param OrderQueueInterfaceFactory $orderQueueFactory
     * @param OrderQueueRepositoryInterface $orderQueueRepository
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param DateTime $dateTime
     */
    public function __construct(
        Context $context,
        DataHelper $dataHelper,
        OrderQueueInterfaceFactory $orderQueueFactory,
        OrderQueueRepositoryInterface $orderQueueRepository,
        OrderCollectionFactory $orderCollectionFactory,
        DateTime $dateTime
    ) {
        parent::__construct($context);

        $this->dataHelper = $dataHelper;
        $this->orderQueueFactory = $orderQueueFactory;
        $this->orderQueueRepository = $orderQueueRepository;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->dateTime = $dateTime;
    }

    /**
     * Execute controller
     */
    public function execute()
    {
        try {
            $totalQueued = $this->queueOrders();
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('An error occured whilst back filling the orders to sync. Please try again.')
            );
        }

        $dateLimit = $this->getDateLimit();
        $this->messageManager->addSuccessMessage(
            __(
                '%1 order(s) were queued to sync%2.',
                $totalQueued,
                $dateLimit ? " (limited to orders created after {$dateLimit})" : null
            )
        );

        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $redirect->setUrl($this->_redirect->getRefererUrl());

        return $redirect;
    }

    /**
     * Queue up orders to be sent to API
     *
     * @return int
     */
    private function queueOrders(): int
    {
        $totalQueued = 0;

        /** @var $order OrderInterface */
        foreach ($this->getOrders() as $order) {
            if (!$order->getId()) {
                continue;
            }

            // Only add to order queue if store config is enabled
            if ($this->dataHelper->getOrderSyncEnabled((int) $order->getStoreId()) == false) {
                continue;
            }

            // Orders with no line items in Magento will not be pushed to the Doddle Purchases API
            if (!$order->getAllItems()) {
                continue;
            }

            $this->queueOrder((int) $order->getId());
            $totalQueued++;
        }

        return $totalQueued;
    }

    /**
     * Get orders to send to API based on criteria
     *
     * @return OrderCollection
     */
    private function getOrders(): OrderCollection
    {
        $orderCollection = $this->orderCollectionFactory->create();

        // Filter out cancelled orders
        $orderCollection->addFieldToFilter('state', ['neq' => Order::STATE_CANCELED]);

        // Filter out orders older than the limit specified in days
        $dateLimit = $this->getDateLimit();

        if ($dateLimit !== null) {
            $orderCollection->addFieldToFilter('main_table.created_at', ['gt' => $dateLimit]);
        }

        // Filter out orders already in the Doddle order queue
        $orderCollection->getSelect()->joinLeft(
            ['order_queue' => $orderCollection->getTable(DataHelper::DB_TABLE_ORDER_QUEUE)],
            'main_table.entity_id = order_queue.order_id',
            []
        )->where('order_queue.order_id IS NULL');

        return $orderCollection;
    }

    /**
     * Queue up the order if it is not already queued
     *
     * @param int $orderId
     */
    private function queueOrder(int $orderId): void
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
     * Get date limit based on request parameter
     *
     * @return string|null
     */
    private function getDateLimit(): ?string
    {
        $limit = (int) $this->_request->getParam('limit');

        if ($limit > 0) {
            $dateString = sprintf('%s -%d days', $this->dateTime->date('Y-m-d'), $limit);
            return $this->dateTime->date('Y-m-d', strtotime($dateString));
        }

        return null;
    }
}
