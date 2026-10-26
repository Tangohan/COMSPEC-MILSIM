<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Database;
use App\Repositories\AtakAdminControlRepository;
use App\Services\Cron\Jobs\PlayerDataRetentionCronJob;
use App\Support\SteamId;
use PDO;

/**
 * Registre des données de la plateforme (/admin/system/donnees) : jeux de données façon tableur,
 * corrélations d'une entité et actions de purge.
 *
 * Chaque jeu de données est une requête de base (sous-requête « x ») avec des colonnes typées, des
 * filtres rapides (« flags »), la recherche, le tri et la pagination côté serveur. Les tables absentes
 * d'une installation sont remplacées par des valeurs vides plutôt que de casser la requête.
 */
final class DataRegistryService
{
    public const DATASETS = [
        'users' => 'Comptes',
        'players' => 'Joueurs (Steam)',
        'terminals' => 'ATAK',
        'units' => 'Unités sur la carte',
        'sessions' => 'Sessions de jeu',
        'tenants' => 'Communautés',
        'cleanup' => 'Nettoyage',
    ];

    /** @var array<string, bool> */
    private array $tables = [];

    public function __construct(private ?PDO $pdo = null, private ?PlayerTracePurgeService $purge = null)
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

    private function purger(): PlayerTracePurgeService
    {
        return $this->purge ??= new PlayerTracePurgeService($this->pdo());
    }

    public function has(string $table): bool
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

    /** Sous-requête scalaire si la table existe, sinon la valeur de repli. */
    private function sub(string $table, string $sql, string $fallback = '0'): string
    {
        return $this->has($table) ? '(' . $sql . ')' : $fallback;
    }

    // ================================================================= définitions

    /**
     * @return array<string, mixed>
     */
    private function definition(string $dataset): array
    {
        return match ($dataset) {
            'users' => $this->defUsers(),
            'players' => $this->defPlayers(),
            'terminals' => $this->defTerminals(),
            'units' => $this->defUnits(),
            'sessions' => $this->defSessions(),
            'tenants' => $this->defTenants(),
            default => throw new \InvalidArgumentException('unknown_dataset'),
        };
    }

    private function defUsers(): array
    {
        $terminals = $this->sub('atak_terminals', 'SELECT COUNT(*) FROM atak_terminals a WHERE a.user_id = u.id');
        $profiles = $this->sub('operator_game_profiles', 'SELECT COUNT(*) FROM operator_game_profiles o WHERE o.user_id = u.id');
        $lastGame = $this->sub('operator_game_profiles', 'SELECT MAX(o.last_seen_at) FROM operator_game_profiles o WHERE o.user_id = u.id', 'NULL');
        $sessions = $this->sub('game_sessions', 'SELECT COUNT(*) FROM game_sessions g WHERE g.user_id = u.id AND g.revoked_at IS NULL AND g.refresh_expires_at > UTC_TIMESTAMP()');
        $dupes = "(SELECT COUNT(*) FROM users d WHERE d.steam_id = u.steam_id AND d.id <> u.id AND d.deleted_at IS NULL)";

        return [
            'sql' => "SELECT u.id, COALESCE(NULLIF(u.display_name, ''), NULLIF(u.callsign, ''), u.email) AS label, u.email, u.callsign,
                    u.tenant_id, t.name AS tenant_name, u.status, u.steam_id, u.created_at, u.last_login_at, u.deleted_at,
                    {$terminals} AS terminals, {$profiles} AS profiles, {$sessions} AS sessions, {$lastGame} AS last_game,
                    IF(u.steam_id IS NULL OR u.steam_id = '', 0, {$dupes}) AS steam_dupes
                FROM users u LEFT JOIN tenants t ON t.id = u.tenant_id",
            'id' => 'id',
            'search' => ['label', 'email', 'callsign', 'steam_id', 'tenant_name'],
            'sort' => 'id',
            'columns' => [
                ['key' => 'id', 'label' => 'ID', 'type' => 'num'],
                ['key' => 'label', 'label' => 'Compte', 'type' => 'entity'],
                ['key' => 'email', 'label' => 'E-mail'],
                ['key' => 'tenant_name', 'label' => 'Communauté', 'type' => 'entity'],
                ['key' => 'status', 'label' => 'Statut', 'options' => $this->distinct('users', 'status')],
                ['key' => 'steam_id', 'label' => 'Steam', 'type' => 'entity'],
                ['key' => 'terminals', 'label' => 'ATAK', 'type' => 'num'],
                ['key' => 'sessions', 'label' => 'Sessions actives', 'type' => 'num'],
                ['key' => 'profiles', 'label' => 'Profils jeu', 'type' => 'num'],
                ['key' => 'last_game', 'label' => 'Vu en jeu', 'type' => 'date'],
                ['key' => 'last_login_at', 'label' => 'Dernière connexion', 'type' => 'date'],
                ['key' => 'created_at', 'label' => 'Créé', 'type' => 'date'],
                ['key' => 'flags', 'label' => 'Signaux', 'type' => 'tags', 'filter' => false, 'sortable' => false],
            ],
            'flags' => [
                'ghost' => ['Supprimés avec traces de jeu', "deleted_at IS NOT NULL AND (terminals > 0 OR profiles > 0 OR sessions > 0)", 'bad', 'Comptes supprimés dont le joueur apparaît encore (carte, ATAK, registre)'],
                'dupe' => ['Steam en double', 'steam_dupes > 0', 'warn', 'Le même Steam est porté par plusieurs fiches vivantes'],
                'deleted' => ['Supprimés / anonymisés', 'deleted_at IS NOT NULL', 'neut', ''],
                'linked' => ['Steam lié', "steam_id IS NOT NULL AND steam_id <> '' AND deleted_at IS NULL", 'ok', ''],
                'never' => ['Liés, jamais vus en jeu', "steam_id IS NOT NULL AND steam_id <> '' AND last_game IS NULL AND deleted_at IS NULL", 'neut', ''],
            ],
            'actions' => [
                ['key' => 'purge_traces', 'label' => 'Effacer les traces de jeu', 'danger' => true, 'confirm' => 'Carte, ATAK, sessions, registre, mods et statistiques de ces comptes seront effacés. Les comptes eux-mêmes restent.'],
                ['key' => 'unlink_steam', 'label' => 'Délier le Steam', 'confirm' => 'Le Steam est retiré des fiches et les sessions de jeu sont fermées.'],
            ],
            'post' => function (array $r): array {
                $r['_corr'] = ['type' => 'user', 'id' => $r['id']];
                $r['_ghost'] = $r['deleted_at'] !== null && ((int) $r['terminals'] + (int) $r['profiles'] + (int) $r['sessions']) > 0;
                $r['label'] = ['type' => 'user', 'id' => $r['id'], 'label' => (string) $r['label'], 'sub' => (string) ($r['callsign'] ?? '')];
                $r['tenant_name'] = $r['tenant_id'] ? ['type' => 'tenant', 'id' => $r['tenant_id'], 'label' => (string) ($r['tenant_name'] ?? ('#' . $r['tenant_id']))] : null;
                $r['steam_id'] = $r['steam_id'] ? ['type' => 'steam', 'id' => $r['steam_id'], 'label' => (string) $r['steam_id']] : null;
                $tags = [];
                if ($r['deleted_at'] !== null) {
                    $tags[] = ['label' => 'Supprimé', 'tone' => 'neut', 'title' => 'Anonymisé le ' . $r['deleted_at']];
                }
                if ($r['_ghost']) {
                    $tags[] = ['label' => 'Traces de jeu', 'tone' => 'bad'];
                }
                if ((int) $r['steam_dupes'] > 0) {
                    $tags[] = ['label' => 'Steam ×' . ((int) $r['steam_dupes'] + 1), 'tone' => 'warn'];
                }
                $r['flags'] = $tags;

                return $r;
            },
        ];
    }

