<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\AcademyController;
use App\Core\Container;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AcademyRepository;
use App\Repositories\TenantRepository;
use App\Services\Academy\AcademyBuiltInCourses;
use App\Services\Academy\AcademyCurriculum;
use App\Services\Academy\AcademyStudio;
use App\Services\Academy\GoogleSlidesImporter;
use App\Services\Platform\FeatureGateService;
use PDO;
use PHPUnit\Framework\TestCase;

/** Studio : brouillons, publication, droits, et import Google Slides avec un téléchargement simulé. */
final class AcademyStudioTest extends AcademyStudioTestCase
{
    public function testUrlValidationAcceptsOnlyGooglePresentationAddresses(): void
    {
        foreach ([
            'https://docs.google.com/presentation/d/' . self::DECK . '/edit',
            'https://docs.google.com/presentation/d/' . self::DECK . '/edit?usp=sharing#slide=id.p',
            'https://docs.google.com/presentation/d/' . self::DECK,
        ] as $url) {
            self::assertSame(self::DECK, GoogleSlidesImporter::deckId($url), $url);
        }
        foreach ([
            'http://docs.google.com/presentation/d/' . self::DECK . '/edit',
            'https://docs.google.com.evil.example/presentation/d/' . self::DECK . '/edit',
            'https://user:pass@docs.google.com/presentation/d/' . self::DECK . '/edit',
            'https://docs.google.com:8443/presentation/d/' . self::DECK . '/edit',
            'https://docs.google.com/document/d/' . self::DECK . '/edit',
            'https://drive.google.com/file/d/' . self::DECK . '/view',
            'https://docs.google.com/presentation/d/short/edit',
            'javascript:alert(1)',
            '',
        ] as $url) {
            self::assertSame(GoogleSlidesImporter::INVALID, GoogleSlidesImporter::deckId($url), $url);
        }
        self::assertSame(GoogleSlidesImporter::PUBLISH_LINK, GoogleSlidesImporter::deckId('https://docs.google.com/presentation/d/e/2PACX-1vRkg3mcWxHu5ZEoeeW7XUOKTMG4yc9X5PHsy050bWHL/pub?start=false'));
        self::assertSame('Baby album', GoogleSlidesImporter::title('attachment; filename="Babyalbum.pdf"; filename*=UTF-8\'\'Baby%20album.pdf'));
        self::assertSame('Google Slides', GoogleSlidesImporter::title(''));
    }

    public function testImporterStoresTheExportPerCommunityAndExplainsFailures(): void
    {
        $importer = Container::get(GoogleSlidesImporter::class);
        $slides = $importer->import(10, 'https://docs.google.com/presentation/d/' . self::DECK . '/edit');
        self::assertSame(['https://docs.google.com/presentation/d/' . self::DECK . '/export/pdf'], $this->fetched);
        self::assertSame(3, $slides['pages']);
        self::assertSame('Briefing radio', $slides['title']['fr']);
        self::assertMatchesRegularExpression(GoogleSlidesImporter::FILE_PATTERN, $slides['file']);
        self::assertNotNull($importer->path(10, $slides['file']));
        self::assertNull($importer->path(11, $slides['file']), 'Une autre communauté ne lit pas ce fichier.');
        self::assertNull($importer->path(10, '../10/' . $slides['file']));

        $cases = [
            [['status' => 200, 'type' => 'text/html', 'body' => '<!doctype html><title>Connexion</title>', 'disposition' => '', 'host' => 'accounts.google.com'], GoogleSlidesImporter::UNAVAILABLE],
            [['status' => 200, 'type' => 'text/html', 'body' => '<!doctype html><title>Connexion</title>', 'disposition' => '', 'host' => 'docs.google.com'], GoogleSlidesImporter::PRIVATE],
            [['status' => 404, 'type' => 'text/html', 'body' => 'nope', 'disposition' => '', 'host' => 'docs.google.com'], GoogleSlidesImporter::PRIVATE],
            [['status' => 500, 'type' => 'text/html', 'body' => '', 'disposition' => '', 'host' => 'docs.google.com'], GoogleSlidesImporter::UNAVAILABLE],
        ];
        foreach ($cases as [$response, $code]) {
            $this->nextResponse = $response;
            try {
                $importer->import(10, 'https://docs.google.com/presentation/d/' . self::DECK . '/edit');
                self::fail('Import attendu en échec : ' . $code);
            } catch (\DomainException $e) {
                self::assertSame($code, $e->getMessage());
            }
        }
        $tooLarge = new GoogleSlidesImporter(static fn (): array => throw new \LengthException('too_large'), $this->storage);
        try {
            $tooLarge->import(10, 'https://docs.google.com/presentation/d/' . self::DECK . '/edit');
            self::fail('Taille non bornée');
        } catch (\DomainException $e) {
            self::assertSame(GoogleSlidesImporter::TOO_LARGE, $e->getMessage());
        }
    }

