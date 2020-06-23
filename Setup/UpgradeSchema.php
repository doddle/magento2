<?php
declare(strict_types=1);

namespace Doddle\Returns\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Doddle\Returns\Helper\Data as DataHelper;
use Doddle\Returns\Api\Data\OrderQueueInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    /** @var SchemaSetupInterface */
    private $setup;

    /** @var AdapterInterface */
    private $connection;

    /** @var DataHelper */
    private $dataHelper;

    /**
     * @param DataHelper $dataHelper
     */
    public function __construct(
        DataHelper $dataHelper
    ) {
        $this->dataHelper = $dataHelper;
    }

    /**
     * Upgrade script for backwards compatibility with Magento < 2.3.0
     *
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     */
    public function upgrade(
        SchemaSetupInterface $setup,
        ModuleContextInterface $context
    ) {
        // Return early to allow db_schema.xml to process in Magento > 2.3.0
        if (version_compare($this->dataHelper->getMajorMinorVersion(), '2.3', '>=')) {
            return;
        }

        $this->setup = $setup;
        $this->connection = $this->setup->getConnection();

        if (version_compare($context->getVersion(), '0.1.0', '<')) {
            $this->addOrderQueueTable();
        }
    }

    /**
     * @throws \Zend_Db_Exception
     */
    private function addOrderQueueTable()
    {
        $table = $this->connection
            ->newTable($this->setup->getTable(DataHelper::DB_TABLE_ORDER_QUEUE))
            ->addColumn(
                OrderQueueInterface::ID,
                Table::TYPE_INTEGER,
                null,
                ['identity' => true, 'primary' => true, 'unsigned' => true, 'nullable' => false],
                'Order sync queue ID'
            )->addColumn(
                OrderQueueInterface::ORDER_ID,
                Table::TYPE_INTEGER,
                255,
                ['unsigned' => true, 'nullable' => false],
                'Magento order ID'
            )->addColumn(
                OrderQueueInterface::STATUS,
                Table::TYPE_TEXT,
                255,
                ['nullable' => false, 'default' => OrderQueueInterface::STATUS_PENDING],
                'Order push status'
            )->addColumn(
                OrderQueueInterface::FAIL_COUNT,
                Table::TYPE_SMALLINT,
                null,
                ['unsigned' => true, 'nullable' => false, 'default' => 0],
                'Count of failed attempts to sync'
            )->addColumn(
                OrderQueueInterface::DODDLE_ORDER_ID,
                Table::TYPE_TEXT,
                null,
                [],
                'Location description'
            )->addColumn(
                OrderQueueInterface::CREATED_AT,
                Table::TYPE_TIMESTAMP,
                null,
                ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
                'Created at date'
            )
            ->addColumn(
                OrderQueueInterface::UPDATED_AT,
                Table::TYPE_TIMESTAMP,
                null,
                ['nullable' => false, 'default' => Table::TIMESTAMP_INIT_UPDATE],
                'Updated at date'
            )->addIndex(
                $this->setup->getIdxName(
                    DataHelper::DB_TABLE_ORDER_QUEUE,
                    [OrderQueueInterface::STATUS],
                    AdapterInterface::INDEX_TYPE_INDEX
                ),
                [OrderQueueInterface::STATUS],
                ['type' => AdapterInterface::INDEX_TYPE_INDEX]
            )->addIndex(
                $this->setup->getIdxName(
                    DataHelper::DB_TABLE_ORDER_QUEUE,
                    [OrderQueueInterface::ORDER_ID],
                    AdapterInterface::INDEX_TYPE_UNIQUE
                ),
                [OrderQueueInterface::ORDER_ID],
                ['type' => AdapterInterface::INDEX_TYPE_UNIQUE]
            )->addForeignKey(
                $this->setup->getFkName(
                    DataHelper::DB_TABLE_ORDER_QUEUE,
                    OrderQueueInterface::ORDER_ID,
                    $this->setup->getTable('sales_order'),
                    'entity_id'
                ),
                OrderQueueInterface::ORDER_ID,
                $this->setup->getTable('sales_order'),
                'entity_id',
                \Magento\Framework\DB\Ddl\Table::ACTION_CASCADE
            )
            ->setComment('Doddle Returns order push queue');

        $this->connection->createTable($table);
    }
}
