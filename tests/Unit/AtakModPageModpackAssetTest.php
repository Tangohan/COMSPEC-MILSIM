<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Page membre /atak/mod : encadré « Télécharger le pack » vers /modpacks, aucune version écrite en dur,
 * plus de lien de téléchargement périmé (abonnement Workshop du mod, archive interne).
 */
final class AtakModPageModpackAssetTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private function read(string $path): string
    {
        return (string) file_get_contents($this->root() . '/' . $path);
    }

    public function testPackBoxPointsToTheModpacksPage(): void
    {
        $view = $this->read('views/atak/mod.php');
        self::assertStringContainsString('id="telecharger-pack"', $view);
        self::assertStringContainsString('Télécharger le pack', $view);
        self::assertStringContainsString("\$urlPack = url('modpacks');", $view);
        self::assertStringContainsString("\$urlTrust = \$urlPack . '#securite';", $view);
        self::assertStringContainsString("\$urlInstall = \$urlPack . '#installation';", $view);
        self::assertStringContainsString('modpack.flag_q', $view);
        self::assertStringContainsString('SHA-256', $view);
        self::assertStringContainsString('public-modpack.css', $view);
        self::assertStringContainsString('mp-box', $view);
    }

    public function testModpackAnchorsExistOnThePublicPage(): void
    {
        $public = $this->read('views/site/public_modpack.php');
        self::assertStringContainsString('id="securite"', $public);
        self::assertStringContainsString('id="installation"', $public);
        self::assertStringContainsString('id="mod-<?=', $public);
    }

    public function testNoVersionIsHardCodedInTheView(): void
    {
        $view = $this->read('views/atak/mod.php');
        self::assertSame(0, preg_match('/\b\d+\.\d+\.\d+\b/', $view), 'Aucun numéro de version dans la vue.');
        self::assertStringNotContainsString('Washington', $view);
        self::assertStringContainsString('$modVersion', $view);
        self::assertStringContainsString('$modLinkVersion', $view);
        $ctl = $this->read('app/Controllers/Web/AtakController.php');
        self::assertStringContainsString('AtakReleaseInfo::latest()', $ctl);
        self::assertStringContainsString("'modPack' => \$modPack", $ctl);
    }

    public function testViewKeepsOneH1AndNoForbiddenClassNames(): void
    {
        $view = $this->read('views/atak/mod.php');
        self::assertSame(1, substr_count($view, '<h1'));
        $css = $this->read('public/assets/css/atak-mod-download.css');
        foreach ([$view, $css] as $text) {
            self::assertSame(0, preg_match('/kicker|eyebrow|overline|surtitre/i', $text));
        }
    }

    public function testStaleDownloadLinksNoLongerPointToTheWorkshop(): void
    {
        $view = $this->read('views/atak/mod.php');
        self::assertStringNotContainsString('SteamWorkshop::clientUrl($main', $view);
        self::assertStringNotContainsString('ATAK_NATIVE_ID', $view);
        self::assertStringContainsString("'/modpacks'", $this->read('app/Controllers/Api/AtakVersionController.php'));
        self::assertStringContainsString("url('modpacks')", $this->read('app/Controllers/Web/AtakFirstLinkController.php'));
        self::assertStringNotContainsString('SteamWorkshop::', $this->read('app/Controllers/Web/AtakFirstLinkController.php'));
    }

    public function testEnglishTranslationsExist(): void
    {
        $en = $this->read('lang/en/site_auto.php');
        foreach (['Télécharger le pack', 'Contenu du pack', 'Dépendances à abonner sur Steam', 'Mon antivirus signale le pack'] as $fr) {
            self::assertStringContainsString("'" . $fr . "' =>", $en);
        }
    }
}
