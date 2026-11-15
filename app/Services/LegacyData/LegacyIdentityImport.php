<?php

declare(strict_types=1);

namespace App\Services\LegacyData;

use App\Core\Database;
use App\Support\Migrations\SchemaInspector;
use PDO;
use Throwable;

/**
 * Reprise NON destructive et réversible des anciens noms de personnage vers le prénom et le nom d'état civil.
 *
 * Déclenchée à la main par un responsable (Qualité des données, « Reprendre les anciens noms »), simulation d'abord ;
 * jamais par une tâche planifiée ni par une migration. Règles :
 *  - seulement quand le prénom ET le nom du membre sont vides (ou que sa fiche d'état civil n'existe pas encore) ;
 *    une valeur existante n'est JAMAIS remplacée (la condition est aussi répétée dans l'UPDATE) ;
 *  - le nom de personnage reste intact dans son dossier ; rien n'est supprimé ;
 *  - chaque membre repris est inscrit au journal legacy_identity_import_log (valeur d'origine, prénom et nom copiés) : c'est
 *    le marqueur « importé depuis l'ancien nom de personnage, à vérifier » et la source du retour arrière ;
 *  - le retour arrière d'un lot ne retire que ce que ce lot a copié, et seulement si le membre ne l'a pas modifié depuis.
 *
 * Écrit dans user_legal_identities (comme l'état civil du reste du site) ; sans cette table ou sans le journal, rien n'est écrit.
 */
final class LegacyIdentityImport
{
    public const MAX_PER_BATCH = 200;

    public const MARKER_FR = 'Importé depuis l’ancien nom de personnage — à vérifier';

    public const MARKER_EN = 'Imported from the old character name — to check';

    private PDO $pdo;

    private SchemaInspector $schema;

