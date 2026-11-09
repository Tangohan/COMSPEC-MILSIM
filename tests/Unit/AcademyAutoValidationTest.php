<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AcademyAutoValidationRepository;
use App\Services\Academy\AcademyAutoValidation;
use App\Services\Academy\AcademyCurriculum;
use App\Services\Academy\AcademyInstructorQueue;
use PHPUnit\Framework\TestCase;

/**
 * Vérification automatique Athena des chapitres en attente d'un formateur : critères objectifs, délai (71 h / 73 h),
 * formateur qui valide avant, double exécution, exclusions, dernier chapitre, annulation, plafond.
 */
final class AcademyAutoValidationTest extends TestCase
{
    private const NOW = 1_800_000_000;
    private const KEY = 'fondamentaux';

    /** État d'un stagiaire en attente de validation du chapitre $chapter depuis $hoursAgo heures. */
    private static function pending(int $chapter = 0, float $hoursAgo = 73, array $override = []): array
    {
        $course = AcademyCurriculum::demo();
        $validations = [];
        for ($i = 0; $i < $chapter; $i++) {
            $validations[$i] = ['by' => 9, 'comment' => 'Validé par un formateur.', 'at' => self::NOW - 999999];
        }

        return $override + [
            'chapter' => $chapter, 'stage' => 6, 'tenant_id' => 1, 'learner_id' => 5, 'snapshot' => $course,
            'attempts' => [['chapter' => $chapter, 'score' => 100, 'expired' => false, 'passed' => true, 'at' => self::NOW - 90000]],
            'debriefs' => [$chapter => 'Un constat précis et une amélioration concrète à mettre en œuvre.'],
            'validations' => $validations, 'updated' => (int) (self::NOW - $hoursAgo * 3600),
        ];
    }

    private static function store(array $states, array $tenants = [1 => ['enabled' => true, 'hours' => 72, 'excluded' => [], 'final_allowed' => []]]): object
    {
        return new class ($states, $tenants) extends AcademyAutoValidationRepository {
            /** @var list<array<string, mixed>> */
            public array $audit = [];
            /** @var list<array{int, int, string}> */
            public array $notes = [];
            /** @var list<array<string, mixed>> */
            public array $checks = [];
            public int $applies = 0;
            /** @var list<array{int, int, string}> */
            public array $granted = [];
            /** @var list<int> */
            public array $revoked = [];
            private int $nextAward = 500;
            /** @var list<int> */
            public array $instructors = [900, 901];

            public function __construct(public array $states, private array $tenants)
            {
            }

            public function enabledTenants(): array
            {
                return $this->tenants;
            }

            public function candidates(int $tenantId, int $hours, int $recheckBefore, int $limit): array
            {
                $out = [];
                foreach ($this->states as $id => $state) {
                    [$t, $u, $k] = explode('|', $id);
                    if ((int) $t === $tenantId && ($state['stage'] ?? 0) === 6 && empty($state['completed'])
                        && (int) ($state['auto_check']['at'] ?? 0) <= $recheckBefore) {
                        $out[] = ['user_id' => (int) $u, 'course_key' => $k];
                    }
                }

                return array_slice($out, 0, $limit);
            }

            public function apply(int $tenantId, int $userId, string $key, callable $change): void
            {
                $this->applies++;
                $id = $tenantId . '|' . $userId . '|' . $key;
                if (!isset($this->states[$id])) {
                    throw new \DomainException('not_enrolled');
                }
                $this->states[$id] = $change($this->states[$id]);
            }

            public function markChecked(int $tenantId, int $userId, string $key, int $chapter, array $check): bool
            {
                $id = $tenantId . '|' . $userId . '|' . $key;
                if (($this->states[$id]['stage'] ?? 0) !== 6 || ($this->states[$id]['chapter'] ?? -1) !== $chapter) {
                    return false;
                }
                $this->states[$id]['auto_check'] = $check;
                $this->checks[] = $check;

                return true;
            }

            public function awardCredentials(int $tenantId, int $learnerId, string $key, int $hours): array
            {
                $this->granted[] = [$tenantId, $learnerId, $key];

                return [$this->nextAward++, $this->nextAward++];
            }

            public function revokeAwards(int $tenantId, array $ids, int $instructorId): void
            {
                $this->revoked = array_merge($this->revoked, $ids);
            }

            public function instructorIds(int $tenantId): array
            {
                return $this->instructors;
            }

            public function notify(int $tenantId, int $userId, string $type, array $payload): void
            {
                $this->notes[] = [$tenantId, $userId, $type];
            }

            public function audit(string $action, int $tenantId, array $details): void
            {
                $this->audit[] = ['action' => $action, 'tenant' => $tenantId] + $details;
            }
        };
    }

