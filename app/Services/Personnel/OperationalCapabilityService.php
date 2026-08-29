<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Core\Database;
use App\Repositories\GradeRepository;
use App\Repositories\PersonnelCareerEventRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\UserRepository;
use PDO;

/**
 * Calcule la capacité opérationnelle sans confondre les axes.
 * Grade haut + fonction intérimaire + quals VALID ≠ déployable si currency morte.
 */
final class OperationalCapabilityService
{
    public function __construct(
        private UserRepository $users,
        private PersonnelProfileRepository $profiles,
        private GradeRepository $grades,
        private QualificationCurrencyService $currency,
        private PersonnelCareerEventRepository $careerEvents,
    ) {}

    public function snapshotForUser(int $tenantId, int $userId): PersonnelCapabilityAxes
    {
        $user = $this->users->findById($userId, $tenantId) ?? [];
        $profile = $this->profiles->getByUserId($userId) ?? [];

        $gradeId = (int) ($user['grade_id'] ?? 0);
        $grade = null;
        if ($gradeId > 0) {
            $g = $this->grades->findById($gradeId);
            if (is_array($g)) {
                $grade = [
                    'id' => $gradeId,
                    'name' => (string) ($g['label_long'] ?? $g['name'] ?? $g['label_short'] ?? ''),
                    'short_name' => (string) ($g['label_short'] ?? $g['short_name'] ?? ''),
                    'nato_code' => (string) ($g['label_otan'] ?? $g['nato_code'] ?? ''),
                    'sort_order' => (int) ($g['sort_order'] ?? $g['rank_order'] ?? 0),
                ];
            }
        }

        $function = $this->resolveFunctionAxis($tenantId, $userId, $profile);
        $quals = [];
        foreach ($this->currency->listForUser($tenantId, $userId) as $q) {
            $quals[] = [
                'id' => (int) ($q['id'] ?? 0),
                'name' => (string) ($q['qualification_name'] ?? ''),
                'admin_status' => (string) ($q['status'] ?? ''),
                'admin_valid' => $this->currency->isAdministrativelyValid($q),
                'currency_status' => (string) ($q['currency_status'] ?? 'UNKNOWN'),
                'is_current' => $this->currency->isCurrent($q),
                'expires_at' => $q['expires_at'] ?? null,
                'currency_expires_at' => $q['currency_expires_at'] ?? null,
                'last_practiced_at' => $q['last_practiced_at'] ?? null,
            ];
        }

        $blocking = [];
        $availability = strtoupper(trim((string) ($profile['operator_status'] ?? 'AVAILABLE')));
        if ($availability === '') {
            $availability = 'AVAILABLE';
        }
        /* Map free-text legacy toward enum-ish codes when possible. */
        $availabilityMap = [
            'ACTIVE' => 'AVAILABLE',
            'DEPLOYABLE' => 'AVAILABLE',
            'NON_DEPLOYABLE' => 'LIMITED',
            'LEAVE' => 'LOA',
            'INACTIVE' => 'ABSENT',
        ];
        $availability = $availabilityMap[$availability] ?? $availability;

        if (in_array($availability, ['SUSPENDED', 'MEDICAL', 'ABSENT', 'LOA'], true)) {
            $blocking[] = 'AVAILABILITY_' . $availability;
        }

        $nonCurrent = 0;
        foreach ($quals as $q) {
            if (!empty($q['admin_valid']) && empty($q['is_current'])) {
                ++$nonCurrent;
                $blocking[] = 'MISSING_CURRENCY:' . ($q['name'] !== '' ? $q['name'] : ('#' . $q['id']));
            }
        }

        $deployableFlag = !empty($profile['deployable']);
        if ($blocking !== []) {
            $deployableFlag = false;
        }

        $readiness = (int) ($profile['readiness_score'] ?? 0);
        if ($nonCurrent > 0) {
            $readiness = max(0, $readiness - min(40, $nonCurrent * 15));
        }
        if (in_array($availability, ['LIMITED', 'RESERVE'], true)) {
            $readiness = max(0, $readiness - 10);
        }

        $capability = [
            'availability' => $availability,
            'deployable' => $deployableFlag ? 1 : 0,
            'readiness_percent' => max(0, min(100, $readiness)),
            'blocking_codes' => array_values(array_unique($blocking)),
            'non_current_qualifications' => $nonCurrent,
        ];

        return new PersonnelCapabilityAxes($tenantId, $userId, $grade, $function, $quals, $capability);
    }

