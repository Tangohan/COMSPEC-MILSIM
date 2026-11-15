<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Controllers\Web\JnetPortalController;
use App\Core\Database;
use App\Repositories\TenantMessageRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\Auth\AuthService;
use App\Services\Jnet\JnetDashboardService;
use App\Services\Jnet\JnetMessagingService;
use App\Services\Rbac\RbacService;
use PDO;
use PDOStatement;

/**
 * Base sqlite de la messagerie JNET (tests et rendus locaux) : une communauté, ses membres, ses formations et ses fils
 * de messages, branchée sur Database le temps d'un test. Le vrai contrôleur, la vraie messagerie (JnetMessagingService,
 * TenantMessageRepository, UnitRepository) tournent dessus ; seules la lecture des membres (requêtes MySQL du dépôt des
 * utilisateurs) et la pose des droits de session sont remplacées par des lectures sqlite équivalentes.
 *
 * Jeux : « small » (5 membres, 9 formations, comme l'unité de production), « large » (140 membres, 30 formations,
 * noms longs). Le lecteur est toujours le membre 1 ; le membre 35 (Liam KELLER) existe dans les deux jeux.
 */
final class JnetMailSqlite
{
    public const TENANT = 1;
    public const READER = 1;
    public const KELLER = 35;
    /** Membre d'une autre communauté : ne doit jamais apparaître ni recevoir. */
    public const FOREIGNER = 900;

    private static ?PDO $saved = null;
    private static bool $swapped = false;
    /** @var array<string, object> */
    private static array $savedInstances = [];

    public static function open(string $dataset = 'small', string $dsn = 'sqlite::memory:'): PDO
    {
        $pdo = self::connect($dsn);
        self::schema($pdo);
        self::seed($pdo, $dataset);
        self::attach($pdo);

        return $pdo;
    }

    /** Ouvre une base déjà remplie (rendus locaux servis sur plusieurs requêtes). */
    public static function reopen(string $dsn): PDO
    {
        $pdo = self::connect($dsn);
        self::attach($pdo);

        return $pdo;
    }

