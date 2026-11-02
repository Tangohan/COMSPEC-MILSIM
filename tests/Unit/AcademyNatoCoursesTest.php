<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\QualificationAwardRuleRepository;
use App\Services\Academy\AcademyBuiltInCourses;
use App\Services\Academy\AcademyBuiltInQualifications;
use App\Services\Academy\AcademyCurriculum;
use App\Services\Personnel\QualificationCriteriaEngine as Eng;
use App\Support\Migrations\MigrationRegistry;
use App\Support\QualificationCriteriaCatalog as Cat;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlDdlSqlitePdo;

/**
 * Cours intégrés de l’Academy inspirés des standards OTAN publics : structure complète (sept étapes, évaluation,
 * débriefing, validation), contenu FR/EN, mention « formation de jeu », et qualification Athena Academy délivrée
 * (fiche du référentiel en mode « Formation Academy », règle {type: academy, course_key}).
 */
final class AcademyNatoCoursesTest extends TestCase
{
    /** Clé => mots attendus dans le cours (procédure couverte). */
    private const COURSES = [
        'otan-procedures-radio-acp125' => ['ACP 125', 'WILCO', 'ACRE'],
        'otan-compte-rendu-salute' => ['SALUTE', 'MGRS', 'ATAK'],
        'otan-medevac-9-line-casevac' => ['9-line', 'MIST', 'CASEVAC', 'ACE'],
        'otan-appel-de-feu-jfo' => ['JFO', 'Fire for effect', 'ATAK', 'Danger close'],
        'otan-prevention-tirs-fratricides' => ['PID', 'IR', 'Check fire'],
        'otan-ordre-cinq-paragraphes' => ['SMEAC', 'WARNO', 'FRAGO'],
    ];

    private static function both(mixed $text, string $where): void
    {
        self::assertIsArray($text, $where);
        foreach (['fr', 'en'] as $lang) {
            self::assertIsString($text[$lang] ?? null, $where . '.' . $lang);
            self::assertNotSame('', trim($text[$lang]), $where . '.' . $lang);
        }
    }

    public function testEveryNatoCourseIsCompleteBilingualAndStaysAnInGameTraining(): void
    {
        $all = AcademyBuiltInCourses::all();
        self::assertSame(AcademyBuiltInCourses::JTAC_KEY, array_key_first($all), 'le JTAC reste en tête');
        self::assertCount(count(AcademyBuiltInCourses::NATO_COURSES), array_intersect_key($all, self::COURSES), 'chaque cours OTAN est livré');
        $codes = [];
        foreach (self::COURSES as $key => $needles) {
            $course = AcademyBuiltInCourses::find($key);
            self::assertIsArray($course, $key);
            self::assertSame($course, AcademyCurriculum::validate($course), $key);
            self::assertTrue($course['built_in']);
            self::assertTrue($course['published']);
            self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $course['version']);
            self::assertGreaterThanOrEqual(3, count($course['chapters']), $key);
            self::assertStringContainsString('sans valeur de qualification réelle', $course['license']['fr']);
            self::assertStringContainsString('no real-world qualification value', $course['license']['en']);
            self::assertStringContainsString('sans valeur de qualification réelle', $course['chapters'][0]['theory']['fr'], $key);
            self::assertStringContainsString('no real-world qualification value', $course['chapters'][0]['theory']['en'], $key);
            foreach ($course['chapters'] as $i => $chapter) {
                $where = $key . ' #' . $i;
                foreach (['title', 'theory', 'demonstration'] as $field) {
                    self::both($chapter[$field], $where . ' ' . $field);
                }
                self::both($chapter['guided']['hint'], $where . ' indice');
                self::assertArrayNotHasKey('hint', $chapter['simulation'], $where . ' : simulation autonome sans aide');
                self::assertNotSame($chapter['guided']['question'], $chapter['simulation']['question'], $where);
                self::assertSame(80, $chapter['assessment']['pass_percent']);
                self::assertGreaterThanOrEqual(5, count($chapter['assessment']['questions']), $where);
                self::assertGreaterThan(1, count(array_unique(array_column($chapter['assessment']['questions'], 'answer'))), $where . ' : bonne réponse toujours au même rang');
                self::assertNotEmpty($chapter['debrief']['criteria']);
                self::assertNotEmpty($chapter['validation']['criteria']);
                self::assertNotEmpty($chapter['resources']);
                self::assertStringContainsString('sans valeur de qualification réelle', $chapter['validation']['brief']['fr']);
            }
            $text = (string) json_encode($course, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            foreach ($needles as $needle) {
                self::assertStringContainsStringIgnoringCase($needle, $text, $key);
            }
            $q = $course['qualification'];
            self::assertMatchesRegularExpression('/^ACAD-OTAN-[A-Z0-9-]{2,30}$/', $q['code'], $key);
            self::assertSame($course['license_code'], $q['license_code']);
            foreach (['name', 'description', 'reference'] as $field) {
                self::both($q[$field], $key . ' qualification.' . $field);
            }
            $codes[] = $q['code'];
        }
        self::assertSame(count($codes), count(array_unique($codes)), 'code de qualification en double');
        self::assertArrayHasKey(AcademyBuiltInCourses::JTAC_KEY, AcademyBuiltInCourses::qualifications(), 'le JTAC délivre aussi sa licence au référentiel');
    }

