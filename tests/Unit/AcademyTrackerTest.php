<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Response;
use App\Repositories\AcademyRepository;
use App\Services\Academy\AcademyCurriculum;
use App\Services\Academy\AcademyInstructorQueue;
use App\Services\Academy\AcademyTracker;
use PHPUnit\Framework\TestCase;

/**
 * Academy › Tableau de suivi : états calculés depuis les inscriptions, matrice filtrée et paginée, indicateurs, droits
 * des actions en ligne, export CSV, lecture agrégée sans le contenu des cours, rendu (vues « cursus » et « formation »,
 * stagiaire seul sur sa ligne) et câblage (route, menu, actions serveur, tableau de bord instructeur).
 */
final class AcademyTrackerTest extends TestCase
{
    private const NOW = 1_800_000_000;
    private const DAY = 86400;

    protected function setUp(): void
    {
        $_SESSION = ['_csrf_token' => str_repeat('a', 64), 'user_id' => 7];
        $_SERVER['REQUEST_URI'] = '/academy/suivi';
        $GLOBALS['__app_locale'] = 'fr';
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__app_locale']);
    }

    private static function row(array $fields): array
    {
        return AcademyTracker::normalizeRow($fields + ['user_id' => 9, 'course_key' => 'cqb', 'updated' => self::NOW - self::DAY]);
    }

    public function testEveryStateIsComputedFromTheEnrolment(): void
    {
        $cell = static fn (?array $row, ?array $award = null): array => AcademyTracker::cell($row, 6, $award, self::NOW);
        self::assertSame('none', $cell(null)['state']);
        self::assertSame('enrolled', $cell(self::row(['chapter' => 0, 'stage' => 0]))['state']);
        $progress = $cell(self::row(['chapter' => 2, 'stage' => 3]));
        self::assertSame('progress', $progress['state']);
        self::assertSame(40, $progress['pct'], '(2×7+3)/42');
        self::assertSame(2, $progress['chapter']);
        $review = $cell(self::row(['chapter' => 5, 'stage' => 6]));
        self::assertSame('review', $review['state']);
        self::assertTrue($review['final']);
        // Évaluation échouée : même règle que la file « À traiter » (pas d'évaluation relancée).
        self::assertSame('failed', $cell(self::row(['chapter' => 1, 'stage' => 4, 'feedback' => '"failed"']))['state']);
        self::assertSame('progress', $cell(self::row(['chapter' => 1, 'stage' => 4, 'feedback' => 'failed', 'exam_started' => self::NOW]))['state']);
        // MariaDB renvoie true, SQLite 1.
        self::assertSame('validated', $cell(self::row(['completed' => 'true']))['state']);
        self::assertSame(100, $cell(self::row(['completed' => 1]))['pct']);
        self::assertSame('exempt', $cell(self::row(['chapter' => 1, 'exempt_at' => self::NOW]))['state']);
        // Qualification délivrée par le cours : échue ou à moins de 30 jours → à renouveler.
        self::assertSame('expired', $cell(self::row(['completed' => 1]), ['held' => true, 'expires' => self::NOW - self::DAY])['state']);
        self::assertSame('expired', $cell(self::row(['completed' => 1]), ['held' => true, 'expires' => self::NOW + 10 * self::DAY])['state']);
        self::assertSame('validated', $cell(self::row(['completed' => 1]), ['held' => true, 'expires' => self::NOW + 90 * self::DAY])['state']);
        self::assertSame('validated', $cell(null, ['held' => true, 'expires' => null])['state'], 'Qualification délivrée sans inscription');
        // En retard : pas d'activité depuis 14 jours, jamais pour une formation terminée.
        self::assertTrue($cell(self::row(['chapter' => 1, 'stage' => 2, 'updated' => self::NOW - 20 * self::DAY]))['late']);
        self::assertFalse($cell(self::row(['completed' => 1, 'updated' => self::NOW - 90 * self::DAY]))['late']);
        self::assertSame(self::NOW - self::DAY, self::row(['updated' => null, 'updated_at' => date('Y-m-d H:i:s', self::NOW - self::DAY)])['updated']);
    }

