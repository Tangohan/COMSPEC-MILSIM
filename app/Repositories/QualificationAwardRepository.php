<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\QualificationAdminStatus;
use App\Support\VisibilityLevel;
use PDO;
use Throwable;

/**
 * Attributions (personnel_qualifications) — référentiel enrichi.
 */
final class QualificationAwardRepository
{
    private PDO $pdo;
    private ?bool $enrichedReady = null;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function enrichedReady(): bool
    {
        if ($this->enrichedReady !== null) {
            return $this->enrichedReady;
        }
        try {
            $st = $this->pdo->prepare(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_qualifications'
                   AND COLUMN_NAME IN ('admin_status', 'issuer_id', 'certificate_number')"
            );
            $st->execute();
            $this->enrichedReady = (int) $st->fetchColumn() >= 2;
        } catch (Throwable) {
            $this->enrichedReady = false;
        }

        return $this->enrichedReady;
    }

    /** @return array<string, mixed>|null */
    public function find(int $id, ?int $tenantId = null): ?array
    {
        $sql = 'SELECT pq.*,
                       d.name AS definition_name, d.code AS definition_code, d.short_name AS definition_short_name,
                       d.badge_media_path AS definition_badge_path, d.grace_period_days, d.alert_before_expiry_days,
                       d.default_validity_months, d.is_permanent, d.certificate_template_id, d.certificate_number_format,
                       d.uses_levels, d.enforce_level_progression, d.requires_panel,
                       ql.name AS level_name, ql.short_name AS level_short_name, ql.badge_media_path AS level_badge_path,
                       qi.name AS issuer_name, qi.short_name AS issuer_short_name,
                       c.name AS category_name
                FROM personnel_qualifications pq
                LEFT JOIN personnel_qualification_definitions d ON d.id = pq.definition_id
                LEFT JOIN qualification_levels ql ON ql.id = pq.qualification_level_id
                LEFT JOIN qualification_issuers qi ON qi.id = pq.issuer_id
                LEFT JOIN qualification_categories c ON c.id = d.category_id
                WHERE pq.id = ?';
        $params = [$id];
        if ($tenantId !== null) {
            $sql .= ' AND (pq.tenant_id = ? OR pq.tenant_id IS NULL)';
            $params[] = $tenantId;
        }
        $sql .= ' LIMIT 1';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function listForUser(int $userId, ?int $tenantId = null): array
    {
        $sql = 'SELECT pq.*,
                       d.name AS definition_name, d.code AS definition_code, d.short_name AS definition_short_name,
                       d.badge_media_path AS definition_badge_path, d.grace_period_days, d.alert_before_expiry_days,
                       d.is_permanent, d.uses_levels,
                       ql.name AS level_name, ql.short_name AS level_short_name, ql.badge_media_path AS level_badge_path,
                       qi.name AS issuer_name,
                       c.name AS category_name
                FROM personnel_qualifications pq
                LEFT JOIN personnel_qualification_definitions d ON d.id = pq.definition_id
                LEFT JOIN qualification_levels ql ON ql.id = pq.qualification_level_id
                LEFT JOIN qualification_issuers qi ON qi.id = pq.issuer_id
                LEFT JOIN qualification_categories c ON c.id = d.category_id
                WHERE pq.user_id = ?';
        $params = [$userId];
        if ($tenantId !== null) {
            $sql .= ' AND (pq.tenant_id = ? OR pq.tenant_id IS NULL)';
            $params[] = $tenantId;
        }
        $sql .= ' ORDER BY pq.is_primary DESC, pq.expires_at IS NULL DESC, pq.expires_at DESC, pq.obtained_at DESC';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listHolders(int $tenantId, int $definitionId): array
    {
        $st = $this->pdo->prepare(
            'SELECT pq.*, u.display_name, u.username, u.email,
                    ql.name AS level_name
             FROM personnel_qualifications pq
             JOIN users u ON u.id = pq.user_id
             LEFT JOIN qualification_levels ql ON ql.id = pq.qualification_level_id
             WHERE pq.definition_id = ? AND (pq.tenant_id = ? OR pq.tenant_id IS NULL)
             ORDER BY pq.obtained_at DESC, pq.id DESC'
        );
        $st->execute([$definitionId, $tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(int $tenantId, int $userId, array $data, ?int $actorId = null): int
    {
        $adminStatus = QualificationAdminStatus::normalize((string) ($data['admin_status'] ?? $data['status'] ?? 'obtained'));
        $visibility = VisibilityLevel::normalize((string) ($data['visibility_level'] ?? $data['visibility'] ?? 'normal'));
        if ($visibility === VisibilityLevel::ANONYMIZED) {
            $visibility = VisibilityLevel::NORMAL;
        }

        $cols = [
            'user_id', 'tenant_id', 'qualification_name', 'definition_id', 'level', 'status',
            'obtained_at', 'expires_at', 'issued_by', 'source',
        ];
        $vals = [
            $userId,
            $tenantId,
            (string) ($data['qualification_name'] ?? $data['name'] ?? 'Qualification'),
            isset($data['definition_id']) ? (int) $data['definition_id'] : null,
            $data['level'] ?? null,
            $adminStatus === QualificationAdminStatus::OBTAINED ? 'valid' : 'in_progress',
            $data['obtained_at'] ?? null,
            $data['expires_at'] ?? null,
            $data['issued_by'] ?? $actorId,
            $data['source'] ?? 'manual',
        ];

        if ($this->enrichedReady()) {
            $extra = [
                'admin_status' => $adminStatus,
                'qualification_level_id' => $data['qualification_level_id'] ?? null,
                'issuer_id' => $data['issuer_id'] ?? null,
                'certificate_number' => $data['certificate_number'] ?? null,
                'attempt_number' => (int) ($data['attempt_number'] ?? 1),
                'renewal_of_id' => $data['renewal_of_id'] ?? null,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'is_primary' => !empty($data['is_primary']) ? 1 : 0,
                'qualification_version_id' => $data['qualification_version_id'] ?? null,
                'is_retrospective' => !empty($data['is_retrospective']) ? 1 : 0,
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'visibility_level' => $visibility,
            ];
            foreach ($extra as $col => $val) {
                if ($this->hasColumn($col)) {
                    $cols[] = $col;
                    $vals[] = $val === '' ? null : $val;
                }
            }
        }

        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $st = $this->pdo->prepare(
            'INSERT INTO personnel_qualifications (' . implode(', ', $cols) . ', created_at, updated_at)
             VALUES (' . $placeholders . ', NOW(), NOW())'
        );
        $st->execute($vals);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data, ?int $actorId = null): bool
    {
        $sets = [];
        $vals = [];
        $map = [
            'qualification_name' => 'qualification_name',
            'level' => 'level',
            'obtained_at' => 'obtained_at',
            'expires_at' => 'expires_at',
            'definition_id' => 'definition_id',
            'qualification_level_id' => 'qualification_level_id',
            'issuer_id' => 'issuer_id',
            'certificate_number' => 'certificate_number',
            'certificate_document_path' => 'certificate_document_path',
            'reference' => 'reference',
            'notes' => 'notes',
            'is_primary' => 'is_primary',
            'visibility_level' => 'visibility_level',
            'attempt_number' => 'attempt_number',
            'is_retrospective' => 'is_retrospective',
        ];
        foreach ($map as $key => $col) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            if (!$this->hasColumn($col) && !in_array($col, ['qualification_name', 'level', 'obtained_at', 'expires_at'], true)) {
                continue;
            }
            $sets[] = $col . ' = ?';
            $v = $data[$key];
            if (in_array($key, ['is_primary', 'is_retrospective'], true)) {
                $v = !empty($v) ? 1 : 0;
            }
            if ($key === 'visibility_level') {
                $v = VisibilityLevel::normalize((string) $v);
            }
            $vals[] = $v === '' ? null : $v;
        }
        if ($actorId !== null && $this->hasColumn('updated_by')) {
            $sets[] = 'updated_by = ?';
            $vals[] = $actorId;
        }
        if ($sets === []) {
            return false;
        }
        $sets[] = 'updated_at = NOW()';
        $vals[] = $id;
        $st = $this->pdo->prepare('UPDATE personnel_qualifications SET ' . implode(', ', $sets) . ' WHERE id = ?');

        return $st->execute($vals);
    }

    public function setAdminStatus(
        int $id,
        string $adminStatus,
        ?int $actorId = null,
        ?string $revocationReason = null
    ): bool {
        $adminStatus = QualificationAdminStatus::normalize($adminStatus);
        $legacyStatus = match ($adminStatus) {
            QualificationAdminStatus::OBTAINED => 'valid',
            QualificationAdminStatus::IN_TRAINING,
            QualificationAdminStatus::IN_EVALUATION,
            QualificationAdminStatus::CANDIDATE => 'in_progress',
            QualificationAdminStatus::FAILED,
            QualificationAdminStatus::SUSPENDED,
            QualificationAdminStatus::REVOKED => 'expired',
            default => 'in_progress',
        };
        $sets = ['status = ?', 'updated_at = NOW()'];
        $vals = [$legacyStatus];
        if ($this->hasColumn('admin_status')) {
            $sets[] = 'admin_status = ?';
            $vals[] = $adminStatus;
        }
        if ($actorId !== null && $this->hasColumn('updated_by')) {
            $sets[] = 'updated_by = ?';
            $vals[] = $actorId;
        }
        if ($adminStatus === QualificationAdminStatus::REVOKED && $this->hasColumn('revoked_at')) {
            $sets[] = 'revoked_at = NOW()';
            $sets[] = 'revoked_by = ?';
            $vals[] = $actorId;
            if ($this->hasColumn('revocation_reason')) {
                $sets[] = 'revocation_reason = ?';
                $vals[] = $revocationReason;
            }
        }
        $vals[] = $id;
        $st = $this->pdo->prepare('UPDATE personnel_qualifications SET ' . implode(', ', $sets) . ' WHERE id = ?');

        return $st->execute($vals);
    }

    public function clearPrimaryForUser(int $userId, ?int $exceptId = null): void
    {
        if (!$this->hasColumn('is_primary')) {
            return;
        }
        $sql = 'UPDATE personnel_qualifications SET is_primary = 0 WHERE user_id = ?';
        $params = [$userId];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }
        $this->pdo->prepare($sql)->execute($params);
    }

    public function addHistory(
        int $tenantId,
        int $awardId,
        string $eventType,
        ?int $performedBy,
        ?string $details = null
    ): void {
        try {
            $st = $this->pdo->prepare(
                'INSERT INTO qualification_history
                    (tenant_id, personnel_qualification_id, event_type, event_date, performed_by, details)
                 VALUES (?, ?, ?, NOW(), ?, ?)'
            );
            $st->execute([$tenantId, $awardId, $eventType, $performedBy, $details]);
        } catch (Throwable) {
        }
    }

    /** @return list<array<string, mixed>> */
    public function listHistory(int $awardId): array
    {
        try {
            $st = $this->pdo->prepare(
                'SELECT h.*, u.display_name AS performer_name
                 FROM qualification_history h
                 LEFT JOIN users u ON u.id = h.performed_by
                 WHERE h.personnel_qualification_id = ?
                 ORDER BY h.event_date DESC, h.id DESC'
            );
            $st->execute([$awardId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listExpiring(int $tenantId, int $withinDays = 30): array
    {
        $st = $this->pdo->prepare(
            'SELECT pq.*, d.name AS definition_name, d.code AS definition_code,
                    d.alert_before_expiry_days, d.grace_period_days,
                    u.display_name, u.username
             FROM personnel_qualifications pq
             JOIN users u ON u.id = pq.user_id
             LEFT JOIN personnel_qualification_definitions d ON d.id = pq.definition_id
             WHERE (pq.tenant_id = ? OR (pq.tenant_id IS NULL AND u.tenant_id = ?))
               AND pq.expires_at IS NOT NULL
               AND COALESCE(pq.admin_status, pq.status) IN (\'obtained\',\'valid\',\'expiring\')
               AND pq.expires_at <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
             ORDER BY pq.expires_at ASC'
        );
        $st->execute([$tenantId, $tenantId, $withinDays]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function nextCertificateSequence(int $tenantId, int $year): int
    {
        try {
            $this->pdo->beginTransaction();
            $st = $this->pdo->prepare(
                'SELECT last_sequence FROM qualification_certificate_sequences
                 WHERE tenant_id = ? AND year_key = ? FOR UPDATE'
            );
            $st->execute([$tenantId, $year]);
            $current = $st->fetchColumn();
            if ($current === false) {
                $ins = $this->pdo->prepare(
                    'INSERT INTO qualification_certificate_sequences (tenant_id, year_key, last_sequence)
                     VALUES (?, ?, 1)'
                );
                $ins->execute([$tenantId, $year]);
                $this->pdo->commit();

                return 1;
            }
            $next = (int) $current + 1;
            $upd = $this->pdo->prepare(
                'UPDATE qualification_certificate_sequences SET last_sequence = ? WHERE tenant_id = ? AND year_key = ?'
            );
            $upd->execute([$next, $tenantId, $year]);
            $this->pdo->commit();

            return $next;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function setCustomValue(int $tenantId, int $awardId, int $fieldId, ?string $value): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO personnel_qualification_custom_values
                (tenant_id, personnel_qualification_id, custom_field_id, value)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()'
        );
        $st->execute([$tenantId, $awardId, $fieldId, $value]);
    }

    /** @return array<int, string|null> field_id => value */
    public function customValuesForAward(int $awardId): array
    {
        try {
            $st = $this->pdo->prepare(
                'SELECT custom_field_id, value FROM personnel_qualification_custom_values
                 WHERE personnel_qualification_id = ?'
            );
            $st->execute([$awardId]);
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $out[(int) $row['custom_field_id']] = $row['value'];
            }

            return $out;
        } catch (Throwable) {
            return [];
        }
    }

    public function replacePanelMembers(int $tenantId, int $awardId, array $members): void
    {
        $del = $this->pdo->prepare(
            'DELETE FROM qualification_award_panels WHERE tenant_id = ? AND personnel_qualification_id = ?'
        );
        $del->execute([$tenantId, $awardId]);
        $ins = $this->pdo->prepare(
            'INSERT INTO qualification_award_panels
                (tenant_id, personnel_qualification_id, panel_member_user_id, role_in_panel)
             VALUES (?, ?, ?, ?)'
        );
        foreach ($members as $m) {
            $uid = (int) ($m['user_id'] ?? 0);
            if ($uid <= 0) {
                continue;
            }
            $role = in_array(($m['role'] ?? ''), ['evaluator', 'president'], true)
                ? $m['role'] : 'evaluator';
            $ins->execute([$tenantId, $awardId, $uid, $role]);
        }
    }

    /** @return list<array<string, mixed>> */
    public function listPanelMembers(int $awardId): array
    {
        try {
            $st = $this->pdo->prepare(
                'SELECT p.*, u.display_name, u.username
                 FROM qualification_award_panels p
                 JOIN users u ON u.id = p.panel_member_user_id
                 WHERE p.personnel_qualification_id = ?'
            );
            $st->execute([$awardId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    private function hasColumn(string $column): bool
    {
        static $cache = [];
        if (array_key_exists($column, $cache)) {
            return $cache[$column];
        }
        try {
            $st = $this->pdo->prepare(
                "SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_qualifications' AND COLUMN_NAME = ?
                 LIMIT 1"
            );
            $st->execute([$column]);
            $cache[$column] = (bool) $st->fetchColumn();
        } catch (Throwable) {
            $cache[$column] = false;
        }

        return $cache[$column];
    }
}
