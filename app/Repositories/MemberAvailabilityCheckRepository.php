<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use PDO;

/**
 * Vérifications de disponibilité enregistrées (table member_availability_checks, étape 4860).
 *
 * Tout est borné par communauté. Sans la table (étape non appliquée), `schemaReady()` est faux : les lectures rendent des
 * listes vides et l'enregistrement est refusé par l'appelant avec un message.
 */
class MemberAvailabilityCheckRepository
{
    use LazyDatabaseConnection;

    public const TABLE = 'member_availability_checks';

    private ?bool $ready = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public function schemaReady(): bool
    {
        if ($this->ready === null) {
            try {
                $this->pdo()->query('SELECT 1 FROM ' . self::TABLE . ' WHERE 1 = 0');
                $this->ready = true;
            } catch (\Throwable) {
                $this->ready = false;
            }
        }

        return $this->ready;
    }

    /**
     * @param array{proposed: string, conclusion: string, corrected: bool, note: string, follow_up: string, follow_up_due: ?string, follow_up_ref: ?string, facts: array<string, mixed>, batch_id: ?string} $f
     */
    public function insert(int $tenantId, int $userId, int $actorId, array $f, ?string $at = null): int
    {
        $st = $this->pdo()->prepare(
            'INSERT INTO ' . self::TABLE . ' (tenant_id, user_id, checked_by, proposed, conclusion, corrected, note, follow_up, follow_up_due, follow_up_ref, facts_json, batch_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $tenantId,
            $userId,
            $actorId > 0 ? $actorId : null,
            mb_substr($f['proposed'], 0, 20),
            mb_substr($f['conclusion'], 0, 20),
            $f['corrected'] ? 1 : 0,
            $f['note'] !== '' ? mb_substr($f['note'], 0, 2000) : null,
            mb_substr($f['follow_up'], 0, 20),
            $f['follow_up_due'],
            $f['follow_up_ref'] !== null ? mb_substr($f['follow_up_ref'], 0, 80) : null,
            json_encode($f['facts'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $f['batch_id'],
            $at ?? date('Y-m-d H:i:s'),
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    /** Lie la suite donnée (étape du suivi du dossier) à la vérification enregistrée. */
    public function setFollowUpRef(int $tenantId, int $id, string $ref): void
    {
        $this->pdo()->prepare('UPDATE ' . self::TABLE . ' SET follow_up_ref = ? WHERE id = ? AND tenant_id = ?')
            ->execute([mb_substr($ref, 0, 80), $id, $tenantId]);
    }

    /**
     * Dernières vérifications d'un membre, de la plus récente à la plus ancienne.
     *
     * @return list<array<string, mixed>>
     */
    public function listForUser(int $tenantId, int $userId, int $limit = 5): array
    {
        if (!$this->schemaReady() || $tenantId < 1 || $userId < 1) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT * FROM ' . self::TABLE . ' WHERE tenant_id = ? AND user_id = ? ORDER BY created_at DESC, id DESC LIMIT ' . max(1, min(50, $limit))
        );
        $st->execute([$tenantId, $userId]);

        return array_values($st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /**
     * Dernière vérification de chaque membre (tableur des effectifs), sans requête par membre.
     *
     * @param list<int> $userIds
     * @return array<int, array{id: int, conclusion: string, created_at: string, corrected: bool}>
     */
    public function latestForUsers(int $tenantId, array $userIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $v): bool => $v > 0)));
        if ($ids === [] || $tenantId < 1 || !$this->schemaReady()) {
            return [];
        }
        $out = [];
        foreach (array_chunk($ids, 400) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            // La plus récente de chaque membre : identifiant le plus grand (une ligne n'est jamais ré-horodatée).
            $st = $this->pdo()->prepare(
                'SELECT c.id, c.user_id, c.conclusion, c.corrected, c.created_at FROM ' . self::TABLE . ' c
                 INNER JOIN (SELECT user_id, MAX(id) AS last_id FROM ' . self::TABLE . " WHERE tenant_id = ? AND user_id IN ({$in}) GROUP BY user_id) m
                   ON m.last_id = c.id
                 WHERE c.tenant_id = ?"
            );
            $st->execute(array_merge([$tenantId], $chunk, [$tenantId]));
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $out[(int) $r['user_id']] = [
                    'id' => (int) $r['id'],
                    'conclusion' => (string) $r['conclusion'],
                    'created_at' => (string) $r['created_at'],
                    'corrected' => (int) $r['corrected'] === 1,
                ];
            }
        }

        return $out;
    }
}
