<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use PDO;

/**
 * Diapositives de briefing tactique — gérées côté back-office, consommées in-game (Arma/Eden)
 * via l'extension native qui télécharge l'image et l'applique en texture (setObjectTexture)
 * ou dans le dialog de briefing (RscPicture).
 */
class TacticalBriefingSlideRepository
{
    use LazyDatabaseConnection;


    public function __construct()
    {
        // PDO + schéma à la première requête (pas au boot Container / mod-report).
    }

    protected function onDatabaseConnected(PDO $pdo): void
    {
        try {
            require_once dirname(__DIR__, 2) . '/bootstrap/tactical_briefing_slide_enrichment_migration.php';
            if (function_exists('ensure_tactical_briefing_slide_enrichment_schema')) {
                ensure_tactical_briefing_slide_enrichment_schema($pdo);
            }
        } catch (\Throwable) {
        }
        try {
            require_once dirname(__DIR__, 2) . '/bootstrap/tactical_briefing_slide_operation_migration.php';
            if (function_exists('ensure_tactical_briefing_slide_operation_schema')) {
                ensure_tactical_briefing_slide_operation_schema($pdo);
            }
        } catch (\Throwable) {
        }
    }

    /** @return list<array<string, mixed>> */
    public function allForTenant(int $tenantId): array
    {
        $stmt = $this->pdo()->prepare(
            $this->selectWithOperationSql() . ' WHERE s.tenant_id = ? ORDER BY s.sort_order ASC, s.id ASC'
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listActiveForTenant(int $tenantId): array
    {
        try {
            $stmt = $this->pdo()->prepare(
                $this->selectWithOperationSql() . ' WHERE s.tenant_id = ? AND s.is_active = 1 ORDER BY s.sort_order ASC, s.id ASC'
            );
            $stmt->execute([$tenantId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            if ($e->getCode() === '42S02' || str_contains($e->getMessage(), "doesn't exist")) {
                return [];
            }
            throw $e;
        }
    }

    /**
     * SELECT de base (alias s) avec l'opération rattachée (operation_code / operation_name) si le schéma le permet.
     */
    private function selectWithOperationSql(): string
    {
        if ($this->hasOperationLink()) {
            return 'SELECT s.*, o.code AS operation_code, o.name AS operation_name
                    FROM tactical_briefing_slides s
                    LEFT JOIN operations o ON o.id = s.operation_id AND o.tenant_id = s.tenant_id';
        }

        return 'SELECT s.*, NULL AS operation_code, NULL AS operation_name FROM tactical_briefing_slides s';
    }

    private function hasOperationIdColumn(): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            $stmt = $this->pdo()->query(
                "SHOW COLUMNS FROM tactical_briefing_slides LIKE 'operation_id'"
            );
            $ready = (bool) ($stmt && $stmt->fetchColumn());
        } catch (\Throwable) {
            $ready = false;
        }

        return $ready;
    }

    /** Colonne operation_id présente et table operations disponible (jointure possible). */
    private function hasOperationLink(): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        if (!$this->hasOperationIdColumn()) {
            return $ready = false;
        }
        try {
            $stmt = $this->pdo()->query(
                "SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'operations' LIMIT 1"
            );
            $ready = (bool) ($stmt && $stmt->fetchColumn());
        } catch (\Throwable) {
            $ready = false;
        }

        return $ready;
    }

    private function normalizeOperationId(mixed $raw): ?int
    {
        $id = (int) ($raw ?? 0);

        return $id > 0 ? $id : null;
    }

    /** Applique operation_id après insert/update (colonne optionnelle selon l'état du schéma). */
    private function applyOperationId(int $id, int $tenantId, ?int $operationId): void
    {
        if ($id < 1 || !$this->hasOperationIdColumn()) {
            return;
        }
        $stmt = $this->pdo()->prepare(
            'UPDATE tactical_briefing_slides SET operation_id = ? WHERE id = ? AND tenant_id = ?'
        );
        $stmt->execute([$operationId, $id, $tenantId]);
    }

    private function hasDetailTextColumn(): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            $stmt = $this->pdo()->query(
                "SHOW COLUMNS FROM tactical_briefing_slides LIKE 'detail_text'"
            );
            $ready = (bool) ($stmt && $stmt->fetchColumn());
        } catch (\Throwable) {
            $ready = false;
        }

