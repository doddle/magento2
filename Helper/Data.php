<?php
declare(strict_types=1);

namespace Doddle\Returns\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    /** @var ProductMetadataInterface */
    private $productMetadata;

    // Database Tables
    public const DB_TABLE_ORDER_QUEUE = 'doddle_returns_order_sync_queue';

    // Attributes
    public const ATTRIBUTE_CODE_RETURNS_ELIGIBILITY = 'doddle_returns_excluded';

    // Integrations
    public const INTEGRATION_NAME = 'Doddle Returns';

    // Config
    private const XML_PATH_API_KEY               = 'doddle_returns/api/key';
    private const XML_PATH_API_SECRET            = 'doddle_returns/api/secret';
    private const XML_PATH_API_MODE              = 'doddle_returns/api/mode';
    private const XML_PATH_API_LIVE_URL          = 'doddle_returns/api/live_url';
    private const XML_PATH_API_TEST_URL          = 'doddle_returns/api/test_url';
    private const XML_PATH_COMPANY_ID            = 'doddle_returns/order_sync/company_id';
    private const XML_PATH_ORDER_SYNC_ENABLED    = 'doddle_returns/order_sync/enabled';
    private const XML_PATH_ORDER_SYNC_BATCH_SIZE = 'doddle_returns/order_sync/batch_size';
    private const XML_PATH_ORDER_SYNC_MAX_FAILS  = 'doddle_returns/order_sync/max_fails';

    /**
     * @param Context $context
     * @param ProductMetadataInterface $productMetadata
     */
    public function __construct(
        Context $context,
        ProductMetadataInterface $productMetadata
    ) {
        parent::__construct($context);
        $this->productMetadata = $productMetadata;
    }

    /**
     * Get API key from config
     *
     * @return string
     */
    public function getApiKey(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_API_KEY);
    }

    /**
     * Get API secret from config
     *
     * @return string
     */
    public function getApiSecret(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_API_SECRET);
    }

    /**
     * Get API mode from config
     *
     * @return string
     */
    public function getApiMode(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_API_MODE);
    }

    /**
     * Get Live API URL from config
     *
     * @return string
     */
    public function getLiveApiUrl(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_API_LIVE_URL);
    }

    /**
     * Get Test API URL from config
     *
     * @return string
     */
    public function getTestApiUrl(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_API_TEST_URL);
    }

    /**
     * Get company ID from config
     *
     * @return string
     */
    public function getCompanyId(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_COMPANY_ID);
    }

    /**
     * Get order sync enabled from config (store scope)
     *
     * @param int $storeId
     * @return bool
     */
    public function getOrderSyncEnabled(int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ORDER_SYNC_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get order sync batch size from config
     *
     * @return int
     */
    public function getOrderSyncBatchSize(): int
    {
        return (int) $this->scopeConfig->getValue(self::XML_PATH_ORDER_SYNC_BATCH_SIZE);
    }

    /**
     * Get order sync max fails from config
     *
     * @return int
     */
    public function getOrderSyncMaxFails(): int
    {
        return (int) $this->scopeConfig->getValue(self::XML_PATH_ORDER_SYNC_MAX_FAILS);
    }

    /**
     * Get Magento major/minor version
     *
     * @return string|null
     */
    public function getMajorMinorVersion(): ?string
    {
        $versionParts = explode('.', $this->productMetadata->getVersion());
        if (!isset($versionParts[0]) || !isset($versionParts[1])) {
            return null;
        }
        return $versionParts[0] . '.' . $versionParts[1];
    }

    /**
     * Check if a value contains non-zero decimals
     *
     * @param $value
     * @return bool
     */
    public function hasDecimals($value): bool
    {
        return is_numeric($value) && floor((float) $value) != $value;
    }
}