    private LegacyDataCounter $counter;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
        $this->schema = new SchemaInspector($this->pdo);
        $this->counter = new LegacyDataCounter($this->pdo);
    }

    /** @return array{ok: bool, reason: string} */
    public function available(): array
    {
        if (!$this->schema->tableExists('user_legal_identities') || !$this->schema->tableExists('personnel_profiles')
            || !$this->schema->columnExists('personnel_profiles', 'character_name')) {
            return ['ok' => false, 'reason' => 'La base n’a pas les fiches d’état civil ou les noms de personnage : rien à reprendre.'];
        }
        if (!$this->schema->tableExists('legacy_identity_import_log')) {
            return ['ok' => false, 'reason' => 'Migration à passer (journal de la reprise des anciens noms absent) : rien n’est écrit.'];
        }

        return ['ok' => true, 'reason' => ''];
    }

    /**
     * Découpe un nom de personnage en prénom et nom : un seul mot devient le nom ; sinon le premier mot est le prénom et le
     * reste le nom. Approximation assumée (l'import est marqué « à vérifier »). Null si le nom est vide.
     *
     * @return array{first: ?string, last: string}|null
     */
    public static function split(string $characterName): ?array
    {
        $clean = trim((string) preg_replace('/\s+/u', ' ', $characterName));
        if ($clean === '') {
            return null;
        }
        $parts = explode(' ', $clean, 2);
        if (count($parts) === 1) {
            return ['first' => null, 'last' => mb_substr($clean, 0, 100)];
        }

        return ['first' => mb_substr($parts[0], 0, 100), 'last' => mb_substr($parts[1], 0, 100)];
    }

    /**
     * Membres dont le nom de personnage serait repris (aucune écriture).
     *
     * @return array{available: bool, reason: string, total: int, candidates: list<array{user_id: int, home_tenant_id: int, identity_id: int, character_name: string, first_name: ?string, last_name: string}>}
     */
    public function simulate(int $tenantId, int $limit = self::MAX_PER_BATCH): array
    {
        $avail = $this->available();
        if (!$avail['ok'] || $tenantId < 1) {
            return ['available' => false, 'reason' => $avail['reason'], 'total' => 0, 'candidates' => []];
        }
        $limit = max(1, min(self::MAX_PER_BATCH, $limit));
        $sql = 'SELECT u.id AS user_id, u.tenant_id AS home_tenant_id, x.id AS identity_id, p.character_name AS character_name
                FROM users u
                INNER JOIN personnel_profiles p ON p.user_id = u.id
                LEFT JOIN user_legal_identities x ON x.user_id = u.id
                WHERE ' . $this->counter->memberWhere() . "
                  AND TRIM(COALESCE(p.character_name, '')) <> ''
                  AND (x.id IS NULL OR (TRIM(COALESCE(x.first_name, '')) = '' AND TRIM(COALESCE(x.last_name, '')) = ''))
                ORDER BY u.id ASC LIMIT " . ($limit * 2);
        $st = $this->pdo->prepare($sql);
        $st->execute($this->counter->memberParams($tenantId));
        $candidates = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $uid = (int) ($row['user_id'] ?? 0);
            $parts = self::split((string) ($row['character_name'] ?? ''));
            if ($uid < 1 || $parts === null || isset($candidates[$uid])) {
                continue;
            }
            $candidates[$uid] = [
                'user_id' => $uid,
                'home_tenant_id' => (int) ($row['home_tenant_id'] ?? 0),
                'identity_id' => (int) ($row['identity_id'] ?? 0),
                'character_name' => trim((string) $row['character_name']),
                'first_name' => $parts['first'],
                'last_name' => $parts['last'],
            ];
            if (count($candidates) >= $limit) {
                break;
            }
        }

        return ['available' => true, 'reason' => '', 'total' => (int) ($this->counter->count($tenantId)['importable_names'] ?? 0), 'candidates' => array_values($candidates)];
    }

    /**
     * Reprend les noms (un lot d'au plus MAX_PER_BATCH membres).
     *
     * @return array{ok: bool, reason: string, batch_id: string, imported: int, skipped: int}
     */
    public function apply(int $tenantId, ?int $actorUserId, int $limit = self::MAX_PER_BATCH, ?int $now = null): array
    {
        $plan = $this->simulate($tenantId, $limit);
        if (!$plan['available']) {
            return ['ok' => false, 'reason' => $plan['reason'], 'batch_id' => '', 'imported' => 0, 'skipped' => 0];
        }
        $stamp = date('Y-m-d H:i:s', $now ?? time());
        $batch = bin2hex(random_bytes(8));
        $imported = 0;
        $skipped = 0;
        try {
            $this->pdo->beginTransaction();
            foreach ($plan['candidates'] as $c) {
                $created = $c['identity_id'] < 1;
                if ($created) {
                    $st = $this->pdo->prepare('INSERT INTO user_legal_identities (tenant_id, user_id, first_name, last_name, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
                    $st->execute([max(1, $c['home_tenant_id']), $c['user_id'], $c['first_name'], $c['last_name'], $stamp, $stamp]);
                    $done = $st->rowCount() === 1;
                } else {
                    // Garde répétée dans l'UPDATE : une valeur saisie entre-temps n'est jamais écrasée.
                    $st = $this->pdo->prepare(
                        "UPDATE user_legal_identities SET first_name = ?, last_name = ?, updated_at = ?
                         WHERE id = ? AND TRIM(COALESCE(first_name, '')) = '' AND TRIM(COALESCE(last_name, '')) = ''"
                    );
                    $st->execute([$c['first_name'], $c['last_name'], $stamp, $c['identity_id']]);
                    $done = $st->rowCount() === 1;
                }
                if (!$done) {
                    $skipped++;
                    continue;
                }
                $log = $this->pdo->prepare(
                    'INSERT INTO legacy_identity_import_log (tenant_id, user_id, batch_id, actor_user_id, source_character_name, first_name, last_name, identity_row_created, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $log->execute([$tenantId, $c['user_id'], $batch, $actorUserId, $c['character_name'], $c['first_name'], $c['last_name'], $created ? 1 : 0, $stamp]);
                $imported++;
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[legacy_identity_import] communauté ' . $tenantId . ' : ' . $e->getMessage());

            return ['ok' => false, 'reason' => 'La reprise a échoué : rien n’a été modifié.', 'batch_id' => '', 'imported' => 0, 'skipped' => $skipped];
        }

        return ['ok' => true, 'reason' => '', 'batch_id' => $batch, 'imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * Annule un lot : retire le prénom et le nom copiés, seulement s'ils sont encore tels que le lot les a posés.
     *
     * @return array{ok: bool, reason: string, reverted: int, kept: int}
     */
    public function revert(int $tenantId, string $batchId, ?int $now = null): array
    {
        $avail = $this->available();
        if (!$avail['ok'] || $batchId === '') {
            return ['ok' => false, 'reason' => $avail['reason'] !== '' ? $avail['reason'] : 'Lot inconnu.', 'reverted' => 0, 'kept' => 0];
        }
        $stamp = date('Y-m-d H:i:s', $now ?? time());
        $reverted = 0;
        $kept = 0;
        try {
            $this->pdo->beginTransaction();
            $st = $this->pdo->prepare('SELECT id, user_id, first_name, last_name, identity_row_created FROM legacy_identity_import_log WHERE tenant_id = ? AND batch_id = ? AND reverted_at IS NULL');
            $st->execute([$tenantId, $batchId]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $log) {
                $cur = $this->pdo->prepare('SELECT id, first_name, last_name FROM user_legal_identities WHERE user_id = ?');
                $cur->execute([(int) $log['user_id']]);
                $row = $cur->fetch(PDO::FETCH_ASSOC);
                $unchanged = is_array($row)
                    && trim((string) ($row['first_name'] ?? '')) === trim((string) ($log['first_name'] ?? ''))
                    && trim((string) ($row['last_name'] ?? '')) === trim((string) ($log['last_name'] ?? ''));
                if (!$unchanged) {
                    $kept++;
                    continue;
                }
                $this->pdo->prepare('UPDATE user_legal_identities SET first_name = NULL, last_name = NULL, updated_at = ? WHERE id = ?')->execute([$stamp, (int) $row['id']]);
                if ((int) $log['identity_row_created'] === 1 && $this->identityRowIsEmpty((int) $row['id'])) {
                    $this->pdo->prepare('DELETE FROM user_legal_identities WHERE id = ?')->execute([(int) $row['id']]);
                }
                $this->pdo->prepare('UPDATE legacy_identity_import_log SET reverted_at = ? WHERE id = ?')->execute([$stamp, (int) $log['id']]);
                $reverted++;
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[legacy_identity_import] annulation du lot ' . $batchId . ' : ' . $e->getMessage());

            return ['ok' => false, 'reason' => 'L’annulation a échoué : rien n’a été modifié.', 'reverted' => 0, 'kept' => 0];
        }

        return ['ok' => true, 'reason' => '', 'reverted' => $reverted, 'kept' => $kept];
    }

    /** Une fiche d'état civil créée par la reprise n'est supprimée que si aucune autre information n'y a été ajoutée depuis. */
    private function identityRowIsEmpty(int $identityId): bool
    {
        $cols = array_values(array_filter(['phone', 'birth_date', 'nationality'], fn (string $c): bool => $this->schema->columnExists('user_legal_identities', $c)));
        if ($cols === []) {
            return true;
        }
        $st = $this->pdo->prepare('SELECT ' . implode(', ', $cols) . ' FROM user_legal_identities WHERE id = ?');
        $st->execute([$identityId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        foreach ($cols as $c) {
            if (is_array($row) && trim((string) ($row[$c] ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Membres dont le nom vient d'une reprise non annulée (marqueur « à vérifier »).
     *
     * @return list<int>
     */
    public function markedUserIds(int $tenantId): array
    {
        if (!$this->schema->tableExists('legacy_identity_import_log')) {
            return [];
        }
        $st = $this->pdo->prepare('SELECT DISTINCT user_id FROM legacy_identity_import_log WHERE tenant_id = ? AND reverted_at IS NULL ORDER BY user_id');
        $st->execute([$tenantId]);

        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /**
     * Lots de reprise de la communauté, du plus récent au plus ancien (journal).
     *
     * @return list<array{batch_id: string, at: string, imported: int, active: int}>
     */
    public function batches(int $tenantId, int $limit = 10): array
    {
        if (!$this->schema->tableExists('legacy_identity_import_log')) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT batch_id, MAX(created_at) AS at, COUNT(*) AS imported, SUM(CASE WHEN reverted_at IS NULL THEN 1 ELSE 0 END) AS active
             FROM legacy_identity_import_log WHERE tenant_id = ? GROUP BY batch_id ORDER BY MAX(created_at) DESC, batch_id DESC LIMIT ' . max(1, min(50, $limit))
        );
        $st->execute([$tenantId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $out[] = ['batch_id' => (string) $r['batch_id'], 'at' => (string) $r['at'], 'imported' => (int) $r['imported'], 'active' => (int) $r['active']];
        }

        return $out;
    }
}