    /**
     * Persiste le snapshot capacité (axe 4 uniquement — ne touche pas grade/fonction/qual).
     */
    public function persistCapability(PersonnelCapabilityAxes $axes): void
    {
        $pdo = Database::getPdo();
        if (!$this->capabilitySchemaReady($pdo)) {
            return;
        }
        $cap = $axes->capability ?? [];
        $blocking = json_encode($axes->blockingCodes(), JSON_UNESCAPED_UNICODE);
        $snap = json_encode($axes->toArray(), JSON_UNESCAPED_UNICODE);
        $st = $pdo->prepare(
            'INSERT INTO personnel_operational_capability
                (tenant_id, user_id, availability, deployable, readiness_percent, blocking_codes, snapshot_json, computed_at)
             VALUES (?,?,?,?,?,?,?,NOW())
             ON DUPLICATE KEY UPDATE
                availability = VALUES(availability),
                deployable = VALUES(deployable),
                readiness_percent = VALUES(readiness_percent),
                blocking_codes = VALUES(blocking_codes),
                snapshot_json = VALUES(snapshot_json),
                computed_at = NOW()'
        );
        $st->execute([
            $axes->tenantId,
            $axes->userId,
            (string) ($cap['availability'] ?? 'AVAILABLE'),
            !empty($cap['deployable']) ? 1 : 0,
            (int) ($cap['readiness_percent'] ?? 0),
            is_string($blocking) ? $blocking : '[]',
            is_string($snap) ? $snap : null,
        ]);
    }

    public function recomputeAndPersist(int $tenantId, int $userId, bool $recordEvent = true): PersonnelCapabilityAxes
    {
        $this->currency->refreshForUser($tenantId, $userId);
        $axes = $this->snapshotForUser($tenantId, $userId);
        $previous = $this->loadPreviousBlocking($tenantId, $userId);
        $this->persistCapability($axes);
        if ($recordEvent) {
            $codes = $axes->blockingCodes();
            sort($codes);
            $prevSorted = $previous;
            sort($prevSorted);
            if ($codes !== $prevSorted || $previous === null) {
                $this->careerEvents->record($tenantId, $userId, 'CAPABILITY_RECOMPUTED', null, [
                    'deployable' => $axes->isDeployable(),
                    'readiness_percent' => $axes->readinessPercent(),
                    'blocking_codes' => $axes->blockingCodes(),
                ]);
            }
        }

        return $axes;
    }

