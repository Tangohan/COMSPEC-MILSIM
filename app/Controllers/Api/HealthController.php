<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Support\Api\ApiResponder;

class HealthController
{
    public function index(Request $request, array $params = []): Response
    {
        try {
            $pdo = Database::getPdo();
            $pdo->query('SELECT 1');
            return ApiResponder::success(['db' => 'ok']);
        } catch (\Throwable $e) {
            // Le message PDO peut contenir l’hôte et l’utilisateur MySQL : journal seulement.
            error_log('[health] ' . $e->getMessage());

            return ApiResponder::error('db_unreachable', 'Base de données indisponible.', 500);
        }
    }
}
