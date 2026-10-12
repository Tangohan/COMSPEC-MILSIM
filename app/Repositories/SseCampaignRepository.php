<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SilentSchemaMigration;
use PDO;

/**
 * Campagnes SSE d'une communauté (sse_campaigns) et leur journal (sse_campaign_log).
 */
final class SseCampaignRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
        static $done = false;
        if (!$done) {
            SilentSchemaMigration::run(base_path('bootstrap/sse_campaigns_migration.php'), $this->pdo);
            $done = true;
        }
    }

    /** @return list<array<string, mixed>> */
    public function listForTenant(int $tenantId, bool $withArchived = true): array
    {
        $sql = 'SELECT id, tenant_id, campaign_key, name, type, status, seed, payload_hash, missions, persons_count, orgs_count,
                       pieces_count, game_updated, origin, shared, share_scope, share_note, source_campaign_id, source_tenant_id,
                       source_hash, created_at, updated_at
                FROM sse_campaigns WHERE tenant_id = ?';
        if (!$withArchived) {
            $sql .= " AND status = 'active'";
        }
        $st = $this->pdo->prepare($sql . ' ORDER BY status ASC, COALESCE(updated_at, created_at) DESC');
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM sse_campaigns WHERE tenant_id = ? AND id = ? LIMIT 1');
        $st->execute([$tenantId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findByKey(int $tenantId, string $key): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM sse_campaigns WHERE tenant_id = ? AND campaign_key = ? LIMIT 1');
        $st->execute([$tenantId, $key]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** Campagne partagée par une communauté, lue depuis une autre. @return array<string, mixed>|null */
    public function findShared(int $id): ?array
    {
        $st = $this->pdo->prepare("SELECT * FROM sse_campaigns WHERE id = ? AND shared = 1 AND status = 'active' LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Catalogue : campagnes que les autres communautés ont choisi de partager.
     *
     * @return list<array<string, mixed>>
     */
    public function sharedCatalog(int $exceptTenantId): array
    {
        $st = $this->pdo->prepare(
            "SELECT c.id, c.tenant_id, c.campaign_key, c.name, c.type, c.seed, c.payload_hash, c.missions, c.persons_count,
                    c.orgs_count, c.share_scope, c.share_note, c.updated_at, c.created_at,
                    t.name AS tenant_name, t.slug AS tenant_slug, t.community_code AS tenant_code
             FROM sse_campaigns c
             LEFT JOIN tenants t ON t.id = c.tenant_id
             WHERE c.shared = 1 AND c.status = 'active' AND c.tenant_id <> ?
             ORDER BY COALESCE(c.updated_at, c.created_at) DESC
             LIMIT 200"
        );
        $st->execute([$exceptTenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Autres campagnes qui utilisent la même graine sans être une copie l'une de l'autre :
     * deux mondes différents produiraient la même biométrie pour des personnages différents.
     *
     * @return list<array<string, mixed>>
     */
    public function seedCollisions(array $campaign): array
    {
        $seed = (string) ($campaign['seed'] ?? '');
        if ($seed === '') {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT c.id, c.tenant_id, c.campaign_key, c.name, c.source_campaign_id, t.name AS tenant_name
             FROM sse_campaigns c LEFT JOIN tenants t ON t.id = c.tenant_id
             WHERE c.seed = ? AND c.id <> ?'
        );
        $st->execute([$seed, (int) $campaign['id']]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $related = (int) ($row['source_campaign_id'] ?? 0) === (int) $campaign['id']
                || (int) ($campaign['source_campaign_id'] ?? 0) === (int) $row['id']
                || ((int) ($row['source_campaign_id'] ?? 0) > 0 && (int) ($row['source_campaign_id'] ?? 0) === (int) ($campaign['source_campaign_id'] ?? 0))
                // Une copie faite pour un drill dans la même communauté garde volontairement la graine.
                || (int) $row['tenant_id'] === (int) $campaign['tenant_id'];
            if (!$related) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $data */
    public function insert(int $tenantId, array $data): int
    {
        $cols = array_keys($data);
        $sql = 'INSERT INTO sse_campaigns (tenant_id, ' . implode(', ', $cols) . ', updated_at) VALUES (?, '
            . implode(', ', array_fill(0, count($cols), '?')) . ', NOW())';
        $st = $this->pdo->prepare($sql);
        $st->execute(array_merge([$tenantId], array_values($data)));

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $tenantId, int $id, array $data): void
    {
        if ($data === []) {
            return;
        }
        $set = implode(', ', array_map(static fn (string $c): string => $c . ' = ?', array_keys($data)));
        $st = $this->pdo->prepare('UPDATE sse_campaigns SET ' . $set . ', updated_at = NOW() WHERE tenant_id = ? AND id = ?');
        $st->execute(array_merge(array_values($data), [$tenantId, $id]));
    }

    public function delete(int $tenantId, int $id): void
    {
        $this->pdo->prepare('DELETE FROM sse_campaign_log WHERE tenant_id = ? AND campaign_id = ?')->execute([$tenantId, $id]);
        $this->pdo->prepare('DELETE FROM sse_campaigns WHERE tenant_id = ? AND id = ?')->execute([$tenantId, $id]);
    }

    public function keyExists(int $tenantId, string $key): bool
    {
        $st = $this->pdo->prepare('SELECT 1 FROM sse_campaigns WHERE tenant_id = ? AND campaign_key = ? LIMIT 1');
        $st->execute([$tenantId, $key]);

        return (bool) $st->fetchColumn();
    }

    public function log(int $tenantId, int $campaignId, string $actor, string $action, string $detail = ''): void
    {
        $st = $this->pdo->prepare('INSERT INTO sse_campaign_log (campaign_id, tenant_id, actor, action, detail) VALUES (?, ?, ?, ?, ?)');
        $st->execute([$campaignId, $tenantId, mb_substr($actor, 0, 120), mb_substr($action, 0, 40), mb_substr($detail, 0, 255)]);
    }

    /** @return list<array<string, mixed>> */
    public function logFor(int $tenantId, int $campaignId, int $limit = 80): array
    {
        $st = $this->pdo->prepare('SELECT actor, action, detail, created_at FROM sse_campaign_log WHERE tenant_id = ? AND campaign_id = ? ORDER BY id DESC LIMIT ' . max(1, min(500, $limit)));
        $st->execute([$tenantId, $campaignId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Nombre de communautés qui ont copié une campagne partagée. */
    public function copiesCount(int $campaignId): int
    {
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM sse_campaigns WHERE source_campaign_id = ?');
        $st->execute([$campaignId]);

        return (int) $st->fetchColumn();
    }
}
