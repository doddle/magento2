<?php
declare(strict_types=1);

namespace Doddle\Returns\Logger\Handler;

use Magento\Framework\Logger\Handler\Base as BaseHandler;
use Monolog\Logger as MonologLogger;

class AlertHandler extends BaseHandler
{
    protected $loggerType = MonologLogger::ALERT;
    protected $fileName = '/var/log/doddle_returns.log';
}