    private static function connect(string $dsn): PDO
    {
        $pdo = new class ($dsn) extends PDO {
            public function __construct(string $dsn)
            {
                parent::__construct($dsn, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            }

            public function prepare(string $query, array $options = []): PDOStatement|false
            {
                return parent::prepare(JnetMailSqlite::sqliteSql($query), $options);
            }

            public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
            {
                return $fetchMode === null
                    ? parent::query(JnetMailSqlite::sqliteSql($query))
                    : parent::query(JnetMailSqlite::sqliteSql($query), $fetchMode, ...$fetchModeArgs);
            }
        };
        $pdo->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'), 0);
        $pdo->sqliteCreateFunction('CURDATE', static fn (): string => date('Y-m-d'), 0);
        $pdo->sqliteCreateFunction('DATABASE', static fn (): string => 'jnet', 0);

        return $pdo;
    }

    /** Les seules tournures MySQL que la messagerie emploie et que sqlite ne lit pas. */
    public static function sqliteSql(string $sql): string
    {
        return (string) preg_replace('/\bINSERT\s+IGNORE\b/i', 'INSERT OR IGNORE', $sql);
    }

    /** information_schema lu par les dépôts (présence d'une table, d'une colonne) : servi par l'état réel de la base. */
    private static function attach(PDO $pdo): void
    {
        $pdo->exec("ATTACH DATABASE ':memory:' AS information_schema");
        $pdo->exec('CREATE TABLE information_schema.TABLES (TABLE_SCHEMA TEXT, TABLE_NAME TEXT)');
        $pdo->exec('CREATE TABLE information_schema.COLUMNS (TABLE_SCHEMA TEXT, TABLE_NAME TEXT, COLUMN_NAME TEXT)');
        $tables = $pdo->query("SELECT name FROM main.sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $insT = $pdo->prepare("INSERT INTO information_schema.TABLES VALUES ('jnet', ?)");
        $insC = $pdo->prepare("INSERT INTO information_schema.COLUMNS VALUES ('jnet', ?, ?)");
        foreach ($tables as $table) {
            $insT->execute([$table]);
            foreach ($pdo->query('PRAGMA main.table_info("' . $table . '")')->fetchAll(PDO::FETCH_ASSOC) ?: [] as $col) {
                $insC->execute([$table, (string) $col['name']]);
            }
        }

        $prop = new \ReflectionProperty(Database::class, 'pdo');
        $instances = new \ReflectionProperty(\App\Core\Container::class, 'instances');
        if (!self::$swapped) {
            $current = $prop->getValue();
            self::$saved = $current instanceof PDO ? $current : null;
            self::$savedInstances = (array) $instances->getValue();
            self::$swapped = true;
        }
        $prop->setValue(null, $pdo);
        // Les services du conteneur sont reconstruits sur cette base (modération, droits d'envoi).
        $instances->setValue(null, []);
    }

    public static function close(): void
    {
        if (self::$swapped) {
            (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, self::$saved);
            (new \ReflectionProperty(\App\Core\Container::class, 'instances'))->setValue(null, self::$savedInstances);
        }
        self::$saved = null;
        self::$savedInstances = [];
        self::$swapped = false;
    }

    private static function schema(PDO $pdo): void
    {
        foreach ([
            'CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, slug TEXT, tenant_type TEXT)',
            'CREATE TABLE roles (id INTEGER PRIMARY KEY, tenant_id INTEGER, name TEXT, slug TEXT)',
            "CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER, email TEXT, display_name TEXT, callsign TEXT,
                status TEXT DEFAULT 'active', avatar_url TEXT, role_id INTEGER, grade_id INTEGER, job_title TEXT, created_at TEXT)",
            'CREATE TABLE user_profiles (user_id INTEGER PRIMARY KEY, first_name TEXT, last_name TEXT)',
            'CREATE TABLE units (id INTEGER PRIMARY KEY, tenant_id INTEGER, parent_id INTEGER, name TEXT, code TEXT, display_order INTEGER DEFAULT 0)',
            'CREATE TABLE user_units (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, unit_id INTEGER, is_primary INTEGER DEFAULT 1, ended_at TEXT)',
            'CREATE TABLE tenant_message_threads (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, subject TEXT, created_by_user_id INTEGER,
                origin TEXT, precedence TEXT, recipients_summary TEXT, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE tenant_message_thread_users (thread_id INTEGER, user_id INTEGER, recipient_kind TEXT, via_label TEXT, last_read_at TEXT,
                hidden_at TEXT, PRIMARY KEY (thread_id, user_id))',
            'CREATE TABLE tenant_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, thread_id INTEGER, sender_user_id INTEGER, body TEXT, created_at TEXT)',
            'CREATE TABLE tenant_email_recipient_groups (id INTEGER PRIMARY KEY, tenant_id INTEGER, name TEXT, description TEXT, definition_json TEXT)',
            // Modération : les tables que le dépôt vérifie à l'ouverture (il ne tente alors aucune création MySQL).
            'CREATE TABLE moderation_actions (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, target_user_id INTEGER, action_type TEXT,
                restrictions_json TEXT, sanction_scope TEXT, revoked_at TEXT, expires_at TEXT)',
            'CREATE TABLE moderation_cases (id INTEGER PRIMARY KEY)',
            'CREATE TABLE blocked_indicators (id INTEGER PRIMARY KEY, display_hint TEXT)',
            'CREATE TABLE community_invitations (id INTEGER PRIMARY KEY)',
            'CREATE TABLE referral_codes (id INTEGER PRIMARY KEY)',
            'CREATE TABLE referral_attributions (id INTEGER PRIMARY KEY)',
        ] as $sql) {
            $pdo->exec($sql);
        }
    }

