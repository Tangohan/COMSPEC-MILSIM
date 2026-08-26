<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Response;
use App\Services\Monitoring\ErrorReportMailer;
use Throwable;

/**
 * Réponses d’échec pour Overwatch / API ATAK : JSON compact, jamais une page HTML.
 */
final class TacticalApiErrorRenderer
{
    public static function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/') ?: '/';
        }
        while ($path !== '/' && ($path === '/public' || str_starts_with($path, '/public/'))) {
            $path = $path === '/public' ? '/' : ('/' . ltrim(substr($path, strlen('/public')), '/'));
            if ($path !== '/') {
                $path = rtrim($path, '/') ?: '/';
            }
        }

        return $path;
    }

    public static function isTacticalPath(string $path): bool
    {
        $path = self::normalizePath($path);

        return $path === '/api/atak' || str_starts_with($path, '/api/atak/');
    }

    public static function clientWantsJson(string $path, string $accept = ''): bool
    {
        $path = self::normalizePath($path);
        if (str_starts_with($path, '/api/')) {
            return true;
        }

        return str_contains(strtolower($accept), 'application/json');
    }

    /**
     * @return array{ok: false, message: string, request_id?: string}
     */
    public static function payload(Throwable $e, ?string $requestId = null): array
    {
        $raw = $e->getMessage();
        $hint = function_exists('athena_error_hint') ? athena_error_hint($raw) : '';
        $message = $hint !== ''
            ? $hint
            : 'Le poste est momentanément indisponible. Réessayez dans un instant.';

        $out = [
            'ok' => false,
            'message' => $message,
        ];
        $rid = trim((string) $requestId);
        if ($rid !== '') {
            $out['request_id'] = $rid;
        }

        return $out;
    }

    public static function httpStatus(): int
    {
        return 503;
    }

    public static function toResponse(Throwable $e, ?string $requestId = null): Response
    {
        $rid = $requestId ?? (string) (getenv('REQUEST_ID') ?: ($_ENV['REQUEST_ID'] ?? ''));
        error_log($e->getMessage() . ' — ' . $e->getFile() . ':' . $e->getLine());
        try {
            (new ErrorReportMailer())->reportThrowable($e, $rid !== '' ? $rid : null);
        } catch (Throwable) {
        }

        $response = Response::json(self::payload($e, $rid !== '' ? $rid : null), self::httpStatus());
        $response->header('Retry-After', '30');

        return $response;
    }
}
