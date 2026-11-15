<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Repositories\EnlistmentRepository;
use App\Repositories\MemberIntegrationPackageRepository as Repo;
use App\Services\MemberIntegration\IntegrationPorts;
use App\Services\MemberIntegration\MemberIntegrationPackageService as Service;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\MysqlDdlSqlitePdo;

/**
 * Paquet d'intégration : application complète, rejeu sans doublon, atomicité, plafond des droits, comportement
 * d'avant sans paquet, isolation par communauté, journal. Vraie base SQLite construite par l'étape de migration, vrais
 * dépôts et vrai service ; seules les attributions de la plateforme (qualifications, formations, intégration) sont
 * remplacées par une implémentation qui écrit dans la même base et peut échouer à la demande.
 */
final class MemberIntegrationPackageTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    private MysqlDdlSqlitePdo $pdo;
    private ?PDO $savedPdo = null;
    private Repo $repo;
    private TestPorts $ports;
    private Service $service;

    protected function setUp(): void
    {
        $this->pdo = new MysqlDdlSqlitePdo();
        $this->pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT)');
        $this->pdo->exec("INSERT INTO tenants (id, name) VALUES (1, 'Alpha'), (2, 'Bravo')");
        $this->pdo->exec('CREATE TABLE personnel_qualification_definitions (id INTEGER PRIMARY KEY, tenant_id INTEGER, name TEXT, archived_at TEXT)');
        $this->pdo->exec('CREATE TABLE training_courses (id INTEGER PRIMARY KEY, tenant_id INTEGER, title TEXT)');
        $this->pdo->exec('CREATE TABLE enlistments (id INTEGER PRIMARY KEY, tenant_id INTEGER, status TEXT, submitter_user_id INTEGER, recruitment_opening_id INTEGER, recruitment_rp_json TEXT)');
        $this->pdo->exec('CREATE TABLE t_member (user_id INTEGER PRIMARY KEY, grade_id INTEGER, unit_id INTEGER, job_id INTEGER, roles TEXT, skip_welcome INTEGER)');
        $this->pdo->exec('CREATE TABLE t_grants (tenant_id INTEGER, user_id INTEGER, definition_id INTEGER, status TEXT, valid_months INTEGER)');
        $this->pdo->exec('CREATE TABLE t_enrollments (tenant_id INTEGER, user_id INTEGER, course_id INTEGER)');
        $this->pdo->exec('CREATE TABLE t_journal (tenant_id INTEGER, user_id INTEGER, enlistment_id INTEGER, actor_id INTEGER, title TEXT, detail TEXT)');
        $this->pdo->exec('CREATE TABLE t_mails (tenant_id INTEGER, user_id INTEGER, courses TEXT)');
        $this->pdo->exec("INSERT INTO personnel_qualification_definitions (id, tenant_id, name) VALUES (10, 1, 'Secourisme'), (11, 1, 'Tireur'), (12, 1, 'Pilote Hélico'), (90, 2, 'Autre communauté')");
        $this->pdo->exec("INSERT INTO training_courses (id, tenant_id, title) VALUES (20, 1, 'Bases Athena'), (21, 1, 'Radio'), (91, 2, 'Cours autre communauté')");
        $this->pdo->exec("INSERT INTO enlistments (id, tenant_id, status, submitter_user_id, recruitment_opening_id) VALUES (1, 1, 'reviewed', 100, 7), (2, 1, 'reviewed', 101, 0), (3, 2, 'reviewed', 200, 0), (4, 1, 'pending', 102, 0)");
        $this->pdo->changes = 0;
        $this->runStep($this->pdo);

        $prop = new \ReflectionProperty(Database::class, 'pdo');
        $current = $prop->getValue();
        $this->savedPdo = $current instanceof PDO ? $current : null;
        $prop->setValue(null, $this->pdo);

        $this->repo = new Repo($this->pdo);
        $this->ports = new TestPorts($this->pdo);
        $this->service = new Service($this->pdo, $this->repo, new EnlistmentRepository(), $this->ports);
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->savedPdo);
    }

    private function runStep(PDO $pdo): string
    {
        $root = self::ROOT;
        $migrationFlush = static function (): void {
        };
        $migrationEnsurePdo = static function (): void {
        };
        require $root . '/database/steps/_context.php';
        ob_start();
        try {
            require $root . '/database/steps/4640_member_integration_packages.php';
        } finally {
            $out = (string) ob_get_clean();
        }

        return $out;
    }

    /** Paquet « Recrue » de la communauté 1 : grade, unité, emploi, accès, deux qualifications, deux formations, mot d'accueil. */
    private function recruitPackage(array $override = []): int
    {
        return $this->repo->save(1, 0, $override + [
            'name' => 'Recrue',
            'is_default' => true,
            'grade_id' => 3,
            'unit_id' => 5,
            'personnel_job_role_id' => 8,
            'role_ids' => [2],
            'welcome_message' => 'Rejoignez le canal #accueil.',
        ], [
            ['item_type' => 'qualification', 'ref_id' => 10, 'status' => 'obtained', 'valid_months' => 12],
            ['item_type' => 'qualification', 'ref_id' => 11, 'status' => 'in_training'],
            ['item_type' => 'course', 'ref_id' => 20],
            ['item_type' => 'course', 'ref_id' => 21],
        ], 1);
    }

    /** @return array{package_id: int, qualification_ids: list<int>, course_ids: list<int>} */
    private function full(int $packageId): array
    {
        return ['package_id' => $packageId, 'qualification_ids' => [10, 11], 'course_ids' => [20, 21]];
    }

    private const OPTIONS = ['first_name' => 'Jean', 'last_name' => 'Dupont', 'grade_id' => 3, 'unit_id' => 5, 'personnel_job_role_id' => 8, 'role_ids' => [2]];
    private const CAPS = ['qualify' => true, 'enroll' => true];

    private function rows(string $table): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }

    public function testFullApplicationGivesEverythingAtOnceAndKeepsAJournal(): void
    {
        $id = $this->recruitPackage();
        $r = $this->service->apply(1, 1, 50, self::OPTIONS, $this->full($id), self::CAPS);

        self::assertTrue($r['ok'], (string) ($r['message'] ?? ''));
        $m = $this->pdo->query('SELECT * FROM t_member WHERE user_id = 100')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(3, (int) $m['grade_id']);
        self::assertSame(5, (int) $m['unit_id']);
        self::assertSame(1, (int) $m['skip_welcome'], 'le récapitulatif du paquet tient lieu de message de bienvenue');
        self::assertSame(2, $this->rows('t_grants'));
        self::assertSame(2, $this->rows('t_enrollments'));
        $grants = $this->pdo->query('SELECT definition_id, status, valid_months FROM t_grants ORDER BY definition_id')->fetchAll(PDO::FETCH_ASSOC);
        self::assertSame('obtained', $grants[0]['status']);
        self::assertSame(12, (int) $grants[0]['valid_months']);
        self::assertSame('in_training', $grants[1]['status']);
        self::assertSame(1, $this->rows('t_journal'));
        $j = $this->pdo->query('SELECT * FROM t_journal')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(50, (int) $j['actor_id'], 'le journal dit qui');
        self::assertStringContainsString('Secourisme', (string) $j['detail']);
        self::assertStringContainsString('Radio', (string) $j['detail']);
        self::assertSame(1, $this->rows('t_mails'), 'un seul récapitulatif au nouveau membre');
        $app = $this->repo->application(1, 1);
        self::assertNotNull($app);
        self::assertNotNull($app['welcome_sent_at']);
    }

    public function testReplayDoesNotDuplicateAnythingNorResendTheWelcome(): void
    {
        $id = $this->recruitPackage();
        $this->service->apply(1, 1, 50, self::OPTIONS, $this->full($id), self::CAPS);
        $again = $this->service->apply(1, 1, 50, self::OPTIONS, $this->full($id), self::CAPS);

        self::assertTrue($again['ok']);
        self::assertTrue($again['unchanged']);
        self::assertSame(2, $this->rows('t_grants'));
        self::assertSame(2, $this->rows('t_enrollments'));
        self::assertSame(1, $this->rows('t_journal'));
        self::assertSame(1, $this->rows('t_mails'));
        self::assertSame(1, $this->rows('member_integration_applications'));
    }

    public function testAFailingStepUndoesEverythingAndSaysWhich(): void
    {
        $id = $this->recruitPackage();
        $this->ports->failOnQualification = 11;
        $r = $this->service->apply(1, 1, 50, self::OPTIONS, $this->full($id), self::CAPS);

        self::assertFalse($r['ok']);
        self::assertStringContainsString('Rien n’a été appliqué', (string) $r['message']);
        self::assertStringContainsString('Tireur', (string) $r['message']);
        self::assertSame(0, $this->rows('t_member'), 'le grade et l’unité du parcours sont annulés aussi');
        self::assertSame(0, $this->rows('t_grants'), 'la première qualification, déjà délivrée, est annulée');
        self::assertSame(0, $this->rows('t_enrollments'));
        self::assertSame(0, $this->rows('member_integration_applications'));
        self::assertSame(0, $this->rows('t_journal'));
        self::assertSame(0, $this->rows('t_mails'));
        self::assertFalse($this->pdo->inTransaction());

        // Corrigé, on relance : tout passe, une seule fois.
        $this->ports->failOnQualification = 0;
        $ok = $this->service->apply(1, 1, 50, self::OPTIONS, $this->full($id), self::CAPS);
        self::assertTrue($ok['ok']);
        self::assertSame(2, $this->rows('t_grants'));
    }

    public function testAFailingBaseStepAppliesNothingOfThePackage(): void
    {
        $id = $this->recruitPackage();
        $this->ports->failBase = true;
        $r = $this->service->apply(1, 1, 50, self::OPTIONS, $this->full($id), self::CAPS);

        self::assertFalse($r['ok']);
        self::assertSame(0, $this->rows('t_grants'));
        self::assertSame(0, $this->rows('t_enrollments'));
        self::assertSame(0, $this->rows('member_integration_applications'));
    }

    public function testAQualificationThatCannotBeAwardedIsRefusedBeforeAnyWrite(): void
    {
        $id = $this->recruitPackage();
        $this->ports->blockers = [11 => 'elle ne se délivre pas à la main.'];
        $r = $this->service->apply(1, 1, 50, self::OPTIONS, $this->full($id), self::CAPS);

        self::assertFalse($r['ok']);
        self::assertStringContainsString('Tireur', (string) $r['message']);
        self::assertSame(0, $this->rows('t_member'), 'aucune écriture : le contrôle précède tout');
        self::assertSame(0, $this->ports->baseCalls);
    }

    public function testRightsCeilingLeavesWhatTheRecruiterMayNotGiveToAManager(): void
    {
        $id = $this->recruitPackage();
        $r = $this->service->apply(1, 1, 50, self::OPTIONS, $this->full($id), ['qualify' => false, 'enroll' => true]);

        self::assertTrue($r['ok']);
        self::assertSame(0, $this->rows('t_grants'), 'pas d’escalade : sans droit, aucune qualification');
        self::assertSame(2, $this->rows('t_enrollments'));
        self::assertContains('Secourisme', $r['applied']['pending']);
        self::assertContains('Tireur', $r['applied']['pending']);
        self::assertStringContainsString('À attribuer par un responsable : Secourisme', (string) $this->pdo->query('SELECT detail FROM t_journal')->fetchColumn());

        // Un responsable habilité rejoue : seules les qualifications manquantes sont données.
        $later = $this->service->apply(1, 1, 60, self::OPTIONS, $this->full($id), self::CAPS);
        self::assertTrue($later['ok']);
        self::assertSame(2, $this->rows('t_grants'));
        self::assertSame(2, $this->rows('t_enrollments'));
        $app = $this->repo->application(1, 1);
        self::assertSame([], $app['applied']['pending']);
    }

    public function testWithoutAPackageTheOldPathRunsUnchanged(): void
    {
        $this->recruitPackage();
        $r = $this->service->apply(1, 2, 50, self::OPTIONS, ['package_id' => 0], self::CAPS);

        self::assertTrue($r['ok']);
        self::assertSame(1, $this->ports->baseCalls);
        self::assertSame(0, (int) $this->pdo->query('SELECT skip_welcome FROM t_member WHERE user_id = 101')->fetchColumn(), 'le message de bienvenue du parcours reste envoyé');
        self::assertSame(0, $this->rows('t_grants'));
        self::assertSame(0, $this->rows('t_enrollments'));
        self::assertSame(0, $this->rows('member_integration_applications'));
        self::assertSame(0, $this->rows('t_journal'));
        self::assertSame(0, $this->rows('t_mails'));
    }

    public function testNothingChangesWhenNoPackageIsDefined(): void
    {
        $preview = $this->service->preview(1, ['id' => 2, 'recruitment_opening_id' => 0], 0, self::CAPS);

        self::assertNull($preview['package']);
        self::assertFalse($preview['hasPackages']);
        self::assertSame([], $preview['qualifications']);
    }

    public function testAnotherCommunitysPackageOrItemsAreNeverUsed(): void
    {
        $foreign = $this->repo->save(2, 0, ['name' => 'Autre', 'is_default' => true], [['item_type' => 'qualification', 'ref_id' => 90]], 9);
        $r = $this->service->apply(1, 1, 50, self::OPTIONS, ['package_id' => $foreign], self::CAPS);
        self::assertFalse($r['ok']);
        self::assertSame(0, $this->ports->baseCalls);
        self::assertNull($this->repo->find(1, $foreign));
        self::assertNull($this->repo->resolve(1, 0, 0), 'le paquet par défaut d’une autre communauté n’est jamais retenu');

        // Qualification ou formation d'une autre communauté glissée dans un paquet : refusée avant toute écriture.
        $sneaky = $this->repo->save(1, 0, ['name' => 'Piégé'], [['item_type' => 'qualification', 'ref_id' => 90], ['item_type' => 'course', 'ref_id' => 91]], 1);
        $r2 = $this->service->apply(1, 1, 50, self::OPTIONS, ['package_id' => $sneaky, 'qualification_ids' => [90], 'course_ids' => [91]], self::CAPS);
        self::assertFalse($r2['ok']);
        self::assertSame(0, $this->rows('t_grants'));
        self::assertSame(0, $this->ports->baseCalls);

        // Candidature d'une autre communauté, ou pas encore acceptée.
        $id = $this->recruitPackage();
        self::assertFalse($this->service->apply(1, 3, 50, self::OPTIONS, $this->full($id), self::CAPS)['ok']);
        self::assertFalse($this->service->apply(1, 4, 50, self::OPTIONS, $this->full($id), self::CAPS)['ok']);
        self::assertSame(0, $this->ports->baseCalls);
    }

    public function testAnItemOutsideThePackageCannotBeForgedIntoTheForm(): void
    {
        $id = $this->recruitPackage();
        $r = $this->service->apply(1, 1, 50, self::OPTIONS, ['package_id' => $id, 'qualification_ids' => [12], 'course_ids' => []], self::CAPS);

        self::assertFalse($r['ok']);
        self::assertSame(0, $this->rows('t_grants'));
        self::assertSame(0, $this->ports->baseCalls);
    }

    public function testTheRecruiterMayGiveLessThanThePackage(): void
    {
        $id = $this->recruitPackage();
        $r = $this->service->apply(1, 1, 50, self::OPTIONS, ['package_id' => $id, 'qualification_ids' => [10], 'course_ids' => []], self::CAPS);

        self::assertTrue($r['ok']);
        self::assertSame(1, $this->rows('t_grants'));
        self::assertSame(0, $this->rows('t_enrollments'));
    }

    public function testPackageIsChosenByOpeningThenUnitThenDefault(): void
    {
        $default = $this->recruitPackage();
        $unit = $this->repo->save(1, 0, ['name' => 'Unité 5', 'match_unit_id' => 5], [], 1);
        $pilot = $this->repo->save(1, 0, ['name' => 'Pilote', 'opening_id' => 7], [], 1);
        $off = $this->repo->save(1, 0, ['name' => 'Inactif', 'opening_id' => 8, 'is_active' => false], [], 1);

        self::assertSame($pilot, (int) $this->repo->resolve(1, 7, 5)['id'], 'le candidat pilote reçoit le paquet pilote');
        self::assertSame($unit, (int) $this->repo->resolve(1, 99, 5)['id']);
        self::assertSame($default, (int) $this->repo->resolve(1, 99, 6)['id']);
        self::assertSame($default, (int) $this->repo->resolve(1, 8, 0)['id'], 'un paquet désactivé n’est jamais retenu');
        self::assertNotSame($off, (int) $this->repo->resolve(1, 8, 0)['id']);
    }

    public function testOnlyOneDefaultPackagePerCommunityAndItemsAreReplaced(): void
    {
        $a = $this->recruitPackage();
        $b = $this->repo->save(1, 0, ['name' => 'B', 'is_default' => true], [], 1);
        self::assertSame(0, (int) $this->repo->find(1, $a)['is_default']);
        self::assertSame(1, (int) $this->repo->find(1, $b)['is_default']);

        $this->repo->save(1, $a, ['name' => 'Recrue 2', 'role_ids' => [2, 4]], [['item_type' => 'course', 'ref_id' => 20], ['item_type' => 'course', 'ref_id' => 20], ['item_type' => 'weird', 'ref_id' => 1]], 1);
        self::assertSame([2, 4], $this->repo->find(1, $a)['role_ids']);
        self::assertCount(1, $this->repo->items(1, $a), 'doublon et type inconnu écartés');
        self::assertNull($this->repo->find(2, $a), 'portée communauté');
        self::assertFalse($this->repo->delete(2, $a));
        self::assertTrue($this->repo->delete(1, $a));
    }

    public function testPreviewListsWhatWillBeAppliedWithRightsAndBlockers(): void
    {
        $this->recruitPackage();
        $this->ports->blockers = [11 => 'auto seulement'];
        $p = $this->service->preview(1, ['id' => 1, 'recruitment_opening_id' => 0], 0, ['qualify' => false, 'enroll' => true]);

        self::assertSame('Recrue', $p['package']['name']);
        self::assertCount(2, $p['qualifications']);
        self::assertTrue($p['qualifications'][0]['needs_rights']);
        self::assertFalse($p['qualifications'][0]['blocked']);
        self::assertTrue($p['qualifications'][1]['blocked']);
        self::assertCount(2, $p['courses']);
        self::assertFalse($p['courses'][0]['needs_rights']);
        self::assertTrue($p['hasPackages']);
    }

    public function testMigrationIsRerunnableAndDeclared(): void
    {
        $empty = new MysqlDdlSqlitePdo();
        self::assertStringContainsString('Tables créées', $this->runStep($empty));
        $empty->changes = 0;
        self::assertStringContainsString('déjà présentes', $this->runStep($empty));
        self::assertSame(0, $empty->changes);
        $registry = (string) file_get_contents(self::ROOT . '/database/steps/registry.php');
        self::assertStringContainsString("'id' => 'member_integration_packages'", $registry);
        self::assertStringContainsString("'file' => '4640_member_integration_packages.php'", $registry);
        self::assertFileExists(self::ROOT . '/migrations/rollback/member_integration_packages_down.sql');
        self::assertStringNotContainsString('ADD UNIQUE KEY', (string) file_get_contents(self::ROOT . '/database/steps/4640_member_integration_packages.php'));
    }

    public function testRoutesWiringAndScreenAreDeclared(): void
    {
        $routes = (string) file_get_contents(self::ROOT . '/routes/web.php');
        self::assertStringContainsString("'/back-office/recruitments/paquets-integration'", $routes);
        self::assertLessThan(
            strpos($routes, "'/back-office/recruitments/{id}', [AdminRecruitmentsController::class, 'show']"),
            strpos($routes, "'/back-office/recruitments/paquets-integration'"),
            'la route fixe passe avant « {id} »'
        );
        $container = (string) file_get_contents(self::ROOT . '/app/Core/Container.php');
        self::assertStringContainsString('MemberIntegrationPackageService::class', $container);
        self::assertStringContainsString('AdminIntegrationPackagesController::class', $container);
        $view = (string) file_get_contents(self::ROOT . '/views/admin/recruitments/onboarding.php');
        self::assertStringContainsString('Ce qui sera appliqué', $view);
        self::assertStringContainsString('Définissez ce que reçoit un nouveau membre', $view);
        self::assertStringNotContainsString('rec-onb__eyebrow">Ce qui sera appliqué', $view);
        $admin = (string) file_get_contents(self::ROOT . '/views/admin/recruitments/integration_packages.php');
        self::assertStringContainsString('Définissez ce que reçoit un nouveau membre', $admin);
        self::assertStringNotContainsString('uppercase tracking', $admin, 'aucun libellé en capitales au-dessus d’un titre');
        $controller = (string) file_get_contents(self::ROOT . '/app/Controllers/Admin/AdminIntegrationPackagesController.php');
        self::assertStringContainsString('Csrf::validate', $controller);
    }
}