    public function testFullPathwayIssuesALicenceNumberedWithTheCourseCode(): void
    {
        $key = array_key_first(self::COURSES);
        $course = AcademyBuiltInCourses::find($key);
        self::assertIsArray($course);
        $state = ['chapter' => 0, 'stage' => 0, 'learner_id' => 10];
        $now = 1000;
        foreach ($course['chapters'] as $index => $chapter) {
            $answers = array_column(AcademyCurriculum::examQuestions($chapter), 'answer');
            foreach (['advance', 'advance', 'practice', 'practice', 'exam_start', 'exam_submit'] as $action) {
                $state = AcademyCurriculum::transition($state, $course, $action, [
                    'expected_stage' => $state['stage'], 'expected_chapter' => $state['chapter'],
                    'answer' => AcademyCurriculum::questionForStage($chapter, (int) $state['stage'])['answer'],
                    'answers' => $answers, 'token' => $state['exam']['token'] ?? '',
                ], $now++);
            }
            // Sans débriefing, pas de validation (règle des cours intégrés).
            try {
                AcademyCurriculum::validateChapter($state, $course, 99, 'Compte rendu répété correctement.', $now);
                self::fail('validation sans débriefing');
            } catch (\DomainException) {
            }
            $state = AcademyCurriculum::transition($state, $course, 'debrief', ['expected_stage' => 5, 'expected_chapter' => $index, 'reflection' => 'J’ai respecté l’ordre du compte rendu et je le répéterai à la radio.'], $now++);
            $state = AcademyCurriculum::validateChapter($state, $course, 99, 'Compte rendu répété correctement en session.', $now++);
        }
        self::assertTrue($state['completed']);
        self::assertMatchesRegularExpression('/^ATH-ACADEMY-' . $course['license_code'] . '-[0-9A-F]{12}$/', $state['license']['number']);
        self::assertSame('simulation', $state['license']['scope']);
        // Ancienne copie du JTAC sans license_code : licence toujours numérotée « JTAC ».
        $jtac = AcademyBuiltInCourses::find(AcademyBuiltInCourses::JTAC_KEY);
        unset($jtac['license_code'], $jtac['qualification']);
        $last = count($jtac['chapters']) - 1;
        $jtacState = ['chapter' => $last, 'stage' => 6, 'learner_id' => 10, 'attempts' => [['chapter' => $last, 'score' => 100]], 'debriefs' => [$last => 'Débriefing complet du dernier chapitre.']];
        $jtacState = AcademyCurriculum::validateChapter($jtacState, $jtac, 99, 'Validation du dernier chapitre.', $now);
        self::assertStringStartsWith('ATH-ACADEMY-JTAC-', $jtacState['license']['number']);
    }