    public function testStudioImportCreatesAChapterAndReimportUpdatesItInPlace(): void
    {
        $key = $this->newDraft();
        $url = 'https://docs.google.com/presentation/d/' . self::DECK . '/edit?usp=sharing';
        $response = $this->post('studio_import', ['course' => $key, 'step' => '0.0', 'slides_url' => $url, 'slides_target' => 'new', 'draft' => $this->form($this->draft($key))]);
        self::assertSame(303, $response->statusCode());
        self::assertStringContainsString('step=1.0', (string) $response->headerValue('Location'));
        self::assertSame('slides_imported', $_SESSION['academy_notice']);
        $draft = $this->draft($key);
        self::assertCount(2, $draft['chapters']);
        self::assertSame(['fr' => 'Briefing radio', 'en' => 'Briefing radio'], $draft['chapters'][1]['title']);
        $first = $draft['chapters'][1]['resources'][0];
        self::assertSame(['slides', 3, self::DECK], [$first['type'], $first['pages'], $first['deck']]);
        self::assertFalse($draft['published']);

        // Ré-import après modification de la présentation : même ressource, nouveau fichier, rien en double.
        $this->nextResponse = $this->pdfResponse('%PDF-1.4 /Type /Page /Type /Page %%EOF', 'Briefing%20radio%20v2.pdf');
        $this->post('studio_import', ['course' => $key, 'reimport_url' => $first['source'], 'draft' => $this->form($draft)]);
        self::assertSame('slides_updated', $_SESSION['academy_notice']);
        $draft = $this->draft($key);
        self::assertCount(2, $draft['chapters']);
        self::assertCount(1, $draft['chapters'][1]['resources']);
        self::assertSame(2, $draft['chapters'][1]['resources'][0]['pages']);
        self::assertNotSame($first['file'], $draft['chapters'][1]['resources'][0]['file']);

        // Présentation non partagée : message clair, brouillon inchangé.
        $this->nextResponse = ['status' => 200, 'type' => 'text/html', 'body' => '<html>Connexion</html>', 'disposition' => '', 'host' => 'docs.google.com'];
        $this->post('studio_import', ['course' => $key, 'slides_url' => $url, 'slides_target' => '0', 'draft' => $this->form($draft)]);
        self::assertSame('slides_private', $_SESSION['academy_notice']);
        self::assertSame($draft['chapters'], $this->draft($key)['chapters']);
    }

    public function testSlidesFromTheBrowserCannotPointAtUnknownFiles(): void
    {
        $key = $this->newDraft();
        $form = $this->form($this->draft($key));
        $form['chapters'][0]['resources'] = [['file' => str_repeat('b', 32) . '.pdf', 'title' => ['fr' => 'X', 'en' => 'X']]];
        $this->post('studio_save', ['course' => $key, 'draft' => $form]);
        self::assertSame([], $this->draft($key)['chapters'][0]['resources']);
    }

    public function testDraftIsSavedIncompleteAndPublishedOnlyWhenComplete(): void
    {
        $key = $this->newDraft();
        $form = $this->form($this->draft($key));
        $form['title'] = ['fr' => 'Coordination', 'en' => 'Coordination'];
        $this->post('studio_publish', ['course' => $key, 'draft' => $form]);
        self::assertSame('studio_not_ready', $_SESSION['academy_notice']);
        self::assertFalse($this->draft($key)['published']);
        self::assertSame('Coordination', $this->draft($key)['title']['fr']);

        $demo = AcademyCurriculum::demo();
        $complete = $this->form($demo);
        $complete['chapters'] = array_map(static fn (array $chapter): array => $chapter + ['resources' => []], $demo['chapters']);
        $this->post('studio_publish', ['course' => $key, 'draft' => $complete]);
        self::assertSame('studio_published', $_SESSION['academy_notice']);
        $published = $this->draft($key);
        self::assertTrue($published['published']);
        AcademyCurriculum::validate($published);

        // Un cours publié ne se modifie plus : nouvelle version en brouillon, qui remplace l'ancienne à la publication.
        self::assertSame(409, $this->post('studio_save', ['course' => $key, 'draft' => $complete])->statusCode());
        $response = $this->post('studio_revise', ['course' => $key]);
        preg_match('#course=(course-[a-f0-9]{16})#', (string) $response->headerValue('Location'), $m);
        $revision = $this->draft($m[1]);
        self::assertSame($key, $revision['replaces']);
        $this->post('studio_publish', ['course' => $m[1], 'draft' => $complete]);
        self::assertTrue($this->draft($m[1])['published']);
        self::assertFalse($this->draft($key)['published']);
    }