    private static function id(int $user = 5): string
    {
        return '1|' . $user . '|' . self::KEY;
    }

    public function testValidatesAfter73HoursWhenCriteriaMet(): void
    {
        $store = self::store([self::id() => self::pending(0, 73)]);
        $r = (new AcademyAutoValidation($store))->run(self::NOW);
        self::assertSame(1, $r['validated']);
        $state = $store->states[self::id()];
        self::assertSame(1, $state['chapter']);
        self::assertSame(0, $state['stage']);
        $entry = $state['validations'][0];
        self::assertTrue($entry['auto']);
        self::assertSame(0, $entry['by']);
        self::assertSame(72, $entry['hours']);
        self::assertStringContainsString('Validée automatiquement par Athena après 72 h sans réponse du formateur', $entry['comment']);
        self::assertSame('academy.learner.auto_validated', $store->audit[0]['action']);
        self::assertSame('system', $store->audit[0]['actor']);
        $types = array_map(static fn (array $n): string => $n[1] . ':' . $n[2], $store->notes);
        self::assertContains('5:academy_auto_validated', $types);
        self::assertContains('900:academy_auto_validated', $types);
        self::assertContains('901:academy_auto_validated', $types);
    }

    public function testDoesNothingAt71Hours(): void
    {
        $store = self::store([self::id() => self::pending(0, 71)]);
        $r = (new AcademyAutoValidation($store))->run(self::NOW);
        self::assertSame(0, $r['validated']);
        self::assertSame(1, $r['skipped']);
        self::assertSame(6, $store->states[self::id()]['stage']);
        self::assertSame([], $store->audit);
        self::assertSame([], $store->notes);
        self::assertArrayNotHasKey('auto_check', $store->states[self::id()]);
    }

    public function testCustomDelayPerCommunity(): void
    {
        $store = self::store([self::id() => self::pending(0, 30)], [1 => ['enabled' => true, 'hours' => 24, 'excluded' => []]]);
        self::assertSame(1, (new AcademyAutoValidation($store))->run(self::NOW)['validated']);
        self::assertSame(24, $store->states[self::id()]['validations'][0]['hours']);
    }

    public function testDisabledCommunityIsNeverTouched(): void
    {
        $store = self::store([self::id() => self::pending(0, 500)], []);
        $r = (new AcademyAutoValidation($store))->run(self::NOW);
        self::assertSame(0, $r['checked']);
        self::assertSame(6, $store->states[self::id()]['stage']);
    }

