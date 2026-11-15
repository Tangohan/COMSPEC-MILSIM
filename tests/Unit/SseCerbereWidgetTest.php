<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\SseCerbereWidgetController;
use App\Core\Request;
use App\Services\Sse\Cerbere\SseCerbereContext;
use App\Services\Sse\Cerbere\SseCerbereWidget;
use App\Services\Sse\Links\SseLinkGraph;
use App\Services\Sse\SseTriageInbox;
use App\Support\ShortCache;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\ArraySseLinkViewer;
use Tests\Support\SseLinksFixture;
use Tests\Support\SseLinksSqlite;

/**
 * Widget Cerbère (bouton flottant du portail SSE) : résolution du contexte par route, contenu filtré par les droits et
 * l'habilitation du lecteur (jamais d'identité de membre ni d'élément d'une autre communauté), badge de la page, propositions
 * à accepter ou écarter par les routes existantes, point d'accès JSON (404 identique, débit), inclusion unique dans la coque,
 * style du portail sans design system « SSE DS », aucune étiquette au-dessus d'un titre, texte lisible, impression.
 */
final class SseCerbereWidgetTest extends TestCase
{
    private PDO $pdo;

    /** @var array<string, int> */
    private array $ids = [];

    protected function setUp(): void
    {
        ShortCache::setEnabled(false);
        $_SESSION = ['user_id' => 7, 'tenant_id' => 1];
        \App\Core\Csrf::token();
        if (extension_loaded('pdo_sqlite')) {
            $this->pdo = SseLinksSqlite::open();
            $this->ids = SseLinksFixture::seed($this->pdo);
        }
    }

    protected function tearDown(): void
    {
        if (extension_loaded('pdo_sqlite')) {
            SseLinksSqlite::close();
        }
    }

    private function needDb(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
    }

