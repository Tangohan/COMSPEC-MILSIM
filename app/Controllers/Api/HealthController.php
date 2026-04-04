<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

class HealthController
{
    public function index(Request $request, array $params = []): Response
    {
        try {
            $pdo = Database::getPdo();
            $pdo->query('SELECT 1');
            return Response::json(['db' => 'ok']);
        } catch (\Throwable $e) {
            return Response::json(['db' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
