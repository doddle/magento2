<?php
declare(strict_types=1);

namespace Doddle\Returns\Service\Api;

use Doddle\Returns\Helper\Data as DataHelper;
use Doddle\Returns\Helper\ValidateField;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\ResourceModel\Category\Collection as CategoryCollection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Store\Model\StoreManagerInterface;

class Purchase
{
    public const API_PATH = '/v1/purchases/';
    public const API_SCOPE = 'purchases:write';
    public const MAX_NAME_LENGTH = 60; // Max length for product name in Purchases API
    public const MAX_SKU_LENGTH = 255; // Max length for SKU in Purchases API

    /** @var DataHelper */
    private $dataHelper;

    /** @var ImageHelper */
    private $imageHelper;

    /** @var ValidateField */
    private $validate;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var CategoryCollectionFactory */
    private $categoryCollectionFactory;

    /** @var PriceCurrencyInterface */
    private $priceCurrency;

    /** @var array */
    private $productCategories = [];

    /** @var array */
    private $productAttributes = [];

    /** @var array */
    private $productImages = [];

    /** @var array */
    private $storeRootCategoryIds = [];

    /**
     * @param DataHelper $dataHelper
     * @param ImageHelper $imageHelper
     * @param ValidateField $validate
     * @param StoreManagerInterface $storeManager
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param PriceCurrencyInterface $priceCurrency
     */
    public function __construct(
        DataHelper $dataHelper,
        ImageHelper $imageHelper,
        ValidateField $validate,
        StoreManagerInterface $storeManager,
        CategoryCollectionFactory $categoryCollectionFactory,
        PriceCurrencyInterface $priceCurrency
    ) {
        $this->dataHelper = $dataHelper;
        $this->imageHelper = $imageHelper;
        $this->validate = $validate;
        $this->storeManager = $storeManager;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->priceCurrency = $priceCurrency;
    }

    /**
     * Compile purchase data
     *
     * @param OrderInterface $order
     * @return array
     */
    public function getPurchaseData(OrderInterface $order): array
    {
        $purchaseData = [
            'companyId' => $this->validate->string($this->dataHelper->getCompanyId()),
            'externalOrderId' => $this->validate->string($order->getIncrementId()),
            'purchaseDate' => date('Y-m-d', strtotime($order->getCreatedAt())),
            'orderLines' => $this->getOrderLineData($order),
            'customer' => $this->getCustomerData($order),
            'deliveryAddress' => $this->getDeliveryData($order)
        ];

        return $purchaseData;
    }

    /**
     * Compile purchase update data
     *
     * @param OrderInterface $order
     * @return array
     */
    public function getPurchaseUpdateData(OrderInterface $order): array
    {
        $purchaseData = [
            'deliveryAddress' => $this->getDeliveryData($order)
        ];

        return $purchaseData;
    }

    /**
     * Compile order line data
     *
     * @param OrderInterface $order
     * @return array
     */
    private function getOrderLineData(OrderInterface $order): array
    {
        $orderLines = [];
        $storeId = (int) $order->getStoreId();

        /** @var OrderItemInterface $orderLine */
        foreach ($order->getAllVisibleItems() as $orderLine) {
            /** @var ProductInterface $product */
            $product = $orderLine->getProduct()->setStoreId($storeId);

            if (!$product) {
                // Skip if product no longer exists
                continue;
            }

            // Decimal quantities must be replaced with '1' in Purchases API.
            $quantity = $this->dataHelper->hasDecimals($orderLine->getQtyOrdered()) ? 1 : $orderLine->getQtyOrdered();

            $orderLineData = [
                'orderLineId' => sprintf('%s%s', $order->getIncrementId(), $orderLine->getItemId()),
                'productName' => $this->validate->string(substr($orderLine->getName(), 0, self::MAX_NAME_LENGTH)),
                'productUrl' => $this->getProductUrl($product),
                'isNotReturnable' => (bool) $product->getData('doddle_returns_excluded'),
                'quantity' => (float) $quantity,
                'sku' => substr($orderLine->getSku(), 0, self::MAX_SKU_LENGTH)
            ];

            if ($orderLine->getPrice() !== null) {
                $orderLineData['price'] = $this->formatPrice((float) $orderLine->getPrice(), $storeId);
                $orderLineData['priceCurrency'] = (string) $order->getOrderCurrencyCode();
            }

            $categories = $this->getProductCategories($product);
            if (!empty($categories)) {
                $orderLineData['categories'] = $categories;
            }

            $attributes = $this->getProductAttributes($product);

            // Merge in configurable child product attributes if available
            if ($orderLine->getProductType() == Configurable::TYPE_CODE) {
                $childAttributes = [];
                foreach ($orderLine->getChildrenItems() as $childItem) {
                    $childProduct = $childItem->getProduct()->setStoreId($storeId);
                    $childAttributes = $this->getProductAttributes($childProduct);
                }

                $attributes = array_merge($attributes, $childAttributes);
            }

            if (!empty($attributes)) {
                $orderLineData['attributes'] = $attributes;
            }

            $imageUrl = $this->getProductImageUrl($product);
            if ($imageUrl !== null) {
                $orderLineData['imageUrl'] = $imageUrl;
            }

            $orderLines[] = $orderLineData;
        }

        return $orderLines;
    }

