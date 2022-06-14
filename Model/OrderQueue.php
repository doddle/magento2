<?php
declare(strict_types=1);

namespace Doddle\Returns\Model;

use Magento\Framework\Model\AbstractModel;
use Doddle\Returns\Api\Data\OrderQueueInterface;
use Doddle\Returns\Model\ResourceModel\OrderQueue as OrderQueueResource;

class OrderQueue extends AbstractModel implements OrderQueueInterface
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(OrderQueueResource::class);
    }

    /**
     * @inheritDoc
     */
    public function getId()
    {
        return $this->getData(self::ID);
    }

    /**
     * @inheritDoc
     */
    public function setId($id)
    {
        return $this->setData(self::ID, $id);
    }

    /**
     * @inheritDoc
     */
    public function getOrderId()
    {
        return $this->getData(self::ORDER_ID);
    }

    /**
     * @inheritDoc
     */
    public function setOrderId($orderId)
    {
        return $this->setData(self::ORDER_ID, $orderId);
    }

    /**
     * @inheritDoc
     */
    public function getStatus()
    {
        return $this->getData(self::STATUS);
    }

    /**
     * @inheritDoc
     */
    public function setStatus($status)
    {
        return $this->setData(self::STATUS, $status);
    }

    /**
     * @inheritDoc
     */
    public function getFailCount()
    {
        return $this->getData(self::FAIL_COUNT);
    }

    /**
     * @inheritDoc
     */
    public function setFailCount($failCount)
    {
        return $this->setData(self::FAIL_COUNT, $failCount);
    }

    /**
     * @inheritDoc
     */
    public function getDoddleOrderId()
    {
        return $this->getData(self::DODDLE_ORDER_ID);
    }

    /**
     * @inheritDoc
     */
    public function setDoddleOrderId($doddleOrderId)
    {
        return $this->setData(self::DODDLE_ORDER_ID, $doddleOrderId);
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt()
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setCreatedAt($createdAt)
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * @inheritDoc
     */
    public function getUpdatedAt()
    {
        return $this->getData(self::UPDATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setUpdatedAt($updatedAt)
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }
}
