<?php
declare(strict_types=1);

namespace Doddle\Returns\Cron;

use Psr\Log\LoggerInterface as PsrLoggerInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Catalog\Helper\Image as imageHelper;
use Doddle\Returns\Helper\Data as DataHelper;
use Doddle\Returns\Helper\Api as ApiHelper;
use Doddle\Returns\Model\ResourceModel\OrderQueue\CollectionFactory as OrderQueueCollectionFactory;
use Doddle\Returns\Model\ResourceModel\OrderQueue\Collection as OrderQueueCollection;
use Doddle\Returns\Api\OrderQueueRepositoryInterface;
use Doddle\Returns\Api\Data\OrderQueueInterface;

class OrderSyncQueue
{
    /**
     * @var PsrLoggerInterface
     */
    private $logger;

    /** @var DataHelper */
    private $dataHelper;

    /** @var ApiHelper */
    private $apiHelper;

    /** @var imageHelper */
    private $imageHelper;

    /** @var OrderQueueCollectionFactory */
    private $orderQueueCollectionFactory;

    /** @var OrderCollectionFactory */
    private $orderCollectionFactory;

    /** @var OrderQueueRepositoryInterface */
    private $orderQueueRepository;

    /**
     * @param PsrLoggerInterface $logger
     * @param DataHelper $dataHelper
     * @param ApiHelper $apiHelper
     * @param imageHelper $imageHelper
     * @param OrderQueueCollectionFactory $orderQueueCollectionFactory
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param OrderQueueRepositoryInterface $orderQueueRepository
     */
    public function __construct(
        PsrLoggerInterface $logger,
        DataHelper $dataHelper,
        ApiHelper $apiHelper,
        ImageHelper $imageHelper,
        OrderQueueCollectionFactory $orderQueueCollectionFactory,
        OrderCollectionFactory $orderCollectionFactory,
        OrderQueueRepositoryInterface $orderQueueRepository
    ) {
        $this->logger = $logger;
        $this->dataHelper = $dataHelper;
        $this->apiHelper = $apiHelper;
        $this->imageHelper = $imageHelper;
        $this->orderQueueCollectionFactory = $orderQueueCollectionFactory;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->orderQueueRepository = $orderQueueRepository;
    }

    /**
     * Cron function to process pending orders in queue, first in first out limited by configured batch size
     */
    public function processPendingOrders()
    {
        /** @var OrderQueueCollection $pendingOrders */
        $pendingOrders = $this->orderQueueCollectionFactory->create();

        $pendingOrders->addFieldToFilter('status', OrderQueueInterface::STATUS_PENDING)
            ->setPageSize($this->dataHelper->getOrderSyncBatchSize())
            ->setCurPage(1)
            ->setOrder('created_at', OrderQueueCollection::SORT_ORDER_ASC);

        $this->pushOrders($pendingOrders);
    }

    /**
     * Cron function to retry failed orders in queue, first in first out limited by configured batch size
     */
    public function retryFailedOrders()
    {
        $maxFails = $this->dataHelper->getOrderSyncMaxFails();

        /** @var OrderQueueCollection $failedOrders */
        $failedOrders = $this->orderQueueCollectionFactory->create();

        $failedOrders->addFieldToFilter('status', OrderQueueInterface::STATUS_FAILED)
            ->setPageSize($this->dataHelper->getOrderSyncBatchSize())
            ->setCurPage(1)
            ->setOrder('created_at', OrderQueueCollection::SORT_ORDER_ASC);

        // Allow for infinate retries where max tries config is set to 0
        if ($maxFails > 0) {
            $failedOrders->addFieldToFilter('fail_count', ['lteq' => $this->dataHelper->getOrderSyncMaxFails()]);
        }

        $this->pushOrders($failedOrders);
    }

    /**
     * @param OrderQueueCollection $orderQueue
     */
    private function pushOrders(OrderQueueCollection $orderQueue)
    {
        $orderIds = $orderQueue->getColumnValues('order_id');

        /** @var OrderCollection $orderCollection */
        $orderCollection = $this->orderCollectionFactory->create();

        $orderCollection->addAttributeToFilter(
            $orderCollection->getResource()->getIdFieldName(),
            ['in' => $orderIds]
        );

        /** @var OrderQueueInterface $queuedOrder */
        foreach ($orderQueue as $queuedOrder) {
            $order = $orderCollection->getItemById($queuedOrder->getOrderId());

            // Only push order if sync store config is enabled
            if ($this->dataHelper->getOrderSyncEnabled((int) $order->getStoreId()) == false) {
                continue;
            }

            $orderData = $this->formatOrderForApi($order);

            // Ensure failed response logic is followed if error occurs contacting Doddle API
            $response = false;

            try {
                $response = $this->apiHelper->sendOrder($orderData);
            } catch (\Exception $e) {
                $this->logger->error(
                    sprintf(
                        '(Magento Order ID: %s) %s',
                        $queuedOrder->getOrderId(),
                        $e->getMessage()
                    )
                );
            }

            if ($response !== false) {
                $queuedOrder->setDoddleOrderId($response);
                $queuedOrder->setStatus(OrderQueueInterface::STATUS_SYNCHED);
            } else {
                $queuedOrder->setStatus(OrderQueueInterface::STATUS_FAILED);
                $queuedOrder->setFailCount($queuedOrder->getFailCount() + 1);
            }

            $this->orderQueueRepository->save($queuedOrder);
        }
    }

