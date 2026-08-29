<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Core\Database;
use App\Repositories\PersonnelCareerEventRepository;
use App\Repositories\PersonnelQualificationRepository;
use DateTimeImmutable;
use PDO;

/**
 * Currency ≠ validité administrative.
 * Exemple : JTAC valide jusqu’en 2027 mais NON_CURRENT après 90 j sans pratique.
 */
final class QualificationCurrencyService
{
    public function __construct(
        private PersonnelQualificationRepository $qualifications,
        private PersonnelCareerEventRepository $careerEvents,
    ) {}

    /**
     * Enregistre une pratique et recalcule la currency.
     *
     * @return array{ok: bool, currency_status?: string, currency_expires_at?: ?string, error?: string}
     */
    public function recordPractice(
        int $tenantId,
        int $userId,
        int $qualificationId,
        ?int $actorId,
        string $practiceType = 'ops',
        ?string $practicedAt = null
    ): array {
        $pdo = Database::getPdo();
        if (!$this->practiceSchemaReady($pdo)) {
            return ['ok' => false, 'error' => 'Schéma currency indisponible.'];
        }
        $row = $this->findQualificationRow($pdo, $tenantId, $userId, $qualificationId);
        if ($row === null) {
            return ['ok' => false, 'error' => 'Qualification introuvable.'];
        }
        $at = $this->normalizeDateTime($practicedAt) ?? new DateTimeImmutable('now');
        $definitionId = isset($row['definition_id']) && (int) $row['definition_id'] > 0
            ? (int) $row['definition_id']
            : null;
        $currencyDays = $this->resolveCurrencyDays($pdo, $tenantId, $definitionId, $row);
        $status = 'CURRENT';
        $expiresAt = null;
        if ($currencyDays !== null && $currencyDays > 0) {
            $expiresAt = $at->modify('+' . $currencyDays . ' days');
        }

        $ins = $pdo->prepare(
            'INSERT INTO personnel_qualification_practice_log
                (tenant_id, user_id, definition_id, qualification_id, practiced_at, practice_type, recorded_by, created_at)
             VALUES (?,?,?,?,?,?,?,NOW())'
        );
        $ins->execute([
            $tenantId,
            $userId,
            $definitionId,
            $qualificationId,
            $at->format('Y-m-d H:i:s'),
            $practiceType,
            $actorId,
        ]);

        $upd = $pdo->prepare(
            'UPDATE personnel_qualifications
             SET last_practiced_at = ?, currency_status = ?, currency_expires_at = ?
             WHERE id = ? AND user_id = ?'
        );
        $upd->execute([
            $at->format('Y-m-d H:i:s'),
            $status,
            $expiresAt?->format('Y-m-d H:i:s'),
            $qualificationId,
            $userId,
        ]);

        $this->careerEvents->record($tenantId, $userId, 'QUALIFICATION_PRACTICED', $actorId, [
            'qualification_id' => $qualificationId,
            'definition_id' => $definitionId,
            'currency_status' => $status,
            'currency_expires_at' => $expiresAt?->format('Y-m-d H:i:s'),
        ]);

        return [
            'ok' => true,
            'currency_status' => $status,
            'currency_expires_at' => $expiresAt?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Recalcule CURRENT / NON_CURRENT pour toutes les quals d’un membre.
     *
     * @return array{updated: int, non_current: int}
     */
    public function refreshForUser(int $tenantId, int $userId): array
    {
        $pdo = Database::getPdo();
        $stats = ['updated' => 0, 'non_current' => 0];
        if (!$this->practiceSchemaReady($pdo) || $tenantId < 1 || $userId < 1) {
            return $stats;
        }
        $st = $pdo->prepare(
            'SELECT id, definition_id, status, expires_at, last_practiced_at, currency_status, currency_expires_at
             FROM personnel_qualifications
             WHERE user_id = ? AND (tenant_id = ? OR tenant_id IS NULL)'
        );
        $st->execute([$userId, $tenantId]);
        $now = new DateTimeImmutable('now');
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $adminStatus = strtolower((string) ($row['status'] ?? ''));
            if (in_array($adminStatus, ['expired', 'revoked'], true)) {
                continue;
            }
            $previousStatus = strtoupper((string) ($row['currency_status'] ?? 'UNKNOWN'));
            $definitionId = isset($row['definition_id']) ? (int) $row['definition_id'] : null;
            $currencyDays = $this->resolveCurrencyDays($pdo, $tenantId, $definitionId > 0 ? $definitionId : null, $row);
            $currencyStatus = 'UNKNOWN';
            $currencyExpires = null;
            if ($currencyDays === null || $currencyDays < 1) {
                $currencyStatus = 'CURRENT';
            } else {
                $last = $this->normalizeDateTime(isset($row['last_practiced_at']) ? (string) $row['last_practiced_at'] : null);
                if ($last === null) {
                    $currencyStatus = 'NON_CURRENT';
                } else {
                    $currencyExpires = $last->modify('+' . $currencyDays . ' days');
                    $currencyStatus = $currencyExpires >= $now ? 'CURRENT' : 'NON_CURRENT';
                }
            }
            $upd = $pdo->prepare(
                'UPDATE personnel_qualifications
                 SET currency_status = ?, currency_expires_at = ?
                 WHERE id = ? AND user_id = ?'
            );
            $upd->execute([
                $currencyStatus,
                $currencyExpires?->format('Y-m-d H:i:s'),
                (int) $row['id'],
                $userId,
            ]);
            ++$stats['updated'];
            if ($currencyStatus === 'NON_CURRENT') {
                ++$stats['non_current'];
                if ($previousStatus !== 'NON_CURRENT') {
                    $this->careerEvents->record($tenantId, $userId, 'QUALIFICATION_CURRENCY_LOST', null, [
                        'qualification_id' => (int) $row['id'],
                        'definition_id' => $definitionId,
                    ]);
                }
            }
        }

        return $stats;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForUser(int $tenantId, int $userId): array
    {
        $pdo = Database::getPdo();
        if ($tenantId < 1 || $userId < 1) {
            return [];
        }
        try {
            $st = $pdo->prepare(
                'SELECT id, qualification_name, status, expires_at, last_practiced_at, currency_status, currency_expires_at, definition_id, training_course_id
                 FROM personnel_qualifications
                 WHERE user_id = ? AND (tenant_id = ? OR tenant_id IS NULL)
                 ORDER BY qualification_name ASC'
            );
            $st->execute([$userId, $tenantId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);

            return is_array($rows) ? $rows : [];
        } catch (\Throwable) {
            return $this->qualifications->listForUser($userId);
        }
    }

    public function isAdministrativelyValid(array $row): bool
    {
        $status = strtolower((string) ($row['status'] ?? ''));
        if (in_array($status, ['expired', 'revoked', 'failed'], true)) {
            return false;
        }
        $expires = $this->normalizeDateTime(isset($row['expires_at']) ? (string) $row['expires_at'] : null);
        if ($expires !== null && $expires < new DateTimeImmutable('today')) {
            return false;
        }

        return true;
    }

    public function isCurrent(array $row): bool
    {
        if (!$this->isAdministrativelyValid($row)) {
            return false;
        }
        $currency = strtoupper((string) ($row['currency_status'] ?? 'UNKNOWN'));
        if ($currency === 'NON_CURRENT') {
            return false;
        }
        if ($currency === 'CURRENT') {
            return true;
        }
        /* UNKNOWN sans règle de currency = traité comme current pour ne pas bloquer l’existant. */
        return true;
    }

    private function practiceSchemaReady(PDO $pdo): bool
    {
        try {
            $st = $pdo->query(
                "SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_qualifications' AND COLUMN_NAME = 'currency_status' LIMIT 1"
            );

            return (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array<string, mixed>|null */
    private function findQualificationRow(PDO $pdo, int $tenantId, int $userId, int $qualificationId): ?array
    {
        $st = $pdo->prepare(
            'SELECT * FROM personnel_qualifications
             WHERE id = ? AND user_id = ? AND (tenant_id = ? OR tenant_id IS NULL)
             LIMIT 1'
        );
        $st->execute([$qualificationId, $userId, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $row */
    private function resolveCurrencyDays(PDO $pdo, int $tenantId, ?int $definitionId, array $row): ?int
    {
        if ($definitionId !== null && $definitionId > 0) {
            try {
                $st = $pdo->prepare(
                    'SELECT currency_days FROM personnel_qualification_definitions
                     WHERE id = ? AND tenant_id = ? LIMIT 1'
                );
                $st->execute([$definitionId, $tenantId]);
                $v = $st->fetchColumn();
                if ($v !== false && $v !== null && (int) $v > 0) {
                    return (int) $v;
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }

    private function normalizeDateTime(?string $raw): ?DateTimeImmutable
    {
        if ($raw === null || trim($raw) === '' || str_starts_with(trim($raw), '0000-00-00')) {
            return null;
        }
        try {
            return new DateTimeImmutable(trim($raw));
        } catch (\Throwable) {
            return null;
        }
    }
}
