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
    const DB_TABLE_ORDER_QUEUE = 'doddle_returns_order_sync_queue';

    // Attributes
    const ATTRIBUTE_CODE_RETURNS_ELIGIBILITY = 'doddle_returns_excluded';

    // Integrations
    const INTEGRATION_NAME = 'Doddle Returns';

    // Config
    const XML_PATH_API_KEY               = 'doddle_returns/api/key';
    const XML_PATH_API_SECRET            = 'doddle_returns/api/secret';
    const XML_PATH_API_MODE              = 'doddle_returns/api/mode';
    const XML_PATH_API_LIVE_URL          = 'doddle_returns/api/live_url';
    const XML_PATH_API_TEST_URL          = 'doddle_returns/api/test_url';
    const XML_PATH_COMPANY_ID            = 'doddle_returns/order_sync/company_id';
    const XML_PATH_ORDER_SYNC_ENABLED    = 'doddle_returns/order_sync/enabled';
    const XML_PATH_ORDER_SYNC_BATCH_SIZE = 'doddle_returns/order_sync/batch_size';
    const XML_PATH_ORDER_SYNC_MAX_FAILS  = 'doddle_returns/order_sync/max_fails';

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
     * @return string
     */
    public function getApiKey()
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_API_KEY);
    }

    /**
     * @return string
     */
    public function getApiSecret()
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_API_SECRET);
    }

    /**
     * @return string
     */
    public function getApiMode()
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_API_MODE);
    }

    /**
     * @return string
     */
    public function getLiveApiUrl()
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_API_LIVE_URL);
    }

    /**
     * @return string
     */
    public function getTestApiUrl()
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_API_TEST_URL);
    }

    /**
     * @return string
     */
    public function getCompanyId()
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_COMPANY_ID);
    }

    /**
     * @param int $storeId
     * @return bool
     */
    public function getOrderSyncEnabled(int $storeId)
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ORDER_SYNC_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @return int
     */
    public function getOrderSyncBatchSize()
    {
        return (int) $this->scopeConfig->getValue(self::XML_PATH_ORDER_SYNC_BATCH_SIZE);
    }

    /**
     * @return int
     */
    public function getOrderSyncMaxFails()
    {
        return (int) $this->scopeConfig->getValue(self::XML_PATH_ORDER_SYNC_MAX_FAILS);
    }

    /**
     * @return bool|string
     */
    public function getMajorMinorVersion()
    {
        $versionParts = explode('.', $this->productMetadata->getVersion());
        if (!isset($versionParts[0]) || !isset($versionParts[1])) {
            return false;
        }
        return $versionParts[0] . '.' . $versionParts[1];
    }
}