    public function testBuiltInCourseCriterionIsKeptEvaluatedAndShownAsAcademyMode(): void
    {
        $key = 'otan-medevac-9-line-casevac';
        $rule = Eng::normalize(json_encode(AcademyBuiltInQualifications::rule($key)));
        self::assertSame(AcademyBuiltInQualifications::rule($key), $rule);
        self::assertNull(Eng::normalizeCriterion(['type' => 'academy', 'course_key' => '../x']));
        self::assertSame(['type' => 'academy', 'course_id' => 4], Eng::normalizeCriterion(['type' => 'academy', 'course_id' => 4]));
        self::assertSame([], Eng::academyOnlyCourses($rule), 'pas d’attestation à rattacher : évaluée membre par membre');
        self::assertSame([Cat::MODE_MANUAL, Cat::MODE_ACADEMY], Eng::modes(['allow_manual' => 1, 'auto_enabled' => 1, 'rule_json' => json_encode($rule)], false));
        $passed = static fn (int|string $c): bool => $c === $key;
        $none = static fn (): float => 0.0;
        $no = static fn (int $id): bool => false;
        self::assertTrue(Eng::evaluate($rule, $none, $no, $passed)['met']);
        self::assertFalse(Eng::evaluate(Eng::normalize(AcademyBuiltInQualifications::rule('otan-appel-de-feu-jfo')), $none, $no, $passed)['met']);
        self::assertSame('Réussir le cours Academy « MEDEVAC »', Cat::describe(['type' => 'academy', 'course_key' => $key], ['builtin' => [$key => 'MEDEVAC']]));
        self::assertArrayHasKey($key, AcademyBuiltInQualifications::courseTitles('en'));
    }

    private static function base(): MysqlDdlSqlitePdo
    {
        $pdo = new MysqlDdlSqlitePdo();
        (new \App\Support\Migrations\SchemaInspector($pdo))->runSqlFile(dirname(__DIR__, 2) . '/migrations/20261029130000_athena_academy.sql');
        foreach ([
            'CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT)',
            'CREATE TABLE personnel_qualification_definitions (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, code TEXT, name TEXT, short_name TEXT, description TEXT, category_id INTEGER, default_issuer_id INTEGER, is_permanent INTEGER, is_active INTEGER, created_by INTEGER, updated_by INTEGER, archived_at TEXT, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE qualification_categories (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, name TEXT, sort_order INTEGER, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE qualification_issuers (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, name TEXT, short_name TEXT, issuer_kind TEXT, parent_issuer_id INTEGER, description TEXT, created_by INTEGER)',
            "INSERT INTO tenants (id, name) VALUES (14, 'SOAR'), (15, 'Phantom')",
        ] as $sql) {
            $pdo->exec($sql);
        }
        (new \App\Support\Migrations\SchemaInspector($pdo))->runSqlFile(dirname(__DIR__, 2) . '/migrations/2026_11_02_010_qualification_award_rules.sql');
        $insert = $pdo->prepare('INSERT INTO academy_learners (tenant_id, user_id, course_key, state_json) VALUES (?, ?, ?, ?)');
        foreach ([
            [14, 1, AcademyBuiltInCourses::JTAC_KEY, ['completed' => true, 'license' => ['number' => 'ATH-ACADEMY-JTAC-ABCDEF123456']]],
            [14, 2, AcademyBuiltInCourses::JTAC_KEY, ['chapter' => 2, 'stage' => 3]],
            [14, 2, 'otan-compte-rendu-salute', ['completed' => true]],
            [15, 3, 'formation-communaute', ['completed' => true]],
        ] as [$tenant, $user, $key, $state]) {
            $insert->execute([$tenant, $user, $key, json_encode($state)]);
        }

        return $pdo;
    }

