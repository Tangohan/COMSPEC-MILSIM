<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Container;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Middleware\TenantTypeModuleAccessMiddleware;
use App\Repositories\QualificationAwardRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Auth\AuthService;
use App\Services\Community\DefaultTenantSessionService;
use App\Services\Personnel\UnitReadinessService;
use App\Services\Rbac\RbacService;
use App\Support\DashboardOrbatTree;
use App\Support\ShortCache;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * Vague d'optimisation côté serveur : chaque test compte les lectures avant et après (PDO compteur sur SQLite en
 * mémoire) et fige le résultat fonctionnel identique à l'ancien chemin, rejoué ici comme référence.
 */
final class PerfVagueServeurTest extends TestCase
{
    private mixed $previousPdo = null;

    /** @var array<string, object> */
    private array $previousInstances = [];

    /** @var array<string, mixed> */
    private array $previousSession = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousPdo = (new \ReflectionProperty(Database::class, 'pdo'))->getValue();
        $this->previousInstances = (new \ReflectionProperty(Container::class, 'instances'))->getValue();
        $this->previousSession = $_SESSION ?? [];
        $_SESSION = [];
        RbacService::resetSchemaMemo();
        ShortCache::setEnabled(false);
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->previousPdo);
        (new \ReflectionProperty(Container::class, 'instances'))->setValue(null, $this->previousInstances);
        $_SESSION = $this->previousSession;
        RbacService::resetSchemaMemo();
        ShortCache::setEnabled(null);
        Gate::reset();
        parent::tearDown();
    }

    // --- Qualifications : tableur des effectifs et « État des unités » ------------------------------------------

    public function testQualificationsDePlusieursMembresEnUneLecture(): void
    {
        $pdo = $this->qualificationsDatabase(30);
        $repo = new QualificationAwardRepository($pdo);
        $ids = range(1, 30);

        $pdo->log = [];
        $reference = [];
        foreach ($ids as $id) {
            $reference[$id] = $repo->listForUser($id, 7);
        }
        $before = count($pdo->log);

        $pdo->log = [];
        $batched = $repo->listForUsers($ids, 7);
        $after = count($pdo->log);

        self::assertSame(30, $before, 'avant : une lecture par membre');
        self::assertSame(1, $after, 'après : une lecture pour la page');
        self::assertSame($reference, $batched, 'mêmes lignes, mêmes colonnes, même ordre par membre');
        // Le membre 30 n'a aucune qualification dans cette communauté : tableau vide, comme listForUser().
        self::assertSame([], $batched[30]);
        // Sans filtre de communauté (appel listForUser($id) historique).
        self::assertSame($repo->listForUser(3), $repo->listForUsers([3])[3]);
    }

    public function testQualificationsLuesParTranchesDe500(): void
    {
        $pdo = $this->qualificationsDatabase(3);
        $repo = new QualificationAwardRepository($pdo);
        $pdo->log = [];
        $out = $repo->listForUsers(range(1, 1200), 7);
        self::assertSame(3, count($pdo->log), '1200 membres : 3 lectures au lieu de 1200');
        self::assertCount(1200, $out);
        self::assertSame([], $repo->listForUsers([], 7));
        self::assertSame([], $repo->listForUsers([0, -4], 7));
    }

    public function testEtatDesUnitesDerniereActiviteGroupee(): void
    {
        $pdo = $this->qualificationsDatabase(12);
        $pdo->exec('CREATE TABLE personnel_career_journal (id INTEGER PRIMARY KEY, tenant_id INT, user_id INT, effective_at TEXT, created_at TEXT)');
        $pdo->exec("INSERT INTO personnel_career_journal (tenant_id, user_id, effective_at, created_at) VALUES
            (7, 1, NULL, '2026-01-05 10:00:00'), (7, 1, '2026-03-01 08:00:00', '2026-01-06 10:00:00'),
            (7, 2, '2025-12-01 08:00:00', '2025-12-01 08:00:00'), (9, 3, '2026-06-01 00:00:00', '2026-06-01 00:00:00'),
            (7, 5, '0000-00-00 00:00:00', '0000-00-00 00:00:00')");
        $pdo->exec("UPDATE users SET last_login_at = '2026-02-02 02:02:02' WHERE id IN (3, 4, 5)");
        $pdo->exec("UPDATE users SET last_login_at = '0000-00-00 00:00:00' WHERE id = 6");
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $pdo);
        $ids = range(1, 12);

        // Référence : l'ancienne lecture membre par membre (journal de carrière, sinon dernière connexion).
        $pdo->log = [];
        $reference = [];
        foreach ($ids as $id) {
            foreach ([
                'SELECT MAX(COALESCE(effective_at, created_at)) FROM personnel_career_journal WHERE tenant_id = ? AND user_id = ?',
                'SELECT MAX(last_login_at) FROM users WHERE tenant_id = ? AND id = ?',
            ] as $sql) {
                $st = $pdo->prepare($sql);
                $st->execute([7, $id]);
                $v = $st->fetchColumn();
                if (is_string($v) && $v !== '' && !str_starts_with($v, '0000')) {
                    $reference[$id] = $v;
                    break;
                }
            }
        }
        $before = count($pdo->log);

        $service = (new \ReflectionClass(UnitReadinessService::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(UnitReadinessService::class, 'lastActivityForUsers');
        $pdo->log = [];
        $batched = $method->invoke($service, 7, $ids);
        $after = count($pdo->log);

        ksort($reference);
        ksort($batched);
        self::assertSame($reference, $batched);
        self::assertSame('2026-03-01 08:00:00', $batched[1]);
        self::assertSame('2026-02-02 02:02:02', $batched[5], 'date nulle du journal : repli sur la dernière connexion');
        self::assertFalse(isset($batched[6]), 'date nulle partout : pas d’activité');
        self::assertSame(22, $before, 'avant : 1 à 2 lectures par membre (12 membres)');
        self::assertSame(2, $after, 'après : 2 lectures pour l’unité');
        self::assertSame([], $method->invoke($service, 7, []));
    }

    public function testEtatDesUnitesMemeScoreAvecLecturesGroupees(): void
    {
        $pdo = $this->qualificationsDatabase(6);
        $pdo->exec('CREATE TABLE personnel_career_journal (id INTEGER PRIMARY KEY, tenant_id INT, user_id INT, effective_at TEXT, created_at TEXT)');
        $pdo->exec("INSERT INTO personnel_career_journal (tenant_id, user_id, effective_at, created_at) VALUES (7, 2, '2026-03-01 08:00:00', '2026-03-01 08:00:00')");
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $pdo);
        $awards = new QualificationAwardRepository($pdo);
        $temporal = new \App\Services\Personnel\QualificationTemporalStatusService();
        // Dépôt des postes indisponible (comme une communauté sans postes) : les membres viennent de user_units.
        $pdo->sqliteCreateFunction('NOW', static fn (): string => '2026-11-14 12:00:00', 0);
        $pdo->exec('CREATE TABLE units (id INTEGER PRIMARY KEY, tenant_id INT)');
        $pdo->exec('CREATE TABLE user_units (user_id INT, unit_id INT, ended_at TEXT)');
        $pdo->exec('INSERT INTO units VALUES (40, 7)');
        $pdo->exec('ALTER TABLE users ADD COLUMN status TEXT');
        $pdo->exec("UPDATE users SET status = 'active'");
        foreach (range(1, 6) as $memberId) {
            $pdo->exec('INSERT INTO user_units (user_id, unit_id) VALUES (' . $memberId . ', 40)');
        }
        $billets = (new \ReflectionClass(\App\Repositories\OrbatBilletRepository::class))->newInstanceWithoutConstructor();
        $service = new UnitReadinessService($billets, $awards, $temporal, (new \ReflectionClass(\App\Repositories\UnitRepository::class))->newInstanceWithoutConstructor());

        $pdo->log = [];
        $score = $service->scoreForUnit(7, ['id' => 40, 'name' => 'Alpha']);
        $after = count(array_filter($pdo->log, static fn (string $sql): bool => str_contains($sql, 'personnel_qualifications pq')
            || str_contains($sql, 'personnel_career_journal') || str_contains($sql, 'last_login_at')));

        // Référence : décompte membre par membre, comme avant.
        $qualOk = 0;
        $qualTotal = 0;
        foreach (range(1, 6) as $id) {
            foreach ($awards->listForUser($id, 7) as $row) {
                $qualTotal++;
                $qualOk += $temporal->isEffectivelyActive($row) ? 1 : 0;
            }
        }
        self::assertSame($qualTotal, $score['qual_total']);
        self::assertSame($qualOk, $score['qual_ok']);
        self::assertGreaterThan(0, $qualTotal);
        self::assertSame('2026-03-01 08:00:00', $score['last_activity']);
        self::assertSame(0, $score['authorized']);
        self::assertSame(3, $after, 'unité de 6 membres : 3 lectures (avant : 6 qualifications + 11 activités = 17)');
    }

    // --- Droits chargés à chaque requête authentifiée -----------------------------------------------------------

    public function testDroitsDeTousLesRolesEnUneLecture(): void
    {
        $pdo = $this->rbacDatabase();
        $rbac = new RbacService($pdo);

        $pdo->log = [];
        $reference = [];
        foreach ([3, 1, 2] as $roleId) {
            $reference = array_merge($reference, $rbac->loadPermissionsForRole($roleId));
        }
        $reference = array_values(array_unique(array_map('strval', $reference)));
        $before = count($pdo->log);

        $pdo->log = [];
        $merged = $rbac->loadPermissionsForRoles([3, 1, 2, 3, 0]);
        $after = count($pdo->log);

        self::assertSame(3, $before);
        self::assertSame(1, $after);
        self::assertSame($reference, $merged, 'mêmes droits, même ordre (rôle par rôle)');
        self::assertNotContains('admin.system', $merged, 'une habilitation plateforme ne vient jamais d’un rôle de communauté');
        self::assertContains('roster.view', $merged);
        self::assertSame([], $rbac->loadPermissionsForRoles([]));
        self::assertSame($rbac->loadPermissionsForRole(2), $rbac->loadPermissionsForRoles([2]));
    }

    public function testColonneAdministrateurPlateformeVerifieeUneSeuleFois(): void
    {
        $pdo = $this->rbacDatabase();
        $rbac = new RbacService($pdo);
        $schemaReads = static fn (): int => count(array_filter(
            $pdo->log,
            static fn (string $sql): bool => str_contains($sql, 'information_schema') && str_contains($sql, 'is_platform_admin')
        ));

        $pdo->log = [];
        $rbac->setPermissionsForGate([1], 'membre@example.test');
        self::assertFalse(Gate::getInstance()->isPlatformAdmin());
        self::assertTrue(Gate::getInstance()->allows('roster.view'));
        $rbac->setPermissionsForGate([1], 'admin@example.test');
        self::assertTrue(Gate::getInstance()->isPlatformAdmin(), 'le drapeau reste lu en base à chaque appel');
        self::assertTrue(Gate::getInstance()->allows('admin.system'));
        (new RbacService($pdo))->setPermissionsForGate([1], 'membre@example.test');
        self::assertFalse(Gate::getInstance()->isPlatformAdmin());
        self::assertFalse(Gate::getInstance()->allows('admin.system'));

        self::assertSame(1, $schemaReads(), '3 chargements de droits : 1 lecture du schéma au lieu de 3');
    }

    // --- Middlewares exécutés à chaque requête ------------------------------------------------------------------

    public function testSessionHorsCommunauteSystemeNeLitPlusLesAdhesions(): void
    {
        $users = $this->countingUsers([
            ['id' => 5, 'tenant_id' => 1, 'name' => 'Sans organisation', 'slug' => 'default'],
            ['id' => 6, 'tenant_id' => 7, 'name' => 'Alpha', 'slug' => 'alpha'],
        ]);
        $auth = $this->recordingAuth();
        $_SESSION = ['email' => 'membre@example.test', 'tenant_id' => 7];

        // Avant (service sans dépôt des communautés) : une lecture des adhésions à chaque requête, sans effet.
        (new DefaultTenantSessionService($users, $auth))->leaveDefaultIfOtherMembershipsExist();
        self::assertSame(1, $users->calls);
        self::assertSame([], $auth->switched);

        // Après : la communauté courante n'est pas la communauté système, aucune lecture.
        $users->calls = 0;
        $tenants = $this->fakeTenants([7 => ['id' => 7, 'slug' => 'alpha'], 1 => ['id' => 1, 'slug' => 'default']]);
        (new DefaultTenantSessionService($users, $auth, $tenants))->leaveDefaultIfOtherMembershipsExist();
        self::assertSame(0, $users->calls);
        self::assertSame([], $auth->switched);
    }

    public function testSessionSurCommunauteSystemeToujoursDeplacee(): void
    {
        $users = $this->countingUsers([
            ['id' => 5, 'tenant_id' => 1, 'name' => 'Sans organisation', 'slug' => 'default'],
            ['id' => 6, 'tenant_id' => 7, 'name' => 'Alpha', 'slug' => 'alpha'],
        ]);
        $tenants = $this->fakeTenants([7 => ['id' => 7, 'slug' => 'alpha'], 1 => ['id' => 1, 'slug' => 'default']]);
        $_SESSION = ['email' => 'membre@example.test', 'tenant_id' => 1];

        $authBefore = $this->recordingAuth();
        (new DefaultTenantSessionService($users, $authBefore))->leaveDefaultIfOtherMembershipsExist();
        $authAfter = $this->recordingAuth();
        (new DefaultTenantSessionService($users, $authAfter, $tenants))->leaveDefaultIfOtherMembershipsExist();

        self::assertSame([7], $authBefore->switched);
        self::assertSame($authBefore->switched, $authAfter->switched);
        self::assertSame(2, $users->calls);

        // Communauté introuvable ou ligne sans slug : ancien chemin (lecture des adhésions).
        $users->calls = 0;
        $_SESSION['tenant_id'] = 99;
        (new DefaultTenantSessionService($users, $this->recordingAuth(), $tenants))->leaveDefaultIfOtherMembershipsExist();
        self::assertSame(1, $users->calls);

        // Pas d'e-mail en session : rien, comme avant.
        $users->calls = 0;
        $_SESSION = ['tenant_id' => 1];
        (new DefaultTenantSessionService($users, $this->recordingAuth(), $tenants))->leaveDefaultIfOtherMembershipsExist();
        self::assertSame(0, $users->calls);
    }

    public function testControleDuTypeDeCommunauteUtiliseLeDepotPartage(): void
    {
        $tenants = $this->fakeTenants([7 => ['id' => 7, 'slug' => 'alpha', 'tenant_type' => 'full']]);
        $instances = $this->previousInstances;
        $instances[TenantRepository::class] = $tenants;
        (new \ReflectionProperty(Container::class, 'instances'))->setValue(null, $instances);
        $_SESSION = ['tenant_id' => 7];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/dashboard';
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        $passed = 0;
        $response = (new TenantTypeModuleAccessMiddleware())(new Request(), static function () use (&$passed): Response {
            $passed++;

            return Response::json(['ok' => true]);
        });

        self::assertSame(1, $passed, 'communauté complète : la requête passe, comme avant');
        self::assertSame(200, $response->statusCode());
        self::assertSame(1, $tenants->calls, 'la ligne est lue par le dépôt du Container, dont le mémo sert le reste de la page');
        // Un dépôt injecté reste prioritaire (tests existants).
        $own = $this->fakeTenants([7 => ['id' => 7, 'slug' => 'alpha', 'tenant_type' => 'full']]);
        (new TenantTypeModuleAccessMiddleware($own))(new Request(), static fn (): Response => Response::json([]));
        self::assertSame(1, $own->calls);
        self::assertSame(1, $tenants->calls);
    }

    public function testAccueilBackOfficeLitLaCommunauteParLeDepotPartage(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Admin/Organization/OrganizationDashboardController.php');
        self::assertStringNotContainsString('new TenantRepository()', $src, '4 lectures de la ligne tenants par page avant, 0 de plus après');
        self::assertSame(4, substr_count($src, 'Container::get(TenantRepository::class)->'));
    }

    // --- Organigramme du tableau de bord ------------------------------------------------------------------------

    public function testColonnesDuProfilLuesEnUneFois(): void
    {
        $pdo = $this->schemaDatabase([
            ['personnel_profiles', 'character_portrait_path'],
            ['personnel_profiles', 'tenant_id'],
            ['users', 'rank_display'],
        ]);
        $pdo->log = [];
        $columns = DashboardOrbatTree::profileColumns($pdo);
        self::assertSame(['character_portrait_path' => true, 'rank_display' => false, 'tenant_id' => true], $columns);
        self::assertSame(1, count($pdo->log), 'avant : 3 lectures de information_schema par affichage');

        // Schéma illisible : tout à faux, comme l'ancien columnExists().
        $broken = new PerfVagueCountingPdo('sqlite::memory:');
        $broken->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::assertSame(['character_portrait_path' => false, 'rank_display' => false, 'tenant_id' => false], DashboardOrbatTree::profileColumns($broken));
    }

    // --- Outils -------------------------------------------------------------------------------------------------

    private function memoryPdo(): PerfVagueCountingPdo
    {
        $pdo = new PerfVagueCountingPdo('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    /** @param list<array{0: string, 1: string}> $columns */
    private function schemaDatabase(array $columns): PerfVagueCountingPdo
    {
        $pdo = $this->memoryPdo();
        $pdo->sqliteCreateFunction('DATABASE', static fn (): string => 'main', 0);
        $pdo->exec("ATTACH DATABASE ':memory:' AS information_schema");
        $pdo->exec('CREATE TABLE information_schema.COLUMNS (TABLE_SCHEMA TEXT, TABLE_NAME TEXT, COLUMN_NAME TEXT)');
        $pdo->exec('CREATE TABLE information_schema.TABLES (TABLE_SCHEMA TEXT, TABLE_NAME TEXT)');
        $insert = $pdo->prepare("INSERT INTO information_schema.COLUMNS VALUES ('main', ?, ?)");
        foreach ($columns as [$table, $column]) {
            $insert->execute([$table, $column]);
        }

        return $pdo;
    }

    private function rbacDatabase(): PerfVagueCountingPdo
    {
        $pdo = $this->schemaDatabase([['users', 'is_platform_admin']]);
        $pdo->exec('CREATE TABLE permissions (id INTEGER PRIMARY KEY, slug TEXT, scope TEXT)');
        $pdo->exec('CREATE TABLE role_permissions (role_id INT, permission_id INT)');
        $pdo->exec('CREATE TABLE roles (id INTEGER PRIMARY KEY, tenant_id INT, role_layer TEXT, slug TEXT)');
        $pdo->exec('CREATE TABLE site_role_assignments (role_id INT, email_normalized TEXT, revoked_at TEXT)');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT, is_platform_admin INT)');
        $pdo->exec("INSERT INTO permissions (id, slug, scope) VALUES (1, 'roster.view', 'community'), (2, 'roster.edit', 'community'),
            (3, 'admin.system', 'site'), (4, 'events.manage', 'community'), (5, 'documents.view', 'community')");
        $pdo->exec('INSERT INTO role_permissions VALUES (1, 1), (1, 2), (2, 2), (2, 4), (2, 3), (3, 5), (3, 1)');
        $pdo->exec("INSERT INTO users (id, email, is_platform_admin) VALUES (1, 'membre@example.test', 0), (2, ' Admin@Example.test ', 1)");

        return $pdo;
    }

    private function qualificationsDatabase(int $users): PerfVagueCountingPdo
    {
        $pdo = $this->memoryPdo();
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INT, last_login_at TEXT)');
        $pdo->exec('CREATE TABLE personnel_qualifications (id INTEGER PRIMARY KEY, user_id INT, tenant_id INT, definition_id INT,
            qualification_level_id INT, issuer_id INT, is_primary INT, expires_at TEXT, obtained_at TEXT, admin_status TEXT, status TEXT)');
        $pdo->exec('CREATE TABLE personnel_qualification_definitions (id INTEGER PRIMARY KEY, name TEXT, code TEXT, short_name TEXT,
            badge_media_path TEXT, grace_period_days INT, alert_before_expiry_days INT, is_permanent INT, uses_levels INT, category_id INT)');
        $pdo->exec('CREATE TABLE qualification_levels (id INTEGER PRIMARY KEY, name TEXT, short_name TEXT, badge_media_path TEXT)');
        $pdo->exec('CREATE TABLE qualification_issuers (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec('CREATE TABLE qualification_categories (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec("INSERT INTO qualification_categories VALUES (1, 'Combat')");
        $pdo->exec("INSERT INTO qualification_issuers VALUES (1, 'École')");
        $pdo->exec("INSERT INTO qualification_levels VALUES (1, 'Niveau 1', 'N1', NULL), (2, 'Niveau 2', 'N2', NULL)");
        $pdo->exec("INSERT INTO personnel_qualification_definitions VALUES (1, 'Secourisme', 'SEC', 'SC', NULL, 30, 15, 0, 1, 1),
            (2, 'Tireur de précision', 'TP', NULL, NULL, 0, 0, 1, 0, 1), (3, 'Radio', 'RAD', 'RD', NULL, 0, 30, 0, 0, NULL)");
        $user = $pdo->prepare('INSERT INTO users (id, tenant_id) VALUES (?, 7)');
        $award = $pdo->prepare('INSERT INTO personnel_qualifications
            (user_id, tenant_id, definition_id, qualification_level_id, issuer_id, is_primary, expires_at, obtained_at, admin_status, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        for ($id = 1; $id <= $users; $id++) {
            $user->execute([$id]);
            if ($id === 30) {
                // Seulement une qualification d'une autre communauté : exclue par le filtre.
                $award->execute([$id, 9, 1, 1, 1, 0, null, '2025-01-01', 'obtained', 'valid']);
                continue;
            }
            $award->execute([$id, 7, 1, ($id % 2) + 1, 1, $id % 2, '2027-0' . (($id % 8) + 1) . '-01', '2025-0' . (($id % 8) + 1) . '-10', 'obtained', 'valid']);
            $award->execute([$id, $id % 3 === 0 ? null : 7, 2, null, null, 0, null, '2024-1' . ($id % 3) . '-15', 'obtained', 'valid']);
            if ($id % 4 === 0) {
                $award->execute([$id, 7, 3, null, 1, 1, '2024-06-30', '2023-06-30', 'expired', 'expired']);
                $award->execute([$id, 9, 3, null, 1, 1, '2028-06-30', '2026-06-30', 'obtained', 'valid']);
            }
        }

        return $pdo;
    }

    /** @param list<array<string, mixed>> $memberships */
    private function countingUsers(array $memberships): UserRepository
    {
        return new class($memberships) extends UserRepository {
            public int $calls = 0;

            /** @param list<array<string, mixed>> $memberships */
            public function __construct(private array $memberships)
            {
            }

            public function listTenantsForEmail(string $email): array
            {
                $this->calls++;

                return $this->memberships;
            }
        };
    }

    private function recordingAuth(): AuthService
    {
        return new class() extends AuthService {
            /** @var list<int> */
            public array $switched = [];

            public function __construct()
            {
            }

            public function switchToTenant(int $tenantId): bool
            {
                $this->switched[] = $tenantId;

                return true;
            }
        };
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function fakeTenants(array $rows): TenantRepository
    {
        return new class($rows) extends TenantRepository {
            public int $calls = 0;

            /** @param array<int, array<string, mixed>> $rows */
            public function __construct(private array $rows)
            {
            }

            public function findById(int $id): ?array
            {
                $this->calls++;

                return $this->rows[$id] ?? null;
            }
        };
    }
}

/** PDO SQLite qui note chaque requête préparée ou lancée (les exec() de préparation du jeu d'essai ne comptent pas). */
final class PerfVagueCountingPdo extends PDO
{
    /** @var list<string> */
    public array $log = [];

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->log[] = $query;

        return parent::prepare($query, $options);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $this->log[] = $query;

        return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs);
    }
}
