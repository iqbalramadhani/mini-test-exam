<?php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\ErrorHandler;

class AppErrorHandler
{
    private static $logger;

    public static function init()
    {
        if (self::$logger === null) {
            $logFile = ROOT . 'tmp/logs/app.log';
            
            // Ensure directory exists
            $logDir = dirname($logFile);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0777, true);
            }

            self::$logger = new Logger('mini-app');
            
            // Format log
            $dateFormat = "Y-m-d H:i:s";
            $output = "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";
            $formatter = new LineFormatter($output, $dateFormat);
            
            $stream = new StreamHandler($logFile, Logger::DEBUG);
            $stream->setFormatter($formatter);
            
            self::$logger->pushHandler($stream);

            // Register global error, exception and fatal error handler
            ErrorHandler::register(self::$logger);
        }
    }

    public static function getLogger()
    {
        if (self::$logger === null) {
            self::init();
        }
        return self::$logger;
    }
}

// Initialize handler immediately when this file is required
AppErrorHandler::init();
