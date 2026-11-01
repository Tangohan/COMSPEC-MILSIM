<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\UserRepository;
use App\Services\Personnel\UnitAssignmentConsistency;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Membre « affecté sur sa fiche mais absent de tous les rosters » après une affectation envoyée deux fois
 * (page d'affectation rouverte pendant le rechargement), et impossible à réaffecter ensuite.
 *
 * Deux connexions SQLite sur le même fichier jouent les deux requêtes ; la seconde passe exactement entre la
 * lecture de l'état et l'écriture de la première (fenêtre de l'ancienne version, lue hors transaction).
 */
final class TeamAssignmentGhostTest extends TestCase
{
    private const TENANT = 3;
    private const OTHER_TENANT = 4;
    private const MEMBER = 7;
    private const TEAM_ALPHA = 31;
    private const TEAM_BRAVO = 32;
    private const OTHER_TEAM = 41;

    private string $file = '';
    private GhostRacingPdo $a;
    private GhostRacingPdo $b;
    private ?PDO $previousPdo = null;

    protected function setUp(): void
    {
        $this->previousPdo = (new ReflectionProperty(Database::class, 'pdo'))->getValue();
        $this->file = (string) tempnam(sys_get_temp_dir(), 'ghost');
        $this->a = $this->connect();
        $this->b = $this->connect();
        foreach ([
            'CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, settings TEXT)',
            'CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER, status TEXT, display_name TEXT, callsign TEXT)',
            'CREATE TABLE units (id INTEGER PRIMARY KEY, tenant_id INTEGER, name TEXT, slug TEXT, type TEXT, commander_user_id INTEGER)',
            'CREATE TABLE personnel_assignments (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, unit_id INTEGER, role_name TEXT,
                is_primary INTEGER DEFAULT 1, started_at TEXT, ended_at TEXT, status TEXT DEFAULT \'active\', created_at TEXT, updated_at TEXT)',
            'CREATE TABLE user_units (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, unit_id INTEGER, is_primary INTEGER DEFAULT 0,
                assigned_by INTEGER, assigned_at TEXT, ended_at TEXT, assignment_type TEXT, notes TEXT)',
            'CREATE TABLE personnel_profiles (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, tenant_id INTEGER, primary_unit_id INTEGER,
                created_at TEXT, updated_at TEXT)',
            "INSERT INTO tenants VALUES (3, 'Task Force', '{}'), (4, 'Autre communauté', '{}')",
            "INSERT INTO users VALUES (7, 3, 'active', 'Viper', 'VIPER'), (8, 3, 'active', 'Ghost', 'GHOST')",
            "INSERT INTO units VALUES (31, 3, 'Équipe Alpha', 'alpha', 'team', NULL), (32, 3, 'Équipe Bravo', 'bravo', 'team', NULL),
                (41, 4, 'Équipe d’ailleurs', 'ailleurs', 'team', NULL)",
            "INSERT INTO personnel_profiles (user_id, tenant_id, primary_unit_id, created_at) VALUES (7, 3, NULL, '2026-01-01')",
        ] as $sql) {
            $this->a->exec($sql);
        }
        (new ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->a);
        $this->resetUserRepositoryCaches();
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->previousPdo);
        $this->resetUserRepositoryCaches();
        unset($this->a, $this->b);
        gc_collect_cycles();
        @unlink($this->file);
    }

    public function testStaleFormSentWhileTheAssignmentIsSavedLeavesProfileAndRostersInAgreement(): void
    {
        // Requête B : le formulaire rouvert pendant le rechargement montrait encore « sans unité ».
        // Requête A : l'affectation à l'équipe Alpha, enregistrée pendant que B est en cours.
        $this->b->beforeBegin = function (): void {
            $this->repo($this->a)->syncPrimaryAssignmentFromDossier(self::MEMBER, self::TEAM_ALPHA, 'Membre', null, self::TENANT);
        };
        $this->repo($this->b)->syncPrimaryAssignmentFromDossier(self::MEMBER, null, 'Membre', null, self::TENANT);

        $this->assertCoherent();
    }

    public function testDoubleSubmissionOfTheSameTeamCreatesOneAssignmentOnly(): void
    {
        $this->a->beforeBegin = function (): void {
            $this->repo($this->b)->syncPrimaryAssignmentFromDossier(self::MEMBER, self::TEAM_ALPHA, 'Membre', null, self::TENANT);
        };
        $this->repo($this->a)->syncPrimaryAssignmentFromDossier(self::MEMBER, self::TEAM_ALPHA, 'Membre', null, self::TENANT);

        self::assertSame(1, $this->scalar("SELECT COUNT(*) FROM personnel_assignments WHERE user_id = 7 AND status = 'active'"));
        self::assertSame(1, $this->scalar('SELECT COUNT(*) FROM user_units WHERE user_id = 7 AND ended_at IS NULL'));
        self::assertSame([self::TEAM_ALPHA], $this->rosterUnits());
        $this->assertCoherent();

        // Changer d'équipe ensuite ne laisse aucune affectation fantôme derrière.
        $this->repo($this->a)->syncPrimaryAssignmentFromDossier(self::MEMBER, self::TEAM_BRAVO, 'Membre', null, self::TENANT);
        self::assertSame([self::TEAM_BRAVO], $this->profileUnits());
        self::assertSame([self::TEAM_BRAVO], $this->rosterUnits());
        $this->assertCoherent();
    }

    public function testStuckMemberIsPutOnTheTeamByResubmittingTheForm(): void
    {
        // État laissé en production : affectation active, aucune ligne d'effectif, dossier sans unité.
        $this->a->exec("INSERT INTO personnel_assignments (user_id, unit_id, role_name, is_primary, started_at, status, created_at)
            VALUES (7, 31, 'Membre', 1, '2026-10-30', 'active', '2026-10-30 20:00:00')");
        self::assertSame([self::TEAM_ALPHA], $this->profileUnits());

        $this->repo($this->a)->syncPrimaryAssignmentFromDossier(self::MEMBER, self::TEAM_ALPHA, 'Membre', null, self::TENANT);

        self::assertContains(self::MEMBER, $this->users()->getIdsByUnit(self::TEAM_ALPHA));
        self::assertSame([self::TEAM_ALPHA => 1], $this->users()->countActiveByUnits([self::TEAM_ALPHA]));
        self::assertSame(self::TEAM_ALPHA, $this->profileUnit());
        $this->assertCoherent();
    }

    public function testAssignmentInOneCommunityLeavesTheOtherCommunityUntouched(): void
    {
        $this->a->exec("INSERT INTO personnel_assignments (user_id, unit_id, role_name, is_primary, started_at, status, created_at)
            VALUES (7, 41, 'Membre', 1, '2026-01-01', 'active', '2026-01-01')");
        $this->a->exec("INSERT INTO user_units (user_id, unit_id, is_primary, assigned_at, assignment_type) VALUES (7, 41, 1, '2026-01-01', 'Membre')");

        $this->repo($this->a)->syncPrimaryAssignmentFromDossier(self::MEMBER, self::TEAM_ALPHA, 'Membre', null, self::TENANT);
        // Une unité d'une autre communauté envoyée dans le formulaire est ignorée.
        $this->repo($this->a)->replaceActiveAssignmentsFromDossier(self::MEMBER, [
            ['unit_id' => self::OTHER_TEAM, 'role_name' => 'Membre', 'is_primary' => true],
            ['unit_id' => self::TEAM_ALPHA, 'role_name' => 'Membre'],
        ], self::TENANT);

        self::assertSame([self::TEAM_ALPHA], $this->profileUnits());
        self::assertSame([self::OTHER_TEAM], array_map('intval', array_column(
            $this->repo($this->a)->listActiveForUserResolved(self::MEMBER, self::OTHER_TENANT),
            'unit_id'
        )));
        self::assertContains(self::MEMBER, $this->users()->getIdsByUnit(self::OTHER_TEAM));
        $this->assertCoherent();
    }

    public function testRepairFixesEveryHalfStateOnceAndThenFindsNothing(): void
    {
        // Membre 7 : affectation active en double, sans ligne d'effectif, dossier vide.
        $this->a->exec("INSERT INTO personnel_assignments (user_id, unit_id, role_name, is_primary, started_at, status, created_at) VALUES
            (7, 31, 'Membre', 1, '2026-10-30', 'active', '2026-10-30'), (7, 31, 'Membre', 1, '2026-10-30', 'active', '2026-10-30')");
        // Membre 8 : seulement l'unité de son dossier (écriture interrompue après la fiche).
        $this->a->exec("INSERT INTO personnel_profiles (user_id, tenant_id, primary_unit_id, created_at) VALUES (8, 3, 32, '2026-01-01')");

        $c = new UnitAssignmentConsistency($this->a);
        self::assertSame([
            ['user_id' => 7, 'tenant_id' => self::TENANT],
            ['user_id' => 8, 'tenant_id' => self::TENANT],
        ], $c->findInconsistent());
        self::assertSame([['user_id' => 7, 'tenant_id' => self::TENANT]], $c->findInconsistent(self::MEMBER, self::TENANT));

        $log7 = $c->repair(self::MEMBER, self::TENANT);
        $log8 = $c->repair(8, self::TENANT);
        self::assertStringContainsString('double', implode(' ; ', $log7));
        self::assertStringContainsString('ajouté à l’effectif de Équipe Alpha', implode(' ; ', $log7));
        self::assertStringContainsString('dossier', implode(' ; ', $log8));

        self::assertSame([], $c->findInconsistent());
        self::assertSame([], $c->repair(self::MEMBER, self::TENANT));
        self::assertSame([self::TEAM_ALPHA], $this->rosterUnits());
        self::assertContains(8, $this->users()->getIdsByUnit(self::TEAM_BRAVO));
        $this->assertCoherent();
    }

    public function testMigrationStepRepairsAndLogsThenChangesNothingOnSecondRun(): void
    {
        $this->a->exec("INSERT INTO personnel_assignments (user_id, unit_id, role_name, is_primary, started_at, status, created_at)
            VALUES (7, 31, 'Membre', 1, '2026-10-30', 'active', '2026-10-30')");

        $first = $this->runStep();
        self::assertStringContainsString('Membre #7 (communauté #3) : ajouté à l’effectif de Équipe Alpha', $first);
        self::assertStringContainsString('1 membre(s) réparé(s)', $first);
        self::assertStringContainsString('0 membre(s) réparé(s) sur 0 signalé(s)', $this->runStep());
        $this->assertCoherent();
    }

    private function runStep(): string
    {
        $pdo = $this->a;
        $root = dirname(__DIR__, 2);
        ob_start();
        try {
            require $root . '/database/steps/3345_unit_assignment_repair.php';
        } finally {
            $out = (string) ob_get_clean();
        }

        return $out;
    }

    /** Fiche (affectations résolues), pages Équipes (effectif de chaque unité) et tableur (unité du dossier) d'accord. */
    private function assertCoherent(): void
    {
        $profile = $this->profileUnits();
        self::assertSame($profile, $this->rosterUnits(), 'Fiche et effectifs des équipes divergent.');
        $primary = null;
        foreach ($this->repo($this->a)->listActiveForUserResolved(self::MEMBER, self::TENANT) as $row) {
            if ((int) ($row['is_primary'] ?? 0) === 1) {
                $primary = (int) $row['unit_id'];
            }
        }
        self::assertSame($primary, $this->profileUnit(), 'Unité du dossier (tableur des effectifs) différente de l’affectation principale.');
        $orbat = $this->repo($this->a)->primaryUnitIdByUserForTenant(self::TENANT);
        self::assertSame($primary, $orbat[self::MEMBER] ?? null, 'ORBAT différent de la fiche.');
        self::assertSame(0, $this->scalar("SELECT COUNT(*) FROM (SELECT unit_id FROM personnel_assignments WHERE user_id = 7 AND status = 'active'
            GROUP BY unit_id HAVING COUNT(*) > 1) d"), 'Affectation active en double.');
    }

    /** @return list<int> */
    private function profileUnits(): array
    {
        $ids = array_map('intval', array_column($this->repo($this->a)->listActiveForUserResolved(self::MEMBER, self::TENANT), 'unit_id'));
        sort($ids);

        return $ids;
    }

    /** @return list<int> */
    private function rosterUnits(): array
    {
        $out = [];
        foreach ([self::TEAM_ALPHA, self::TEAM_BRAVO] as $unitId) {
            if (in_array(self::MEMBER, $this->users()->getIdsByUnit($unitId), true)) {
                $out[] = $unitId;
            }
        }

        return $out;
    }

    private function profileUnit(): ?int
    {
        $v = $this->a->query('SELECT primary_unit_id FROM personnel_profiles WHERE user_id = 7 AND tenant_id = 3')->fetchColumn();

        return $v === null || $v === false ? null : (int) $v;
    }

    private function scalar(string $sql): int
    {
        return (int) $this->a->query($sql)->fetchColumn();
    }

    private function repo(PDO $pdo): PersonnelAssignmentRepository
    {
        $property = new ReflectionProperty(Database::class, 'pdo');
        $previous = $property->getValue();
        $property->setValue(null, $pdo);
        try {
            return new PersonnelAssignmentRepository($pdo);
        } finally {
            $property->setValue(null, $previous);
        }
    }

    private function users(): UserRepository
    {
        return new UserRepository($this->a);
    }

    private function resetUserRepositoryCaches(): void
    {
        foreach (['tableExistsCache' => [], 'columnExistsCache' => [], 'hasUserUnitsTable' => null] as $name => $value) {
            (new ReflectionProperty(UserRepository::class, $name))->setValue(null, $value);
        }
    }

    private function connect(): GhostRacingPdo
    {
        $pdo = new GhostRacingPdo('sqlite:' . $this->file, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
        // SQLite déguisé en MySQL : NOW(), CURDATE(), DATABASE() et information_schema.TABLES.
        $pdo->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'), 0);
        $pdo->sqliteCreateFunction('CURDATE', static fn (): string => date('Y-m-d'), 0);
        $pdo->sqliteCreateFunction('DATABASE', static fn (): string => 'main', 0);
        $pdo->exec("ATTACH DATABASE ':memory:' AS information_schema");
        $pdo->exec('CREATE TABLE information_schema.TABLES (TABLE_SCHEMA TEXT, TABLE_NAME TEXT)');
        $pdo->exec("INSERT INTO information_schema.TABLES VALUES ('main', 'personnel_assignments'), ('main', 'user_units'),
            ('main', 'personnel_profiles'), ('main', 'units'), ('main', 'users')");

        return $pdo;
    }
}

/** Connexion dont la prochaine ouverture de transaction laisse passer une autre requête juste avant. */
final class GhostRacingPdo extends PDO
{
    public ?\Closure $beforeBegin = null;

    public function beginTransaction(): bool
    {
        $hook = $this->beforeBegin;
        $this->beforeBegin = null;
        if ($hook !== null) {
            $hook();
        }

        return parent::beginTransaction();
    }
}
