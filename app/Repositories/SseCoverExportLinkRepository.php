<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SilentSchemaMigration;

/**
 * Lien d'une couverture générée (sse_cover_exports) vers l'identité du membre sur laquelle elle est bâtie et les papiers
 * de cette identité qu'elle emporte (table sse_cover_export_links). Toute requête est bornée à la communauté et au membre.
 *
 * Lectures et écritures gardées : table absente ou illisible = aucune couverture rattachée, jamais une erreur.
 */
class SseCoverExportLinkRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        self::ensureSchema();
    }

    /** Table créée au premier usage (même migration que database/steps/4440, sans le rétro-remplissage). */
    public static function ensureSchema(): void
    {
        static $done = false;
        if (!$done) {
            $done = true;
            SilentSchemaMigration::run(base_path('bootstrap/sse_cover_export_links_migration.php'));
        }
    }

    /**
     * Liens des couvertures d'un membre, par identifiant de couverture.
     *
     * @return array<int, array{identity_id: int, document_ids: list<int>}>
     */
    public function forUser(int $tenantId, int $userId): array
    {
        try {
            $out = [];
            foreach ($this->db->fetchAll(
                'SELECT export_id, identity_id, document_ids FROM sse_cover_export_links WHERE tenant_id = :t AND user_id = :u',
                ['t' => $tenantId, 'u' => $userId]
            ) as $r) {
                $ids = json_decode((string) ($r['document_ids'] ?? ''), true);
                $out[(int) $r['export_id']] = [
                    'identity_id' => (int) $r['identity_id'],
                    'document_ids' => array_values(array_unique(array_filter(array_map('intval', is_array($ids) ? $ids : []), static fn (int $i): bool => $i > 0))),
                ];
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Pose ou remplace le lien d'une couverture du membre. Faux : lien non enregistré (table absente).
     *
     * @param list<int> $documentIds
     */
    public function save(int $tenantId, int $userId, int $exportId, int $identityId, array $documentIds, string $now): bool
    {
        $json = (string) json_encode(array_values(array_unique(array_map('intval', $documentIds))));
        try {
            $key = ['t' => $tenantId, 'e' => $exportId];
            if ($this->db->fetchOne('SELECT id FROM sse_cover_export_links WHERE tenant_id = :t AND export_id = :e', $key) === null) {
                $this->db->insert(
                    'INSERT INTO sse_cover_export_links (tenant_id, export_id, user_id, identity_id, document_ids, created_at, updated_at)
                     VALUES (:t, :e, :u, :i, :d, :now, :now2)',
                    $key + ['u' => $userId, 'i' => $identityId, 'd' => $json, 'now' => $now, 'now2' => $now]
                );

                return true;
            }
            $this->db->execute(
                'UPDATE sse_cover_export_links SET identity_id = :i, document_ids = :d, updated_at = :now WHERE tenant_id = :t AND export_id = :e AND user_id = :u',
                $key + ['u' => $userId, 'i' => $identityId, 'd' => $json, 'now' => $now]
            );

            return true;
        } catch (\Throwable $e) {
            error_log('Couvertures : lien vers l’identité non enregistré : ' . $e->getMessage());

            return false;
        }
    }
}