    private function defPlayers(): array
    {
        $parts = [];
        $add = function (string $table, string $steamCol, string $dateCol, string $src, string $cs = 'NULL', string $tenant = 'tenant_id') use (&$parts): void {
            if ($this->has($table)) {
                // Agrégé par Steam et communauté : certaines tables ont une ligne par mod ou par jour.
                // Collation imposée : les tables n'ont pas toutes la même (UNION et comparaisons avec users).
                $cv = static fn (string $e): string => $e === 'NULL' ? 'NULL' : "CONVERT({$e} USING utf8mb4) COLLATE utf8mb4_unicode_ci";
                $group = $tenant === 'NULL' ? $steamCol : "{$steamCol}, {$tenant}";
                $parts[] = 'SELECT ' . $cv($steamCol) . " AS steam, MAX({$dateCol}) AS seen, '{$src}' AS src, " . ($cs === 'NULL' ? 'NULL' : 'MAX(' . $cv($cs) . ')') . " AS cs, {$tenant} AS tid
                    FROM {$table} WHERE {$steamCol} IS NOT NULL AND {$steamCol} <> '' GROUP BY {$group}";
            }
        };
        $add('users', 'steam_id', 'last_login_at', 'fiche', 'callsign');
        $add('operator_game_profiles', 'steam_id', 'last_seen_at', 'profil', 'callsign');
        $add('game_sessions', 'steam_id', 'COALESCE(last_seen_at, created_at)', 'session');
        $add('game_player_mods', 'steam_id', 'last_seen', 'mods');
        $add('atak_send_queue_snapshots', 'steam_uid', 'received_at', 'file', 'call_sign');
        $add('atak_ingest_player_daily', 'steam_uid', 'last_at', 'débit', 'callsign');
        $add('atak_mod_reports', 'steam_uid', 'last_seen_at', 'incident', 'callsign', 'NULL');
        if ($parts === []) {
            $parts[] = "SELECT NULL steam, NULL seen, NULL src, NULL cs, NULL tid FROM DUAL WHERE 0";
        }
        $live = "(SELECT COUNT(*) FROM users lu WHERE lu.steam_id = s.steam AND lu.deleted_at IS NULL AND COALESCE(lu.status, '') NOT IN ('merged', 'deleted'))";
        $names = "(SELECT GROUP_CONCAT(DISTINCT COALESCE(NULLIF(lu.display_name, ''), lu.email) SEPARATOR ', ') FROM users lu WHERE lu.steam_id = s.steam AND lu.deleted_at IS NULL)";

        return [
            'sql' => "SELECT s.steam, MAX(s.seen) AS last_seen, GROUP_CONCAT(DISTINCT s.src ORDER BY s.src SEPARATOR ', ') AS sources,
                    COUNT(DISTINCT s.src) AS source_count, SUBSTRING_INDEX(GROUP_CONCAT(DISTINCT s.cs ORDER BY s.cs SEPARATOR '|'), '|', 3) AS callsigns,
                    COUNT(DISTINCT s.tid) AS tenants, {$live} AS live_users, {$names} AS accounts
                FROM (" . implode(' UNION ALL ', $parts) . ") s GROUP BY s.steam",
            'id' => 'steam',
            'search' => ['steam', 'callsigns', 'accounts'],
            'sort' => 'last_seen',
            'columns' => [
                ['key' => 'steam', 'label' => 'Steam', 'type' => 'entity'],
                ['key' => 'callsigns', 'label' => 'Indicatifs'],
                ['key' => 'accounts', 'label' => 'Comptes vivants'],
                ['key' => 'live_users', 'label' => 'Nb comptes', 'type' => 'num'],
                ['key' => 'tenants', 'label' => 'Communautés', 'type' => 'num'],
                ['key' => 'sources', 'label' => 'Présent dans'],
                ['key' => 'last_seen', 'label' => 'Dernière trace', 'type' => 'date'],
                ['key' => 'flags', 'label' => 'Signaux', 'type' => 'tags', 'filter' => false, 'sortable' => false],
            ],
            'flags' => [
                'orphan' => ['Sans compte (fantômes)', 'live_users = 0', 'bad', 'Joueur présent dans les données de jeu mais rattaché à aucun compte vivant'],
                'multi' => ['Plusieurs comptes', 'live_users > 1', 'warn', 'Le même Steam est lié à plusieurs fiches'],
                'stale' => ['Inactifs depuis 90 jours', 'last_seen < DATE_SUB(NOW(), INTERVAL 90 DAY)', 'neut', ''],
            ],
            'actions' => [
                ['key' => 'purge_steam', 'label' => 'Effacer toutes les traces', 'danger' => true, 'confirm' => 'Toutes les données de jeu de ces Steam seront effacées et leur liaison retirée des fiches. Le mod recréera le joueur s’il rejoue (bloquez-le pour l’empêcher).'],
                ['key' => 'block_steam', 'label' => 'Bloquer sur toute la plateforme', 'danger' => true, 'confirm' => 'Ces Steam ne pourront plus envoyer de données de jeu à Athena.'],
            ],
            'post' => function (array $r): array {
                $r['_id'] = $r['steam'];
                $r['_corr'] = ['type' => 'steam', 'id' => $r['steam']];
                $r['_ghost'] = (int) $r['live_users'] === 0;
                $r['steam'] = ['type' => 'steam', 'id' => $r['steam'], 'label' => (string) $r['steam']];
                $r['callsigns'] = str_replace('|', ', ', (string) ($r['callsigns'] ?? ''));
                $tags = [];
                if ((int) $r['live_users'] === 0) {
                    $tags[] = ['label' => 'Fantôme', 'tone' => 'bad', 'title' => 'Aucun compte vivant'];
                } elseif ((int) $r['live_users'] > 1) {
                    $tags[] = ['label' => $r['live_users'] . ' comptes', 'tone' => 'warn'];
                }
                $r['flags'] = $tags;

                return $r;
            },
        ];
    }

