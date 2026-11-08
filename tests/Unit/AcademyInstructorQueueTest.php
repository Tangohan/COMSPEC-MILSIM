<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Response;
use App\Repositories\AcademyRepository;
use App\Services\Academy\AcademyCurriculum;
use App\Services\Academy\AcademyInstructorQueue;
use PHPUnit\Framework\TestCase;

/**
 * File « à traiter » de l'encadrement : ce qui attend un instructeur (chapitres, propositions, demandes, évaluations
 * échouées, stagiaires sans activité), le bloc du tableau de bord, la file de l'espace instructeur, le badge du menu et
 * ce que voit le stagiaire qui attend.
 */
final class AcademyInstructorQueueTest extends TestCase
{
    private const NOW = 1_800_000_000;

    protected function setUp(): void
    {
        $_SESSION = ['_csrf_token' => str_repeat('a', 64), 'user_id' => 7];
        $_SERVER['REQUEST_URI'] = '/academy/dashboard';
        $GLOBALS['__app_locale'] = 'fr';
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__app_locale']);
    }

    private static function learner(int $userId, string $key, array $state, int $ageDays = 1): array
    {
        $snapshot = AcademyCurriculum::demo();
        $snapshot['key'] = $key;
        return ['user_id' => $userId, 'course_key' => $key, 'updated_at' => date('Y-m-d H:i:s', self::NOW - $ageDays * 86400),
            'state' => $state + ['learner_id' => $userId, 'snapshot' => $snapshot]];
    }

    private static function queue(bool $certify = true): array
    {
        $last = count(AcademyCurriculum::demo()['chapters']) - 1;
        $learners = [
            self::learner(9, 'cours-a', ['chapter' => 0, 'stage' => 6, 'debriefs' => [0 => 'Débriefing complet du stagiaire.']], 3),
            self::learner(10, 'cours-a', ['chapter' => $last, 'stage' => 6, 'debriefs' => [$last => 'Dernier débriefing du stagiaire.']], 1),
            self::learner(7, 'cours-a', ['chapter' => 0, 'stage' => 6]),
            self::learner(11, 'cours-b', ['chapter' => 0, 'stage' => 6]),
            self::learner(12, 'cours-a', ['chapter' => 0, 'stage' => 4, 'attempts' => [['chapter' => 0, 'score' => 40, 'passed' => false, 'at' => self::NOW - 3600]]]),
            self::learner(13, 'cours-a', ['chapter' => 0, 'stage' => 1], 20),
            self::learner(14, 'cours-a', ['chapter' => 0, 'stage' => 6, 'completed' => true], 30),
        ];
        $enrollments = [['id' => 55, 'user_id' => 15, 'course_title' => 'Formation radio', 'display_name' => 'Recrue', 'assigned_at' => date('Y-m-d H:i:s', self::NOW - 86400)]];
        $proposals = [['id' => 77, 'user_id' => 16, 'definition_id' => 5, 'name' => 'Brevet de chef de groupe', 'since' => date('Y-m-d H:i:s', self::NOW - 5 * 86400)]];
        return AcademyInstructorQueue::build($learners, static fn (string $key): bool => $key === 'cours-a', $certify, $enrollments, $proposals, 7, self::NOW);
    }

