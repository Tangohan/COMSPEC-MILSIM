<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Bilans d’étape rattachés à un dossier personnel.
 */
final class PersonnelStageBilanRepository
{
    public const KIND_RECRUTEMENT = 'recrutement';

    public const KIND_RH = 'rh';

    public const KIND_COMMANDEMENT = 'commandement';

    /** @var list<string> */
    public const KINDS = [self::KIND_RECRUTEMENT, self::KIND_RH, self::KIND_COMMANDEMENT];

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
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_stage_bilans' LIMIT 1"
            );
            self::$tableExists = $st && (bool) $st->fetchColumn();
        }

        return self::$tableExists;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForUser(int $tenantId, int $userId, int $limit = 50): array
    {
        if (!$this->tableExists() || $tenantId < 1 || $userId < 1) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT b.*, u.display_name AS author_display_name, u.callsign AS author_callsign
             FROM personnel_stage_bilans b
             LEFT JOIN users u ON u.id = b.created_by
             WHERE b.tenant_id = ? AND b.user_id = ?
             ORDER BY b.event_date DESC, b.id DESC
             LIMIT {$limit}"
        );
        $stmt->execute([$tenantId, $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function create(
        int $tenantId,
        int $userId,
        string $kind,
        string $stageLabel,
        string $title,
        string $body,
        string $eventDate,
        ?int $rating,
        ?int $createdBy
    ): ?int {
        if (!$this->tableExists() || $tenantId < 1 || $userId < 1) {
            return null;
        }
        $kind = trim($kind);
        if (!in_array($kind, self::KINDS, true)) {
            $kind = self::KIND_RH;
        }
        $stage = mb_substr(trim($stageLabel), 0, 120);
        $label = mb_substr(trim($title), 0, 180);
        $text = mb_substr(trim($body), 0, 8000);
        if ($stage === '' || $label === '' || $text === '') {
            return null;
        }
        $date = trim($eventDate);
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }
        $score = $rating;
        if ($score !== null) {
            $score = max(1, min(5, $score));
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO personnel_stage_bilans
            (tenant_id, user_id, bilan_kind, stage_label, title, rating, body, event_date, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $ok = $stmt->execute([
            $tenantId,
            $userId,
            $kind,
            $stage,
            $label,
            $score,
            $text,
            $date,
            $createdBy,
        ]);

        return $ok ? (int) $this->pdo->lastInsertId() : null;
    }
}