    public function testStudioRightsAreUnchanged(): void
    {
        $key = $this->newDraft();
        Gate::reset();
        foreach (['studio_new', 'studio_save', 'studio_import', 'studio_publish', 'studio_revise'] as $action) {
            self::assertSame(403, $this->post($action, ['course' => $key])->statusCode(), $action);
        }
        $_SERVER['REQUEST_METHOD'] = 'GET';
        self::assertSame(403, (new AcademyController())->space(new Request(), ['space' => 'studio'])->statusCode());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM academy_courses')->fetchColumn());
        $_POST = ['_csrf_token' => 'bad', 'course' => $key];
        Gate::getInstance()->setPermissions(['training.create']);
        self::assertSame(419, (new AcademyController())->action(new Request(), ['action' => 'studio_save'])->statusCode());
    }

    public function testStudioRendersStructureStepsAndImportInBothLanguages(): void
    {
        $key = $this->newDraft();
        $this->post('studio_import', ['course' => $key, 'slides_url' => 'https://docs.google.com/presentation/d/' . self::DECK . '/edit', 'slides_target' => '0', 'draft' => $this->form($this->draft($key))]);
        foreach (['fr' => ['Importer depuis Google Slides', 'Mettre à jour depuis Google Slides', 'Exercice guidé'], 'en' => ['Import from Google Slides', 'Update from Google Slides', 'Guided exercise']] as $language => $texts) {
            $GLOBALS['__app_locale'] = $language;
            $_GET = ['course' => $key, 'step' => '0.2'];
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $response = (new AcademyController())->space(new Request(), ['space' => 'studio']);
            self::assertSame(200, $response->statusCode());
            $body = $response->body();
            foreach ($texts as $text) {
                self::assertStringContainsString($text, $body);
            }
            self::assertStringContainsString('data-step-panel="0.2" aria-labelledby', $body, 'Étape demandée visible');
            self::assertStringContainsString('data-step-panel="0.0" hidden', $body);
            self::assertSame(7, preg_match_all('/<a class="l[ "][^>]*data-step-link="0.[0-6]"/', $body), 'Sept étapes par chapitre dans la structure');
            self::assertStringContainsString('name="draft[chapters][0][resources][0][file]"', $body);
            self::assertStringContainsString('formaction="/academy/action/studio_publish" disabled', $body, 'Brouillon incomplet : publication bloquée');
            self::assertStringNotContainsString('content_json', $body);
        }
        // Liste des cours : modules intégrés en lecture seule, copie proposée.
        $_GET = [];
        $body = (new AcademyController())->space(new Request(), ['space' => 'studio'])->body();
        self::assertStringContainsString(AcademyBuiltInCourses::JTAC_KEY, $body);
        self::assertStringContainsString('studio?course=' . $key, $body);
    }

    public function testMediaIsServedOnlyInsideTheCommunity(): void
    {
        $slides = Container::get(GoogleSlidesImporter::class)->import(10, 'https://docs.google.com/presentation/d/' . self::DECK . '/edit');
        $response = (new AcademyController())->media(new Request(), ['file' => $slides['file']]);
        self::assertSame(200, $response->statusCode());
        self::assertSame('application/pdf', $response->headerValue('Content-Type'));
        self::assertTrue($response->hasBodyStream());
        $_SESSION['tenant_id'] = 11;
        self::assertSame(404, (new AcademyController())->media(new Request(), ['file' => $slides['file']])->statusCode());
        self::assertSame(404, (new AcademyController())->media(new Request(), ['file' => '../../.env'])->statusCode());
    }

