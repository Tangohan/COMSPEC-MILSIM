<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\JnetUi;
use PHPUnit\Framework\TestCase;

/** JNET : habillage « Extranet » commun au hub, à l'entrée, à l'espace d'unité et aux autres pages. */
final class JnetAthenaUiTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';

    private function read(string $path): string
    {
        return (string) file_get_contents(self::ROOT . $path);
    }

    public function testEverySurfaceSharesTheSameStylesheet(): void
    {
        $css = $this->read('public/assets/css/jnet_athena.css');
        foreach (['--ja-void', '.ja-hd', '.ja-tab', '.ja-crest', '.ja-pill', '.ja-row', '.ja-stat', '.ja-b1', '.ja-disp'] as $needle) {
            self::assertStringContainsString($needle, $css, $needle);
        }
        foreach (['hub', 'entry', 'unit_space', '_page'] as $view) {
            self::assertStringContainsString('assets/css/jnet_athena.css', $this->read('views/jnet/' . $view . '.php'), $view);
            self::assertStringContainsString('class="ja ', $this->read('views/jnet/' . $view . '.php'), $view);
        }
    }

    public function testOtherPagesLeaveTheBackOfficeTheme(): void
    {
        $ctl = $this->read('app/Controllers/Web/JnetPortalController.php');
        self::assertStringContainsString("Response::view('jnet._page'", $ctl);
        self::assertStringNotContainsString("Response::view('layout.main'", $ctl);
        self::assertStringNotContainsString('jnet_bo_embed.css', $ctl);
        self::assertStringContainsString('jnet_spaces.css', $ctl);

        $page = $this->read('views/jnet/_page.php');
        self::assertStringContainsString('<!DOCTYPE html>', $page);
        self::assertStringNotContainsString('layout.main', $page);
        self::assertStringNotContainsString('back-office-', $page);
        self::assertStringContainsString('_bo_content.php', $page);
        foreach (['Espace commun', 'Personnel', 'Opérations', 'Renseignement', 'Cibles', 'Exploitation', 'Bibliothèque', 'Messagerie', 'Système'] as $tab) {
            self::assertStringContainsString($tab, $page, $tab);
        }
        // Les composants repris prennent les couleurs du gabarit par leurs jetons.
        $css = $this->read('public/assets/css/jnet_pages.css');
        self::assertStringContainsString('--jnet-panel: var(--ja-c1);', $css);
        self::assertStringContainsString('--ath-surface: var(--ja-c1);', $css);
    }

    public function testNoEyebrowNoPopupInTheNewInterface(): void
    {
        foreach (['hub', 'entry', 'unit_space', '_page'] as $view) {
            $src = $this->read('views/jnet/' . $view . '.php');
            self::assertStringNotContainsString('toast', strtolower($src), $view);
            self::assertStringNotContainsString('alert(', $src, $view);
            self::assertStringNotContainsString('class="kk"', $src, $view);
            self::assertStringNotContainsString('hb-kk', $src, $view);
        }
        // L'information qui précédait un titre passe dessous.
        foreach (['unit', 'personnel_show', 'target_show'] as $view) {
            $src = $this->read('views/jnet/' . $view . '.php');
            $h1 = strpos($src, '<h1');
            $kicker = strpos($src, 'jnet-kicker');
            self::assertNotFalse($h1, $view);
            self::assertNotFalse($kicker, $view);
            self::assertLessThan($kicker, $h1, $view . ' : le titre vient avant le libellé');
        }
        self::assertStringNotContainsString('jn-chip">Échelon organisation', $this->read('views/jnet/home.php'));
    }

    public function testEntryScreenIsTheDesignSequence(): void
    {
        $view = $this->read('views/jnet/entry.php');
        foreach (['Identité', 'Affectation à l’unité', 'Droit d’accès', 'Synchronisation de l’espace', 'Accès autorisé'] as $line) {
            self::assertStringContainsString($line, $view, $line);
        }
        self::assertStringNotContainsString('<script', $view);
        $ctl = $this->read('app/Controllers/Web/JnetPortalController.php');
        self::assertStringContainsString("'entryRight' => \$right['label']", $ctl);
        // Le droit affiché est celui du registre : même calcul.
        self::assertStringContainsString('JnetMemberHub::rightFor($tree, $unitId', $ctl);
    }

    public function testUnitSpaceHasNumberedTabsHeroAndStats(): void
    {
        $view = $this->read('views/jnet/unit_space.php');
        self::assertStringContainsString('ja-hd ja-hd--rows', $view);
        self::assertStringContainsString('class="ja-tab', $view);
        self::assertStringContainsString('us-hero__bg', $view);
        self::assertStringContainsString('ja-stats', $view);
        self::assertStringContainsString('JnetUi::genericPhoto($unitId)', $view);
        self::assertStringContainsString('assets/css/jnet_unit_shell.css', $view);
        // Les composants d'échange gardent leurs actions et leurs droits (formulaires, accusé de réception).
        self::assertStringContainsString('_exchange_composer.php', $view);
        self::assertStringContainsString('_exchange.php', $view);
    }

    public function testGenericPhotosAreStablePerUnitAndShipped(): void
    {
        self::assertGreaterThan(2, count(JnetUi::GENERIC_PHOTOS));
        foreach (JnetUi::GENERIC_PHOTOS as $photo) {
            self::assertTrue(is_file(self::ROOT . 'public/' . $photo), $photo);
        }
        self::assertSame(JnetUi::genericPhoto(7), JnetUi::genericPhoto(7));
        self::assertContains(JnetUi::genericPhoto(12345), JnetUi::GENERIC_PHOTOS);
        $seen = [];
        for ($id = 1; $id <= count(JnetUi::GENERIC_PHOTOS); $id++) {
            $seen[JnetUi::genericPhoto($id)] = true;
        }
        self::assertCount(count(JnetUi::GENERIC_PHOTOS), $seen, 'des unités voisines n\'ont pas toutes la même photo');
        // Elles ne sont chargées qu'au survol : jamais en image de fond posée d'emblée.
        $css = $this->read('public/assets/css/jnet_hub.css');
        self::assertStringContainsString('background-image: var(--ph);', $css);
        self::assertStringNotContainsString('url(', $css);
    }

    public function testCrestIsEscapedAndHasNoShield(): void
    {
        $mono = JnetUi::crest('<b>1R', null);
        self::assertStringContainsString('&lt;b&gt;1R', $mono);
        self::assertStringNotContainsString('<b><b>', $mono);
        self::assertStringNotContainsString('<svg', $mono);
        self::assertStringNotContainsString('has-img', $mono);

        $img = JnetUi::crest('1R', 'https://x.test/a.png?x="y"', 'ja-crest ja-crest--md');
        self::assertStringContainsString('has-img', $img);
        self::assertStringContainsString('ja-crest--md', $img);
        self::assertStringContainsString('x=&quot;y&quot;', $img);
        self::assertStringNotContainsString('<b>', $img);
        self::assertStringContainsString('aria-hidden="true"', $img);
    }

    public function testReadableUnitTypeLabels(): void
    {
        self::assertSame('Compagnie', JnetUi::echelonLabel('compagnie'));
        self::assertSame('Équipe', JnetUi::echelonLabel('equipe'));
        self::assertSame('Demi-section', JnetUi::echelonLabel('section_otan'));
        self::assertSame('Infanterie légère', JnetUi::functionLabel('infantry_light'));
        self::assertSame('', JnetUi::echelonLabel('inconnu'));
        self::assertSame('', JnetUi::functionLabel(''));
        self::assertSame('1 membre', JnetUi::count(1, 'membre'));
        self::assertSame('12 membres', JnetUi::count(12, 'membre'));
    }
}
