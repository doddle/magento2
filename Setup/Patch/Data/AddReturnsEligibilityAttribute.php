<?php
declare(strict_types=1);

namespace Doddle\Returns\Setup\Patch\Data;

use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Catalog\Model\Product;
use Doddle\Returns\Helper\Data as DataHelper;

class AddReturnsEligibilityAttribute implements DataPatchInterface
{
    /** @var ModuleDataSetupInterface */
    private $moduleDataSetup;

    /** @var EavSetupFactory */
    private $eavSetupFactory;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param EavSetupFactory $eavSetupFactory
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        EavSetupFactory $eavSetupFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    /**
     * @inheritdoc
     */
    public function apply(): void
    {
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $attribute = $eavSetup->getAttribute(
            Product::ENTITY,
            DataHelper::ATTRIBUTE_CODE_RETURNS_ELIGIBILITY
        );

        // Exit if the attribute already exists
        if (!empty($attribute)) {
            return;
        }

        $eavSetup->addAttribute(
            Product::ENTITY,
            DataHelper::ATTRIBUTE_CODE_RETURNS_ELIGIBILITY,
            [
                'label' => 'Exclude from Doddle Returns',
                'group' => 'Doddle Returns',
                'note' => 'Products flagged as excluded will not be available to return via Doddle Returns.',
                'backend' => \Doddle\Returns\Model\Product\Attribute\Backend\Boolean::class,
                'frontend' => '',
                'input' => 'select',
                'class' => '',
                'source' => \Magento\Eav\Model\Entity\Attribute\Source\Boolean::class,
                'global' => true,
                'visible' => true,
                'required' => false,
                'user_defined' => false,
                'default' => \Magento\Eav\Model\Entity\Attribute\Source\Boolean::VALUE_NO,
                'apply_to' => '',
                'visible_on_front' => false,
                'is_used_in_grid' => true,
                'is_visible_in_grid' => false,
                'is_filterable_in_grid' => false
            ]
        );
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