    /**
     * Remove non-numerics (currency, decimal separator etc) from price and convert to int
     *
     * @param float $price
     * @param int $storeId
     * @return int
     */
    private function formatPrice(float $price, int $storeId): int
    {
        // Convert float to formatted currency for store so as to include correct decimal places
        $price = $this->priceCurrency->format(
            $price,
            false,
            PriceCurrencyInterface::DEFAULT_PRECISION,
            $storeId
        );

        // Return only the integers of the price
        return (int) preg_replace('/\D/', '', $price);
    }

    /**
     * Retrieve product's URL for relevant store
     *
     * @param ProductInterface $product
     * @return string
     */
    private function getProductUrl(ProductInterface $product): string
    {
        $url = $product->getUrlModel()->getProductUrl($product);

        // Return URL with ___store param removed
        return preg_replace('/\?___store.*/', '', $url);
    }

    /**
     * Compile category name data
     *
     * @param ProductInterface $product
     * @return array
     */
    private function getProductCategories(ProductInterface $product): array
    {
        $storeId = (int) $product->getStoreId();
        $productId = (int) $product->getEntityId();

        // Return cached result if set
        if (isset($this->productCategories[$storeId][$productId])) {
            return $this->productCategories[$storeId][$productId];
        }

        $categories = [];
        $collection = $this->getCategoryCollection($product, $storeId);

        /** @var CategoryInterface $category */
        foreach ($collection as $category) {
            $categories[] = $category->getName();
        }

        // Cache result incase requested again
        $this->productCategories[$storeId][$productId] = $categories;

        return $categories;
    }

    /**
     * Build the category collection for a given product in a given store.
     *
     * @param ProductInterface $product
     * @param int $storeId
     * @return CategoryCollection|null
     */
    private function getCategoryCollection(ProductInterface $product, int $storeId): ?CategoryCollection
    {
        // Get the root category ID of the relevant store to limit the collection
        $rootCategoryId = $this->getRootCategoryId($storeId);

        if (!$rootCategoryId) {
            return null;
        }

        $collection = $product->getResource()->getCategoryCollection($product);

        // Add name to select and only return sub categories of the product store's root category
        $collection->setStoreId($storeId)
            ->addAttributeToSelect('name', true)
            ->addPathFilter('./' . $rootCategoryId . '/*');

        return $collection;
    }

    /**
     * Retrieve the root category ID of the supplied store
     *
     * @param int $storeId
     * @return int|null
     */
    private function getRootCategoryId(int $storeId): ?int
    {
        if (!isset($storeRootCategoryIds[$storeId])) {
            try {
                $rootCategoryId = (int) $this->storeManager->getStore($storeId)->getGroup()->getRootCategoryId();
            } catch (NoSuchEntityException $e) {
                $rootCategoryId = null;
            }

            $storeRootCategoryIds[$storeId] = $rootCategoryId;
        }

        return $storeRootCategoryIds[$storeId];
    }

    /**
     * Compile product attribute data
     *
     * @param ProductInterface $product
     * @return array
     */
    private function getProductAttributes(ProductInterface $product): array
    {
        $storeId = (int) $product->getStoreId();
        $productId = (int) $product->getEntityId();

        // Return cached result if set
        if (!isset($this->productAttributes[$storeId][$productId])) {
            $attributes = [];

            // Add Size attribute value, if available
            $size = $this->getProductAttributeValue($product, 'size');
            if ($size) {
                $attributes['size'] = $size;
            }

            // Add Weight attribute value, if available
            $weight = $this->getProductAttributeValue($product, 'weight');
            if ($weight) {
                $attributes['weight'] = (float) $weight;
            }

            // Add Colour/Color attribute value, if available
            $color = $this->getProductAttributeValue($product, 'color');
            $colour = $this->getProductAttributeValue($product, 'colour');

            if ($color || $colour) {
                $attributes['color'] = $color ?? $colour;
            }

            // Add Length/Width/Height attribute values, if any available
            $length = $this->getProductAttributeValue($product, 'length');
            $width = $this->getProductAttributeValue($product, 'width');
            $height = $this->getProductAttributeValue($product, 'height');
            if ($length || $width || $height) {
                $attributes['dimensions']['length'] = $this->validate->number($length);
                $attributes['dimensions']['width'] = $this->validate->number($width);
                $attributes['dimensions']['height'] = $this->validate->number($height);
            }

            // Cache result incase requested again
            $this->productAttributes[$storeId][$productId] = $attributes;
        }

        return $this->productAttributes[$storeId][$productId];
    }

