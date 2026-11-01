<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Response;
use App\Services\Academy\AcademyCurriculum;
use PHPUnit\Framework\TestCase;

/** Salle de cours (maquette Classroom) branchée sur l'état réel du stagiaire. */
final class AcademyClassroomViewTest extends TestCase
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
        $course['cover'] = 'fog';
        $course['chapters'][0]['resources'][] = ['type' => 'slides', 'title' => ['fr' => 'Briefing radio', 'en' => 'Radio briefing'], 'file' => str_repeat('c', 32) . '.pdf', 'pages' => 12, 'deck' => 'x', 'source' => 'https://docs.google.com/presentation/d/x/edit'];
        $course['chapters'][0]['validation'] = ['brief' => ['fr' => 'Relire', 'en' => 'Review'], 'criteria' => [['fr' => 'Critère vérifiable', 'en' => 'Checkable criterion']]];
        return $course;
    }

    private function render(?array $state, bool $staff = false, array $extra = []): string
    {
        $course = $this->course();
        $states = $state ? [$course['key'] => $state + ['snapshot' => $course]] : [];
        $states['__notes'] = ['text' => '<b>mes notes</b>'];
        return Response::view('academy.space', $extra + [
            'space' => 'classroom', 'staff' => $staff, 'states' => $states, 'courses' => [$course], 'selected' => $course,
            'state' => $state ? $state + ['snapshot' => $course] : null, 'learners' => [], 'notice' => '',
        ])->body();
    }

    public function testTheoryStepShowsImportedSlidesNotesResourcesAndCriteria(): void
    {
        $body = $this->render(['chapter' => 0, 'stage' => 0]);
        self::assertStringContainsString('data-academy-deck', $body);
        self::assertStringContainsString('/academy/media/' . str_repeat('c', 32) . '.pdf', $body);
        self::assertStringContainsString('12 diapositives', $body);
        self::assertStringContainsString('assets/vendor/pdfjs/pdf.js', $body);
        self::assertStringContainsString('name="return" value="classroom"', $body);
        self::assertStringContainsString('&lt;b&gt;mes notes&lt;/b&gt;', $body);
        self::assertStringNotContainsString('<b>mes notes</b>', $body);
        self::assertStringContainsString('Critère vérifiable', $body);
        self::assertStringContainsString('/action/advance', $body);
        self::assertStringContainsString('assets/images/academy/cover-fog.webp', $body);
        self::assertStringContainsString('aria-current="step"', $body);
        // Étapes non atteintes : verrouillées, sans lien.
        self::assertStringNotContainsString('step=0.1', $body);
        self::assertStringNotContainsString('Academy Studio', $body, 'Le lien Studio est réservé à l’encadrement');
        self::assertStringContainsString('Academy Studio', $this->render(['chapter' => 0, 'stage' => 0], true));
    }

    public function testRunningExamLocksNavigationAndHidesNotesAndMaterials(): void
    {
        $body = $this->render(['chapter' => 0, 'stage' => 4, 'exam' => ['deadline' => time() + 300, 'token' => 'tok']], false, ['viewStep' => '0.0']);
        self::assertStringContainsString('name="token" value="tok"', $body);
        self::assertStringNotContainsString('step=0.0', $body);
        self::assertStringNotContainsString('data-tabs', $body);
        self::assertStringNotContainsString('data-academy-deck', $body);
    }

    public function testPendingValidationAndPreviewStates(): void
    {
        $body = $this->render(['chapter' => 0, 'stage' => 6, 'debriefs' => [0 => 'Mon débriefing transmis']]);
        self::assertStringContainsString('Validation par un instructeur', $body);
        self::assertStringContainsString('Mon débriefing transmis', $body);
        $preview = $this->render(null, true, ['preview' => true, 'academyStorageReady' => false]);
        self::assertStringContainsString('Aperçu', $preview);
        self::assertStringNotContainsString('/action/enroll', $preview);
        $GLOBALS['__app_locale'] = 'en';
        self::assertStringContainsString('Validated by an instructor', $this->render(['chapter' => 0, 'stage' => 6]));
    }
}
