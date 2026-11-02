<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\AcademyController;
use App\Core\Container;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AcademyAccessRepository;
use App\Repositories\AcademyCourseShareRepository;
use App\Repositories\AcademyRepository;
use App\Repositories\TrainingEnrollmentRepository;
use App\Repositories\TrainingRepository;
use App\Services\Academy\AcademyCurriculum;
use App\Services\Academy\AcademyRights;
use App\Services\Training\TrainingService;

/**
 * Droits d'accès de l'Academy : qui crée, qui voit, qui valide. Chaque refus est vérifié côté serveur (action ou page),
 * pas seulement dans l'interface ; sans réglage, le comportement d'origine est conservé.
 */
final class AcademyAccessRightsTest extends AcademyStudioTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Même schéma sur une base qui accepte les deux tournures MySQL des inscriptions (INSERT IGNORE, FOR UPDATE).
        $base = $this->pdo;
        $this->pdo = new class ('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]) extends \PDO {
            public function prepare(string $query, array $options = []): \PDOStatement|false
            {
                return parent::prepare(str_replace([' FOR UPDATE', 'INSERT IGNORE'], ['', 'INSERT OR IGNORE'], $query), $options);
            }
        };
        foreach ($base->query("SELECT sql FROM sqlite_master WHERE type = 'table'")->fetchAll(\PDO::FETCH_COLUMN) as $sql) {
            $this->pdo->exec((string) $sql);
        }
        (new \ReflectionProperty(\App\Core\Database::class, 'pdo'))->setValue(null, $this->pdo);
        foreach ([
            // Étape academy_access_rules (3520).
            "CREATE TABLE academy_access_rules (tenant_id INTEGER NOT NULL, course_key TEXT NOT NULL DEFAULT '', capability TEXT NOT NULL, subject_type TEXT NOT NULL, subject_id INTEGER NOT NULL DEFAULT 0, created_by INTEGER NULL, created_at TEXT NOT NULL, PRIMARY KEY (tenant_id, course_key, capability, subject_type, subject_id))",
            "CREATE TABLE academy_course_settings (tenant_id INTEGER NOT NULL, course_key TEXT NOT NULL, owner_id INTEGER NULL, audience TEXT NOT NULL DEFAULT 'community', private_draft INTEGER NOT NULL DEFAULT 0, updated_by INTEGER NULL, updated_at TEXT NOT NULL, PRIMARY KEY (tenant_id, course_key))",
            // Appartenances utilisées par les règles : rôles d'accès, unités (avec parent), fonctions.
            'CREATE TABLE tenant_user_roles (tenant_id INTEGER, user_id INTEGER, role_id INTEGER, valid_until TEXT NULL)',
            'CREATE TABLE units (id INTEGER PRIMARY KEY, tenant_id INTEGER, parent_id INTEGER NULL, name TEXT)',
            'CREATE TABLE user_units (user_id INTEGER, unit_id INTEGER, ended_at TEXT NULL)',
            'CREATE TABLE personnel_job_roles (id INTEGER PRIMARY KEY, tenant_id INTEGER, name TEXT)',
            'CREATE TABLE personnel_profile_job_roles (tenant_id INTEGER, user_id INTEGER, personnel_job_role_id INTEGER)',
            "INSERT INTO units (id, tenant_id, parent_id, name) VALUES (100, 10, NULL, 'Escadron'), (101, 10, 100, 'Peloton Alpha'), (200, 11, NULL, 'Autre communauté')",
            'INSERT INTO user_units (user_id, unit_id, ended_at) VALUES (8, 101, NULL)',
            "INSERT INTO personnel_job_roles (id, tenant_id, name) VALUES (50, 10, 'Contrôleur JTAC')",
            'INSERT INTO personnel_profile_job_roles (tenant_id, user_id, personnel_job_role_id) VALUES (10, 9, 50)',
        ] as $sql) {
            $this->pdo->exec($sql);
        }
        $training = $this->createMock(TrainingService::class);
        $training->method('getCatalogue')->willReturn([]);
        $enrollments = $this->createMock(TrainingEnrollmentRepository::class);
        $enrollments->method('listByUserId')->willReturn([]);
        $enrollments->method('listPendingApproval')->willReturn([]);
        $legacy = $this->createMock(TrainingRepository::class);
        $legacy->method('listPublishedForTenant')->willReturn([]);
        $container = new \ReflectionProperty(Container::class, 'instances');
        $container->setValue(null, array_replace($container->getValue(), [TrainingService::class => $training, TrainingEnrollmentRepository::class => $enrollments,
            TrainingRepository::class => $legacy, AcademyAccessRepository::class => new AcademyAccessRepository($this->pdo),
            AcademyCourseShareRepository::class => new AcademyCourseShareRepository($this->pdo)]));
    }

    private function as(int $userId, array $permissions = []): void
    {
        Gate::reset();
        Gate::getInstance()->setPermissions($permissions);
        $_SESSION['user_id'] = $userId;
    }

    private function page(string $space, array $query = []): Response
    {
        $_GET = $query;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        return (new AcademyController())->space(new Request(), ['space' => $space]);
    }

    private function publishedCourse(): string
    {
        $key = $this->newDraft();
        $demo = AcademyCurriculum::demo();
        $form = $this->form($demo);
        $form['chapters'] = array_map(static fn (array $chapter): array => $chapter + ['resources' => []], $demo['chapters']);
        $this->post('studio_publish', ['course' => $key, 'draft' => $form]);
        self::assertTrue($this->draft($key)['published']);
        return $key;
    }

    /** Stagiaire arrivé à la validation (débriefing envoyé) du chapitre donné. */
    private function learnerAwaitingValidation(string $key, int $userId, int $chapter): void
    {
        $snapshot = AcademyCurriculum::demo();
        $snapshot['key'] = $key;
        $state = ['chapter' => $chapter, 'stage' => 6, 'learner_id' => $userId, 'snapshot' => $snapshot, 'debriefs' => [$chapter => 'Débriefing complet du stagiaire.']];
        $stmt = $this->pdo->prepare('INSERT INTO academy_learners (tenant_id, user_id, course_key, state_json) VALUES (10, ?, ?, ?)');
        $stmt->execute([$userId, $key, json_encode($state)]);
    }

    public function testDefaultsKeepTheOriginalBehaviour(): void
    {
        $rights = new AcademyRights(10, 7, static fn (string $p): bool => $p === 'training.create', new AcademyAccessRepository($this->pdo));
        foreach (AcademyRights::CAPABILITIES as $capability) {
            self::assertTrue($rights->can($capability), $capability . ' : l’encadrement garde le droit par défaut');
            self::assertFalse($rights->configured($capability));
        }
        $member = new AcademyRights(10, 8, static fn (string $p): bool => false, new AcademyAccessRepository($this->pdo));
        self::assertFalse($member->can('create'));
        self::assertTrue($member->canSee(['key' => 'any', 'published' => true]), 'Sans réglage, un cours est visible par toute la communauté');
        self::assertFalse($member->entersInstructor());
        // Sans les tables de l'étape 3520 : aucune erreur, même comportement.
        $legacy = new AcademyRights(10, 7, static fn (string $p): bool => $p === 'training.update', new AcademyAccessRepository(new \PDO('sqlite::memory:')));
        self::assertTrue($legacy->can('publish'));
        $this->as(8);
        self::assertSame(403, $this->page('instructor')->statusCode());
        self::assertSame(403, $this->post('studio_new', ['course' => 'academy'])->statusCode());
    }

    public function testInstructorPageExplainsTheRoleAndListsWhatToDo(): void
    {
        $key = $this->publishedCourse();
        $this->learnerAwaitingValidation($key, 9, 0);
        foreach (['fr' => ['Encadrer, valider, faire progresser', 'À traiter', 'Mes élèves', 'Ghost', 'Débriefing complet du stagiaire.'],
            'en' => ['Coach, validate, help learners progress', 'To do', 'My learners', 'Ghost']] as $language => $texts) {
            $GLOBALS['__app_locale'] = $language;
            $response = $this->page('instructor');
            self::assertSame(200, $response->statusCode());
            foreach ($texts as $text) {
                self::assertStringContainsString($text, $response->body(), $language . ' : ' . $text);
            }
            self::assertStringContainsString('name="learner" value="9"', $response->body());
        }
    }

    public function testOnlyRightsManagersSeeAndChangeTheRightsAndEveryChangeIsLogged(): void
    {
        // training.create : encadrement, mais pas responsable de la formation.
        self::assertSame(403, $this->page('droits')->statusCode());
        self::assertSame(403, $this->post('rights_save', ['course' => 'academy', 'capability' => 'create', 'units' => ['100']])->statusCode());
        $this->as(7, ['training.manage']);
        $page = $this->page('droits');
        self::assertSame(200, $page->statusCode());
        self::assertStringContainsString('Qui peut faire quoi dans l’Academy', $page->body());
        // Une unité d'une autre communauté est ignorée ; l'unité « Escadron » couvre ses sous-unités.
        $this->post('rights_save', ['course' => 'academy', 'capability' => 'create', 'units' => ['100', '200'], 'users' => ['99']]);
        self::assertSame('rights_saved', $_SESSION['academy_notice']);
        self::assertContains('academy.rights.updated', $this->audited);
        $rules = (new AcademyAccessRepository($this->pdo))->rules(10)['create'];
        self::assertSame([['type' => 'admins', 'id' => 0], ['type' => 'unit', 'id' => 100]], $rules);

        $this->as(8);
        self::assertSame(303, $this->post('studio_new', ['course' => 'academy', 'new_title' => 'Cours du peloton'])->statusCode(), 'Membre du peloton (sous-unité) : peut créer');
        // L'encadrement sans l'unité perd ce droit tant que la communauté ne l'a pas coché.
        $this->as(9, ['training.create']);
        self::assertSame(403, $this->post('studio_new', ['course' => 'academy'])->statusCode());
        $this->as(7, ['training.manage']);
        $this->post('rights_reset', ['course' => 'academy', 'capability' => 'create']);
        $this->as(9, ['training.create']);
        self::assertSame(303, $this->post('studio_new', ['course' => 'academy'])->statusCode(), 'Réglage par défaut rétabli');
    }

    public function testRestrictedCourseIsInvisibleAndUnreachableOutsideItsAudience(): void
    {
        $key = $this->publishedCourse();
        $title = AcademyCurriculum::demo()['title']['fr'];
        $this->pdo->exec("INSERT INTO academy_learners (tenant_id, user_id, course_key, state_json) VALUES (10, 20, '" . $key . "', '" . json_encode(['chapter' => 0, 'stage' => 0, 'snapshot' => ['title' => ['fr' => 'Copie', 'en' => 'Copy'], 'chapters' => []]]) . "')");
        $this->post('course_access_save', ['course' => $key, 'audience' => 'restricted', 'jobs' => ['50']]);
        self::assertSame('access_saved', $_SESSION['academy_notice']);
        self::assertContains('academy.course.access_updated', $this->audited);

        $this->as(8);
        self::assertStringNotContainsString($title, $this->page('catalogue')->body(), 'Hors du public : absent du catalogue');
        self::assertSame(404, $this->post('enroll', ['course' => $key])->statusCode(), 'Hors du public : inscription refusée');
        self::assertSame(403, $this->post('course_access_save', ['course' => $key, 'audience' => 'community'])->statusCode(), 'Ne peut pas élargir le public');
        $this->as(9);
        self::assertStringContainsString($title, $this->page('catalogue')->body(), 'Fonction choisie : visible');
        self::assertSame(303, $this->post('enroll', ['course' => $key])->statusCode());
        self::assertArrayHasKey($key, (new AcademyRepository($this->pdo))->states(10, 20), 'Un stagiaire déjà inscrit garde son cours');
    }

    public function testPrivateDraftIsHiddenFromOtherStaffButNotFromItsAuthorOrAdministrators(): void
    {
        $response = $this->post('studio_new', ['course' => 'academy', 'new_title' => 'Brouillon confidentiel', 'new_private' => '1']);
        preg_match('#course=(course-[a-f0-9]{16})#', (string) $response->headerValue('Location'), $m);
        $key = $m[1];
        self::assertSame(7, (new AcademyAccessRepository($this->pdo))->settings(10, $key)['owner_id']);
        self::assertSame(200, $this->page('studio', ['course' => $key])->statusCode());

        $this->as(9, ['training.update', 'training.publish']);
        self::assertStringNotContainsString('Brouillon confidentiel', $this->page('studio')->body());
        self::assertSame(403, $this->page('studio', ['course' => $key])->statusCode());
        self::assertSame(403, $this->post('studio_save', ['course' => $key, 'draft' => $this->form($this->draft($key))])->statusCode());
        self::assertSame(403, $this->post('studio_archive', ['course' => $key])->statusCode());
        $other = new AcademyRights(10, 9, static fn (string $p): bool => in_array($p, ['training.update', 'training.publish'], true), new AcademyAccessRepository($this->pdo));
        self::assertFalse($other->canPreview($this->draft($key)), 'Pas d’aperçu stagiaire non plus');
        $this->as(8, ['admin.organization']);
        self::assertStringContainsString('Brouillon confidentiel', $this->page('studio')->body(), 'Les administrateurs voient tout');
    }

    public function testInstructorShareValidatesItsCourseButFinalValidationNeedsTheCertifyRight(): void
    {
        $key = $this->publishedCourse();
        $this->learnerAwaitingValidation($key, 8, 0);
        $this->as(9);
        self::assertSame(403, $this->post('validate', ['course' => $key, 'learner' => '8', 'expected_chapter' => '0', 'comment' => 'Très bon débriefing.'])->statusCode(), 'Sans droit ni partage');
        (new AcademyCourseShareRepository($this->pdo))->grant(10, $key, 9, 'instructor', 7);
        self::assertSame(200, $this->page('instructor')->statusCode(), 'Instructeur invité : accès à l’espace instructeur');
        self::assertSame(303, $this->page('studio', ['course' => $key])->statusCode(), 'Instructeur : aperçu, jamais l’éditeur');
        self::assertSame(303, $this->post('validate', ['course' => $key, 'learner' => '8', 'expected_chapter' => '0', 'comment' => 'Très bon débriefing.'])->statusCode());
        self::assertSame(1, (new AcademyRepository($this->pdo))->states(10, 8)[$key]['chapter']);
        self::assertContains('academy.learner.validated', $this->audited);
        // Dernier chapitre : délivre l'attestation, réservé au droit « délivrer » (encadrement par défaut).
        $this->pdo->exec("DELETE FROM academy_learners WHERE user_id = 8");
        $this->learnerAwaitingValidation($key, 8, 2);
        self::assertSame(403, $this->post('validate', ['course' => $key, 'learner' => '8', 'expected_chapter' => '2', 'comment' => 'Validation finale.'])->statusCode());
        self::assertArrayNotHasKey('completed', (new AcademyRepository($this->pdo))->states(10, 8)[$key]);
        $this->as(7, ['training.create']);
        self::assertSame(303, $this->post('validate', ['course' => $key, 'learner' => '8', 'expected_chapter' => '2', 'comment' => 'Validation finale.'])->statusCode());
        self::assertTrue((new AcademyRepository($this->pdo))->states(10, 8)[$key]['completed']);
    }

    public function testAuthorWithoutGlobalEditRightEditsOnlyTheirOwnDraftAndCannotPublish(): void
    {
        $other = $this->newDraft();
        $this->as(7, ['training.manage']);
        $this->post('rights_save', ['course' => 'academy', 'capability' => 'create', 'users' => ['8']]);
        $this->post('rights_save', ['course' => 'academy', 'capability' => 'edit']);
        $this->post('rights_save', ['course' => 'academy', 'capability' => 'publish']);
        $this->as(8);
        $response = $this->post('studio_new', ['course' => 'academy', 'new_title' => 'Mon cours']);
        preg_match('#course=(course-[a-f0-9]{16})#', (string) $response->headerValue('Location'), $m);
        $mine = $m[1];
        $form = $this->form($this->draft($mine));
        $form['title'] = ['fr' => 'Mon cours revu', 'en' => ''];
        self::assertSame(303, $this->post('studio_save', ['course' => $mine, 'draft' => $form])->statusCode());
        self::assertSame('Mon cours revu', $this->draft($mine)['title']['fr']);
        self::assertSame(403, $this->post('studio_publish', ['course' => $mine, 'draft' => $form])->statusCode());
        self::assertSame(403, $this->post('studio_save', ['course' => $other, 'draft' => $this->form($this->draft($other))])->statusCode(), 'Cours d’un autre auteur');
        self::assertSame(403, $this->page('studio', ['course' => $other])->statusCode());
        // L'auteur règle le public et les personnes invitées de son cours, pas le lien public.
        self::assertSame(303, $this->post('studio_share_add', ['course' => $mine, 'share_user' => '9', 'share_role' => 'editor'])->statusCode());
        self::assertSame(403, $this->post('studio_link_save', ['course' => $mine, 'link_access' => 'public'])->statusCode());
        // L'encadrement d'origine (training.create) ne publie plus.
        $this->as(7, ['training.create']);
        self::assertSame(403, $this->post('studio_archive', ['course' => $other])->statusCode());
    }

    public function testAssignEnrolsOnlyMembersOfTheCourseAudience(): void
    {
        $key = $this->publishedCourse();
        $this->post('course_access_save', ['course' => $key, 'audience' => 'restricted', 'units' => ['100']]);
        $this->as(9);
        self::assertSame(403, $this->post('assign', ['course' => $key, 'member' => '8'])->statusCode());
        $this->as(7, ['training.assign']);
        $this->post('assign', ['course' => $key, 'member' => '9']);
        self::assertSame('assign_outside_audience', $_SESSION['academy_notice']);
        $this->post('assign', ['course' => $key, 'member' => '8']);
        self::assertSame('assign_done', $_SESSION['academy_notice']);
        self::assertArrayHasKey($key, (new AcademyRepository($this->pdo))->states(10, 8));
        self::assertContains('academy.course.assigned', $this->audited);
    }

    public function testViewAsPreviewShowsAMembersEffectiveRights(): void
    {
        $key = $this->publishedCourse();
        $this->post('course_access_save', ['course' => $key, 'audience' => 'restricted', 'users' => ['9']]);
        $this->as(7, ['admin.organization']);
        $body = $this->page('droits', ['apercu' => '8'])->body();
        self::assertStringContainsString('Ce que Viper peut faire', $body);
        self::assertMatchesRegularExpression('/Créer des cours<span class="sr-only"> · non autorisé/', $body);
    }
}
