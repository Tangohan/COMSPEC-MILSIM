<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Core\Request;
use App\Core\Session;
use Throwable;

/**
 * Envoie un rapport d’exception / erreur fatale par e-mail (hors réponse HTTP client).
 */
final class ErrorReportMailer
{
    public function reportThrowable(Throwable $e, ?string $requestId = null): void
    {
        $this->sendInternal(
            kind: 'exception',
            className: $e::class,
            message: $e->getMessage(),
            file: $e->getFile(),
            line: $e->getLine(),
            trace: $e->getTraceAsString(),
            requestId: $requestId
        );
    }

    /**
     * @param array{type:int,message:string,file:string,line:int}|null $err error_get_last()
     */
    public function reportFatal(?array $err, ?string $requestId = null): void
    {
        if ($err === null) {
            return;
        }
        $this->sendInternal(
            kind: 'fatal',
            className: 'PHP Fatal',
            message: (string) ($err['message'] ?? ''),
            file: (string) ($err['file'] ?? ''),
            line: (int) ($err['line'] ?? 0),
            trace: '',
            requestId: $requestId
        );
    }

    private function sendInternal(
        string $kind,
        string $className,
        string $message,
        string $file,
        int $line,
        string $trace,
        ?string $requestId
    ): void {
        if (!filter_var((string) env('ERROR_ALERT_ENABLED', true), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }
        $to = trim((string) env('ERROR_ALERT_EMAIL', ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $ip = $this->clientIp();
        $fingerprint = $kind . '|' . $className . '|' . $file . '|' . $line . '|' . substr($message, 0, 200);
        $dedupeKey = $fingerprint . '|' . $ip;
        $throttle = ErrorAlertThrottle::fromEnv();
        if ($throttle->isThrottled($dedupeKey)) {
            return;
        }

        $path = Request::normalizePathFromServer();
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');

        $userId = null;
        $tenantId = null;
        try {
            Session::start();
            $userId = Session::get('user_id');
            $tenantId = Session::get('tenant_id');
        } catch (\Throwable) {
        }

        $lines = [
            'COMSPEC — rapport d’erreur',
            'Type: ' . $kind,
            'Classe / moteur: ' . $className,
            'Message: ' . $message,
            'Fichier: ' . $file . ':' . $line,
            'Méthode: ' . $method,
            'Chemin: ' . $path,
            'URI: ' . $this->sanitizeUri($uri),
            'IP client: ' . $ip,
            'Request-ID: ' . ($requestId ?? (string) (getenv('REQUEST_ID') ?: $_ENV['REQUEST_ID'] ?? '')),
            'APP_ENV: ' . (string) env('APP_ENV', ''),
            'User-ID: ' . ($userId !== null ? (string) $userId : '—'),
            'Tenant-ID: ' . ($tenantId !== null ? (string) $tenantId : '—'),
            'Accept: ' . $accept,
            '',
            '--- Trace ---',
            $trace !== '' ? $trace : '(non disponible pour erreur fatale)',
        ];
        $text = implode("\n", $lines);

        $html = '<pre style="font-family:monospace;white-space:pre-wrap;">'
            . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</pre>';

        $subject = '[COMSPEC erreur] ' . $className . ' — ' . $path;

        try {
            $mailer = \App\Core\Container::get(\App\Services\EmailService::class);
            $mailer->send(
                'error_alert',
                $to,
                $subject,
                $html,
                $text,
                is_numeric($tenantId) ? (int) $tenantId : null,
                null,
                ['kind' => $kind, 'path' => $path]
            );
        } catch (\Throwable) {
            // ne pas masquer l’erreur d’origine
        }
    }

    private function clientIp(): string
    {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0';
        if (is_string($ip) && str_contains($ip, ',')) {
            return trim(explode(',', $ip)[0]);
        }

        return is_string($ip) ? $ip : '0';
    }

    private function sanitizeUri(string $uri): string
    {
        $parts = parse_url($uri);
        if (!is_array($parts)) {
            return $uri;
        }
        $q = $parts['query'] ?? '';
        if ($q === '') {
            return $uri;
        }
        parse_str($q, $params);
        $redacted = ['password', 'password_confirmation', 'token', '_csrf_token', 'csrf'];
        foreach ($redacted as $k) {
            if (isset($params[$k])) {
                $params[$k] = '[omis]';
            }
        }
        $parts['query'] = http_build_query($params);

        return $this->buildUri($parts);
    }

    /** @param array<string, mixed> $parts */
    private function buildUri(array $parts): string
    {
        $s = '';
        if (!empty($parts['scheme'])) {
            $s .= $parts['scheme'] . '://';
        }
        if (!empty($parts['host'])) {
            $s .= $parts['host'];
        }
        if (!empty($parts['path'])) {
            $s .= $parts['path'];
        }
        if (!empty($parts['query'])) {
            $s .= '?' . $parts['query'];
        }

        return $s !== '' ? $s : (string) ($parts['path'] ?? '/');
    }
}
