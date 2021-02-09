<?php
declare(strict_types=1);

namespace Doddle\Returns\Setup;

use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Exception\IntegrationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Integration\Api\IntegrationServiceInterface;
use Magento\Integration\Model\Integration;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Catalog\Model\Product;
use Doddle\Returns\Helper\Data as DataHelper;

class UpgradeData implements UpgradeDataInterface
{
    /** @var ModuleDataSetupInterface */
    private $setup;

    /** @var EavSetupFactory */
    private $eavSetupFactory;

    /** @var DataHelper */
    private $dataHelper;

    /** @var IntegrationServiceInterface */
    private $integrationService;

    /**
     * @param EavSetupFactory $eavSetupFactory
     * @param DataHelper $dataHelper
     * @param IntegrationServiceInterface $integrationService
     */
    public function __construct(
        EavSetupFactory $eavSetupFactory,
        DataHelper $dataHelper,
        IntegrationServiceInterface $integrationService
    ) {
        $this->eavSetupFactory = $eavSetupFactory;
        $this->dataHelper = $dataHelper;
        $this->integrationService = $integrationService;
    }

    /**
     * Upgrade script for backwards compatibility with Magento < 2.3.0
     *
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     * @throws LocalizedException
     * @throws \Zend_Validate_Exception
     */
    public function upgrade(
        ModuleDataSetupInterface $setup,
        ModuleContextInterface $context
    ) {
        // Return early to allow data patches to process in Magento > 2.3.0
        if (version_compare($this->dataHelper->getMajorMinorVersion(), '2.3', '>=')) {
            return;
        }

        $this->setup = $setup;
        $this->connection = $this->setup->getConnection();

        if (version_compare($context->getVersion(), '0.1.0', '<')) {
            $this->addReturnsEligibilityAttribute();
            $this->addReturnsIntegration();
        }
    }

    /**
     * @throws LocalizedException
     * @throws \Zend_Validate_Exception
     */
    private function addReturnsEligibilityAttribute()
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->setup]);

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
     * @throws IntegrationException
     */
    private function addReturnsIntegration()
    {
        $this->integrationService->create([
            Integration::NAME => DataHelper::INTEGRATION_NAME,
            'resource' => [
                'Magento_Backend::admin',
                'Doddle_Returns::returns',
                'Doddle_Returns::variations'
            ]
        ]);
    }
}