    public function testChapterStepsFollowTheLearner(): void
    {
        $steps = static fn (array $row): array => AcademyTracker::steps(AcademyTracker::cell(self::row($row), 4, null, self::NOW), 4);
        self::assertSame(['done', 'done', 'current', 'todo'], $steps(['chapter' => 2, 'stage' => 3]));
        self::assertSame(['done', 'todo', 'todo', 'todo'], $steps(['chapter' => 1, 'stage' => 0]));
        self::assertSame(['done', 'done', 'done', 'review'], $steps(['chapter' => 3, 'stage' => 6]));
        self::assertSame(['failed', 'todo', 'todo', 'todo'], $steps(['chapter' => 0, 'stage' => 4, 'feedback' => 'failed']));
        self::assertSame(['done', 'done', 'done', 'done'], $steps(['completed' => true]));
        self::assertSame(array_fill(0, 4, 'exempt'), $steps(['exempt_at' => 5]));
        self::assertSame(array_fill(0, 4, 'none'), AcademyTracker::steps(AcademyTracker::cell(null, 4, null, self::NOW), 4));
    }

    public function testMatrixFiltersPaginatesAndCountsIndicators(): void
    {
        $members = [
            ['id' => 9, 'name' => 'Viper', 'grade' => 'Sgt', 'units' => [1], 'jobs' => [3], 'groups' => [5]],
            ['id' => 10, 'name' => 'Nomad', 'grade' => 'Cpl', 'units' => [1, 2], 'jobs' => [], 'groups' => []],
            ['id' => 11, 'name' => 'Drift', 'grade' => 'Sdt', 'units' => [2], 'jobs' => [3], 'groups' => [5]],
        ];
        self::assertSame([9, 10], array_column(AcademyTracker::filterMembers($members, ['unit' => 1]), 'id'));
        self::assertSame([9, 11], array_column(AcademyTracker::filterMembers($members, ['job' => 3, 'group' => 5]), 'id'));
        self::assertSame([10], array_column(AcademyTracker::filterMembers($members, ['grade' => 'Cpl']), 'id'));
        self::assertSame([11], array_column(AcademyTracker::filterMembers($members, ['q' => 'dri']), 'id'));

        $columns = [['key' => 'cqb', 'chapters' => 6], ['key' => 'med', 'chapters' => 3]];
        $rows = [
            9 => ['cqb' => self::row(['completed' => true]), 'med' => self::row(['user_id' => 9, 'course_key' => 'med', 'chapter' => 1, 'stage' => 6])],
            10 => ['cqb' => self::row(['user_id' => 10, 'chapter' => 2, 'stage' => 1, 'updated' => self::NOW - 30 * self::DAY])],
            11 => ['med' => self::row(['user_id' => 11, 'course_key' => 'med', 'exempt_at' => self::NOW])],
        ];
        $matrix = AcademyTracker::matrix($members, $columns, $rows, [], self::NOW, 1, '', 2);
        self::assertSame(3, $matrix['total']);
        self::assertSame(2, $matrix['pages']);
        self::assertCount(2, $matrix['rows']);
        $k = $matrix['kpis'];
        self::assertSame([6, 4, 2, 1, 1, 1, 1], [$k['cells'], $k['tracked'], $k['none'], $k['validated'], $k['exempt'], $k['review'], $k['late']]);
        self::assertSame(50, $k['complete_pct'], '(1 validé + 1 dispensé) / 4 suivis');
        self::assertSame(['Drift'], array_column(array_column(AcademyTracker::matrix($members, $columns, $rows, [], self::NOW, 2, '', 2)['rows'], 'member'), 'name'));
        self::assertSame([9], array_map(static fn (array $r): int => $r['member']['id'], AcademyTracker::matrix($members, $columns, $rows, [], self::NOW, 1, 'review')['rows']));
        self::assertSame([10], array_map(static fn (array $r): int => $r['member']['id'], AcademyTracker::matrix($members, $columns, $rows, [], self::NOW, 1, 'late')['rows']));
        // Page hors limites : dernière page.
        self::assertSame(2, AcademyTracker::matrix($members, $columns, $rows, [], self::NOW, 99, '', 2)['page']);
    }

