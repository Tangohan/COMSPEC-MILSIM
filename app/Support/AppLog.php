<?php

declare(strict_types=1);

namespace App\Support;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

/**
 * Journal applicatif (JSON possible via format Monolog).
 */
final class AppLog
{
    private static ?Logger $logger = null;

    public static function logger(): Logger
    {
        if (self::$logger !== null) {
            return self::$logger;
        }
        $log = new Logger('athena');
        $dir = base_path('storage/logs');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $path = $dir . '/app.log';
        $log->pushHandler(new StreamHandler($path, Level::Info, true));
        self::$logger = $log;

        return self::$logger;
    }
}
