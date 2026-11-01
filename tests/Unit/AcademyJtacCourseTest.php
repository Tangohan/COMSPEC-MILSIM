<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Academy\AcademyBuiltInCourses;
use App\Services\Academy\AcademyCurriculum;
use App\Support\Migrations\MigrationRegistry;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlDdlSqlitePdo;

require_once dirname(__DIR__, 2) . '/bootstrap/migration_runner.php';

/**
 * Cours JTAC 2.0 : cinq phases de la procédure de la communauté + brevity, sept étapes complètes FR/EN par chapitre,
 * questions à trois choix valides, parcours complet jusqu'à la licence et mise à jour des inscrits non commencés.
 */
final class AcademyJtacCourseTest extends TestCase
{
    private static function course(): array
    {
        $course = AcademyBuiltInCourses::find(AcademyBuiltInCourses::JTAC_KEY);
        self::assertIsArray($course);

        return $course;
    }

    private static function both(mixed $text): string
    {
        self::assertIsArray($text);
        foreach (['fr', 'en'] as $lang) {
            self::assertIsString($text[$lang] ?? null, $lang);
            self::assertNotSame('', trim($text[$lang]), $lang);
        }

        return $text['fr'] . "\n" . $text['en'];
    }

    private static function assertQuestion(array $question): void
    {
        self::both($question['question']);
        self::both($question['feedback']);
        self::assertCount(3, $question['options']);
        foreach ($question['options'] as $option) {
            self::both($option);
        }
        self::assertContains($question['answer'], [0, 1, 2]);
    }

    public function testCourseFollowsTheFivePhasesThenBrevityWithSevenCompleteStages(): void
    {
        $course = self::course();
        self::assertSame($course, AcademyCurriculum::validate($course));
        self::assertSame('2.0', $course['version']);
        self::assertTrue($course['built_in']);
        self::assertCount(6, $course['chapters']);
        $titles = array_map(static fn (array $c): string => $c['title']['fr'] . ' | ' . $c['title']['en'], $course['chapters']);
        foreach (['Contact initial', 'Situation update', 'Game plan', '9-line', 'BDA', 'Brevity'] as $i => $needle) {
            self::assertStringContainsStringIgnoringCase($needle, $titles[$i]);
        }
        $total = 0;
        foreach ($course['chapters'] as $index => $chapter) {
            self::assertCount(7, AcademyCurriculum::STAGES);
            self::both($chapter['theory']);
            self::both($chapter['demonstration']);
            foreach (['guided', 'simulation'] as $stage) {
                self::both($chapter[$stage]['brief']);
                self::assertQuestion($chapter[$stage]);
            }
            self::both($chapter['guided']['hint']);
            self::assertArrayNotHasKey('hint', $chapter['simulation'], 'Simulation autonome sans aide');
            self::assertNotSame($chapter['guided']['question'], $chapter['simulation']['question'], 'Cas guidé et autonome distincts');
            self::both($chapter['assessment']['instructions']);
            self::assertSame(80, $chapter['assessment']['pass_percent']);
            self::assertGreaterThanOrEqual(5, count($chapter['assessment']['questions']), 'Chapitre ' . $index);
            $answers = [];
            foreach ($chapter['assessment']['questions'] as $question) {
                self::assertQuestion($question);
                $answers[] = $question['answer'];
            }
            self::assertGreaterThan(1, count(array_unique($answers)), 'Bonne réponse toujours à la même place');
            $total += count($answers);
            self::both($chapter['debrief']['prompt']);
            self::both($chapter['validation']['brief']);
            self::assertNotEmpty($chapter['debrief']['criteria']);
            self::assertNotEmpty($chapter['validation']['criteria']);
            self::assertNotEmpty($chapter['resources']);
        }
        self::assertSame(43, $total);
    }

