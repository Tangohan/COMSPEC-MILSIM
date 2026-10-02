<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Écriture du cadre compétences d'une organisation : modules (ALPHA → DELTA), activation pour le tenant,
 * renouvellement, prérequis et progression des membres.
 *
 * Les tables viennent de migrations/20260408000001_competency_progression_framework.sql.
 * SQL volontairement portable (pas de ON DUPLICATE KEY) : lecture puis insertion ou mise à jour.
 */
class CompetencyModuleRepository
{
    public const PHASES = ['ALPHA', 'BRAVO', 'CHARLIE', 'DELTA'];
    public const DELIVERY_MODES = ['INITIAL', 'RENFORCE', 'RECYCLAGE', 'CRITIQUE'];
    public const STATUSES = ['NOT_STARTED', 'IN_PROGRESS', 'COMPLETED', 'FAILED', 'EXPIRED'];

    private ?PDO $pdo;
    private ?bool $ready = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    public function schemaReady(): bool
    {
        if ($this->ready !== null) {
            return $this->ready;
        }
        try {
            foreach (['modules', 'tenant_modules', 'recurrence_rules', 'module_dependencies', 'user_progress'] as $t) {
                $this->pdo()->query('SELECT 1 FROM ' . $t . ' LIMIT 1');
            }
            $this->ready = true;
        } catch (\Throwable) {
            $this->ready = false;
        }

        return $this->ready;
    }

