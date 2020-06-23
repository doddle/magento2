<?php
namespace Doddle\Returns\Api\Data;

interface OrderQueueInterface
{
    const ID              = 'sync_id';
    const ORDER_ID        = 'order_id';
    const STATUS          = 'status';
    const FAIL_COUNT      = 'fail_count';
    const DODDLE_ORDER_ID = 'doddle_order_id';
    const CREATED_AT      = 'created_at';
    const UPDATED_AT      = 'updated_at';
    const STATUS_PENDING  = 'pending';
    const STATUS_SYNCHED  = 'synched';
    const STATUS_FAILED   = 'failed';

    /**
     * @return int
     */
    public function getId();

    /**
     * @param int $id
     * @return $this
     */
    public function setId($id);

    /**
     * @return int
     */
    public function getOrderId();

    /**
     * @param int $orderId
     * @return $this
     */
    public function setOrderId($orderId);

    /**
     * @return string
     */
    public function getStatus();

    /**
     * @param string $status
     * @return $this
     */
    public function setStatus($status);

    /**
     * @return int
     */
    public function getFailCount();

    /**
     * @param int $failCount
     * @return $this
     */
    public function setFailCount($failCount);

    /**
     * @return int|null
     */
    public function getDoddleOrderId();

    /**
     * @param int $doddleOrderId
     * @return $this
     */
    public function setDoddleOrderId($doddleOrderId);

    /**
     * @return string|null
     */
    public function getCreatedAt();

    /**
     * @param string $createdAt
     * @return $this
     */
    public function setCreatedAt($createdAt);

    /**
     * @return string|null
     */
    public function getUpdatedAt();

    /**
     * @param string $updatedAt
     * @return $this
     */
    public function setUpdatedAt($updatedAt);
}