    public function testQueueCollectsEverythingThatWaitsWithWhoCanAct(): void
    {
        $queue = self::queue(false);
        $byId = array_column($queue['items'], null, 'id');
        self::assertSame(['chapter' => 4, 'proposal' => 1, 'enrollment' => 1, 'exam' => 1, 'stalled' => 1, 'auto' => 0], $queue['counts']);
        self::assertSame('ok', $byId['chapter-9-cours-a']['can']);
        self::assertSame('needs_certify', $byId['chapter-10-cours-a']['can'], 'Dernier chapitre sans le droit « délivrer »');
        self::assertTrue($byId['chapter-10-cours-a']['final']);
        self::assertSame('own', $byId['chapter-7-cours-a']['can'], 'Ses propres acquis ne se valident pas');
        self::assertSame('not_allowed', $byId['chapter-11-cours-b']['can']);
        self::assertArrayNotHasKey('chapter-14-cours-a', $byId, 'Cours terminé : plus rien à traiter');
        self::assertSame(40, $byId['exam-12-cours-a']['attempt']['score']);
        self::assertArrayHasKey('stalled-13-cours-a', $byId);
        self::assertSame(55, $byId['enrollment-55']['enrollment_id']);
        self::assertSame(77, $byId['proposal-77']['award_id']);
        // Décisions possibles : 2 chapitres (dont un réservé au droit « délivrer »), 1 proposition, 1 demande.
        self::assertSame(4, $queue['total']);
        // Chapitres d'abord, le plus ancien en tête.
        self::assertSame('chapter-9-cours-a', $queue['items'][0]['id']);
    }

    public function testBadgeUsesTheLightReadAndSkipsOwnFinishedAndForeignCourses(): void
    {
        $rows = [
            ['user_id' => '9', 'course_key' => 'cours-a', 'stage' => '6', 'completed' => null],
            ['user_id' => '7', 'course_key' => 'cours-a', 'stage' => '6', 'completed' => null],
            ['user_id' => '10', 'course_key' => 'cours-a', 'stage' => '6', 'completed' => 'true'],
            ['user_id' => '11', 'course_key' => 'cours-b', 'stage' => '6', 'completed' => null],
            ['user_id' => '12', 'course_key' => 'cours-a', 'stage' => '3', 'completed' => null],
        ];
        self::assertSame(3, AcademyInstructorQueue::badge($rows, static fn (string $key): bool => $key === 'cours-a', 1, 1, 7));
    }

    public function testReviewRowsReadStageWithoutTheCourseContent(): void
    {
        $pdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("CREATE TABLE academy_learners (tenant_id INTEGER, user_id INTEGER, course_key TEXT, state_json TEXT, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)");
        $insert = $pdo->prepare('INSERT INTO academy_learners (tenant_id, user_id, course_key, state_json) VALUES (?, ?, ?, ?)');
        $insert->execute([1, 9, 'cours-a', json_encode(['stage' => 6, 'chapter' => 0, 'snapshot' => AcademyCurriculum::demo()])]);
        $insert->execute([1, 10, 'cours-a', json_encode(['stage' => 6, 'completed' => true])]);
        $insert->execute([1, 9, '__notes', json_encode(['text' => 'privé'])]);
        $insert->execute([2, 11, 'cours-a', json_encode(['stage' => 6])]);
        $rows = (new AcademyRepository($pdo))->reviewRows(1);
        self::assertCount(2, $rows);
        self::assertStringNotContainsString('snapshot', json_encode($rows));
        self::assertSame(1, AcademyInstructorQueue::badge($rows, static fn (): bool => true, 0, 0, 7));
    }

    public function testDashboardShowsTheToDoBlockWithQuickValidation(): void
    {
        foreach (['fr' => ['À traiter', 'Chapitres à valider', 'Qualifications à approuver', 'Inscriptions en attente', 'Stagiaires en retard', 'Séances à venir', 'Lire et valider', 'Débriefing complet du stagiaire.'],
            'en' => ['To do', 'Chapters to validate', 'Qualifications to approve', 'Pending enrolments', 'Learners falling behind', 'Read and validate']] as $lang => $texts) {
            $GLOBALS['__app_locale'] = $lang;
            $body = $this->space('dashboard', self::queue() + ['sessions' => [], 'can' => ['enrollment' => true, 'proposal' => true]]);
            foreach ($texts as $text) {
                self::assertStringContainsString($text, $body, $lang . ' : ' . $text);
            }
            self::assertStringContainsString('<dialog class="q-dialog" id="dlg-chapter-9-cours-a"', $body);
            self::assertStringContainsString('name="learner" value="9"', $body);
            self::assertStringContainsString('name="return" value="dashboard"', $body);
            self::assertStringContainsString('/academy/instructor?type=chapter#file', $body);
            // Réponses rapides : formulaires existants, retour à l'espace instructeur.
            self::assertStringContainsString('/formation/enrollments/55/approve', $body);
            self::assertStringContainsString('/back-office/referentiels/qualifications/propositions/77', $body);
            self::assertStringContainsString('name="return_to" value="academy"', $body);
            self::assertStringContainsString('academy-queue.js', $body);
        }
        $GLOBALS['__app_locale'] = 'fr';
        $empty = $this->space('dashboard', ['items' => [], 'counts' => [], 'total' => 0, 'sessions' => [], 'can' => []]);
        self::assertStringContainsString('Rien à valider.', $empty);
        self::assertStringNotContainsString('<dialog class="q-dialog"', $empty);
    }