    public function testUnmetCriteriaNeverValidateAndRemindInstructors(): void
    {
        $state = self::pending(0, 80, ['attempts' => [['chapter' => 0, 'score' => 33, 'expired' => false, 'passed' => false, 'at' => 1]]]);
        $store = self::store([self::id() => $state]);
        $r = (new AcademyAutoValidation($store))->run(self::NOW);
        self::assertSame(1, $r['unmet']);
        $after = $store->states[self::id()];
        self::assertSame(6, $after['stage']);
        self::assertArrayNotHasKey(0, $after['validations']);
        self::assertContains('exam_not_passed', $after['auto_check']['reasons']);
        self::assertSame('academy.learner.auto_check_unmet', $store->audit[0]['action']);
        $types = array_map(static fn (array $n): string => $n[1] . ':' . $n[2], $store->notes);
        self::assertSame(['900:academy_auto_pending', '901:academy_auto_pending'], $types);
        // Seconde exécution : déjà relancé dans ce délai, rien de plus.
        $second = (new AcademyAutoValidation($store))->run(self::NOW + 3600);
        self::assertSame(0, $second['checked']);
        self::assertCount(2, $store->notes);
    }

    public function testMissingDebriefOrPrerequisiteBlocks(): void
    {
        $noDebrief = self::pending(0, 80, ['debriefs' => [0 => 'court']]);
        self::assertContains('debrief_missing', AcademyCurriculum::autoValidationCheck($noDebrief, AcademyCurriculum::demo())['reasons']);
        $skipped = self::pending(1, 80);
        unset($skipped['validations'][0]);
        self::assertContains('prerequisites', AcademyCurriculum::autoValidationCheck($skipped, AcademyCurriculum::demo())['reasons']);
        $expired = self::pending(0, 80, ['attempts' => [['chapter' => 0, 'score' => 100, 'expired' => true, 'passed' => true, 'at' => 1]]]);
        self::assertContains('exam_not_passed', AcademyCurriculum::autoValidationCheck($expired, AcademyCurriculum::demo())['reasons']);
        $nothing = self::pending(0, 80, ['debriefs' => [0 => AcademyCurriculum::NOTHING_TO_IMPROVE]]);
        self::assertTrue(AcademyCurriculum::autoValidationCheck($nothing, AcademyCurriculum::demo())['ok']);
    }

    public function testSubjectiveStepWithoutVerifiableCriterionIsNeverValidated(): void
    {
        $course = AcademyCurriculum::demo();
        unset($course['chapters'][0]['answer']);
        $state = self::pending(0, 100, ['snapshot' => $course]);
        self::assertContains('no_objective_criteria', AcademyCurriculum::autoValidationCheck($state, $course)['reasons']);
        $manual = AcademyCurriculum::demo();
        $manual['chapters'][0]['validation'] = ['manual_only' => true];
        self::assertContains('manual_only', AcademyCurriculum::autoValidationCheck(self::pending(0, 100, ['snapshot' => $manual]), $manual)['reasons']);
    }

    public function testInstructorWhoValidatedFirstWins(): void
    {
        $store = self::store([self::id() => self::pending(0, 100)]);
        // Le formateur valide entre le choix des candidats et le passage d'Athena : l'état sous verrou n'est plus en attente.
        $store->states[self::id()] = AcademyCurriculum::validateChapter($store->states[self::id()], AcademyCurriculum::demo(), 99, 'Compétence démontrée, bien joué.', self::NOW - 60);
        $settings = ['enabled' => true, 'hours' => 72, 'excluded' => []];
        self::assertSame('skipped', (new AcademyAutoValidation($store))->process(1, 5, self::KEY, $settings, self::NOW));
        self::assertArrayNotHasKey('auto', $store->states[self::id()]['validations'][0]);
        self::assertSame(99, $store->states[self::id()]['validations'][0]['by']);
        self::assertSame([], $store->audit);
        self::assertSame([], $store->notes);
    }

    public function testDoubleRunIsIdempotent(): void
    {
        $store = self::store([self::id() => self::pending(0, 73)]);
        $service = new AcademyAutoValidation($store);
        self::assertSame(1, $service->run(self::NOW)['validated']);
        $audit = count($store->audit);
        $notes = count($store->notes);
        $second = $service->run(self::NOW);
        self::assertSame(0, $second['validated']);
        self::assertCount($audit, $store->audit);
        self::assertCount($notes, $store->notes);
        self::assertCount(1, array_filter($store->states[self::id()]['validations'], static fn (array $v): bool => !empty($v['auto'])));
        // Appel direct répété sur un état qui n'est plus en attente : refusé.
        self::assertSame('skipped', $service->process(1, 5, self::KEY, ['enabled' => true, 'hours' => 72, 'excluded' => []], self::NOW));
    }

