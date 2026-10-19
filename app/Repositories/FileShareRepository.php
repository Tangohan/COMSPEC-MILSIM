<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use PDO;

/**
 * Partage de fichiers d'une communauté (tables tenant_share_*). Toutes les requêtes sont bornées à la communauté :
 * un identifiant d'une autre communauté ne renvoie jamais rien.
 */
class FileShareRepository
{
    use LazyDatabaseConnection;

    private static ?bool $ready = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public function schemaReady(): bool
    {
        if (self::$ready === null) {
            try {
                $st = $this->pdo()->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('tenant_share_folders', 'tenant_share_files', 'tenant_share_grants', 'tenant_share_events')");
                self::$ready = $st !== false && (int) $st->fetchColumn() === 4;
            } catch (\Throwable) {
                self::$ready = false;
            }
        }

        return self::$ready;
    }

    /** @return list<array<string, mixed>> */
    private function rows(string $sql, array $params = []): array
    {
        $st = $this->pdo()->prepare($sql);
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    private function row(string $sql, array $params = []): ?array
    {
        $st = $this->pdo()->prepare($sql);
        $st->execute($params);
        $r = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($r) ? $r : null;
    }

    private function exec(string $sql, array $params = []): int
    {
        $st = $this->pdo()->prepare($sql);
        $st->execute($params);

        return $st->rowCount();
    }

    // ——— Personne : rôles et unités ———

    /** @return list<int> */
    public function roleIdsForUser(int $tenantId, int $userId): array
    {
        $ids = [];
        try {
            foreach ($this->rows('SELECT role_id FROM tenant_user_roles WHERE tenant_id = ? AND user_id = ? AND (valid_until IS NULL OR valid_until > NOW())', [$tenantId, $userId]) as $r) {
                $ids[] = (int) $r['role_id'];
            }
        } catch (\Throwable) {
        }
        $legacy = $this->row('SELECT u.role_id FROM users u INNER JOIN roles r ON r.id = u.role_id AND r.tenant_id = ? WHERE u.id = ?', [$tenantId, $userId]);
        if ($legacy !== null && (int) $legacy['role_id'] > 0) {
            $ids[] = (int) $legacy['role_id'];
        }

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }

    /** @return list<int> */
    public function unitIdsForUser(int $tenantId, int $userId): array
    {
        $ids = [];
        foreach ($this->rows('SELECT uu.unit_id FROM user_units uu INNER JOIN units un ON un.id = uu.unit_id AND un.tenant_id = ? WHERE uu.user_id = ? AND (uu.ended_at IS NULL OR uu.ended_at > NOW())', [$tenantId, $userId]) as $r) {
            $ids[] = (int) $r['unit_id'];
        }
        try {
            foreach ($this->rows("SELECT pa.unit_id FROM personnel_assignments pa INNER JOIN units un ON un.id = pa.unit_id AND un.tenant_id = ? WHERE pa.user_id = ? AND pa.status = 'active' AND (pa.ended_at IS NULL OR pa.ended_at > CURDATE())", [$tenantId, $userId]) as $r) {
                $ids[] = (int) $r['unit_id'];
            }
        } catch (\Throwable) {
            // personnel_assignments absente sur les vieilles installations.
        }

        return array_values(array_unique($ids));
    }

    /** @return array<int, int> id d'unité => parent */
    public function unitParents(int $tenantId): array
    {
        $out = [];
        foreach ($this->rows('SELECT id, parent_id FROM units WHERE tenant_id = ?', [$tenantId]) as $r) {
            $out[(int) $r['id']] = (int) ($r['parent_id'] ?? 0);
        }

        return $out;
    }

    private function memberPredicate(string $alias): string
    {
        return '(' . $alias . '.tenant_id = :tid OR EXISTS (SELECT 1 FROM user_community_memberships m WHERE m.user_id = ' . $alias . ".id AND m.tenant_id = :tid2 AND m.status = 'active'))";
    }

    /** @return list<array{id:int, label:string}> */
    public function members(int $tenantId): array
    {
        try {
            $rows = $this->rows('SELECT u.id, u.display_name, u.callsign FROM users u WHERE ' . $this->memberPredicate('u') . " AND u.status = 'active' ORDER BY COALESCE(NULLIF(u.callsign, ''), u.display_name) LIMIT 2000", ['tid' => $tenantId, 'tid2' => $tenantId]);
        } catch (\Throwable) {
            $rows = $this->rows("SELECT u.id, u.display_name, u.callsign FROM users u WHERE u.tenant_id = ? AND u.status = 'active' ORDER BY u.display_name LIMIT 2000", [$tenantId]);
        }

        return array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'label' => self::userLabel($r)], $rows);
    }

    public function isMember(int $tenantId, int $userId): bool
    {
        try {
            return $this->row('SELECT u.id FROM users u WHERE u.id = :uid AND ' . $this->memberPredicate('u') . " AND u.status = 'active'", ['uid' => $userId, 'tid' => $tenantId, 'tid2' => $tenantId]) !== null;
        } catch (\Throwable) {
            return $this->row("SELECT id FROM users WHERE id = ? AND tenant_id = ? AND status = 'active'", [$userId, $tenantId]) !== null;
        }
    }

    /** @return list<array{id:int, label:string}> */
    public function roles(int $tenantId): array
    {
        $rows = $this->rows("SELECT id, name FROM roles WHERE tenant_id = ? AND role_layer IN ('community', 'intra') ORDER BY name", [$tenantId]);

        return array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'label' => (string) $r['name']], $rows);
    }

    /** @return list<array{id:int, label:string}> */
    public function units(int $tenantId): array
    {
        $rows = $this->rows('SELECT id, name FROM units WHERE tenant_id = ? ORDER BY display_order, name', [$tenantId]);

        return array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'label' => (string) $r['name']], $rows);
    }

    /** @param array<string, mixed> $r */
    public static function userLabel(array $r): string
    {
        $cs = trim((string) ($r['callsign'] ?? ''));
        $dn = trim((string) ($r['display_name'] ?? ''));
        if ($cs !== '' && $dn !== '' && strcasecmp($cs, $dn) !== 0) {
            return $cs . ' (' . $dn . ')';
        }

        return $cs !== '' ? $cs : ($dn !== '' ? $dn : 'Membre');
    }

    /** @param list<int> $ids @return array<int, string> */
    public function userLabels(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }
        $out = [];
        foreach ($this->rows('SELECT id, display_name, callsign FROM users WHERE id IN (' . implode(',', $ids) . ')') as $r) {
            $out[(int) $r['id']] = self::userLabel($r);
        }

        return $out;
    }

    // ——— Dossiers ———

    /** @return list<array<string, mixed>> Tous les dossiers de la communauté, corbeille comprise. */
    public function allFolders(int $tenantId): array
    {
        return $this->rows('SELECT id, parent_id, name, description, owner_user_id, created_at, updated_at, deleted_at, deleted_by FROM tenant_share_folders WHERE tenant_id = ? ORDER BY name', [$tenantId]);
    }

    public function createFolder(int $tenantId, ?int $parentId, string $name, string $description, int $ownerId): int
    {
        $this->exec('INSERT INTO tenant_share_folders (tenant_id, parent_id, name, description, owner_user_id) VALUES (?, ?, ?, ?, ?)', [$tenantId, $parentId, $name, $description !== '' ? $description : null, $ownerId]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function updateFolder(int $tenantId, int $id, string $name, string $description): void
    {
        $this->exec('UPDATE tenant_share_folders SET name = ?, description = ? WHERE id = ? AND tenant_id = ?', [$name, $description !== '' ? $description : null, $id, $tenantId]);
    }

    public function setFolderOwner(int $tenantId, int $id, int $ownerId): void
    {
        $this->exec('UPDATE tenant_share_folders SET owner_user_id = ? WHERE id = ? AND tenant_id = ?', [$ownerId, $id, $tenantId]);
    }

    public function trashFolder(int $tenantId, int $id, int $by): void
    {
        $this->exec('UPDATE tenant_share_folders SET deleted_at = NOW(), deleted_by = ? WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL', [$by, $id, $tenantId]);
    }

    public function restoreFolder(int $tenantId, int $id): void
    {
        $this->exec('UPDATE tenant_share_folders SET deleted_at = NULL, deleted_by = NULL WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }

    /**
     * Efface définitivement des dossiers (et leurs fichiers, partages, journal). Renvoie les chemins des fichiers à
     * effacer du disque.
     *
     * @param list<int> $folderIds
     * @return list<string>
     */
    public function purgeFolders(int $tenantId, array $folderIds): array
    {
        $folderIds = array_values(array_filter(array_map('intval', $folderIds)));
        if ($folderIds === []) {
            return [];
        }
        $in = implode(',', $folderIds);
        $paths = array_map(static fn (array $r): string => (string) $r['stored_path'], $this->rows("SELECT stored_path FROM tenant_share_files WHERE tenant_id = ? AND folder_id IN ($in)", [$tenantId]));
        $fileIds = array_map(static fn (array $r): int => (int) $r['id'], $this->rows("SELECT id FROM tenant_share_files WHERE tenant_id = ? AND folder_id IN ($in)", [$tenantId]));
        if ($fileIds !== []) {
            $this->exec('DELETE FROM tenant_share_grants WHERE tenant_id = ? AND file_id IN (' . implode(',', $fileIds) . ')', [$tenantId]);
        }
        $this->exec("DELETE FROM tenant_share_files WHERE tenant_id = ? AND folder_id IN ($in)", [$tenantId]);
        $this->exec("DELETE FROM tenant_share_grants WHERE tenant_id = ? AND folder_id IN ($in)", [$tenantId]);
        $this->exec("DELETE FROM tenant_share_folders WHERE tenant_id = ? AND id IN ($in)", [$tenantId]);

        return $paths;
    }

    // ——— Fichiers ———

    /** @return list<array<string, mixed>> */
    public function filesInFolder(int $tenantId, int $folderId): array
    {
        return $this->rows('SELECT * FROM tenant_share_files WHERE tenant_id = ? AND folder_id = ? AND deleted_at IS NULL ORDER BY name', [$tenantId, $folderId]);
    }

    /** @param list<int> $ids @return list<array<string, mixed>> */
    public function filesByIds(int $tenantId, array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }

        return $this->rows('SELECT * FROM tenant_share_files WHERE tenant_id = ? AND id IN (' . implode(',', $ids) . ') ORDER BY name', [$tenantId]);
    }

    /** @return array<string, mixed>|null */
    public function file(int $tenantId, int $id): ?array
    {
        return $this->row('SELECT * FROM tenant_share_files WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }

    /** @return array<int, array{files:int, bytes:int}> par dossier (hors corbeille) */
    public function folderStats(int $tenantId): array
    {
        $out = [];
        foreach ($this->rows('SELECT folder_id, COUNT(*) n, COALESCE(SUM(size_bytes), 0) b FROM tenant_share_files WHERE tenant_id = ? AND deleted_at IS NULL GROUP BY folder_id', [$tenantId]) as $r) {
            $out[(int) $r['folder_id']] = ['files' => (int) $r['n'], 'bytes' => (int) $r['b']];
        }

        return $out;
    }

    public function tenantUsage(int $tenantId): int
    {
        $r = $this->row('SELECT COALESCE(SUM(size_bytes), 0) b FROM tenant_share_files WHERE tenant_id = ?', [$tenantId]);

        return (int) ($r['b'] ?? 0);
    }

    /** @param array{path:string, mime:string, size:int, sha256:string} $stored */
    public function addFile(int $tenantId, int $folderId, string $name, array $stored, int $by): int
    {
        $this->exec('INSERT INTO tenant_share_files (tenant_id, folder_id, name, stored_path, mime, size_bytes, sha256, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [$tenantId, $folderId, $name, $stored['path'], $stored['mime'], $stored['size'], $stored['sha256'], $by]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function renameFile(int $tenantId, int $id, string $name): void
    {
        $this->exec('UPDATE tenant_share_files SET name = ? WHERE id = ? AND tenant_id = ?', [$name, $id, $tenantId]);
    }

    public function trashFile(int $tenantId, int $id, int $by): void
    {
        $this->exec('UPDATE tenant_share_files SET deleted_at = NOW(), deleted_by = ? WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL', [$by, $id, $tenantId]);
    }

    public function restoreFile(int $tenantId, int $id): void
    {
        $this->exec('UPDATE tenant_share_files SET deleted_at = NULL, deleted_by = NULL WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }

    public function purgeFile(int $tenantId, int $id): ?string
    {
        $f = $this->file($tenantId, $id);
        if ($f === null) {
            return null;
        }
        $this->exec('DELETE FROM tenant_share_grants WHERE tenant_id = ? AND file_id = ?', [$tenantId, $id]);
        $this->exec('DELETE FROM tenant_share_files WHERE tenant_id = ? AND id = ?', [$tenantId, $id]);

        return (string) $f['stored_path'];
    }

    public function countDownload(int $tenantId, int $id): void
    {
        $this->exec('UPDATE tenant_share_files SET download_count = download_count + 1 WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }

    /** @return list<array<string, mixed>> Fichiers à la corbeille dont le dossier, lui, ne l'est pas. */
    public function trashedFiles(int $tenantId): array
    {
        return $this->rows('SELECT fi.* FROM tenant_share_files fi INNER JOIN tenant_share_folders fo ON fo.id = fi.folder_id AND fo.tenant_id = fi.tenant_id WHERE fi.tenant_id = ? AND fi.deleted_at IS NOT NULL AND fo.deleted_at IS NULL ORDER BY fi.deleted_at DESC LIMIT 300', [$tenantId]);
    }

    // ——— Partages ———

    /** @return list<array<string, mixed>> Tous les partages de la communauté. */
    public function allGrants(int $tenantId): array
    {
        return $this->rows('SELECT * FROM tenant_share_grants WHERE tenant_id = ? ORDER BY id', [$tenantId]);
    }

    public function addGrant(int $tenantId, ?int $folderId, ?int $fileId, string $targetType, int $targetId, string $level, ?string $expiresAt, int $by): int
    {
        // Un même destinataire n'a qu'un partage par élément : le nouveau remplace l'ancien.
        $this->exec(
            'DELETE FROM tenant_share_grants WHERE tenant_id = ? AND target_type = ? AND target_id = ? AND ' . ($folderId !== null ? 'folder_id = ?' : 'file_id = ?'),
            [$tenantId, $targetType, $targetId, $folderId ?? $fileId]
        );
        $this->exec('INSERT INTO tenant_share_grants (tenant_id, folder_id, file_id, target_type, target_id, level, expires_at, granted_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [$tenantId, $folderId, $fileId, $targetType, $targetId, $level, $expiresAt, $by]);

        return (int) $this->pdo()->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function grant(int $tenantId, int $id): ?array
    {
        return $this->row('SELECT * FROM tenant_share_grants WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }

    public function deleteGrant(int $tenantId, int $id): void
    {
        $this->exec('DELETE FROM tenant_share_grants WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }

    /** @return array{user:array<int,string>, role:array<int,string>, unit:array<int,string>} */
    public function targetLabels(int $tenantId): array
    {
        $out = ['user' => [], 'role' => [], 'unit' => []];
        $userIds = array_map(static fn (array $r): int => (int) $r['target_id'], $this->rows("SELECT DISTINCT target_id FROM tenant_share_grants WHERE tenant_id = ? AND target_type = 'user'", [$tenantId]));
        $out['user'] = $this->userLabels($userIds);
        foreach ($this->roles($tenantId) as $r) {
            $out['role'][$r['id']] = $r['label'];
        }
        foreach ($this->units($tenantId) as $u) {
            $out['unit'][$u['id']] = $u['label'];
        }

        return $out;
    }

    // ——— Journal ———

    public function log(int $tenantId, int $userId, ?int $folderId, ?int $fileId, string $action, string $detail = '', string $ip = ''): void
    {
        try {
            $this->exec('INSERT INTO tenant_share_events (tenant_id, user_id, folder_id, file_id, action, detail, ip) VALUES (?, ?, ?, ?, ?, ?, ?)', [$tenantId, $userId, $folderId, $fileId, $action, mb_substr($detail, 0, 255), $ip !== '' ? mb_substr($ip, 0, 45) : null]);
        } catch (\Throwable) {
            // Le journal ne doit jamais bloquer l'action.
        }
    }

    /** @param list<int> $folderIds @return list<array<string, mixed>> */
    public function events(int $tenantId, array $folderIds, int $limit = 80): array
    {
        $folderIds = array_values(array_filter(array_map('intval', $folderIds)));
        if ($folderIds === []) {
            return [];
        }
        $in = implode(',', $folderIds);

        return $this->rows(
            "SELECT e.*, fi.name AS file_name, fo.name AS folder_name FROM tenant_share_events e
             LEFT JOIN tenant_share_files fi ON fi.id = e.file_id AND fi.tenant_id = e.tenant_id
             LEFT JOIN tenant_share_folders fo ON fo.id = e.folder_id AND fo.tenant_id = e.tenant_id
             WHERE e.tenant_id = ? AND (e.folder_id IN ($in) OR fi.folder_id IN ($in))
             ORDER BY e.id DESC LIMIT " . max(1, min(500, $limit)),
            [$tenantId]
        );
    }
}