    /**
     * Tous les modules de l'organisation (actifs et archivés), avec activation, renouvellement,
     * prérequis et compteurs de progression.
     *
     * @return list<array<string, mixed>>
     */
    public function listForTenant(int $tenantId): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT m.id, m.code, m.name, m.module_type, m.delivery_mode, m.description, m.duration_min,
                    m.is_active AS module_active, m.updated_at,
                    tm.id AS tm_id, tm.is_active AS tenant_active, tm.is_mandatory, tm.custom_order,
                    r.recurrence_type, r.interval_days
             FROM modules m
             LEFT JOIN tenant_modules tm ON tm.module_id = m.id AND tm.tenant_id = m.tenant_id
             LEFT JOIN recurrence_rules r ON r.module_id = m.id
             WHERE m.tenant_id = ?
             ORDER BY CASE m.module_type WHEN \'ALPHA\' THEN 1 WHEN \'BRAVO\' THEN 2 WHEN \'CHARLIE\' THEN 3 ELSE 4 END,
                      (tm.custom_order IS NULL), tm.custom_order, m.code'
        );
        $st->execute([$tenantId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows === []) {
            return [];
        }
        $ids = array_map(static fn (array $r): int => (int) $r['id'], $rows);
        $prereqs = $this->prerequisitesFor($ids);
        $counts = $this->progressCounts($tenantId, $ids);
        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            $out[] = $this->normalize($r) + [
                'prereq_ids' => $prereqs[$id] ?? [],
                'progress' => $counts[$id] ?? ['COMPLETED' => 0, 'IN_PROGRESS' => 0, 'FAILED' => 0, 'EXPIRED' => 0],
            ];
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $moduleId): ?array
    {
        foreach ($this->listForTenant($tenantId) as $m) {
            if ($m['id'] === $moduleId) {
                return $m;
            }
        }

        return null;
    }

    public function codeTaken(int $tenantId, string $code, int $exceptId = 0): bool
    {
        $st = $this->pdo()->prepare('SELECT id FROM modules WHERE tenant_id = ? AND code = ? AND id <> ? LIMIT 1');
        $st->execute([$tenantId, $code, $exceptId]);

        return (bool) $st->fetchColumn();
    }

    /**
     * Crée ou met à jour un module. Les entrées sont déjà validées (CompetencyModuleService).
     *
     * @param array{code:string,name:string,module_type:string,delivery_mode:string,description:string,
     *              duration_min:?int,is_active:bool,is_mandatory:bool,custom_order:?int,recurrence_days:?int,
     *              prereq_ids:list<int>} $data
     */
    public function save(int $tenantId, int $actorUserId, ?int $moduleId, array $data): int
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            if ($moduleId === null) {
                $st = $pdo->prepare(
                    'INSERT INTO modules (tenant_id, code, name, module_type, delivery_mode, description, duration_min,
                                          is_active, is_mandatory_default, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)'
                );
                $st->execute([
                    $tenantId, $data['code'], $data['name'], $data['module_type'], $data['delivery_mode'],
                    $data['description'] !== '' ? $data['description'] : null, $data['duration_min'],
                    $data['is_mandatory'] ? 1 : 0, $actorUserId > 0 ? $actorUserId : null,
                ]);
                $moduleId = (int) $pdo->lastInsertId();
            } else {
                $st = $pdo->prepare(
                    'UPDATE modules SET code = ?, name = ?, module_type = ?, delivery_mode = ?, description = ?,
                            duration_min = ?, is_mandatory_default = ?, is_active = 1
                     WHERE id = ? AND tenant_id = ?'
                );
                $st->execute([
                    $data['code'], $data['name'], $data['module_type'], $data['delivery_mode'],
                    $data['description'] !== '' ? $data['description'] : null, $data['duration_min'],
                    $data['is_mandatory'] ? 1 : 0, $moduleId, $tenantId,
                ]);
            }

            $this->upsertTenantModule($tenantId, $moduleId, $data['is_active'], $data['is_mandatory'], $data['custom_order']);
            $this->upsertRecurrence($moduleId, $data['recurrence_days'], $data['is_mandatory']);
            $this->replacePrerequisites($moduleId, $data['prereq_ids']);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $moduleId;
    }

    /** Active ou retire un module du parcours de l'organisation (le module et l'historique restent). */
    public function setActive(int $tenantId, int $moduleId, bool $active): bool
    {
        $st = $this->pdo()->prepare('SELECT is_mandatory, custom_order FROM tenant_modules WHERE tenant_id = ? AND module_id = ?');
        $st->execute([$tenantId, $moduleId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        $exists = $this->pdo()->prepare('SELECT 1 FROM modules WHERE id = ? AND tenant_id = ?');
        $exists->execute([$moduleId, $tenantId]);
        if (!$exists->fetchColumn()) {
            return false;
        }
        $this->upsertTenantModule(
            $tenantId,
            $moduleId,
            $active,
            is_array($row) && (int) $row['is_mandatory'] === 1,
            is_array($row) && $row['custom_order'] !== null ? (int) $row['custom_order'] : null
        );

        return true;
    }

    private function upsertTenantModule(int $tenantId, int $moduleId, bool $active, bool $mandatory, ?int $order): void
    {
        $st = $this->pdo()->prepare('SELECT id FROM tenant_modules WHERE tenant_id = ? AND module_id = ?');
        $st->execute([$tenantId, $moduleId]);
        $id = $st->fetchColumn();
        if ($id) {
            $this->pdo()->prepare('UPDATE tenant_modules SET is_active = ?, is_mandatory = ?, custom_order = ? WHERE id = ?')
                ->execute([$active ? 1 : 0, $mandatory ? 1 : 0, $order, (int) $id]);
        } else {
            $this->pdo()->prepare('INSERT INTO tenant_modules (tenant_id, module_id, is_active, is_mandatory, custom_order) VALUES (?, ?, ?, ?, ?)')
                ->execute([$tenantId, $moduleId, $active ? 1 : 0, $mandatory ? 1 : 0, $order]);
        }
    }

    private function upsertRecurrence(int $moduleId, ?int $days, bool $mandatory): void
    {
        $type = $days !== null && $days > 0 ? 'PERIODIC' : 'NONE';
        $st = $this->pdo()->prepare('SELECT id FROM recurrence_rules WHERE module_id = ?');
        $st->execute([$moduleId]);
        $id = $st->fetchColumn();
        if ($id) {
            $this->pdo()->prepare('UPDATE recurrence_rules SET recurrence_type = ?, interval_days = ?, mandatory = ? WHERE id = ?')
                ->execute([$type, $type === 'PERIODIC' ? $days : null, $mandatory ? 1 : 0, (int) $id]);
        } else {
            $this->pdo()->prepare('INSERT INTO recurrence_rules (module_id, recurrence_type, interval_days, mandatory) VALUES (?, ?, ?, ?)')
                ->execute([$moduleId, $type, $type === 'PERIODIC' ? $days : null, $mandatory ? 1 : 0]);
        }
    }

    /** @param list<int> $prereqIds */
    private function replacePrerequisites(int $moduleId, array $prereqIds): void
    {
        $this->pdo()->prepare('DELETE FROM module_dependencies WHERE module_id = ? AND dependency_type = \'PREREQUIS\'')
            ->execute([$moduleId]);
        $ins = $this->pdo()->prepare('INSERT INTO module_dependencies (module_id, requires_module_id, dependency_type) VALUES (?, ?, \'PREREQUIS\')');
        foreach (array_values(array_unique($prereqIds)) as $req) {
            if ($req > 0 && $req !== $moduleId) {
                $ins->execute([$moduleId, $req]);
            }
        }
    }

    /**
     * Prérequis par module.
     *
     * @param list<int> $moduleIds
     * @return array<int, list<int>>
     */
    public function prerequisitesFor(array $moduleIds): array
    {
        if ($moduleIds === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($moduleIds), '?'));
        $st = $this->pdo()->prepare(
            "SELECT module_id, requires_module_id FROM module_dependencies
             WHERE dependency_type = 'PREREQUIS' AND module_id IN ($in)"
        );
        $st->execute(array_values($moduleIds));
        $out = [];
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $out[(int) $r['module_id']][] = (int) $r['requires_module_id'];
        }

        return $out;
    }

    /**
     * @param list<int> $moduleIds
     * @return array<int, array<string, int>>
     */
    private function progressCounts(int $tenantId, array $moduleIds): array
    {
        $in = implode(',', array_fill(0, count($moduleIds), '?'));
        $st = $this->pdo()->prepare(
            "SELECT module_id, status, expires_at FROM user_progress WHERE tenant_id = ? AND module_id IN ($in)"
        );
        $st->execute(array_merge([$tenantId], array_values($moduleIds)));
        $out = [];
        $now = time();
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $mid = (int) $r['module_id'];
            $status = self::effectiveStatus((string) $r['status'], $r['expires_at'] ?? null, $now);
            $out[$mid] ??= ['COMPLETED' => 0, 'IN_PROGRESS' => 0, 'FAILED' => 0, 'EXPIRED' => 0];
            if (isset($out[$mid][$status])) {
                $out[$mid][$status]++;
            }
        }

        return $out;
    }

    /**
     * Progression des membres sur un module, indexée par user_id.
     *
     * @return array<int, array<string, mixed>>
     */
    public function progressForModule(int $tenantId, int $moduleId): array
    {
        $st = $this->pdo()->prepare(
            'SELECT up.user_id, up.status, up.score, up.attempts, up.validated_at, up.expires_at, up.started_at,
                    up.last_activity_at, v.display_name AS validator_name, v.callsign AS validator_callsign
             FROM user_progress up
             LEFT JOIN users v ON v.id = up.validated_by
             WHERE up.tenant_id = ? AND up.module_id = ?'
        );
        $st->execute([$tenantId, $moduleId]);
        $out = [];
        $now = time();
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $r['effective_status'] = self::effectiveStatus((string) $r['status'], $r['expires_at'] ?? null, $now);
            $out[(int) $r['user_id']] = $r;
        }

        return $out;
    }

    /**
     * Statut de progression des membres sur une liste de modules (pour contrôler les prérequis).
     *
     * @param list<int> $moduleIds
     * @return array<int, array<int, string>> user_id => module_id => statut effectif
     */
    public function statusesForModules(int $tenantId, array $moduleIds): array
    {
        if ($moduleIds === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($moduleIds), '?'));
        $st = $this->pdo()->prepare("SELECT user_id, module_id, status, expires_at FROM user_progress WHERE tenant_id = ? AND module_id IN ($in)");
        $st->execute(array_merge([$tenantId], array_values($moduleIds)));
        $out = [];
        $now = time();
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $out[(int) $r['user_id']][(int) $r['module_id']] = self::effectiveStatus((string) $r['status'], $r['expires_at'] ?? null, $now);
        }

        return $out;
    }

    /** Enregistre le statut d'un membre sur un module. */
    public function setProgress(
        int $tenantId,
        int $moduleId,
        int $userId,
        string $status,
        int $actorUserId,
        ?string $expiresAt,
        ?float $score
    ): void {
        $now = date('Y-m-d H:i:s');
        $st = $this->pdo()->prepare('SELECT id, started_at, attempts FROM user_progress WHERE user_id = ? AND module_id = ?');
        $st->execute([$userId, $moduleId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        $isAttempt = in_array($status, ['COMPLETED', 'FAILED'], true);
        $validated = $status === 'COMPLETED';
        $startedAt = $status === 'NOT_STARTED' ? null : (is_array($row) && $row['started_at'] ? (string) $row['started_at'] : $now);

        if (is_array($row)) {
            $this->pdo()->prepare(
                'UPDATE user_progress SET status = ?, score = ?, attempts = ?, validated_by = ?, validated_at = ?,
                        started_at = ?, last_activity_at = ?, expires_at = ?
                 WHERE id = ?'
            )->execute([
                $status,
                $score,
                (int) $row['attempts'] + ($isAttempt ? 1 : 0),
                $validated ? $actorUserId : null,
                $validated ? $now : null,
                $startedAt,
                $now,
                $validated ? $expiresAt : null,
                (int) $row['id'],
            ]);
        } else {
            $this->pdo()->prepare(
                'INSERT INTO user_progress (tenant_id, user_id, module_id, status, score, attempts, validated_by, validated_at,
                                            started_at, last_activity_at, expires_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $tenantId, $userId, $moduleId, $status, $score, $isAttempt ? 1 : 0,
                $validated ? $actorUserId : null, $validated ? $now : null, $startedAt, $now, $validated ? $expiresAt : null,
            ]);
        }
    }

    /** Une validation dont l'échéance est passée compte comme « à renouveler ». */
    public static function effectiveStatus(string $status, mixed $expiresAt, int $now): string
    {
        $status = in_array($status, self::STATUSES, true) ? $status : 'NOT_STARTED';
        if ($status === 'COMPLETED' && is_string($expiresAt) && $expiresAt !== '') {
            $ts = strtotime($expiresAt);
            if ($ts !== false && $ts < $now) {
                return 'EXPIRED';
            }
        }

        return $status;
    }

    /** @param array<string, mixed> $r @return array<string, mixed> */
    private function normalize(array $r): array
    {
        $days = $r['interval_days'] !== null && (string) ($r['recurrence_type'] ?? '') === 'PERIODIC' ? (int) $r['interval_days'] : null;

        return [
            'id' => (int) $r['id'],
            'code' => (string) $r['code'],
            'name' => (string) $r['name'],
            'module_type' => (string) $r['module_type'],
            'delivery_mode' => (string) $r['delivery_mode'],
            'description' => (string) ($r['description'] ?? ''),
            'duration_min' => $r['duration_min'] !== null ? (int) $r['duration_min'] : null,
            // Actif dans le parcours = module non archivé ET activé pour l'organisation.
            'is_active' => (int) $r['module_active'] === 1 && (int) ($r['tenant_active'] ?? 0) === 1,
            'is_mandatory' => (int) ($r['is_mandatory'] ?? 0) === 1,
            'custom_order' => $r['custom_order'] !== null ? (int) $r['custom_order'] : null,
            'recurrence_days' => $days,
        ];
    }
}
