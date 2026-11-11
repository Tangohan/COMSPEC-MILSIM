<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Couvertures générées sur Athena puis téléchargées pour le jeu (table sse_cover_exports). Toute requête est bornée à la
 * communauté. Ces lignes restent à part du registre des identités.
 */
class SseCoverExportRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /** @param array<string, mixed> $row */
    public function insert(int $tenantId, int $userId, ?int $by, array $row, string $now): int
    {
        return $this->db->insert(
            'INSERT INTO sse_cover_exports (tenant_id, user_id, created_by_user_id, ref, seed, country_code, cover_name, doc_number, content_sha, payload_json, created_at)
             VALUES (:t, :u, :by, :ref, :seed, :cc, :name, :num, :sha, :json, :now)',
            [
                't' => $tenantId, 'u' => $userId, 'by' => $by, 'ref' => (string) $row['ref'], 'seed' => (int) $row['seed'],
                'cc' => (string) $row['country_code'], 'name' => mb_substr((string) $row['cover_name'], 0, 190),
                'num' => mb_substr((string) $row['doc_number'], 0, 60), 'sha' => (string) $row['content_sha'],
                'json' => (string) $row['payload_json'], 'now' => $now,
            ]
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM sse_cover_exports WHERE id = :id AND tenant_id = :t', ['id' => $id, 't' => $tenantId]);
    }

    /**
     * Historique d'un membre, le plus récent d'abord, sans le contenu du fichier.
     *
     * @return list<array<string, mixed>>
     */
    public function listForUser(int $tenantId, int $userId, int $limit = 20): array
    {
        return array_values($this->db->fetchAll(
            'SELECT id, ref, seed, country_code, cover_name, doc_number, created_by_user_id, created_at, downloaded_at
               FROM sse_cover_exports WHERE tenant_id = :t AND user_id = :u ORDER BY id DESC LIMIT ' . max(1, min(100, $limit)),
            ['t' => $tenantId, 'u' => $userId]
        ));
    }

    /**
     * Couvertures d'un membre avec le texte du fichier (synchronisation depuis le téléphone), la plus récente d'abord.
     *
     * @return list<array<string, mixed>>
     */
    public function listFilesForUser(int $tenantId, int $userId, int $limit = 20): array
    {
        return array_values($this->db->fetchAll(
            'SELECT id, ref, country_code, cover_name, content_sha, payload_json, created_by_user_id, created_at, downloaded_at
               FROM sse_cover_exports WHERE tenant_id = :t AND user_id = :u ORDER BY id DESC LIMIT ' . max(1, min(50, $limit)),
            ['t' => $tenantId, 'u' => $userId]
        ));
    }

    /**
     * Couverture d'une référence, seulement si elle est de ce membre et de cette communauté.
     *
     * @return array<string, mixed>|null
     */
    public function findForUserByRef(int $tenantId, int $userId, string $ref): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM sse_cover_exports WHERE tenant_id = :t AND user_id = :u AND ref = :r ORDER BY id DESC LIMIT 1',
            ['t' => $tenantId, 'u' => $userId, 'r' => $ref]
        );
    }

    public function countSince(int $tenantId, int $userId, string $since): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS n FROM sse_cover_exports WHERE tenant_id = :t AND user_id = :u AND created_at >= :s',
            ['t' => $tenantId, 'u' => $userId, 's' => $since]
        );

        return (int) ($row['n'] ?? 0);
    }

    /**
     * Remplace le fichier d'une couverture du membre (légende modifiée) : même référence, nouveau contenu signé.
     *
     * @param array{cover_name: string, doc_number: string, content_sha: string, payload_json: string} $row
     */
    public function updatePayload(int $tenantId, int $userId, int $id, array $row): bool
    {
        return $this->db->execute(
            'UPDATE sse_cover_exports SET cover_name = :name, doc_number = :num, content_sha = :sha, payload_json = :json WHERE id = :id AND tenant_id = :t AND user_id = :u',
            [
                'name' => mb_substr((string) $row['cover_name'], 0, 190), 'num' => mb_substr((string) $row['doc_number'], 0, 60),
                'sha' => (string) $row['content_sha'], 'json' => (string) $row['payload_json'], 'id' => $id, 't' => $tenantId, 'u' => $userId,
            ]
        ) > 0;
    }

    public function markDownloaded(int $tenantId, int $id, string $now): void
    {
        $this->db->execute('UPDATE sse_cover_exports SET downloaded_at = :now WHERE id = :id AND tenant_id = :t', ['now' => $now, 'id' => $id, 't' => $tenantId]);
    }

    /**
     * Noms et numéros de papier déjà pris dans la communauté (registre des identités, papiers, couvertures générées).
     *
     * @return array{names: array<string, true>, numbers: array<string, true>}
     */
    public function taken(int $tenantId): array
    {
        $names = [];
        $numbers = [];
        $run = function (string $sql, callable $each) use ($tenantId): void {
            try {
                foreach ($this->db->fetchAll($sql, ['t' => $tenantId]) as $r) {
                    $each($r);
                }
            } catch (\Throwable $e) {
                error_log('Couvertures : lecture des noms pris impossible : ' . $e->getMessage());
            }
        };
        $run('SELECT first_name, last_name FROM sse_identities WHERE tenant_id = :t', static function (array $r) use (&$names): void {
            $names[mb_strtolower(trim((string) $r['first_name'] . ' ' . (string) $r['last_name']))] = true;
        });
        $run('SELECT doc_number FROM sse_id_documents WHERE tenant_id = :t', static function (array $r) use (&$numbers): void {
            $numbers[strtoupper((string) $r['doc_number'])] = true;
        });
        $run('SELECT cover_name, doc_number FROM sse_cover_exports WHERE tenant_id = :t', static function (array $r) use (&$names, &$numbers): void {
            $names[mb_strtolower((string) $r['cover_name'])] = true;
            $numbers[strtoupper((string) $r['doc_number'])] = true;
        });

        return ['names' => $names, 'numbers' => $numbers];
    }
}