/** Plateforme simulée dans la même base : écrit ce que les vrais services écriraient, et échoue à la demande. */
final class TestPorts implements IntegrationPorts
{
    public int $failOnQualification = 0;
    public bool $failBase = false;
    /** @var array<int, string> */
    public array $blockers = [];
    public int $baseCalls = 0;

    public function __construct(private PDO $pdo)
    {
    }

    public function ensureAccount(int $tenantId, int $enlistmentId, int $actorId): int
    {
        return (int) $this->pdo->query('SELECT submitter_user_id FROM enlistments WHERE id = ' . $enlistmentId . ' AND tenant_id = ' . $tenantId)->fetchColumn();
    }

    public function applyBase(int $tenantId, int $enlistmentId, int $actorId, array $options): array
    {
        $this->baseCalls++;
        $userId = $this->ensureAccount($tenantId, $enlistmentId, $actorId);
        $this->pdo->prepare('DELETE FROM t_member WHERE user_id = ?')->execute([$userId]);
        $this->pdo->prepare('INSERT INTO t_member (user_id, grade_id, unit_id, job_id, roles, skip_welcome) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$userId, (int) ($options['grade_id'] ?? 0), (int) ($options['unit_id'] ?? 0), (int) ($options['personnel_job_role_id'] ?? 0), json_encode($options['role_ids'] ?? []), !empty($options['skip_welcome']) ? 1 : 0]);
        if ($this->failBase) {
            return ['ok' => false, 'message' => 'Unité introuvable.'];
        }

        return ['ok' => true, 'message' => null, 'user_id' => $userId];
    }

    public function qualificationBlocker(int $tenantId, int $definitionId): ?string
    {
        return $this->blockers[$definitionId] ?? null;
    }

    public function awardQualification(int $tenantId, int $userId, int $definitionId, string $status, int $validMonths, int $actorId): string
    {
        if ($definitionId === $this->failOnQualification) {
            throw new RuntimeException('Prérequis manquants.');
        }
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM t_grants WHERE tenant_id = ? AND user_id = ? AND definition_id = ?');
        $st->execute([$tenantId, $userId, $definitionId]);
        if ((int) $st->fetchColumn() > 0) {
            return 'unchanged';
        }
        $this->pdo->prepare('INSERT INTO t_grants (tenant_id, user_id, definition_id, status, valid_months) VALUES (?, ?, ?, ?, ?)')
            ->execute([$tenantId, $userId, $definitionId, $status, $validMonths]);

        return 'applied';
    }

    public function enrollCourse(int $tenantId, int $userId, int $courseId, int $actorId): string
    {
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM t_enrollments WHERE tenant_id = ? AND user_id = ? AND course_id = ?');
        $st->execute([$tenantId, $userId, $courseId]);
        if ((int) $st->fetchColumn() > 0) {
            return 'unchanged';
        }
        $this->pdo->prepare('INSERT INTO t_enrollments (tenant_id, user_id, course_id) VALUES (?, ?, ?)')->execute([$tenantId, $userId, $courseId]);

        return 'applied';
    }

    public function baseLabels(int $tenantId, array $options): array
    {
        return ['grade' => 'Soldat', 'unit' => 'Section Alpha', 'job' => 'Fusilier', 'roles' => ['Membre']];
    }

    public function journal(int $tenantId, int $userId, int $enlistmentId, int $actorId, string $title, string $detail): void
    {
        $this->pdo->prepare('INSERT INTO t_journal (tenant_id, user_id, enlistment_id, actor_id, title, detail) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$tenantId, $userId, $enlistmentId, $actorId, $title, $detail]);
    }

    public function sendWelcome(int $tenantId, int $userId, array $recap): bool
    {
        $this->pdo->prepare('INSERT INTO t_mails (tenant_id, user_id, courses) VALUES (?, ?, ?)')->execute([$tenantId, $userId, json_encode($recap['courses'] ?? [])]);

        return true;
    }
}
