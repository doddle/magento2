<?php
declare(strict_types=1);

namespace Doddle\Returns\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class ApiMode implements ArrayInterface
{
    public const API_MODE_LIVE = 'live';
    public const API_MODE_TEST = 'test';

    /**
     * Retrieve as option array
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::API_MODE_LIVE, 'label' => __('Live')],
            ['value' => self::API_MODE_TEST, 'label' => __('Test')]
        ];
    }

    /**
     * Retrieve as array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            self::API_MODE_LIVE => __('Live'),
            self::API_MODE_TEST => __('Test'),
        ];
    }
}
