<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\AcademyController;
use App\Core\Gate;
use App\Core\Request;

/**
 * Studio : éditeur de texte riche (barre d'outils, aperçu rendu par le serveur, aide, fenêtre image) sans régression
 * de l'enregistrement ; aperçu et envoi d'image soumis aux mêmes droits que l'édition.
 */
final class AcademyStudioRichEditorTest extends AcademyStudioTestCase
{
    public function testEditorShowsToolbarLivePreviewAndHelpForRichFieldsInBothLanguages(): void
    {
        $key = $this->newDraft();
        $form = $this->form($this->draft($key));
        $form['chapters'][0]['theory'] = ['fr' => "## Section\n:::astuce\nTexte **gras**\n:::\n<script>x</script>", 'en' => "## Section EN"];
        $response = $this->post('studio_save', ['course' => $key, 'draft' => $form]);
        self::assertSame(303, $response->statusCode());
        self::assertSame("## Section\n:::astuce\nTexte **gras**\n:::\n<script>x</script>", $this->draft($key)['chapters'][0]['theory']['fr'], 'Syntaxe enregistrée telle quelle');
        foreach (['fr' => ['Mise en forme du texte', 'Aperçu stagiaire', 'Aide : syntaxe de mise en forme', 'Insérer une image', 'Médiathèque de la communauté'],
            'en' => ['Text formatting', 'Learner preview', 'Help: formatting syntax', 'Insert an image', 'Community media library']] as $language => $texts) {
            $GLOBALS['__app_locale'] = $language;
            $_GET = ['course' => $key, 'step' => '0.0'];
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $body = (new AcademyController())->space(new Request(), ['space' => 'studio'])->body();
            foreach ($texts as $text) {
                self::assertStringContainsString($text, $body, $language . ' : ' . $text);
            }
            foreach (['bold', 'italic', 'heading', 'list', 'numbered', 'quote', 'rule', 'radio', 'image', 'link', 'table', 'steps', 'log'] as $command) {
                self::assertStringContainsString('data-rte-cmd="' . $command . '"', $body, $command);
            }
            self::assertStringContainsString('data-rte-callout', $body);
            self::assertStringContainsString(':::' . ($language === 'en' ? 'tip' : 'astuce'), $body, 'Aide : mot-clé de la langue');
            // Aperçu initial : même rendu que la salle de cours, texte saisi échappé.
            self::assertStringContainsString('<h2 class="rt-h">Section</h2>', $body);
            self::assertStringContainsString('Texte <strong>gras</strong>', $body);
            self::assertStringContainsString('&lt;script&gt;x&lt;/script&gt;', $body);
            self::assertStringNotContainsString('<script>x</script>', $body);
            self::assertStringContainsString('data-preview-url="/academy/action/studio_preview"', $body);
            self::assertStringContainsString('data-image-url="/academy/action/studio_image"', $body);
            self::assertStringContainsString('assets/js/academy-richtext.js', $body);
            self::assertStringContainsString('name="draft[chapters][0][theory][fr]"', $body);
        }
    }

    public function testPreviewEndpointRendersLikeTheClassroomAndRespectsRights(): void
    {
        $key = $this->newDraft();
        $response = $this->post('studio_preview', ['course' => $key, 'text' => ":::transmission\nBastion : <b>Prêt</b>\n:::\n[x](javascript:alert(1))"]);
        self::assertSame(200, $response->statusCode());
        $json = json_decode($response->body(), true);
        self::assertIsArray($json);
        self::assertStringContainsString('<b class="rt-tx__who">Bastion</b><span class="rt-tx__msg">&lt;b&gt;Prêt&lt;/b&gt;</span>', $json['html']);
        self::assertStringNotContainsString('javascript:', $json['html']);
        self::assertSame(422, $this->post('studio_preview', ['course' => $key, 'text' => str_repeat('a', 20001)])->statusCode());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM academy_courses')->fetchColumn(), 'Rien n’est enregistré');
        Gate::reset();
        foreach (['studio_preview', 'studio_image'] as $action) {
            self::assertSame(403, $this->post($action, ['course' => $key, 'text' => 'x'])->statusCode(), $action . ' : réservé à l’encadrement et aux éditeurs invités');
        }
    }

    public function testImageUploadRefusesMissingOrInvalidFiles(): void
    {
        $key = $this->newDraft();
        $_FILES = [];
        $response = $this->post('studio_image', ['course' => $key]);
        self::assertSame(422, $response->statusCode());
        self::assertSame(['error' => 'rte_upload_failed'], json_decode($response->body(), true));
        $_FILES = ['image' => ['name' => 'x.php', 'type' => 'image/png', 'tmp_name' => __FILE__, 'error' => UPLOAD_ERR_OK, 'size' => 10]];
        self::assertSame(422, $this->post('studio_image', ['course' => $key])->statusCode(), 'Fichier non envoyé par le navigateur');
        $_FILES = [];
    }
}