    /**
     * @param OrderInterface $order
     * @return array
     */
    private function formatOrderForApi(OrderInterface $order)
    {
        $orderData = [
            "companyId" => $this->dataHelper->getCompanyId(),
            "externalOrderId" => $order->getIncrementId(),
            "orderType" => "EXTERNAL",
            "externalOrderData" => [
                "purchaseDate" => date('d-m-Y', strtotime($order->getCreatedAt()))
            ],
            "customer" => [
                "email" => $order->getCustomerEmail(),
                "name" => $this->getCustomerName($order)
            ]
        ];

        // Add telephone number if set
        if ($order->getBillingAddress()->getTelephone()) {
            $orderData["customer"]["mobileNumber"] = $order->getBillingAddress()->getTelephone();
        }

        // Add delivery address for physical orders only
        if (!$order->getIsVirtual()) {
            $orderData['externalOrderData']['deliveryAddress'] = $this->formatShippingAddress(
                $order->getShippingAddress()
            );
        }

        /** @var OrderItemInterface $orderLine */
        foreach ($order->getAllVisibleItems() as $orderLine) {
            $orderLineData = [
                "package" => [
                    "labelValue" => sprintf('%s-%s', $order->getIncrementId(), $orderLine->getId()),
                    "weight" => (float) $orderLine->getRowWeight()
                ],
                "product" => [
                    "description" => $orderLine->getName(),
                    "sku" => $orderLine->getSku(),
                    "price" => (float) $orderLine->getPrice(),
                    "imageUrl" => $this->getProductImageUrl($orderLine->getProduct()),
                    "quantity" => (int) $orderLine->getQtyOrdered(),
                    // "isNotReturnable" => (bool) $orderLine->getProduct()->getData("doddle_returns_excluded")
                ],
                "sourceLocation" => [],
                "destinationLocation" => [
                    "locationType" => "external"
                ],
                "fulfilmentMethod" => "NONE"
            ];

            // Add size attribute if available
            if ($orderLine->getProduct()->getSize()) {
                $orderLineData['product']['size'] = $orderLine->getProduct()->getSize();
            }

            // Add colour attribute if available
            if ($orderLine->getProduct()->getColor() || $orderLine->getProduct()->getColour()) {
                $orderLineData['product']['colour'] = $orderLine->getProduct()->getColor() ?
                    $orderLine->getProduct()->getColor() :
                    $orderLine->getProduct()->getColour();
            }

            $orderData['orderLines'][] = $orderLineData;
        }

        return $orderData;
    }

    /**
     * @param OrderInterface $order
     * @return string[]
     */
    private function getCustomerName(OrderInterface $order)
    {
        $customerName = [
            "firstName" => $order->getCustomerFirstname() ? $order->getCustomerFirstname() : "Guest"
        ];

        if ($order->getCustomerLastname()) {
            $customerName["lastName"] = $order->getCustomerLastname();
        }

        return $customerName;
    }

    /**
     * @param $product
     * @return mixed
     */
    private function getProductImageUrl($product)
    {
        return $product->getMediaConfig()->getMediaUrl($product->getImage());
    }

    /**
     * @param OrderAddressInterface $shippingAddress
     * @return array
     */
    private function formatShippingAddress(OrderAddressInterface $shippingAddress)
    {
        $formattedAddress = [
            "town" => $shippingAddress->getCity(),
            "postcode" => $shippingAddress->getPostcode() ? $shippingAddress->getPostcode() : 'n/a',
            "country" => $shippingAddress->getCountryId()
        ];

        // Add area to address only if set in Magento order
        if ($shippingAddress->getRegion()) {
            $formattedAddress["area"] = $shippingAddress->getRegion();
        }

        foreach ($shippingAddress->getStreet() as $index => $streetLine) {
            if ($streetLine) {
                $formattedAddress["line" . ($index + 1)] = $streetLine;
            }
        }

        return $formattedAddress;
    }
}