    /**
     * Get product attribute real value (not the ID of a dropdown option) for relevant store
     *
     * @param ProductInterface $product
     * @param string $attributeCode
     * @return bool|mixed|string|null
     */
    private function getProductAttributeValue(ProductInterface $product, string $attributeCode)
    {
        $attribute = $product->getResource()->getAttribute($attributeCode);

        if (!$attribute) {
            // Exit early if attribute doesn't exist
            return null;
        }

        $storeId = $product->getStoreId();
        $attribute->setStoreId($storeId);
        $value = $product->getResource()->getAttributeRawValue($product->getId(), $attributeCode, $storeId);

        if (in_array($attribute->getFrontendInput(), ['select', 'multiselect'])) {
            if (is_array($value)) {
                if (!empty($value)) {
                    foreach ($value as $key => $val) {
                        $value[$key] = $attribute->getSource()->getOptionText($val);

                        if (is_array($value[$key])) {
                            $value[$key] = $value[$key]['label'];
                        }
                    }
                    $value = implode(', ', $value);
                } else {
                    $value = null;
                }
            } else {
                $value = $attribute->getSource()->getOptionText($value);
                if (is_array($value)) {
                    $value = $value['label'];
                }
            }
        } elseif ($attribute->getFrontendInput() == 'boolean') {
            $value = (bool) $value;
        }

        return $value;
    }

    /**
     * Retrieve product's main image URL for relevant store
     *
     * @param ProductInterface $product
     * @return string|null
     */
    private function getProductImageUrl(ProductInterface $product): ?string
    {
        if (!$product->getImage()) {
            return null;
        }

        $storeId = (int) $product->getStoreId();
        $productId = (int) $product->getEntityId();

        if (!isset($this->productImages[$storeId][$productId])) {
            try {
                $store = $this->storeManager->getStore($product->getStoreId());
            } catch (NoSuchEntityException $e) {
                return null;
            }

            // @see \Magento\Catalog\Model\Product\Media\Config::_prepareFile
            $imageFile = ltrim(str_replace('\\', '/', $product->getImage()), '/');

            $this->productImages[$storeId][$productId] = sprintf(
                '%scatalog/product/%s',
                $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA),
                $imageFile
            );
        }

        return $this->productImages[$storeId][$productId];
    }

    /**
     * Compile customer data
     *
     * @param OrderInterface $order
     * @return array
     */
    private function getCustomerData(OrderInterface $order): array
    {
        $customer = [
            'email' => $this->validate->string($order->getCustomerEmail())
        ];

        $firstName = $order->getCustomerFirstname() ? $order->getCustomerFirstname() : 'Guest';
        $customer['name']['firstName'] = $this->validate->string($firstName);

        if ($order->getCustomerLastname()) {
            $customer['name']['lastName'] = $order->getCustomerLastname();
        }

        // Add telephone number if set
        if ($order->getBillingAddress()->getTelephone()) {
            $customer['mobileNumber'] = $order->getBillingAddress()->getTelephone();
        }

        return $customer;
    }

    /**
     * Compile delivery data
     *
     * @param OrderInterface $order
     * @return array
     */
    private function getDeliveryData(OrderInterface $order): array
    {
        $deliveryData = $this->getSkeletonAddress();
        $shippingAddress = $order->getShippingAddress();

        if (!$shippingAddress) {
            // Return empty address skeleton for orders with no shipping address (likely virtual orders)
            return $deliveryData;
        }

        $deliveryData['town'] = $this->validate->string($shippingAddress->getCity());
        $deliveryData['postcode'] = $this->validate->string($shippingAddress->getPostcode());
        $deliveryData['country'] = $this->validate->string($shippingAddress->getCountryId());

        // Add area to address only if set in Magento order
        if ($shippingAddress->getRegion()) {
            $deliveryData['area'] = $shippingAddress->getRegion();
        }

        foreach ($shippingAddress->getStreet() as $index => $streetLine) {
            if ($streetLine) {
                $deliveryData['line' . ($index + 1)] = $this->validate->string($streetLine);
            }
        }

        return $deliveryData;
    }

    /**
     * Provide a valid empty address data array
     *
     * @return array
     */
    private function getSkeletonAddress(): array
    {
        return [
            'town' => $this->validate->string(),
            'postcode' => $this->validate->string(),
            'country' => '  ', // API enforces minimum 2 chars here
            'line1' => $this->validate->string()
        ];
    }
}
