<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Services\PhotoLibrary\PhotoGeometry;

/**
 * Photothèque : lecture des photos terrain (recon_images) filtrées pour un lecteur, et tables propres à la photothèque
 * (visages, masques, commentaires, étiquettes, état des fichiers, versements SSE). Toutes les requêtes portent la
 * communauté ; un identifiant d'une autre communauté ne retourne rien. Chaque lecture tolère l'absence des tables (étape
 * de migration pas encore passée) : listes vides, jamais d'erreur.
 */
class PhotoLibraryRepository
{
    public const TABLES = ['photo_library_tags', 'photo_library_masks', 'photo_library_comments', 'photo_library_labels', 'photo_library_state', 'photo_library_sse'];

    /** Tri accepté => ORDER BY (colonnes de recon_images, alias r). */
    public const SORTS = [
        'recent' => 'COALESCE(r.captured_at, r.created_at) DESC, r.id DESC',
        'ancien' => 'COALESCE(r.captured_at, r.created_at) ASC, r.id ASC',
        'auteur' => 'r.author_callsign ASC, COALESCE(r.captured_at, r.created_at) DESC, r.id DESC',
        'lieu' => 'r.grid_ref ASC, COALESCE(r.captured_at, r.created_at) DESC, r.id DESC',
    ];

    private ?Database $db;

    /** @var array<string, bool> */
    private array $memo = [];

    public function __construct(?Database $db = null)
    {
        $this->db = $db;
    }

    protected function db(): Database
    {
        return $this->db ??= Database::getInstance();
    }

    // ----------------------------------------------------------- Schéma

    public function tableExists(string $table): bool
    {
        $key = 't:' . $table;
        if (!array_key_exists($key, $this->memo)) {
            try {
                $this->db()->fetchOne('SELECT 1 FROM `' . str_replace('`', '', $table) . '` LIMIT 1');
                $this->memo[$key] = true;
            } catch (\Throwable) {
                $this->memo[$key] = false;
            }
        }

        return $this->memo[$key];
    }

    /** Photos terrain présentes et tables de la photothèque créées (étape photo_library passée). */
    public function ready(): bool
    {
        foreach (array_merge(['recon_images'], self::TABLES) as $t) {
            if (!$this->tableExists($t)) {
                return false;
            }
        }

        return true;
    }

    public function photosReady(): bool
    {
        return $this->tableExists('recon_images');
    }

    public function reconHasColumn(string $column): bool
    {
        $key = 'c:' . $column;
        if (!array_key_exists($key, $this->memo)) {
            try {
                $this->memo[$key] = $this->db()->fetchOne(
                    'SELECT 1 AS ok FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = \'recon_images\' AND COLUMN_NAME = :c LIMIT 1',
                    ['c' => $column]
                ) !== null;
            } catch (\Throwable) {
                $this->memo[$key] = false;
            }
        }

        return $this->memo[$key];
    }

    private function col(string $column, string $alias = 'r'): string
    {
        return $this->reconHasColumn($column) ? $alias . '.' . $column : 'NULL';
    }

    // ----------------------------------------------------------- Photos