    public function testInstructorQueueIsSortableFilterableWithBulkActionsAndHelp(): void
    {
        $body = $this->space('instructor', self::queue() + ['sessions' => [], 'can' => ['enrollment' => true, 'proposal' => true]], ['queueType' => 'proposal']);
        self::assertStringContainsString('id="file"', $body);
        self::assertStringContainsString('data-q-filter-initial="proposal"', $body);
        foreach (['type', 'course', 'learner', 'since'] as $sort) {
            self::assertStringContainsString('data-q-sort="' . $sort . '"', $body);
        }
        self::assertStringContainsString('Comment valider un chapitre ?', $body);
        self::assertStringContainsString('data-q-run="approve"', $body);
        self::assertSame(4, substr_count($body, 'data-q-pick'), 'Cochables : chapitres validables, demande, proposition');
        self::assertStringContainsString('id="chapter-9-cours-a"', $body);
        self::assertStringContainsString('name="return" value="queue"', $body);
        self::assertStringContainsString('Un instructeur ne peut pas valider ses propres acquis.', $body);
        // Badge du menu.
        self::assertMatchesRegularExpression('/Espace instructeur<\/span>.*?lms-nav__badge">4</s', $body);
        $empty = $this->space('instructor', ['items' => [], 'counts' => [], 'total' => 0, 'sessions' => [], 'can' => []]);
        self::assertStringContainsString('Rien à valider.', $empty);
    }

    public function testLearnerSeesThatAnInstructorMustValidate(): void
    {
        $course = AcademyCurriculum::demo();
        $state = ['chapter' => 0, 'stage' => 6, 'snapshot' => $course];
        $body = Response::view('academy.space', ['space' => 'courses', 'staff' => false, 'states' => [$course['key'] => $state], 'courses' => [$course], 'selected' => $course, 'state' => $state, 'learners' => [], 'notice' => ''])->body();
        self::assertStringContainsString('En attente de validation par un instructeur', $body);
    }

    public function testDecisionsTakenFromTheQueueReturnToTheInstructorArea(): void
    {
        $root = dirname(__DIR__, 2);
        self::assertStringContainsString("inputString('return_to') === 'academy'", (string) file_get_contents($root . '/app/Controllers/Admin/AdminTrainingController.php'));
        self::assertStringContainsString("=== 'academy' ? url('academy/instructor')", (string) file_get_contents($root . '/app/Controllers/Admin/Organization/QualificationRulesController.php'));
        $controller = (string) file_get_contents($root . '/app/Controllers/Web/AcademyController.php');
        self::assertStringContainsString("inputString('return') === 'dashboard'", $controller);
        self::assertStringContainsString('AcademyInstructorQueue::badge(', $controller);
    }

    private function space(string $space, array $queue, array $extra = []): string
    {
        $course = AcademyCurriculum::demo();
        $_SERVER['REQUEST_URI'] = '/academy/' . $space;
        return Response::view('academy.space', ['space' => $space, 'staff' => true, 'states' => [], 'courses' => [$course], 'selected' => $course, 'state' => null,
            'learners' => [], 'notice' => '', 'queue' => $queue, 'instructorBadge' => (int) $queue['total'], 'queueMembers' => [9 => 'Ghost', 10 => 'Viper', 16 => 'Hawk']] + $extra)->body();
    }
}
