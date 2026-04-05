<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class UnitRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function allForTenant(int $tenantId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM units WHERE tenant_id = ? ORDER BY display_order ASC, name ASC');
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Unités visibles sur la fiche publique vitrine (ORBAT public). */
    public function listPublicForTenant(int $tenantId): array
    {
        if (!$this->columnExists('units', 'show_on_public_page')) {
            return $this->allForTenant($tenantId);
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM units WHERE tenant_id = ? AND show_on_public_page = 1 ORDER BY display_order ASC, name ASC'
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countPublicForTenant(int $tenantId): int
    {
        if (!$this->columnExists('units', 'show_on_public_page')) {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM units WHERE tenant_id = ?');
            $stmt->execute([$tenantId]);

            return (int) $stmt->fetchColumn();
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM units WHERE tenant_id = ? AND show_on_public_page = 1');
        $stmt->execute([$tenantId]);

        return (int) $stmt->fetchColumn();
    }

    /** @return array<int, int> unit_id => effectif actif rattaché (affectations ouvertes) */
    public function countActiveMembersByUnitForTenant(int $tenantId): array
    {
        $sql = 'SELECT uu.unit_id, COUNT(*) AS c
            FROM user_units uu
            INNER JOIN users u ON u.id = uu.user_id AND u.tenant_id = ?
            WHERE u.status = \'active\' AND (uu.ended_at IS NULL OR uu.ended_at > NOW())
            GROUP BY uu.unit_id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$tenantId]);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $out[(int) $row['unit_id']] = (int) $row['c'];
        }

        return $out;
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table, $column]);

        return (bool) $stmt->fetchColumn();
    }

    public function getByType(int $tenantId, string $type): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM units WHERE tenant_id = ? AND type = ? ORDER BY display_order ASC, name ASC');
        $stmt->execute([$tenantId, $type]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTeams(int $tenantId): array
    {
        return $this->getByType($tenantId, 'team');
    }

    public function getGroups(int $tenantId): array
    {
        return $this->getByType($tenantId, 'group');
    }

    public function findById(int $id, ?int $tenantId = null): ?array
    {
        $sql = 'SELECT * FROM units WHERE id = ?';
        $params = [$id];
        if ($tenantId !== null) {
            $sql .= ' AND tenant_id = ?';
            $params[] = $tenantId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getTree(int $tenantId): array
    {
        $all = $this->allForTenant($tenantId);
        $byParent = [];
        foreach ($all as $u) {
            $pid = (int) ($u['parent_id'] ?? 0);
            $byParent[$pid][] = $u;
        }
        return $this->buildTree($byParent, 0);
    }

    private function buildTree(array $byParent, int $parentId): array
    {
        $out = [];
        foreach ($byParent[$parentId] ?? [] as $u) {
            $u['children'] = $this->buildTree($byParent, (int) $u['id']);
            $out[] = $u;
        }
        return $out;
    }

    public function create(int $tenantId, array $data): array
    {
        $slug = $data['slug'] ?? $this->slugify($data['name'] ?? '');
        $publicBlurb = isset($data['public_blurb']) ? (string) $data['public_blurb'] : null;
        $publicTagsJson = $this->encodePublicTags($data['public_tags'] ?? null);
        $showPublic = array_key_exists('show_on_public_page', $data)
            ? ((int) $data['show_on_public_page'] ? 1 : 0)
            : 1;

        if ($this->columnExists('units', 'public_blurb')) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO units (tenant_id, parent_id, name, slug, type, code, commander_user_id, display_order, public_blurb, public_tags, show_on_public_page, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $tenantId,
                isset($data['parent_id']) ? (int) $data['parent_id'] : null,
                $data['name'] ?? '',
                $slug,
                $data['type'] ?? null,
                $data['code'] ?? null,
                isset($data['commander_user_id']) ? (int) $data['commander_user_id'] : null,
                (int) ($data['display_order'] ?? 0),
                $publicBlurb !== '' ? $publicBlurb : null,
                $publicTagsJson,
                $showPublic,
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO units (tenant_id, parent_id, name, slug, type, code, commander_user_id, display_order, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $tenantId,
                isset($data['parent_id']) ? (int) $data['parent_id'] : null,
                $data['name'] ?? '',
                $slug,
                $data['type'] ?? null,
                $data['code'] ?? null,
                isset($data['commander_user_id']) ? (int) $data['commander_user_id'] : null,
                (int) ($data['display_order'] ?? 0),
            ]);
        }
        $id = (int) $this->pdo->lastInsertId();
        $row = $this->findById($id, $tenantId);
        return $row ?? [];
    }

    public function update(int $id, int $tenantId, array $data): bool
    {
        $fields = [];
        $params = [];
        $allowed = ['parent_id', 'name', 'slug', 'type', 'code', 'commander_user_id', 'display_order'];
        if ($this->columnExists('units', 'public_blurb')) {
            $allowed[] = 'public_blurb';
            $allowed[] = 'public_tags';
            $allowed[] = 'show_on_public_page';
        }
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            if ($key === 'public_tags') {
                $fields[] = 'public_tags = ?';
                $params[] = $this->encodePublicTags($data['public_tags']);

                continue;
            }
            $fields[] = $key . ' = ?';
            if ($key === 'show_on_public_page') {
                $params[] = !empty($data[$key]) ? 1 : 0;
            } elseif ($key === 'parent_id' || $key === 'commander_user_id' || $key === 'display_order') {
                $params[] = $data[$key] !== '' && $data[$key] !== null ? (int) $data[$key] : null;
            } elseif ($key === 'public_blurb') {
                $v = trim((string) $data[$key]);
                $params[] = $v === '' ? null : $v;
            } else {
                $params[] = $data[$key];
            }
        }
        if (empty($fields)) return true;
        $fields[] = 'updated_at = NOW()';
        $params[] = $id;
        $params[] = $tenantId;
        $stmt = $this->pdo->prepare('UPDATE units SET ' . implode(', ', $fields) . ' WHERE id = ? AND tenant_id = ?');
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $id, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM units WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$id, $tenantId]);
        return $stmt->rowCount() > 0;
    }

    public function slugExists(int $tenantId, string $slug, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM units WHERE tenant_id = ? AND slug = ?';
        $params = [$tenantId, $slug];
        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    private function slugify(string $name): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', trim($name));
        return strtolower(trim($slug, '-') ?: 'unite');
    }

    /**
     * @param mixed $raw list<string>, JSON string, ou null
     */
    private function encodePublicTags(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_string($raw)) {
            $t = trim($raw);
            if ($t === '') {
                return null;
            }
            $dec = json_decode($t, true);
            if (is_array($dec)) {
                $raw = $dec;
            } else {
                $parts = array_map('trim', preg_split('/[,;\n]+/', $t) ?: []);
                $raw = array_values(array_filter($parts, static fn ($s) => $s !== ''));
            }
        }
        if (!is_array($raw)) {
            return null;
        }
        $tags = [];
        foreach ($raw as $t) {
            if (!is_string($t)) {
                continue;
            }
            $t = trim($t);
            if ($t !== '' && count($tags) < 24) {
                $tags[] = mb_substr($t, 0, 64);
            }
        }
        if ($tags === []) {
            return null;
        }
        $json = json_encode(array_values($tags), JSON_UNESCAPED_UNICODE);

        return $json !== false ? $json : null;
    }
}
