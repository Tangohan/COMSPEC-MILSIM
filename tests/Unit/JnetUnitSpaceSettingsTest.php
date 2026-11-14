<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Jnet\JnetUnitSpaceSettings as S;
use PHPUnit\Framework\TestCase;

/** JNET : espace dédié de chaque unité (thème, couleur, devise, images, écran d'entrée) et sa personnalisation. */
final class JnetUnitSpaceSettingsTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';

    public function testFormValuesAreCleaned(): void
    {
        $v = S::normalize([
            'theme' => 'inconnu',
            'accent_custom' => '1',
            'accent_color' => '#ABC',
            'tagline' => "  <b>Toujours</b>\n prêts  ",
            'welcome_text' => "Ligne 1\r\n\r\n\r\n\r\nLigne 2<script>x</script>",
            'access_label' => str_repeat('A', 80),
            'entry_screen' => '',
        ]);
        self::assertSame(S::DEFAULT_THEME, $v['theme']);
        self::assertSame('#aabbcc', $v['accent_color']);
        self::assertSame('Toujours prêts', $v['tagline']);
        self::assertSame("Ligne 1\n\nLigne 2x", $v['welcome_text']);
        self::assertSame(S::ACCESS_LABEL_MAX, mb_strlen($v['access_label']));
        self::assertFalse($v['entry_screen']);
        // Couleur non choisie : rien n'est gardé, l'ORBAT ou le thème décide.
        self::assertSame('', S::normalize(['accent_custom' => '', 'accent_color' => '#123456'])['accent_color']);
        self::assertSame('', S::color('red; background:url(x)'));
    }

    public function testLookFallsBackToOrbatThenTheme(): void
    {
        $space = ['label' => '1re Compagnie', 'accent' => '#ff8800', 'motto' => 'Semper', 'badge' => 'https://x/badge.png'];
        $look = S::look(S::defaults(), $space);
        self::assertSame('#ff8800', $look['accent']);
        self::assertSame('Semper', $look['tagline']);
        self::assertSame('https://x/badge.png', $look['emblem']);
        self::assertSame(S::DEFAULT_ACCESS_LABEL, $look['access_label']);
        self::assertSame('1R', $look['monogram']);
        self::assertTrue($look['entry_screen']);

        $look = S::look(array_merge(S::defaults(), ['theme' => 'desert', 'accent_color' => '#112233', 'tagline' => 'Au-delà']), $space);
        self::assertSame('#112233', $look['accent']);
        self::assertSame('Au-delà', $look['tagline']);
        self::assertSame(S::THEMES['desert']['bg'], $look['bg']);

        self::assertSame(S::THEMES[S::DEFAULT_THEME]['accent'], S::look(S::defaults(), ['label' => 'X'])['accent']);
        self::assertStringContainsString('--jn-unit:#ff8800;', S::cssVars(S::look(S::defaults(), $space)));
    }

    public function testOnlyThisCommunityImagesAreServedOrDeleted(): void
    {
        self::assertTrue(S::isOwnImage('uploads/jnet-espaces/7/insigne-ab12.png', 7));
        self::assertFalse(S::isOwnImage('uploads/jnet-espaces/8/insigne-ab12.png', 7));
        self::assertFalse(S::isOwnImage('uploads/jnet-espaces/7/../../.env', 7));
        self::assertFalse(S::isOwnImage('uploads/login-accueil/7/a.png', 7));
        self::assertNull(S::publicUrl('../secret.png'));
        self::assertNull(S::publicUrl('uploads/autre/7/a.png'));
    }

    public function testEveryThemeHasValidColours(): void
    {
        foreach (S::THEMES as $key => $t) {
            foreach (['bg', 'panel', 'ink', 'accent'] as $k) {
                self::assertSame($t[$k], S::color($t[$k]), $key . '.' . $k);
            }
        }
    }

    public function testRoutesAndRedirections(): void
    {
        $routes = (string) file_get_contents(self::ROOT . 'routes/web.php');
        foreach (["get('/jnet/u/{id}/entree'", "get('/jnet/u/{id}/personnaliser'", "post('/jnet/u/{id}/personnaliser'"] as $r) {
            self::assertStringContainsString($r, $routes);
        }
        $ctrl = (string) file_get_contents(self::ROOT . 'app/Controllers/Web/JnetPortalController.php');
        // Un espace passe par son écran d'entrée une fois par session ; ?direct=1 (bouton « Entrer ») l'évite.
        self::assertStringContainsString("return Response::redirect(url('jnet/u/' . \$unitId . '/entree'));", $ctrl);
        self::assertStringContainsString("'entryNext' => url('jnet/u/' . \$unitId . '?direct=1')", $ctrl);
        // Un membre arrive sur l'extranet « Une unité. Un espace. » ; l'espace commun reste à ?commun=1.
        self::assertStringContainsString('return $this->hubResponse($ctx);', $ctrl);
        // Personnaliser : jeton CSRF et droit vérifiés à l'enregistrement aussi.
        self::assertSame(2, substr_count($ctrl, 'Seuls le chef de l’unité et l’encadrement de l’organisation personnalisent cet espace.'));
        self::assertStringContainsString('JnetUnitSpaceSettings::deleteImage($old, $tenantId)', $ctrl);
    }

    public function testEntryScreenWorksWithoutJavascript(): void
    {
        $view = (string) file_get_contents(self::ROOT . 'views/jnet/entry.php');
        self::assertStringContainsString('<meta http-equiv="refresh"', $view);
        self::assertStringContainsString('jx-enter"', $view);
        self::assertStringNotContainsString('<script', $view);
        $css = (string) file_get_contents(self::ROOT . 'public/assets/css/jnet_entry.css');
        self::assertStringContainsString('prefers-reduced-motion: reduce', $css);
    }

    /** L'espace d'une unité a sa propre interface : page autonome au thème de l'unité, hors de la coque du back-office. */
    public function testUnitSpaceHasItsOwnInterface(): void
    {
        $ctrl = (string) file_get_contents(self::ROOT . 'app/Controllers/Web/JnetPortalController.php');
        self::assertStringContainsString("Response::view('jnet.unit_space'", $ctrl);
        self::assertStringContainsString("private const UNIT_VIEWS = ['accueil', 'echanges', 'effectif', 'taches', 'pages', 'page', 'photos', 'documents', 'operations', 'sous-unites', 'partager'];", $ctrl);
        $view = (string) file_get_contents(self::ROOT . 'views/jnet/unit_space.php');
        self::assertStringContainsString('<!DOCTYPE html>', $view);
        self::assertStringNotContainsString('layout.main', $view);
        self::assertStringNotContainsString('back-office-', $view);
        foreach (['Ordres et échanges', 'Effectif', 'Opérations', 'Sous-unités', 'Changer d’espace', 'Personnaliser'] as $label) {
            self::assertStringContainsString($label, $view, $label);
        }
        // Le thème de l'unité fixe tous les jetons, y compris ceux des composants d'échange repris.
        $css = (string) file_get_contents(self::ROOT . 'public/assets/css/jnet_unit_shell.css');
        self::assertStringContainsString('--ath-surface: var(--us-panel);', $css);
        self::assertStringContainsString('--ath-accent: var(--jn-unit);', $css);
    }

    public function testMigrationStepIsRegistered(): void
    {
        $registry = (string) file_get_contents(self::ROOT . 'database/steps/registry.php');
        self::assertStringContainsString("'file' => '4500_jnet_unit_spaces.php'", $registry);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS jnet_unit_spaces', (string) file_get_contents(self::ROOT . 'migrations/2026_11_14_030_jnet_unit_spaces.sql'));
    }
}
