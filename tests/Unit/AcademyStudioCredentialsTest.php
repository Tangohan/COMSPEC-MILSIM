<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Response;
use App\Services\Academy\AcademyBuiltInQualifications;
use App\Services\Academy\AcademyStudioCredentials as Creds;
use App\Services\Personnel\QualificationCriteriaEngine as Eng;
use App\Support\QualificationDefinitionPayload;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlDdlSqlitePdo;

/** Studio de l'Academy : qualifications et brevets (règle d'obtention, liens avec les cours, fiches, vues). */
final class AcademyStudioCredentialsTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = ['_csrf_token' => str_repeat('a', 64), 'user_id' => 1];
        $GLOBALS['__app_locale'] = 'fr';
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__app_locale']);
    }

    public function testFormBecomesOneBlockRuleAndBack(): void
    {
        $rule = Creds::ruleFromForm([
            'op' => 'any',
            'courses' => ['course-abc', 'course-abc', 'BAD KEY'],
            'qualifications' => ['7', '0'],
            'metrics' => [
                ['metric' => 'flight_hours', 'scope' => 'kind:heli', 'min' => '12,5', 'window_days' => 0],
                ['metric' => 'playtime_hours', 'scope' => '', 'min' => '', 'window_days' => 0],
                ['metric' => 'unknown', 'min' => '3'],
            ],
        ]);
        self::assertSame('any', $rule['groups'][0]['op']);
        self::assertCount(1, $rule['groups']);
        self::assertSame(['course-abc'], Creds::coursesInRule($rule));
        $form = Creds::formFromRule($rule);
        self::assertSame(['course-abc'], $form['courses']);
        self::assertSame([7], $form['qualifications']);
        self::assertCount(1, $form['metrics']);
        self::assertSame('flight_hours', $form['metrics'][0]['metric']);
        self::assertSame(12.5, (float) $form['metrics'][0]['min']);
        self::assertSame([], Creds::ruleFromForm(['courses' => [], 'metrics' => [['metric' => 'playtime_hours', 'min' => '']]]));
        self::assertFalse(Creds::isAdvanced($rule));
    }

    public function testLinkingACourseAddsItOnceAndUnlinkingRemovesItWithOlderVersions(): void
    {
        $rule = Creds::withCourse([], 'course-new');
        self::assertSame(Eng::normalize(AcademyBuiltInQualifications::rule('course-new')), $rule);
        self::assertSame($rule, Creds::withCourse($rule, 'course-new'), 'déjà rattaché');
        $withHours = Creds::ruleFromForm(['metrics' => [['metric' => 'playtime_hours', 'scope' => '', 'min' => '20']]]);
        $linked = Creds::withCourse($withHours, 'course-new');
        self::assertCount(2, $linked['groups'][0]['items'], 'ajouté au bloc existant');
        self::assertSame(['course-new'], Creds::coursesInRule($linked));
        $twoVersions = Creds::withCourse($linked, 'course-old');
        $unlinked = Creds::withoutCourses($twoVersions, ['course-new', 'course-old']);
        self::assertSame([], Creds::coursesInRule($unlinked));
        self::assertCount(1, $unlinked['groups'][0]['items'], 'les heures restent');
        self::assertSame([], Creds::withoutCourses(Creds::withCourse([], 'x-1'), ['x-1']), 'plus aucune condition');
        self::assertTrue(Creds::isAdvanced(['op' => 'all', 'groups' => [['op' => 'all', 'items' => []], ['op' => 'any', 'items' => []]]]));
    }

    public function testCodeOriginAndPayloadMerge(): void
    {
        self::assertMatchesRegularExpression('/\ABRV-BDCDG-[0-9A-F]{3}\z/', Creds::codeFrom('Brevet de chef de groupe', 'brevet'));
        self::assertMatchesRegularExpression('/\AQUAL-SAUVETAG-[0-9A-F]{3}\z/', Creds::codeFrom('Sauvetage', 'qualification'));
        self::assertSame('academy', Creds::origin('ACAD-JTAC'));
        self::assertSame('community', Creds::origin('ZZ-NOT-IN-ANY-CATALOG-42'));
        $merged = QualificationDefinitionPayload::merge(['name' => 'Ancien', 'requires_panel' => 1, 'grace_period_days' => 15, 'qualification_scope' => 'unit'], ['name' => 'Nouveau', 'qualification_scope' => 'bogus']);
        self::assertSame('Nouveau', $merged['name']);
        self::assertSame(1, $merged['requires_panel'], 'champ absent du Studio : gardé');
        self::assertSame(15, $merged['grace_period_days']);
        self::assertSame('global', $merged['qualification_scope']);
        self::assertSame(QualificationDefinitionPayload::FIELDS, array_keys($merged));
        $fromInput = QualificationDefinitionPayload::fromInput(static fn (string $f): ?string => $f === 'code' ? 'SC1' : null);
        self::assertSame(['SC1', 'global'], [$fromInput['code'], $fromInput['qualification_scope']]);
    }

    public function testCompletingANewVersionCountsForTheCourseItReplaces(): void
    {
        self::assertSame(['c3' => ['c2', 'c1'], 'c2' => ['c1']], AcademyBuiltInQualifications::chains(['c3' => 'c2', 'c2' => 'c1']));
        $loop = AcademyBuiltInQualifications::chains(['a' => 'b', 'b' => 'a']);
        self::assertSame(['a' => ['b'], 'b' => ['a']], $loop, 'boucle : arrêt sans répétition');

        $pdo = new MysqlDdlSqlitePdo();
        (new \App\Support\Migrations\SchemaInspector($pdo))->runSqlFile(dirname(__DIR__, 2) . '/migrations/20261029130000_athena_academy.sql');
        $pdo->exec('CREATE TABLE personnel_qualification_definitions (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, code TEXT)');
        $course = $pdo->prepare('INSERT INTO academy_courses (tenant_id, course_key, content_json, published, updated_by) VALUES (?, ?, ?, ?, 1)');
        $course->execute([20, 'course-v1', json_encode(['title' => ['fr' => 'V1']]), 0]);
        $course->execute([20, 'course-v2', json_encode(['title' => ['fr' => 'V2'], 'replaces' => 'course-v1']), 1]);
        $learner = $pdo->prepare('INSERT INTO academy_learners (tenant_id, user_id, course_key, state_json) VALUES (?, ?, ?, ?)');
        $learner->execute([20, 5, 'course-v2', json_encode(['completed' => true])]);
        $learner->execute([20, 6, 'course-v1', json_encode(['chapter' => 1])]);
        $service = AcademyBuiltInQualifications::forPdo($pdo);
        self::assertSame(['course-v2' => ['course-v1']], $service->lineage(20));
        self::assertSame([5 => ['course-v2' => true, 'course-v1' => true]], $service->completions(20));
        $rule = Eng::normalize(AcademyBuiltInQualifications::rule('course-v1'));
        $passed = static fn (int|string $c): bool => !empty($service->completions(20)[5][$c]);
        self::assertTrue(Eng::evaluate($rule, static fn (): ?float => 0.0, static fn (): bool => false, $passed)['met']);
    }

    private function cred(int $id, string $name, string $kind, string $origin, array $courses = []): array
    {
        return ['id' => $id, 'code' => 'C' . $id, 'name' => $name, 'short' => '', 'description' => '', 'category' => '', 'kind' => $kind, 'origin' => $origin,
            'badge' => '', 'holders' => 0, 'archived' => false, 'permanent' => true, 'months' => 0, 'courses' => $courses, 'updated_at' => '2026-11-01 10:00:00'];
    }

    private function view(array $data): string
    {
        return Response::view('academy.studio_credentials', $data + [
            'space' => 'studio', 'tenant' => ['name' => 'Escadron'], 'notice' => '', 'canManage' => true, 'canArchive' => true,
            'credOrigin' => 'tout', 'editing' => null, 'counts' => ['qualification' => 1, 'brevet' => 1], 'rulesReady' => true, 'oldForm' => [],
            'studioCourses' => ['course-a' => ['title' => 'Appui aérien', 'source' => 'studio', 'status' => 'pub'], 'jtac' => ['title' => 'JTAC', 'source' => 'builtin', 'status' => 'builtin']],
        ])->body();
    }

    public function testListPutsCommunityCreationsFirstAndOffersNew(): void
    {
        $body = $this->view(['credKind' => 'brevet', 'credList' => true, 'credentials' => [
            $this->cred(1, 'Brevet de chef de bord', 'brevet', 'community', ['course-a']),
            $this->cred(2, 'Brevet JTAC', 'brevet', 'catalog'),
            $this->cred(3, 'Secourisme', 'qualification', 'community'),
        ]]);
        self::assertStringContainsString('Créations de la communauté', $body);
        self::assertLessThan(strpos($body, 'Brevet JTAC'), strpos($body, 'Brevet de chef de bord'));
        self::assertStringNotContainsString('Secourisme', $body, 'autre nature : onglet Qualifications');
        self::assertStringContainsString('?nouveau=brevet', $body);
        self::assertStringContainsString('?nouveau=qualification', $body, 'fenêtre « Nouveau »');
        self::assertStringContainsString('1 cours y mène(nt)', $body);
        self::assertStringContainsString('cred-card is-mine', $body);
    }

    public function testNewBrevetFormPrefillsTheCourseAndShowsBrevetFields(): void
    {
        $body = $this->view(['credKind' => 'brevet', 'credList' => false, 'credentials' => [$this->cred(3, 'Secourisme', 'qualification', 'community')],
            'categories' => [['id' => 4, 'name' => 'Santé']], 'catalogCategories' => ['Aviation'], 'issuers' => [['id' => 9, 'name' => 'École des pilotes']],
            'templates' => [], 'metricOptions' => ['playtime_hours' => 'Heures de jeu', 'flight_hours' => 'Heures de vol'],
            'aircraftOptions' => [['', 'Tous les aéronefs', ''], ['kind:heli', 'Hélicoptères', '']], 'prefillCourse' => 'course-a', 'backOfficeUrl' => '']);
        self::assertStringContainsString('action/studio_cred_save', $body);
        self::assertStringContainsString('name="cred_kind" value="brevet"', $body);
        self::assertStringContainsString('name="certificate_number_format"', $body);
        self::assertStringContainsString('name="badge"', $body);
        self::assertStringContainsString('enctype="multipart/form-data"', $body);
        self::assertMatchesRegularExpression('/value="course-a" checked/', $body, 'cours d’origine coché');
        self::assertStringContainsString('École des pilotes', $body);
        self::assertStringContainsString('name="conditions[metrics][0][scope]"', $body);
        self::assertStringContainsString('name="conditions[qualifications][]" value="3"', $body);
        self::assertStringContainsString('Enregistrez la fiche pour y rattacher des cours.', $body);
    }

    private function studio(?array $course, array $extra = []): string
    {
        return Response::view('academy.studio', $extra + [
            'space' => 'studio', 'staff' => true, 'canCreate' => true, 'canShare' => false, 'role' => 'owner', 'tenant' => ['name' => 'Escadron'],
            'account' => [], 'notice' => '', 'course' => $course, 'studioCourses' => [], 'sharedWithMe' => [], 'builtInCourses' => [],
            'issues' => [], 'learnerCount' => 0, 'lifecycleReady' => true, 'sharing' => null, 'sharingReady' => false, 'viewStep' => '',
            'mediaImages' => [], 'editLang' => 'fr', 'openShare' => false, 'studioView' => '',
        ])->body();
    }

    public function testStudioHomeOffersNewMenuAndKindTabs(): void
    {
        $body = $this->studio(null, ['credentialCounts' => ['qualification' => 2, 'brevet' => 1], 'openNewCourse' => true]);
        self::assertStringContainsString('data-open-dialog="sth-pick"', $body);
        self::assertStringContainsString('id="sth-pick"', $body);
        self::assertStringContainsString('?vue=brevets', $body);
        self::assertStringContainsString('?nouveau=brevet', $body);
        self::assertMatchesRegularExpression('/id="sth-new"[^>]*data-autoshow/', $body);
    }

    public function testCourseEditorListsWhatTheCourseAwards(): void
    {
        $course = \App\Services\Academy\AcademyStudio::blankCourse() + ['key' => 'course-a', 'published' => false];
        $body = $this->studio($course, ['courseCredentials' => [
            'linked' => [$this->cred(1, 'Brevet de chef de bord', 'brevet', 'community', ['course-a'])],
            'available' => [$this->cred(2, 'Secourisme', 'qualification', 'community')], 'canManage' => true, 'rulesReady' => true,
        ]]);
        self::assertStringContainsString('Délivré à la réussite', $body);
        self::assertStringContainsString('?fiche=1', $body);
        self::assertStringContainsString('form="ast-cred-link"', $body);
        self::assertStringContainsString('id="ast-cred-unlink"', $body);
        self::assertStringContainsString('?nouveau=brevet&amp;cours=course-a', $body);
    }

    public function testEditingShowsLinkedCoursesAndEnglishCopy(): void
    {
        $GLOBALS['__app_locale'] = 'en';
        $editing = $this->cred(1, 'Brevet de chef de bord', 'brevet', 'community', ['course-a']) + [
            'definition' => ['id' => 1, 'code' => 'C1', 'name' => 'Brevet de chef de bord', 'is_permanent' => 1],
            'settings' => ['auto_enabled' => 1, 'auto_mode' => 'propose', 'exists' => true],
            'conditions' => ['op' => 'all', 'courses' => ['course-a'], 'qualifications' => [], 'metrics' => []], 'advanced' => false, 'issuer_id' => 0,
        ];
        $body = $this->view(['credKind' => 'brevet', 'credList' => false, 'credentials' => [$editing], 'editing' => $editing,
            'categories' => [], 'catalogCategories' => [], 'issuers' => [], 'templates' => [], 'metricOptions' => [], 'aircraftOptions' => [], 'prefillCourse' => '', 'backOfficeUrl' => '']);
        self::assertStringContainsString('Courses that lead to it', $body);
        self::assertStringContainsString('action/studio_cred_unlink', $body);
        self::assertStringContainsString('action/studio_cred_link', $body);
        self::assertStringContainsString('<option value="jtac">', $body, 'cours encore rattachable');
        self::assertStringContainsString('value="propose" selected', $body);
        self::assertStringContainsString('action/studio_cred_archive', $body);
        self::assertStringContainsString(' readonly', $body, 'code figé');
    }
}
