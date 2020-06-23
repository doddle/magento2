<?php
declare(strict_types=1);

namespace Doddle\Returns\Model\Product;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\ConfigurableProduct\Api\Data\OptionInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;
use Magento\InventoryApi\Api\GetSourceItemsBySkuInterface;
use Magento\Inventory\Model\SourceItem;
use Doddle\Returns\Api\Data\Product\VariationAttributeInterface;
use Doddle\Returns\Api\Data\Product\VariationAttributeInterfaceFactory;
use Doddle\Returns\Api\Data\Product\VariationInterface;
use Doddle\Returns\Api\Data\Product\VariationInterfaceFactory;
use Doddle\Returns\Api\ProductVariationListInterface;
use Doddle\Returns\Helper\Data as DataHelper;

class VariationProvider implements ProductVariationListInterface
{
    private $parentProduct;
    private $superAttributes;
    private $superAttributeCodes;

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
        ProductStatus $productStatus
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
    }

    /**
     * {@inheritdoc}
     */
    public function getItems($sku)
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
     * @param ProductInterface $product
     * @return ProductCollection
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function getSiblingsCollection(ProductInterface $product)
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
     * @param ProductInterface $product
     * @return VariationInterface
     */
    private function prepareProductForResponse(ProductInterface $product)
    {
        /** @var VariationInterface $productVariation */
        $productVariant = $this->productVariationFactory->create();

        $productVariant->setEntityId($product->getId());
        $productVariant->setSku($product->getSku());
        $productVariant->setName($product->getName());
        $productVariant->setImageUrl($this->getProductImageUrl($product));
        $productVariant->setReturnsExcluded($product->getData(DataHelper::ATTRIBUTE_CODE_RETURNS_ELIGIBILITY));
        $productVariant->setAttributes($this->getAttributes($product));

        // Add stock quantity, or null if stock management disabled, using MSI or legacy stock model
        if (interface_exists(GetSourceItemsBySkuInterface::class)) {
            $productVariant->setStock($this->getProductStock($product));
        } else {
            $productVariant->setStock($this->getLegacyProductStock($product));
        }

        return $productVariant;
    }

    /**
     * Get the MSI (Magento > 2.3.0) stock quantity for a product.
     *
     * @param ProductInterface $product
     * @return float|int|null
     */
    private function getProductStock(ProductInterface $product)
    {
        // Set default value to "false" (stock not managed).
        $qty = null;

        /** @var GetSourceItemsBySkuInterface $getSourceItemsBySku */
        $getSourceItemsBySku = ObjectManager::getInstance()->get(GetSourceItemsBySkuInterface::class);

        // Get all stock sources assigned to the product
        $sourceItems = $getSourceItemsBySku->execute($product->getSku());

        /** @var SourceItem $sourceItem */
        foreach ($sourceItems as $sourceItem) {
            // If the source is enabled for the product get its quantity
            if ($sourceItem->getStatus() == true) {
                // Start the counter at 0 now if this is the first active source for the product
                if ($qty == false) {
                    $qty = 0;
                }
                $qty += $sourceItem->getQuantity();
            }
        }

        return $qty;
    }

    /**
     * @param ProductInterface $product
     * @return float|null
     */
    private function getLegacyProductStock(ProductInterface $product)
    {
        // Set default value to "false" (stock not managed).
        $qty = null;

        $stockItem = $this->stockRegistry->getStockItem($product->getId());
        if ($stockItem->getManageStock() == true) {
            $qty = $stockItem->getQty();
        }

        return $qty;
    }

    /**
     * @param ProductInterface $product
     * @return array|mixed
     * @throws NoSuchEntityException
     */
    private function getSiblingProductIds(ProductInterface $product)
    {
        $parentProduct = $this->getParentProduct($product->getId());
        $siblingIds = $this->configurableType->getChildrenIds($parentProduct->getId());

        $siblingIds = reset($siblingIds);

        return array_keys($siblingIds);
    }

    /**
     * @param $productId
     * @return ProductInterface
     * @throws NoSuchEntityException
     */
    private function getParentProduct($productId)
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
     * @param ProductInterface $product
     * @return array
     */
    private function getAttributes(ProductInterface $product)
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
     * @param ProductInterface $product
     * @return mixed
     */
    private function getSuperAttributeCodes(ProductInterface $product)
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
     * @param ProductInterface $product
     * @return OptionInterface
     */
    private function getSuperAttributes(ProductInterface $product)
    {
        if (!isset($this->superAttributes)) {
            $parentProduct = $this->getParentProduct($product->getId());
            $this->superAttributes = $parentProduct->getTypeInstance()->getConfigurableAttributes($parentProduct);
        }

        return $this->superAttributes;
    }

    /**
     * Returns the main image url of the variation product, or the parent product if no image applied to the variation
     *
     * @param ProductInterface $product
     * @return string
     */
    private function getProductImageUrl(ProductInterface $product)
    {
        $image = $product->getImage();

        // Get the parent product's image if variation has no image set
        if (!$image || $image == 'no_selection') {
            $image = $this->getParentProduct($product->getId())->getImage();
        }

        return (string) $product->getMediaConfig()->getMediaUrl($image);
    }
}