    public function testAwardsOfTheQualificationsDeliveredByEachCourse(): void
    {
        $awards = AcademyTracker::awardsByCourse(
            [['id' => 3, 'courses' => ['cqb']], ['id' => 4, 'courses' => ['cqb', 'med']]],
            [
                ['user_id' => 9, 'definition_id' => 3, 'expires_at' => '2020-01-01', 'status' => 'obtained'],
                ['user_id' => 9, 'definition_id' => 4, 'expires_at' => null, 'status' => 'valid'],
                ['user_id' => 10, 'definition_id' => 3, 'expires_at' => '2030-01-01', 'status' => 'revoked'],
                ['user_id' => 11, 'definition_id' => 3, 'expires_at' => '2020-01-01', 'status' => 'expired'],
            ],
        );
        self::assertSame(['held' => true, 'expires' => null], $awards[9]['cqb'], 'Permanente : la meilleure');
        self::assertSame(['held' => true, 'expires' => null], $awards[9]['med']);
        self::assertArrayNotHasKey(10, $awards, 'Retirée : ne compte pas');
        self::assertSame('expired', AcademyTracker::cell(null, 6, $awards[11]['cqb'], self::NOW)['state']);
    }

    public function testInlineActionsFollowTheAcademyRights(): void
    {
        $all = ['assign' => true, 'teach' => true, 'certify' => true, 'me' => 7];
        $cell = static fn (string $state, bool $final = false): array => ['state' => $state, 'final' => $final, 'reminded' => 0];
        self::assertSame(['enroll', 'exempt'], AcademyTracker::actions($cell('none'), 9, $all));
        self::assertSame(['remind', 'exempt'], AcademyTracker::actions($cell('progress'), 9, $all));
        self::assertSame(['validate', 'exempt'], AcademyTracker::actions($cell('review', true), 9, $all));
        self::assertSame(['unexempt'], AcademyTracker::actions($cell('exempt'), 9, $all));
        self::assertSame([], AcademyTracker::actions($cell('validated'), 9, $all));
        // Dernier chapitre sans droit « délivrer » : pas de validation, pas de dispense.
        self::assertSame([], AcademyTracker::actions($cell('review', true), 9, ['certify' => false, 'assign' => false] + $all));
        self::assertSame(['validate'], AcademyTracker::actions($cell('review'), 9, ['certify' => false, 'assign' => false] + $all));
        // Cours que l'on n'encadre pas : inscrire et relancer seulement avec le droit « inscrire ».
        self::assertSame(['enroll'], AcademyTracker::actions($cell('none'), 9, ['teach' => false] + $all));
        self::assertSame([], AcademyTracker::actions($cell('review'), 9, ['teach' => false, 'assign' => false] + $all));
        // Jamais sur soi-même (sauf s'inscrire).
        self::assertSame(['enroll'], AcademyTracker::actions($cell('none'), 7, $all));
        self::assertSame([], AcademyTracker::actions($cell('review'), 7, $all));
        // Une relance par 24 h.
        self::assertTrue(AcademyTracker::canRemindNow(['state' => 'progress', 'reminded' => self::NOW - 2 * self::DAY], self::NOW));
        self::assertFalse(AcademyTracker::canRemindNow(['state' => 'progress', 'reminded' => self::NOW - 3600], self::NOW));
        self::assertFalse(AcademyTracker::canRemindNow(['state' => 'validated', 'reminded' => 0], self::NOW));
    }

    public function testCsvExportIsExcelFriendlyAndNeutralisesFormulas(): void
    {
        $csv = AcademyTracker::csv(['Stagiaire', 'CQB'], [['=HYPERLINK("x")', 'Validé'], ['Nomad', 'En cours (40 %)']]);
        self::assertStringStartsWith("\xEF\xBB\xBF", $csv);
        self::assertStringContainsString("Stagiaire;CQB\n", $csv);
        self::assertStringContainsString("\"'=HYPERLINK(\"\"x\"\")\";Validé", $csv);
        self::assertStringContainsString('Nomad;"En cours (40 %)"', $csv);
    }

