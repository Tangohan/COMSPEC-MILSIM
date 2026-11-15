<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Liaisons entre unités (jnet_unit_links) et liens entre contenus (jnet_links) du JNET. Tolère l'absence des tables
 * (étape jnet_links non passée) : lectures vides, écritures refusées par l'appelant. Toutes les requêtes sont bornées par
 * communauté ; aucune ne décide d'un droit : c'est JnetLinkService qui contrôle chaque lecteur.
 */
class JnetLinksRepository
{
    private ?bool $unitReady = null;
    private ?bool $linksReady = null;

    public function __construct(private ?PDO $pdo = null)
    {
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    public function unitLinksReady(): bool
    {
        return $this->unitReady ??= $this->tableExists('jnet_unit_links');
    }

    public function linksReady(): bool
    {
        return $this->linksReady ??= $this->tableExists('jnet_links');
    }

    private function tableExists(string $table): bool
    {
        try {
            $this->pdo()->query('SELECT 1 FROM ' . $table . ' LIMIT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    // ——— Liaisons d'unités ———

    /**
     * Liaisons où l'espace est l'une des deux unités, les plus récentes d'abord (terminées comprises).
     *
     * @return list<array<string, mixed>>
     */
    public function unitLinksOf(int $tenantId, int $spaceId): array
    {
        if (!$this->unitLinksReady()) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT * FROM jnet_unit_links WHERE tenant_id = ? AND (unit_id = ? OR partner_unit_id = ?) ORDER BY id DESC LIMIT 100'
        );
        $st->execute([$tenantId, $spaceId, $spaceId]);

        return array_map([self::class, 'unitRow'], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /**
     * Toutes les liaisons non terminées à la main de la communauté (pour le registre des unités).
     *
     * @return list<array<string, mixed>>
     */
    public function openUnitLinks(int $tenantId): array
    {
        if (!$this->unitLinksReady()) {
            return [];
        }
        $st = $this->pdo()->prepare('SELECT * FROM jnet_unit_links WHERE tenant_id = ? AND ended_at IS NULL ORDER BY id DESC LIMIT 500');
        $st->execute([$tenantId]);

        return array_map([self::class, 'unitRow'], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /** @return array<string, mixed>|null */
    public function unitLink(int $tenantId, int $linkId): ?array
    {
        if (!$this->unitLinksReady()) {
            return null;
        }
        $st = $this->pdo()->prepare('SELECT * FROM jnet_unit_links WHERE tenant_id = ? AND id = ?');
        $st->execute([$tenantId, $linkId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::unitRow($row) : null;
    }

    /** @param array{kind: string, direction: string, starts_on: ?string, ends_on: ?string, note: string, grant_read: bool} $v */
    public function addUnitLink(int $tenantId, int $unitId, int $partnerId, array $v, int $userId): int
    {
        $this->pdo()->prepare(
            'INSERT INTO jnet_unit_links (tenant_id, unit_id, partner_unit_id, kind, direction, starts_on, ends_on, note, grant_read, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$tenantId, $unitId, $partnerId, $v['kind'], $v['direction'], $v['starts_on'], $v['ends_on'], $v['note'], $v['grant_read'] ? 1 : 0, $userId]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function endUnitLink(int $tenantId, int $linkId, int $userId): void
    {
        $this->pdo()->prepare('UPDATE jnet_unit_links SET ended_at = NOW(), ended_by = ? WHERE tenant_id = ? AND id = ? AND ended_at IS NULL')
            ->execute([$userId, $tenantId, $linkId]);
    }

    /** @param array<string, mixed> $r @return array<string, mixed> */
    private static function unitRow(array $r): array
    {
        $r['id'] = (int) $r['id'];
        $r['unit_id'] = (int) $r['unit_id'];
        $r['partner_unit_id'] = (int) $r['partner_unit_id'];
        $r['grant_read'] = (int) ($r['grant_read'] ?? 0) === 1;
        $r['created_by'] = (int) ($r['created_by'] ?? 0);
        foreach (['starts_on', 'ends_on', 'ended_at'] as $k) {
            $r[$k] = isset($r[$k]) && trim((string) $r[$k]) !== '' ? (string) $r[$k] : null;
        }

        return $r;
    }

    // ——— Liens entre contenus ———

    /**
     * Liens où le contenu est la source ou la cible.
     *
     * @return list<array<string, mixed>>
     */
    public function linksOf(int $tenantId, string $type, string $id, int $limit = 100): array
    {
        if (!$this->linksReady()) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT * FROM jnet_links WHERE tenant_id = ? AND ((src_type = ? AND src_id = ?) OR (dst_type = ? AND dst_id = ?))
             ORDER BY id DESC LIMIT ' . max(1, min(300, $limit))
        );
        $st->execute([$tenantId, $type, $id, $type, $id]);

        return array_map([self::class, 'linkRow'], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /**
     * Liens de plusieurs contenus d'un même type (les échanges d'un écran), en une requête.
     *
     * @param list<string> $ids
     * @return list<array<string, mixed>>
     */
    public function linksOfMany(int $tenantId, string $type, array $ids): array
    {
        $ids = array_values(array_unique(array_map('strval', $ids)));
        if ($ids === [] || !$this->linksReady()) {
            return [];
        }
        $ids = array_slice($ids, 0, 200);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->pdo()->prepare(
            "SELECT * FROM jnet_links WHERE tenant_id = ? AND ((src_type = ? AND src_id IN ($in)) OR (dst_type = ? AND dst_id IN ($in))) ORDER BY id DESC LIMIT 600"
        );
        $st->execute(array_merge([$tenantId, $type], $ids, [$type], $ids));

        return array_map([self::class, 'linkRow'], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /** @return array<string, mixed>|null */
    public function link(int $tenantId, int $linkId): ?array
    {
        if (!$this->linksReady()) {
            return null;
        }
        $st = $this->pdo()->prepare('SELECT * FROM jnet_links WHERE tenant_id = ? AND id = ?');
        $st->execute([$tenantId, $linkId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::linkRow($row) : null;
    }

    /** Les deux contenus sont-ils déjà reliés, dans un sens ou dans l'autre ? */
    public function pairExists(int $tenantId, string $aType, string $aId, string $bType, string $bId): bool
    {
        if (!$this->linksReady()) {
            return false;
        }
        $st = $this->pdo()->prepare(
            'SELECT 1 FROM jnet_links WHERE tenant_id = ? AND ((src_type = ? AND src_id = ? AND dst_type = ? AND dst_id = ?)
                OR (src_type = ? AND src_id = ? AND dst_type = ? AND dst_id = ?)) LIMIT 1'
        );
        $st->execute([$tenantId, $aType, $aId, $bType, $bId, $bType, $bId, $aType, $aId]);

        return $st->fetchColumn() !== false;
    }

    public function countOf(int $tenantId, string $type, string $id): int
    {
        if (!$this->linksReady()) {
            return 0;
        }
        $st = $this->pdo()->prepare('SELECT COUNT(*) FROM jnet_links WHERE tenant_id = ? AND ((src_type = ? AND src_id = ?) OR (dst_type = ? AND dst_id = ?))');
        $st->execute([$tenantId, $type, $id, $type, $id]);

        return (int) $st->fetchColumn();
    }

    public function addLink(int $tenantId, string $srcType, string $srcId, string $dstType, string $dstId, int $spaceId, int $userId): int
    {
        $this->pdo()->prepare(
            'INSERT INTO jnet_links (tenant_id, src_type, src_id, dst_type, dst_id, space_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$tenantId, $srcType, $srcId, $dstType, $dstId, $spaceId, $userId]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function removeLink(int $tenantId, int $linkId): void
    {
        $this->pdo()->prepare('DELETE FROM jnet_links WHERE tenant_id = ? AND id = ?')->execute([$tenantId, $linkId]);
    }

    /** @param array<string, mixed> $r @return array<string, mixed> */
    private static function linkRow(array $r): array
    {
        $r['id'] = (int) $r['id'];
        $r['space_id'] = (int) $r['space_id'];
        $r['created_by'] = (int) $r['created_by'];
        $r['src_id'] = (string) $r['src_id'];
        $r['dst_id'] = (string) $r['dst_id'];

        return $r;
    }
}
