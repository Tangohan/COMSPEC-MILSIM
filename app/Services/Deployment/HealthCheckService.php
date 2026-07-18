<?php

declare(strict_types=1);

namespace App\Services\Deployment;

use App\Repositories\PlatformAppReleaseRepository;

/**
 * Contrôles post-déploiement (santé minimale).
 */
final class HealthCheckService
{
    /**
     * @return array{ok:bool, checks:array<string,bool>, messages:list<string>}
     */
    public function run(): array
    {
        $checks = [];
        $messages = [];

        try {
            $pdo = \App\Core\Database::getPdo();
            $pdo->query('SELECT 1');
            $checks['database'] = true;
        } catch (\Throwable $e) {
            $checks['database'] = false;
            $messages[] = 'Base de données inaccessible.';
        }

        $checks['version_file'] = is_file(base_path('storage/app_version.json'));
        if (!$checks['version_file']) {
            $messages[] = 'Fichier de version manquant.';
        }

        $checks['public_index'] = is_file(base_path('public/index.php'));
        if (!$checks['public_index']) {
            $messages[] = 'Point d’entrée public manquant.';
        }

        $checks['bootstrap'] = is_file(base_path('bootstrap/app.php'));
        if (!$checks['bootstrap']) {
            $messages[] = 'Bootstrap applicatif manquant.';
        }

        $ok = !in_array(false, $checks, true);

        return [
            'ok' => $ok,
            'checks' => $checks,
            'messages' => $messages,
        ];
    }
}
