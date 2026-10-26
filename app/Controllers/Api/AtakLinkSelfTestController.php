<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Container;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakDataRepository;
use App\Repositories\TenantAtakConfigRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Game\GameAuthService;
use App\Support\AtakPlanAccess;
use App\Support\AtakReleaseInfo;
use App\Support\ComspecApiKeyAuth;
use App\Support\SteamId;

/**
 * Auto-test de la liaison, demandé par « Dépannage liaison » en jeu (DLL : Link.SelfTest).
 * Refait côté serveur tout ce qui peut refuser ou dégrader la liaison d'un joueur, sans rien écrire :
 * authentification, communauté, maintenance, formule, compte, unité sur la carte, base, erreurs récentes,
 * versions publiées. Répond toujours 200 (sauf clé refusée par le middleware) avec une liste de contrôles.
 */
final class AtakLinkSelfTestController
{
    public function selfTest(Request $request, array $params = []): Response
    {
        $checks = [];
        $add = static function (string $id, string $level, string $label, string $value, string $hint = '') use (&$checks): void {
            $checks[] = ['id' => $id, 'level' => $level, 'label' => $label, 'value' => $value, 'hint' => $hint];
        };

        // 1. Authentification.
        $userId = ComspecApiKeyAuth::matchedUserId() ?? (int) (Session::get('user_id') ?? 0);
        $kind = match (true) {
            ComspecApiKeyAuth::matchedUserId() !== null => 'session jeu',
            ComspecApiKeyAuth::matchedPlatformKey() => 'clé plateforme',
            ComspecApiKeyAuth::matchedTenantId() !== null => 'clé de communauté',
            $userId > 0 => 'session du site',
            default => 'aucune',
        };
        if ($kind === 'aucune') {
            $add('auth', 'ko', 'Authentification', 'aucune', 'Athena ne reconnaît pas ce jeu : Lier mon compte (code sur le site).');
        } else {
            $add('auth', 'ok', 'Authentification', $kind);
        }

        // 2. Communauté.
        $tenantId = ComspecApiKeyAuth::matchedTenantId() ?? (int) (Session::get('tenant_id') ?? 0);
        $tenant = null;
        if ($tenantId > 0) {
            try {
                $tenant = $this->repo(TenantRepository::class)->findById($tenantId);
            } catch (\Throwable) {
                $tenant = null;
            }
        }
        if (!is_array($tenant)) {
            $add('tenant', 'ko', 'Communauté', 'introuvable', 'Le jeu n’est rattaché à aucune communauté : liez le compte en jeu.');
        } elseif (!empty($tenant['archived_at']) || (isset($tenant['status']) && !in_array((string) $tenant['status'], ['', 'active'], true))) {
            $add('tenant', 'ko', 'Communauté', (string) ($tenant['name'] ?? ('#' . $tenantId)) . ' (archivée ou inactive)', 'La communauté est archivée : son staff doit la restaurer.');
        } else {
            $add('tenant', 'ok', 'Communauté', (string) ($tenant['name'] ?? ('#' . $tenantId)));
        }

        if ($tenantId > 0) {
            // 3. Maintenance.
            try {
                $config = $this->repo(TenantAtakConfigRepository::class);
                if ($config->isMaintenanceEnabled($tenantId)) {
                    $msg = trim($config->getMaintenanceMessage($tenantId));
                    $add('maintenance', 'ko', 'Maintenance', 'en cours', $msg !== '' ? $msg : 'La carte est suspendue par le staff de la communauté.');
                } else {
                    $add('maintenance', 'ok', 'Maintenance', 'non');
                }
            } catch (\Throwable) {
                $add('maintenance', 'warn', 'Maintenance', 'non vérifiable');
            }

            // 4. Formule.
            if (AtakPlanAccess::allows($tenantId)) {
                $add('plan', 'ok', 'Formule', 'carte complète');
            } elseif (AtakPlanAccess::allowsBasic($tenantId)) {
                $add('plan', 'warn', 'Formule', 'liaison de base', 'Positions, comptes rendus et briefing seulement : photos, ordres, marqueurs, chat… demandent une formule Standard.');
            } else {
                $add('plan', 'ko', 'Formule', 'sans carte', 'La formule de la communauté n’inclut pas la carte ATAK.');
            }
        }

        // 5. Compte du joueur.
        $user = null;
        if ($userId > 0 && $tenantId > 0) {
            try {
                $problem = $this->repo(GameAuthService::class)->gameAccessProblem($userId, $tenantId);
            } catch (\Throwable) {
                $problem = null;
            }
            if ($problem !== null) {
                $add('account', 'ko', 'Compte', (string) $problem['code'], (string) $problem['message']);
            }
            try {
                $user = $this->repo(UserRepository::class)->findById($userId, $tenantId);
            } catch (\Throwable) {
                $user = null;
            }
            if ($problem === null && is_array($user)) {
                $add('account', 'ok', 'Compte', trim((string) ($user['callsign'] ?? '')) ?: ('#' . $userId));
            }
        } elseif ($kind !== 'aucune') {
            $add('account', 'warn', 'Compte', 'non lié', 'Liaison par clé sans compte : vos messages et photos ne remontent pas à votre nom. Lier mon compte.');
        }

        // 6. Steam : celui du jeu doit être celui de la fiche.
        $gameSteam = SteamId::normalize(ComspecApiKeyAuth::matchedSteamId() ?? (string) $request->query('steam_uid', ''));
        $userSteam = is_array($user) ? SteamId::normalize((string) ($user['steam_id'] ?? '')) : null;
        if ($gameSteam !== null && $userSteam !== null && $gameSteam !== $userSteam) {
            $add('steam', 'warn', 'Steam', 'différent de la fiche', 'Le Steam du jeu n’est pas celui de votre fiche Athena : refaites Lier mon compte depuis ce jeu.');
        } elseif ($gameSteam !== null || $userSteam !== null) {
            $add('steam', 'ok', 'Steam', '…' . substr((string) ($gameSteam ?? $userSteam), -4));
        }

        // 7. Unité sur la carte.
        $mapId = max(1, (int) ($request->query('mapId') ?? $request->query('map_id') ?? 1));
        $steam = $gameSteam ?? $userSteam;
        if ($tenantId > 0 && $steam !== null) {
            try {
                $unit = $this->repo(AtakDataRepository::class)->findUnitBySteamUid($tenantId, $mapId, $steam);
            } catch (\Throwable) {
                $unit = null;
            }
            if (!is_array($unit)) {
                $add('unit', 'warn', 'Position sur la carte', 'aucune reçue', 'Athena n’a encore reçu aucune position de ce joueur sur cette carte : portez un téléphone ATAK et bougez un peu.');
            } else {
                $status = (string) ($unit['status'] ?? '');
                $age = (int) ($unit['age_seconds'] ?? -1);
                $value = trim((string) ($unit['call_sign'] ?? '')) . ' · ' . ($status !== '' ? $status : '?') . ($age >= 0 ? ' · il y a ' . $age . ' s' : '');
                $add('unit', in_array($status, ['linked', 'delayed'], true) ? 'ok' : 'warn', 'Position sur la carte', $value,
                    in_array($status, ['linked', 'delayed'], true) ? '' : 'La dernière position est trop ancienne : vérifiez que le téléphone est porté et que la liaison n’est pas en pause.');
            }
        }

        // 8. Base de données.
        try {
            $t0 = microtime(true);
            Database::getPdo()->query('SELECT 1')->fetchColumn();
            $ms = (int) round((microtime(true) - $t0) * 1000);
            $add('database', $ms > 250 ? 'warn' : 'ok', 'Base Athena', $ms . ' ms', $ms > 250 ? 'Athena est chargée : les échanges peuvent prendre du retard.' : '');
        } catch (\Throwable) {
            $add('database', 'ko', 'Base Athena', 'injoignable', 'Athena ne joint plus sa base : prévenez le staff, rien ne peut remonter.');
        }

        // 9. Erreurs récentes de ce joueur (ou de la communauté) sur l'API du jeu, 15 dernières minutes.
        $recent = $this->recentErrors($tenantId, $userId);
        if ($recent === null) {
            $add('errors', 'info', 'Erreurs récentes', 'non disponibles');
        } elseif ($recent === []) {
            $add('errors', 'ok', 'Erreurs récentes', 'aucune (15 min)');
        } else {
            $parts = [];
            foreach ($recent as $r) {
                $parts[] = $r['route'] . ' ' . $r['code'] . ' ×' . $r['n'];
            }
            $add('errors', 'warn', 'Erreurs récentes', implode(' ; ', $parts), 'Ces appels ont échoué récemment côté Athena (codes HTTP).');
        }

        // 10. Versions publiées (le jeu compare avec les siennes).
        try {
            $rel = AtakReleaseInfo::latest();
            $add('versions', 'info', 'Versions publiées', 'ATAK ' . ($rel['atak_native'] ?? '?') . ' · Link ' . ($rel['link'] ?? '?'));
        } catch (\Throwable) {
        }

        $worst = 'ok';
        foreach ($checks as $c) {
            if ($c['level'] === 'ko') {
                $worst = 'ko';
                break;
            }
            if ($c['level'] === 'warn') {
                $worst = 'warn';
            }
        }

        return Response::json([
            'ok' => $worst !== 'ko',
            'verdict' => $worst,
            'server_time' => gmdate('Y-m-d\TH:i:s\Z'),
            'server_unix' => time(),
            'checks' => $checks,
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * @return list<array{route: string, code: int, n: int}>|null null si la table n'existe pas
     */
    private function recentErrors(int $tenantId, int $userId): ?array
    {
        if ($tenantId < 1 && $userId < 1) {
            return [];
        }
        try {
            $where = $userId > 0 ? 'user_id = ?' : 'tenant_id = ?';
            $stmt = Database::getPdo()->prepare(
                "SELECT route_path, status_code, COUNT(*) AS n FROM request_telemetry
                 WHERE {$where} AND route_path LIKE '/api/%' AND status_code >= 400
                   AND created_at >= NOW() - INTERVAL 15 MINUTE
                 GROUP BY route_path, status_code ORDER BY n DESC LIMIT 5"
            );
            $stmt->execute([$userId > 0 ? $userId : $tenantId]);
            $out = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $out[] = ['route' => (string) $row['route_path'], 'code' => (int) $row['status_code'], 'n' => (int) $row['n']];
            }

            return $out;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    private function repo(string $class): object
    {
        try {
            $o = Container::get($class);
            if ($o instanceof $class) {
                return $o;
            }
        } catch (\Throwable) {
        }

        return new $class();
    }
}
