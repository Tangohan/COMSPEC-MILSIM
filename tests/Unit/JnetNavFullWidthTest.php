<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Session;
use App\Services\Jnet\JnetUnitSpaceSettings as S;
use App\Support\JnetUi;
use PHPUnit\Framework\TestCase;

/**
 * JNET : en-têtes pleine largeur (aucune colonne centrée), accès évident à « Mon extranet » et à l'espace commun depuis
 * chaque page, fil d'Ariane sur les pages profondes, et annuaire du personnel refait. Les vues touchées sont rendues pour
 * de vrai : une vue qui appelle une classe sans « use » plante en production.
 */
final class JnetNavFullWidthTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';

    private ?bool $sessionStarted = null;
    /** @var array<string, mixed> */
    private array $savedSession = [];

    protected function setUp(): void
    {
        $prop = new \ReflectionProperty(Session::class, 'started');
        $this->sessionStarted = $prop->getValue();
        $prop->setValue(null, true);
        $this->savedSession = $_SESSION ?? [];
        $_SESSION = ['_csrf_token' => str_repeat('a', 64)];
        $GLOBALS['__app_locale'] = 'fr';
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->sessionStarted);
        $_SESSION = $this->savedSession;
        unset($GLOBALS['__app_locale']);
    }

    private function read(string $path): string
    {
        return (string) file_get_contents(self::ROOT . $path);
    }

    /** @param array<string, mixed> $vars */
    private function render(string $view, array $vars): string
    {
        $render = static function (string $__file, array $__vars): string {
            extract($__vars, EXTR_SKIP);
            ob_start();
            try {
                require $__file;
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }

            return (string) ob_get_clean();
        };

        return $render(base_path('views/' . $view . '.php'), $vars);
    }

    /** @return list<array<string, mixed>> */
    private function people(): array
    {
        $unitFil = ['chain' => ['2e Bataillon'], 'href' => 'https://exemple.test/jnet/u/4'];

        return [
            ['id' => 1, 'jnet_id' => 'PER-000001', 'name' => 'VIPER 1-1', 'callsign' => 'VIPER 1-1', 'grade' => 'Capitaine', 'grade_rank' => 10, 'unit' => '1re Compagnie', 'unit_id' => 4, 'function' => 'Chef de compagnie', 'role' => '', 'duty' => 'active', 'duty_label' => 'En service', 'photo' => null, 'initials' => 'VI', 'href' => 'https://exemple.test/jnet/personnel/1', 'fil' => $unitFil, 'match' => true],
            ['id' => 2, 'jnet_id' => 'PER-000002', 'name' => 'Léa Dubois', 'callsign' => 'ECHO', 'grade' => 'Lieutenant', 'grade_rank' => 20, 'unit' => '1re Compagnie', 'unit_id' => 4, 'function' => '', 'role' => '', 'duty' => 'off', 'duty_label' => 'Indisponible', 'photo' => 'https://exemple.test/p.jpg', 'initials' => 'LD', 'href' => 'https://exemple.test/jnet/personnel/2', 'fil' => $unitFil, 'match' => false],
            ['id' => 3, 'jnet_id' => 'PER-000003', 'name' => 'Hugo <b>Leroy</b>', 'callsign' => '', 'grade' => '', 'grade_rank' => 999, 'unit' => 'Hors ORBAT', 'unit_id' => 0, 'function' => 'Radio', 'role' => '', 'duty' => 'active', 'duty_label' => 'En service', 'photo' => null, 'initials' => 'HL', 'href' => 'https://exemple.test/jnet/personnel/3', 'fil' => null, 'match' => true],
        ];
    }

    /** @return array<string, mixed> */
    private function shell(string $inner, string $nav, string $title): array
    {
        return [
            'jnetInnerView' => $inner,
            'activeNav' => $nav,
            'jnetPageTitle' => $title,
            'title' => $title,
            'jnetTenantName' => 'Communauté d’essai',
            'jnetDisplayName' => 'Vincent Martin',
            'jnetCallsign' => 'VIPER 1-1',
            'jnetCanTba' => false,
            'jnetUnreadMail' => 0,
            'jnetPageJs' => ['jnet_personnel.js'],
            'jnetPageCss' => ['jnet_portal.css', 'jnet_personnel.css'],
        ];
    }

    public function testShellIsFluidAndNeverACenteredColumn(): void
    {
        $css = $this->read('public/assets/css/jnet_athena.css');
        self::assertStringNotContainsString('1320', $css);
        self::assertStringContainsString('--ja-gut: clamp(16px, 3vw, 40px);', $css);
        self::assertSame(1, preg_match('/\.ja-main \{[^}]*\}/', $css, $m));
        self::assertStringContainsString('max-width: none;', $m[0]);
        self::assertStringNotContainsString('margin: 0 auto', $m[0]);
        // Le plafond de lecture porte sur les paragraphes, pas sur le conteneur.
        self::assertStringContainsString(':where(.ja-main) p:not([class])', $css);

        foreach (['jnet_hub.css', 'jnet_entry.css', 'jnet_pages.css', 'jnet_personnel.css', 'partage.css'] as $file) {
            self::assertStringNotContainsString('1320', $this->read('public/assets/css/' . $file), $file);
        }
    }

    public function testEveryHeaderHasAClickableBrandAndAnExplicitExtranetLink(): void
    {
        // Coque des pages internes et fichiers partagés : marque + rubrique « Mon extranet » + « Espace commun ».
        foreach (['views/jnet/_page.php', 'views/partage/_page.php'] as $file) {
            $src = $this->read($file);
            self::assertMatchesRegularExpression('/class="ja-brand" href="<\?= \$h\(url\(\'jnet\'\)\) \?>"/', $src, $file);
            self::assertStringContainsString("'label' => 'Mon extranet'", $src, $file);
            self::assertStringContainsString("'label' => 'Espace commun'", $src, $file);
            self::assertStringContainsString('JnetUi::crumbs(', $src, $file);
        }

        $hub = $this->read('views/jnet/hub.php');
        self::assertStringContainsString('class="ja-hnav"', $hub);
        self::assertStringContainsString('>Mon extranet</a>', $hub);
        self::assertStringContainsString('>Espace commun</a>', $hub);
        self::assertStringContainsString('class="hb-common"', $hub);

        $entry = $this->read('views/jnet/entry.php');
        self::assertStringContainsString('ja-ib ja-ib--nav', $entry);
        self::assertStringContainsString('Mon extranet</span>', $entry);

        // Espace d'unité : « Mon extranet » juste avant « Changer d'espace », et la marque.
        $space = $this->read('views/jnet/unit_space.php');
        $nav = strpos($space, 'ja-ib ja-ib--nav');
        $switch = strpos($space, 'Changer d’espace');
        self::assertNotFalse($nav);
        self::assertNotFalse($switch);
        self::assertLessThan($switch, $nav);
        self::assertStringContainsString('class="ja-brand ja-brand--sm"', $space);
        self::assertStringContainsString('JnetUi::crumbs($crumbs)', $space);
        self::assertStringContainsString('url(\'jnet?commun=1\')', $space);
    }

    public function testBreadcrumbHelper(): void
    {
        $html = JnetUi::crumbs([['Mon extranet', '/jnet'], ['Personnel', '/jnet/personnel'], ['Ghost <1>', null]]);
        self::assertStringContainsString('<nav class="ja-crumbs" aria-label="Fil d’Ariane">', $html);
        self::assertStringContainsString('<a href="/jnet">Mon extranet</a>', $html);
        self::assertStringContainsString('<a href="/jnet/personnel">Personnel</a>', $html);
        self::assertStringContainsString('<span aria-current="page">Ghost &lt;1&gt;</span>', $html);
        self::assertSame(2, substr_count($html, '<a '));

        // Le dernier élément n'est jamais un lien, un libellé vide est ignoré, aucun élément = aucun fil.
        $last = JnetUi::crumbs([['Mon extranet', '/jnet'], ['', '/x'], ['Fichiers', '/partage']]);
        self::assertStringNotContainsString('href="/partage"', $last);
        self::assertStringContainsString('<span aria-current="page">Fichiers</span>', $last);
        self::assertSame('', JnetUi::crumbs([]));
    }

    public function testViewsImportTheClassesTheyCall(): void
    {
        $dirs = ['views/jnet', 'views/partage'];
        $builtin = ['self', 'static', 'parent', 'DateTime', 'DateTimeImmutable', 'DateTimeZone', 'DateInterval', 'IntlDateFormatter', 'Normalizer', 'PDO', 'Closure', 'ArrayObject'];
        foreach ($dirs as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::ROOT . $dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                    continue;
                }
                $src = (string) file_get_contents($file->getPathname());
                $imported = [];
                if (preg_match_all('/^use\s+([\w\\\\]+?)(?:\s+as\s+(\w+))?;/m', $src, $uses, PREG_SET_ORDER)) {
                    foreach ($uses as $u) {
                        $imported[] = $u[2] ?? substr(strrchr('\\' . $u[1], '\\'), 1);
                    }
                }
                $code = preg_replace('~/\*.*?\*/~s', '', $src) ?? $src;
                $code = preg_replace('~(?<!:)//[^\n]*~', '', $code) ?? $code;
                $code = preg_replace("~'(?:[^'\\\\\\n]|\\\\.)*'~", "''", $code) ?? $code;
                if (preg_match_all('/(?<![\\\\\w$>:])([A-Z]\w*)::/', $code, $calls)) {
                    foreach (array_unique($calls[1]) as $class) {
                        if (in_array($class, $builtin, true)) {
                            continue;
                        }
                        self::assertContains($class, $imported, basename($file->getPathname()) . ' appelle ' . $class . ' sans « use »');
                    }
                }
            }
        }
    }

    public function testPersonnelDirectoryRendersWithItsTools(): void
    {
        $html = $this->render('jnet/_page', $this->shell('jnet.personnel', 'personnel', 'Personnel') + [
            'personnel' => $this->people(),
            'personnelFilter' => 'all',
            'personnelFilters' => [['key' => 'all', 'label' => 'Tous'], ['key' => 'off', 'label' => 'Indisponibles']],
            'personnelTotal' => 3,
        ]);
        // Fil d'Ariane et en-tête.
        self::assertStringContainsString('class="ja-crumbs"', $html);
        self::assertStringContainsString('<span aria-current="page">Personnel</span>', $html);
        self::assertStringContainsString('class="ja-brand"', $html);
        self::assertStringContainsString('Mon extranet', $html);
        // Outils de l'annuaire : recherche, filtres, tri, regroupement, densité, compteur.
        foreach (['data-pe-q', 'data-pe-unit', 'data-pe-grade', 'data-pe-fn', 'data-pe-status', 'data-pe-sort', 'data-pe-group="unit"', 'data-pe-group="grade"', 'data-pe-view="cards"', 'data-pe-n', 'data-pe-none', 'data-pe-reset'] as $hook) {
            self::assertStringContainsString($hook, $html, $hook);
        }
        self::assertStringContainsString('<b data-pe-n>2</b> sur 3 membres', $html);
        // Unité cliquable avec son fil hiérarchique ; sans fil visible, l'unité reste un simple texte.
        self::assertStringContainsString('<a href="https://exemple.test/jnet/u/4">1re Compagnie</a><small>2e Bataillon</small>', $html);
        self::assertStringContainsString('<span>Hors ORBAT</span>', $html);
        // Le membre écarté par le filtre de l'adresse reste dans la page, masqué ; rien n'est affiché sans échappement.
        self::assertMatchesRegularExpression('/data-name="Léa Dubois"[^>]*hidden/', $html);
        self::assertStringNotContainsString('<b>Leroy</b>', $html);
        self::assertStringContainsString('Hugo &lt;b&gt;Leroy&lt;/b&gt;', $html);
        self::assertStringContainsString('<img src="https://exemple.test/p.jpg"', $html);
        // Un membre sans nom de personnage (indicatif seul) : la ligne sous son nom le dit, et ceux qui ont un nom gardent leur indicatif.
        self::assertStringContainsString('<small class="pe-noname">Nom non renseigné</small>', $html);
        self::assertStringContainsString('<small class="ja-mono">ECHO</small>', $html);
        self::assertStringContainsString('Indisponible', $html);
        self::assertStringContainsString('jnet_personnel.js', $html);
        self::assertStringNotContainsString('1320', $html);
    }

    public function testPersonnelUrlFilterBecomesTheStartingState(): void
    {
        $vars = $this->shell('jnet.personnel', 'personnel', 'Personnel') + [
            'personnel' => $this->people(),
            'personnelFilters' => [['key' => 'all', 'label' => 'Tous']],
            'personnelTotal' => 3,
        ];
        $byUnit = $this->render('jnet/_page', $vars + ['personnelFilter' => '1re compagnie']);
        self::assertStringContainsString('data-init-unit="1re Compagnie"', $byUnit);
        self::assertStringContainsString('<option value="1re Compagnie" selected>', $byUnit);

        $off = $this->render('jnet/_page', $vars + ['personnelFilter' => 'off']);
        self::assertStringContainsString('data-init-status="off"', $off);

        $text = $this->render('jnet/_page', $vars + ['personnelFilter' => 'radio']);
        self::assertStringContainsString('data-init-q="radio"', $text);
    }

    public function testPersonnelEmptyDirectoryIsHonest(): void
    {
        $html = $this->render('jnet/_page', $this->shell('jnet.personnel', 'personnel', 'Personnel') + ['personnel' => [], 'personnelFilter' => 'all', 'personnelFilters' => [], 'personnelTotal' => 0]);
        self::assertStringContainsString('Aucun membre actif dans l’annuaire.', $html);
        self::assertStringNotContainsString('data-pe-q', $html);
    }

    public function testPersonnelRecordRendersWithBreadcrumb(): void
    {
        $person = $this->people()[0] + ['profileFacts' => [['label' => 'Langues', 'value' => 'Français']], 'qualifications' => [['label' => 'Chef de groupe', 'name' => 'Chef de groupe', 'status' => 'Valide', 'expires' => '12/03/2027']], 'dossierHref' => 'https://exemple.test/personnel/1'];
        $html = $this->render('jnet/_page', $this->shell('jnet.personnel_show', 'personnel_show', 'VIPER 1-1') + ['person' => $person]);
        self::assertStringContainsString('<a href="', $html);
        self::assertMatchesRegularExpression('~<li><a href="[^"]*jnet">Mon extranet</a></li><li><a href="[^"]*jnet/personnel">Personnel</a></li><li><span aria-current="page">VIPER 1-1</span></li>~', $html);
        self::assertStringContainsString('<h1 id="pf-name">VIPER 1-1</h1>', $html);
        self::assertStringContainsString('Valide · <span>jusqu’au 12/03/2027</span>', $html);
        self::assertStringContainsString('Dossier complet', $html);
        self::assertStringContainsString('<small>2e Bataillon</small>', $html);
        // Le libellé d'identifiant reste sous le titre.
        self::assertLessThan((int) strpos($html, 'jnet-kicker'), (int) strpos($html, '<h1'));
    }

    public function testInternalPagesShowTheBreadcrumbInTheirShell(): void
    {
        $html = $this->render('jnet/_page', $this->shell('jnet.system', 'system', 'Système') + ['jnetInnerView' => 'jnet.does_not_exist']);
        self::assertStringContainsString('<span aria-current="page">Système</span>', $html);
        self::assertStringContainsString('class="ja-tab"', $html);
        self::assertStringContainsString('Espace commun', $html);
    }

    public function testHubNamesTheCommonSpaceAndTheExtranet(): void
    {
        $unit = ['id' => 4, 'label' => '1re Compagnie', 'kind' => 'Compagnie', 'facts' => ['18 membres'], 'accent' => '', 'monogram' => '1C', 'emblem' => null, 'href' => 'https://exemple.test/jnet/u/4', 'photo' => 'assets/images/login-accueil-nvg-forest.jpg', 'banner' => null, 'right' => ['label' => 'Membre', 'key' => 'write'], 'unread' => 2, 'mine' => true, 'depth' => 1, 'chain' => ['Organisation', '1re Compagnie'], 'links' => []];
        $html = $this->render('jnet/hub', [
            'hubCommunity' => 'Communauté d’essai',
            'hubOperator' => 'VIPER 1-1',
            'hubMine' => [$unit],
            'hubOthers' => [],
            'hubTree' => [$unit],
            'hubOrg' => ['label' => 'Communauté d’essai', 'href' => 'https://exemple.test/jnet?commun=1', 'unread' => 1],
            'hubSections' => [['key' => 'unite', 'title' => 'Mon unité', 'lead' => 'Vos rubriques.', 'items' => []]],
            'hubUnreadMail' => 0,
            'hubCanTba' => false,
        ]);
        self::assertStringContainsString('<nav class="ja-hnav"', $html);
        self::assertStringContainsString('aria-current="page">Mon extranet</a>', $html);
        self::assertStringContainsString('>Espace commun</a>', $html);
        self::assertStringContainsString('class="hb-common" href="https://exemple.test/jnet?commun=1"', $html);
        self::assertStringContainsString('1 non lu', $html);
        self::assertStringContainsString('title="Mon extranet" aria-label="ATHENA', $html);
    }

    public function testUnitSpaceHeaderLinksBackToTheExtranetAndCommonSpace(): void
    {
        $node = ['label' => '1re Compagnie', 'accent' => '', 'motto' => '', 'badge' => null];
        $look = S::look(S::defaults(), $node);
        $ref = static fn (int $id, string $label): array => ['id' => $id, 'label' => $label, 'accent' => '', 'href' => '#', 'code' => ''];
        $vars = [
            'space' => ['id' => 4, 'label' => $node['label'], 'leader' => '', 'deputy' => '', 'parent' => $ref(2, '2e Bataillon'), 'chain' => [$ref(0, 'Organisation'), $ref(2, '2e Bataillon'), $ref(4, $node['label'])], 'strength' => ['total' => 3, 'available' => 2], 'href' => '#'],
            'spaceLook' => $look,
            'usView' => 'effectif',
            'usHome' => 'https://exemple.test/jnet',
            'usHomeLabel' => 'Mon extranet',
            'usOperator' => 'VIPER 1-1',
            'usCommunity' => 'Communauté d’essai',
            'spacesRail' => [['id' => 4, 'label' => $node['label'], 'depth' => 1, 'href' => '#', 'active' => true, 'mine' => true]],
            'exchangesReady' => true,
            'canPost' => false,
            'received' => [], 'internal' => [], 'sentUp' => [], 'members' => [], 'spaceOps' => [], 'subTree' => [],
        ];
        $html = $this->render('jnet/unit_space', $vars);
        self::assertStringContainsString('class="ja-brand ja-brand--sm" href="https://exemple.test/jnet"', $html);
        self::assertStringContainsString('class="ja-ib ja-ib--nav" href="https://exemple.test/jnet"', $html);
        self::assertLessThan((int) strpos($html, 'Changer d’espace'), (int) strpos($html, 'ja-ib--nav'));
        self::assertStringContainsString('Espace commun</a></li>', $html);
        // Fil d'Ariane : Mon extranet › unité › rubrique.
        self::assertStringContainsString('<li><a href="https://exemple.test/jnet">Mon extranet</a></li>', $html);
        self::assertStringContainsString('<span aria-current="page">Effectif</span>', $html);
        // Pied de page : les deux destinations.
        self::assertSame(1, preg_match('/<footer class="ja-foot us-foot">.*?<\/footer>/s', $html, $foot));
        self::assertStringContainsString('>Mon extranet</a>', $foot[0]);
        self::assertStringContainsString('>Espace commun</a>', $foot[0]);
    }

    public function testPersonnelControllerKeepsVisibilityAndAddsNoData(): void
    {
        $ctl = $this->read('app/Controllers/Web/JnetPortalController.php');
        // Les mêmes cartes, le même filtre de l'adresse, et un fil tiré de l'arbre ORBAT déjà filtré pour le lecteur.
        self::assertStringContainsString('$this->jnet->loadPersonnelCards(', $ctl);
        self::assertStringContainsString('personnelFilterOptions', $ctl);
        self::assertStringContainsString('private function personnelMatches', $ctl);
        self::assertStringContainsString('$this->spaces->tree($tenantId, $userId)', $ctl);
        self::assertStringContainsString("'jnet_personnel.css'", $ctl);

        $js = $this->read('public/assets/js/jnet_personnel.js');
        foreach (['fetch(', 'XMLHttpRequest', 'sendBeacon'] as $network) {
            self::assertStringNotContainsString($network, $js, 'l’annuaire ne demande aucune donnée de plus');
        }
        self::assertStringContainsString('localStorage', $js);

        $view = $this->read('views/jnet/personnel.php');
        self::assertStringNotContainsString('class="kk"', $view);
        self::assertStringNotContainsString('alert(', $view);
    }
}
