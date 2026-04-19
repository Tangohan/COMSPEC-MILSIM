<?php

declare(strict_types=1);

namespace App\Support\Api;

use App\Core\Response;

final class ApiResponder
{
    /** @param array<string,mixed> $data */
    public static function success(array $data = [], int $status = 200): Response
    {
        return Response::json(array_merge(['success' => true], $data), $status);
    }

    /** @param array<string,mixed> $details */
    public static function error(string $code, string $message, int $status, array $details = []): Response
    {
        $payload = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];

        if ($details !== []) {
            $payload['error']['details'] = $details;
        }

        return Response::json($payload, $status);
    }
}
