<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\AarCustomForm;
use PDO;

final class AarReportTemplateRepository
{
    private PDO $pdo;
    private bool $ensured = false;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
        $this->ensureSchema();
    }

    public function ensureSchema(): void
    {
        if ($this->ensured) {
            return;
        }
        $this->ensured = true;
        $migration = dirname(__DIR__, 2) . '/bootstrap/aar_reports_migration.php';
        if (!is_file($migration)) {
            return;
        }
        try {
            $runner = require $migration;
            if (is_callable($runner)) {
                $runner($this->pdo);
            }
        } catch (\Throwable) {
        }
    }

    public function tablesReady(): bool
    {
        try {
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aar_report_templates' LIMIT 1"
            );

            return $st !== false && (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForTenant(int $tenantId, bool $activeOnly = false): array
    {
        if (!$this->tablesReady() || $tenantId < 1) {
            return [];
        }
        $sql = 'SELECT t.*, u.display_name AS author_name
                FROM aar_report_templates t
                LEFT JOIN users u ON u.id = t.created_by_user_id
                WHERE t.tenant_id = ?';
        $args = [$tenantId];
        if ($activeOnly) {
            $sql .= " AND t.status = 'active'";
        }
        $sql .= ' ORDER BY t.status ASC, t.title ASC, t.id DESC';
        $st = $this->pdo->prepare($sql);
        $st->execute($args);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map([$this, 'present'], $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForTenant(int $tenantId, int $id): ?array
    {
        if (!$this->tablesReady() || $tenantId < 1 || $id < 1) {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT t.*, u.display_name AS author_name
             FROM aar_report_templates t
             LEFT JOIN users u ON u.id = t.created_by_user_id
             WHERE t.tenant_id = ? AND t.id = ? LIMIT 1'
        );
        $st->execute([$tenantId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->present($row) : null;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function save(int $tenantId, ?int $id, int $authorUserId, array $payload): array
    {
        $title = $this->clip((string) ($payload['title'] ?? ''), 200);
        if ($title === '') {
            $title = 'Modèle de debriefing';
        }
        $description = $this->nullable($payload['description'] ?? null, 500);
        $status = strtolower(trim((string) ($payload['status'] ?? 'active')));
        if (!in_array($status, ['active', 'archived'], true)) {
            $status = 'active';
        }
        $fields = AarCustomForm::normalizeFields($payload['fields'] ?? []);
        $json = json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = '[]';
        }

        if ($id !== null && $id > 0) {
            $this->pdo->prepare(
                'UPDATE aar_report_templates
                 SET title = ?, description = ?, status = ?, fields_json = ?, updated_at = UTC_TIMESTAMP()
                 WHERE tenant_id = ? AND id = ?'
            )->execute([$title, $description, $status, $json, $tenantId, $id]);

            return $this->findForTenant($tenantId, $id) ?? [];
        }

        $this->pdo->prepare(
            'INSERT INTO aar_report_templates
             (tenant_id, created_by_user_id, title, description, status, fields_json, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        )->execute([
            $tenantId,
            $authorUserId > 0 ? $authorUserId : null,
            $title,
            $description,
            $status,
            $json,
        ]);

        return $this->findForTenant($tenantId, (int) $this->pdo->lastInsertId()) ?? [];
    }

    public function archive(int $tenantId, int $id): bool
    {
        if (!$this->tablesReady() || $tenantId < 1 || $id < 1) {
            return false;
        }
        $st = $this->pdo->prepare(
            "UPDATE aar_report_templates
             SET status = 'archived', updated_at = UTC_TIMESTAMP()
             WHERE tenant_id = ? AND id = ?"
        );
        $st->execute([$tenantId, $id]);

        return $st->rowCount() > 0;
    }

    public function restore(int $tenantId, int $id): bool
    {
        if (!$this->tablesReady() || $tenantId < 1 || $id < 1) {
            return false;
        }
        $st = $this->pdo->prepare(
            "UPDATE aar_report_templates
             SET status = 'active', updated_at = UTC_TIMESTAMP()
             WHERE tenant_id = ? AND id = ?"
        );
        $st->execute([$tenantId, $id]);

        return $st->rowCount() > 0;
    }

    public function countForTenant(int $tenantId): int
    {
        if (!$this->tablesReady() || $tenantId < 1) {
            return 0;
        }
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM aar_report_templates WHERE tenant_id = ?');
        $st->execute([$tenantId]);

        return (int) $st->fetchColumn();
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function present(array $row): array
    {
        $fields = AarCustomForm::normalizeFields($row['fields_json'] ?? []);
        $status = (string) ($row['status'] ?? 'active');

        return [
            'id' => (int) ($row['id'] ?? 0),
            'title' => (string) ($row['title'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'status' => $status,
            'status_label' => $status === 'archived' ? 'Archivé' : 'Actif',
            'fields' => $fields,
            'field_count' => count($fields),
            'author_name' => (string) ($row['author_name'] ?? ''),
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function clip(string $value, int $max): string
    {
        $value = trim($value);

        return mb_strlen($value) <= $max ? $value : mb_substr($value, 0, $max);
    }

    private function nullable(mixed $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $this->clip($value, $max);
    }
}
