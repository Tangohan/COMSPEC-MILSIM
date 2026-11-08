<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Annexes d'un compte rendu post-op : BDA et comptes rendus du terrain joints, textes rédigés.
 * Toujours filtré par communauté, y compris pour les rapports du terrain joints.
 */
final class AarAnnexRepository
{
    public const KINDS = ['bda' => 'BDA', 'cr' => 'Compte rendu du terrain', 'note' => 'Annexe rédigée'];

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    public function tablesReady(): bool
    {
        try {
            $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aar_report_annexes' LIMIT 1");

            return $st !== false && (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Rapports du terrain de la communauté que l'on peut joindre (les plus récents d'abord).
     *
     * @return list<array<string, mixed>>
     */
    public function attachableSources(int $tenantId, int $limit = 150): array
    {
        if ($tenantId < 1) {
            return [];
        }
        try {
            $st = $this->pdo->prepare(
                'SELECT id, report_type, report_number, summary, submitter_callsign, created_at
                 FROM atak_tactical_reports WHERE tenant_id = ? AND deleted_at IS NULL
                 ORDER BY created_at DESC, id DESC LIMIT ' . max(1, min(500, $limit))
            );
            $st->execute([$tenantId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForReport(int $tenantId, int $reportId): array
    {
        if (!$this->tablesReady() || $tenantId < 1 || $reportId < 1) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT a.id, a.kind, a.source_id, a.title, a.body, a.sort_order,
                    t.report_type AS src_type, t.report_number AS src_number, t.summary AS src_summary,
                    t.details AS src_details, t.created_at AS src_at
             FROM aar_report_annexes a
             LEFT JOIN atak_tactical_reports t ON t.id = a.source_id AND t.tenant_id = a.tenant_id
             WHERE a.tenant_id = ? AND a.report_id = ?
             ORDER BY a.sort_order, a.id'
        );
        $st->execute([$tenantId, $reportId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Remplace les annexes. Une source du terrain d'une autre communauté, ou supprimée, est ignorée.
     *
     * @param list<array{kind?: string, source_id?: int, title?: string, body?: string}> $items
     */
    public function replaceForReport(int $tenantId, int $reportId, array $items): void
    {
        if (!$this->tablesReady() || $tenantId < 1 || $reportId < 1) {
            return;
        }
        $this->pdo->prepare('DELETE FROM aar_report_annexes WHERE tenant_id = ? AND report_id = ?')->execute([$tenantId, $reportId]);
        $ins = $this->pdo->prepare(
            'INSERT INTO aar_report_annexes (tenant_id, report_id, kind, source_id, title, body, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $order = 0;
        foreach (array_slice($items, 0, 60) as $it) {
            $kind = (string) ($it['kind'] ?? '');
            if (!isset(self::KINDS[$kind])) {
                continue;
            }
            $sourceId = null;
            $title = mb_substr(trim((string) ($it['title'] ?? '')), 0, 200);
            $body = mb_substr(trim((string) ($it['body'] ?? '')), 0, 20000);
            if ($kind === 'note') {
                if ($title === '' && $body === '') {
                    continue;
                }
            } else {
                $sourceId = (int) ($it['source_id'] ?? 0);
                if ($sourceId < 1 || !$this->sourceBelongsToTenant($tenantId, $sourceId, $kind)) {
                    continue;
                }
            }
            $ins->execute([$tenantId, $reportId, $kind, $sourceId, $title, $body !== '' ? $body : null, $order++]);
        }
    }

    public function deleteForReport(int $tenantId, int $reportId): void
    {
        if (!$this->tablesReady() || $tenantId < 1 || $reportId < 1) {
            return;
        }
        $this->pdo->prepare('DELETE FROM aar_report_annexes WHERE tenant_id = ? AND report_id = ?')->execute([$tenantId, $reportId]);
    }

    private function sourceBelongsToTenant(int $tenantId, int $sourceId, string $kind): bool
    {
        try {
            $st = $this->pdo->prepare('SELECT report_type FROM atak_tactical_reports WHERE tenant_id = ? AND id = ? AND deleted_at IS NULL LIMIT 1');
            $st->execute([$tenantId, $sourceId]);
            $type = $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
        if ($type === false) {
            return false;
        }

        return $kind !== 'bda' || strtoupper((string) $type) === 'BDA';
    }
}
