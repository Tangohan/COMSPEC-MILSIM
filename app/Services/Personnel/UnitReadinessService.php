<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Core\Database;
use App\Repositories\OrbatBilletRepository;
use App\Repositories\QualificationAwardRepository;
use App\Repositories\UnitRepository;
use App\Services\Organization\OrgVisibilityService;
use App\Support\ConfidentialDisplay;
use App\Support\OrgVisibilityCapabilities;
use App\Support\VisibilityLevel;
use PDO;

/**
 * Score de disponibilité par unité : pourvu/autorisé + quals à jour + dernière activité.
 */
final class UnitReadinessService
{
    public function __construct(
        private OrbatBilletRepository $billets,
        private QualificationAwardRepository $awards,
        private QualificationTemporalStatusService $temporal,
        private ?UnitRepository $units = null,
    ) {
        $this->units ??= new UnitRepository();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function scoresForTenant(int $tenantId): array
    {
        $units = [];
        try {
            $units = $this->units->listFlatForStructure($tenantId);
        } catch (\Throwable) {
            $units = $this->fallbackUnits($tenantId);
        }
        $out = [];
        foreach ($units as $unit) {
            $unitId = (int) ($unit['id'] ?? 0);
            if ($unitId < 1) {
                continue;
            }
            $out[] = $this->scoreForUnit($tenantId, $unit);
        }
        usort($out, static fn (array $a, array $b): int => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        return $out;
    }

    /**
     * Applique la confidentialité des unités au tableau de disponibilité (mêmes règles que l’ORBAT) :
     * unité masquée retirée, anonymisée sous le libellé commun, pastille de niveau pour les lecteurs habilités.
     * Ajoute aussi le nom de l’unité parente quand il est affichable.
     *
     * @param list<array<string, mixed>> $scores
     * @param list<array<string, mixed>> $units Lignes {id, name, parent_id, visibility_level?}
     * @return list<array<string, mixed>>
     */
    public static function applyVisibility(array $scores, array $units, OrgVisibilityCapabilities $caps): array
    {
        $levels = OrgVisibilityService::effectiveUnitLevels($units);
        $byId = [];
        foreach ($units as $u) {
            $byId[(int) ($u['id'] ?? 0)] = $u;
        }
        $staff = $caps->bypassAll || $caps->viewHiddenUnits || $caps->viewRestrictedUnits;
        $displayName = static function (int $id) use ($levels, $byId, $caps, $staff): ?string {
            $level = $levels[$id] ?? VisibilityLevel::NORMAL;
            if ($level === VisibilityLevel::HIDDEN && !$caps->canSeeUnitLevel(VisibilityLevel::HIDDEN)) {
                return null;
            }
            if ($level === VisibilityLevel::ANONYMIZED && !$staff) {
                return ConfidentialDisplay::label(ConfidentialDisplay::UNIT_ANONYMIZED);
            }

            return (string) ($byId[$id]['name'] ?? '');
        };
        $out = [];
        foreach ($scores as $s) {
            $id = (int) ($s['unit_id'] ?? 0);
            $name = $displayName($id);
            if ($name === null) {
                continue;
            }
            $level = $levels[$id] ?? VisibilityLevel::NORMAL;
            $kind = ConfidentialDisplay::kindForLevel($level, 'unit');
            $s['name'] = $name !== '' ? $name : (string) ($s['name'] ?? 'Unité');
            $s['confidential'] = $staff
                ? ConfidentialDisplay::staffPayload($level, 'unit')
                : ($kind !== null ? ConfidentialDisplay::payload($kind) : null);
            $s['anonymized'] = $level === VisibilityLevel::ANONYMIZED && !$staff;
            $parentId = (int) ($byId[$id]['parent_id'] ?? 0);
            $parentName = $parentId > 0 && isset($byId[$parentId]) ? $displayName($parentId) : null;
            $s['parent_name'] = $s['anonymized'] ? '' : (string) ($parentName ?? '');
            $out[] = $s;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $unit
     * @return array<string, mixed>
     */
    public function scoreForUnit(int $tenantId, array $unit): array
    {
        $unitId = (int) ($unit['id'] ?? 0);
        $manning = ['authorized' => 0, 'filled' => 0, 'vacant' => 0, 'billets' => []];
        try {
            $manning = $this->billets->manningForUnit($tenantId, $unitId);
        } catch (\Throwable) {
        }
        $authorized = max(0, (int) ($manning['authorized'] ?? 0));
        $filled = max(0, (int) ($manning['filled'] ?? 0));
        $fillRatio = $authorized > 0 ? $filled / $authorized : ($filled > 0 ? 1.0 : 0.0);

        $memberIds = [];
        foreach ($manning['billets'] ?? [] as $b) {
            foreach ($b['holders'] ?? [] as $h) {
                $uid = (int) ($h['user_id'] ?? $h['personnel_id'] ?? 0);
                if ($uid > 0) {
                    $memberIds[$uid] = $uid;
                }
            }
        }
        if ($memberIds === []) {
            $memberIds = $this->memberIdsForUnit($tenantId, $unitId);
        }

        $qualOk = 0;
        $qualTotal = 0;
        $lastActivity = null;
        // Qualifications et dernière activité de tous les membres de l’unité : 3 lectures au plus pour l’unité,
        // au lieu de 2 à 3 par membre.
        $memberIdList = array_values(array_map('intval', $memberIds));
        try {
            $awardsByUser = $memberIdList !== [] ? $this->awards->listForUsers($memberIdList, $tenantId) : [];
        } catch (\Throwable) {
            $awardsByUser = [];
        }
        $activityByUser = $this->lastActivityForUsers($tenantId, $memberIdList);
        foreach ($memberIds as $uid) {
            foreach ($awardsByUser[(int) $uid] ?? [] as $row) {
                $qualTotal++;
                if ($this->temporal->isEffectivelyActive($row)) {
                    $qualOk++;
                }
            }
            $act = $activityByUser[(int) $uid] ?? null;
            if ($act !== null && ($lastActivity === null || $act > $lastActivity)) {
                $lastActivity = $act;
            }
        }
        $qualRatio = $qualTotal > 0 ? $qualOk / $qualTotal : 1.0;

        $activityScore = 0.5;
        if ($lastActivity !== null) {
            $days = (int) floor((time() - strtotime($lastActivity)) / 86400);
            $activityScore = $days <= 7 ? 1.0 : ($days <= 30 ? 0.75 : ($days <= 90 ? 0.45 : 0.2));
        }

        $score = (int) round(100 * (0.45 * $fillRatio + 0.40 * $qualRatio + 0.15 * $activityScore));

        return [
            'unit_id' => $unitId,
            'name' => (string) ($unit['name'] ?? 'Unité'),
            'authorized' => $authorized,
            'filled' => $filled,
            'vacant' => max(0, $authorized - $filled),
            'qual_ok' => $qualOk,
            'qual_total' => $qualTotal,
            'last_activity' => $lastActivity,
            'score' => max(0, min(100, $score)),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fallbackUnits(int $tenantId): array
    {
        try {
            $st = Database::getPdo()->prepare(
                'SELECT id, name FROM units WHERE tenant_id = ? ORDER BY name ASC'
            );
            $st->execute([$tenantId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<int, int> */
    private function memberIdsForUnit(int $tenantId, int $unitId): array
    {
        // users n'a ni user_id ni unit_id : l'ancienne requête échouait toujours (unité sans membres).
        // Même règle d'appartenance que la fiche, les rosters et l'ORBAT (affectations, sinon user_units).
        foreach ([true, false] as $hasAssignments) {
            try {
                $membership = UnitAssignmentConsistency::membershipSql($hasAssignments, ' AND pa.unit_id = ?', ' AND uu.unit_id = ?');
                $st = Database::getPdo()->prepare(
                    'SELECT DISTINCT m.user_id FROM (' . $membership . ') m
                     INNER JOIN units un ON un.id = m.unit_id AND un.tenant_id = ?
                     INNER JOIN users u ON u.id = m.user_id AND u.status = \'active\''
                );
                $st->execute($hasAssignments ? [$unitId, $unitId, $tenantId] : [$unitId, $tenantId]);
                $ids = [];
                foreach ($st->fetchAll(PDO::FETCH_COLUMN) ?: [] as $id) {
                    $ids[(int) $id] = (int) $id;
                }

                return $ids;
            } catch (\Throwable) {
            }
        }

        return [];
    }

    /**
     * Dernière activité par membre : dernier événement du journal de carrière, sinon dernière connexion (même
     * règle qu’avant, lue pour tous les membres à la fois).
     *
     * @param list<int> $userIds
     * @return array<int, string>
     */
    private function lastActivityForUsers(int $tenantId, array $userIds): array
    {
        $out = [];
        if ($userIds === []) {
            return $out;
        }
        $pdo = Database::getPdo();
        foreach ([
            'SELECT user_id AS uid, MAX(COALESCE(effective_at, created_at)) AS v FROM personnel_career_journal WHERE tenant_id = ? AND user_id IN (%s) GROUP BY user_id',
            'SELECT id AS uid, last_login_at AS v FROM users WHERE tenant_id = ? AND id IN (%s)',
        ] as $sql) {
            $missing = array_values(array_filter($userIds, static fn (int $id): bool => !isset($out[$id])));
            foreach (array_chunk($missing, 500) as $chunk) {
                try {
                    $st = $pdo->prepare(sprintf($sql, implode(',', array_fill(0, count($chunk), '?'))));
                    $st->execute([$tenantId, ...$chunk]);
                    foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                        $v = $row['v'] ?? null;
                        if (is_string($v) && $v !== '' && !str_starts_with($v, '0000')) {
                            $out[(int) $row['uid']] = $v;
                        }
                    }
                } catch (\Throwable) {
                }
            }
        }

        return $out;
    }
}
