<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Les trois assistants de création du portail SSE (toile, dossier, objet) : routes et champs d'envoi inchangés, cinq étapes,
 * un seul h1, aucun surtitre, validation et brouillon pilotés par le script commun, textes traduits.
 */
final class SseCreationWizardViewsTest extends TestCase
{
    private const VIEWS = [
        'mesh_form' => ['action' => "atak/sse/toiles'", 'fields' => ['title', 'summary', 'case_id', 'mesh_type', 'start']],
        'case_form' => ['action' => "atak/sse/dossiers'", 'fields' => ['title', 'summary', 'parent_id', 'unlock_code', 'case_type', 'theme', 'urgency']],
        'object_create' => ['action' => "atak/sse/objets/creer'", 'fields' => ['label', 'detail', 'kind', 'mesh_id', 'case_id', 'image', 'meta[etat]']],
    ];

    private static function read(string $rel): string
    {
        $path = dirname(__DIR__, 2) . '/' . $rel;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function testEachViewKeepsItsRouteAndFields(): void
    {
        foreach (self::VIEWS as $view => $spec) {
            $src = self::read('views/atak/sse/' . $view . '.php');
            self::assertStringContainsString($spec['action'], $src, $view);
            self::assertStringContainsString('Csrf::field()', $src, $view);
            self::assertStringContainsString('data-sse-wizard', $src, $view);
            foreach ($spec['fields'] as $field) {
                self::assertMatchesRegularExpression('/(name="' . preg_quote($field, '/') . '"|cards\(\'' . preg_quote($field, '/') . '\')/', $src, $view . ' : ' . $field);
            }
            self::assertSame(5, substr_count($src, 'data-wz-step '), $view . ' : cinq étapes');
            self::assertSame(1, preg_match_all('/<h1[ >]/', $src), $view . ' : un seul h1');
            self::assertStringContainsString('sse-wizard.js', $src, $view);
        }
    }

    public function testClassificationCardsComeFromTheSharedPartial(): void
    {
        self::assertStringContainsString("cards('classification'", self::read('views/atak/sse/partials/wizard_classification.php'));
        foreach (array_keys(self::VIEWS) as $view) {
            self::assertStringContainsString('wizard_classification.php', self::read('views/atak/sse/' . $view . '.php'), $view);
        }
    }

    public function testNoKickerEyebrowOrOverlineAnywhere(): void
    {
        $files = ['views/atak/sse/mesh_form.php', 'views/atak/sse/case_form.php', 'views/atak/sse/object_create.php', 'app/Support/SseWizardUi.php',
            'public/assets/js/sse-wizard.js', 'public/assets/css/sse_wizard.css', 'views/atak/sse/partials/wizard_nav.php', 'views/atak/sse/partials/wizard_banners.php',
            'views/atak/sse/partials/wizard_classification.php'];
        foreach ($files as $rel) {
            self::assertDoesNotMatchRegularExpression('/kicker|eyebrow|overline|surtitre/i', self::read($rel), $rel);
        }
        self::assertStringNotContainsString('sse-obj-create', self::read('public/assets/css/sse_portal.css'));
    }

    public function testScriptKeepsTheDraftSafe(): void
    {
        $js = self::read('public/assets/js/sse-wizard.js');
        self::assertStringContainsString('localStorage', $js);
        self::assertGreaterThanOrEqual(2, substr_count($js, 'try {'), 'le stockage est toujours protégé par try/catch');
        self::assertStringContainsString('data-wz-nodraft', $js);
        self::assertStringContainsString('password', $js);
        self::assertStringContainsString('focusField', $js);
        $code = (string) preg_replace(['~/\*.*?\*/~s', '~//[^
]*~'], '', $js);
        self::assertDoesNotMatchRegularExpression('/[éèàêçù]/u', $code, 'aucun texte français dans le script : tout vient du HTML');
        self::assertStringContainsString('data-wz-nodraft', self::read('views/atak/sse/case_form.php'));
    }

    public function testControllerCapsClassificationAndChecksScope(): void
    {
        $src = self::read('app/Controllers/Web/SsePortalController.php');
        self::assertSame(3, substr_count($src, '$this->creationClassification('), 'toile, dossier, objet');
        self::assertStringContainsString('attachableMeshes()', $src);
        self::assertStringContainsString("'is_folder' => \$isFolder", $src);
        self::assertStringContainsString('requireCase($parentId)', $src);
    }

    public function testTranslationsAreAppendedAtTheEnd(): void
    {
        $en = self::read('lang/en/site_auto.php');
        foreach (['Repartir de zéro', 'Type et objectif', 'Brouillon enregistré dans ce navigateur', 'Au-dessus de votre habilitation'] as $fr) {
            self::assertStringContainsString("'" . $fr . "' =>", $en, $fr);
        }
    }
}
