<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use App\Support\SseDocumentChromeCatalog;
use PDO;

final class SseDocumentPrefabRepository
{
    use LazyDatabaseConnection;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public function schemaReady(): bool
    {
        try {
            $stmt = $this->pdo()->prepare(
                'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $stmt->execute(['sse_document_prefabs']);

            return (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForTenant(int $tenantId, bool $activeOnly = false): array
    {
        $this->ensureSeeded($tenantId);
        if (!$this->schemaReady()) {
            return SseDocumentChromeCatalog::builtInPrefabs();
        }
        $sql = 'SELECT * FROM sse_document_prefabs WHERE tenant_id = ?';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, label ASC';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([$tenantId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static fn (array $r): array => SseDocumentChromeCatalog::normalize($r), $rows);
    }

    public function findByCode(int $tenantId, string $code): ?array
    {
        $this->ensureSeeded($tenantId);
        if (!$this->schemaReady()) {
            return SseDocumentChromeCatalog::findBuiltIn($code);
        }
        $stmt = $this->pdo()->prepare(
            'SELECT * FROM sse_document_prefabs WHERE tenant_id = ? AND code = ? LIMIT 1'
        );
        $stmt->execute([$tenantId, trim($code)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? SseDocumentChromeCatalog::normalize($row) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function activeChrome(int $tenantId): array
    {
        $code = $this->activePrefabCode($tenantId);
        $row = $this->findByCode($tenantId, $code);

        return $row ?? SseDocumentChromeCatalog::defaultChrome();
    }

    public function activePrefabCode(int $tenantId): string
    {
        $this->ensureSeeded($tenantId);
        if (!$this->schemaReady()) {
            return (string) (SseDocumentChromeCatalog::defaultChrome()['code'] ?? 'standard_restreint');
        }
        try {
            $stmt = $this->pdo()->prepare(
                'SELECT active_prefab_code FROM sse_document_chrome_settings WHERE tenant_id = ? LIMIT 1'
            );
            $stmt->execute([$tenantId]);
            $code = trim((string) ($stmt->fetchColumn() ?: ''));
            if ($code !== '') {
                return $code;
            }
        } catch (\Throwable) {
        }

        return (string) (SseDocumentChromeCatalog::defaultChrome()['code'] ?? 'standard_restreint');
    }

    public function setActivePrefab(int $tenantId, string $code, ?int $userId = null): bool
    {
        $this->ensureSeeded($tenantId);
        $code = trim($code);
        if ($this->findByCode($tenantId, $code) === null) {
            return false;
        }
        if (!$this->schemaReady()) {
            return false;
        }
        $this->pdo()->prepare(
            'INSERT INTO sse_document_chrome_settings (tenant_id, active_prefab_code, updated_by)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE active_prefab_code = VALUES(active_prefab_code), updated_by = VALUES(updated_by)'
        )->execute([$tenantId, $code, $userId]);
        $this->pdo()->prepare(
            'UPDATE sse_document_prefabs SET is_default = IF(code = ?, 1, 0) WHERE tenant_id = ?'
        )->execute([$code, $tenantId]);

        return true;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function upsert(int $tenantId, array $data, ?int $userId = null): ?array
    {
        $this->ensureSeeded($tenantId);
        if (!$this->schemaReady()) {
            return null;
        }
        $norm = SseDocumentChromeCatalog::normalize($data);
        $code = preg_replace('/[^a-z0-9_]+/', '_', strtolower(trim((string) ($norm['code'] ?? '')))) ?? '';
        $code = trim($code, '_');
        if ($code === '') {
            $code = 'custom_' . substr(sha1((string) ($norm['label'] ?? microtime())), 0, 8);
        }
        $norm['code'] = $code;
        $existing = $this->findByCode($tenantId, $code);
        if ($existing !== null && !empty($existing['is_builtin']) && empty($data['force_builtin_edit'])) {
            // Autoriser la personnalisation d’un builtin en le clonant sous le même code (écrasement tenant).
        }
        $this->pdo()->prepare(
            'INSERT INTO sse_document_prefabs (
                tenant_id, code, label, description, paper_style, banner,
                title_person, title_docs, subtitle_dossier, subtitle_feuille, subtitle_docs,
                footer, quality_prefix, org_line, seal_top, seal_bottom, access_note,
                btn_consult, btn_transmit, btn_close, is_builtin, is_default, is_active, sort_order,
                created_by, updated_by
            ) VALUES (
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, 0, 0, 1, 100,
                ?, ?
            )
            ON DUPLICATE KEY UPDATE
                label = VALUES(label),
                description = VALUES(description),
                paper_style = VALUES(paper_style),
                banner = VALUES(banner),
                title_person = VALUES(title_person),
                title_docs = VALUES(title_docs),
                subtitle_dossier = VALUES(subtitle_dossier),
                subtitle_feuille = VALUES(subtitle_feuille),
                subtitle_docs = VALUES(subtitle_docs),
                footer = VALUES(footer),
                quality_prefix = VALUES(quality_prefix),
                org_line = VALUES(org_line),
                seal_top = VALUES(seal_top),
                seal_bottom = VALUES(seal_bottom),
                access_note = VALUES(access_note),
                btn_consult = VALUES(btn_consult),
                btn_transmit = VALUES(btn_transmit),
                btn_close = VALUES(btn_close),
                is_active = 1,
                updated_by = VALUES(updated_by)'
        )->execute([
            $tenantId,
            $code,
            (string) $norm['label'],
            $norm['description'] ?? null,
            (string) $norm['paper_style'],
            (string) $norm['banner'],
            (string) $norm['title_person'],
            (string) $norm['title_docs'],
            (string) $norm['subtitle_dossier'],
            (string) $norm['subtitle_feuille'],
            (string) $norm['subtitle_docs'],
            (string) $norm['footer'],
            (string) $norm['quality_prefix'],
            (string) $norm['org_line'],
            (string) $norm['seal_top'],
            (string) $norm['seal_bottom'],
            $norm['access_note'] ?? null,
            (string) $norm['btn_consult'],
            (string) $norm['btn_transmit'],
            (string) $norm['btn_close'],
            $userId,
            $userId,
        ]);

        return $this->findByCode($tenantId, $code);
    }

    public function ensureSeeded(int $tenantId): void
    {
        if ($tenantId < 1 || !$this->schemaReady()) {
            return;
        }
        static $done = [];
        if (isset($done[$tenantId])) {
            return;
        }
        $stmt = $this->pdo()->prepare('SELECT COUNT(*) FROM sse_document_prefabs WHERE tenant_id = ?');
        $stmt->execute([$tenantId]);
        if ((int) $stmt->fetchColumn() > 0) {
            $done[$tenantId] = true;

            return;
        }
        $ins = $this->pdo()->prepare(
            'INSERT IGNORE INTO sse_document_prefabs (
                tenant_id, code, label, description, paper_style, banner,
                title_person, title_docs, subtitle_dossier, subtitle_feuille, subtitle_docs,
                footer, quality_prefix, org_line, seal_top, seal_bottom, access_note,
                btn_consult, btn_transmit, btn_close, is_builtin, is_default, is_active, sort_order
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,?,?)'
        );
        $order = 10;
        foreach (SseDocumentChromeCatalog::builtInPrefabs() as $row) {
            $ins->execute([
                $tenantId,
                (string) $row['code'],
                (string) $row['label'],
                $row['description'] ?? null,
                (string) $row['paper_style'],
                (string) $row['banner'],
                (string) $row['title_person'],
                (string) $row['title_docs'],
                (string) $row['subtitle_dossier'],
                (string) $row['subtitle_feuille'],
                (string) $row['subtitle_docs'],
                (string) $row['footer'],
                (string) $row['quality_prefix'],
                (string) $row['org_line'],
                (string) $row['seal_top'],
                (string) $row['seal_bottom'],
                $row['access_note'] ?? null,
                (string) $row['btn_consult'],
                (string) $row['btn_transmit'],
                (string) $row['btn_close'],
                !empty($row['is_default']) ? 1 : 0,
                1,
                $order,
            ]);
            $order += 10;
        }
        $def = (string) (SseDocumentChromeCatalog::defaultChrome()['code'] ?? 'standard_restreint');
        $this->pdo()->prepare(
            'INSERT IGNORE INTO sse_document_chrome_settings (tenant_id, active_prefab_code) VALUES (?, ?)'
        )->execute([$tenantId, $def]);
        $done[$tenantId] = true;
    }
}
