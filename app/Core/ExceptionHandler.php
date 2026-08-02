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
            // TCPDF (bibliothèque tierce embarquée) : Close() → _destroy(false) unset les propriétés,
            // puis __destruct → _destroy(true) relit $imagekeys & co. déjà absentes ou nulles
            // (« Undefined property TCPDF::$… », « foreach() argument must be of type array|object »).
            // Ces avertissements surviennent après l’envoi du PDF : on les journalise sans les
            // transformer en ErrorException, sinon le téléchargement se solde par une page 500.
            $isNotice = ($severity & (E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE | E_DEPRECATED | E_USER_DEPRECATED)) !== 0;
            if ($isNotice && str_contains(str_replace('\\', '/', $file), '/tcpdf/')) {
                error_log('[tcpdf] ' . $message . ' — ' . $file . ':' . $line);

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

        // Réponse binaire déjà partie (PDF, export, image…) : y concaténer une page d’erreur
        // corromprait le fichier téléchargé. On se contente alors de la journalisation.
        if (self::responseAlreadyStartedAsBinary()) {
            return;
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
            $errorReference = (string) (getenv('REQUEST_ID') ?: ($_ENV['REQUEST_ID'] ?? ''));
            $errorHint = function_exists('athena_error_hint') ? athena_error_hint($e->getMessage()) : '';
            require $path;

            return;
        }
        http_response_code(500);
        echo '<h1>500 Server Error</h1>';
    }

    /**
     * Vrai si la réponse a déjà commencé à être envoyée avec un type non HTML/JSON
     * (Content-Type binaire ou en-tête de téléchargement).
     */
    private static function responseAlreadyStartedAsBinary(): bool
    {
        if (!headers_sent()) {
            return false;
        }
        foreach (headers_list() as $header) {
            $lower = strtolower($header);
            if (str_starts_with($lower, 'content-disposition:') && str_contains($lower, 'attachment')) {
                return true;
            }
            if (!str_starts_with($lower, 'content-type:')) {
                continue;
            }
            if (str_contains($lower, 'text/html') || str_contains($lower, 'application/json')) {
                return false;
            }

            return true;
        }

        return false;
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
