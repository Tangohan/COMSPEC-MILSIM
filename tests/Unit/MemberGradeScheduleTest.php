<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\GradeRepository;
use App\Repositories\MemberGradeChangeRepository;
use App\Repositories\MemberGradeScheduleRepository;
use App\Repositories\UserRepository;
use App\Services\Personnel\MemberGradeScheduleService;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\MysqlDdlSqlitePdo;

/**
 * Changements de grade planifiés : planification (futur, motif, un seul à venir par membre), modification, annulation,
 * application à l'échéance (idempotente, origine « planifié », auteur d'origine), cas sans erreur (grade disparu,
 * membre parti), migration relançable. Base SQLite en mémoire avec la vraie migration SQL ; UserRepository et
 * GradeRepository sont remplacés par de petites sous-classes (pas de base complète).
 */
final class MemberGradeScheduleTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';
    private const TENANT = 7;

    private MysqlDdlSqlitePdo $pdo;
    private int $now;
    /** @var array<int, array<string, mixed>> */
    private array $users = [];
    /** @var list<array<string, mixed>> */
    private array $grades = [];
    /** @var list<array{int, ?int, ?int}> utilisateur, source, auteur vus pendant update() */
    private array $updates = [];

    protected function setUp(): void
    {
        $this->now = strtotime('2026-11-23 10:00:00');
        $this->pdo = new MysqlDdlSqlitePdo();
        $sql = preg_replace('/^--.*$/m', '', str_replace("\r\n", "\n", (string) file_get_contents(self::ROOT . '/migrations/2026_11_23_110_member_grade_schedules.sql'))) ?? '';
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            $this->pdo->exec($statement);
        }
        $this->users = [
            1 => ['id' => 1, 'display_name' => 'Sergent Faure', 'callsign' => 'FAUCON', 'grade_id' => 10],
            2 => ['id' => 2, 'display_name' => 'Caporal Diaz', 'callsign' => 'LYNX', 'grade_id' => null],
        ];
        $this->grades = [
            ['id' => 10, 'label_long' => 'Sergent', 'label_short' => 'SGT'],
            ['id' => 11, 'label_long' => 'Sergent-chef', 'label_short' => 'SCH'],
        ];
        $this->updates = [];
    }

    private function service(): MemberGradeScheduleService
    {
        $test = $this;
        $users = new class ($test) extends UserRepository {
            public function __construct(private MemberGradeScheduleTest $t)
            {
            }

            public function findById(int $id, ?int $tenantId = null): ?array
            {
                return $this->t->user($id);
            }

            public function update(int $id, int $tenantId, array $data): bool
            {
                $this->t->recordUpdate($id, $data);

                return true;
            }
        };
        $grades = new class ($test) extends GradeRepository {
            public function __construct(private MemberGradeScheduleTest $t)
            {
            }

            public function listForTenant(int $tenantId): array
            {
                return $this->t->grades();
            }
        };

        return new MemberGradeScheduleService(
            new MemberGradeScheduleRepository($this->pdo),
            $users,
            $grades,
            null,
            null,
            fn (): int => $this->now
        );
    }

    /** @return array<string, mixed>|null */
    public function user(int $id): ?array
    {
        return $this->users[$id] ?? null;
    }

    /** @return list<array<string, mixed>> */
    public function grades(): array
    {
        return $this->grades;
    }

    /** @param array<string, mixed> $data */
    public function recordUpdate(int $id, array $data): void
    {
        $this->updates[] = [$id, MemberGradeChangeRepository::currentSource() === MemberGradeChangeRepository::SOURCE_SCHEDULED ? 1 : 0, MemberGradeChangeRepository::currentActor()];
        $this->users[$id]['grade_id'] = $data['grade_id'];
    }

    public function testScheduleRequiresFutureDateReasonAndKnownGrade(): void
    {
        $s = $this->service();
        foreach ([
            ['2026-11-23', '09:00', 'Motif', 11, 'futur'],
            ['pas-une-date', '09:00', 'Motif', 11, 'invalide'],
            ['2026-12-01', '09:00', '  ', 11, 'motif'],
            ['2026-12-01', '09:00', 'Motif', 999, 'communauté'],
        ] as [$date, $time, $reason, $grade, $needle]) {
            try {
                $s->schedule(self::TENANT, 1, $grade, $date, $time, $reason, 5);
                self::fail('Une exception était attendue : ' . $needle);
            } catch (RuntimeException $e) {
                self::assertStringContainsString($needle, $e->getMessage());
            }
        }
        $id = $s->schedule(self::TENANT, 1, 11, '2026-12-01', '', 'Fin de stage', 5);
        $row = (new MemberGradeScheduleRepository($this->pdo))->find($id, self::TENANT);
        self::assertSame('2026-12-01 08:00:00', $row['scheduled_for']);
        self::assertSame('pending', $row['status']);
    }

    public function testOnlyOnePendingChangePerMemberAndItCanBeEditedThenCancelled(): void
    {
        $s = $this->service();
        $id = $s->schedule(self::TENANT, 1, 11, '2026-12-01', '09:00', 'Fin de stage', 5);
        try {
            $s->schedule(self::TENANT, 1, 11, '2026-12-02', '09:00', 'Doublon', 5);
            self::fail('Un second changement à venir doit être refusé.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('déjà planifié', $e->getMessage());
        }
        $s->update(self::TENANT, $id, 11, '2026-12-05', '10:30', 'Reporté', 6);
        $row = (new MemberGradeScheduleRepository($this->pdo))->find($id, self::TENANT);
        self::assertSame('2026-12-05 10:30:00', $row['scheduled_for']);
        self::assertSame('Reporté', $row['reason']);
        self::assertSame(5, (int) $row['created_by']);
        self::assertSame(6, (int) $row['updated_by']);

        $s->cancel(self::TENANT, $id, 6);
        self::assertSame('cancelled', (new MemberGradeScheduleRepository($this->pdo))->find($id, self::TENANT)['status']);
        $this->expectException(RuntimeException::class);
        $s->cancel(self::TENANT, $id, 6);
    }

    public function testPastDateCanNoLongerBeCancelled(): void
    {
        $s = $this->service();
        $id = $s->schedule(self::TENANT, 1, 11, '2026-11-24', '09:00', 'Motif', 5);
        $this->now = strtotime('2026-11-24 09:30:00');
        $this->expectException(RuntimeException::class);
        $this->service()->cancel(self::TENANT, $id, 5);
    }

    public function testDueChangeIsAppliedOnceWithScheduledSourceAndOriginalAuthor(): void
    {
        $s = $this->service();
        $id = $s->schedule(self::TENANT, 1, 11, '2026-11-24', '09:00', 'Motif', 5);
        self::assertSame(['applied' => 0, 'cancelled' => 0, 'skipped' => 0], $this->service()->applyDue());

        $this->now = strtotime('2026-11-24 09:15:00');
        self::assertSame(1, $this->service()->applyDue()['applied']);
        // Second passage de la tâche : rien à refaire.
        self::assertSame(['applied' => 0, 'cancelled' => 0, 'skipped' => 0], $this->service()->applyDue());
        self::assertSame([[1, 1, 5]], $this->updates);
        self::assertSame(11, $this->users[1]['grade_id']);
        $row = (new MemberGradeScheduleRepository($this->pdo))->find($id, self::TENANT);
        self::assertSame('applied', $row['status']);
        self::assertNull($row['open_slot']);
        // Une nouvelle planification redevient possible une fois la précédente appliquée.
        $this->users[1]['grade_id'] = 10;
        self::assertGreaterThan($id, $this->service()->schedule(self::TENANT, 1, 11, '2026-12-20', '09:00', 'Autre', 5));
    }

    public function testClaimIsExclusive(): void
    {
        $s = $this->service();
        $id = $s->schedule(self::TENANT, 1, 11, '2026-11-24', '09:00', 'Motif', 5);
        $repo = new MemberGradeScheduleRepository($this->pdo);
        $later = date('Y-m-d H:i:s', strtotime('2026-11-24 10:00:00'));
        self::assertTrue($repo->claim($id, $later));
        self::assertFalse($repo->claim($id, $later));
    }

    public function testMissingGradeOrDepartedMemberCancelsWithoutError(): void
    {
        $s = $this->service();
        $a = $s->schedule(self::TENANT, 1, 11, '2026-11-24', '09:00', 'Motif', 5);
        $b = $s->schedule(self::TENANT, 2, 11, '2026-11-24', '09:00', 'Motif', 5);
        $this->now = strtotime('2026-11-25 08:00:00');
        $this->grades = [['id' => 10, 'label_long' => 'Sergent', 'label_short' => 'SGT']];
        unset($this->users[2]);
        self::assertSame(['applied' => 0, 'cancelled' => 2, 'skipped' => 0], $this->service()->applyDue());
        $repo = new MemberGradeScheduleRepository($this->pdo);
        self::assertSame('Le grade visé n’existe plus.', $repo->find($a, self::TENANT)['status_reason']);
        self::assertSame('Le membre a quitté la communauté.', $repo->find($b, self::TENANT)['status_reason']);
        self::assertSame([], $this->updates);
    }

    public function testDashboardListsNextFiveWithRelativeDate(): void
    {
        $s = $this->service();
        $s->schedule(self::TENANT, 1, 11, '2026-11-24', '09:00', 'Motif', 5);
        $rows = $s->upcoming(self::TENANT, 5);
        self::assertCount(1, $rows);
        self::assertSame('Sergent Faure', $rows[0]['name']);
        self::assertSame('Sergent (SGT)', $rows[0]['from_label']);
        self::assertSame('Sergent-chef (SCH)', $rows[0]['to_label']);
        self::assertSame('tomorrow', $rows[0]['relative']['kind']);
        self::assertSame('days', $s->relative('2026-11-30 08:00:00')['kind']);
        self::assertSame(7, $s->relative('2026-11-30 08:00:00')['days']);
    }

    public function testMigrationStepIsIdempotentAndHasARollback(): void
    {
        $pdo = new MysqlDdlSqlitePdo();
        $root = self::ROOT;
        ob_start();
        require self::ROOT . '/database/steps/4990_member_grade_schedules.php';
        self::assertStringContainsString('Table créée', (string) ob_get_clean());
        $changes = $pdo->changes;
        ob_start();
        require self::ROOT . '/database/steps/4990_member_grade_schedules.php';
        self::assertStringContainsString('déjà présente', (string) ob_get_clean());
        self::assertSame($changes, $pdo->changes);
        self::assertStringContainsString('DROP TABLE IF EXISTS member_grade_schedules', (string) file_get_contents(self::ROOT . '/migrations/rollback/member_grade_schedules_down.sql'));
        $registry = require self::ROOT . '/database/steps/registry.php';
        $prefixes = array_map(static fn (array $s): string => substr((string) $s['file'], 0, 4), $registry);
        self::assertSame(count($prefixes), count(array_unique($prefixes)), 'Numéro d’étape en double.');
        $entry = array_values(array_filter($registry, static fn (array $s): bool => ($s['id'] ?? '') === 'member_grade_schedules'));
        self::assertCount(1, $entry);
        self::assertSame('4990_member_grade_schedules.php', $entry[0]['file']);
        self::assertTrue($entry[0]['rerun']);
    }
}
