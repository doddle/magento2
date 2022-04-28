<?php
declare(strict_types=1);

namespace Doddle\Returns\Cron;

use Doddle\Returns\Api\Data\OrderQueueInterface;
use Doddle\Returns\Api\OrderQueueRepositoryInterface;
use Doddle\Returns\Helper\Api as ApiHelper;
use Doddle\Returns\Helper\Data as DataHelper;
use Doddle\Returns\Model\ResourceModel\OrderQueue\Collection as OrderQueueCollection;
use Doddle\Returns\Model\ResourceModel\OrderQueue\CollectionFactory as OrderQueueCollectionFactory;
use Doddle\Returns\Service\Api\Purchase as PurchaseApiService;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Psr\Log\LoggerInterface as PsrLoggerInterface;

class OrderSyncQueue
{
    /** @var PsrLoggerInterface */
    private $logger;

    /** @var DataHelper */
    private $dataHelper;

    /** @var ApiHelper */
    private $apiHelper;

    /** @var OrderQueueCollectionFactory */
    private $orderQueueCollectionFactory;

    /** @var OrderCollectionFactory */
    private $orderCollectionFactory;

    /** @var OrderQueueRepositoryInterface */
    private $orderQueueRepository;

    /** @var PurchaseApiService */
    private $purchaseApiService;

    /**
     * @param PsrLoggerInterface $logger
     * @param DataHelper $dataHelper
     * @param ApiHelper $apiHelper
     * @param OrderQueueCollectionFactory $orderQueueCollectionFactory
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param OrderQueueRepositoryInterface $orderQueueRepository
     * @param PurchaseApiService $purchaseApiService
     */
    public function __construct(
        PsrLoggerInterface $logger,
        DataHelper $dataHelper,
        ApiHelper $apiHelper,
        OrderQueueCollectionFactory $orderQueueCollectionFactory,
        OrderCollectionFactory $orderCollectionFactory,
        OrderQueueRepositoryInterface $orderQueueRepository,
        PurchaseApiService $purchaseApiService
    ) {
        $this->logger = $logger;
        $this->dataHelper = $dataHelper;
        $this->apiHelper = $apiHelper;
        $this->orderQueueCollectionFactory = $orderQueueCollectionFactory;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->orderQueueRepository = $orderQueueRepository;
        $this->purchaseApiService = $purchaseApiService;
    }

    /**
     * Cron function to process pending orders in queue, first in first out limited by configured batch size
     */
    public function processPendingOrders(): void
    {
        /** @var OrderQueueCollection $pendingOrders */
        $pendingOrders = $this->orderQueueCollectionFactory->create();

        $pendingOrders->addFieldToFilter(OrderQueueInterface::STATUS, OrderQueueInterface::STATUS_PENDING)
            ->setPageSize($this->dataHelper->getOrderSyncBatchSize())
            ->setCurPage(1)
            ->setOrder(OrderQueueInterface::CREATED_AT, OrderQueueCollection::SORT_ORDER_ASC);

        $this->pushOrders($pendingOrders);
    }

    /**
     * Cron function to retry failed orders in queue, first in first out limited by configured batch size
     */
    public function retryFailedOrders(): void
    {
        $maxFails = $this->dataHelper->getOrderSyncMaxFails();

        /** @var OrderQueueCollection $failedOrders */
        $failedOrders = $this->orderQueueCollectionFactory->create();

        $failedOrders->addFieldToFilter(OrderQueueInterface::STATUS, OrderQueueInterface::STATUS_FAILED)
            ->setPageSize($this->dataHelper->getOrderSyncBatchSize())
            ->setCurPage(1)
            ->setOrder(OrderQueueInterface::FAIL_COUNT, OrderQueueCollection::SORT_ORDER_ASC)
            ->setOrder(OrderQueueInterface::CREATED_AT, OrderQueueCollection::SORT_ORDER_ASC);

        // Allow for infinite retries where max tries config is set to 0
        if ($maxFails > 0) {
            $failedOrders->addFieldToFilter('fail_count', ['lteq' => $this->dataHelper->getOrderSyncMaxFails()]);
        }

        $this->pushOrders($failedOrders);
    }

    /**
     * Push orders cron function
     *
     * @param OrderQueueCollection $orderQueue
     */
    private function pushOrders(OrderQueueCollection $orderQueue): void
    {
        $orderIds = $orderQueue->getColumnValues(OrderQueueInterface::ORDER_ID);

        /** @var OrderCollection $orderCollection */
        $orderCollection = $this->orderCollectionFactory->create();

        $orderCollection->addAttributeToFilter(
            $orderCollection->getResource()->getIdFieldName(),
            ['in' => $orderIds]
        );

        $companyId = $this->dataHelper->getCompanyId();

        /** @var OrderQueueInterface $queuedOrder */
        foreach ($orderQueue as $queuedOrder) {
            $order = $orderCollection->getItemById($queuedOrder->getOrderId());

            if ($this->validateOrderForPush($order) === false) {
                // Skip any orders deemed invalid for push
                continue;
            }

            $purchase = $this->purchaseApiService->getPurchaseData($order);

            try {
                $response = $this->apiHelper->postRequest(
                    PurchaseApiService::API_PATH,
                    $purchase,
                    sprintf('%s organisation_%s', PurchaseApiService::API_SCOPE, $companyId)
                );
            } catch (\Exception $e) {
                $this->logger->error(
                    sprintf(
                        '(Magento Order ID: %s) %s',
                        $queuedOrder->getOrderId(),
                        $e->getMessage()
                    )
                );
            }

            if (isset($response['resource'])) {
                $queuedOrder->setStatus(OrderQueueInterface::STATUS_SYNCHED);
            } else {
                $queuedOrder->setStatus(OrderQueueInterface::STATUS_FAILED);
                $queuedOrder->setFailCount($queuedOrder->getFailCount() + 1);
            }

            $this->orderQueueRepository->save($queuedOrder);
        }
    }

    /**
     * Validate orders to prevent invalid orders being pushed
     *
     * @param OrderInterface $order
     * @return bool
     */
    private function validateOrderForPush(OrderInterface $order): bool
    {
        // Don't push cancelled orders
        if ($order->getState() == Order::STATE_CANCELED) {
            return false;
        }

        // Only push order if sync store config is enabled
        if ($this->dataHelper->getOrderSyncEnabled((int) $order->getStoreId()) == false) {
            return false;
        }

        return true;
    }
}
