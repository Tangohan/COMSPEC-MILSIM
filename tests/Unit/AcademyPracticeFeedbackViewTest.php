<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Response;
use App\Services\Academy\AcademyCurriculum;
use PHPUnit\Framework\TestCase;

/**
 * Salle de cours : le retour d'un exercice s'affiche sur la question validée (choix marqué, carte de retour,
 * « Continuer vers… »), plus en tête de l'étape suivante ; évaluation : écran de résultat sans dévoiler les réponses
 * avant la réussite. Les textes d'étape passent par le rendu riche.
 */
final class AcademyPracticeFeedbackViewTest extends TestCase
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

    private function course(): array
    {
        $course = AcademyCurriculum::demo();
        $course['key'] = 'course-0123456789abcdef';
        $course['chapters'][0]['demonstration']['fr'] = "## Démonstration\n:::transmission\nBastion : Voodoo 1, prêt ?\n:::\n:::analyse\nLe **JTAC** vérifie.\n:::";
        $course['chapters'][0]['feedback']['fr'] = 'Le clignotement est **visible** ; sa cause reste inconnue.';
        $course['chapters'][0]['simulation'] = [
            'brief' => ['fr' => "Situation :\n- un convoi\n- un pont", 'en' => 'Situation'],
            'question' => ['fr' => 'Que faites-vous ?', 'en' => 'What do you do?'],
            'options' => [['fr' => 'Observer', 'en' => 'Observe'], ['fr' => 'Tirer', 'en' => 'Fire'], ['fr' => 'Partir', 'en' => 'Leave']],
            'answer' => 0,
            'feedback' => ['fr' => 'Explication secrète de la simulation', 'en' => 'Secret'],
        ];
        $course['chapters'][0]['assessment'] = ['instructions' => ['fr' => 'Consignes **importantes**', 'en' => 'Rules'], 'seconds' => 300, 'pass_percent' => 50, 'questions' => [
            ['question' => ['fr' => 'Question une', 'en' => 'Q1'], 'options' => [['fr' => 'Choix bon', 'en' => 'a'], ['fr' => 'Choix faux', 'en' => 'b'], ['fr' => 'Autre', 'en' => 'c']], 'answer' => 0, 'feedback' => ['fr' => 'Pourquoi la une', 'en' => 'Why'], 'option_feedback' => [['fr' => 'Oui', 'en' => 'Yes'], ['fr' => 'Non', 'en' => 'No'], ['fr' => 'Non plus', 'en' => 'Neither']]],
            ['question' => ['fr' => 'Question deux', 'en' => 'Q2'], 'options' => [['fr' => 'Mauvais choix', 'en' => 'a'], ['fr' => 'Bon choix deux', 'en' => 'b'], ['fr' => 'Autre deux', 'en' => 'c']], 'answer' => 1, 'feedback' => ['fr' => 'Pourquoi la deux', 'en' => 'Why'], 'option_feedback' => [['fr' => 'Non', 'en' => 'No'], ['fr' => 'Oui', 'en' => 'Yes'], ['fr' => 'Non plus', 'en' => 'Neither']]],
        ]];
        return AcademyCurriculum::validate($course);
    }

    private function render(array $state, string $step = ''): string
    {
        $course = $this->course();
        $state += ['snapshot' => $course];
        return Response::view('academy.space', [
            'space' => 'classroom', 'staff' => false, 'states' => [$course['key'] => $state], 'courses' => [$course], 'selected' => $course,
            'state' => $state, 'learners' => [], 'notice' => '', 'viewStep' => $step,
        ])->body();
    }

    public function testDemonstrationUsesRichTextRendering(): void
    {
        $body = $this->render(['chapter' => 0, 'stage' => 1]);
        self::assertStringContainsString('<div class="lesson-text rt"><h2 class="rt-h">Démonstration</h2>', $body);
        self::assertStringContainsString('<b class="rt-tx__who">Bastion</b>', $body);
        self::assertStringContainsString('rt-callout--analysis', $body);
        self::assertStringContainsString('Le <strong>JTAC</strong> vérifie.', $body);
        self::assertStringContainsString('assets/css/academy-richtext.css', $body);
        self::assertStringContainsString('data-academy-theme', $body);
    }

    public function testCorrectAnswerIsShownOnTheValidatedQuestionWithAContinueButton(): void
    {
        $state = ['chapter' => 0, 'stage' => 3, 'feedback' => 'correct', 'practice' => [['chapter' => 0, 'stage' => 2, 'answer' => 0, 'correct' => true, 'at' => 1]],
            'last_practice_feedback' => ['fr' => 'x', 'en' => 'x']];
        $body = $this->render($state, '0.2');
        self::assertStringContainsString('acr-result acr-result--ok is-new', $body);
        self::assertStringContainsString('data-result-announce="Bonne réponse : choix A."', $body);
        self::assertStringContainsString('<li class="op is-correct">', $body);
        self::assertSame(2, substr_count($body, ' is-off"'), 'Autres choix désactivés');
        self::assertStringContainsString('Votre choix · Bonne réponse', $body);
        self::assertStringContainsString('Le clignotement est <strong>visible</strong>', $body);
        self::assertStringContainsString('Continuer vers simulation autonome', $body);
        self::assertStringContainsString('step=0.3', $body);
        self::assertStringNotContainsString('Vous relisez', $body);
        // L'étape suivante ne reprend plus le bandeau de l'étape précédente.
        $next = $this->render($state);
        self::assertStringNotContainsString('acr-result', $next);
        self::assertStringNotContainsString('L’étape suivante est disponible', $next);
        self::assertStringNotContainsString('acr-feedback--ok', $next);
        // Relecture plus tard : la réponse validée reste marquée, sans annonce.
        $later = $this->render(['feedback' => null] + $state, '0.2');
        self::assertStringContainsString('Réponse validée', $later);
        self::assertStringNotContainsString('data-result-announce', $later);
        self::assertStringNotContainsString('Continuer vers', $later);
    }

    public function testWrongGuidedAnswerIsMarkedWithExplanationAndRetryWithoutRevealingTheLetter(): void
    {
        $body = $this->render(['chapter' => 0, 'stage' => 2, 'feedback' => 'retry', 'practice' => [['chapter' => 0, 'stage' => 2, 'answer' => 1, 'correct' => false, 'at' => 1]]]);
        self::assertStringContainsString('<label class="op is-wrong">', $body);
        self::assertSame(1, substr_count($body, 'is-wrong'));
        self::assertStringContainsString('✗</span> Choix précédent', $body);
        self::assertStringContainsString('acr-result acr-result--ko', $body);
        self::assertStringContainsString('Pas tout à fait', $body);
        self::assertStringContainsString('data-result-announce="Réponse incorrecte : choix B."', $body);
        self::assertStringContainsString('Le clignotement est <strong>visible</strong>', $body);
        self::assertStringContainsString('data-retry', $body);
        self::assertStringNotContainsString('is-correct', $body, 'La bonne réponse n’est pas révélée avant un nouvel essai');
        self::assertStringContainsString('/action/practice', $body);
    }

    public function testWrongSimulationAnswerGivesNoExplanation(): void
    {
        $body = $this->render(['chapter' => 0, 'stage' => 3, 'feedback' => 'retry', 'practice' => [['chapter' => 0, 'stage' => 3, 'answer' => 2, 'correct' => false, 'at' => 1]]]);
        self::assertStringContainsString('aucune aide n’est donnée pendant la simulation', $body);
        self::assertStringNotContainsString('Explication secrète de la simulation', $body);
        self::assertStringContainsString('<ul class="rt-list"><li>un convoi</li><li>un pont</li></ul>', $body);
    }

    public function testFailedAssessmentShowsScoreWithoutTheCorrection(): void
    {
        $attempt = ['chapter' => 0, 'score' => 0, 'passed' => false, 'expired' => false, 'correct_count' => 0, 'question_count' => 2, 'answers' => [1, 0],
            'results' => [['selected' => 1, 'correct' => false], ['selected' => 0, 'correct' => false]], 'at' => 1];
        $body = $this->render(['chapter' => 0, 'stage' => 4, 'feedback' => 'failed', 'attempts' => [$attempt]], '0.4');
        self::assertStringContainsString('Évaluation non validée', $body);
        self::assertStringContainsString('0 bonne(s) réponse(s) sur 2', $body);
        self::assertStringContainsString('La correction détaillée s’affiche une fois l’évaluation réussie', $body);
        self::assertStringNotContainsString('Réponse attendue', $body);
        self::assertStringNotContainsString('Pourquoi la une', $body);
        self::assertStringNotContainsString('<i class="ko">', $body, 'Pas de détail question par question avant réussite');
        self::assertStringContainsString('Nouvelle tentative', $body);
        self::assertStringContainsString('/action/exam_start', $body);
        self::assertStringContainsString('Consignes <strong>importantes</strong>', $body);
    }

    public function testPassedAssessmentShowsQuestionByQuestionAndContinues(): void
    {
        $attempt = ['chapter' => 0, 'score' => 50, 'passed' => true, 'expired' => false, 'correct_count' => 1, 'question_count' => 2, 'answers' => [0, 0],
            'results' => [['selected' => 0, 'correct' => true], ['selected' => 0, 'correct' => false]], 'at' => 1];
        $body = $this->render(['chapter' => 0, 'stage' => 5, 'feedback' => 'passed', 'attempts' => [$attempt]], '0.4');
        self::assertStringContainsString('Évaluation réussie', $body);
        self::assertStringContainsString('<b>50 %</b>', $body);
        self::assertStringContainsString('Question par question', $body);
        self::assertStringContainsString('<details class="acr-byq" open>', $body);
        self::assertStringContainsString('Réponse attendue :</span> <b>B</b> Bon choix deux', $body);
        self::assertStringContainsString('Pourquoi la deux', $body);
        self::assertStringContainsString('Continuer vers débriefing', $body);
        self::assertStringNotContainsString('Vous relisez', $body);
    }
}
