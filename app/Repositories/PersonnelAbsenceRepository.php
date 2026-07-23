<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Absences déclarées sur le dossier personnel (avec ou sans date de fin).
 */
final class PersonnelAbsenceRepository
{
    public const REASON_PERSONNEL = 'personnel';

    public const REASON_SANTE = 'sante';

    public const REASON_PROFESSIONNEL = 'professionnel';

    public const REASON_SERVICE = 'service';

    public const REASON_AUTRE = 'autre';

    /** @var list<string> */
    public const REASONS = [
        self::REASON_PERSONNEL,
        self::REASON_SANTE,
        self::REASON_PROFESSIONNEL,
        self::REASON_SERVICE,
        self::REASON_AUTRE,
    ];

    /** @var array<string, string> */
    public const REASON_LABELS = [
        self::REASON_PERSONNEL => 'Indisponibilité personnelle',
        self::REASON_SANTE => 'Santé',
        self::REASON_PROFESSIONNEL => 'Obligations professionnelles ou scolaires',
        self::REASON_SERVICE => 'Service / mission',
        self::REASON_AUTRE => 'Autre',
    ];

    private PDO $pdo;

    private static ?bool $tableExists = null;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tableExists(): bool
    {
        if (self::$tableExists === null) {
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_absences' LIMIT 1"
            );
            self::$tableExists = $st && (bool) $st->fetchColumn();
        }

        return self::$tableExists;
    }

    public static function reasonLabel(string $reason): string
    {
        return self::REASON_LABELS[$reason] ?? self::REASON_LABELS[self::REASON_AUTRE];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForUser(int $tenantId, int $userId, int $limit = 30): array
    {
        if (!$this->tableExists() || $tenantId < 1 || $userId < 1) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT *
             FROM personnel_absences
             WHERE tenant_id = ? AND user_id = ?
             ORDER BY starts_on DESC, id DESC
             LIMIT {$limit}"
        );
        $stmt->execute([$tenantId, $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Absences encore en cours (statut actif, période couvrant aujourd’hui ou ouverte).
     *
     * @return list<array<string, mixed>>
     */
    public function listActiveForUser(int $tenantId, int $userId, ?string $onDate = null): array
    {
        if (!$this->tableExists() || $tenantId < 1 || $userId < 1) {
            return [];
        }
        $day = $onDate !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $onDate) ? $onDate : date('Y-m-d');
        $stmt = $this->pdo->prepare(
            'SELECT *
             FROM personnel_absences
             WHERE tenant_id = ? AND user_id = ? AND status = \'active\'
               AND starts_on <= ?
               AND (ends_on IS NULL OR ends_on >= ?)
             ORDER BY starts_on DESC, id DESC'
        );
        $stmt->execute([$tenantId, $userId, $day, $day]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findOwned(int $tenantId, int $userId, int $absenceId): ?array
    {
        if (!$this->tableExists() || $tenantId < 1 || $userId < 1 || $absenceId < 1) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM personnel_absences WHERE id = ? AND tenant_id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$absenceId, $tenantId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function create(
        int $tenantId,
        int $userId,
        string $startsOn,
        ?string $endsOn,
        string $reason,
        ?string $note,
        ?int $createdBy
    ): ?int {
        if (!$this->tableExists() || $tenantId < 1 || $userId < 1) {
            return null;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startsOn)) {
            return null;
        }
        if ($endsOn !== null) {
            $endsOn = trim($endsOn);
            if ($endsOn === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endsOn)) {
                $endsOn = null;
            } elseif ($endsOn < $startsOn) {
                return null;
            }
        }
        $reason = trim($reason);
        if (!in_array($reason, self::REASONS, true)) {
            $reason = self::REASON_AUTRE;
        }
        $noteText = $note !== null ? mb_substr(trim($note), 0, 500) : '';
        if ($noteText === '') {
            $noteText = null;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO personnel_absences
                (tenant_id, user_id, starts_on, ends_on, reason, note, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, \'active\', ?)'
        );
        $stmt->execute([
            $tenantId,
            $userId,
            $startsOn,
            $endsOn,
            $reason,
            $noteText,
            $createdBy !== null && $createdBy > 0 ? $createdBy : null,
        ]);
        $id = (int) $this->pdo->lastInsertId();

        return $id > 0 ? $id : null;
    }

    public function cancel(int $tenantId, int $userId, int $absenceId): bool
    {
        if (!$this->tableExists() || $tenantId < 1 || $userId < 1 || $absenceId < 1) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE personnel_absences
             SET status = \'cancelled\', cancelled_at = NOW()
             WHERE id = ? AND tenant_id = ? AND user_id = ? AND status = \'active\''
        );
        $stmt->execute([$absenceId, $tenantId, $userId]);

        return $stmt->rowCount() > 0;
    }
}