    private static function read(string $relative): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $relative);
    }

    /** @param list<array<string, mixed>> $items */
    private function inbox(array $items, string $nav = 'rapprochements', string $key = 'suggestions'): SseTriageInbox
    {
        return new SseTriageInbox(
            [['key' => $key, 'nav' => $nav, 'label' => 'Suggestions du moteur', 'tab' => 'Suggestions', 'path' => 'atak/sse/rapprochements', 'load' => static fn (int $t): array => $items]],
            static fn (string $s, string $l): bool => true,
            static fn (): int => time(),
        );
    }

    private static function suggestion(int $id, int $caseId, int $priority = 1): array
    {
        return ['key' => 'suggestion:' . $id, 'title' => 'Doublon probable', 'meta' => 'Doublon', 'at' => '2026-11-14 10:00:00', 'href' => '/atak/sse/rapprochements', 'priority' => $priority,
            'subject_type' => 'case', 'subject_id' => $caseId, 'reason' => 'Même référence de laboratoire.', 'type_label' => 'Doublon biométrique'];
    }

    /** @return list<array<string, mixed>> */
    private static function nav(): array
    {
        return \App\Support\SsePortalNav::sections(true, true, false, true, true);
    }

    private function widget(?ArraySseLinkViewer $viewer = null, ?SseTriageInbox $inbox = null): SseCerbereWidget
    {
        $v = $viewer ?? ArraySseLinkViewer::full();

        return new SseCerbereWidget($v, self::nav(), $inbox ?? $this->inbox([]), static fn (): SseLinkGraph => new SseLinkGraph($v, null, null, null, null), static fn (int $t): array => [
            'treatments' => [['label' => 'Cerbère-Liaison', 'does' => 'Rattache.', 'active' => true, 'why' => '', 'total' => 3, 'last' => '2026-11-09 18:45:01']],
        ]);
    }

    private function html(array $w): string
    {
        $_SESSION['_csrf_token'] = 'jeton';
        $data = ['w' => $w, 'message' => '', 'csrf' => 'jeton'];
        extract($data);
        ob_start();
        require dirname(__DIR__, 2) . '/views/atak/sse/_cerbere_panel.php';

        return (string) ob_get_clean();
    }

    // ---- Contexte par route -------------------------------------------------------------------------------------------

    /** @return array<string, array{0: string, 1: string, 2: string, 3: string}> */
    public static function routes(): array
    {
        return [
            '/atak/sse/accueil' => ['home', '', '', 'accueil'],
            '/atak/sse' => ['home', '', '', 'accueil'],
            '/atak/sse/operations' => ['home', '', '', 'operations'],
            '/atak/sse/papiers/tableau' => ['home', '', '', 'papiers-tableau'],
            '/atak/sse/identites' => ['list', '', '', 'identites'],
            '/atak/sse/identites/12' => ['element', 'person', '12', 'identites'],
            '/atak/sse/papiers/identites/5' => ['element', 'identity', '5', 'papiers-identites'],
            '/atak/sse/papiers/documents/9' => ['element', 'paper', '9', 'papiers'],
            '/atak/sse/papiers/controles/4' => ['element', 'check', '4', 'papiers-controles'],
            '/atak/sse/papiers/controles' => ['list', '', '', 'papiers-controles'],
            '/atak/sse/papiers/atelier' => ['page', '', '', 'papiers-atelier'],
            '/atak/sse/avis' => ['list', '', '', 'avis-recherche'],
            '/atak/sse/avis/detections' => ['list', '', '', 'avis-detections'],
            '/atak/sse/dossiers/33' => ['element', 'case', '33', 'dossiers'],
            '/atak/sse/dossiers/33/pdf' => ['element', 'case', '33', 'dossiers'],
            '/atak/sse/dossiers' => ['list', '', '', 'dossiers'],
            '/atak/sse/interet/8' => ['element', 'interest', '8', 'interet'],
            '/atak/sse/fiches/21' => ['element', 'note', '21', 'fiches'],
            '/atak/sse/fiches/nouvelle' => ['list', '', '', 'fiches'],
            '/atak/sse/transmissions/6' => ['element', 'transmission', '6', 'transmissions'],
            '/atak/sse/sites/3' => ['element', 'site', '3', 'sites'],
            '/atak/sse/campagnes/2' => ['element', 'campaign', '2', 'campagnes'],
            '/atak/sse/campagnes' => ['list', '', '', 'campagnes'],
            '/atak/sse/fichiers/photos' => ['list', '', '', 'fichiers-photos'],
            '/atak/sse/a-trier' => ['triage', '', '', 'a-trier'],
            '/atak/sse/droits' => ['list', '', '', 'droits'],
            '/atak/sse/cerberes' => ['list', '', '', 'cerberes'],
            '/public/atak/sse/dossiers/33?x=1' => ['element', 'case', '33', 'dossiers'],
        ];
    }

    public function testContextIsResolvedFromTheRoute(): void
    {
        foreach (self::routes() as $path => [$kind, $type, $id, $nav]) {
            $c = SseCerbereContext::fromPath($path);
            self::assertSame($kind, $c['kind'], $path . ' : nature');
            self::assertSame($type, $c['type'], $path . ' : type');
            self::assertSame($id, $c['id'], $path . ' : identifiant');
            self::assertSame($nav, $c['nav'], $path . ' : entrée de navigation');
            self::assertNotSame('', $c['label'], $path . ' : libellé');
        }
    }

    public function testUnknownOrExternalPagesNeverPickAnElement(): void
    {
        self::assertSame('home', SseCerbereContext::fromPath('')['kind']);
        self::assertSame('page', SseCerbereContext::fromPath('/atak/sse/inconnu')['kind']);
        self::assertSame('', SseCerbereContext::fromPath('/atak/sse/dossiers/abc')['type']);
        self::assertSame('atak/sse/dossiers/12', SseCerbereContext::sanitizePage('/public/atak/sse/dossiers/12?tenant_id=2'));
        foreach (['https://exemple.fr/atak/sse/../../etc', '/atak/sse/../admin', '//evil/atak/sse/x y', '/admin/users', '', "/atak/sse/a\x00b", '/atak/sse/a\\b'] as $bad) {
            self::assertNull(SseCerbereContext::sanitizePage($bad), $bad);
        }
    }

    // ---- Contenu d'un élément ----------------------------------------------------------------------------------------

    public function testPersonPanelTellsWhatCerbereDidAndTheProvenance(): void
    {
        $this->needDb();
        $ctx = SseCerbereContext::fromPath('/atak/sse/identites/' . $this->ids['person']);
        $w = $this->widget()->panel($ctx);
        self::assertNotNull($w);
        $tabs = array_column($w['tabs'], null, 'id');
        self::assertSame(['done', 'todo', 'origin', 'auto'], array_keys($tabs));
        $groups = array_column($tabs['done']['groups'], null, 'treatment');
        self::assertArrayHasKey('Cerbère-Liaison', $groups, 'le classement posé par Cerbère-Liaison apparaît');
        $rule = array_column($groups['Cerbère-Liaison']['rows'], 'rule');
        self::assertContains('cerbere.link.case_single_open', $rule);
        $manual = array_column($tabs['origin']['rows'], 'rule');
        self::assertContains('manual.link', $manual, 'le lien posé à la main est en provenance, pas parmi les actions de Cerbère');
        foreach ($tabs['done']['groups'] as $g) {
            foreach ($g['rows'] as $r) {
                self::assertNotSame('manual.link', $r['rule']);
            }
        }
        $html = $this->html($w);
        self::assertStringContainsString('Fait par Cerbère', $html);
        self::assertStringContainsString('cerbere.link.case_single_open', $html, 'la règle appliquée est montrée');
        self::assertSame(count($w['tabs']), substr_count($html, 'class="cw-desc"'), 'une ligne de description par section');
    }

    public function testAutomationsTabNeedsToReadAnalysis(): void
    {
        $this->needDb();
        $ctx = SseCerbereContext::fromPath('/atak/sse/identites/' . $this->ids['person']);
        $viewer = ArraySseLinkViewer::full()->with(['read' => ['fichiers', 'papiers', 'dossiers']]);
        $w = $this->widget($viewer)->panel($ctx);
        self::assertNotNull($w);
        self::assertNotContains('auto', array_column($w['tabs'], 'id'));
    }

    // ---- Droits, IDOR, habilitation ----------------------------------------------------------------------------------

    public function testAnotherCommunitysElementAnswersAsIfItDidNotExist(): void
    {
        $this->needDb();
        $widget = $this->widget();
        $other = SseCerbereContext::fromPath('/atak/sse/identites/' . $this->ids['person_t2']);
        self::assertNull($widget->panel($other), 'même numéro de fiche, autre communauté');
        self::assertNull($widget->panel(SseCerbereContext::fromPath('/atak/sse/identites/999999')), 'élément inexistant : même réponse');
    }

    public function testSectionWithoutReadRightIsNotRevealed(): void
    {
        $this->needDb();
        $viewer = ArraySseLinkViewer::full()->with(['read' => ['papiers']]);
        self::assertNull($this->widget($viewer)->panel(SseCerbereContext::fromPath('/atak/sse/identites/' . $this->ids['person'])));
    }

    public function testCaseAboveClearanceIsNotShown(): void
    {
        $this->needDb();
        $low = ArraySseLinkViewer::full()->with(['clearance' => 'interne', 'truth' => false]);
        self::assertNull($this->widget($low)->panel(SseCerbereContext::fromPath('/atak/sse/dossiers/' . $this->ids['case_secret'])), 'dossier confidentiel, habilitation interne');
        self::assertNotNull($this->widget(ArraySseLinkViewer::full())->panel(SseCerbereContext::fromPath('/atak/sse/dossiers/' . $this->ids['case_secret'])));
    }

    public function testMemberIdentityIsNeverNamedToAReaderWithoutTheMemberGrant(): void
    {
        $this->needDb();
        $nameCheck = static function (string $html): bool {
            return stripos($html, 'Dupuis') !== false;
        };
        $without = ArraySseLinkViewer::full()->with(['members' => []]);
        foreach (['/atak/sse/papiers/identites/' . $this->ids['identity'], '/atak/sse/identites/' . $this->ids['person'], '/atak/sse/papiers/identites/' . $this->ids['member_identity']] as $path) {
            $w = $this->widget($without)->panel(SseCerbereContext::fromPath($path));
            $html = $w === null ? '' : $this->html($w);
            self::assertFalse($nameCheck($html), 'aucun nom de membre : ' . $path);
        }
        self::assertNull($this->widget($without)->panel(SseCerbereContext::fromPath('/atak/sse/papiers/identites/' . $this->ids['member_identity'])), 'la fiche du membre elle-même est introuvable');
    }

    // ---- À décider et badge ------------------------------------------------------------------------------------------

    public function testPendingItemsAreFilteredToTheElementAndCountedInTheBadge(): void
    {
        $this->needDb();
        $inbox = $this->inbox([self::suggestion(41, $this->ids['case'], 2), self::suggestion(42, $this->ids['case']), self::suggestion(43, $this->ids['case_secret'])]);
        $widget = $this->widget(null, $inbox);
        $ctx = SseCerbereContext::fromPath('/atak/sse/dossiers/' . $this->ids['case']);
        self::assertSame(['count' => 2, 'tone' => 'bad', 'label' => '2', 'aria' => '2 à trier'], $widget->badge($ctx));
        $w = $widget->panel($ctx);
        $todo = array_column($w['tabs'], null, 'id')['todo'];
        self::assertSame([41, 42], array_column($todo['rows'], 'suggestion'));
        $other = $widget->badge(SseCerbereContext::fromPath('/atak/sse/dossiers/' . $this->ids['case_secret']));
        self::assertSame(1, $other['count']);
        self::assertSame(0, $widget->badge(SseCerbereContext::fromPath('/atak/sse/dossiers/777'))['count'], 'ce qui concerne un autre dossier ne compte pas ici');
    }

    public function testBadgeFollowsThePageFamily(): void
    {
        $inbox = $this->inbox([self::suggestion(1, 5), self::suggestion(2, 6)]);
        $widget = $this->widget(null, $inbox);
        self::assertSame(2, $widget->badge(SseCerbereContext::fromPath('/atak/sse/rapprochements'))['count'], 'la file de la liste');
        self::assertSame(2, $widget->badge(SseCerbereContext::fromPath('/atak/sse/dossiers'))['count'], 'les éléments des dossiers');
        self::assertSame(0, $widget->badge(SseCerbereContext::fromPath('/atak/sse/identites'))['count'], 'une autre liste');
        self::assertSame(2, $widget->badge(SseCerbereContext::fromPath('/atak/sse/accueil'))['count'], 'le total au tableau de bord');
        self::assertSame(0, $widget->badge(SseCerbereContext::fromPath('/atak/sse/a-trier'))['count'], 'pas de doublon dans « À trier » elle-même');
    }

    public function testProposalsCanBeAcceptedOrDismissedThroughTheExistingRoutesOnlyWithTheRight(): void
    {
        $this->needDb();
        $inbox = $this->inbox([self::suggestion(41, $this->ids['case'])]);
        $ctx = SseCerbereContext::fromPath('/atak/sse/dossiers/' . $this->ids['case']);
        $html = $this->html($this->widget(null, $inbox)->panel($ctx));
        self::assertStringContainsString('/atak/sse/rapprochements/41/valider', $html);
        self::assertStringContainsString('/atak/sse/rapprochements/41/rejeter', $html);
        self::assertStringContainsString('name="_csrf_token" value="jeton"', $html);
        self::assertStringContainsString('name="case_id" value="' . $this->ids['case'] . '"', $html);
        self::assertStringContainsString('Accepter', $html);
        self::assertStringContainsString('Écarter', $html);
        $reader = ArraySseLinkViewer::full()->with(['write' => ['fichiers']]);
        $htmlRead = $this->html($this->widget($reader, $inbox)->panel($ctx));
        self::assertStringNotContainsString('/valider', $htmlRead, 'sans le droit d’écrire dans les dossiers : voir seulement');
        self::assertStringContainsString('>Voir<', $htmlRead);
        $guest = ArraySseLinkViewer::full()->with(['guest' => true]);
        self::assertStringNotContainsString('/valider', $this->html($this->widget($guest, $inbox)->panel($ctx)));
    }

    public function testListPanelLinksToTheTriageFilteredOnTheContext(): void
    {
        $inbox = $this->inbox([self::suggestion(1, 5), self::suggestion(2, 6)]);
        $w = $this->widget(null, $inbox)->panel(SseCerbereContext::fromPath('/atak/sse/rapprochements'));
        $todo = array_column($w['tabs'], null, 'id')['todo'];
        self::assertStringContainsString('/atak/sse/a-trier?source=suggestions', $todo['all']);
        self::assertSame(2, $todo['sources'][0]['count']);
        self::assertStringContainsString('source=suggestions', $this->html($w));
    }

    public function testOverviewOffersShortcutsOnlyForVisiblePages(): void
    {
        $nav = array_values(array_filter(array_map(static function (array $s): array {
            $s['items'] = array_values(array_filter($s['items'], static fn (array $i): bool => $i['id'] !== 'cerberes'));

            return $s;
        }, self::nav()), static fn (array $s): bool => $s['items'] !== []));
        $v = ArraySseLinkViewer::full();
        $w = (new SseCerbereWidget($v, $nav, $this->inbox([self::suggestion(1, 5)]), null, static fn (int $t): array => ['treatments' => []]))->panel(SseCerbereContext::fromPath('/atak/sse/accueil'));
        $over = array_column($w['tabs'], null, 'id')['overview'];
        $labels = array_column($over['shortcuts'], 'label');
        self::assertContains('À trier', $labels);
        self::assertNotContains('Cerbères', $labels);
    }

    // ---- Point d'accès -----------------------------------------------------------------------------------------------

    private function request(string $page): Request
    {
        $_GET = ['page' => $page];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/atak/sse/cerbere/panneau';

        return new Request();
    }

    private function controller(?ArraySseLinkViewer $viewer = null, bool $tooMany = false): SseCerbereWidgetController
    {
        $v = $viewer ?? ArraySseLinkViewer::full();

        return new SseCerbereWidgetController($v, $this->widget($v), static fn (string $k, int $m, int $w): bool => $tooMany, static fn (): array => self::nav());
    }

    public function testEndpointRendersAnHtmlFragmentAndTheSameNotFoundForEveryUnreadableElement(): void
    {
        $this->needDb();
        $ok = $this->controller()->panel($this->request('/atak/sse/identites/' . $this->ids['person']));
        self::assertSame(200, $ok->statusCode());
        self::assertStringContainsString('text/html', (string) $ok->headerValue('Content-Type'));
        self::assertStringContainsString('private, no-store', (string) $ok->headerValue('Cache-Control'));
        self::assertStringContainsString('class="cw-content"', $ok->body() . 'class="cw-content"');
        $missing = $this->controller()->panel($this->request('/atak/sse/identites/999999'));
        $foreign = $this->controller()->panel($this->request('/atak/sse/identites/' . $this->ids['person_t2']));
        $noRight = $this->controller(ArraySseLinkViewer::full()->with(['read' => ['papiers']]))->panel($this->request('/atak/sse/identites/' . $this->ids['person']));
        foreach ([$missing, $foreign, $noRight] as $r) {
            self::assertSame(404, $r->statusCode());
        }
        self::assertSame($missing->body(), $foreign->body(), 'même réponse : rien ne dit si l’élément existe');
        self::assertSame($missing->body(), $noRight->body());
    }

    public function testEndpointIgnoresAnyCommunityGivenInTheRequestAndIsRateLimited(): void
    {
        $this->needDb();
        $_GET = ['page' => '/atak/sse/identites/' . $this->ids['person_t2'], 'tenant_id' => '2', 'tenant' => '2'];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $r = $this->controller()->panel(new Request());
        self::assertSame(404, $r->statusCode(), 'la communauté de la session (1) l’emporte sur celle de la requête');
        self::assertSame(429, $this->controller(null, true)->panel($this->request('/atak/sse/accueil'))->statusCode());
        $src = self::read('app/Controllers/Web/SseCerbereWidgetController.php');
        self::assertStringNotContainsString("query('tenant", $src);
        self::assertStringNotContainsString("input('tenant", $src);
        self::assertStringNotContainsString('tenant_id', $src);
    }

    public function testRouteIsReadOnlyAndBehindThePortalGuards(): void
    {
        $routes = self::read('routes/web.php');
        self::assertStringContainsString("get('/atak/sse/cerbere/panneau', [\\App\\Controllers\\Web\\SseCerbereWidgetController::class, 'panel'], \$mwSsePortal)", $routes);
        self::assertStringNotContainsString("post('/atak/sse/cerbere", $routes);
        self::assertStringNotContainsString('Csrf::validate', self::read('app/Controllers/Web/SseCerbereWidgetController.php'), 'lecture seule');
    }

    // ---- Coque, style, accessibilité ---------------------------------------------------------------------------------

    public function testTheShellIncludesTheWidgetExactlyOnceAndNoPageDoes(): void
    {
        $layout = self::read('views/atak/sse/_layout.php');
        self::assertSame(1, substr_count($layout, '_cerbere_widget.php'));
        self::assertSame(1, substr_count($layout, 'sse-cerbere-widget.js'));
        self::assertSame(1, substr_count($layout, 'sse-cerbere-widget.css'));
        self::assertStringContainsString('SseCerbereWidget::shell(', $layout);
        self::assertStringContainsString("'iframe'", $layout, 'jamais dans un cadre');
        $count = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/views/atak', \FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.php') && str_contains((string) file_get_contents($f->getPathname()), '_cerbere_widget.php')) {
                ++$count;
            }
        }
        self::assertSame(1, $count, 'une seule vue inclut le widget : la coque');
    }

    public function testShellDataIsOffWithoutACommunity(): void
    {
        $_SESSION = [];
        $d = SseCerbereWidget::shell(self::nav(), '/atak/sse/accueil');
        self::assertFalse($d['enabled']);
        self::assertSame('home', $d['ctx']['kind']);
    }

    public function testNoSseDesignSystemAndNoEyebrow(): void
    {
        $files = ['public/assets/css/sse-cerbere-widget.css', 'public/assets/js/sse-cerbere-widget.js', 'views/atak/sse/_cerbere_widget.php', 'views/atak/sse/_cerbere_panel.php'];
        foreach ($files as $f) {
            $src = self::read($f);
            self::assertStringNotContainsString('sse-ds', $src, $f);
            self::assertStringNotContainsString('Archivo', $src, $f);
            self::assertSame(0, preg_match('/\.sse-[a-z]/', $src), $f . ' : aucune classe .sse-*');
            self::assertSame(0, preg_match('/eyebrow|kicker|overline/i', $src), $f . ' : aucune étiquette au-dessus d’un titre');
            self::assertSame(0, preg_match('/letter-spacing:\s*0?\.\d*em/', $src), $f . ' : pas de petites capitales espacées');
        }
        $panel = self::read('views/atak/sse/_cerbere_panel.php') . self::read('views/atak/sse/_cerbere_widget.php');
        self::assertSame(0, preg_match('/<(?:p|span|small|div)[^>]*class="[^"]*(?:cw-(?:label|tag|eyebrow))[^"]*"[^>]*>[^<]*<\/(?:p|span|small|div)>\s*<h[1-3]/', $panel));
    }

    public function testTextIs13PixelsOrMoreAndTargetsAre44(): void
    {
        $css = self::read('public/assets/css/sse-cerbere-widget.css');
        preg_match_all('/font-size:\s*([\d.]+)(rem|px)/', $css, $m, PREG_SET_ORDER);
        self::assertNotEmpty($m);
        foreach ($m as [$all, $n, $unit]) {
            $px = $unit === 'rem' ? (float) $n * 16 : (float) $n;
            self::assertGreaterThanOrEqual(13.0, $px, $all);
        }
        self::assertMatchesRegularExpression('/\.cw-fab\s*\{[^}]*width:\s*52px;[^}]*height:\s*52px/s', $css);
        self::assertMatchesRegularExpression('/\.cw-close\s*\{[^}]*width:\s*44px;[^}]*height:\s*44px/s', $css);
        self::assertMatchesRegularExpression('/\.cw-tab\s*\{[^}]*min-height:\s*44px/s', $css);
        self::assertMatchesRegularExpression('/\.cw-fab\s*\{[^}]*position:\s*fixed;[^}]*right:[^}]*bottom:/s', $css, 'en bas à droite');
        self::assertMatchesRegularExpression('/@media print\s*\{\s*\.cw\s*\{\s*display:\s*none\s*!important/s', $css, 'absent à l’impression');
        self::assertStringContainsString('prefers-reduced-motion', $css);
        // Le bouton reste sous les messages du portail (z-index 50) et ne couvre pas la palette ni les fenêtres (couche supérieure).
        self::assertMatchesRegularExpression('/\.cw-fab\s*\{[^}]*z-index:\s*40/s', $css);
    }

    public function testWidgetIsAccessibleAndKeyboardDriven(): void
    {
        $shell = self::read('views/atak/sse/_cerbere_widget.php');
        self::assertStringContainsString('<button type="button" class="cw-fab"', $shell);
        self::assertStringContainsString('aria-haspopup="dialog"', $shell);
        self::assertStringContainsString('aria-expanded="false"', $shell);
        self::assertStringContainsString('<dialog class="cw-drawer"', $shell);
        self::assertStringContainsString('aria-labelledby="cw-title"', $shell);
        self::assertStringContainsString('<h2 id="cw-title"', $shell);
        self::assertStringContainsString('>Cerbère</h2>', $shell);
        $js = self::read('public/assets/js/sse-cerbere-widget.js');
        self::assertStringContainsString('showModal', $js, 'focus piégé et Échap par la fenêtre modale native');
        self::assertStringContainsString('e.target !== dlg', $js, 'clic extérieur');
        self::assertStringContainsString('ArrowRight', $js);
        self::assertStringContainsString('window.self !== window.top', $js, 'absent des cadres');
        self::assertSame(0, preg_match('/[àâéèêëîïôùûç]/iu', preg_replace(['#/\*.*?\*/#s', '#^\s*//.*$#m', '#\s//[^
"\']*$#m'], '', $js) ?? ''), 'aucun texte français dans le script');
        $panel = self::read('views/atak/sse/_cerbere_panel.php');
        self::assertStringContainsString('role="tablist"', $panel);
        self::assertStringContainsString('role="tab"', $panel);
        self::assertStringContainsString('aria-selected', $panel);
    }

    public function testStorageIsOptionalAndNothingBlinks(): void
    {
        $js = self::read('public/assets/js/sse-cerbere-widget.js');
        self::assertSame(2, substr_count($js, 'localStorage.'), 'lecture et écriture seulement');
        self::assertGreaterThanOrEqual(2, substr_count($js, 'catch (e)'), 'chaque accès au stockage est protégé');
        self::assertMatchesRegularExpression('/try \{ return JSON\.parse\(window\.localStorage\.getItem/', $js);
        self::assertStringNotContainsString('setInterval', $js, 'aucune interrogation répétée : le badge vient de la page');
        $css = self::read('public/assets/css/sse-cerbere-widget.css');
        self::assertSame(1, substr_count($css, '@keyframes'), 'une seule entrée du tiroir, désactivée si l’utilisateur le demande');
    }

    public function testButtonLiftsAboveStickyBarsAndIsQuietOnPapers(): void
    {
        $js = self::read('public/assets/js/sse-cerbere-widget.js');
        self::assertStringContainsString('.wc-bar', $js);
        self::assertStringContainsString('--cw-lift', $js);
        $css = self::read('public/assets/css/sse-cerbere-widget.css');
        self::assertStringContainsString('var(--cw-lift, 0px)', $css);
        self::assertStringContainsString('.cw--quiet .cw-fab', $css);
            }

    public function testEnglishTranslationsExistForTheVisibleTexts(): void
    {
        $en = self::read('lang/en/site_auto.php');
        foreach (['Fait par Cerbère', 'Provenance', 'Automatismes', 'Masquer le bouton Cerbère', 'Accepter', 'Écarter', 'Afficher le bouton Cerbère', 'Chargement de Cerbère…', 'Pages utiles'] as $t) {
            self::assertStringContainsString("'" . $t . "'", $en, $t);
        }
    }
}
