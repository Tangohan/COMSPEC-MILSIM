<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

class ExceptionHandler
{
    public static function register(): void
    {
        set_exception_handler([self::class, 'handle']);
        set_error_handler(function (int $severity, string $message, string $file, int $line) {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
    }

    public static function handle(Throwable $e): void
    {
        $debug = config('app.debug', false);
        $logPath = base_path('storage/logs/app.log');

        $message = $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
        if (is_dir(dirname($logPath))) {
            error_log(date('[Y-m-d H:i:s] ') . $message . PHP_EOL, 3, $logPath);
        }

        if ($debug) {
            echo '<pre>' . htmlspecialchars((string) $e) . '</pre>';
            return;
        }

        $path = base_path('views/errors/500.php');
        if (is_file($path)) {
            http_response_code(500);
            require $path;
            return;
        }
        http_response_code(500);
        echo '<h1>500 Server Error</h1>';
    }
}
