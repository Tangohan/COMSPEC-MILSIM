<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Pièces jointes d'un avis de recherche (table sse_wanted_attachments) : une ligne par image rangée dans l'espace privé
 * (SseImageStorage, dossier « sse-wanted »). Toute requête est bornée à la communauté. Sans la table : aucune pièce.
 */
final class SseWantedAttachmentRepository
{
    public const MAX_PER_NOTICE = 6;

    private Database $db;

    /** @var \WeakMap<PDO, array{ok: bool, at: int}>|null */
    private static ?\WeakMap $readyByConnection = null;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public static function ready(): bool
    {
        try {
            $pdo = Database::getPdo();
            self::$readyByConnection ??= new \WeakMap();
            $known = self::$readyByConnection[$pdo] ?? null;
            if (is_array($known) && ($known['ok'] || time() - $known['at'] < 20)) {
                return $known['ok'];
            }
            try {
                $pdo->query('SELECT 1 FROM sse_wanted_attachments WHERE 1 = 0');
                self::$readyByConnection[$pdo] = ['ok' => true, 'at' => time()];

                return true;
            } catch (\Throwable) {
                self::$readyByConnection[$pdo] = ['ok' => false, 'at' => time()];

                return false;
            }
        } catch (\Throwable) {
            return false;
        }
    }

    public static function forgetReady(): void
    {
        self::$readyByConnection = null;
    }

    /** @return list<array<string, mixed>> la principale d'abord, puis par ordre d'ajout */
    public function forNotice(int $tenantId, int $noticeId): array
    {
        if ($tenantId < 1 || $noticeId < 1 || !self::ready()) {
            return [];
        }

        return array_map([self::class, 'hydrate'], $this->db->fetchAll(
            'SELECT * FROM sse_wanted_attachments WHERE tenant_id = :t AND notice_id = :n ORDER BY is_main DESC, id ASC',
            ['t' => $tenantId, 'n' => $noticeId]
        ));
    }

    /**
     * Nombre de pièces de plusieurs avis.
     *
     * @param list<int> $noticeIds
     * @return array<int, int>
     */
    public function counts(int $tenantId, array $noticeIds): array
    {
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $noticeIds), static fn (int $i): bool => $i > 0))), 0, 500);
        if ($tenantId < 1 || $ids === [] || !self::ready()) {
            return [];
        }
        $params = ['t' => $tenantId];
        $marks = [];
        foreach ($ids as $k => $id) {
            $marks[] = ':n' . $k;
            $params['n' . $k] = $id;
        }
        $out = [];
        foreach ($this->db->fetchAll('SELECT notice_id, COUNT(*) AS n FROM sse_wanted_attachments WHERE tenant_id = :t AND notice_id IN (' . implode(', ', $marks) . ') GROUP BY notice_id', $params) as $r) {
            $out[(int) $r['notice_id']] = (int) $r['n'];
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $id): ?array
    {
        if ($tenantId < 1 || $id < 1 || !self::ready()) {
            return null;
        }
        $row = $this->db->fetchOne('SELECT * FROM sse_wanted_attachments WHERE id = :id AND tenant_id = :t', ['id' => $id, 't' => $tenantId]);

        return $row === null ? null : self::hydrate($row);
    }

    /** @param array<string, mixed> $row */
    public function add(int $tenantId, int $noticeId, int $identityId, array $row, ?int $userId): int
    {
        $isFirst = $this->forNotice($tenantId, $noticeId) === [];
        $main = $isFirst || !empty($row['is_main']);
        if ($main && !$isFirst) {
            $this->db->execute('UPDATE sse_wanted_attachments SET is_main = 0 WHERE tenant_id = :t AND notice_id = :n', ['t' => $tenantId, 'n' => $noticeId]);
        }

        return $this->db->insert(
            'INSERT INTO sse_wanted_attachments (tenant_id, notice_id, identity_id, kind, caption, path, thumb_path, mime, width, height, bytes, sha1, is_main,
                source_photo_id, created_by_user_id, created_at)
             VALUES (:t, :n, :i, :kind, :cap, :path, :thumb, :mime, :w, :h, :b, :sha, :main, :src, :u, :now)',
            [
                't' => $tenantId, 'n' => $noticeId, 'i' => $identityId, 'kind' => (string) ($row['kind'] ?? 'other'), 'cap' => $row['caption'] ?? null,
                'path' => (string) $row['path'], 'thumb' => $row['thumb_path'] ?? null, 'mime' => (string) ($row['mime'] ?? 'image/jpeg'),
                'w' => $row['width'] ?? null, 'h' => $row['height'] ?? null, 'b' => $row['bytes'] ?? null, 'sha' => $row['sha1'] ?? null,
                'main' => $main ? 1 : 0, 'src' => $row['source_photo_id'] ?? null, 'u' => $userId, 'now' => gmdate('Y-m-d H:i:s'),
            ]
        );
    }

    /** Supprime la ligne ; la plus ancienne restante devient la principale si on retire la principale. */
    public function delete(int $tenantId, int $id): bool
    {
        $att = $this->find($tenantId, $id);
        if ($att === null) {
            return false;
        }
        $this->db->execute('DELETE FROM sse_wanted_attachments WHERE id = :id AND tenant_id = :t', ['id' => $id, 't' => $tenantId]);
        if ($att['is_main']) {
            $left = $this->forNotice($tenantId, (int) $att['notice_id']);
            if ($left !== []) {
                $this->setMain($tenantId, (int) $left[0]['id']);
            }
        }

        return true;
    }

    public function setMain(int $tenantId, int $id): bool
    {
        $att = $this->find($tenantId, $id);
        if ($att === null) {
            return false;
        }
        $this->db->execute('UPDATE sse_wanted_attachments SET is_main = 0 WHERE tenant_id = :t AND notice_id = :n', ['t' => $tenantId, 'n' => (int) $att['notice_id']]);
        $this->db->execute('UPDATE sse_wanted_attachments SET is_main = 1 WHERE id = :id AND tenant_id = :t', ['id' => $id, 't' => $tenantId]);

        return true;
    }

    /**
     * @param array<string, mixed> $r
     * @return array<string, mixed>
     */
    public static function hydrate(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'tenant_id' => (int) $r['tenant_id'],
            'notice_id' => (int) $r['notice_id'],
            'identity_id' => (int) $r['identity_id'],
            'kind' => (string) ($r['kind'] ?? 'other'),
            'caption' => ($r['caption'] ?? null) !== null && $r['caption'] !== '' ? (string) $r['caption'] : null,
            'path' => (string) $r['path'],
            'thumb_path' => ($r['thumb_path'] ?? null) !== null && $r['thumb_path'] !== '' ? (string) $r['thumb_path'] : null,
            'mime' => (string) ($r['mime'] ?? 'image/jpeg'),
            'width' => ($r['width'] ?? null) !== null ? (int) $r['width'] : null,
            'height' => ($r['height'] ?? null) !== null ? (int) $r['height'] : null,
            'bytes' => ($r['bytes'] ?? null) !== null ? (int) $r['bytes'] : null,
            'sha1' => ($r['sha1'] ?? null) !== null && $r['sha1'] !== '' ? (string) $r['sha1'] : null,
            'is_main' => (int) ($r['is_main'] ?? 0) === 1,
            'source_photo_id' => ($r['source_photo_id'] ?? null) !== null ? (int) $r['source_photo_id'] : null,
            'created_by_user_id' => ($r['created_by_user_id'] ?? null) !== null ? (int) $r['created_by_user_id'] : null,
            'created_at' => (string) ($r['created_at'] ?? ''),
        ];
    }
}
