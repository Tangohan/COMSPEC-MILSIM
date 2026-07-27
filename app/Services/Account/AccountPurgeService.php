<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * Suppression définitive d’un compte — par opposition à l’anonymisation.
 *
 * {@see AccountDeletionService} conserve la ligne `users` en la neutralisant : le compte
 * reste visible sous « Compte supprimé / deleted-N@deleted.invalid ». C’est le bon
 * comportement quand l’historique doit rester cohérent. Ce service fait l’autre choix :
 * la ligne et tout ce qui décrit la personne disparaissent de la base.
 *
 * Deux catégories de colonnes, traitées différemment — c’est le cœur du service :
 *
 * - **Possession** (`user_id`, `target_user_id`, `author_user_id`…) : la ligne *parle de*
 *   la personne. Elle est supprimée.
 * - **Attribution** (`created_by_user_id`, `actor_user_id`, `deleted_by`…) : la ligne parle
 *   de *quelqu’un d’autre*, la personne n’y est qu’un signataire. La référence est mise à
 *   NULL, la ligne reste. Supprimer ces lignes effacerait l’historique de tiers — la
 *   sanction qu’un cadre a prononcée disparaîtrait avec le départ du cadre.
 *
 * Le balayage passe par `information_schema` plutôt que par une liste figée : une bonne
 * partie du schéma n’a aucune clé étrangère vers `users` (le lot ATAK les retire
 * volontairement), donc `ON DELETE CASCADE` ne peut pas servir de filet.
 *
 * Opération irréversible et sans reprise possible : réservée à l’administration
 * plateforme, et journalisée avant exécution.
 */
final class AccountPurgeService
{
    /**
     * Colonnes qui désignent le sujet de la ligne : la ligne est supprimée.
     *
     * @var list<string>
     */
    private const OWNERSHIP_COLUMNS = [
        'user_id',
        'target_user_id',
        'author_user_id',
        'owner_user_id',
        'member_user_id',
        'profile_user_id',
        'subject_user_id',
        'student_user_id',
        'trainee_user_id',
        'holder_user_id',
        'recipient_user_id',
        'sender_user_id',
        'submitter_user_id',
        'requesting_user_id',
        'requested_by_user_id',
        'reported_by_user_id',
        'observer_user_id',
    ];

    /**
     * Colonnes de signature : la référence est mise à NULL, la ligne survit.
     *
     * @var list<string>
     */
    private const ATTRIBUTION_COLUMNS = [
        'actor_user_id',
        'created_by_user_id',
        'updated_by_user_id',
        'deleted_by_user_id',
        'acknowledged_by_user_id',
        'performed_by_user_id',
        'validated_by_user_id',
        'granted_by_user_id',
        'flagged_by_user_id',
        'changed_by_user_id',
        'assigned_by_user_id',
        'taken_by_user_id',
        'reached_by_user_id',
        'resolved_by_user_id',
        'approved_by_user_id',
        'rejected_by_user_id',
        'reviewed_by_user_id',
        'closed_by_user_id',
        'instructor_user_id',
        'issuer_user_id',
        'pilot_user_id',
        'assigned_pilot_user_id',
        'assigned_qrf_leader_user_id',
        'crew_commander_user_id',
        'commander_user_id',
        'source_user_id',
        'created_by',
        'updated_by',
        'deleted_by',
        'validated_by',
        'uploaded_by',
        'signed_by',
        'locked_by',
        'granted_by',
        'assigned_by',
        'acted_by',
        'deployed_by',
        'last_modified_by',
    ];

    /**
     * Tables jamais balayées : leur colonne `user_id` ne référence pas `users`, ou la ligne
     * doit survivre au compte pour rester exploitable.
     *
     * @var list<string>
     */
    private const SKIP_TABLES = [
        'users',
        'migrations',
    ];

    /**
     * @param PDO|null $pdo Connexion explicite — sert aux tests, qui rejouent le balayage
     *                      sur un schéma jetable plutôt que sur la base de production.
     */
    public function __construct(private ?PDO $pdo = null) {}

