<?php
declare(strict_types=1);

namespace Doddle\Returns\Helper;

class ValidateField
{
    private const DEFAULT_NUMBER = 0;
    private const DEFAULT_BOOL = false;
    private const DEFAULT_STRING = ' ';

    /**
     * Validate number and swap in default value if required
     *
     * @param $value
     * @return float
     */
    public function number($value = null)
    {
        return is_numeric($value) ? (float) $value : self::DEFAULT_NUMBER;
    }

    /**
     * Validate boolean and swap in default value if require
     *
     * @param $value
     * @return bool
     */
    public function boolean($value = null)
    {
        return is_bool($value) ? $value : self::DEFAULT_BOOL;
    }

    /**
     * Validate string and swap in default value if require
     *
     * @param $value
     * @return string
     */
    public function string($value = null): string
    {
        return ($value && strlen($value) > 0) ? (string) $value : self::DEFAULT_STRING;
    }
}
