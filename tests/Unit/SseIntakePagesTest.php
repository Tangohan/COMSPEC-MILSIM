<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\AdminIntelIntakeController;
use App\Controllers\Web\SseIntakeController;
use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\DiscordBotRepository;
use App\Services\Intel\IntelIntakeAdvisor;
use App\Services\Intel\IntelIntakeConversion;
use App\Services\Intel\IntelIntakeDesk;
use App\Services\Intel\IntelIntakeDiscord;
use App\Services\Intel\IntelIntakeService;
use App\Services\Intel\IntelPlayerFeedService;
use App\Services\Sse\Attach\SseAttachWindow;
use App\Services\Sse\SseTriageInbox;
use App\Support\SsePortalNav;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\ArraySseLinkViewer;
use Tests\Support\IntelIntakeSqlite;

/**
 * Remontées passées du back-office au bureau SSE, sur une base sqlite réelle : anciennes adresses redirigées (statut et
 * cible), page claire sans entrée au bureau, rendu de la file et du fil dans la coque du portail, actions POST (mêmes
 * champs, jeton, droits, retour, journal sse_audit_log), pièces floutées, réglages Discord, menu et badge, liens internes.
 */
final class SseIntakePagesTest extends TestCase
{
    private const ALL = ['treat' => true, 'manage' => true, 'redact' => true, 'steam' => true, 'convert' => true, 'link' => true, 'discord' => true];
    private const TREAT = ['treat' => true, 'discord' => true, 'convert' => true, 'link' => true];

    private PDO $pdo;
    /** @var array{a: int, b: int, cr: int, case: int, me: int, analyst: int} */
    private array $ids;
    private int $now;
    private ?bool $sessionStarted = null;
    /** @var array<string, mixed> */
    private array $savedSession = [];
    /** @var list<array<string, mixed>> */
    private array $audit = [];

    protected function setUp(): void
    {
        $prop = new \ReflectionProperty(Session::class, 'started');
        $this->sessionStarted = $prop->getValue();
        $prop->setValue(null, true);
        $this->savedSession = $_SESSION ?? [];
        $_SESSION = ['_csrf_token' => str_repeat('a', 64), 'tenant_id' => 1, 'user_id' => 7, 'callsign' => 'TA1'];
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/atak/sse/remontees';
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        $GLOBALS['__app_locale'] = 'fr';
        $this->now = time();
        $this->pdo = IntelIntakeSqlite::open();
        $this->ids = IntelIntakeSqlite::seed($this->pdo, $this->now);
        $this->audit = [];
        SseAttachWindow::useViewer(ArraySseLinkViewer::full());
    }