    public function testTrackerRowsIsOneTenantScopedReadWithoutCourseContent(): void
    {
        $pdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE academy_learners (tenant_id INTEGER, user_id INTEGER, course_key TEXT, state_json TEXT, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $insert = $pdo->prepare('INSERT INTO academy_learners (tenant_id, user_id, course_key, state_json) VALUES (?, ?, ?, ?)');
        $snapshot = AcademyCurriculum::demo();
        $insert->execute([1, 9, 'cqb', json_encode(['chapter' => 1, 'stage' => 6, 'debriefs' => [1 => 'Débriefing du stagiaire.'], 'snapshot' => $snapshot, 'updated' => self::NOW])]);
        $insert->execute([1, 10, 'cqb', json_encode(['chapter' => 0, 'stage' => 4, 'feedback' => 'failed', 'debriefs' => ['caché'], 'snapshot' => $snapshot, 'reminded' => self::NOW])]);
        $insert->execute([1, 11, 'cqb', json_encode(['completed' => true, 'exempt' => ['by' => 7, 'at' => self::NOW], 'snapshot' => $snapshot])]);
        $insert->execute([1, 9, 'autre', json_encode(['chapter' => 0, 'stage' => 1])]);
        $insert->execute([2, 12, 'cqb', json_encode(['chapter' => 0, 'stage' => 6])]);
        $insert->execute([1, 9, '__notes', json_encode(['text' => 'privé'])]);
        $repository = new AcademyRepository($pdo);
        $raw = $repository->trackerRows(1, ['cqb', '__notes', 'Bad Key']);
        self::assertCount(3, $raw);
        $json = json_encode($raw);
        self::assertStringNotContainsString('snapshot', $json);
        self::assertStringNotContainsString('chapters', $json);
        self::assertStringNotContainsString('caché', $json, 'Débriefing lu seulement pour un chapitre à valider');
        $rows = array_column(array_map([AcademyTracker::class, 'normalizeRow'], $raw), null, 'user_id');
        self::assertSame('Débriefing du stagiaire.', $rows[9]['debriefs'][1]);
        self::assertTrue($rows[10]['failed']);
        self::assertSame(self::NOW, $rows[10]['reminded']);
        self::assertTrue($rows[11]['exempt']);
        self::assertTrue($rows[11]['completed']);
        self::assertCount(1, $repository->trackerRows(1, ['cqb'], 10));
        self::assertSame([], $repository->trackerRows(1, []));
        // Relance : notée sans changer la date d'activité du stagiaire.
        $pdo->exec("UPDATE academy_learners SET updated_at = '2026-01-01 10:00:00' WHERE user_id = 9 AND course_key = 'cqb'");
        $repository->markReminded(1, 9, 'cqb', self::NOW + 5);
        $repository->markReminded(2, 9, 'cqb', 1);
        $after = $pdo->query("SELECT state_json, updated_at FROM academy_learners WHERE tenant_id = 1 AND user_id = 9 AND course_key = 'cqb'")->fetch(\PDO::FETCH_ASSOC);
        self::assertSame(self::NOW + 5, json_decode($after['state_json'], true)['reminded']);
        self::assertSame(1, json_decode($after['state_json'], true)['chapter']);
        self::assertSame('2026-01-01 10:00:00', $after['updated_at']);
        self::assertSame(['autre' => 1, 'cqb' => 3], (static function (array $c): array { ksort($c); return $c; })($repository->enrollmentCounts(1)));
    }

    public function testQueueNoLongerFollowsExemptedLearners(): void
    {
        $snapshot = AcademyCurriculum::demo();
        $row = static fn (array $state): array => ['user_id' => 9, 'course_key' => 'cqb', 'updated_at' => date('Y-m-d H:i:s', self::NOW - 30 * self::DAY), 'state' => $state + ['snapshot' => $snapshot, 'learner_id' => 9]];
        $queue = static fn (array $state): int => count(AcademyInstructorQueue::build([$row($state)], static fn (): bool => true, true, [], [], 7, self::NOW)['items']);
        self::assertSame(1, $queue(['chapter' => 0, 'stage' => 1]));
        self::assertSame(0, $queue(['chapter' => 0, 'stage' => 1, 'exempt' => ['by' => 7, 'at' => self::NOW]]));
    }

    /** Données au format de AcademyController::trackerData(). */
    private static function tracker(string $view, bool $staff = true, array $can = []): array
    {
        $course = AcademyCurriculum::demo();
        $course['key'] = 'cqb';
        $course['license_code'] = 'CQB';
        $course['title'] = ['fr' => 'Combat en zone urbaine', 'en' => 'Close quarters battle'];
        $other = ['key' => 'med', 'title' => ['fr' => 'Secourisme au combat', 'en' => 'Combat first aid'], 'chapters' => [['title' => ['fr' => 'Garrot']]]];
        $courses = ['cqb' => $course, 'med' => $other];
        $chapters = count($course['chapters']);
        $members = $staff
            ? [['id' => 9, 'name' => 'Talon', 'grade' => '1cl', 'unit' => 'ALPHA'], ['id' => 10, 'name' => 'Drift', 'grade' => 'Sdt', 'unit' => 'BRAVO'], ['id' => 11, 'name' => 'Raven', 'grade' => 'Cpl', 'unit' => 'ALPHA']]
            : [['id' => 7, 'name' => 'Moi', 'grade' => '', 'unit' => '']];
        $rows = $staff ? [
            9 => ['cqb' => self::row(['chapter' => 1, 'stage' => 6, 'debriefs' => json_encode([1 => 'Entrée dynamique réussie, débriefing complet.']), 'updated' => self::NOW - self::DAY])],
            10 => ['cqb' => self::row(['user_id' => 10, 'chapter' => 0, 'stage' => 2, 'updated' => self::NOW - 20 * self::DAY])],
        ] : [7 => ['cqb' => self::row(['user_id' => 7, 'chapter' => 1, 'stage' => 1])]];
        $columns = $view === 'course' ? [['key' => 'cqb', 'chapters' => $chapters, 'course' => $course]]
            : [['key' => 'cqb', 'chapters' => $chapters, 'course' => $course], ['key' => 'med', 'chapters' => 1, 'course' => $other]];
        $matrix = AcademyTracker::matrix($members, $columns, $rows, [], self::NOW);
        return [
            'view' => $view, 'focus' => 'cqb', 'columns' => $columns, 'courses' => $courses, 'counts' => ['cqb' => 2], 'matrix' => $matrix, 'rows' => $rows,
            'filters' => ['unit' => 0, 'job' => 0, 'grade' => '', 'group' => 0, 'q' => ''], 'state' => '', 'onlyEnrolled' => false, 'now' => self::NOW,
            'options' => $staff ? ['unit' => [1 => 'Alpha', 2 => 'Bravo'], 'job' => [3 => 'Infirmier'], 'group' => [5 => 'Promotion 2026'], 'grade' => ['1cl', 'Cpl', 'Sdt']] : [],
            'can' => $can + ['assign' => $staff, 'certify' => $staff, 'me' => 7, 'teach' => $staff ? ['cqb' => true, 'med' => true] : [], 'detail' => $staff],
            'query' => $view === 'course' ? ['vue' => 'formation', 'course' => 'cqb', 'unite' => 1] : ['unite' => 1],
        ];
    }

    private function render(array $tracker, bool $staff = true, string $notice = ''): string
    {
        return Response::view('academy.space', ['space' => 'suivi', 'staff' => $staff, 'states' => [], 'courses' => [], 'selected' => null, 'state' => null,
            'learners' => [], 'notice' => $notice, 'noticeCount' => 3, 'tracker' => $tracker, 'instructorBadge' => 0])->body();
    }

    public function testCourseViewReusesTheCqbTrackerLayoutWithInlineActions(): void
    {
        foreach (['fr' => ['Tableau de suivi', 'Par formation', 'Taux de complétion', 'À valider', 'En retard', 'Exporter (CSV)', 'Imprimer', 'Inscrire toute l’unité', 'Relancer les retardataires', 'Valider le chapitre', 'Dispenser', 'Légende', '3 stagiaire(s) inscrit(s).'],
            'en' => ['Training tracker', 'By course', 'Completion rate', 'To validate', 'Falling behind', 'Export (CSV)', 'Enrol the whole unit', 'Remind those falling behind', 'Validate chapter', 'Exempt', 'Legend', '3 learner(s) enrolled.']] as $lang => $texts) {
            $GLOBALS['__app_locale'] = $lang;
            $body = $this->render(self::tracker('course'), true, 'trk_enrolled');
            foreach ($texts as $text) {
                self::assertStringContainsString($text, $body, $lang . ' : ' . $text);
            }
        }
        $GLOBALS['__app_locale'] = 'fr';
        $body = $this->render(self::tracker('course'));
        // Composants du tableau CQB : badge de formation, bandes de chapitres, barre, pastille d'état, avatar à initiales.
        foreach (['academy-tracker.css', 'academy-tracker.js', 'academy-queue.js', 'class="trk-course__badge" translate="no">CQB<', 'class="trk-steps"', 'class="trk-meter', 'trk-st--review', 'trk-st--progress', 'trk-st--none', '<i>TA</i>'] as $needle) {
            self::assertStringContainsString($needle, $body, $needle);
        }
        self::assertStringContainsString('<i class="is-done"', $body);
        self::assertStringContainsString('<i class="is-review"', $body);
        self::assertStringContainsString('<i class="is-current"', $body);
        // Validation : même action que la file « À traiter », retour au tableau avec ses filtres.
        self::assertStringContainsString('<dialog class="q-dialog" id="trk-rv-9-cqb"', $body);
        self::assertStringContainsString('action="/academy/action/validate"', $body);
        self::assertStringContainsString('name="return" value="tracker"', $body);
        self::assertStringContainsString('name="back" value="?vue=formation&amp;course=cqb&amp;unite=1"', $body);
        self::assertStringContainsString('Entrée dynamique réussie, débriefing complet.', $body);
        // Relancer / inscrire / dispenser : formulaires du serveur, protégés par le jeton CSRF.
        self::assertStringContainsString('formaction="/academy/action/tracker_remind" name="cell" value="10|cqb"', $body);
        self::assertStringContainsString('formaction="/academy/action/tracker_enroll" name="cell" value="11|cqb"', $body);
        self::assertStringContainsString('data-trk-exempt="10|cqb"', $body);
        self::assertStringContainsString('<dialog class="q-dialog" id="trk-exempt"', $body);
        self::assertGreaterThanOrEqual(5, substr_count($body, 'name="_csrf_token"'));
        self::assertStringContainsString('/academy/suivi?vue=formation&amp;course=cqb&amp;unite=1&amp;export=csv', $body);
        // Menu Academy : entrée « Tableau de suivi » active.
        self::assertMatchesRegularExpression('/href="\/academy\/suivi"[^>]*aria-current="page"/', $body);
    }

    public function testPathViewIsAMatrixOfLearnersByCourse(): void
    {
        $body = $this->render(self::tracker('path'));
        self::assertStringContainsString('class="trk-table"', $body);
        self::assertStringContainsString('Par cursus', $body);
        self::assertStringContainsString('Combat en zone urbaine', $body);
        self::assertStringContainsString('Secourisme au combat', $body);
        self::assertStringContainsString('trk-cell--review', $body);
        self::assertStringContainsString('trk-cell--progress is-late', $body);
        self::assertStringContainsString('data-trk-cols', $body);
        self::assertStringContainsString('Voir le détail', $body);
        self::assertStringContainsString('/academy/instructor#eleve-9-cqb', $body);
        self::assertStringContainsString('Chapitre 2 sur', $body);
        // Filtres : unité, fonction, cohorte, grade, état.
        foreach (['name="unite"', 'name="fonction"', 'name="cohorte"', 'name="grade"', 'name="etat"', 'Promotion 2026'] as $needle) {
            self::assertStringContainsString($needle, $body, $needle);
        }
        // Sans droit « inscrire » ni « délivrer » : ni inscription, ni dispense.
        $limited = $this->render(self::tracker('path', true, ['assign' => false, 'certify' => false]));
        self::assertStringNotContainsString('/academy/action/tracker_enroll" name="cell"', $limited);
        self::assertStringNotContainsString('data-trk-exempt=', $limited);
        self::assertStringNotContainsString('Inscrire toute l’unité', $limited);
    }

    public function testLearnerSeesOnlyTheirOwnLineWithoutActions(): void
    {
        $body = $this->render(self::tracker('path', false), false);
        self::assertStringContainsString('Votre avancement dans chacune de vos formations.', $body);
        self::assertStringContainsString('<b>Moi</b>', $body);
        self::assertStringNotContainsString('Talon', $body);
        self::assertStringNotContainsString('tracker_enroll', $body);
        self::assertStringNotContainsString('tracker_remind', $body);
        self::assertStringNotContainsString('data-trk-pick', $body);
        self::assertStringNotContainsString('name="unite"', $body);
    }

    public function testControllerWiringRightsAndSafeReturn(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = str_replace("\r\n", "\n", (string) file_get_contents($root . '/app/Controllers/Web/AcademyController.php'));
        self::assertStringContainsString("'droits', 'suivi']", $controller);
        self::assertStringContainsString("if (\$space === 'suivi') {", $controller);
        self::assertStringContainsString("['tracker_enroll', 'tracker_remind', 'tracker_exempt', 'tracker_unexempt']", $controller);
        self::assertStringContainsString("inputString('return') === 'tracker'", $controller);
        // Droits revérifiés côté serveur.
        self::assertStringContainsString("if (!\$rights->can('assign')) {\n                return \$this->error('forbidden', 403);", $controller);
        self::assertStringContainsString("if (!\$rights->canTeach(\$key) && !\$rights->can('assign')) {", $controller);
        self::assertStringContainsString("if (!\$rights->canTeach(\$key) || !\$rights->can('certify')) {", $controller);
        // Stagiaire : sa seule ligne (lecture limitée à lui).
        self::assertStringContainsString('trackerRows($tenantId, $keys, $staff ? null : $userId)', $controller);
        $url = new \ReflectionMethod(\App\Controllers\Web\AcademyController::class, 'trackerUrl');
        $instance = (new \ReflectionClass(\App\Controllers\Web\AcademyController::class))->newInstanceWithoutConstructor();
        self::assertSame('/academy/suivi?vue=formation&course=cqb', $url->invoke($instance, '?vue=formation&course=cqb'));
        self::assertSame('/academy/suivi', $url->invoke($instance, '//evil.example/'));
        self::assertSame('/academy/suivi', $url->invoke($instance, '?a="><script>'));
        // Entrées : menu Academy, espace instructeur, tableau de bord.
        self::assertStringContainsString("'instructor','suivi','studio'", (string) file_get_contents($root . '/views/academy/init.php'));
        self::assertStringContainsString("\$link('suivi')", (string) file_get_contents($root . '/views/academy/instructor.php'));
        self::assertStringContainsString("\$link('suivi')", (string) file_get_contents($root . '/views/academy/queue_dashboard.php'));
        self::assertFileExists($root . '/public/assets/js/academy-tracker.js');
    }

    public function testDashboardLinksToTheTracker(): void
    {
        $course = AcademyCurriculum::demo();
        $_SERVER['REQUEST_URI'] = '/academy/dashboard';
        $body = Response::view('academy.space', ['space' => 'dashboard', 'staff' => true, 'states' => [], 'courses' => [$course], 'selected' => $course, 'state' => null,
            'learners' => [], 'notice' => '', 'queue' => ['items' => [], 'counts' => [], 'total' => 0, 'sessions' => [], 'can' => []], 'instructorBadge' => 0, 'queueMembers' => []])->body();
        self::assertStringContainsString('Ouvrir le tableau de suivi', $body);
        self::assertStringContainsString('href="/academy/suivi"', $body);
    }
}