    /**
     * @return list<string>|null null = pas encore de snapshot
     */
    private function loadPreviousBlocking(int $tenantId, int $userId): ?array
    {
        $pdo = Database::getPdo();
        if (!$this->capabilitySchemaReady($pdo)) {
            return null;
        }
        try {
            $st = $pdo->prepare(
                'SELECT blocking_codes FROM personnel_operational_capability WHERE tenant_id = ? AND user_id = ? LIMIT 1'
            );
            $st->execute([$tenantId, $userId]);
            $raw = $st->fetchColumn();
            if ($raw === false) {
                return null;
            }
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            if (!is_array($decoded)) {
                return [];
            }

            return array_values(array_filter(array_map('strval', $decoded)));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Effectif théorique / réel pour les billets d’une unité.
     *
     * @return array{unit_id: int, billets: list<array<string, mixed>>, deployable: bool, readiness_percent: int, gaps: list<string>}
     */
    public function unitManningSnapshot(int $tenantId, int $unitId): array
    {
        $pdo = Database::getPdo();
        $out = [
            'unit_id' => $unitId,
            'billets' => [],
            'deployable' => true,
            'readiness_percent' => 100,
            'gaps' => [],
        ];
        if (!$this->billetSchemaReady($pdo)) {
            return $out;
        }
        $st = $pdo->prepare(
            'SELECT b.*,
                    (SELECT COUNT(*) FROM orbat_billet_holders h
                     WHERE h.billet_id = b.id AND h.holder_role = \'PRIMARY\'
                       AND (h.ends_at IS NULL OR h.ends_at >= CURDATE())) AS filled_primary
             FROM orbat_billets b
             WHERE b.tenant_id = ? AND b.unit_id = ? AND b.is_active = 1
             ORDER BY b.title ASC'
        );
        $st->execute([$tenantId, $unitId]);
        $criticalGaps = 0;
        $totalSlots = 0;
        $filledSlots = 0;
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $auth = max(1, (int) ($row['authorized_slots'] ?? 1));
            $filled = (int) ($row['filled_primary'] ?? 0);
            $totalSlots += $auth;
            $filledSlots += min($auth, $filled);
            $gap = max(0, $auth - $filled);
            $item = [
                'id' => (int) $row['id'],
                'code' => (string) ($row['code'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'authorized' => $auth,
                'filled' => $filled,
                'display' => $filled . ' / ' . $auth,
                'is_critical' => !empty($row['is_critical']),
                'gap' => $gap,
            ];
            $out['billets'][] = $item;
            if ($gap > 0 && !empty($row['is_critical'])) {
                ++$criticalGaps;
                $out['gaps'][] = 'CRITICAL_BILLET_GAP:' . $item['code'];
                $out['deployable'] = false;
            }
        }
        $out['readiness_percent'] = $totalSlots > 0
            ? (int) round(100 * $filledSlots / $totalSlots)
            : 100;
        if ($criticalGaps > 0) {
            $out['readiness_percent'] = min($out['readiness_percent'], 70);
        }

        if ($this->capabilitySchemaReady($pdo)) {
            $jsonM = json_encode($out['billets'], JSON_UNESCAPED_UNICODE);
            $jsonG = json_encode($out['gaps'], JSON_UNESCAPED_UNICODE);
            $up = $pdo->prepare(
                'INSERT INTO unit_operational_capability
                    (tenant_id, unit_id, readiness_percent, deployable, manning_json, gaps_json, computed_at)
                 VALUES (?,?,?,?,?,?,NOW())
                 ON DUPLICATE KEY UPDATE
                    readiness_percent = VALUES(readiness_percent),
                    deployable = VALUES(deployable),
                    manning_json = VALUES(manning_json),
                    gaps_json = VALUES(gaps_json),
                    computed_at = NOW()'
            );
            $up->execute([
                $tenantId,
                $unitId,
                $out['readiness_percent'],
                $out['deployable'] ? 1 : 0,
                is_string($jsonM) ? $jsonM : null,
                is_string($jsonG) ? $jsonG : null,
            ]);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $profile
     * @return array<string, mixed>
     */
    private function resolveFunctionAxis(int $tenantId, int $userId, array $profile): array
    {
        $primaryRole = trim((string) ($profile['primary_role'] ?? ''));
        $unitId = (int) ($profile['primary_unit_id'] ?? 0);
        $acting = null;
        $pdo = Database::getPdo();
        if ($this->tempAssignSchemaReady($pdo)) {
            $st = $pdo->prepare(
                'SELECT * FROM personnel_temporary_assignments
                 WHERE tenant_id = ? AND user_id = ?
                   AND starts_at <= CURDATE()
                   AND (ends_at IS NULL OR ends_at >= CURDATE())
                 ORDER BY starts_at DESC LIMIT 1'
            );
            $st->execute([$tenantId, $userId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                $acting = [
                    'type' => (string) ($row['assignment_type'] ?? 'ACTING'),
                    'title' => (string) ($row['title'] ?? ''),
                    'does_not_change_grade' => !empty($row['does_not_change_grade']),
                    'starts_at' => (string) ($row['starts_at'] ?? ''),
                    'ends_at' => $row['ends_at'] ?? null,
                ];
            }
        }

        return [
            'primary_role' => $primaryRole,
            'primary_unit_id' => $unitId > 0 ? $unitId : null,
            'temporary_assignment' => $acting,
            'note' => 'Function/billet is independent from grade_level.',
        ];
    }

    private function capabilitySchemaReady(PDO $pdo): bool
    {
        try {
            $st = $pdo->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_operational_capability' LIMIT 1"
            );

            return (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    private function billetSchemaReady(PDO $pdo): bool
    {
        try {
            $st = $pdo->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orbat_billets' LIMIT 1"
            );

            return (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    private function tempAssignSchemaReady(PDO $pdo): bool
    {
        try {
            $st = $pdo->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_temporary_assignments' LIMIT 1"
            );

            return (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }
}