    public function testExcludedCourseIsNeverAutoValidated(): void
    {
        $store = self::store([self::id() => self::pending(0, 200)], [1 => ['enabled' => true, 'hours' => 72, 'excluded' => [self::KEY]]]);
        $r = (new AcademyAutoValidation($store))->run(self::NOW);
        self::assertSame(1, $r['unmet']);
        self::assertSame(6, $store->states[self::id()]['stage']);
        self::assertContains('excluded_course', $store->states[self::id()]['auto_check']['reasons']);
    }

    public function testFinalChapterIsNeverAutoValidated(): void
    {
        $last = count(AcademyCurriculum::demo()['chapters']) - 1;
        $store = self::store([self::id() => self::pending($last, 200)]);
        $r = (new AcademyAutoValidation($store))->run(self::NOW);
        self::assertSame(1, $r['unmet']);
        self::assertContains('final_chapter', $store->states[self::id()]['auto_check']['reasons']);
        self::assertEmpty($store->states[self::id()]['completed'] ?? false);
        self::assertArrayNotHasKey('license', $store->states[self::id()]);
    }

    public function testTenantIsolation(): void
    {
        $store = self::store([
            self::id() => self::pending(0, 100),
            '2|6|' . self::KEY => self::pending(0, 100),
        ]);
        $r = (new AcademyAutoValidation($store))->run(self::NOW);
        self::assertSame(1, $r['validated']);
        self::assertSame(6, $store->states['2|6|' . self::KEY]['stage']);
        foreach ($store->notes as [$tenant]) {
            self::assertSame(1, $tenant);
        }
    }

    public function testCapPerRun(): void
    {
        $states = [];
        for ($u = 10; $u < 15; $u++) {
            $states[self::id($u)] = self::pending(0, 100);
        }
        $store = self::store($states);
        $r = (new AcademyAutoValidation($store))->run(self::NOW, 2);
        self::assertSame(2, $r['checked']);
        self::assertSame(2, $r['validated']);
        self::assertSame(2, $store->applies);
    }

    public function testInstructorCanRevokeUntilLearnerMovesOn(): void
    {
        $store = self::store([self::id() => self::pending(0, 73)]);
        $service = new AcademyAutoValidation($store);
        $service->run(self::NOW);
        $service->revoke(1, 5, self::KEY, 0, 99, self::NOW + 10);
        $state = $store->states[self::id()];
        self::assertSame(0, $state['chapter']);
        self::assertSame(6, $state['stage']);
        self::assertArrayNotHasKey(0, $state['validations']);
        self::assertSame(99, $state['revoked_auto'][0]['by']);
        self::assertSame('academy.learner.auto_validation_revoked', end($store->audit)['action']);

        $again = self::store([self::id() => self::pending(0, 73)]);
        $svc = new AcademyAutoValidation($again);
        $svc->run(self::NOW);
        $again->states[self::id()]['stage'] = 2;
        $this->expectException(\DomainException::class);
        $svc->revoke(1, 5, self::KEY, 0, 99, self::NOW + 10);
    }

    public function testRevokeRefusesManualValidationAndSelf(): void
    {
        $state = self::pending(1, 80);
        $state['chapter'] = 2;
        $state['stage'] = 0;
        try {
            AcademyCurriculum::revokeAutoValidation($state, 0, 99, self::NOW);
            self::fail('Une validation de formateur ne s’annule pas ici.');
        } catch (\DomainException) {
            self::assertTrue(true);
        }
        $store = self::store([self::id() => self::pending(0, 80)]);
        $this->expectException(\DomainException::class);
        (new AcademyAutoValidation($store))->revoke(1, 5, self::KEY, 0, 5, self::NOW);
    }