    /**
     * Classe une colonne : possession (ligne supprimée), attribution (référence détachée),
     * ou hors-sujet. Extrait du balayage pour être vérifiable sans base.
     *
     * @return 'ownership'|'attribution'|null
     */
    public static function classifyColumn(string $column, bool $nullable): ?string
    {
        if (in_array($column, self::OWNERSHIP_COLUMNS, true)) {
            return 'ownership';
        }

        // Une colonne de signature NOT NULL ne peut pas être détachée : la laisser en
        // l’état vaut mieux que de casser la ligne d’un tiers.
        if (!$nullable) {
            return null;
        }

        // Règle de forme avant liste nominative : `<verbe>_by_user_id` désigne toujours un
        // signataire, et le schéma en invente régulièrement (issued_by_user_id,
        // escalated_by_user_id…). Une liste figée les manquerait en silence, et la
        // référence resterait pointée sur un compte effacé.
        //
        // Le suffixe est volontairement `_by_user_id` et non `_by` : `sort_by`, `ordered_by`
        // et consorts sont du texte, les mettre à NULL casserait des écrans.
        if (str_ends_with($column, '_by_user_id')) {
            return 'attribution';
        }

        return in_array($column, self::ATTRIBUTION_COLUMNS, true) ? 'attribution' : null;
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    /**
     * Supprime définitivement un compte et tous les comptes partageant son adresse.
     *
     * @return array{
     *     ok: bool,
     *     purged_user_ids: list<int>,
     *     rows_deleted: int,
     *     rows_detached: int,
     *     tables: array<string, array{deleted: int, detached: int}>,
     *     errors: list<string>
     * }
     */
    public function purge(int $userId, array $siblingIds = []): array
    {
        $report = [
            'ok' => true,
            'purged_user_ids' => [],
            'rows_deleted' => 0,
            'rows_detached' => 0,
            'tables' => [],
            'errors' => [],
        ];

        $ids = array_values(array_unique(array_filter(
            array_map('intval', array_merge([$userId], $siblingIds)),
            static fn (int $id): bool => $id > 0
        )));
        if ($ids === []) {
            $report['ok'] = false;
            $report['errors'][] = 'Aucun identifiant de compte valide.';

            return $report;
        }

        try {
            $pdo = $this->pdo();
        } catch (Throwable $e) {
            $report['ok'] = false;
            $report['errors'][] = 'Connexion base indisponible : ' . $e->getMessage();

            return $report;
        }

        $plan = $this->buildPlan($pdo);

        // Les contraintes sont levées le temps du balayage : le schéma mêle des FK
        // RESTRICT, des FK CASCADE et des colonnes sans FK du tout. Sans cela, l’ordre
        // de suppression deviendrait un casse-tête, pour un gain nul.
        $restoreChecks = false;
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            $restoreChecks = true;
        } catch (Throwable) {
        }

        try {
            foreach ($plan['ownership'] as $table => $columns) {
                foreach ($columns as $column) {
                    $affected = $this->deleteRows($pdo, $table, $column, $ids, $report);
                    if ($affected > 0) {
                        $this->tally($report, $table, 'deleted', $affected);
                    }
                }
            }

            foreach ($plan['attribution'] as $table => $columns) {
                foreach ($columns as $column) {
                    $affected = $this->detachRows($pdo, $table, $column, $ids, $report);
                    if ($affected > 0) {
                        $this->tally($report, $table, 'detached', $affected);
                    }
                }
            }

            foreach ($ids as $id) {
                try {
                    $stmt = $pdo->prepare('DELETE FROM `users` WHERE `id` = ?');
                    $stmt->execute([$id]);
                    if ($stmt->rowCount() > 0) {
                        $report['purged_user_ids'][] = $id;
                        $report['rows_deleted'] += $stmt->rowCount();
                        $this->tally($report, 'users', 'deleted', $stmt->rowCount());
                    }
                } catch (Throwable $e) {
                    $report['ok'] = false;
                    $report['errors'][] = 'users #' . $id . ' : ' . $e->getMessage();
                }
            }
        } finally {
            if ($restoreChecks) {
                try {
                    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
                } catch (Throwable) {
                }
            }
        }

        if ($report['purged_user_ids'] === []) {
            $report['ok'] = false;
            $report['errors'][] = 'Aucune ligne users supprimée.';
        }

        return $report;
    }

