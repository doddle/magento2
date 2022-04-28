<?php
declare(strict_types=1);

namespace Doddle\Returns\Model\Product;

use Doddle\Returns\Api\Data\Product\VariationAttributeInterface;
use Doddle\Returns\Api\Data\Product\VariationAttributeInterfaceFactory;
use Doddle\Returns\Api\Data\Product\VariationInterface;
use Doddle\Returns\Api\Data\Product\VariationInterfaceFactory;
use Doddle\Returns\Api\ProductVariationListInterface;
use Doddle\Returns\Helper\Data as DataHelper;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\ConfigurableProduct\Api\Data\OptionInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable\Attribute\Collection;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Model\StoreManagerInterface;

class VariationProvider implements ProductVariationListInterface
{
    /** @var VariationInterfaceFactory */
    private $productVariationFactory;

    /** @var ProductRepositoryInterface */
    private $productRepository;

    /** @var ConfigurableType */
    private $configurableType;

    /** @var VariationAttributeInterfaceFactory */
    private $variationAttributeFactory;

    /** @var StockRegistryInterface */
    private $stockRegistry;

    /** @var SearchCriteriaBuilder */
    private $searchCriteriaBuilder;

    /** @var FilterGroupBuilder */
    private $filterGroupBuilder;

    /** @var FilterBuilder */
    private $filterBuilder;

    /** @var ProductStatus */
    private $productStatus;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var ModuleManager */
    private $moduleManager;

    /** @var ProductInterface */
    private $parentProduct;

    /** @var array */
    private $superAttributes;

    /** @var array */
    private $superAttributeCodes;

    /** @var int */
    private $stockId;

    /**
     * @param VariationInterfaceFactory $productVariationFactory
     * @param VariationAttributeInterfaceFactory $variationAttributeFactory
     * @param ProductRepositoryInterface $productRepository
     * @param ConfigurableType $configurableType
     * @param StockRegistryInterface $stockRegistry
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     * @param FilterBuilder $filterBuilder
     * @param ProductStatus $productStatus
     * @param StoreManagerInterface $storeManager
     * @param ModuleManager $moduleManager
     */
    public function __construct(
        VariationInterfaceFactory $productVariationFactory,
        VariationAttributeInterfaceFactory $variationAttributeFactory,
        ProductRepositoryInterface $productRepository,
        ConfigurableType $configurableType,
        StockRegistryInterface $stockRegistry,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterGroupBuilder $filterGroupBuilder,
        FilterBuilder $filterBuilder,
        ProductStatus $productStatus,
        StoreManagerInterface $storeManager,
        ModuleManager $moduleManager
    ) {
        $this->productVariationFactory = $productVariationFactory;
        $this->variationAttributeFactory = $variationAttributeFactory;
        $this->productRepository = $productRepository;
        $this->configurableType = $configurableType;
        $this->stockRegistry = $stockRegistry;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterGroupBuilder = $filterGroupBuilder;
        $this->filterBuilder = $filterBuilder;
        $this->productStatus = $productStatus;
        $this->storeManager = $storeManager;
        $this->moduleManager = $moduleManager;
    }

    /**
     * @inheritdoc
     */
    public function getItems($sku): array
    {
        $output = [];

        $product = $this->productRepository->get($sku, false);
        $collection = $this->getSiblingsCollection($product);

        foreach ($collection as $product) {
            $productVariation = $this->prepareProductForResponse($product);
            $output[] = $productVariation;
        }

        return $output;
    }

    /**
     * Get sibling products collection
     *
     * @param ProductInterface $product
     * @return array
     * @throws NoSuchEntityException
     */
    private function getSiblingsCollection(ProductInterface $product): array
    {
        // Filter collection by sibling product IDs
        $siblingsIds = $this->getSiblingProductIds($product);

        if (count($siblingsIds) < 1) {
            return [];
        }

        $searchCriteria = $this->searchCriteriaBuilder->create();

        $searchCriteria->setFilterGroups([
            $this->filterGroupBuilder->setFilters([
                $this->filterBuilder
                    ->setField('entity_id')
                    ->setConditionType('in')
                    ->setValue($siblingsIds)
                    ->create()
            ])->create(),
            $this->filterGroupBuilder->setFilters([
                $this->filterBuilder
                    ->setField('status')
                    ->setConditionType('in')
                    ->setValue($this->productStatus->getVisibleStatusIds())
                    ->create()
            ])->create()
        ]);

        $products = $this->productRepository->getList($searchCriteria);

        return $products->getItems();
    }

    /**
     * Prepare product for response
     *
     * @param ProductInterface $product
     * @return VariationInterface
     */
    private function prepareProductForResponse(ProductInterface $product): VariationInterface
    {
        /** @var VariationInterface $productVariant */
        $productVariant = $this->productVariationFactory->create();

        $productVariant->setEntityId($product->getId());
        $productVariant->setSku($product->getSku());
        $productVariant->setName($product->getName());
        $productVariant->setImageUrl($this->getProductImageUrl($product));
        $productVariant->setReturnsExcluded($product->getData(DataHelper::ATTRIBUTE_CODE_RETURNS_ELIGIBILITY));
        $productVariant->setAttributes($this->getAttributes($product));

        // Add stock quantity, or null if stock management disabled, using MSI or legacy stock model
        if ($this->isMSIEnabled()) {
            $productVariant->setStock($this->getProductStock($product));
        } else {
            $productVariant->setStock($this->getLegacyProductStock($product));
        }

        return $productVariant;
    }

