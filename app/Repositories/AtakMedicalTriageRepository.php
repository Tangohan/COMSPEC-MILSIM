<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\MedicalAlertParser;
use PDO;

/**
 * Persistance du triage médical des alertes (messages tchat ATAK).
 */
class AtakMedicalTriageRepository
{
    private PDO $pdo;

    private ?bool $tablesReady = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    public function tablesReady(): bool
    {
        if ($this->tablesReady !== null) {
            return $this->tablesReady;
        }
        try {
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atak_medical_alert_triage' LIMIT 1"
            );
            $this->tablesReady = (bool) $st->fetchColumn();
        } catch (\Throwable) {
            $this->tablesReady = false;
        }

        return $this->tablesReady;
    }

    /**
     * @param list<int> $chatIds
     * @return array<int, array<string, mixed>> indexé par chat_message_id
     */
    public function getByChatIds(int $tenantId, array $chatIds): array
    {
        if (!$this->tablesReady() || $chatIds === []) {
            return [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $chatIds), static fn (int $id) => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$tenantId], $ids);
        $stmt = $this->pdo->prepare(
            "SELECT chat_message_id, status, status_by, status_note, updated_at
             FROM atak_medical_alert_triage
             WHERE tenant_id = ? AND chat_message_id IN ($placeholders)"
        );
        $stmt->execute($params);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cid = (int) ($row['chat_message_id'] ?? 0);
            if ($cid < 1) {
                continue;
            }
            $status = MedicalAlertParser::normalizeTriageStatus((string) ($row['status'] ?? ''));
            $out[$cid] = [
                'status' => $status,
                'status_label' => MedicalAlertParser::triageLabelFr($status),
                'status_by' => (string) ($row['status_by'] ?? ''),
                'status_note' => (string) ($row['status_note'] ?? ''),
                'updated_at' => (string) ($row['updated_at'] ?? ''),
                'is_resolved' => MedicalAlertParser::isResolvedTriage($status),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function upsert(
        int $tenantId,
        int $mapId,
        int $chatMessageId,
        string $status,
        string $by = '',
        string $note = ''
    ): ?array {
        if (!$this->tablesReady() || $chatMessageId < 1) {
            return null;
        }
        $normalized = MedicalAlertParser::normalizeTriageStatus($status);
        $by = mb_substr(trim($by), 0, 120);
        $note = mb_substr(trim($note), 0, 500);

        $stmt = $this->pdo->prepare(
            'INSERT INTO atak_medical_alert_triage
                (tenant_id, map_id, chat_message_id, status, status_by, status_note)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                status_by = VALUES(status_by),
                status_note = VALUES(status_note),
                map_id = VALUES(map_id),
                updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([$tenantId, $mapId, $chatMessageId, $normalized, $by !== '' ? $by : null, $note !== '' ? $note : null]);

        $get = $this->getByChatIds($tenantId, [$chatMessageId]);

        return $get[$chatMessageId] ?? [
            'status' => $normalized,
            'status_label' => MedicalAlertParser::triageLabelFr($normalized),
            'status_by' => $by,
            'status_note' => $note,
            'updated_at' => date('Y-m-d H:i:s'),
            'is_resolved' => MedicalAlertParser::isResolvedTriage($normalized),
        ];
    }
}