    private function defTerminals(): array
    {
        if (!$this->has('atak_terminals')) {
            return $this->emptyDef();
        }

        return [
            'sql' => "SELECT a.id, a.tenant_id, t.name AS tenant_name, a.terminal_uid, a.terminal_label, a.terminal_type, a.status,
                    a.compromise_state, a.operator_callsign, a.user_id, COALESCE(NULLIF(u.display_name, ''), u.email) AS user_label,
                    u.deleted_at AS user_deleted, a.mod_version, a.extension_version, a.first_seen_at, a.last_seen_at
                FROM atak_terminals a LEFT JOIN users u ON u.id = a.user_id LEFT JOIN tenants t ON t.id = a.tenant_id",
            'id' => 'id',
            'search' => ['terminal_uid', 'terminal_label', 'operator_callsign', 'user_label', 'tenant_name'],
            'sort' => 'last_seen_at',
            'columns' => [
                ['key' => 'terminal_label', 'label' => 'ATAK', 'type' => 'entity'],
                ['key' => 'terminal_uid', 'label' => 'Identifiant', 'type' => 'mono'],
                ['key' => 'terminal_type', 'label' => 'Type', 'options' => $this->distinct('atak_terminals', 'terminal_type')],
                ['key' => 'tenant_name', 'label' => 'Communauté', 'type' => 'entity'],
                ['key' => 'user_label', 'label' => 'Compte', 'type' => 'entity'],
                ['key' => 'operator_callsign', 'label' => 'Indicatif'],
                ['key' => 'status', 'label' => 'Statut', 'options' => $this->distinct('atak_terminals', 'status')],
                ['key' => 'compromise_state', 'label' => 'Compromission', 'options' => $this->distinct('atak_terminals', 'compromise_state')],
                ['key' => 'mod_version', 'label' => 'Mod'],
                ['key' => 'extension_version', 'label' => 'Link'],
                ['key' => 'last_seen_at', 'label' => 'Dernier contact', 'type' => 'date'],
                ['key' => 'first_seen_at', 'label' => 'Mise en service', 'type' => 'date'],
                ['key' => 'flags', 'label' => 'Signaux', 'type' => 'tags', 'filter' => false, 'sortable' => false],
            ],
            'flags' => [
                'ghost' => ['Compte supprimé', 'user_deleted IS NOT NULL', 'bad', ''],
                'orphan' => ['Sans compte', 'user_id IS NULL', 'warn', ''],
                'never' => ['Jamais vus', 'last_seen_at IS NULL', 'neut', ''],
                'stale' => ['Muets depuis 30 jours', 'last_seen_at < DATE_SUB(NOW(), INTERVAL 30 DAY)', 'neut', ''],
                'compromised' => ['Compromis', "compromise_state NOT IN ('none', '')", 'bad', ''],
            ],
            'actions' => [
                ['key' => 'retire_terminal', 'label' => 'Retirer (révoque et supprime)', 'danger' => true, 'confirm' => 'Certificats et appareils de confiance révoqués, sessions fermées, fiche supprimée.'],
            ],
            'post' => function (array $r): array {
                $r['_corr'] = ['type' => 'terminal', 'id' => $r['id']];
                $r['_ghost'] = $r['user_deleted'] !== null || $r['user_id'] === null;
                $r['terminal_label'] = ['type' => 'terminal', 'id' => $r['id'], 'label' => (string) ($r['terminal_label'] ?: $r['terminal_uid'])];
                $r['tenant_name'] = ['type' => 'tenant', 'id' => $r['tenant_id'], 'label' => (string) ($r['tenant_name'] ?? '#' . $r['tenant_id'])];
                $r['user_label'] = $r['user_id'] ? ['type' => 'user', 'id' => $r['user_id'], 'label' => (string) ($r['user_label'] ?? '#' . $r['user_id']), 'sub' => $r['user_deleted'] ? 'supprimé' : ''] : null;
                $tags = [];
                if ($r['user_deleted'] !== null) {
                    $tags[] = ['label' => 'Compte supprimé', 'tone' => 'bad'];
                } elseif ($r['user_id'] === null) {
                    $tags[] = ['label' => 'Sans compte', 'tone' => 'warn'];
                }
                if (!in_array((string) $r['compromise_state'], ['none', ''], true)) {
                    $tags[] = ['label' => (string) $r['compromise_state'], 'tone' => 'bad'];
                }
                $r['flags'] = $tags;

                return $r;
            },
        ];
    }

    private function defUnits(): array
    {
        if (!$this->has('atak_units')) {
            return $this->emptyDef();
        }
        $steam = "NULLIF(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(a.extra, '$.steam_uid')), 'null'), '')";
        $owner = "(SELECT COUNT(*) FROM users lu WHERE lu.tenant_id = a.tenant_id AND lu.steam_id = {$steam} AND lu.deleted_at IS NULL)";

        return [
            'sql' => "SELECT a.id, a.tenant_id, t.name AS tenant_name, a.map_id, a.call_sign, a.role, a.group_name, a.status,
                    {$steam} AS steam, a.updated_at, TIMESTAMPDIFF(HOUR, a.updated_at, NOW()) AS idle_h, {$owner} AS owners
                FROM atak_units a LEFT JOIN tenants t ON t.id = a.tenant_id",
            'id' => 'id',
            'search' => ['call_sign', 'steam', 'tenant_name', 'group_name'],
            'sort' => 'updated_at',
            'columns' => [
                ['key' => 'call_sign', 'label' => 'Indicatif'],
                ['key' => 'tenant_name', 'label' => 'Communauté', 'type' => 'entity'],
                ['key' => 'map_id', 'label' => 'Carte', 'type' => 'num'],
                ['key' => 'group_name', 'label' => 'Groupe'],
                ['key' => 'role', 'label' => 'Rôle'],
                ['key' => 'status', 'label' => 'Statut', 'options' => $this->distinct('atak_units', 'status')],
                ['key' => 'steam', 'label' => 'Steam', 'type' => 'entity'],
                ['key' => 'updated_at', 'label' => 'Dernière position', 'type' => 'date'],
                ['key' => 'idle_h', 'label' => 'Inactive (h)', 'type' => 'num'],
                ['key' => 'flags', 'label' => 'Signaux', 'type' => 'tags', 'filter' => false, 'sortable' => false],
            ],
            'flags' => [
                'ghost' => ['Steam sans compte', 'steam IS NOT NULL AND owners = 0', 'bad', 'Unité d’un joueur qui n’a plus de compte dans cette communauté'],
                'stale1' => ['Muettes depuis 24 h', 'idle_h >= 24', 'warn', ''],
                'stale7' => ['Muettes depuis 7 jours', 'idle_h >= 168', 'bad', ''],
                'nosteam' => ['Sans Steam', 'steam IS NULL', 'neut', 'IA, véhicule ou ancien mod'],
            ],
            'actions' => [
                ['key' => 'delete_unit', 'label' => 'Retirer de la carte', 'danger' => true, 'confirm' => 'Les unités disparaissent de la carte. Un joueur encore en jeu réapparaîtra à sa prochaine position.'],
            ],
            'post' => function (array $r): array {
                $r['_corr'] = $r['steam'] ? ['type' => 'steam', 'id' => $r['steam']] : null;
                $r['_ghost'] = $r['steam'] !== null && (int) $r['owners'] === 0;
                $r['tenant_name'] = ['type' => 'tenant', 'id' => $r['tenant_id'], 'label' => (string) ($r['tenant_name'] ?? '#' . $r['tenant_id'])];
                $r['steam'] = $r['steam'] ? ['type' => 'steam', 'id' => $r['steam'], 'label' => (string) $r['steam']] : null;
                $tags = [];
                if ($r['_ghost']) {
                    $tags[] = ['label' => 'Sans compte', 'tone' => 'bad'];
                }
                if ((int) $r['idle_h'] >= 168) {
                    $tags[] = ['label' => 'Muette ' . intdiv((int) $r['idle_h'], 24) . ' j', 'tone' => 'warn'];
                }
                $r['flags'] = $tags;

                return $r;
            },
        ];
    }

