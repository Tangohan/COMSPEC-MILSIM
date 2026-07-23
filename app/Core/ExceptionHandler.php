<?php

declare(strict_types=1);

namespace App\Core;

use App\Services\Monitoring\ErrorReportMailer;
use Throwable;

class ExceptionHandler
{
    public static function register(): void
    {
        set_exception_handler([self::class, 'handle']);
        set_error_handler(function (int $severity, string $message, string $file, int $line) {
            // PHP 8+ invoque toujours le handler même avec @ ; error_reporting() vaut alors 0.
            // Sans ce garde-fou, un @unlink TCPDF (fichier déjà purgé) devient une ErrorException fatale.
            if (!(error_reporting() & $severity)) {
                return false;
            }
            // TCPDF : Close() → _destroy(false) unset les props, puis __destruct → _destroy(true)
            // lit $imagekeys (et parfois d’autres) déjà absentes → Warning PHP 8.2+ hors bloc @.
            if (
                ($severity === E_WARNING || $severity === E_NOTICE || $severity === E_USER_WARNING || $severity === E_USER_NOTICE)
                && str_contains($message, 'TCPDF::$')
                && str_contains(str_replace('\\', '/', $file), '/tcpdf.php')
            ) {
                return true;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
    }

    public static function handle(Throwable $e): void
    {
        try {
            if (empty($GLOBALS['__app_locale']) && class_exists(\App\Services\I18n\LocaleService::class)) {
                (new \App\Services\I18n\LocaleService())->boot();
            }
        } catch (Throwable) {
        }

        $debug = (bool) config('app.debug', false);
        $logPath = base_path('storage/logs/app.log');

        $message = $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
        if (is_dir(dirname($logPath))) {
            error_log(date('[Y-m-d H:i:s] ') . $message . "\n" . $e->getTraceAsString() . PHP_EOL, 3, $logPath);
        }
        error_log($e->getMessage() . ' — ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());

        try {
            $rid = (string) (getenv('REQUEST_ID') ?: ($_ENV['REQUEST_ID'] ?? ''));
            (new ErrorReportMailer())->reportThrowable($e, $rid !== '' ? $rid : null);
        } catch (Throwable) {
        }

        $wantsJson = self::clientWantsJson();

        if ($wantsJson) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            http_response_code(500);
            echo json_encode([
                'error' => 'server_error',
                'message' => function_exists('__') ? __('errors.json_server_error') : 'Une erreur est survenue. Merci de réessayer plus tard.',
            ], JSON_UNESCAPED_UNICODE);

            return;
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

    private static function clientWantsJson(): bool
    {
        $path = Request::normalizePathFromServer();
        if (str_starts_with($path, '/api/')) {
            return true;
        }
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));

        return str_contains($accept, 'application/json');
    }
}
