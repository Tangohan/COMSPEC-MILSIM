<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Database;
use App\Support\SteamId;
use PDO;

/**
 * Efface toutes les traces de jeu d'un joueur, quelle que soit la clé de la table.
 *
 * Les tables ATAK n'ont pas de clé étrangère vers users : selon la table, un joueur est retrouvé par
 * user_id, par identifiant Steam ou par indicatif (atak_units). Les suppressions de compte ne
 * travaillaient que sur user_id : le joueur restait sur la carte, dans les registres et les
 * statistiques, et réapparaissait au sync suivant. Ce service rassemble toutes les identités du joueur
 * (Steam et indicatifs connus, même après anonymisation de la fiche) puis nettoie chaque table.
 *
 * Conservé volontairement : l'historique opérationnel (comptes rendus, fiches SSE, messages, journal
 * des actions d'administration), qui reste attribué au compte anonymisé.
 *
 * Mode « dry run » (plan + count) utilisé par le registre des données pour montrer les corrélations.
 */
final class PlayerTracePurgeService
{
    /** @var array<string, bool> */
    private array $tables = [];

    /** @var array<string, array<string, bool>> */
    private array $columns = [];

    public function __construct(private ?PDO $pdo = null)
    {
    }

    private function pdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = Database::getPdo();
        }
        \App\Support\UsersDeletedAtSchema::ensure($this->pdo);

        return $this->pdo;
    }

    // ================================================================= identités

    /**
     * Steam, indicatifs et communautés connus d'un compte, y compris depuis les tables de jeu
     * (la fiche anonymisée n'a plus de steam_id ni d'indicatif).
     *
     * @return array{user_ids: list<int>, steams: list<string>, callsigns: list<string>, tenants: list<int>}
     */
    public function identityForUser(int $userId): array
    {
        $steams = [];
        $callsigns = [];
        $tenants = [];
        foreach ($this->q('users', 'SELECT tenant_id, steam_id, callsign FROM users WHERE id = ?', [$userId]) as $r) {
            $tenants[] = (int) $r['tenant_id'];
            $steams[] = (string) ($r['steam_id'] ?? '');
            $callsigns[] = (string) ($r['callsign'] ?? '');
        }
        foreach ($this->q('operator_game_profiles', 'SELECT tenant_id, steam_id, callsign FROM operator_game_profiles WHERE user_id = ?', [$userId]) as $r) {
            $tenants[] = (int) $r['tenant_id'];
            $steams[] = (string) $r['steam_id'];
            $callsigns[] = (string) ($r['callsign'] ?? '');
        }
        foreach ($this->q('game_sessions', 'SELECT DISTINCT tenant_id, steam_id FROM game_sessions WHERE user_id = ?', [$userId]) as $r) {
            $tenants[] = (int) $r['tenant_id'];
            $steams[] = (string) ($r['steam_id'] ?? '');
        }
        foreach ($this->q('account_tenant_memberships', 'SELECT a.steam_id FROM account_tenant_memberships m JOIN athena_accounts a ON a.id = m.account_id WHERE m.user_id = ?', [$userId]) as $r) {
            $steams[] = (string) ($r['steam_id'] ?? '');
        }
        foreach ($this->q('atak_terminals', 'SELECT tenant_id, operator_callsign FROM atak_terminals WHERE user_id = ?', [$userId]) as $r) {
            $tenants[] = (int) $r['tenant_id'];
            $callsigns[] = (string) ($r['operator_callsign'] ?? '');
        }
        foreach ($this->q('atak_operator_ids', 'SELECT tenant_id, call_sign FROM atak_operator_ids WHERE user_id = ?', [$userId]) as $r) {
            $tenants[] = (int) $r['tenant_id'];
            $callsigns[] = (string) ($r['call_sign'] ?? '');
        }

        // Un Steam passé depuis sur un autre compte vivant ne doit pas être arraché à ce compte.
        $steams = array_values(array_filter(self::cleanSteams($steams), function (string $sid) use ($userId): bool {
            return $this->q(
                'users',
                "SELECT 1 FROM users WHERE steam_id = ? AND id <> ? AND deleted_at IS NULL AND COALESCE(status, '') NOT IN ('merged', 'deleted') LIMIT 1",
                [$sid, $userId]
            ) === [];
        }));

        return [
            'user_ids' => [$userId],
            'steams' => $steams,
            'callsigns' => self::cleanStrings($callsigns),
            'tenants' => array_values(array_unique(array_filter($tenants, static fn (int $t): bool => $t > 0))),
        ];
    }

    /**
     * @return array{user_ids: list<int>, steams: list<string>, callsigns: list<string>, tenants: list<int>}
     */
    public function identityForSteam(string $steam): array
    {
        $sid = SteamId::normalize($steam) ?? trim($steam);
        $userIds = [];
        $callsigns = [];
        $tenants = [];
        foreach ($this->q('users', 'SELECT id, tenant_id, callsign FROM users WHERE steam_id = ?', [$sid]) as $r) {
            $userIds[] = (int) $r['id'];
            $tenants[] = (int) $r['tenant_id'];
            $callsigns[] = (string) ($r['callsign'] ?? '');
        }
        foreach ($this->q('operator_game_profiles', 'SELECT user_id, tenant_id, callsign FROM operator_game_profiles WHERE steam_id = ?', [$sid]) as $r) {
            if ((int) ($r['user_id'] ?? 0) > 0) {
                $userIds[] = (int) $r['user_id'];
            }
            $tenants[] = (int) $r['tenant_id'];
            $callsigns[] = (string) ($r['callsign'] ?? '');
        }

        return [
            'user_ids' => array_values(array_unique($userIds)),
            'steams' => [$sid],
            'callsigns' => self::cleanStrings($callsigns),
            'tenants' => array_values(array_unique(array_filter($tenants, static fn (int $t): bool => $t > 0))),
        ];
    }

    // ================================================================= plan

    /**
     * Tables touchées pour cette identité : [table, libellé, mode (delete|null|keep), where, params, colonne de date].
     * $tenantScope limite au périmètre d'une communauté (retrait d'un membre) ; null = toute la plateforme.
     *
     * @param array{user_ids: list<int>, steams: list<string>, callsigns: list<string>, tenants: list<int>} $id
     * @return list<array{table: string, label: string, mode: string, where: string, params: list<mixed>, date: ?string, set?: string}>
     */
    public function plan(array $id, ?int $tenantScope = null, bool $includeUsers = true): array
    {
        $users = $includeUsers ? $id['user_ids'] : [];
        $steams = $id['steams'];
        $callsigns = $id['callsigns'];
        $tenants = $tenantScope !== null ? [$tenantScope] : $id['tenants'];
        $in = static fn (array $v): string => implode(',', array_fill(0, max(1, count($v)), '?'));
        $tw = static fn (string $col = 'tenant_id'): array => $tenantScope !== null ? [' AND ' . $col . ' = ?', [$tenantScope]] : ['', []];

        $p = [];
        $byUser = function (string $table, string $label, string $col = 'user_id', ?string $date = null, string $mode = 'delete') use (&$p, $users, $in, $tw): void {
            if ($users === []) {
                return;
            }
            [$t, $tp] = $tw();
            $p[] = ['table' => $table, 'label' => $label, 'mode' => $mode, 'where' => "{$col} IN (" . $in($users) . "){$t}", 'params' => array_merge($users, $tp), 'date' => $date];
        };
        $bySteam = function (string $table, string $label, string $col, ?string $date = null, string $mode = 'delete', ?string $tenantCol = 'tenant_id') use (&$p, $steams, $in, $tenantScope): void {
            if ($steams === []) {
                return;
            }
            $t = $tenantScope !== null && $tenantCol !== null ? " AND {$tenantCol} = ?" : '';
            $p[] = ['table' => $table, 'label' => $label, 'mode' => $mode, 'where' => "{$col} IN (" . $in($steams) . "){$t}", 'params' => array_merge($steams, $t !== '' ? [$tenantScope] : []), 'date' => $date];
        };

        // Liaison jeu
        $byUser('game_sessions', 'Sessions de jeu', 'user_id', 'created_at');
        $bySteam('game_sessions', 'Sessions de jeu (par Steam)', 'steam_id', 'created_at');
        $bySteam('game_device_pairings', 'Appairages du jeu', 'steam_id', 'created_at', 'delete', null);
        $bySteam('game_atak_pair_challenges', 'Demandes de liaison', 'steam_id', 'created_at', 'delete', null);
        $byUser('tactical_game_link_codes', 'Codes de liaison', 'user_id', 'created_at');
        // ATAK
        $byUser('atak_certificates', 'Certificats ATAK', 'user_id', 'created_at');
        $byUser('atak_trusted_devices', 'Appareils de confiance', 'user_id', 'created_at');
        $byUser('atak_terminals', 'Terminaux ATAK', 'user_id', 'last_seen_at');
        $byUser('atak_operator_ids', 'Identifiants opérateur', 'user_id', 'updated_at');
        $byUser('game_phone_identities', 'Téléphone en jeu', 'user_id', 'updated_at');
        // Unités sur la carte (BFT) : Steam dans extra, ou indicatif sans Steam dans les communautés du joueur.
        if ($steams !== [] || ($callsigns !== [] && $tenants !== [])) {
            $parts = [];
            $params = [];
            if ($steams !== []) {
                $parts[] = "JSON_UNQUOTE(JSON_EXTRACT(extra, '$.steam_uid')) IN (" . $in($steams) . ')';
                array_push($params, ...$steams);
            }
            if ($callsigns !== [] && $tenants !== []) {
                $parts[] = '(call_sign IN (' . $in($callsigns) . ') AND tenant_id IN (' . $in($tenants) . ")
                    AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(extra, '$.steam_uid')), '') IN ('', 'null'))";
                array_push($params, ...$callsigns, ...$tenants);
            }
            [$t, $tp] = $tw();
            $p[] = ['table' => 'atak_units', 'label' => 'Unités sur la carte (BFT)', 'mode' => 'delete', 'where' => '(' . implode(' OR ', $parts) . ')' . $t, 'params' => array_merge($params, $tp), 'date' => 'updated_at'];
        }
        // Registre opérateur, mods, statistiques
        $byUser('operator_game_profiles', 'Profils opérateur (jeu)', 'user_id', 'last_seen_at');
        $bySteam('operator_game_profiles', 'Profils opérateur (par Steam)', 'steam_id', 'last_seen_at');
        $bySteam('operator_game_events', 'Événements du registre opérateur', 'steam_id', 'occurred_at');
        $bySteam('game_player_mods', 'Mods chargés', 'steam_id', 'last_seen');
        $bySteam('game_item_usage', 'Équipement utilisé', 'steam_id', 'last_seen');
        $bySteam('tenant_game_server_presence', 'Présence sur les serveurs', 'steam_uid', 'seen_at');
        $bySteam('atak_send_queue_snapshots', 'Files d’envoi COMSPEC Link', 'steam_uid', 'received_at');
        $bySteam('atak_send_queue_history', 'Historique des files d’envoi', 'steam_uid', 'received_at');
        $bySteam('atak_ingest_player_daily', 'Débit par joueur', 'steam_uid', 'last_at');
        $bySteam('atak_device_logs', 'Journal de l’appareil', 'steam_uid', 'logged_at');
        $bySteam('atak_mod_reports', 'Rapports d’erreurs du mod', 'steam_uid', 'last_seen_at', 'delete', null);
        $byUser('user_arma_playtime', 'Temps de jeu Arma', 'user_id', 'updated_at');
        $byUser('atak_screen_time', 'Temps d’écran ATAK', 'user_id', 'day');
        $byUser('atak_distance_stats', 'Distances parcourues', 'user_id', 'day');
        $byUser('atak_pilot_time', 'Heures de vol', 'user_id', null);
        // Identité Steam portée par les fiches et comptes
        if ($steams !== []) {
            [$t, $tp] = $tw();
            $p[] = ['table' => 'users', 'label' => 'Steam sur les fiches', 'mode' => 'null', 'set' => 'steam_id = NULL', 'where' => 'steam_id IN (' . $in($steams) . ')' . $t, 'params' => array_merge($steams, $tp), 'date' => null];
            if ($tenantScope === null) {
                $p[] = ['table' => 'athena_accounts', 'label' => 'Steam sur les comptes Athena', 'mode' => 'null', 'set' => 'steam_id = NULL', 'where' => 'steam_id IN (' . $in($steams) . ')', 'params' => $steams, 'date' => null];
            }
        }
        // Historique conservé (attribué au compte anonymisé)
        $byUser('atak_tactical_reports', 'Comptes rendus (conservés)', 'submitter_user_id', 'created_at', 'keep');
        $byUser('sse_field_notes', 'Fiches SSE (conservées)', 'author_user_id', 'created_at', 'keep');
        $byUser('atak_admin_actions', 'Journal des actions ATAK (conservé)', 'user_id', 'created_at', 'keep');

        return array_values(array_filter($p, fn (array $row): bool => $this->usable($row)));
    }

    /**
     * Compte les lignes de chaque étape du plan (corrélations).
     *
     * @param list<array<string, mixed>> $plan
     * @return list<array{table: string, label: string, count: int, last: ?string, purge: bool}>
     */
    public function count(array $plan): array
    {
        $out = [];
        foreach ($plan as $s) {
            $date = $s['date'] !== null && $this->hasColumn($s['table'], (string) $s['date']) ? $s['date'] : null;
            $r = $this->q($s['table'], 'SELECT COUNT(*) n' . ($date !== null ? ", MAX({$date}) last" : '') . " FROM {$s['table']} WHERE {$s['where']}", $s['params'])[0] ?? [];
            $n = (int) ($r['n'] ?? 0);
            if ($n > 0) {
                $out[] = ['table' => $s['table'], 'label' => $s['label'], 'count' => $n, 'last' => isset($r['last']) ? (string) $r['last'] : null, 'purge' => $s['mode'] !== 'keep'];
            }
        }

        return $out;
    }

    /**
     * Exécute le plan (hors étapes « keep »).
     *
     * @param list<array<string, mixed>> $plan
     * @return array<string, int> lignes touchées par table
     */
    public function execute(array $plan): array
    {
        $done = [];
        foreach ($plan as $s) {
            if ($s['mode'] === 'keep') {
                continue;
            }
            try {
                if ($s['table'] === 'atak_terminals') {
                    // Les certificats pointent sur le terminal sans clé étrangère.
                    if ($this->usable(['table' => 'atak_certificates'])) {
                        $this->pdo()->prepare("DELETE FROM atak_certificates WHERE terminal_id IN (SELECT id FROM (SELECT id FROM atak_terminals WHERE {$s['where']}) x)")->execute($s['params']);
                    }
                }
                $sql = $s['mode'] === 'null'
                    ? "UPDATE {$s['table']} SET {$s['set']} WHERE {$s['where']}"
                    : "DELETE FROM {$s['table']} WHERE {$s['where']}";
                $st = $this->pdo()->prepare($sql);
                $st->execute($s['params']);
                $done[$s['table']] = ($done[$s['table']] ?? 0) + $st->rowCount();
            } catch (\Throwable $e) {
                error_log('[player-trace-purge] ' . $s['table'] . ' : ' . mb_substr($e->getMessage(), 0, 200));
            }
        }

        return array_filter($done);
    }

    // ================================================================= raccourcis

    /**
     * Toutes les traces de jeu d'un compte. $tenantScope : seulement dans cette communauté.
     *
     * @return array<string, int>
     */
    public function purgeUser(int $userId, ?int $tenantScope = null): array
    {
        if ($userId < 1) {
            return [];
        }

        return $this->execute($this->plan($this->identityForUser($userId), $tenantScope));
    }

    /**
     * Toutes les traces d'un identifiant Steam (joueur sans compte, ou compte déjà purgé).
     *
     * @return array<string, int>
     */
    public function purgeSteam(string $steam, ?int $tenantScope = null): array
    {
        $id = $this->identityForSteam($steam);

        // Les fiches qui portent ce Steam restent : seule leur liaison Steam est retirée.
        return $this->execute($this->plan($id, $tenantScope, false));
    }

    /** Somme lisible d'un résultat d'exécution. */
    public static function summary(array $done): string
    {
        if ($done === []) {
            return 'aucune trace trouvée';
        }
        $n = array_sum($done);

        return $n . ' ligne(s) dans ' . count($done) . ' table(s)';
    }

    // ================================================================= utilitaires

    /** @param array<string, mixed> $step */
    private function usable(array $step): bool
    {
        $t = (string) $step['table'];
        if (!$this->tableExists($t)) {
            return false;
        }
        if (isset($step['where']) && preg_match_all('/\b([a-z_]+)\s+(?:IN|=)\s/', (string) $step['where'], $m)) {
            foreach (array_unique($m[1]) as $col) {
                if (in_array($col, ['AND', 'OR'], true)) {
                    continue;
                }
                if (!$this->hasColumn($t, $col)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function tableExists(string $table): bool
    {
        if (!isset($this->tables[$table])) {
            try {
                $st = $this->pdo()->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
                $st->execute([$table]);
                $this->tables[$table] = (bool) $st->fetchColumn();
            } catch (\Throwable) {
                $this->tables[$table] = false;
            }
        }

        return $this->tables[$table];
    }

    private function hasColumn(string $table, string $column): bool
    {
        if (!isset($this->columns[$table])) {
            $this->columns[$table] = [];
            try {
                $st = $this->pdo()->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
                $st->execute([$table]);
                foreach ($st->fetchAll(PDO::FETCH_COLUMN) ?: [] as $c) {
                    $this->columns[$table][(string) $c] = true;
                }
            } catch (\Throwable) {
            }
        }

        return isset($this->columns[$table][$column]);
    }

    /**
     * @param list<mixed> $params
     * @return list<array<string, mixed>>
     */
    private function q(string $table, string $sql, array $params): array
    {
        if (!$this->tableExists($table)) {
            return [];
        }
        try {
            $st = $this->pdo()->prepare($sql);
            $st->execute($params);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param list<string> $v
     * @return list<string>
     */
    public static function cleanSteams(array $v): array
    {
        $out = [];
        foreach ($v as $s) {
            $n = SteamId::normalize((string) $s);
            if ($n !== null) {
                $out[$n] = true;
            }
        }

        // array_keys rendrait les SteamID64 (chaînes numériques) en entiers.
        return array_map('strval', array_keys($out));
    }

    /**
     * @param list<string> $v
     * @return list<string>
     */
    public static function cleanStrings(array $v): array
    {
        $out = [];
        foreach ($v as $s) {
            $s = trim((string) $s);
            if ($s !== '' && mb_strlen($s) <= 255) {
                $out[$s] = true;
            }
        }

        return array_map('strval', array_keys($out));
    }
}