    /**
     * Ce que la purge ferait, sans rien exécuter — pour montrer l’ampleur avant de valider.
     *
     * @return array{tables_scanned: int, ownership: int, attribution: int, rows: array<string, int>}
     */
    public function preview(int $userId, array $siblingIds = []): array
    {
        $summary = ['tables_scanned' => 0, 'ownership' => 0, 'attribution' => 0, 'rows' => []];

        $ids = array_values(array_unique(array_filter(
            array_map('intval', array_merge([$userId], $siblingIds)),
            static fn (int $id): bool => $id > 0
        )));
        if ($ids === []) {
            return $summary;
        }

        try {
            $pdo = $this->pdo();
        } catch (Throwable) {
            return $summary;
        }

        $plan = $this->buildPlan($pdo);
        $summary['ownership'] = count($plan['ownership']);
        $summary['attribution'] = count($plan['attribution']);
        $summary['tables_scanned'] = count(array_unique(array_merge(
            array_keys($plan['ownership']),
            array_keys($plan['attribution'])
        )));

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        foreach ($plan['ownership'] as $table => $columns) {
            foreach ($columns as $column) {
                try {
                    $stmt = $pdo->prepare(
                        'SELECT COUNT(*) FROM `' . $table . '` WHERE `' . $column . '` IN (' . $placeholders . ')'
                    );
                    $stmt->execute($ids);
                    $count = (int) $stmt->fetchColumn();
                    if ($count > 0) {
                        $summary['rows'][$table] = ($summary['rows'][$table] ?? 0) + $count;
                    }
                } catch (Throwable) {
                }
            }
        }

        arsort($summary['rows']);

        return $summary;
    }

