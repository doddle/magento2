<?php
declare(strict_types=1);

namespace Doddle\Returns\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class ApiMode implements ArrayInterface
{
    const API_MODE_LIVE = 'live';
    const API_MODE_TEST = 'test';

    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::API_MODE_LIVE, 'label' => __('Live')],
            ['value' => self::API_MODE_TEST, 'label' => __('Test')]
        ];
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            self::API_MODE_LIVE => __('Live'),
            self::API_MODE_TEST => __('Test'),
        ];
    }
}