    /**
     * Photos visibles pour ce lecteur selon ses portes (le service refait le contrôle de chaque ligne).
     *
     * @param array{q?: string, author?: string, unit?: string, album?: string, from?: string, to?: string, grid?: string, label?: string, person?: string, sse?: string} $f
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function listPhotos(int $tenantId, array $f, string $sort, int $limit, int $offset, bool $door, bool $clearConfidential): array
    {
        if ($tenantId < 1 || !$this->photosReady()) {
            return ['rows' => [], 'total' => 0];
        }
        [$where, $params] = $this->photoWhere($tenantId, $f, $door, $clearConfidential);
        $order = self::SORTS[$sort] ?? self::SORTS['recent'];
        $ready = $this->ready();
        $sseExists = $ready ? 'EXISTS (SELECT 1 FROM photo_library_sse s WHERE s.tenant_id = r.tenant_id AND s.photo_id = r.id AND s.withdrawn_at IS NULL)' : '0';
        $tagCount = $ready ? '(SELECT COUNT(*) FROM photo_library_tags t WHERE t.tenant_id = r.tenant_id AND t.photo_id = r.id' . ($door ? '' : " AND t.target_type <> 'sse'") . ')' : '0';
        $commentCount = $ready ? '(SELECT COUNT(*) FROM photo_library_comments c WHERE c.tenant_id = r.tenant_id AND c.photo_id = r.id AND c.deleted_at IS NULL)' : '0';
        $maskLast = $ready ? '(SELECT COALESCE(MAX(m2.id), 0) FROM photo_library_masks m2 WHERE m2.tenant_id = r.tenant_id AND m2.photo_id = r.id)' : '0';
        $maskCount = $ready ? '(SELECT COUNT(*) FROM photo_library_masks m WHERE m.tenant_id = r.tenant_id AND m.photo_id = r.id)' : '0';
        try {
            $total = (int) ($this->db()->fetchOne('SELECT COUNT(*) AS n FROM recon_images r WHERE ' . $where, $params)['n'] ?? 0);
            $rows = $this->db()->fetchAll(
                'SELECT r.id, r.tenant_id, r.author_callsign, r.unit_name, r.mission_id, r.caption, r.grid_ref, r.device_type, r.image_path,
                        r.captured_at, r.created_at, ' . $this->col('title') . ' AS title, ' . $this->col('description') . ' AS description,
                        ' . $this->col('is_confidential') . ' AS is_confidential, ' . $this->col('is_sse') . ' AS is_sse,
                        ' . $this->col('deleted_at') . ' AS deleted_at, ' . $this->col('author_user_id') . ' AS author_user_id,
                        ' . $sseExists . ' AS sse_versed, ' . $tagCount . ' AS tags_n, ' . $commentCount . ' AS comments_n, ' . $maskCount . ' AS masks_n, ' . $maskLast . ' AS masks_last
                 FROM recon_images r WHERE ' . $where . ' ORDER BY ' . $order . ' LIMIT ' . max(1, min(120, $limit)) . ' OFFSET ' . max(0, $offset),
                $params
            );
        } catch (\Throwable $e) {
            error_log('Photothèque : liste illisible : ' . $e->getMessage());

            return ['rows' => [], 'total' => 0];
        }

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * @param array<string, mixed> $f
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function photoWhere(int $tenantId, array $f, bool $door, bool $clearConfidential): array
    {
        $ready = $this->ready();
        $where = ['r.tenant_id = :tenant'];
        $params = ['tenant' => $tenantId];
        if ($this->reconHasColumn('deleted_at')) {
            $where[] = 'r.deleted_at IS NULL';
        }
        // Flux des caméras (casque, drone) et ancienne live cam : pas des photos.
        $where[] = "(r.unit_name IS NULL OR (r.unit_name NOT LIKE 'chest:%' AND r.unit_name NOT LIKE 'helmet:%' AND r.unit_name NOT LIKE 'drone:%'))";
        if ($this->reconHasColumn('is_confidential') && !$clearConfidential) {
            $where[] = "(r.is_confidential = 0 AND r.image_path NOT LIKE 'recon-private/%')";
        }
        if ($ready && !$door) {
            $where[] = 'NOT EXISTS (SELECT 1 FROM photo_library_sse sx WHERE sx.tenant_id = r.tenant_id AND sx.photo_id = r.id AND sx.withdrawn_at IS NULL)';
        }
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . self::likeEscape($q) . '%';
            $parts = ['r.caption LIKE :q1', 'r.author_callsign LIKE :q2', 'r.mission_id LIKE :q3', 'r.grid_ref LIKE :q4'];
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
            if ($this->reconHasColumn('title')) {
                $parts[] = 'r.title LIKE :q5';
                $params['q5'] = $like;
            }
            if ($this->reconHasColumn('description')) {
                $parts[] = 'r.description LIKE :q6';
                $params['q6'] = $like;
            }
            if ($ready) {
                $parts[] = 'EXISTS (SELECT 1 FROM photo_library_labels lq WHERE lq.tenant_id = r.tenant_id AND lq.photo_id = r.id AND lq.label LIKE :q7)';
                $params['q7'] = $like;
            }
            $where[] = '(' . implode(' OR ', $parts) . ')';
        }
        foreach (['author' => 'r.author_callsign', 'unit' => 'r.unit_name', 'album' => 'r.mission_id'] as $key => $column) {
            $v = trim((string) ($f[$key] ?? ''));
            if ($v !== '') {
                $where[] = $column . ' = :' . $key;
                $params[$key] = mb_substr($v, 0, 190);
            }
        }
        $from = self::day((string) ($f['from'] ?? ''));
        if ($from !== null) {
            $where[] = 'COALESCE(r.captured_at, r.created_at) >= :dfrom';
            $params['dfrom'] = $from . ' 00:00:00';
        }
        $to = self::day((string) ($f['to'] ?? ''));
        if ($to !== null) {
            $where[] = 'COALESCE(r.captured_at, r.created_at) <= :dto';
            $params['dto'] = $to . ' 23:59:59';
        }
        $grid = trim((string) ($f['grid'] ?? ''));
        if ($grid !== '') {
            $where[] = 'r.grid_ref LIKE :grid';
            $params['grid'] = self::likeEscape(mb_substr($grid, 0, 32)) . '%';
        }
        if ($ready) {
            $label = PhotoGeometry::labelNorm((string) ($f['label'] ?? ''));
            if ($label !== '') {
                $where[] = 'EXISTS (SELECT 1 FROM photo_library_labels lf WHERE lf.tenant_id = r.tenant_id AND lf.photo_id = r.id AND lf.label_norm = :label)';
                $params['label'] = $label;
            }
            $person = trim((string) ($f['person'] ?? ''));
            if ($person === 'any') {
                $where[] = 'EXISTS (SELECT 1 FROM photo_library_tags tp WHERE tp.tenant_id = r.tenant_id AND tp.photo_id = r.id' . ($door ? '' : " AND tp.target_type <> 'sse'") . ')';
            } elseif (preg_match('/^m(\d{1,10})$/', $person, $m) === 1) {
                $where[] = "EXISTS (SELECT 1 FROM photo_library_tags tm WHERE tm.tenant_id = r.tenant_id AND tm.photo_id = r.id AND tm.target_type = 'member' AND tm.target_id = :pmember)";
                $params['pmember'] = (int) $m[1];
            } elseif ($door && preg_match('/^s(\d{1,10})$/', $person, $m) === 1) {
                $where[] = "EXISTS (SELECT 1 FROM photo_library_tags ts WHERE ts.tenant_id = r.tenant_id AND ts.photo_id = r.id AND ts.target_type = 'sse' AND ts.target_id = :psse)";
                $params['psse'] = (int) $m[1];
            } elseif ($person === 'unknown') {
                $where[] = "EXISTS (SELECT 1 FROM photo_library_tags tu WHERE tu.tenant_id = r.tenant_id AND tu.photo_id = r.id AND tu.target_type = 'unknown')";
            }
            $sse = (string) ($f['sse'] ?? '');
            if ($door && $sse === 'sse') {
                $where[] = 'EXISTS (SELECT 1 FROM photo_library_sse sy WHERE sy.tenant_id = r.tenant_id AND sy.photo_id = r.id AND sy.withdrawn_at IS NULL)';
            } elseif ($sse === 'none') {
                $where[] = 'NOT EXISTS (SELECT 1 FROM photo_library_sse sn WHERE sn.tenant_id = r.tenant_id AND sn.photo_id = r.id AND sn.withdrawn_at IS NULL)';
            }
        }

        return [implode(' AND ', $where), $params];
    }

    /** @return array<string, mixed>|null */
    public function photo(int $tenantId, int $photoId): ?array
    {
        if ($tenantId < 1 || $photoId < 1 || !$this->photosReady()) {
            return null;
        }
        try {
            $row = $this->db()->fetchOne('SELECT * FROM recon_images WHERE tenant_id = :t AND id = :id', ['t' => $tenantId, 'id' => $photoId]);
            if ($row === null) {
                return null;
            }
            if (!array_key_exists('author_user_id', $row)) {
                $row['author_user_id'] = null;
            }
            $row['sse_versed'] = $this->activeTransfer($tenantId, $photoId) !== null ? 1 : 0;

            return $row;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Valeurs proposées par les filtres : auteurs, unités, albums (missions), étiquettes les plus posées.
     *
     * @return array{authors: list<string>, units: list<string>, albums: list<string>, labels: list<string>}
     */
    public function facets(int $tenantId): array
    {
        $out = ['authors' => [], 'units' => [], 'albums' => [], 'labels' => []];
        if ($tenantId < 1 || !$this->photosReady()) {
            return $out;
        }
        $alive = $this->reconHasColumn('deleted_at') ? ' AND deleted_at IS NULL' : '';
        $conf = $this->reconHasColumn('is_confidential') ? ' AND is_confidential = 0' : '';
        $base = ' FROM recon_images WHERE tenant_id = :t' . $alive . $conf . " AND (unit_name IS NULL OR (unit_name NOT LIKE 'chest:%' AND unit_name NOT LIKE 'helmet:%' AND unit_name NOT LIKE 'drone:%'))";
        try {
            foreach (['authors' => 'author_callsign', 'units' => 'unit_name', 'albums' => 'mission_id'] as $key => $column) {
                $rows = $this->db()->fetchAll('SELECT ' . $column . ' AS v, COUNT(*) AS n' . $base . ' AND ' . $column . " IS NOT NULL AND " . $column . " <> '' GROUP BY " . $column . ' ORDER BY n DESC LIMIT 60', ['t' => $tenantId]);
                $out[$key] = array_values(array_map(static fn (array $r): string => (string) $r['v'], $rows));
            }
            if ($this->tableExists('photo_library_labels')) {
                $rows = $this->db()->fetchAll('SELECT label, COUNT(*) AS n FROM photo_library_labels WHERE tenant_id = :t GROUP BY label_norm, label ORDER BY n DESC LIMIT 40', ['t' => $tenantId]);
                $out['labels'] = array_values(array_map(static fn (array $r): string => (string) $r['label'], $rows));
            }
        } catch (\Throwable) {
        }

        return $out;
    }

    /** Écrit les colonnes de recon_images que la photothèque modifie (flag SSE, confidentiel, vignette). @param array<string, mixed> $set */
    public function updatePhotoColumns(int $tenantId, int $photoId, array $set): bool
    {
        $allowed = ['is_confidential', 'is_sse', 'thumb_path', 'image_path', 'sse_case_ref'];
        $fields = [];
        $params = ['t' => $tenantId, 'id' => $photoId];
        foreach ($set as $col => $val) {
            if (!in_array($col, $allowed, true) || !$this->reconHasColumn($col)) {
                continue;
            }
            $fields[] = $col . ' = :v_' . $col;
            $params['v_' . $col] = $val;
        }
        if ($fields === []) {
            return true;
        }
        try {
            $this->db()->execute('UPDATE recon_images SET ' . implode(', ', $fields) . ' WHERE tenant_id = :t AND id = :id', $params);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    // ----------------------------------------------------------- Visages

    /** @return list<array<string, mixed>> */
    public function tags(int $tenantId, int $photoId): array
    {
        return $this->all('SELECT * FROM photo_library_tags WHERE tenant_id = :t AND photo_id = :p ORDER BY id', ['t' => $tenantId, 'p' => $photoId]);
    }

    /** @return array<string, mixed>|null */
    public function tag(int $tenantId, int $photoId, int $tagId): ?array
    {
        return $this->one('SELECT * FROM photo_library_tags WHERE tenant_id = :t AND photo_id = :p AND id = :id', ['t' => $tenantId, 'p' => $photoId, 'id' => $tagId]);
    }

    public function countTags(int $tenantId, int $photoId): int
    {
        return (int) ($this->one('SELECT COUNT(*) AS n FROM photo_library_tags WHERE tenant_id = :t AND photo_id = :p', ['t' => $tenantId, 'p' => $photoId])['n'] ?? 0);
    }

    /** @param array{x: float, y: float, w: float, h: float} $r */
    public function addTag(int $tenantId, int $photoId, array $r, string $type, ?int $targetId, int $userId): int
    {
        return $this->write('INSERT INTO photo_library_tags (tenant_id, photo_id, x, y, w, h, target_type, target_id, author_user_id, created_at)
            VALUES (:t, :p, :x, :y, :w, :h, :ty, :tid, :u, :now)', [
            't' => $tenantId, 'p' => $photoId, 'x' => $r['x'], 'y' => $r['y'], 'w' => $r['w'], 'h' => $r['h'],
            'ty' => $type, 'tid' => $targetId, 'u' => $userId, 'now' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @param array{x: float, y: float, w: float, h: float}|null $r */
    public function updateTag(int $tenantId, int $photoId, int $tagId, ?array $r, string $type, ?int $targetId): bool
    {
        $set = 'target_type = :ty, target_id = :tid, updated_at = :now';
        $params = ['ty' => $type, 'tid' => $targetId, 'now' => date('Y-m-d H:i:s'), 't' => $tenantId, 'p' => $photoId, 'id' => $tagId];
        if ($r !== null) {
            $set .= ', x = :x, y = :y, w = :w, h = :h';
            $params += ['x' => $r['x'], 'y' => $r['y'], 'w' => $r['w'], 'h' => $r['h']];
        }

        return $this->exec('UPDATE photo_library_tags SET ' . $set . ' WHERE tenant_id = :t AND photo_id = :p AND id = :id', $params) > 0;
    }

    public function deleteTag(int $tenantId, int $photoId, int $tagId): bool
    {
        return $this->exec('DELETE FROM photo_library_tags WHERE tenant_id = :t AND photo_id = :p AND id = :id', ['t' => $tenantId, 'p' => $photoId, 'id' => $tagId]) > 0;
    }

    // ----------------------------------------------------------- Masques

    /** @return list<array<string, mixed>> */
    public function masks(int $tenantId, int $photoId): array
    {
        return $this->all('SELECT * FROM photo_library_masks WHERE tenant_id = :t AND photo_id = :p ORDER BY id', ['t' => $tenantId, 'p' => $photoId]);
    }

    /** @return array<string, mixed>|null */
    public function mask(int $tenantId, int $photoId, int $maskId): ?array
    {
        return $this->one('SELECT * FROM photo_library_masks WHERE tenant_id = :t AND photo_id = :p AND id = :id', ['t' => $tenantId, 'p' => $photoId, 'id' => $maskId]);
    }

    /** @param array{x: float, y: float, w: float, h: float} $r */
    public function addMask(int $tenantId, int $photoId, array $r, string $mode, int $userId): int
    {
        return $this->write('INSERT INTO photo_library_masks (tenant_id, photo_id, x, y, w, h, mode, author_user_id, created_at)
            VALUES (:t, :p, :x, :y, :w, :h, :m, :u, :now)', [
            't' => $tenantId, 'p' => $photoId, 'x' => $r['x'], 'y' => $r['y'], 'w' => $r['w'], 'h' => $r['h'],
            'm' => $mode, 'u' => $userId, 'now' => date('Y-m-d H:i:s'),
        ]);
    }

    public function deleteMask(int $tenantId, int $photoId, int $maskId): bool
    {
        return $this->exec('DELETE FROM photo_library_masks WHERE tenant_id = :t AND photo_id = :p AND id = :id', ['t' => $tenantId, 'p' => $photoId, 'id' => $maskId]) > 0;
    }

    /** @return array<string, mixed>|null */
    public function state(int $tenantId, int $photoId): ?array
    {
        return $this->one('SELECT * FROM photo_library_state WHERE tenant_id = :t AND photo_id = :p', ['t' => $tenantId, 'p' => $photoId]);
    }

    public function saveState(int $tenantId, int $photoId, ?string $originalPath, ?string $zonesHash): void
    {
        if ($originalPath === null) {
            $this->exec('DELETE FROM photo_library_state WHERE tenant_id = :t AND photo_id = :p', ['t' => $tenantId, 'p' => $photoId]);

            return;
        }
        $this->exec(
            'INSERT INTO photo_library_state (photo_id, tenant_id, original_path, zones_hash, updated_at) VALUES (:p, :t, :o, :h, :now)
             ON DUPLICATE KEY UPDATE original_path = VALUES(original_path), zones_hash = VALUES(zones_hash), updated_at = VALUES(updated_at)',
            ['p' => $photoId, 't' => $tenantId, 'o' => $originalPath, 'h' => $zonesHash, 'now' => date('Y-m-d H:i:s')]
        );
    }

    // ----------------------------------------------------------- Commentaires

    /** @return list<array<string, mixed>> */
    public function comments(int $tenantId, int $photoId): array
    {
        return $this->all('SELECT * FROM photo_library_comments WHERE tenant_id = :t AND photo_id = :p AND deleted_at IS NULL ORDER BY id', ['t' => $tenantId, 'p' => $photoId]);
    }

    /** @return array<string, mixed>|null */
    public function comment(int $tenantId, int $photoId, int $commentId): ?array
    {
        return $this->one('SELECT * FROM photo_library_comments WHERE tenant_id = :t AND photo_id = :p AND id = :id AND deleted_at IS NULL', ['t' => $tenantId, 'p' => $photoId, 'id' => $commentId]);
    }

    /** @param array{kind: string, x?: ?float, y?: ?float, w?: ?float, h?: ?float, tag?: ?int} $anchor @param list<int> $mentions */
    public function addComment(int $tenantId, int $photoId, string $body, array $anchor, array $mentions, int $userId): int
    {
        return $this->write('INSERT INTO photo_library_comments (tenant_id, photo_id, body, anchor_kind, anchor_x, anchor_y, anchor_w, anchor_h, anchor_tag_id, mentions, author_user_id, created_at)
            VALUES (:t, :p, :b, :ak, :ax, :ay, :aw, :ah, :at, :m, :u, :now)', [
            't' => $tenantId, 'p' => $photoId, 'b' => $body, 'ak' => $anchor['kind'],
            'ax' => $anchor['x'] ?? null, 'ay' => $anchor['y'] ?? null, 'aw' => $anchor['w'] ?? null, 'ah' => $anchor['h'] ?? null,
            'at' => $anchor['tag'] ?? null, 'm' => mb_substr(implode(',', $mentions), 0, 255), 'u' => $userId, 'now' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @param list<int> $mentions */
    public function editComment(int $tenantId, int $photoId, int $commentId, string $body, array $mentions): bool
    {
        return $this->exec('UPDATE photo_library_comments SET body = :b, mentions = :m, edited_at = :now WHERE tenant_id = :t AND photo_id = :p AND id = :id AND deleted_at IS NULL', [
            'b' => $body, 'm' => mb_substr(implode(',', $mentions), 0, 255), 'now' => date('Y-m-d H:i:s'), 't' => $tenantId, 'p' => $photoId, 'id' => $commentId,
        ]) > 0;
    }

    public function deleteComment(int $tenantId, int $photoId, int $commentId, int $userId): bool
    {
        return $this->exec('UPDATE photo_library_comments SET deleted_at = :now, deleted_by = :u WHERE tenant_id = :t AND photo_id = :p AND id = :id AND deleted_at IS NULL', [
            'now' => date('Y-m-d H:i:s'), 'u' => $userId, 't' => $tenantId, 'p' => $photoId, 'id' => $commentId,
        ]) > 0;
    }

    // ----------------------------------------------------------- Étiquettes libres

    /** @return list<string> */
    public function labels(int $tenantId, int $photoId): array
    {
        return array_values(array_map(static fn (array $r): string => (string) $r['label'], $this->all('SELECT label FROM photo_library_labels WHERE tenant_id = :t AND photo_id = :p ORDER BY label', ['t' => $tenantId, 'p' => $photoId])));
    }

    public function countLabels(int $tenantId, int $photoId): int
    {
        return (int) ($this->one('SELECT COUNT(*) AS n FROM photo_library_labels WHERE tenant_id = :t AND photo_id = :p', ['t' => $tenantId, 'p' => $photoId])['n'] ?? 0);
    }

    public function addLabel(int $tenantId, int $photoId, string $label, string $norm, int $userId): bool
    {
        return $this->exec('INSERT IGNORE INTO photo_library_labels (tenant_id, photo_id, label, label_norm, author_user_id, created_at) VALUES (:t, :p, :l, :n, :u, :now)', [
            't' => $tenantId, 'p' => $photoId, 'l' => $label, 'n' => $norm, 'u' => $userId, 'now' => date('Y-m-d H:i:s'),
        ]) > 0;
    }

    public function removeLabel(int $tenantId, int $photoId, string $norm): bool
    {
        return $this->exec('DELETE FROM photo_library_labels WHERE tenant_id = :t AND photo_id = :p AND label_norm = :n', ['t' => $tenantId, 'p' => $photoId, 'n' => $norm]) > 0;
    }

    // ----------------------------------------------------------- Versements SSE

    /** @return array<string, mixed>|null */
    public function activeTransfer(int $tenantId, int $photoId): ?array
    {
        return $this->one('SELECT * FROM photo_library_sse WHERE tenant_id = :t AND photo_id = :p AND withdrawn_at IS NULL ORDER BY id DESC LIMIT 1', ['t' => $tenantId, 'p' => $photoId]);
    }

    public function addTransfer(int $tenantId, int $photoId, ?int $ssePhotoId, ?int $identityId, int $userId, bool $prevConfidential, bool $prevSse): int
    {
        return $this->write('INSERT INTO photo_library_sse (tenant_id, photo_id, sse_photo_id, identity_id, actor_user_id, prev_confidential, prev_sse, created_at)
            VALUES (:t, :p, :s, :i, :u, :pc, :ps, :now)', [
            't' => $tenantId, 'p' => $photoId, 's' => $ssePhotoId, 'i' => $identityId, 'u' => $userId,
            'pc' => $prevConfidential ? 1 : 0, 'ps' => $prevSse ? 1 : 0, 'now' => date('Y-m-d H:i:s'),
        ]);
    }

    public function withdrawTransfer(int $tenantId, int $transferId, int $userId): bool
    {
        return $this->exec('UPDATE photo_library_sse SET withdrawn_at = :now, withdrawn_by = :u WHERE tenant_id = :t AND id = :id AND withdrawn_at IS NULL', [
            'now' => date('Y-m-d H:i:s'), 'u' => $userId, 't' => $tenantId, 'id' => $transferId,
        ]) > 0;
    }

    // ----------------------------------------------------------- Membres

    /**
     * @param list<int> $ids
     * @return array<int, array{id: int, name: string, callsign: string}>
     */
    public function members(int $tenantId, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0)));
        if ($ids === [] || $tenantId < 1) {
            return [];
        }
        $params = ['t' => $tenantId];
        $in = [];
        foreach ($ids as $i => $id) {
            $in[] = ':i' . $i;
            $params['i' . $i] = $id;
        }
        $out = [];
        foreach ($this->all('SELECT u.id, u.display_name, u.callsign FROM users u WHERE ' . $this->memberPredicate($tenantId) . ' AND u.id IN (' . implode(',', $in) . ')', $params) as $r) {
            $out[(int) $r['id']] = ['id' => (int) $r['id'], 'name' => self::memberName($r), 'callsign' => trim((string) ($r['callsign'] ?? ''))];
        }

        return $out;
    }

    /** @return list<array{id: int, name: string, callsign: string}> */
    public function searchMembers(int $tenantId, string $q, int $limit = 8): array
    {
        $q = trim($q);
        if ($tenantId < 1 || $q === '') {
            return [];
        }
        $like = '%' . self::likeEscape($q) . '%';
        $rows = $this->all(
            'SELECT u.id, u.display_name, u.callsign FROM users u WHERE ' . $this->memberPredicate($tenantId) . ' AND (u.display_name LIKE :a OR u.callsign LIKE :b) ORDER BY u.callsign, u.display_name LIMIT ' . max(1, min(20, $limit)),
            ['a' => $like, 'b' => $like]
        );

        return array_values(array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'name' => self::memberName($r), 'callsign' => trim((string) ($r['callsign'] ?? ''))], $rows));
    }

    /** Compte de l'auteur d'une photo d'après son indicatif : seulement si un seul membre le porte (sinon null, jamais de devinette). */
    public function uniqueMemberByCallsign(int $tenantId, string $callsign): ?int
    {
        $callsign = trim($callsign);
        if ($tenantId < 1 || $callsign === '' || mb_strtolower($callsign) === 'unknown') {
            return null;
        }
        $rows = $this->all('SELECT u.id FROM users u WHERE ' . $this->memberPredicate($tenantId) . ' AND (LOWER(u.callsign) = :a OR LOWER(u.display_name) = :b) LIMIT 2', ['a' => mb_strtolower($callsign), 'b' => mb_strtolower($callsign)]);

        return count($rows) === 1 ? (int) $rows[0]['id'] : null;
    }

    /** Membres cités par @nom : un nom qui désigne un seul membre de la communauté. @param list<string> $names @return array<int, string> id => nom */
    public function membersByHandle(int $tenantId, array $names): array
    {
        $out = [];
        foreach (array_slice(array_values(array_unique($names)), 0, 10) as $name) {
            $id = $this->uniqueMemberByCallsign($tenantId, $name);
            if ($id !== null) {
                $out[$id] = $name;
            }
        }

        return $out;
    }

    private function memberPredicate(int $tenantId): string
    {
        $t = max(0, $tenantId);

        return '(u.tenant_id = ' . $t . ' OR EXISTS (SELECT 1 FROM user_community_memberships ucm WHERE ucm.user_id = u.id AND ucm.tenant_id = ' . $t . " AND ucm.status = 'active'))";
    }

    /** @param array<string, mixed> $r */
    private static function memberName(array $r): string
    {
        $c = trim((string) ($r['callsign'] ?? ''));
        $n = trim((string) ($r['display_name'] ?? ''));

        return $c !== '' ? $c : ($n !== '' ? $n : 'Membre');
    }

    // ----------------------------------------------------------- Outils

    public static function likeEscape(string $s): string
    {
        return addcslashes($s, '%_\\');
    }

    private static function day(string $s): ?string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) === 1 && strtotime($s) !== false ? $s : null;
    }

    /** @param array<string, mixed> $params @return list<array<string, mixed>> */
    private function all(string $sql, array $params): array
    {
        try {
            return $this->db()->fetchAll($sql, $params);
        } catch (\Throwable) {
            return [];
        }
    }

    /** @param array<string, mixed> $params @return array<string, mixed>|null */
    private function one(string $sql, array $params): ?array
    {
        try {
            return $this->db()->fetchOne($sql, $params);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $params */
    private function exec(string $sql, array $params): int
    {
        try {
            return $this->db()->execute($sql, $params);
        } catch (\Throwable $e) {
            error_log('Photothèque : écriture refusée : ' . $e->getMessage());

            return 0;
        }
    }

    /** @param array<string, mixed> $params */
    private function write(string $sql, array $params): int
    {
        try {
            return $this->db()->insert($sql, $params);
        } catch (\Throwable $e) {
            error_log('Photothèque : écriture refusée : ' . $e->getMessage());

            return 0;
        }
    }
}
