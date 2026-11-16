<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Gate;
use App\Core\Session;
use App\Support\EspacesMenu;
use PHPUnit\Framework\TestCase;

/**
 * Menu « Espaces » de la barre Athena : entrée Bureau SSE selon les droits du portail, espace courant,
 * regroupement, balisage accessible, clavier, thèmes, mouvement réduit.
 */
final class EspacesMenuTest extends TestCase
{
    protected function setUp(): void
    {
        Gate::reset();
        Session::actAs([]);
    }

    protected function tearDown(): void
    {
        Gate::reset();
        Session::actAs([]);
    }

    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private static function read(string $rel): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::root() . '/' . $rel));
    }

    /** @return list<array<string, mixed>> */
    private static function links(bool $withSse): array
    {
        $links = [
            ['abbr' => 'JNT', 'label' => 'JNET Extranet', 'desc' => 'Bureau numérique d’unité', 'href' => url('jnet')],
            ['abbr' => 'ATK', 'label' => 'ATAK', 'desc' => 'Carte tactique', 'href' => url('atak')],
            ['abbr' => 'OPR', 'label' => 'Outils opérateur', 'desc' => 'Identités, papiers, fichiers', 'href' => url('operateur'), 'tone' => 'blue'],
            ['abbr' => 'TOC', 'label' => 'Poste TOC', 'desc' => 'Console du poste', 'href' => url('atak/toc')],
            ['abbr' => 'PLN', 'label' => 'Plans d’opération', 'desc' => 'Préparer', 'href' => url('atak/toc/plans')],
            ['abbr' => 'BO', 'label' => 'Back-office', 'desc' => 'Gestion', 'href' => url('back-office')],
        ];
        if ($withSse) {
            $links[] = EspacesMenu::sseEntry();
        }

        return $links;
    }

    private static function render(array $links, string $current, string $mode = 'bar'): string
    {
        $espacesGroups = EspacesMenu::groups($links, $current);
        $espacesMode = $mode;
        $espacesLabel = 'Espaces';
        $espacesAria = 'Espaces Athena';
        ob_start();
        require self::root() . '/views/partials/athena_espaces_menu.php';

        return (string) ob_get_clean();
    }

    public function testSseEntryFollowsThePortalDoor(): void
    {
        self::assertFalse(EspacesMenu::canEnterSse(), 'invité : pas de bureau SSE');

        Session::actAs(['user_id' => 7, 'tenant_id' => 1]);
        Gate::getInstance()->setPermissions(['jnet.read']);
        self::assertFalse(EspacesMenu::canEnterSse(), 'compte sans droit SSE');

        Gate::getInstance()->setPermissions(['atak.sse.access']);
        self::assertTrue(EspacesMenu::canEnterSse(), 'membre habilité');

        Gate::getInstance()->setPermissions(['atak.sse.grant']);
        self::assertTrue(EspacesMenu::canEnterSse(), 'commandement');
    }

    public function testSseEntryPointsAtThePortalHomeAndSitsInItsOwnGroup(): void
    {
        $entry = EspacesMenu::sseEntry();
        self::assertSame('Bureau SSE', $entry['label']);
        self::assertStringEndsWith('/atak/sse/accueil', $entry['href']);

        $groups = EspacesMenu::groups(self::links(true), '');
        $byId = array_column($groups, null, 'id');
        self::assertSame(['SSE', 'OPR'], array_column($byId['renseignement']['items'], 'abbr'));
        self::assertSame(['ATK', 'TOC', 'PLN'], array_column($byId['operations']['items'], 'abbr'));
    }

    public function testCurrentSpaceIsTheMostSpecificMatchAndOnlyOne(): void
    {
        $links = self::links(true);
        $active = static function (string $path) use ($links): ?string {
            return EspacesMenu::activeAbbr($links, $path);
        };
        self::assertSame('ATK', $active('atak'));
        self::assertSame('SSE', $active('atak/sse/fichiers'));
        self::assertSame('SSE', $active('atak/sse/accueil'));
        self::assertSame('PLN', $active('atak/toc/plans'));
        self::assertSame('TOC', $active('atak/toc'));
        self::assertSame('BO', $active('back-office/personnel'));
        self::assertNull($active('hub'));

        $flat = array_merge(...array_column(EspacesMenu::groups($links, 'atak/sse/dossiers'), 'items'));
        self::assertSame(['SSE'], array_column(array_filter($flat, static fn (array $i): bool => $i['active']), 'abbr'));
    }

    public function testBarMarkupHasTheBureauSseCardActiveStateAndAria(): void
    {
        $html = self::render(self::links(true), 'atak/sse/fichiers');

        self::assertSame(1, substr_count($html, 'Bureau SSE</strong>'));
        self::assertMatchesRegularExpression('#<a href="[^"]*/atak/sse/accueil"[^>]*class="esp-card is-active"[^>]*role="menuitem"[^>]*aria-current="page"#', $html);
        self::assertSame(1, substr_count($html, 'aria-current="page"'), 'un seul espace courant');
        self::assertStringContainsString('aria-haspopup="menu"', $html);
        self::assertStringContainsString('aria-expanded="false"', $html);
        self::assertStringContainsString('aria-controls="athena-header-espaces"', $html);
        self::assertStringContainsString('id="athena-header-espaces"', $html);
        self::assertStringContainsString('role="menu"', $html);
        self::assertStringContainsString('role="group" aria-labelledby="esp-b-renseignement"', $html);
        self::assertStringContainsString('id="esp-b-renseignement"', $html);
        self::assertStringContainsString('esp-pill--here', $html);
        self::assertStringContainsString('esp-card--blue', $html, 'Outils opérateur reste bleu');
        // Chaque carte est un lien avec icône SVG décorative et nom.
        self::assertSame(count(self::links(true)), substr_count($html, 'role="menuitem"'));
        self::assertSame(count(self::links(true)), substr_count($html, '<svg viewBox="0 0 24 24" width="20"'));
    }

    public function testNoBureauSseCardWithoutTheRight(): void
    {
        $html = self::render(self::links(false), 'atak');

        self::assertStringNotContainsString('Bureau SSE', $html);
        self::assertStringNotContainsString('atak/sse', $html);
        self::assertStringContainsString('esp-card is-active', $html);
    }

    public function testDrawerModeHasNoMenuRolesAndNoButton(): void
    {
        $html = self::render(self::links(true), 'atak/sse', 'drawer');

        self::assertStringContainsString('esp esp--drawer', $html);
        self::assertStringContainsString('Bureau SSE</strong>', $html);
        self::assertStringNotContainsString('role="menu', $html);
        self::assertStringNotContainsString('data-athena-toggle', $html);
        self::assertStringContainsString('aria-current="page"', $html);
    }

    public function testBothHeadersUseTheSharedPartial(): void
    {
        $member = self::read('views/partials/athena_caverne_header.php');
        $guest = self::read('views/partials/athena_header_guest.php');

        self::assertStringContainsString('EspacesMenu::canEnterSse()', $member);
        self::assertStringContainsString("'bar'; ", $member);
        self::assertStringContainsString("'drawer'; ", $member);
        self::assertSame(2, substr_count($member, 'athena_espaces_menu.php'));
        self::assertSame(1, substr_count($guest, 'athena_espaces_menu.php'));
        self::assertStringNotContainsString('athena-header__espace-item', $member . $guest);
        self::assertStringNotContainsString('athena-header__espaces-grid', $member . $guest);
        // Pas de surtitre au-dessus du titre du panneau.
        self::assertStringNotContainsString('header_espaces_kicker', $guest);
    }

    public function testScriptGivesKeyboardNavigationEscapeAndFocusReturn(): void
    {
        $js = self::read('public/assets/js/athena-header.js');

        foreach (['ArrowDown', 'ArrowUp', 'Home', 'End', 'Escape', 'owner.focus()', 'prefers-reduced-motion', "'role') === 'menu'"] as $needle) {
            self::assertStringContainsString($needle, $js, $needle);
        }
    }

    public function testStylesCoverThemesMotionTouchTargetsAndTextSize(): void
    {
        $css = self::read('public/assets/css/athena-header.css');
        $from = strpos($css, 'Menu « Espaces » : une carte par espace');
        self::assertNotFalse($from);
        $block = substr($css, $from);

        self::assertStringContainsString('body.layout-light .esp', $block, 'thème clair');
        self::assertStringContainsString('html[data-bo-theme="dark"] .esp', $block, 'thème sombre');
        self::assertStringContainsString('@media (prefers-reduced-motion: reduce)', $block);
        self::assertStringContainsString('.esp-card:focus-visible', $block);
        self::assertStringContainsString('min-height: 3.5rem', $block, 'cible tactile de 44 px au moins');
        self::assertStringContainsString('@media (max-width: 899px)', $block, 'feuille pleine largeur');
        self::assertStringNotContainsString('sse-ds', $block);
        self::assertStringNotContainsString('Archivo', $block);
        self::assertStringNotContainsString('text-transform: uppercase', $block, 'pas de surtitre en capitales');

        preg_match_all('/font-size:\s*(\d+(?:\.\d+)?)px/', $block, $m);
        self::assertNotEmpty($m[1]);
        foreach ($m[1] as $px) {
            self::assertGreaterThanOrEqual(13, (float) $px);
        }
    }

    public function testEnglishTranslationsExistForNewTexts(): void
    {
        $dict = require self::root() . '/lang/en/site_auto.php';
        foreach (['Renseignement et outils', 'Le site', 'Nous rejoindre', 'Autres espaces', 'Habilité', 'Ici', 'Bureau SSE', 'Dossiers, personnes et lieux'] as $fr) {
            self::assertArrayHasKey($fr, $dict, $fr);
            self::assertNotSame('', trim((string) $dict[$fr]));
        }
    }
}