    private function defSessions(): array
    {
        if (!$this->has('game_sessions')) {
            return $this->emptyDef();
        }

        return [
            'sql' => "SELECT g.id, g.user_id, COALESCE(NULLIF(u.display_name, ''), u.email) AS user_label, u.deleted_at AS user_deleted,
                    g.tenant_id, t.name AS tenant_name, g.steam_id, g.device_id, g.mod_version, g.extension_version,
                    g.created_at, g.last_seen_at, g.refresh_expires_at, g.revoked_at,
                    CASE WHEN g.revoked_at IS NOT NULL THEN 'révoquée' WHEN g.refresh_expires_at <= UTC_TIMESTAMP() THEN 'expirée' ELSE 'active' END AS state
                FROM game_sessions g LEFT JOIN users u ON u.id = g.user_id LEFT JOIN tenants t ON t.id = g.tenant_id",
            'id' => 'id',
            'search' => ['user_label', 'steam_id', 'device_id', 'tenant_name'],
            'sort' => 'id',
            'columns' => [
                ['key' => 'id', 'label' => 'ID', 'type' => 'num'],
                ['key' => 'user_label', 'label' => 'Compte', 'type' => 'entity'],
                ['key' => 'tenant_name', 'label' => 'Communauté', 'type' => 'entity'],
                ['key' => 'steam_id', 'label' => 'Steam', 'type' => 'entity'],
                ['key' => 'state', 'label' => 'État', 'options' => [['value' => 'active', 'label' => 'active'], ['value' => 'révoquée', 'label' => 'révoquée'], ['value' => 'expirée', 'label' => 'expirée']]],
                ['key' => 'mod_version', 'label' => 'Mod'],
                ['key' => 'extension_version', 'label' => 'Link'],
                ['key' => 'device_id', 'label' => 'Appareil', 'type' => 'mono'],
                ['key' => 'created_at', 'label' => 'Ouverte', 'type' => 'date'],
                ['key' => 'last_seen_at', 'label' => 'Dernier usage', 'type' => 'date'],
                ['key' => 'revoked_at', 'label' => 'Révoquée', 'type' => 'date'],
            ],
            'flags' => [
                'active' => ['Actives', "state = 'active'", 'ok', ''],
                'ghost' => ['Compte supprimé', 'user_deleted IS NOT NULL', 'bad', ''],
                'dead' => ['Révoquées ou expirées', "state <> 'active'", 'neut', ''],
            ],
            'actions' => [
                ['key' => 'revoke_session', 'label' => 'Révoquer', 'confirm' => 'Le jeu devra se reconnecter.'],
                ['key' => 'delete_session', 'label' => 'Supprimer', 'danger' => true, 'confirm' => 'Les sessions sont effacées.'],
            ],
            'post' => function (array $r): array {
                $r['_corr'] = $r['user_id'] ? ['type' => 'user', 'id' => $r['user_id']] : null;
                $r['_ghost'] = $r['user_deleted'] !== null;
                $r['user_label'] = $r['user_id'] ? ['type' => 'user', 'id' => $r['user_id'], 'label' => (string) ($r['user_label'] ?? '#' . $r['user_id']), 'sub' => $r['user_deleted'] ? 'supprimé' : ''] : null;
                $r['tenant_name'] = ['type' => 'tenant', 'id' => $r['tenant_id'], 'label' => (string) ($r['tenant_name'] ?? '#' . $r['tenant_id'])];
                $r['steam_id'] = $r['steam_id'] ? ['type' => 'steam', 'id' => $r['steam_id'], 'label' => (string) $r['steam_id']] : null;

                return $r;
            },
        ];
    }

    private function defTenants(): array
    {
        $members = "(SELECT COUNT(*) FROM users u WHERE u.tenant_id = t.id AND u.deleted_at IS NULL)";
        $deleted = "(SELECT COUNT(*) FROM users u WHERE u.tenant_id = t.id AND u.deleted_at IS NOT NULL)";
        $linked = "(SELECT COUNT(*) FROM users u WHERE u.tenant_id = t.id AND u.deleted_at IS NULL AND u.steam_id IS NOT NULL AND u.steam_id <> '')";

        return [
            'sql' => "SELECT t.id, t.name, t.slug, t.tenant_type, t.created_at, {$members} AS members, {$deleted} AS deleted_members, {$linked} AS steam_linked,
                    " . $this->sub('atak_terminals', 'SELECT COUNT(*) FROM atak_terminals a WHERE a.tenant_id = t.id') . " AS terminals,
                    " . $this->sub('atak_units', 'SELECT COUNT(*) FROM atak_units a WHERE a.tenant_id = t.id') . " AS units,
                    " . $this->sub('operator_game_profiles', 'SELECT COUNT(*) FROM operator_game_profiles o WHERE o.tenant_id = t.id') . " AS profiles,
                    " . $this->sub('atak_ingest_traffic', 'SELECT MAX(x.bucket_ts) FROM atak_ingest_traffic x WHERE x.tenant_id = t.id', 'NULL') . " AS last_game
                FROM tenants t",
            'id' => 'id',
            'search' => ['name', 'slug'],
            'sort' => 'members',
            'columns' => [
                ['key' => 'id', 'label' => 'ID', 'type' => 'num'],
                ['key' => 'name', 'label' => 'Communauté', 'type' => 'entity'],
                ['key' => 'slug', 'label' => 'Adresse', 'type' => 'mono'],
                ['key' => 'tenant_type', 'label' => 'Type', 'options' => $this->distinct('tenants', 'tenant_type')],
                ['key' => 'members', 'label' => 'Membres', 'type' => 'num'],
                ['key' => 'deleted_members', 'label' => 'Supprimés', 'type' => 'num'],
                ['key' => 'steam_linked', 'label' => 'Steam liés', 'type' => 'num'],
                ['key' => 'terminals', 'label' => 'ATAK', 'type' => 'num'],
                ['key' => 'units', 'label' => 'Unités carte', 'type' => 'num'],
                ['key' => 'profiles', 'label' => 'Profils jeu', 'type' => 'num'],
                ['key' => 'last_game', 'label' => 'Dernière activité jeu', 'type' => 'date'],
                ['key' => 'created_at', 'label' => 'Créée', 'type' => 'date'],
            ],
            'flags' => [
                'game' => ['Actives en jeu (30 j)', 'last_game >= DATE_SUB(NOW(), INTERVAL 30 DAY)', 'ok', ''],
                'dormant' => ['Sans jeu depuis 30 j', 'last_game IS NULL OR last_game < DATE_SUB(NOW(), INTERVAL 30 DAY)', 'neut', ''],
                'empty' => ['Sans membre', 'members = 0', 'warn', ''],
            ],
            'actions' => [
                ['key' => 'tenant_ghosts', 'label' => 'Nettoyer les fantômes', 'confirm' => 'Traces de jeu des membres supprimés et unités muettes depuis 7 jours effacées dans ces communautés.'],
            ],
            'post' => function (array $r): array {
                $r['_corr'] = ['type' => 'tenant', 'id' => $r['id']];
                $r['name'] = ['type' => 'tenant', 'id' => $r['id'], 'label' => (string) $r['name']];

                return $r;
            },
        ];
    }

    private function emptyDef(): array
    {
        return [
            'sql' => 'SELECT 0 AS id FROM DUAL WHERE 0', 'id' => 'id', 'search' => [], 'sort' => 'id',
            'columns' => [['key' => 'id', 'label' => 'ID', 'type' => 'num']], 'flags' => [], 'actions' => [],
            'note' => 'Table absente de cette installation.', 'post' => static fn (array $r): array => $r,
        ];
    }