    /**
     * Purge en série les comptes déjà anonymisés — le passif laissé par l’ancien
     * comportement, ces fiches « Compte supprimé » qui traînent dans les annuaires.
     *
     * @return array{ok: bool, purged: int, failed: int, rows_deleted: int, errors: list<string>}
     */
    public function purgeAnonymizedAccounts(int $limit = 200): array
    {
        $result = ['ok' => true, 'purged' => 0, 'failed' => 0, 'rows_deleted' => 0, 'errors' => []];

        try {
            $pdo = $this->pdo();
            $limit = max(1, min(1000, $limit));
            $stmt = $pdo->query(
                "SELECT `id` FROM `users` WHERE `email` LIKE '%@deleted.invalid' LIMIT " . $limit
            );
            $ids = $stmt ? array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)) : [];
        } catch (Throwable $e) {
            return ['ok' => false, 'purged' => 0, 'failed' => 0, 'rows_deleted' => 0, 'errors' => [$e->getMessage()]];
        }

        foreach ($ids as $id) {
            // Chaque compte est purgé seul : leurs adresses anonymisées sont déjà
            // distinctes, il n’y a pas de fratrie à regrouper.
            $report = $this->purge($id);
            if ($report['ok']) {
                $result['purged']++;
                $result['rows_deleted'] += $report['rows_deleted'];
            } else {
                $result['failed']++;
                $result['ok'] = false;
                foreach ($report['errors'] as $error) {
                    $result['errors'][] = '#' . $id . ' : ' . $error;
                }
            }
        }

        return $result;
    }

    /**
     * Construit le plan de balayage à partir du schéma réel.
     *
     * @return array{ownership: array<string, list<string>>, attribution: array<string, list<string>>}
     */
    private function buildPlan(PDO $pdo): array
    {
        $plan = ['ownership' => [], 'attribution' => []];

        foreach ($this->listColumns($pdo) as $row) {
            [$table, $column, $nullable] = $row;
            if ($table === '' || $column === '' || in_array($table, self::SKIP_TABLES, true)) {
                continue;
            }
            if (!$this->isSafeIdentifier($table) || !$this->isSafeIdentifier($column)) {
                continue;
            }

            $kind = self::classifyColumn($column, $nullable);
            if ($kind !== null) {
                $plan[$kind][$table][] = $column;
            }
        }

        return $plan;
    }

    /**
     * Inventaire des colonnes du schéma courant, sous la forme [table, colonne, nullable].
     *
     * MySQL en production ; SQLite est reconnu pour que le harnais de vérification puisse
     * rejouer une purge complète sur un schéma jetable, sans serveur MySQL.
     *
     * @return list<array{0: string, 1: string, 2: bool}>
     */
    private function listColumns(PDO $pdo): array
    {
        try {
            $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (Throwable) {
            $driver = 'mysql';
        }

        try {
            if ($driver === 'sqlite') {
                $tables = $pdo->query(
                    "SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name"
                );
                $names = $tables ? $tables->fetchAll(PDO::FETCH_COLUMN) : [];
                $out = [];
                foreach ($names as $name) {
                    $name = (string) $name;
                    if (!$this->isSafeIdentifier($name)) {
                        continue;
                    }
                    $info = $pdo->query('PRAGMA table_info(`' . $name . '`)');
                    foreach ($info ? $info->fetchAll(PDO::FETCH_ASSOC) : [] as $col) {
                        $out[] = [$name, (string) ($col['name'] ?? ''), ((int) ($col['notnull'] ?? 0)) === 0];
                    }
                }

                return $out;
            }

            $stmt = $pdo->query(
                'SELECT TABLE_NAME, COLUMN_NAME, IS_NULLABLE
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                 ORDER BY TABLE_NAME, COLUMN_NAME'
            );
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            $out = [];
            foreach ($rows as $row) {
                $out[] = [
                    (string) ($row['TABLE_NAME'] ?? ''),
                    (string) ($row['COLUMN_NAME'] ?? ''),
                    strtoupper((string) ($row['IS_NULLABLE'] ?? 'YES')) === 'YES',
                ];
            }

            return $out;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param list<int> $ids
     * @param array<string, mixed> $report
     */
    private function deleteRows(PDO $pdo, string $table, string $column, array $ids, array &$report): int
    {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        try {
            $stmt = $pdo->prepare(
                'DELETE FROM `' . $table . '` WHERE `' . $column . '` IN (' . $placeholders . ')'
            );
            $stmt->execute($ids);
            $affected = $stmt->rowCount();
            $report['rows_deleted'] += $affected;

            return $affected;
        } catch (Throwable $e) {
            $report['errors'][] = $table . '.' . $column . ' (suppression) : ' . $e->getMessage();

            return 0;
        }
    }

    /**
     * @param list<int> $ids
     * @param array<string, mixed> $report
     */
    private function detachRows(PDO $pdo, string $table, string $column, array $ids, array &$report): int
    {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        try {
            $stmt = $pdo->prepare(
                'UPDATE `' . $table . '` SET `' . $column . '` = NULL WHERE `' . $column . '` IN (' . $placeholders . ')'
            );
            $stmt->execute($ids);
            $affected = $stmt->rowCount();
            $report['rows_detached'] += $affected;

            return $affected;
        } catch (Throwable $e) {
            $report['errors'][] = $table . '.' . $column . ' (détachement) : ' . $e->getMessage();

            return 0;
        }
    }

    /**
     * @param array<string, mixed> $report
     */
    private function tally(array &$report, string $table, string $key, int $count): void
    {
        if (!isset($report['tables'][$table])) {
            $report['tables'][$table] = ['deleted' => 0, 'detached' => 0];
        }
        $report['tables'][$table][$key] += $count;
    }

    /**
     * Les noms viennent d’information_schema, donc d’une source de confiance — mais ils
     * sont interpolés dans le SQL, alors on vérifie quand même.
     */
    private function isSafeIdentifier(string $name): bool
    {
        return $name !== '' && preg_match('/^[A-Za-z0-9_]+$/', $name) === 1;
    }
}