    public function testCompletionCreatesTheAcademyQualificationOnceAndCompletionsFeedTheRule(): void
    {
        $pdo = self::base();
        $service = AcademyBuiltInQualifications::forPdo($pdo);
        self::assertTrue($service->ready());
        self::assertSame([1 => [AcademyBuiltInCourses::JTAC_KEY => true], 2 => ['otan-compte-rendu-salute' => true]], $service->completions(14));
        self::assertSame([], $service->completions(15), 'formation de la communauté : hors cours intégrés');
        self::assertSame($service->completions(14), (new QualificationAwardRuleRepository($pdo))->builtInCourseCompletions(14));

        $id = $service->ensureDefinition(14, 'otan-compte-rendu-salute', 'en', 99);
        self::assertGreaterThan(0, $id);
        self::assertSame($id, $service->ensureDefinition(14, 'otan-compte-rendu-salute', 'fr'), 'retrouvée par son code');
        self::assertSame(0, $service->ensureDefinition(14, 'formation-communaute', 'fr'), 'cours sans qualification');
        $def = $pdo->query('SELECT * FROM personnel_qualification_definitions WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC);
        $q = AcademyBuiltInCourses::qualifications()['otan-compte-rendu-salute'];
        self::assertSame([$q['code'], $q['name']['en'], 14], [$def['code'], $def['name'], (int) $def['tenant_id']]);
        self::assertStringContainsString('Academy course', (string) $def['description']);
        self::assertSame(['Athena Academy', 'school'], array_values($pdo->query('SELECT name, issuer_kind FROM qualification_issuers WHERE id = ' . (int) $def['default_issuer_id'])->fetch(PDO::FETCH_ASSOC)));
        self::assertSame('Academy', $pdo->query('SELECT name FROM qualification_categories WHERE id = ' . (int) $def['category_id'])->fetchColumn());
        $settings = $pdo->query('SELECT * FROM qualification_award_rules WHERE definition_id = ' . $id)->fetch(PDO::FETCH_ASSOC);
        self::assertSame(1, (int) $settings['auto_enabled']);
        self::assertSame('direct', $settings['auto_mode']);
        self::assertSame(AcademyBuiltInQualifications::rule('otan-compte-rendu-salute'), json_decode((string) $settings['rule_json'], true));
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM qualification_award_rules')->fetchColumn());
    }

    /** Exécute l’étape comme run-migrations.php : fichier inclus avec $pdo et $root, sortie capturée. */
    private static function runStep(PDO $pdo): string
    {
        $root = dirname(__DIR__, 2);
        $migrationFlush = static function (): void {
        };
        $migrationEnsurePdo = static function (): void {
        };
        require $root . '/database/steps/_context.php';
        $step = MigrationRegistry::find($root, 'academy_builtin_qualifications');
        self::assertNotNull($step);
        self::assertSame('3490_academy_builtin_qualifications.php', $step['file']);
        ob_start();
        try {
            require $step['path'];
        } finally {
            $output = (string) ob_get_clean();
        }

        return $output;
    }

    public function testMigrationStepCreatesTheQualificationsOfCompletedCoursesAndIsReplayable(): void
    {
        self::assertStringContainsString('rien à faire', self::runStep(new MysqlDdlSqlitePdo()));
        $pdo = self::base();
        $out = self::runStep($pdo);
        self::assertStringContainsString('2 fiche(s) créée(s)', $out);
        self::assertStringContainsString('ACAD-JTAC', $out);
        $codes = $pdo->query('SELECT code FROM personnel_qualification_definitions WHERE tenant_id = 14 ORDER BY code')->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame(['ACAD-JTAC', AcademyBuiltInCourses::qualifications()['otan-compte-rendu-salute']['code']], $codes);
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM personnel_qualification_definitions WHERE tenant_id = 15')->fetchColumn());
        $before = $pdo->query('SELECT * FROM personnel_qualification_definitions ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        self::assertStringContainsString('0 fiche(s) créée(s)', self::runStep($pdo));
        self::assertSame($before, $pdo->query('SELECT * FROM personnel_qualification_definitions ORDER BY id')->fetchAll(PDO::FETCH_ASSOC), 'second passage sans modification');
        self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM qualification_issuers WHERE tenant_id = 14 AND name = 'Athena Academy'")->fetchColumn());
    }
}