    /** @return list<array{value: string, label: string}> */
    private function distinct(string $table, string $col): array
    {
        if (!$this->has($table)) {
            return [];
        }
        try {
            $rows = $this->pdo()->query("SELECT DISTINCT {$col} v FROM {$table} WHERE {$col} IS NOT NULL AND {$col} <> '' ORDER BY v LIMIT 40")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (\Throwable) {
            return [];
        }

        return array_map(static fn ($v): array => ['value' => (string) $v, 'label' => (string) $v], $rows);
    }

    // ================================================================= lecture

    /**
     * @param array<string, string> $filters
     * @return array{meta: array<string, mixed>, rows: list<array<string, mixed>>, total: int}
     */
    public function query(string $dataset, string $q, array $filters, string $flag, string $sort, string $dir, int $page, int $per): array
    {
        if ($dataset === 'cleanup') {
            return $this->cleanup();
        }
        $def = $this->definition($dataset);
        $colKeys = array_column($def['columns'], 'key');
        $where = [];
        $params = [];
        if ($q !== '' && $def['search'] !== []) {
            $where[] = '(' . implode(' OR ', array_map(static fn ($c) => "x.{$c} LIKE ?", $def['search'])) . ')';
            foreach ($def['search'] as $_) {
                $params[] = '%' . $q . '%';
            }
        }
        foreach ($filters as $k => $v) {
            if (!in_array($k, $colKeys, true) || $v === '' || $k === 'flags') {
                continue;
            }
            $col = current(array_filter($def['columns'], static fn ($c) => $c['key'] === $k));
            if (isset($col['options'])) {
                $where[] = "x.{$k} = ?";
                $params[] = $v;
            } else {
                $where[] = "CAST(x.{$k} AS CHAR) LIKE ?";
                $params[] = '%' . $v . '%';
            }
        }
        $flagWhere = $flag !== '' && isset($def['flags'][$flag]) ? ' AND (' . $this->qualify($def['flags'][$flag][1]) . ')' : '';
        $w = ($where !== [] ? ' WHERE ' . implode(' AND ', $where) : ' WHERE 1=1') . $flagWhere;

        $sortCol = in_array($sort, $colKeys, true) && $sort !== 'flags' ? $sort : $def['sort'];
        $dirSql = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';
        $per = max(10, min(100000, $per));
        $page = max(1, $page);
        $base = '(' . $def['sql'] . ') x';

        $total = 0;
        $rows = [];
        $flagCounts = [];
        $totalAll = 0;
        $error = null;
        try {
            $st = $this->pdo()->prepare("SELECT COUNT(*) FROM {$base}{$w}");
            $st->execute($params);
            $total = (int) $st->fetchColumn();
            $st = $this->pdo()->prepare("SELECT * FROM {$base}{$w} ORDER BY x.{$sortCol} IS NULL, x.{$sortCol} {$dirSql} LIMIT {$per} OFFSET " . (($page - 1) * $per));
            $st->execute($params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if ($def['flags'] !== []) {
                $sums = [];
                foreach ($def['flags'] as $k => $f) {
                    $sums[] = 'SUM(CASE WHEN ' . $this->qualify($f[1]) . " THEN 1 ELSE 0 END) AS `{$k}`";
                }
                $fr = $this->pdo()->query('SELECT COUNT(*) AS _all, ' . implode(', ', $sums) . " FROM {$base}")->fetch(PDO::FETCH_ASSOC) ?: [];
                $totalAll = (int) ($fr['_all'] ?? 0);
                foreach ($def['flags'] as $k => $f) {
                    $flagCounts[] = ['key' => $k, 'label' => $f[0], 'tone' => $f[2], 'title' => $f[3] ?? '', 'count' => (int) ($fr[$k] ?? 0)];
                }
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            error_log('[data-registry] ' . $dataset . ' : ' . mb_substr($error, 0, 300));
        }

        $post = $def['post'];
        $rows = array_map(static function (array $r) use ($post, $def): array {
            $id = $r[$def['id']] ?? null;
            $r = $post($r);
            $r['_id'] ??= $id;

            return $r;
        }, $rows);

        return [
            'meta' => [
                'columns' => array_map(static fn ($c) => array_intersect_key($c, array_flip(['key', 'label', 'type', 'options', 'filter', 'sortable'])), $def['columns']),
                'flags' => $flagCounts,
                'total_all' => $totalAll,
                'actions' => $def['actions'],
                'note' => $error !== null ? 'Erreur de lecture : voir le journal du serveur.' : ($def['note'] ?? ''),
                'lead' => self::LEADS[$dataset] ?? '',
            ],
            'rows' => $rows,
            'total' => $total,
        ];
    }

    private const LEADS = [
        'users' => 'Toutes les fiches, toutes communautés. « Supprimés avec traces de jeu » : comptes effacés dont le joueur apparaît encore sur la carte, en ATAK ou dans le registre.',
        'players' => 'Chaque identifiant Steam trouvé dans les données de jeu, avec les comptes qui le portent. Un « fantôme » n’a plus aucun compte vivant.',
        'terminals' => 'Tous les ATAK enregistrés, avec leur compte. Retirer révoque certificats et appareils de confiance avant de supprimer la fiche.',
        'units' => 'Ce que la carte BFT affiche pour chaque communauté. Une unité muette ou sans compte est un fantôme.',
        'sessions' => 'Sessions ouvertes par le jeu (COMSPEC Link) : actives, révoquées ou expirées.',
        'tenants' => 'Communautés et volume de leurs données de jeu.',
        'cleanup' => 'Données orphelines ou périmées. L’entretien automatique en efface une partie chaque nuit ; ici vous pouvez lancer le nettoyage tout de suite.',
    ];

    /** Préfixe les colonnes des conditions de « flags » par l'alias x (expressions simples). */
    private function qualify(string $cond): string
    {
        return (string) preg_replace_callback('/(?<![\w.\'])([a-z_][a-z0-9_]*)(?=\s*(?:IS|=|<>|<|>|>=|<=|NOT IN|IN)\b|\s*(?:=|<>|<=|>=|<|>))/', static function ($m) {
            $kw = ['AND', 'OR', 'NOT', 'NULL', 'IS', 'IN'];

            return in_array(strtoupper($m[1]), $kw, true) ? $m[1] : 'x.' . $m[1];
        }, $cond);
    }

    // ================================================================= nettoyage

    /** @return array{meta: array<string, mixed>, rows: list<array<string, mixed>>, total: int} */
    public function cleanup(): array
    {
        $rows = [];
        $ghosts = (new PlayerDataRetentionCronJob($this->pdo()))->ghostUserIds(100000);
        $rows[] = ['_id' => 'ghost_users', 'check' => 'Comptes supprimés avec traces de jeu', 'detail' => 'Carte, ATAK, registre, mods et statistiques de comptes déjà supprimés', 'count' => count($ghosts), 'auto' => 'Oui (100 par passage)'];
        if ($this->has('atak_units')) {
            $rows[] = ['_id' => 'units_ghost', 'check' => 'Unités dont le Steam n’a plus de compte', 'detail' => 'Joueur parti ou supprimé, encore affiché sur la carte', 'count' => $this->count("SELECT COUNT(*) FROM atak_units a WHERE NULLIF(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(a.extra, '$.steam_uid')), 'null'), '') IS NOT NULL AND NOT EXISTS (SELECT 1 FROM users u WHERE u.tenant_id = a.tenant_id AND u.deleted_at IS NULL AND u.steam_id = JSON_UNQUOTE(JSON_EXTRACT(a.extra, '$.steam_uid')))"), 'auto' => 'Non'];
            $rows[] = ['_id' => 'units_7d', 'check' => 'Unités muettes depuis 7 jours', 'detail' => 'Plus aucune position reçue', 'count' => $this->count('SELECT COUNT(*) FROM atak_units WHERE updated_at < DATE_SUB(NOW(), INTERVAL 7 DAY)'), 'auto' => 'À 30 jours'];
        }
        if ($this->has('operator_game_profiles')) {
            $rows[] = ['_id' => 'profiles_orphan', 'check' => 'Profils opérateur sans compte', 'detail' => 'Steam vu en jeu, jamais rattaché, muet depuis 30 jours', 'count' => $this->count('SELECT COUNT(*) FROM operator_game_profiles WHERE user_id IS NULL AND last_seen_at < DATE_SUB(NOW(), INTERVAL 30 DAY)'), 'auto' => 'Non'];
        }
        if ($this->has('atak_terminals')) {
            $rows[] = ['_id' => 'terminals_orphan', 'check' => 'ATAK sans compte ou de compte supprimé', 'detail' => 'Fiche terminal sans propriétaire vivant', 'count' => $this->count('SELECT COUNT(*) FROM atak_terminals a LEFT JOIN users u ON u.id = a.user_id WHERE a.user_id IS NULL OR u.id IS NULL OR u.deleted_at IS NOT NULL'), 'auto' => 'Comptes supprimés : oui'];
        }
        foreach (PlayerDataRetentionCronJob::RULES as [$table, $cond, $label]) {
            if ($this->has($table)) {
                $rows[] = ['_id' => 'rule:' . $table, 'check' => $label, 'detail' => $table, 'count' => $this->count("SELECT COUNT(*) FROM {$table} WHERE {$cond}"), 'auto' => 'Oui'];
            }
        }
        foreach ($rows as &$r) {
            $r['_ghost'] = (int) $r['count'] > 0;
        }
        unset($r);

        return [
            'meta' => [
                'columns' => [
                    ['key' => 'check', 'label' => 'Contrôle', 'filter' => false, 'sortable' => false],
                    ['key' => 'detail', 'label' => 'Détail', 'filter' => false, 'sortable' => false],
                    ['key' => 'count', 'label' => 'Lignes', 'type' => 'num', 'filter' => false, 'sortable' => false],
                    ['key' => 'auto', 'label' => 'Entretien automatique', 'filter' => false, 'sortable' => false],
                ],
                'flags' => [],
                'total_all' => count($rows),
                'actions' => [['key' => 'cleanup', 'label' => 'Nettoyer maintenant', 'danger' => true, 'confirm' => 'Les lignes des contrôles sélectionnés sont effacées.']],
                'note' => '',
                'lead' => self::LEADS['cleanup'],
            ],
            'rows' => $rows,
            'total' => count($rows),
        ];
    }

    private function count(string $sql): int
    {
        try {
            return (int) $this->pdo()->query($sql)->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    // ================================================================= corrélations

    /**
     * @return array<string, mixed>|null
     */
    public function correlations(string $type, string $id): ?array
    {
        $purge = $this->purger();

        if ($type === 'user') {
            $u = $this->row('SELECT u.*, t.name AS tenant_name FROM users u LEFT JOIN tenants t ON t.id = u.tenant_id WHERE u.id = ?', [(int) $id]);
            if ($u === null) {
                return null;
            }
            $identity = $purge->identityForUser((int) $id);
            $links = [];
            $links[] = ['type' => 'tenant', 'id' => $u['tenant_id'], 'label' => (string) ($u['tenant_name'] ?? '#' . $u['tenant_id']), 'kind' => 'Communauté'];
            foreach ($identity['steams'] as $s) {
                $others = $this->rows('SELECT id, COALESCE(NULLIF(display_name, \'\'), email) l FROM users WHERE steam_id = ? AND id <> ? AND deleted_at IS NULL', [$s, (int) $id]);
                $links[] = ['type' => 'steam', 'id' => $s, 'label' => $s, 'kind' => 'Steam', 'warn' => $others !== [] ? 'aussi sur ' . count($others) . ' autre(s) compte(s)' : ''];
            }
            foreach ($this->has('atak_terminals') ? $this->rows('SELECT id, terminal_uid, terminal_label FROM atak_terminals WHERE user_id = ?', [(int) $id]) : [] as $t) {
                $links[] = ['type' => 'terminal', 'id' => $t['id'], 'label' => (string) ($t['terminal_label'] ?: $t['terminal_uid']), 'kind' => 'ATAK', 'sub' => (string) $t['terminal_uid']];
            }
            foreach ($this->rows("SELECT id, tenant_id, COALESCE(NULLIF(display_name, ''), email) l FROM users WHERE email = ? AND id <> ? AND email NOT LIKE '%@deleted.invalid'", [(string) $u['email'], (int) $id]) as $o) {
                $links[] = ['type' => 'user', 'id' => $o['id'], 'label' => (string) $o['l'], 'kind' => 'Même e-mail', 'sub' => 'communauté #' . $o['tenant_id']];
            }
            $warnings = [];
            if ($u['deleted_at'] !== null) {
                $warnings[] = 'Compte supprimé le ' . $u['deleted_at'] . ' : les traces de jeu ci-dessous auraient dû disparaître.';
            }

            return [
                'entity' => ['type' => 'user', 'id' => (int) $id, 'kind' => 'Compte', 'label' => (string) (($u['display_name'] ?? '') ?: $u['email']), 'facts' => [
                    ['ID', $u['id']], ['E-mail', $u['email']], ['Indicatif', $u['callsign']], ['Communauté', $u['tenant_name']], ['Statut', $u['status']],
                    ['Steam', $u['steam_id']], ['Créé', $u['created_at']], ['Supprimé', $u['deleted_at']],
                ]],
                'links' => $links,
                'tables' => $purge->count($purge->plan($identity, null)),
                'warnings' => $warnings,
                'purge' => ['label' => 'Effacer toutes ses traces de jeu', 'lead' => 'Carte, ATAK, sessions, registre opérateur, mods et statistiques. La fiche, ses comptes rendus et son historique restent.', 'confirm' => 'PURGER'],
                'open' => [['label' => 'Ouvrir la fiche', 'href' => url('admin/users/' . (int) $id . '/edit') . '#atak']],
            ];
        }

        if ($type === 'steam') {
            $sid = SteamId::normalize($id) ?? trim($id);
            $identity = $purge->identityForSteam($sid);
            $links = [];
            foreach ($this->rows("SELECT u.id, u.tenant_id, t.name tn, COALESCE(NULLIF(u.display_name, ''), u.email) l, u.deleted_at FROM users u LEFT JOIN tenants t ON t.id = u.tenant_id WHERE u.steam_id = ?", [$sid]) as $u) {
                $links[] = ['type' => 'user', 'id' => $u['id'], 'label' => (string) $u['l'], 'kind' => 'Compte', 'sub' => (string) ($u['tn'] ?? ''), 'warn' => $u['deleted_at'] ? 'supprimé' : ''];
            }
            foreach ($identity['tenants'] as $tid) {
                $links[] = ['type' => 'tenant', 'id' => $tid, 'label' => (string) ($this->row('SELECT name FROM tenants WHERE id = ?', [$tid])['name'] ?? '#' . $tid), 'kind' => 'Communauté'];
            }
            $live = array_filter($links, static fn ($l) => $l['type'] === 'user' && $l['warn'] === '');
            $plan = $purge->plan($identity, null, false);

            return [
                'entity' => ['type' => 'steam', 'id' => $sid, 'kind' => 'Joueur Steam', 'label' => $sid, 'facts' => [
                    ['Steam', $sid], ['Indicatifs vus', implode(', ', $identity['callsigns']) ?: '—'], ['Comptes vivants', (string) count($live)],
                ]],
                'links' => $links,
                'tables' => $purge->count($plan),
                'warnings' => $live === [] ? ['Aucun compte vivant ne porte ce Steam : c’est un joueur fantôme.'] : [],
                'purge' => ['label' => 'Effacer toutes les traces de ce Steam', 'lead' => 'Données de jeu effacées et Steam retiré des fiches. Le mod recréera le joueur s’il rejoue : bloquez-le dans le tableau pour l’en empêcher.', 'confirm' => substr($sid, -6)],
                'open' => [['label' => 'Profil Steam', 'href' => 'https://steamcommunity.com/profiles/' . rawurlencode($sid)]],
            ];
        }

        if ($type === 'terminal') {
            $t = $this->has('atak_terminals') ? $this->row('SELECT a.*, t.name tn, COALESCE(NULLIF(u.display_name, \'\'), u.email) ul, u.deleted_at ud FROM atak_terminals a LEFT JOIN tenants t ON t.id = a.tenant_id LEFT JOIN users u ON u.id = a.user_id WHERE a.id = ?', [(int) $id]) : null;
            if ($t === null) {
                return null;
            }
            $links = [['type' => 'tenant', 'id' => $t['tenant_id'], 'label' => (string) ($t['tn'] ?? ''), 'kind' => 'Communauté']];
            if ($t['user_id']) {
                $links[] = ['type' => 'user', 'id' => $t['user_id'], 'label' => (string) ($t['ul'] ?? '#' . $t['user_id']), 'kind' => 'Compte', 'warn' => $t['ud'] ? 'supprimé' : ''];
            }
            $tables = [];
            foreach ([
                ['atak_certificates', 'Certificats', 'terminal_id = ?', [(int) $id], 'created_at'],
                ['atak_trusted_devices', 'Appareils de confiance', 'tenant_id = ? AND terminal_uid = ?', [$t['tenant_id'], $t['terminal_uid']], 'created_at'],
                ['atak_device_logs', 'Journal de l’appareil', 'tenant_id = ? AND terminal_uid = ?', [$t['tenant_id'], $t['terminal_uid']], 'logged_at'],
                ['atak_terminal_sync', 'Synchronisation', 'tenant_id = ? AND terminal_uid = ?', [$t['tenant_id'], $t['terminal_uid']], 'reported_at'],
                ['atak_admin_actions', 'Actions d’administration', 'terminal_uid = ?', [$t['terminal_uid']], 'created_at'],
            ] as [$tb, $lb, $w, $p, $d]) {
                if ($this->has($tb)) {
                    $r = $this->row("SELECT COUNT(*) n, MAX({$d}) l FROM {$tb} WHERE {$w}", $p);
                    if ((int) ($r['n'] ?? 0) > 0) {
                        $tables[] = ['table' => $tb, 'label' => $lb, 'count' => (int) $r['n'], 'last' => $r['l'], 'purge' => $tb !== 'atak_admin_actions'];
                    }
                }
            }

            return [
                'entity' => ['type' => 'terminal', 'id' => (int) $id, 'kind' => 'ATAK', 'label' => (string) ($t['terminal_label'] ?: $t['terminal_uid']), 'facts' => [
                    ['Identifiant', $t['terminal_uid']], ['Type', $t['terminal_type']], ['Statut', $t['status']], ['Compromission', $t['compromise_state']],
                    ['Indicatif', $t['operator_callsign']], ['Mod / Link', ($t['mod_version'] ?? '?') . ' · ' . ($t['extension_version'] ?? '?')],
                    ['Mise en service', $t['first_seen_at']], ['Dernier contact', $t['last_seen_at']],
                ]],
                'links' => $links,
                'tables' => $tables,
                'warnings' => $t['ud'] ? ['Le compte de cet ATAK est supprimé.'] : ($t['user_id'] ? [] : ['Cet ATAK n’est rattaché à aucun compte.']),
                'purge' => ['label' => 'Retirer cet ATAK', 'lead' => 'Certificats et appareils de confiance révoqués, sessions fermées, fiche et journal supprimés.', 'confirm' => 'RETIRER'],
                'open' => [['label' => 'Fiche du parc ATAK', 'href' => url('admin/parc-atak/fiche') . '?id=' . (int) $id]],
            ];
        }

        if ($type === 'tenant') {
            $t = $this->row('SELECT * FROM tenants WHERE id = ?', [(int) $id]);
            if ($t === null) {
                return null;
            }
            $tables = [];
            foreach ([
                ['users', 'Fiches (dont supprimées)', 'tenant_id = ?', 'created_at'], ['atak_terminals', 'Terminaux ATAK', 'tenant_id = ?', 'last_seen_at'],
                ['atak_units', 'Unités sur la carte', 'tenant_id = ?', 'updated_at'], ['operator_game_profiles', 'Profils opérateur', 'tenant_id = ?', 'last_seen_at'],
                ['game_sessions', 'Sessions de jeu', 'tenant_id = ?', 'created_at'], ['game_player_mods', 'Mods chargés', 'tenant_id = ?', 'last_seen'],
                ['atak_device_logs', 'Journal des appareils', 'tenant_id = ?', 'logged_at'], ['atak_tactical_reports', 'Comptes rendus', 'tenant_id = ?', 'created_at'],
                ['sse_field_notes', 'Fiches SSE', 'tenant_id = ?', 'created_at'], ['atak_chat_messages', 'Messages', 'tenant_id = ?', 'created_at'],
                ['atak_ingest_traffic', 'Trafic jeu', 'tenant_id = ?', 'bucket_ts'],
            ] as [$tb, $lb, $w, $d]) {
                if ($this->has($tb)) {
                    $r = $this->row("SELECT COUNT(*) n, MAX({$d}) l FROM {$tb} WHERE {$w}", [(int) $id]);
                    if ((int) ($r['n'] ?? 0) > 0) {
                        $tables[] = ['table' => $tb, 'label' => $lb, 'count' => (int) $r['n'], 'last' => $r['l'], 'purge' => false];
                    }
                }
            }

            return [
                'entity' => ['type' => 'tenant', 'id' => (int) $id, 'kind' => 'Communauté', 'label' => (string) $t['name'], 'facts' => [
                    ['ID', $t['id']], ['Adresse', $t['slug']], ['Type', $t['tenant_type']], ['Créée', $t['created_at']],
                ]],
                'links' => [],
                'tables' => $tables,
                'warnings' => [],
                'purge' => null,
                'open' => [['label' => 'Fiche de la communauté', 'href' => url('admin/tenants/' . (int) $id)]],
            ];
        }

        return null;
    }

    // ================================================================= actions

    /**
     * @param list<string> $ids
     * @return array{ok: bool, message: string}
     */
    public function bulk(string $dataset, string $action, array $ids, int $actorId, string $actorLabel): array
    {
        $ids = array_slice(array_values(array_unique(array_filter(array_map('strval', $ids), static fn ($v) => $v !== ''))), 0, 2000);
        if ($ids === []) {
            return ['ok' => false, 'message' => 'Aucune ligne sélectionnée.'];
        }
        $purge = $this->purger();
        $control = new AtakAdminControlRepository();
        $rows = 0;
        $n = 0;

        switch ("{$dataset}:{$action}") {
            case 'users:purge_traces':
                foreach ($ids as $id) {
                    $rows += array_sum($purge->purgeUser((int) $id, null));
                    $n++;
                }

                return ['ok' => true, 'message' => "Traces de jeu effacées pour {$n} compte(s) : {$rows} ligne(s)."];
            case 'users:unlink_steam':
                $svc = new AdminUserAtakService();
                foreach ($ids as $id) {
                    if (!empty($svc->unlinkSteam((int) $id, $actorId, $actorLabel)['ok'])) {
                        $n++;
                    }
                }

                return ['ok' => true, 'message' => "Steam délié sur {$n} compte(s)."];
            case 'players:purge_steam':
                foreach ($ids as $sid) {
                    $rows += array_sum($purge->purgeSteam($sid, null));
                    $n++;
                }

                return ['ok' => true, 'message' => "Traces effacées pour {$n} Steam : {$rows} ligne(s)."];
            case 'players:block_steam':
                $block = \App\Core\Container::get(\App\Services\Moderation\IndicatorBlocklistService::class);
                foreach ($ids as $sid) {
                    try {
                        $block->addSteamBlock($actorId, 'global', null, $sid, 'Bloqué depuis le registre des données', null);
                        $n++;
                    } catch (\Throwable) {
                    }
                }

                return ['ok' => true, 'message' => "{$n} Steam bloqué(s) sur toute la plateforme."];
            case 'terminals:retire_terminal':
                foreach ($ids as $id) {
                    $t = $control->findTerminal((int) $id);
                    if ($t !== null && $control->retireTerminal($t, 'Retiré depuis le registre des données')) {
                        $control->log($t, 'retire', 'Retiré depuis le registre des données.', $actorId, $actorLabel);
                        $n++;
                    }
                }

                return ['ok' => true, 'message' => "{$n} ATAK retiré(s)."];
            case 'units:delete_unit':
                $n = $this->exec('DELETE FROM atak_units WHERE id IN (' . $this->marks($ids) . ')', array_map('intval', $ids));

                return ['ok' => true, 'message' => "{$n} unité(s) retirée(s) de la carte."];
            case 'sessions:revoke_session':
                $n = $this->exec('UPDATE game_sessions SET revoked_at = NOW() WHERE revoked_at IS NULL AND id IN (' . $this->marks($ids) . ')', array_map('intval', $ids));

                return ['ok' => true, 'message' => "{$n} session(s) révoquée(s)."];
            case 'sessions:delete_session':
                $n = $this->exec('DELETE FROM game_sessions WHERE id IN (' . $this->marks($ids) . ')', array_map('intval', $ids));

                return ['ok' => true, 'message' => "{$n} session(s) supprimée(s)."];
            case 'tenants:tenant_ghosts':
                foreach ($ids as $tid) {
                    foreach ($this->rows('SELECT id FROM users WHERE tenant_id = ? AND deleted_at IS NOT NULL', [(int) $tid]) as $u) {
                        $rows += array_sum($purge->purgeUser((int) $u['id'], (int) $tid));
                    }
                    if ($this->has('atak_units')) {
                        $rows += $this->exec('DELETE FROM atak_units WHERE tenant_id = ? AND updated_at < DATE_SUB(NOW(), INTERVAL 7 DAY)', [(int) $tid]);
                    }
                    $n++;
                }

                return ['ok' => true, 'message' => "{$n} communauté(s) nettoyée(s) : {$rows} ligne(s)."];
            case 'cleanup:cleanup':
                return $this->runCleanup($ids);
        }

        return ['ok' => false, 'message' => 'Action inconnue.'];
    }

    /**
     * @param list<string> $keys
     * @return array{ok: bool, message: string}
     */
    private function runCleanup(array $keys): array
    {
        $rows = 0;
        $purge = $this->purger();
        foreach ($keys as $k) {
            if ($k === 'ghost_users') {
                foreach ((new PlayerDataRetentionCronJob($this->pdo()))->ghostUserIds(5000) as $uid) {
                    $rows += array_sum($purge->purgeUser($uid, null));
                }
            } elseif ($k === 'units_ghost') {
                $rows += $this->exec("DELETE a FROM atak_units a WHERE NULLIF(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(a.extra, '$.steam_uid')), 'null'), '') IS NOT NULL AND NOT EXISTS (SELECT 1 FROM users u WHERE u.tenant_id = a.tenant_id AND u.deleted_at IS NULL AND u.steam_id = JSON_UNQUOTE(JSON_EXTRACT(a.extra, '$.steam_uid')))", []);
            } elseif ($k === 'units_7d') {
                $rows += $this->exec('DELETE FROM atak_units WHERE updated_at < DATE_SUB(NOW(), INTERVAL 7 DAY)', []);
            } elseif ($k === 'profiles_orphan') {
                $rows += $this->exec('DELETE FROM operator_game_profiles WHERE user_id IS NULL AND last_seen_at < DATE_SUB(NOW(), INTERVAL 30 DAY)', []);
            } elseif ($k === 'terminals_orphan') {
                $control = new AtakAdminControlRepository();
                foreach ($this->rows('SELECT a.id FROM atak_terminals a LEFT JOIN users u ON u.id = a.user_id WHERE a.user_id IS NULL OR u.id IS NULL OR u.deleted_at IS NOT NULL LIMIT 2000', []) as $r) {
                    $t = $control->findTerminal((int) $r['id']);
                    if ($t !== null && $control->retireTerminal($t, 'Nettoyage : ATAK sans compte')) {
                        $rows++;
                    }
                }
            } elseif (str_starts_with($k, 'rule:')) {
                $table = substr($k, 5);
                foreach (PlayerDataRetentionCronJob::RULES as [$t, $cond]) {
                    if ($t === $table && $this->has($t)) {
                        $rows += $this->exec("DELETE FROM {$t} WHERE {$cond}", []);
                    }
                }
            }
        }

        return ['ok' => true, 'message' => "Nettoyage terminé : {$rows} ligne(s) effacée(s)."];
    }

    /**
     * Purge depuis le tiroir de corrélations (confirmation saisie).
     *
     * @return array{ok: bool, message: string}
     */
    public function purgeEntity(string $type, string $id, string $confirm, int $actorId, string $actorLabel): array
    {
        $c = $this->correlations($type, $id);
        if ($c === null || empty($c['purge'])) {
            return ['ok' => false, 'message' => 'Rien à purger.'];
        }
        if (trim($confirm) !== (string) $c['purge']['confirm']) {
            return ['ok' => false, 'message' => 'Confirmation incorrecte : tapez exactement « ' . $c['purge']['confirm'] . ' ».'];
        }
        $done = match ($type) {
            'user' => $this->purger()->purgeUser((int) $id, null),
            'steam' => $this->purger()->purgeSteam($id, null),
            default => null,
        };
        if ($type === 'terminal') {
            $control = new AtakAdminControlRepository();
            $t = $control->findTerminal((int) $id);
            $ok = $t !== null && $control->retireTerminal($t, 'Retiré depuis le registre des données');
            if ($ok) {
                $control->log($t, 'retire', 'Retiré depuis le registre des données.', $actorId, $actorLabel);
                $this->exec('DELETE FROM atak_device_logs WHERE tenant_id = ? AND terminal_uid = ?', [(int) $t['tenant_id'], (string) $t['terminal_uid']]);
            }

            return ['ok' => $ok, 'message' => $ok ? 'ATAK retiré.' : 'Le retrait a échoué.'];
        }

        return ['ok' => true, 'message' => 'Purge terminée : ' . PlayerTracePurgeService::summary($done ?? []) . '.'];
    }

    // ================================================================= utilitaires

    /** @param list<mixed> $p */
    private function row(string $sql, array $p): ?array
    {
        return $this->rows($sql, $p)[0] ?? null;
    }

    /**
     * @param list<mixed> $p
     * @return list<array<string, mixed>>
     */
    private function rows(string $sql, array $p): array
    {
        try {
            $st = $this->pdo()->prepare($sql);
            $st->execute($p);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** @param list<mixed> $p */
    private function exec(string $sql, array $p): int
    {
        try {
            $st = $this->pdo()->prepare($sql);
            $st->execute($p);

            return $st->rowCount();
        } catch (\Throwable $e) {
            error_log('[data-registry] ' . mb_substr($e->getMessage(), 0, 200));

            return 0;
        }
    }

    /** @param list<mixed> $ids */
    private function marks(array $ids): string
    {
        return implode(',', array_fill(0, max(1, count($ids)), '?'));
    }

    /**
     * Export CSV (séparateur « ; », BOM UTF-8 : ouverture directe dans Excel).
     *
     * @param array{meta: array<string, mixed>, rows: list<array<string, mixed>>, total: int} $data
     */
    public static function csv(array $data): string
    {
        $fh = fopen('php://temp', 'w+');
        fwrite($fh, "\xEF\xBB\xBF");
        $cols = array_values(array_filter($data['meta']['columns'], static fn ($c) => ($c['key'] ?? '') !== ''));
        fputcsv($fh, array_map(static fn ($c) => $c['label'], $cols), ';', '"', '\\');
        foreach ($data['rows'] as $r) {
            fputcsv($fh, array_map(static function ($c) use ($r) {
                $v = $r[$c['key']] ?? '';
                if (is_array($v)) {
                    $v = isset($v['label']) ? $v['label'] : implode(', ', array_map(static fn ($t) => is_array($t) ? ($t['label'] ?? '') : (string) $t, $v));
                }

                return (string) $v;
            }, $cols), ';', '"', '\\');
        }
        rewind($fh);
        $out = (string) stream_get_contents($fh);
        fclose($fh);

        return $out;
    }
}