    public function testQueueShowsAutoValidationAndFlagsInconclusiveCheck(): void
    {
        $state = self::pending(0, 73);
        $auto = AcademyCurriculum::autoValidateChapter($state, AcademyCurriculum::demo(), [], [], 72, self::NOW);
        $flag = self::pending(0, 80, ['auto_check' => ['at' => self::NOW, 'chapter' => 0, 'hours' => 72, 'reasons' => ['exam_not_passed']]]);
        $row = static fn (int $u, array $s): array => ['user_id' => $u, 'course_key' => self::KEY, 'updated_at' => date('Y-m-d H:i:s', self::NOW - 3600), 'state' => $s];
        $queue = AcademyInstructorQueue::build([$row(5, $auto), $row(6, $flag)], static fn (string $k): bool => true, true, [], [], 99, self::NOW);
        $types = array_column($queue['items'], 'type');
        self::assertContains('auto', $types);
        $autoItem = $queue['items'][array_search('auto', $types, true)];
        self::assertTrue($autoItem['revocable']);
        self::assertSame('ok', $autoItem['can']);
        $chapter = $queue['items'][array_search('chapter', $types, true)];
        self::assertNotNull($chapter['auto_check']);
        // Seul le chapitre en attente compte pour le badge : la validation automatique est du suivi.
        self::assertSame(1, $queue['total']);
    }

    public function testNormalizeSettings(): void
    {
        $s = AcademyAutoValidationRepository::normalize(['enabled' => '1', 'delay_hours' => '99999', 'excluded_courses' => json_encode(['jtac-licence-ath-academy', 'Bad Key!', 5])]);
        self::assertTrue($s['enabled']);
        self::assertSame(720, $s['hours']);
        self::assertSame(['jtac-licence-ath-academy'], $s['excluded']);
        self::assertSame(['enabled' => false, 'hours' => 72, 'excluded' => [], 'final_allowed' => []], AcademyAutoValidationRepository::normalize([]));
    }

    private static function finalSettings(array $over = []): array
    {
        return [1 => $over + ['enabled' => true, 'hours' => 72, 'excluded' => [], 'final_allowed' => [self::KEY]]];
    }

    private static function finalPending(): array
    {
        $last = count(AcademyCurriculum::demo()['chapters']) - 1;

        return self::pending($last, 100);
    }

    public function testFinalChapterAllowedIsGrantedWithMentionAndCredentials(): void
    {
        $store = self::store([self::id() => self::finalPending()], self::finalSettings());
        $r = (new AcademyAutoValidation($store))->run(self::NOW);
        self::assertSame(1, $r['validated']);
        $state = $store->states[self::id()];
        $last = count(AcademyCurriculum::demo()['chapters']) - 1;
        self::assertTrue($state['completed']);
        self::assertTrue($state['validations'][$last]['auto']);
        self::assertTrue($state['validations'][$last]['final']);
        self::assertStringContainsString('Athena', $state['validations'][$last]['comment']);
        self::assertSame([[1, 5, self::KEY]], $store->granted);
        self::assertSame([500, 501], $state['auto_awards']);
        self::assertTrue($store->audit[0]['final']);
        self::assertSame([500, 501], $store->audit[0]['awards']);
        // Notifications avec l'indication « final ».
        self::assertContains([1, 5, 'academy_auto_validated'], $store->notes);
    }