    protected function tearDown(): void
    {
        SseAttachWindow::useViewer(null);
        IntelIntakeSqlite::close();
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->sessionStarted);
        $_SESSION = $this->savedSession;
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($GLOBALS['__app_locale'], $_SERVER['HTTP_X_REQUESTED_WITH']);
        Gate::reset();
    }

    private function intake(): IntelIntakeService
    {
        return new IntelIntakeService($this->pdo);
    }

    private function desk(): IntelIntakeDesk
    {
        $intake = $this->intake();

        return new IntelIntakeDesk(
            $intake,
            new IntelIntakeAdvisor($this->pdo),
            new IntelIntakeConversion($intake),
            new IntelIntakeDiscord($this->pdo, new DiscordBotRepository($this->pdo), $intake, static fn (string $p): string => 'https://athena.test/' . $p),
            ArraySseLinkViewer::full(),
            static function (): void {
            },
        );
    }

    /** @param array<string, bool> $rights */
    private function sse(array $rights = self::ALL, bool $canEditSettings = true): SseIntakeController
    {
        return new SseIntakeController(
            $this->desk(),
            null,
            null,
            new IntelPlayerFeedService($this->pdo),
            static fn (): array => $rights,
            function (array $entry): void {
                $this->audit[] = $entry;
            },
            static fn (): bool => $canEditSettings,
            new IntelIntakeDiscord($this->pdo, new DiscordBotRepository($this->pdo), $this->intake()),
            new DiscordBotRepository($this->pdo),
            'tres_restreint',
        );
    }

    /** @param array<string, mixed> $get */
    private static function get(array $get = [], string $uri = '/atak/sse/remontees'): Request
    {
        $_GET = $get;
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $uri . ($get !== [] ? '?' . http_build_query($get) : '');

        return new Request();
    }

    /** @param array<string, mixed> $post */
    private static function post(array $post, bool $withToken = true): Request
    {
        $_GET = [];
        $_POST = $post + ($withToken ? ['_csrf_token' => Csrf::token()] : []);
        $_SERVER['REQUEST_METHOD'] = 'POST';

        return new Request();
    }

    private static function body(Response $r): string
    {
        return str_replace("\r\n", "\n", $r->body());
    }

    /** Contenu propre à la page (sans la coque du portail). */
    private static function own(string $html, string $root): string
    {
        $start = strpos($html, $root);
        self::assertNotFalse($start, 'page rendue : ' . $root);
        $ends = array_filter([strpos($html, 'id="ra-window"', (int) $start), strpos($html, '</main>', (int) $start)], static fn ($p): bool => $p !== false);

        return substr($html, (int) $start, ($ends === [] ? strlen($html) : min($ends)) - (int) $start);
    }

    private static function flash(string $kind): string
    {
        return (string) ($_SESSION['_flash'][$kind] ?? $_SESSION['flash'][$kind] ?? Session::getFlash($kind) ?? '');
    }

    // ------------------------------------------------------------------ anciennes adresses du back-office

    public function testEveryOldAddressRedirectsToItsSseEquivalentWithTheSameId(): void
    {
        $bo = new AdminIntelIntakeController(static fn (): bool => true);
        $cases = [
            ['index', [], ['etat' => 'closed', 'source' => 'cr', 'pastille' => 'retard'], 'atak/sse/remontees?etat=closed&source=cr&pastille=retard'],
            ['players', [], ['periode' => '7j'], 'atak/sse/remontees/joueurs?periode=7j'],
            ['player', ['key' => 'u-8'], [], 'atak/sse/remontees/joueurs/u-8'],
            ['show', ['source' => 'fiche', 'id' => '5'], [], 'atak/sse/remontees/fiche/5'],
            ['show', ['source' => 'cr', 'id' => (string) $this->ids['cr']], [], 'atak/sse/remontees/cr/' . $this->ids['cr']],
            ['suggestions', ['source' => 'fiche', 'id' => '5'], ['genre' => 'doublon', 'q' => 'FR'], 'atak/sse/remontees/fiche/5/suggestions?genre=doublon&q=FR'],
        ];
        foreach ($cases as [$method, $params, $get, $target]) {
            $res = $bo->{$method}(self::get($get, '/back-office/remontees'), $params);
            self::assertSame(302, $res->statusCode(), $method);
            self::assertSame(url($target), $res->headerValue('Location'), $method);
        }
        // Un formulaire resté ouvert sur l'ancienne page : 307, même corps, mêmes noms de champs.
        $res = $bo->action(self::post(['state' => 'en_cours']), ['source' => 'fiche', 'id' => '5', 'action' => 'state']);
        self::assertSame(307, $res->statusCode());
        self::assertSame(url('atak/sse/remontees/fiche/5/state'), $res->headerValue('Location'));
        // Valeurs non scalaires écartées de l'adresse.
        self::assertSame(url('atak/sse/remontees?q=x'), AdminIntelIntakeController::target('atak/sse/remontees', ['q' => 'x', 'z' => ['a'], 'v' => '']));
    }

    public function testWithoutSseEntryTheOldAddressSaysWhyInsteadOfAnEmptyPage(): void
    {
        $bo = new AdminIntelIntakeController(static fn (): bool => false);
        foreach (['index' => [], 'show' => ['source' => 'fiche', 'id' => '5']] as $method => $params) {
            $res = $bo->{$method}(self::get(), $params);
            self::assertSame(403, $res->statusCode(), $method);
            self::assertNull($res->headerValue('Location'), $method);
        }
        $view = (string) file_get_contents(base_path('views/admin/intel/intake_moved.php'));
        self::assertStringContainsString('Accès au bureau SSE requis', $view);
        self::assertStringContainsString("url('atak/sse')", $view);
        self::assertStringNotContainsString('<h1', $view, 'le titre vient du shell du back-office');
        // Sans session : la connexion d'abord.
        unset($_SESSION['user_id']);
        $res = $bo->index(self::get(), []);
        self::assertSame(url('login'), $res->headerValue('Location'));
    }

    public function testRightsMatrixKeepsWhatTheBackOfficeGranted(): void
    {
        // Session invitée ou sans compte : rien.
        self::assertSame(IntelIntakeDesk::rights([]), SseIntakeController::sessionRights(true, 1));
        Gate::reset();
        Gate::getInstance()->setPermissions([]);
        self::assertFalse(SseIntakeController::sessionRights(false, 1, 'tres_restreint')['treat'], 'sans droit, pas de traitement');
        // Ancien droit du back-office (administrateur de la communauté) : tout, y compris gérer et l'identifiant Steam.
        Gate::reset();
        Gate::getInstance()->setPermissions(['admin.organization']);
        $admin = SseIntakeController::sessionRights(false, 1, 'tres_restreint');
        self::assertSame(self::ALL, $admin);
        self::assertFalse(SseIntakeController::sessionRights(false, 1, 'interne')['redact'], 'caviarder demande la plus haute habilitation');
        // Responsable SSE (gestion des dossiers) : tout sauf l'identifiant Steam, réservé aux administrateurs.
        Gate::reset();
        Gate::getInstance()->setPermissions(['atak.sse.case.manage']);
        $sse = SseIntakeController::sessionRights(false, 1, 'tres_restreint');
        self::assertTrue($sse['treat'] && $sse['manage'] && $sse['convert'] && $sse['link'] && $sse['discord']);
        self::assertFalse($sse['steam']);
    }

    // ------------------------------------------------------------------ pages du bureau

    public function testListLivesInThePortalShellWithKeyFiguresFiltersAndSort(): void
    {
        $res = $this->sse()->index(self::get(['etat' => 'all', 'tri' => 'emetteur']), []);
        self::assertSame(200, $res->statusCode());
        $html = self::body($res);
        self::assertStringContainsString('<!DOCTYPE html>', $html, 'la vraie coque du portail');
        $page = self::own($html, '<div class="rmt rmt--sse rmt-list"');
        self::assertMatchesRegularExpression('#<span class="fng-strip__index" aria-hidden="true">R\.02</span>\s*<div>\s*<h1 class="fng-title">Remontées</h1>\s*<p class="fng-strip__desc">#', $page);
        self::assertMatchesRegularExpression('#<p class="fng-key"><strong>\d+</strong><span>à traiter</span></p>#', $page);
        foreach (['en retard', 'traitées cette semaine', 'ouvertes', 'attribuées à moi'] as $figure) {
            self::assertStringContainsString('<dt>' . $figure . '</dt>', $page);
        }
        self::assertStringContainsString('aria-sort="descending"', $page);
        self::assertStringContainsString('name="source"', $page);
        self::assertStringContainsString('name="attribue"', $page);
        self::assertStringContainsString('name="type"', $page);
        self::assertStringContainsString('<option value="deleted"', $page, 'un gestionnaire voit les supprimées');
        self::assertStringContainsString('href="' . url('atak/sse/remontees/fiche/' . $this->ids['b']) . '"', $page);
        self::assertStringContainsString('href="' . url('atak/sse/remontees/cr/' . $this->ids['cr']) . '"', $page);
        self::assertStringNotContainsString('back-office/remontees', $page);
        self::assertStringNotContainsString('76561198', $page);
        self::assertStringNotContainsString('sse-ds', $html);
        self::assertStringNotContainsString('kicker', $page);
        // Sans le droit de gérer : pas d'onglet « Supprimées », et l'adresse ne les montre pas.
        $page = self::own(self::body($this->sse(self::TREAT)->index(self::get(['etat' => 'deleted']), [])), '<div class="rmt rmt--sse rmt-list"');
        self::assertStringNotContainsString('<option value="deleted"', $page);
        // Sans droit de traitement : renvoyé vers les fiches avec une explication.
        $denied = $this->sse([])->index(self::get(), []);
        self::assertSame(url('atak/sse/fiches'), $denied->headerValue('Location'));
    }

    public function testKeyFiguresCountTodoLateAndTreatedThisWeek(): void
    {
        $ctrl = $this->sse();
        $counts = $this->intake()->counts(1, [], 7);
        $before = $ctrl->keyFigures(1, 7, $counts, $this->now);
        self::assertSame((int) $counts['states']['a_traiter'], $before['todo']);
        self::assertGreaterThanOrEqual(1, $before['late']);
        self::assertTrue($this->desk()->perform(1, 'fiche', $this->ids['b'], 'state', ['state' => 'close'], ['id' => 7, 'label' => 'TA1'], self::ALL, 'tres_restreint')['ok']);
        $after = $ctrl->keyFigures(1, 7, $this->intake()->counts(1, [], 7), $this->now + 5);
        self::assertSame($before['week'] + 1, $after['week'], 'close à l’instant : traitée cette semaine');
        self::assertSame(0, $this->intake()->closedSince(2, '2000-01-01 00:00:00'), 'jamais une autre communauté');
    }

    public function testDetailPageServesBothSourcesWithTheirIdAndClassificationBand(): void
    {
        $html = self::body($this->sse()->show(self::get([], '/atak/sse/remontees/fiche/' . $this->ids['b']), ['source' => 'fiche', 'id' => (string) $this->ids['b']]));
        $page = self::own($html, '<div class="rmt rmt--sse rmt-show"');
        self::assertStringContainsString('FR-2026-000041', $page);
        self::assertMatchesRegularExpression('#<p class="rmt-band rmt-band--[a-z_]+">[A-Z/ ]+ · [^<]+</p>#', $page);
        self::assertSame(5, preg_match_all('~role="tab"~', $page));
        self::assertStringContainsString('action="' . url('atak/sse/remontees/fiche/' . $this->ids['b'] . '/take') . '"', $page);
        self::assertStringContainsString('href="' . url('atak/sse/fiches/' . $this->ids['b']) . '"', $page, 'la fiche elle-même reste à un clic');
        self::assertStringContainsString('data-ra-open data-ra-pieces="note:' . $this->ids['b'] . '"', $page);
        self::assertStringContainsString('id="ra-window"', $html, 'fenêtre « Rattacher » rendue une fois');
        self::assertStringNotContainsString('rmt-crumbs', $page, 'le fil d’Ariane est celui du portail');
        // Le fil d'Ariane du portail nomme la page : Bureau SSE › Renseignement et enquêtes › Remontées › référence.
        self::assertMatchesRegularExpression('#<nav class="sx-crumbs"[\s\S]*?Remontées</a>[\s\S]*?aria-current="page">FR-2026-000041<#', $html);
        $read = array_column($this->intake()->reads(1, 'fiche', $this->ids['b']), 'channel');
        self::assertContains('web', $read, 'lecture notée côté portail');

        $cr = self::own(self::body($this->sse()->show(self::get(), ['source' => 'cr', 'id' => (string) $this->ids['cr']])), '<div class="rmt rmt--sse rmt-show"');
        self::assertStringContainsString('SPOT-2026-0117', $cr);
        self::assertStringNotContainsString('data-ra-open', $cr, 'un compte rendu ne se rattache pas : Doublon, Transmettre ou Convertir');
        // Inconnue ou d'une autre source : retour à la file.
        self::assertSame(url('atak/sse/remontees'), $this->sse()->show(self::get(), ['source' => 'x', 'id' => '1'])->headerValue('Location'));
        self::assertSame(url('atak/sse/remontees'), $this->sse()->show(self::get(), ['source' => 'fiche', 'id' => '999999'])->headerValue('Location'));
    }

    public function testDeletedReportIsOnlyReadableByWhoCanRestoreIt(): void
    {
        self::assertTrue($this->desk()->perform(1, 'fiche', $this->ids['a'], 'delete', ['reason' => 'Essai', 'confirm' => 'FR-2026-000002'], ['id' => 7, 'label' => 'TA1'], self::ALL, 'tres_restreint')['ok']);
        $params = ['source' => 'fiche', 'id' => (string) $this->ids['a']];
        self::assertSame(url('atak/sse/remontees'), $this->sse(self::TREAT)->show(self::get(), $params)->headerValue('Location'));
        $page = self::own(self::body($this->sse()->show(self::get(), $params)), '<div class="rmt rmt--sse rmt-show"');
        self::assertStringContainsString('/restore"', $page);
    }

    public function testBlurredAttachmentsKeepTheirAddressFromReadersWhoCannotManage(): void
    {
        $this->pdo->exec("UPDATE sse_field_note_attachments SET file_path = 'intel/pont-secret.jpg', blurred = 1 WHERE note_id = " . $this->ids['b'] . " AND kind = 'photo'");
        $params = ['source' => 'fiche', 'id' => (string) $this->ids['b']];
        $treat = self::own(self::body($this->sse(self::TREAT)->show(self::get(), $params)), '<div class="rmt rmt--sse rmt-show"');
        self::assertStringNotContainsString('pont-secret.jpg', $treat);
        self::assertStringContainsString('Pièce floutée', $treat);
        self::assertStringNotContainsString('/blur"', $treat, 'flouter est réservé à la gestion');
        $manager = self::own(self::body($this->sse()->show(self::get(), $params)), '<div class="rmt rmt--sse rmt-show"');
        self::assertStringContainsString('/blur"', $manager);
        self::assertStringContainsString('Retirer le flou', $manager);
    }

    public function testActionsKeepTheirFieldNamesCheckTokenAndRightsAndAreJournaled(): void
    {
        $params = static fn (int $id, string $action, string $source = 'fiche'): array => ['source' => $source, 'id' => (string) $id, 'action' => $action];
        // Sans jeton : rien ne bouge.
        $res = $this->sse()->action(self::post([], false), $params($this->ids['b'], 'take'));
        self::assertSame(url('atak/sse/remontees/fiche/' . $this->ids['b']), $res->headerValue('Location'));
        self::assertSame('a_traiter', $this->intake()->find(1, 'fiche', $this->ids['b'])['state']);
        self::assertSame([], $this->audit);

        // Prendre en compte, puis un commentaire (#fil-fin) : journal du bureau, jamais le texte.
        $res = $this->sse()->action(self::post([]), $params($this->ids['b'], 'take'));
        self::assertSame(url('atak/sse/remontees/fiche/' . $this->ids['b']), $res->headerValue('Location'));
        self::assertSame('prise_en_compte', $this->intake()->find(1, 'fiche', $this->ids['b'])['state']);
        $res = $this->sse()->action(self::post(['body' => 'Recoupement fait avec Karimi.']), $params($this->ids['b'], 'comment'));
        self::assertSame(url('atak/sse/remontees/fiche/' . $this->ids['b']) . '#fil-fin', $res->headerValue('Location'));
        self::assertSame(['intake.take', 'intake.comment'], array_column($this->audit, 'action'));
        self::assertSame(['field_note', $this->ids['b'], 'FR-2026-000041'], [$this->audit[0]['object_type'], $this->audit[0]['object_id'], $this->audit[0]['reason']]);
        self::assertStringNotContainsString('Karimi', (string) json_encode($this->audit, JSON_UNESCAPED_UNICODE));

        // Depuis l'onglet « Suivi » d'une fiche : retour sur la fiche.
        $res = $this->sse()->action(self::post(['state' => 'en_cours', 'retour' => 'fiche']), $params($this->ids['b'], 'state'));
        self::assertSame(url('atak/sse/fiches/' . $this->ids['b']) . '#suivi', $res->headerValue('Location'));
        self::assertSame('en_cours', $this->intake()->find(1, 'fiche', $this->ids['b'])['state']);

        // Traiter sans gérer : supprimer et modifier refusés, sans trace au journal.
        $count = count($this->audit);
        $this->sse(self::TREAT)->action(self::post(['reason' => 'x', 'confirm' => 'FR-2026-000041']), $params($this->ids['b'], 'delete'));
        $this->sse(self::TREAT)->action(self::post(['title' => 'Autre']), $params($this->ids['b'], 'edit'));
        self::assertFalse($this->intake()->find(1, 'fiche', $this->ids['b'])['deleted']);
        self::assertCount($count, $this->audit);

        // Gérer : supprimer renvoie à la file ; restaurer revient sur la remontée.
        $res = $this->sse()->action(self::post(['reason' => 'Essai', 'confirm' => 'FR-2026-000041']), $params($this->ids['b'], 'delete'));
        self::assertSame(url('atak/sse/remontees'), $res->headerValue('Location'));
        self::assertTrue($this->intake()->find(1, 'fiche', $this->ids['b'])['deleted']);
        $this->sse()->action(self::post([]), $params($this->ids['b'], 'restore'));
        self::assertFalse($this->intake()->find(1, 'fiche', $this->ids['b'])['deleted']);

        // Identifiant Steam demandé par le script de la page : réponse JSON, consultation notée.
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'fetch';
        $json = json_decode($this->sse()->action(self::post(['mode' => 'copy']), $params($this->ids['b'], 'steam'))->body(), true);
        self::assertSame([true, '76561198000117733'], [$json['ok'], $json['steam']]);
        $denied = $this->sse(self::TREAT)->action(self::post([]), $params($this->ids['b'], 'steam'));
        self::assertSame(403, $denied->statusCode());
        self::assertStringNotContainsString('76561198', $denied->body());
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);

        // Action inconnue : refusée.
        $res = $this->sse()->action(self::post([]), $params($this->ids['b'], 'inconnue'));
        self::assertSame(url('atak/sse/remontees/fiche/' . $this->ids['b']), $res->headerValue('Location'));
        self::assertSame(['intake.take', 'intake.comment', 'intake.state', 'intake.delete', 'intake.restore', 'intake.steam'], array_column($this->audit, 'action'));
    }

    public function testSuggestionsStayInTheCommunityAndFollowRights(): void
    {
        $res = $this->sse()->suggestions(self::get(['genre' => 'doublon', 'q' => 'FR-2026']), ['source' => 'fiche', 'id' => (string) $this->ids['b']]);
        self::assertSame(['fiche/' . $this->ids['a']], array_column(json_decode($res->body(), true)['items'], 'value'));
        self::assertSame(403, $this->sse([])->suggestions(self::get(), ['source' => 'fiche', 'id' => (string) $this->ids['b']])->statusCode());
    }

    public function testPlayersPagesMovedIntoThePortal(): void
    {
        $html = self::body($this->sse()->players(self::get(['periode' => 'tout'], '/atak/sse/remontees/joueurs'), []));
        $page = self::own($html, '<div class="rmj rmj--sse"');
        self::assertStringContainsString('Remontées par joueur', $page);
        self::assertStringContainsString('class="rmt__view is-active" aria-current="page"', $page);
        self::assertStringContainsString('href="' . url('atak/sse/remontees/reglages') . '"', $page);
        self::assertStringNotContainsString('back-office/remontees', $page);
        // Joueur inconnu : retour au tableau.
        self::assertSame(url('atak/sse/remontees/joueurs'), $this->sse()->player(self::get(), ['key' => 'zz'])->headerValue('Location'));
    }

    public function testSettingsShowDelaysStatesAndOnlyAdminsChangeDiscord(): void
    {
        IntelIntakeSqlite::linkDiscord($this->pdo, $this->now);
        $html = self::body($this->sse()->settings(self::get([], '/atak/sse/remontees/reglages'), []));
        $page = self::own($html, '<div class="rmt rmt--sse rmt-settings"');
        self::assertSame(3, preg_match_all('~<h2 id="rmt-set-[a-z]+" class="rmt-h2">[^<]+</h2>\s*<p class="rmt-desc">~', $page), 'trois sections, une ligne de description chacune');
        self::assertStringContainsString('15 min', $page);
        self::assertStringContainsString('<dialog class="rmt-dialog" id="rmt-dlg-discord-set"', $page);
        self::assertStringContainsString('name="intake_channel_id"', $page);
        self::assertSame(preg_match_all('~<form\b[^>]*method="post"~', $page), preg_match_all('~name="_csrf_token"~', $page));
        $readOnly = self::own(self::body($this->sse(self::ALL, false)->settings(self::get(), [])), '<div class="rmt rmt--sse rmt-settings"');
        self::assertStringNotContainsString('<dialog', $readOnly);
        self::assertStringContainsString('réservé aux administrateurs de la communauté', $readOnly);

        // Mêmes champs que l'ancien formulaire : enregistré par le même service.
        $fields = ['intake_enabled' => '1', 'intake_channel_id' => '800000000000000001', 'intake_role_id' => '700000000000000001', 'intake_events' => ['new', 'late']];
        $refused = $this->sse(self::ALL, false)->settingsSave(self::post($fields), []);
        self::assertSame(url('atak/sse/remontees/reglages') . '#discord', $refused->headerValue('Location'));
        self::assertFalse((new IntelIntakeDiscord($this->pdo, new DiscordBotRepository($this->pdo)))->settings(1)['enabled']);
        $this->sse()->settingsSave(self::post($fields), []);
        $saved = (new IntelIntakeDiscord($this->pdo, new DiscordBotRepository($this->pdo)))->settings(1);
        self::assertSame([true, '800000000000000001', ['new', 'late']], [$saved['enabled'], $saved['channel_id'], $saved['events']]);
        self::assertSame('intake.settings', end($this->audit)['action']);
        // Sans jeton : rien.
        $this->sse()->settingsSave(self::post(['intake_enabled' => ''], false), []);
        self::assertTrue((new IntelIntakeDiscord($this->pdo, new DiscordBotRepository($this->pdo)))->settings(1)['enabled']);
    }

    // ------------------------------------------------------------------ menu, badge, liens

    public function testNavigationHasTheEntriesActiveStateAndBreadcrumb(): void
    {
        $sections = SsePortalNav::sections(true, true, false, true, true);
        $ids = [];
        foreach ($sections as $s) {
            foreach ($s['items'] as $i) {
                $ids[$i['id']] = $i['path'];
            }
        }
        self::assertSame('atak/sse/remontees', $ids['remontees']);
        self::assertSame('atak/sse/remontees/joueurs', $ids['remontees-joueurs']);
        self::assertSame('atak/sse/remontees/reglages', $ids['remontees-reglages']);
        self::assertSame('remontees', SsePortalNav::activeId($sections, '/public/atak/sse/remontees/fiche/5', 'fiches'), 'l’URL l’emporte sur activeNav');
        self::assertSame('remontees-joueurs', SsePortalNav::activeId($sections, '/atak/sse/remontees/joueurs/u-8'));
        self::assertSame('remontees-reglages', SsePortalNav::activeId($sections, '/atak/sse/remontees/reglages'));
        $crumbs = SsePortalNav::breadcrumb($sections, 'remontees', '/atak/sse/remontees/cr/3', 'SPOT-2026-0117', static fn (string $p): string => '/' . $p);
        self::assertSame(['Bureau SSE', 'Renseignement et enquêtes', 'Remontées', 'SPOT-2026-0117'], array_column($crumbs, 'label'));
        self::assertSame('/atak/sse/remontees', $crumbs[2]['href']);
        // Le badge « à traiter » de l'entrée vient de la boîte À trier, file « remontees ».
        $defs = array_column(SseTriageInbox::defaultDefinitions(7), null, 'key');
        self::assertSame(['remontees', 'atak/sse/remontees'], [$defs['remontees']['nav'], $defs['remontees']['path']]);
        // Les contrôleurs ne forcent plus « fiches » comme entrée active.
        self::assertStringNotContainsString("'activeNav' => 'fiches'", (string) file_get_contents(base_path('app/Controllers/Web/SseIntakeController.php')));
    }

    public function testNoInternalLinkStillPointsToTheBackOfficeRemontees(): void
    {
        $root = base_path();
        $hits = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            if (!$file->isFile() || !preg_match('~^(app|views|config|bots|public/assets/js)/~', $rel) || !preg_match('~\.(php|js|py)$~', $rel)) {
                continue;
            }
            if ($rel === 'app/Controllers/Admin/AdminIntelIntakeController.php') {
                continue; // les redirections elles-mêmes
            }
            foreach (preg_split('/\R/', (string) file_get_contents($file->getPathname())) ?: [] as $n => $line) {
                if (!str_contains($line, 'back-office/remontees') || preg_match('~^\s*(\*|//|#)~', $line)) {
                    continue;
                }
                // L'en-tête de l'ancienne page (page « accès au bureau SSE requis ») n'est pas un lien.
                if ($rel === 'config/back_office_pages.php' && str_contains($line, "'group' => 'Renseignement'")) {
                    continue;
                }
                $hits[] = $rel . ':' . ($n + 1);
            }
        }
        self::assertSame([], $hits, 'Lien interne vers l’ancien back-office Remontées');
        // Les messages Discord et le fil d'un joueur mènent au bureau.
        self::assertStringContainsString("\$this->url('atak/sse/remontees/' . \$source", (string) file_get_contents(base_path('app/Services/Intel/IntelIntakeDiscord.php')));
        self::assertStringContainsString("'atak/sse/remontees/fiche/' . \$id", (string) file_get_contents(base_path('app/Services/Intel/IntelPlayerFeedService.php')));
    }
}