    private static function seed(PDO $pdo, string $dataset): void
    {
        $pdo->exec("INSERT INTO tenants (id, name, slug, tenant_type) VALUES (1, '1er Régiment de Marche', 'rm1', 'full'), (2, 'Bravo Company', 'bravo', 'full')");
        $pdo->exec("INSERT INTO roles (id, tenant_id, name, slug) VALUES (1, 1, 'Administrateur', 'tenant_admin'), (2, 1, 'Membre', 'member')");
        $user = $pdo->prepare('INSERT INTO users (id, tenant_id, email, display_name, callsign, status, role_id, job_title, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $profile = $pdo->prepare('INSERT INTO user_profiles (user_id, first_name, last_name) VALUES (?, ?, ?)');
        $unit = $pdo->prepare('INSERT INTO units (id, tenant_id, parent_id, name, code, display_order) VALUES (?, ?, ?, ?, ?, ?)');
        $link = $pdo->prepare('INSERT INTO user_units (user_id, unit_id, is_primary) VALUES (?, ?, 1)');
        $add = static function (int $id, int $tenant, string $first, string $last, string $callsign, string $job, int $unitId, int $role = 2, string $status = 'active') use ($user, $profile, $link): void {
            $user->execute([$id, $tenant, strtolower($first . '.' . $last) . $id . '@exemple.test', $first . ' ' . $last, $callsign, $status, $role, $job, '2026-01-10 10:00:00']);
            $profile->execute([$id, $first, $last]);
            if ($unitId > 0) {
                $link->execute([$id, $unitId]);
            }
        };

        if ($dataset === 'large') {
            // 30 formations : un régiment, 4 compagnies, 25 sections aux noms longs.
            $unit->execute([1, 1, null, '1er Régiment de Marche', 'RM1', 0]);
            $companies = ['Compagnie de commandement et de logistique', '1re Compagnie de combat', '2e Compagnie de combat', 'Compagnie d’appui et de reconnaissance'];
            foreach ($companies as $i => $name) {
                $unit->execute([2 + $i, 1, 1, $name, 'CIE' . ($i + 1), 10 + $i]);
            }
            $sectionNames = ['Section de commandement', 'Section de transmissions et systèmes d’information', 'Section de maintenance et de soutien logistique', 'Groupe de santé et d’évacuation',
                '1re Section d’infanterie', '2e Section d’infanterie', '3e Section d’infanterie', 'Section d’appui feu', 'Section de tireurs d’élite',
                'Section de reconnaissance et d’observation', 'Section de génie de combat', 'Détachement d’hélicoptères de manœuvre', 'Équipe de contrôle aérien avancé'];
            for ($i = 0; $i < 25; $i++) {
                $base = $sectionNames[$i % count($sectionNames)];
                $unit->execute([6 + $i, 1, 2 + ($i % 4), $i < count($sectionNames) ? $base : $base . ' (' . (2 + intdiv($i, count($sectionNames))) . 'e échelon)', 'S' . ($i + 1), 20 + $i]);
            }
            $firsts = ['Liam', 'Élodie', 'Maximilien', 'Anaïs', 'Jean-Baptiste', 'Noé', 'Chloé', 'Théo', 'Inès', 'Hugo', 'Zoé', 'Raphaël', 'Maëlle', 'Côme'];
            $lasts = ['Keller', 'Lefèbvre', 'de La Rochefoucauld-Montmorency', 'Garçon', 'Müller', 'O’Connor', 'Dupont', 'Nguyễn', 'Fernández', 'Benali', 'Rousseau', 'Schwarzenberg', 'Petit', 'Élie'];
            $jobs = ['Chef de section', 'Opérateur radio', 'Grenadier-voltigeur', 'Auxiliaire sanitaire', 'Tireur de précision', 'Pilote d’hélicoptère de manœuvre', 'Sapeur', 'Chef de groupe', 'Adjoint au commandant d’unité'];
            $add(1, 1, 'Martin', 'Dupont', 'VIPER 1-1', 'Chef de corps', 1, 1);
            $add(self::KELLER, 1, 'Liam', 'Keller', 'KELLER', 'Chef de section', 10);
            $n = 0;
            for ($id = 100; $n < 138; $id++, $n++) {
                $add($id, 1, $firsts[$n % 14], $lasts[($n * 5 + 3 + intdiv($n, 14) * 3) % 14], 'ECHO ' . (1 + intdiv($n, 9)) . '-' . (1 + $n % 9), $jobs[$n % 9], 6 + ($n % 25), $n < 3 ? 1 : 2);
            }
        } else {
            // 9 formations, 5 membres.
            foreach ([
                [1, null, '1er Régiment de Marche', 'RM1'], [2, 1, 'État-major', 'EM'], [3, 1, '1re Compagnie', 'CIE1'], [4, 3, 'Section Alpha', 'ALPHA'],
                [5, 3, 'Section Bravo', 'BRAVO'], [6, 1, 'Compagnie d’appui', 'CA'], [7, 6, 'Groupe santé', 'SAN'], [8, 1, 'Détachement aérien', 'AIR'], [9, 8, 'Équipage Griffon', 'GRIF'],
            ] as $i => [$id, $parent, $name, $code]) {
                $unit->execute([$id, 1, $parent, $name, $code, $i]);
            }
            $add(1, 1, 'Martin', 'Dupont', 'VIPER 1-1', 'Chef de corps', 2, 1);
            $add(self::KELLER, 1, 'Liam', 'Keller', 'KELLER', 'Chef de section', 4);
            $add(36, 1, 'Élodie', 'Lefèbvre', 'ÉCHO', 'Auxiliaire sanitaire', 7);
            $add(37, 1, 'Noé', 'Garçon', 'NOMAD', 'Opérateur radio', 5);
            $add(38, 1, 'Anaïs', 'Müller', 'ARROW', 'Pilote', 9);
        }
        // Hors annuaire : compte suspendu de la communauté, membre d'une autre communauté.
        $add(60, 1, 'Paul', 'Ancien', 'GHOST', 'Réserviste', 0, 2, 'suspended');
        $add(self::FOREIGNER, 2, 'Bob', 'Étranger', 'BRAVO 6', 'Chef', 0);

        // Deux fils reçus (un non lu) pour le compteur de la boîte.
        $now = date('Y-m-d H:i:s');
        $pdo->exec("INSERT INTO tenant_message_threads (id, tenant_id, subject, created_by_user_id, origin, precedence, recipients_summary, created_at, updated_at)
            VALUES (1, 1, 'Consignes pour la sortie de dimanche', 35, 'jnet', 'routine', '1 destinataire nominatif', '{$now}', '{$now}')");
        $pdo->exec("INSERT INTO tenant_message_thread_users (thread_id, user_id, recipient_kind, last_read_at) VALUES (1, 35, 'sender', '{$now}'), (1, 1, 'to', NULL)");
        $pdo->exec("INSERT INTO tenant_messages (thread_id, sender_user_id, body, created_at) VALUES (1, 35, 'Rassemblement à 20 h 30.', '{$now}')");
    }

    /**
     * Session du lecteur sans cookie ni fichier (la session est tenue pour ouverte) ; jeton de formulaire « jeton ».
     *
     * @param array<string, mixed> $extra
     */
    public static function signIn(int $userId = self::READER, array $extra = []): void
    {
        (new \ReflectionProperty(\App\Core\Session::class, 'started'))->setValue(null, true);
        $_SESSION = $extra + ['user_id' => $userId, 'tenant_id' => self::TENANT, 'display_name' => 'Martin Dupont', 'callsign' => 'VIPER 1-1', '_csrf_token' => 'jeton'];
    }

    public static function signOut(): void
    {
        (new \ReflectionProperty(\App\Core\Session::class, 'started'))->setValue(null, false);
        $_SESSION = [];
    }

    /** Interdit l'envoi de messages au membre (mesure de modération active). */
    public static function muteMessages(PDO $pdo, int $userId): void
    {
        $pdo->prepare("INSERT INTO moderation_actions (tenant_id, target_user_id, action_type, restrictions_json) VALUES (?, ?, 'mute', ?)")
            ->execute([self::TENANT, $userId, json_encode(['messages_blocked' => true])]);
    }

    public static function controller(PDO $pdo): JnetPortalController
    {
        $users = self::users($pdo);
        $tenants = new TenantRepository($pdo);
        $units = new UnitRepository($pdo);
        $messages = new TenantMessageRepository();
        $jnet = new JnetDashboardService($users, $tenants, $units);
        $auth = new AuthService($users, $tenants, (new \ReflectionClass(\App\Services\Personnel\SeniorityEnrollmentBootstrapService::class))->newInstanceWithoutConstructor());
        $rbac = new class ($pdo) extends RbacService {
            public function setPermissionsForGateFromUserRow(array $user, UserRepository $users): void
            {
            }
        };

        return new JnetPortalController(
            $auth,
            $rbac,
            $users,
            $tenants,
            $messages,
            $jnet,
            new JnetMessagingService($users, $units, $messages, null, $jnet),
        );
    }

    /** Dépôt des utilisateurs réduit aux lectures de l'annuaire JNET, en sqlite. */
    public static function users(PDO $pdo): UserRepository
    {
        return new class ($pdo) extends UserRepository {
            public function __construct(private PDO $db)
            {
                parent::__construct($db);
            }

            protected function onDatabaseConnected(PDO $pdo): void
            {
            }

            public function findById(int $id, ?int $tenantId = null): ?array
            {
                $st = $this->db->prepare('SELECT * FROM users WHERE id = ?' . ($tenantId !== null ? ' AND tenant_id = ' . $tenantId : ''));
                $st->execute([$id]);

                return $st->fetch(PDO::FETCH_ASSOC) ?: null;
            }

            public function listForTenant(int $tenantId, ?string $search = null, ?string $status = null, ?int $roleId = null, ?int $limit = null, ?int $offset = null, bool $excludeServiceAccounts = true, ?bool $onlyWithoutUnit = null, ?bool $onlyWithoutRole = null): array
            {
                $st = $this->db->prepare('SELECT u.*, r.name AS role_name FROM users u LEFT JOIN roles r ON r.id = u.role_id
                    WHERE u.tenant_id = ? AND u.status = ? ORDER BY u.email LIMIT ' . max(1, min(200, (int) ($limit ?? 200))) . ' OFFSET ' . max(0, (int) ($offset ?? 0)));
                $st->execute([$tenantId, $status ?? 'active']);

                return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }

            public function listEffectifsRosterByIds(int $tenantId, array $userIds): array
            {
                $ids = array_values(array_filter(array_map('intval', $userIds)));
                if ($ids === []) {
                    return [];
                }
                $st = $this->db->prepare('SELECT u.id, u.tenant_id, u.email, u.display_name, u.callsign, u.status, u.job_title AS job_role_display,
                        un.name AS unit_name, un.code AS unit_code, un.id AS unit_id
                    FROM users u LEFT JOIN user_units uu ON uu.user_id = u.id AND uu.is_primary = 1 LEFT JOIN units un ON un.id = uu.unit_id
                    WHERE u.tenant_id = ? AND u.id IN (' . implode(',', $ids) . ')');
                $st->execute([$tenantId]);

                return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        };
    }
}
