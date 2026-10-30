<?php

declare(strict_types=1);

namespace Tests\Contract;

use App\Repositories\AcademyRepository;
use App\Services\Academy\AcademyCurriculum;
use App\Services\Academy\AcademyBuiltInCourses;
use PDO;
use PHPUnit\Framework\TestCase;

/** Opt-in disposable MariaDB only; never uses the application database. */
final class AcademyRepositoryTest extends TestCase
{
    private PDO $pdo;
    private AcademyRepository $repository;

    protected function setUp(): void
    {
        $dsn = getenv('ACADEMY_TEST_DSN');
        if (!$dsn) self::markTestSkipped('Set ACADEMY_TEST_DSN to a disposable academy_test database.');
        $this->pdo = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        self::assertSame('academy_test', $this->pdo->query('SELECT DATABASE()')->fetchColumn());
        $this->pdo->exec(file_get_contents(dirname(__DIR__,2) . '/migrations/20261029130000_athena_academy.sql'));
        $this->pdo->exec('DELETE FROM academy_courses');
        $this->pdo->exec('DELETE FROM academy_learners');
        $this->repository = new AcademyRepository($this->pdo);
    }

    public function testEnrollmentNotesAndStaffReviewAreIsolatedByTenantAndUser(): void
    {
        $course = AcademyCurriculum::demo();
        $this->repository->enroll(1, 10, $course);
        $this->repository->enroll(2, 20, $course);
        $this->repository->saveNotes(1, 10, 'Private notebook');
        self::assertArrayHasKey('fondamentaux', $this->repository->states(1,10));
        self::assertSame([], $this->repository->states(2,10));
        self::assertSame([], $this->repository->states(1,20));
        self::assertCount(1, $this->repository->learners(1));
        self::assertSame(10, (int)$this->repository->learners(1)[0]['user_id']);
        self::assertStringNotContainsString('Private notebook', json_encode($this->repository->learners(1)));
        self::assertSame('Private notebook', $this->repository->states(1,10)['__notes']['text']);
    }

    public function testWrongTenantCannotMutateAndFailureRollsBack(): void
    {
        $this->repository->enroll(1, 10, AcademyCurriculum::demo());
        try {
            $this->repository->mutate(2, 10, 'fondamentaux', fn($state) => ['compromised' => true]);
            self::fail('Cross-tenant mutation accepted');
        } catch (\DomainException $e) {
            self::assertSame('not_enrolled', $e->getMessage());
        }
        try {
            $this->repository->mutate(1, 10, 'fondamentaux', function ($state) { throw new \DomainException('rollback'); });
        } catch (\DomainException $e) {
            self::assertSame('rollback', $e->getMessage());
        }
        self::assertSame(0, $this->repository->states(1,10)['fondamentaux']['stage']);
        self::assertFalse($this->pdo->inTransaction());
    }

    public function testDraftPublicationAndSnapshotsRemainScopedAndStable(): void
    {
        $course = AcademyCurriculum::demo();
        $this->repository->saveCourse(1, 10, $course, false);
        self::assertCount(0, $this->communityCourses(1));
        $draft = $this->communityCourses(1, true)[0];
        $this->repository->publish(2, $draft['key']);
        self::assertCount(0, $this->communityCourses(1));
        $this->repository->publish(1, $draft['key']);
        self::assertCount(1, $this->communityCourses(1));
        self::assertCount(0, $this->communityCourses(2));
        $this->repository->enroll(1,10,$draft);
        $this->repository->enroll(1,10,array_replace($draft,['title'=>['fr'=>'Nouveau','en'=>'New']]));
        self::assertSame($draft['title'], $this->repository->states(1,10)[$draft['key']]['snapshot']['title']);
    }

    private function communityCourses(int $tenantId, bool $drafts = false): array
    {
        return array_values(array_filter($this->repository->courses($tenantId, $drafts), static fn (array $course): bool => empty($course['built_in'])));
    }

    public function testBuiltInModuleNeedsNoCourseSeedAndItsProgressAndLicenceRemainPrivate(): void
    {
        $key = 'jtac-licence-ath-academy';
        $course = $this->repository->course(14, $key);
        self::assertSame(AcademyBuiltInCourses::find($key), $course);
        self::assertSame($course, $this->repository->course(15, $key));
        self::assertNull($this->repository->course(0, $key));
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM academy_courses')->fetchColumn());
        $this->repository->enroll(14, 10, $course);
        $different = array_replace($course, ['version' => '999', 'title' => ['fr' => 'Remplacement', 'en' => 'Replacement']]);
        $this->repository->enroll(14, 10, $different);
        self::assertSame($course, $this->repository->states(14, 10)[$key]['snapshot']);
        $chapter = $course['chapters'][0];
        foreach (['advance', 'advance', 'practice', 'practice', 'exam_start', 'exam_submit', 'debrief'] as $action) {
            $this->repository->mutate(14, 10, $key, static function (array $state) use ($action, $course, $chapter): array {
                $answers = array_column(AcademyCurriculum::examQuestions($chapter), 'answer');
                $input = ['expected_stage' => $state['stage'], 'expected_chapter' => $state['chapter'],
                    'answer' => AcademyCurriculum::questionForStage($chapter, (int) $state['stage'])['answer'],
                    'answers' => $answers, 'token' => $state['exam']['token'] ?? '',
                    'reflection' => 'J’ai distingué les faits et les hypothèses et je vérifierai les informations ambiguës avant de poursuivre.'];
                return AcademyCurriculum::transition($state, $course, $action, $input, 1000);
            });
        }
        $pending = $this->repository->states(14, 10)[$key];
        self::assertSame(6, $pending['stage']);
        self::assertArrayNotHasKey('license', $pending);
        $this->repository->mutate(14, 10, $key, static fn (array $state): array => AcademyCurriculum::validateChapter($state, $course, 99, 'Compétences démontrées en simulation et débriefing examiné.', 1100));
        $finished = $this->repository->states(14, 10)[$key];
        self::assertTrue($finished['completed']);
        self::assertMatchesRegularExpression('/^ATH-ACADEMY-JTAC-[A-F0-9]{12}$/', $finished['license']['number']);
        self::assertSame(99, $finished['license']['validated_by']);
        self::assertSame('simulation', $finished['license']['scope']);
        self::assertSame([], $this->repository->states(15, 10));
        self::assertSame([], $this->repository->states(14, 20));
        self::assertSame(1, count($this->repository->learners(14)));
        self::assertSame([], $this->repository->learners(15));
    }
}