    /**
     * Check if MSI is enabled and all required code interfaces are present
     *
     * @return bool
     */
    private function isMSIEnabled(): bool
    {
        return $this->moduleManager->isEnabled('Magento_Inventory') &&
            interface_exists(GetProductSalableQtyInterface::class) &&
            interface_exists(StockResolverInterface::class) &&
            interface_exists(SalesChannelInterface::class);
    }

    /**
     * Get the MSI (Magento > 2.3.0) stock quantity for a product.
     *
     * @param ProductInterface $product
     * @return float|null
     */
    private function getProductStock(ProductInterface $product): ?float
    {
        /** @var GetProductSalableQtyInterface $getProductSalableQty */
        $getProductSalableQty = ObjectManager::getInstance()->get(GetProductSalableQtyInterface::class);

        try {
            $qty = $getProductSalableQty->execute($product->getSku(), $this->getMSIStockId());
        } catch (\Exception $e) {
            // Set default value to NULL (stock not managed).
            $qty = null;
        }

        return $qty;
    }

    /**
     * Get MSI stock ID for the current website's channel (based on Base URL of API request)
     *
     * @return int|null
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function getMSIStockId()
    {
        if (!$this->stockId) {
            $websiteCode = $this->storeManager->getWebsite()->getCode();

            /** @var StockResolverInterface $stockResolver */
            $stockResolver = ObjectManager::getInstance()->get(StockResolverInterface::class);

            $this->stockId = $stockResolver->execute(SalesChannelInterface::TYPE_WEBSITE, $websiteCode)->getStockId();
        }

        return $this->stockId;
    }

    /**
     * Get the legacy (Magento < 2.3.0) stock quantity for a product.
     *
     * @param ProductInterface $product
     * @return float|null
     */
    private function getLegacyProductStock(ProductInterface $product): ?float
    {
        // Set default value to NULL (stock not managed).
        $qty = null;

        $stockItem = $this->stockRegistry->getStockItem($product->getId());
        if ($stockItem->getManageStock() == true) {
            $qty = $stockItem->getQty();
        }

        return $qty;
    }

    /**
     * Get sibling product IDs
     *
     * @param ProductInterface $product
     * @return array
     * @throws NoSuchEntityException
     */
    private function getSiblingProductIds(ProductInterface $product): array
    {
        $parentProduct = $this->getParentProduct((int) $product->getId());
        $siblingIds = $this->configurableType->getChildrenIds($parentProduct->getId());

        $siblingIds = reset($siblingIds);

        return array_keys($siblingIds);
    }

    /**
     * Get parent product
     *
     * @param int $productId
     * @return ProductInterface
     * @throws NoSuchEntityException
     */
    private function getParentProduct(int $productId): ProductInterface
    {
        if (!isset($this->parentProduct)) {
            $parentIds = $this->configurableType->getParentIdsByChild($productId);

            if (count($parentIds)  < 1) {
                throw new NoSuchEntityException(__('Parent product not found.'));
            }

            // Get most recent parent association
            $parentId = end($parentIds);
            $this->parentProduct = $this->productRepository->getById($parentId);
        }

        return $this->parentProduct;
    }

    /**
     * Get product's attributes
     *
     * @param ProductInterface $product
     * @return array
     */
    private function getAttributes(ProductInterface $product): array
    {
        $attributes = [];
        $superAttributes = $this->getSuperAttributes($product);

        /** @var OptionInterface $attribute */
        foreach ($superAttributes as $attribute) {
            $attributes[] = $this->variationAttributeFactory->create(['data' => [
                VariationAttributeInterface::LABEL       => $attribute->getLabel(),
                VariationAttributeInterface::VALUE       => $product->getAttributeText(
                    $attribute->getProductAttribute()->getAttributeCode()
                ),
                VariationAttributeInterface::VALUE_INDEX => $product->getData(
                    $attribute->getProductAttribute()->getAttributeCode()
                )
            ]]);
        }

        return $attributes;
    }

    /**
     * Get super attribute codes
     *
     * @param ProductInterface $product
     * @return array
     * @throws NoSuchEntityException
     */
    private function getSuperAttributeCodes(ProductInterface $product): array
    {
        if (!isset($this->superAttributeCodes)) {
            $superAttributes = $this->getSuperAttributes($product);
            foreach ($superAttributes as $superAttribute) {
                $this->superAttributeCodes[] = $superAttribute->getProductAttribute()->getAttributeCode();
            }
        }

        return $this->superAttributeCodes;
    }

    /**
     * Get super attributes
     *
     * @param ProductInterface $product
     * @return array|Collection
     * @throws NoSuchEntityException
     */
    private function getSuperAttributes(ProductInterface $product)
    {
        if (!isset($this->superAttributes)) {
            $parentProduct = $this->getParentProduct((int) $product->getId());
            $this->superAttributes = $parentProduct->getTypeInstance()->getConfigurableAttributes($parentProduct);
        }

        return $this->superAttributes;
    }

    /**
     * Retrieve the main image url of the variation product, or the parent product if no image applied to the variation
     *
     * @param ProductInterface $product
     * @return string
     */
    private function getProductImageUrl(ProductInterface $product): string
    {
        $image = $product->getImage();

        // Get the parent product's image if variation has no image set
        if (!$image || $image == 'no_selection') {
            $image = $this->getParentProduct((int) $product->getId())->getImage();
        }

        return (string) $product->getMediaConfig()->getMediaUrl($image);
    }
}