    public function testContentCoversTheCommunityProcedureAndStaysAnInGameTraining(): void
    {
        $course = self::course();
        $all = json_encode($course, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        self::assertIsString($all);
        $chapter = static fn (int $i): string => (string) json_encode($course['chapters'][$i], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        foreach (['code laser', 'code abort', 'rover', 'Trafic aérien'] as $needle) {
            self::assertStringContainsString($needle, $chapter(0));
        }
        foreach (['Type 1', 'Type 2', 'Type 3', 'bomb on target', 'bomb on coordinate', 'JDAM', 'GBU-38', 'retour radio si prêt pour 9-Line', 'Voodoo 1'] as $needle) {
            self::assertStringContainsString($needle, $chapter(2));
        }
        foreach (['IP / BP', 'Heading / Offset', 'Target Elevation', 'Target Description', 'Target Location', 'Mark Type', 'Friendlies', 'Egress', 'au moins 4 km', 'danger close', 'Continue'] as $needle) {
            self::assertStringContainsString($needle, $chapter(3));
        }
        foreach (['Abort', 'Continue dry', 'Clear hot', 'Cleared to engage', 'talk-on', 'nouvelle 9-Line', 'libère la patrouille'] as $needle) {
            self::assertStringContainsString($needle, $chapter(4));
        }
        foreach (['Visual', 'Blind', 'Tally', 'No Joy', 'Contact', 'Spot', 'Captured', 'Winchester', 'Paveway', 'Dust Off'] as $needle) {
            self::assertStringContainsString($needle, $chapter(5));
        }
        // La désignation d'objectifs fait désormais partie du cursus ; la mention « formation de jeu » demeure.
        self::assertStringNotContainsString('ne font pas partie de ce cursus', $all);
        self::assertStringNotContainsString('outside this curriculum', $all);
        self::assertStringContainsString('aucune valeur de qualification réelle', $course['chapters'][0]['theory']['fr']);
        self::assertStringContainsString('no real-world', $course['chapters'][0]['theory']['en']);
        self::assertStringContainsString('sans valeur de qualification réelle', $course['license']['fr']);
    }

    public function testEachChapterRequiresEightyPercentAndTheFullPathwayIssuesTheLicence(): void
    {
        $course = self::course();
        $state = ['chapter' => 0, 'stage' => 0, 'learner_id' => 10];
        $now = 1000;
        foreach ($course['chapters'] as $index => $chapter) {
            $questions = AcademyCurriculum::examQuestions($chapter);
            $answers = array_column($questions, 'answer');
            $needed = (int) ceil(count($answers) * 0.8);
            foreach (['advance', 'advance', 'practice', 'practice', 'exam_start', 'exam_submit'] as $action) {
                $state = AcademyCurriculum::transition($state, $course, $action, [
                    'expected_stage' => $state['stage'], 'expected_chapter' => $state['chapter'],
                    'answer' => AcademyCurriculum::questionForStage($chapter, (int) $state['stage'])['answer'],
                    'answers' => $answers, 'token' => $state['exam']['token'] ?? '',
                ], $now++);
            }
            self::assertSame(5, $state['stage'], 'Chapitre ' . $index);
            self::assertSame(100, end($state['attempts'])['score']);
            // Un seuil de 80 % : une réponse de moins que le minimum échoue.
            $wrong = $answers;
            for ($i = 0; $i < count($answers) - $needed + 1; $i++) {
                $wrong[$i] = ($answers[$i] + 1) % 3;
            }
            $probe = AcademyCurriculum::transition(['chapter' => $index, 'stage' => 4] + $state, $course, 'exam_start', ['expected_stage' => 4, 'expected_chapter' => $index], $now);
            $probe = AcademyCurriculum::transition($probe, $course, 'exam_submit', ['expected_stage' => 4, 'expected_chapter' => $index, 'answers' => $wrong, 'token' => $probe['exam']['token']], $now + 1);
            self::assertFalse(end($probe['attempts'])['passed'], 'Chapitre ' . $index);
            $state = AcademyCurriculum::transition($state, $course, 'debrief', ['expected_stage' => 5, 'expected_chapter' => $index, 'reflection' => 'J’ai suivi les phases dans l’ordre et je répéterai le 9-line à la radio.'], $now++);
            self::assertArrayNotHasKey('license', $state);
            $state = AcademyCurriculum::validateChapter($state, $course, 99, 'Phrase radio répétée correctement en session.', $now++);
        }
        self::assertTrue($state['completed']);
        self::assertSame('2.0', $state['license']['course_version']);
        self::assertSame('simulation', $state['license']['scope']);
        self::assertCount(6, $state['validations']);
    }

    /** Exécute l'étape comme run-migrations.php : fichier inclus avec $pdo et $root, sortie capturée. */
    private static function runStep(PDO $pdo): string
    {
        $root = dirname(__DIR__, 2);
        $migrationFlush = static function (): void {
        };
        $migrationEnsurePdo = static function (): void {
        };
        require $root . '/database/steps/_context.php';
        $step = MigrationRegistry::find($root, 'academy_jtac_v2_refresh');
        self::assertNotNull($step);
        self::assertSame('3280_academy_jtac_v2_refresh.php', $step['file']);
        ob_start();
        try {
            require $step['path'];
        } finally {
            $output = (string) ob_get_clean();
        }

        return $output;
    }

    public function testRefreshStepOnlyUpdatesUntouchedEnrolmentsAndIsIdempotent(): void
    {
        $pdo = new MysqlDdlSqlitePdo();
        self::assertStringContainsString('[OK]', self::runStep($pdo), 'Sans table : aucune erreur');
        (new \App\Support\Migrations\SchemaInspector($pdo))->runSqlFile(dirname(__DIR__, 2) . '/migrations/20261029130000_athena_academy.sql');
        $old = ['key' => AcademyBuiltInCourses::JTAC_KEY, 'version' => '1.0', 'chapters' => [['title' => ['fr' => 'Ancien', 'en' => 'Old']]]];
        $rows = [
            1 => ['chapter' => 0, 'stage' => 0, 'learner_id' => 1, 'snapshot' => $old],
            2 => ['chapter' => 0, 'stage' => 3, 'learner_id' => 2, 'snapshot' => $old, 'practice' => [['correct' => true]]],
            3 => ['chapter' => 0, 'stage' => 6, 'learner_id' => 3, 'snapshot' => $old, 'completed' => true, 'license' => ['number' => 'ATH-ACADEMY-JTAC-ABCDEF123456']],
            4 => ['chapter' => 0, 'stage' => 0, 'learner_id' => 4, 'snapshot' => $old],
        ];
        $insert = $pdo->prepare('INSERT INTO academy_learners (tenant_id, user_id, course_key, state_json) VALUES (?, ?, ?, ?)');
        foreach ($rows as $user => $state) {
            $insert->execute([14, $user, AcademyBuiltInCourses::JTAC_KEY, json_encode($state, JSON_UNESCAPED_UNICODE)]);
        }
        $insert->execute([14, 4, 'course-community', json_encode($rows[4], JSON_UNESCAPED_UNICODE)]);
        $read = static fn (): array => $pdo->query('SELECT user_id, course_key, state_json FROM academy_learners ORDER BY user_id, course_key')->fetchAll(PDO::FETCH_ASSOC);

        $out = self::runStep($pdo);
        self::assertStringContainsString('2 inscription(s)', $out);
        self::assertStringContainsString('2 parcours', $out);
        $after = [];
        foreach ($read() as $row) {
            $after[$row['user_id'] . ':' . $row['course_key']] = json_decode($row['state_json'], true);
        }
        $key = AcademyBuiltInCourses::JTAC_KEY;
        self::assertSame(self::course(), $after['1:' . $key]['snapshot']);
        self::assertSame(self::course(), $after['4:' . $key]['snapshot']);
        self::assertSame($old, $after['2:' . $key]['snapshot'], 'Parcours entamé conservé');
        self::assertSame($rows[2]['practice'], $after['2:' . $key]['practice']);
        self::assertSame($old, $after['3:' . $key]['snapshot'], 'Licence conservée sur sa version');
        self::assertSame('ATH-ACADEMY-JTAC-ABCDEF123456', $after['3:' . $key]['license']['number']);
        self::assertSame($old, $after['4:course-community']['snapshot'], 'Autre cours non touché');

        $before = $read();
        self::assertStringContainsString('0 inscription(s)', self::runStep($pdo));
        self::assertSame($before, $read(), 'Second passage sans modification');
    }
}
