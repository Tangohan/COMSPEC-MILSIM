<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Container;
use App\Repositories\CommunityEventRepository;
use App\Services\Personnel\UnitReadinessService;

/**
 * Agrégats du tableau de bord de pilotage (back-office communauté) qui n’existaient pas encore :
 * participation aux derniers créneaux, présence sur 30 jours, créneaux du mois et état des unités.
 *
 * Chaque bloc est indépendant : une erreur renvoie un bloc vide, jamais une page cassée.
 * L’état des unités (calcul coûteux, plusieurs requêtes par unité) est gardé quelques minutes en cache fichier.
 */
final class PilotageDashboardService
{
    public const UNITS_CACHE_TTL = 300;

    public function __construct(
        private ?CommunityEventRepository $events = null,
        private ?UnitReadinessService $readiness = null,
        private ?string $cacheDir = null,
    ) {
    }

    /**
     * @return list<array{id:int,title:string,starts_at:string,event_type:string,registered:int,present:int}>
     */
    public function participation(int $tenantId, int $limit = 10): array
    {
        if ($tenantId < 1) {
            return [];
        }
        try {
            return $this->events()->participationForRecentEvents($tenantId, $limit);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array{current_yes:int,current_present:int,current_checked:int,previous_yes:int,previous_present:int}|null
     */
    public function presence(int $tenantId, int $windowDays = 30): ?array
    {
        if ($tenantId < 1) {
            return null;
        }
        try {
            return $this->events()->presenceWindowsForTenant($tenantId, $windowDays);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Créneaux du mois en cours (calendrier du serveur).
     *
     * @return array{total:int,done:int,month:int}|null
     */
    public function monthEvents(int $tenantId, ?int $now = null): ?array
    {
        if ($tenantId < 1) {
            return null;
        }
        $now ??= time();
        $start = date('Y-m-01 00:00:00', $now);
        $end = date('Y-m-01 00:00:00', (int) strtotime('+1 month', (int) strtotime($start)));
        try {
            $counts = $this->events()->countStartingInPeriod($tenantId, $start, $end);
        } catch (\Throwable) {
            return null;
        }

        return ['total' => $counts['total'], 'done' => $counts['done'], 'month' => (int) date('n', $now)];
    }

    /**
     * Score de disponibilité par unité (pourvu / autorisé, qualifications à jour, dernière activité).
     *
     * @return list<array<string, mixed>>
     */
    public function unitReadiness(int $tenantId): array
    {
        if ($tenantId < 1) {
            return [];
        }
        $file = $this->cacheFile($tenantId);
        if ($file !== null && is_file($file) && (time() - (int) @filemtime($file)) < self::UNITS_CACHE_TTL) {
            $cached = json_decode((string) @file_get_contents($file), true);
            if (is_array($cached) && array_is_list($cached)) {
                return $cached;
            }
        }
        try {
            $rows = $this->readiness()->scoresForTenant($tenantId);
        } catch (\Throwable) {
            return [];
        }
        if ($file !== null) {
            $dir = dirname($file);
            if (is_dir($dir) || @mkdir($dir, 0775, true)) {
                @file_put_contents($file, (string) json_encode($rows, JSON_UNESCAPED_UNICODE), LOCK_EX);
            }
        }

        return $rows;
    }

    private function cacheFile(int $tenantId): ?string
    {
        $dir = $this->cacheDir;
        if ($dir === null) {
            if (!function_exists('base_path')) {
                return null;
            }
            $dir = base_path('storage/cache/pilotage');
        }

        return rtrim($dir, '/\\') . '/unit_readiness_' . $tenantId . '.json';
    }

    private function events(): CommunityEventRepository
    {
        return $this->events ??= new CommunityEventRepository();
    }

    private function readiness(): UnitReadinessService
    {
        if ($this->readiness === null) {
            $service = Container::get(UnitReadinessService::class);
            if (!$service instanceof UnitReadinessService) {
                throw new \RuntimeException('Service de disponibilité indisponible.');
            }
            $this->readiness = $service;
        }

        return $this->readiness;
    }
}