    public function testFinalChapterStaysManualByDefaultAndWhenCourseExcluded(): void
    {
        $default = self::store([self::id() => self::finalPending()]);
        (new AcademyAutoValidation($default))->run(self::NOW);
        self::assertContains('final_chapter', $default->states[self::id()]['auto_check']['reasons']);
        self::assertSame([], $default->granted);

        $excluded = self::store([self::id() => self::finalPending()], self::finalSettings(['excluded' => [self::KEY]]));
        (new AcademyAutoValidation($excluded))->run(self::NOW);
        self::assertContains('excluded_course', $excluded->states[self::id()]['auto_check']['reasons']);
        self::assertEmpty($excluded->states[self::id()]['completed'] ?? false);
        self::assertSame([], $excluded->granted);
    }

    public function testFinalDoubleRunDoesNotGrantTwice(): void
    {
        $store = self::store([self::id() => self::finalPending()], self::finalSettings());
        $service = new AcademyAutoValidation($store);
        $service->run(self::NOW);
        $service->run(self::NOW + 7200);
        self::assertCount(1, $store->granted);
    }

    public function testRevokingFinalChapterRevokesOnlyAutoGrantedCredentials(): void
    {
        $store = self::store([self::id() => self::finalPending()], self::finalSettings());
        $service = new AcademyAutoValidation($store);
        $service->run(self::NOW);
        $service->revoke(1, 5, self::KEY, count(AcademyCurriculum::demo()['chapters']) - 1, 99, self::NOW + 10);
        $state = $store->states[self::id()];
        self::assertEmpty($state['completed'] ?? false);
        self::assertSame(6, $state['stage']);
        self::assertArrayNotHasKey('auto_awards', $state);
        self::assertSame([500, 501], $store->revoked);
        self::assertSame('academy.learner.auto_validation_revoked', end($store->audit)['action']);
    }

    public function testManualFinalValidationIsNotRevocableAsAuto(): void
    {
        $state = self::finalPending();
        $done = AcademyCurriculum::validateChapter($state, AcademyCurriculum::demo(), 99, 'Compétence démontrée, bien joué.', self::NOW);
        $this->expectException(\DomainException::class);
        AcademyCurriculum::revokeAutoValidation($done, (int) $state['chapter'], 98, self::NOW);
    }

    public function testInstructorWhoValidatedFinalFirstWins(): void
    {
        $store = self::store([self::id() => self::finalPending()], self::finalSettings());
        $store->states[self::id()] = AcademyCurriculum::validateChapter($store->states[self::id()], AcademyCurriculum::demo(), 99, 'Compétence démontrée, bien joué.', self::NOW - 60);
        self::assertSame('skipped', (new AcademyAutoValidation($store))->process(1, 5, self::KEY, self::finalSettings()[1], self::NOW));
        self::assertSame([], $store->granted);
    }

    public function testFinalGrantStaysInsideItsCommunity(): void
    {
        $store = self::store([self::id() => self::finalPending(), '2|6|' . self::KEY => self::finalPending()], self::finalSettings());
        (new AcademyAutoValidation($store))->run(self::NOW);
        self::assertSame([[1, 5, self::KEY]], $store->granted);
        self::assertEmpty($store->states['2|6|' . self::KEY]['completed'] ?? false);
    }

    public function testBuiltInLicenseIsMarkedAutoAndRestoredOnRevoke(): void
    {
        $course = AcademyCurriculum::demo();
        $course['built_in'] = true;
        $course['license_code'] = 'TST';
        $last = count($course['chapters']) - 1;
        $state = self::pending($last, 100, ['snapshot' => $course]);
        $done = AcademyCurriculum::autoValidateChapter($state, $course + ['key' => self::KEY], [], [self::KEY], 72, self::NOW);
        self::assertTrue($done['license']['auto']);
        self::assertSame(0, $done['license']['validated_by']);
        self::assertStringStartsWith('ATH-ACADEMY-TST-', $done['license']['number']);
        $back = AcademyCurriculum::revokeAutoValidation($done, $last, 99, self::NOW + 1);
        self::assertArrayNotHasKey('license', $back);
        self::assertEmpty($back['completed'] ?? false);
    }
}
