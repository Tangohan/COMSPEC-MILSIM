<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Core\Database;
use App\Repositories\PersonnelCareerEventRepository;
use PDO;

/**
 * Évaluateur de progression (idempotent). Lot 1 : détecte le schéma et n’agit
 * que lorsqu’il existe des parcours publiés + memberships — sinon no-op sûr.
 */
final class PersonnelProgressionEvaluator
{
    public function __construct(
        private PersonnelCareerEventRepository $careerEvents,
    ) {}

    /**
     * @return array{tenants: int, memberships: int, requests_created: int, skipped: int, details: list<string>}
     */
    public function evaluateAllActiveTenants(): array
    {
        $stats = [
            'tenants' => 0,
            'memberships' => 0,
            'requests_created' => 0,
            'skipped' => 0,
            'details' => [],
        ];
        $pdo = Database::getPdo();
        if (!$this->schemaReady($pdo)) {
            $stats['details'][] = 'Schéma progression absent — rien à évaluer.';

            return $stats;
        }

        $tenantIds = $pdo->query(
            "SELECT DISTINCT t.id
             FROM tenants t
             INNER JOIN personnel_progression_tracks tr ON tr.tenant_id = t.id AND tr.status = 'PUBLISHED' AND tr.is_active = 1"
        )->fetchAll(PDO::FETCH_COLUMN);
        if (!is_array($tenantIds) || $tenantIds === []) {
            $stats['details'][] = 'Aucun parcours publié — no-op.';

            return $stats;
        }

        foreach ($tenantIds as $tenantIdRaw) {
            $tenantId = (int) $tenantIdRaw;
            if ($tenantId < 1) {
                continue;
            }
            ++$stats['tenants'];
            $one = $this->evaluateTenant($pdo, $tenantId);
            $stats['memberships'] += $one['memberships'];
            $stats['requests_created'] += $one['requests_created'];
            $stats['skipped'] += $one['skipped'];
        }

        return $stats;
    }

    /**
     * @return array{memberships: int, requests_created: int, skipped: int}
     */
    public function evaluateTenant(PDO $pdo, int $tenantId): array
    {
        $out = ['memberships' => 0, 'requests_created' => 0, 'skipped' => 0];
        $st = $pdo->prepare(
            "SELECT m.id, m.user_id, m.track_id, m.current_stage_id, m.status
             FROM personnel_progression_memberships m
             INNER JOIN personnel_progression_tracks t ON t.id = m.track_id AND t.tenant_id = m.tenant_id
             WHERE m.tenant_id = ? AND m.status = 'ACTIVE' AND t.status = 'PUBLISHED' AND t.is_active = 1"
        );
        $st->execute([$tenantId]);
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            ++$out['memberships'];
            if ($this->hasActiveHold($pdo, $tenantId, (int) $row['user_id'], (int) $row['track_id'])) {
                ++$out['skipped'];
                continue;
            }
            // Lot 1 : pas encore d’évaluation de conditions métier — réservé aux lots suivants.
            // L’idempotence est garantie : aucune demande créée sans transition calculée.
            ++$out['skipped'];
        }

        return $out;
    }

    private function hasActiveHold(PDO $pdo, int $tenantId, int $userId, int $trackId): bool
    {
        $st = $pdo->prepare(
            'SELECT 1 FROM personnel_progression_holds
             WHERE tenant_id = ?
               AND (user_id IS NULL OR user_id = ?)
               AND (track_id IS NULL OR track_id = ?)
               AND starts_at <= NOW()
               AND (ends_at IS NULL OR ends_at > NOW())
             LIMIT 1'
        );
        $st->execute([$tenantId, $userId, $trackId]);

        return (bool) $st->fetchColumn();
    }

    private function schemaReady(PDO $pdo): bool
    {
        try {
            $st = $pdo->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_progression_memberships' LIMIT 1"
            );

            return (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }
}
