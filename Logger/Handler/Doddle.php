<?php
declare(strict_types=1);

namespace Doddle\Returns\Logger\Handler;

use Magento\Framework\Logger\Handler\Base as BaseHandler;
use Monolog\Logger as MonologLogger;

class Doddle extends BaseHandler
{
    /** @var string */
    protected $fileName = '/var/log/doddle_returns.log';
}