    public function testFormNormalisationDropsEmptyOptionalStagesAndKeepsStructureOperations(): void
    {
        $course = AcademyStudio::fromForm(['title' => ['fr' => ' Titre ', 'en' => 'Title'], 'cover' => 'evil', 'chapters' => [
            ['title' => ['fr' => 'A', 'en' => 'A'], 'guided' => ['brief' => ['fr' => '', 'en' => '']], 'assessment' => ['minutes' => '999', 'pass_percent' => '80', 'questions' => [['question' => ['fr' => 'Q', 'en' => 'Q']]]]],
            ['title' => ['fr' => 'B', 'en' => 'B'], 'debrief' => ['prompt' => ['fr' => 'P', 'en' => 'P'], 'criteria' => [['fr' => '', 'en' => ''], ['fr' => 'C', 'en' => 'C']]]],
        ]], []);
        self::assertSame('Titre', $course['title']['fr']);
        self::assertSame('night', $course['cover']);
        self::assertArrayNotHasKey('guided', $course['chapters'][0]);
        self::assertSame(7200, $course['chapters'][0]['assessment']['seconds']);
        self::assertCount(1, $course['chapters'][1]['debrief']['criteria']);
        $issues = array_column(AcademyStudio::issues($course), 'issue');
        self::assertContains('issue_course_summary', $issues);
        self::assertContains('issue_assessment', $issues);
        $moved = AcademyStudio::applyOperation($course, 'down:0');
        self::assertSame('B', $moved['chapters'][0]['title']['fr']);
        self::assertCount(1, AcademyStudio::applyOperation($course, 'remove_chapter:1')['chapters']);
        self::assertCount(3, AcademyStudio::applyOperation($course, 'add_chapter')['chapters']);
        self::assertCount(1, AcademyStudio::applyOperation(['chapters' => [AcademyStudio::blankChapter()]], 'remove_chapter:0')['chapters'], 'Un cours garde au moins un chapitre');
    }

    public function testNewCourseDialogDuplicateAndHomeCards(): void
    {
        $response = $this->post('studio_new', ['course' => 'academy', 'new_title' => 'Appui feu indirect', 'new_summary' => 'Bases du réglage', 'new_cover' => 'fog']);
        preg_match('#course=(course-[a-f0-9]{16})#', (string) $response->headerValue('Location'), $m);
        $draft = $this->draft($m[1]);
        self::assertSame(['fr' => 'Appui feu indirect', 'en' => ''], $draft['title']);
        self::assertSame('fog', $draft['cover']);
        $this->post('studio_duplicate', ['course' => $m[1]]);
        self::assertSame('studio_copied', $_SESSION['academy_notice']);
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $body = (new AcademyController())->space(new Request(), ['space' => 'studio'])->body();
        self::assertStringContainsString('Appui feu indirect (copie)', $body);
        self::assertSame(2, substr_count($body, 'data-course-card data-status="draft"'));
        self::assertStringContainsString('cover-fog.webp', $body);
        self::assertStringContainsString('class="ready"', $body, 'Un repère de préparation par chapitre');
        self::assertStringContainsString('id="sth-new"', $body);
    }

    public function testEditorShowsOneLanguageAtATimeWithFrenchHintsAndTranslationProgress(): void
    {
        $key = $this->newDraft();
        $form = $this->form($this->draft($key));
        $form['chapters'][0]['theory'] = ['fr' => 'Observer avant de transmettre.', 'en' => ''];
        $form['chapters'][0]['validation'] = ['brief' => ['fr' => 'Relire', 'en' => 'Review'], 'criteria' => [['fr' => 'Critère un', 'en' => 'One'], ['fr' => 'Critère deux', 'en' => 'Two']]];
        $this->post('studio_save', ['course' => $key, 'draft' => $form]);
        $this->post('studio_save', ['course' => $key, 'draft' => $this->form($this->draft($key)), 'op' => 'remove_criterion:0:validation:0']);
        self::assertSame([['fr' => 'Critère deux', 'en' => 'Two']], $this->draft($key)['chapters'][0]['validation']['criteria']);
        self::assertSame([3, 2], \App\Services\Academy\AcademyStudio::translation($this->draft($key)));
        $_GET = ['course' => $key, 'edit' => 'en'];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $body = (new AcademyController())->space(new Request(), ['space' => 'studio'])->body();
        self::assertStringContainsString('data-lang="en"', $body);
        self::assertStringContainsString('placeholder="FR : Observer avant de transmettre."', $body);
        self::assertStringContainsString('is-missing', $body);
        self::assertStringContainsString('EN<small>66 %</small>', $body);
        self::assertStringContainsString('name="edit_lang" value="en"', $body);
        $response = $this->post('studio_save', ['course' => $key, 'draft' => $this->form($this->draft($key)), 'edit_lang' => 'en', 'step' => '0.6']);
        self::assertStringEndsWith('step=0.6&edit=en', (string) $response->headerValue('Location'));
    }
}