        return $ready;
    }

    public function findByIdForTenant(int $id, int $tenantId): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM tactical_briefing_slides WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @param array<string, mixed> $data */
    public function insert(int $tenantId, array $data): int
    {
        $detail = $this->normalizeDetail($data['detail_text'] ?? null);
        if ($this->hasDetailTextColumn()) {
            $stmt = $this->pdo()->prepare(
                'INSERT INTO tactical_briefing_slides (tenant_id, title, detail_text, image_path, sort_order, is_active, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $tenantId,
                (string) ($data['title'] ?? ''),
                $detail,
                (string) ($data['image_path'] ?? ''),
                (int) ($data['sort_order'] ?? 0),
                !empty($data['is_active']) ? 1 : 0,
            ]);
        } else {
            $stmt = $this->pdo()->prepare(
                'INSERT INTO tactical_briefing_slides (tenant_id, title, image_path, sort_order, is_active, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $tenantId,
                (string) ($data['title'] ?? ''),
                (string) ($data['image_path'] ?? ''),
                (int) ($data['sort_order'] ?? 0),
                !empty($data['is_active']) ? 1 : 0,
            ]);
        }

        $newId = (int) $this->pdo()->lastInsertId();
        if (array_key_exists('operation_id', $data)) {
            $this->applyOperationId($newId, $tenantId, $this->normalizeOperationId($data['operation_id']));
        }

        return $newId;
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, int $tenantId, array $data): bool
    {
        $detail = $this->normalizeDetail($data['detail_text'] ?? null);
        if ($this->hasDetailTextColumn()) {
            $stmt = $this->pdo()->prepare(
                'UPDATE tactical_briefing_slides
                 SET title = ?, detail_text = ?, image_path = ?, sort_order = ?, is_active = ?, updated_at = NOW()
                 WHERE id = ? AND tenant_id = ?'
            );
            $stmt->execute([
                (string) ($data['title'] ?? ''),
                $detail,
                (string) ($data['image_path'] ?? ''),
                (int) ($data['sort_order'] ?? 0),
                !empty($data['is_active']) ? 1 : 0,
                $id,
                $tenantId,
            ]);
        } else {
            $stmt = $this->pdo()->prepare(
                'UPDATE tactical_briefing_slides
                 SET title = ?, image_path = ?, sort_order = ?, is_active = ?, updated_at = NOW()
                 WHERE id = ? AND tenant_id = ?'
            );
            $stmt->execute([
                (string) ($data['title'] ?? ''),
                (string) ($data['image_path'] ?? ''),
                (int) ($data['sort_order'] ?? 0),
                !empty($data['is_active']) ? 1 : 0,
                $id,
                $tenantId,
            ]);
        }
        $changed = $stmt->rowCount() > 0;
        if (array_key_exists('operation_id', $data)) {
            $this->applyOperationId($id, $tenantId, $this->normalizeOperationId($data['operation_id']));
        }

        return $changed;
    }

    private function normalizeDetail(mixed $raw): ?string
    {
        $detail = trim((string) ($raw ?? ''));
        if ($detail === '') {
            return null;
        }
        if (mb_strlen($detail) > 8000) {
            $detail = mb_substr($detail, 0, 8000);
        }

        return $detail;
    }

    public function delete(int $id, int $tenantId): bool
    {
        $stmt = $this->pdo()->prepare('DELETE FROM tactical_briefing_slides WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$id, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Déplace une diapositive d’un cran (ordre d’affichage).
     * @param 'up'|'down' $direction
     */
    public function moveOrder(int $id, int $tenantId, string $direction): bool
    {
        $row = $this->findByIdForTenant($id, $tenantId);
        if (!$row) {
            return false;
        }
        $current = (int) ($row['sort_order'] ?? 0);
        $all = $this->allForTenant($tenantId);
        $idx = -1;
        foreach ($all as $i => $r) {
            if ((int) ($r['id'] ?? 0) === $id) {
                $idx = $i;
                break;
            }
        }
        if ($idx < 0) {
            return false;
        }
        $swapIdx = $direction === 'up' ? $idx - 1 : $idx + 1;
        if ($swapIdx < 0 || $swapIdx >= count($all)) {
            return false;
        }
        $other = $all[$swapIdx];
        $otherId = (int) ($other['id'] ?? 0);
        $otherOrder = (int) ($other['sort_order'] ?? 0);
        if ($otherId < 1) {
            return false;
        }
        // Si ordres identiques, forcer un écart.
        $newCurrent = $otherOrder;
        $newOther = $current;
        if ($newCurrent === $newOther) {
            $newCurrent = $direction === 'up' ? $current - 1 : $current + 1;
            $newOther = $current;
        }
        $st = $this->pdo()->prepare(
            'UPDATE tactical_briefing_slides SET sort_order = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?'
        );
        $st->execute([$newCurrent, $id, $tenantId]);
        $st->execute([$newOther, $otherId, $tenantId]);

        return true;
    }

    public function setActive(int $id, int $tenantId, bool $active): bool
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE tactical_briefing_slides SET is_active = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?'
        );
        $stmt->execute([$active ? 1 : 0, $id, $tenantId]);

        return $stmt->rowCount() > 0;
    }
}
