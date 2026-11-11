<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\OperatorHubController;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\OperatorHubRepository;
use App\Repositories\PlatformAlertRepository;
use App\Repositories\SseCoverExportRepository;
use App\Repositories\TenantAlertRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserAlertDismissalRepository;
use App\Services\Alerts\AlertPresentationService;
use App\Services\Operator\OperatorHubCatalog;
use App\Services\Operator\OperatorIdentityService;
use App\Services\Security\FileRateLimiter;
use App\Services\Sse\SseCoverExportService;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseIdentitySqlite;

/**
 * Outils opérateur : outils visibles selon les droits (aucun lien mort), favoris, tuile du menu Espaces, routes
 * protégées, écritures (jeton, propriété), rendu des pages, annonce « Nouveau : vos outils opérateur ».
 */
final class OperatorHubTest extends TestCase
{
    private ?PDO $pdo = null;
    /** @var list<array{0: string, 1: string}> */
    private array $flashes = [];
    /** @var array<string, mixed> */
    private array $rendered = [];

    protected function tearDown(): void
    {
        if ($this->pdo !== null) {
            SseIdentitySqlite::close();
            $this->pdo = null;
        }
        $_POST = $_GET = $_FILES = [];
        Session::forgetMany(['user_id', 'tenant_id']);
        (new \ReflectionProperty(AlertPresentationService::class, 'cachedForRequest'))->setValue(null, null);
    }

    private function db(): PDO
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = SseIdentitySqlite::open();
        $root = dirname(__DIR__, 2);
        (require $root . '/bootstrap/sse_cover_exports_migration.php')($this->pdo);
        (require $root . '/bootstrap/sse_files_records_migration.php')($this->pdo);
        (require $root . '/bootstrap/operator_hub_migration.php')($this->pdo);
        $this->pdo->exec("INSERT INTO users (id, tenant_id, steam_id, display_name) VALUES (10, 1, '76561198000000010', 'Jake'), (11, 1, NULL, 'Autre')");

        return $this->pdo;
    }

    /**
     * @param list<string> $permissions
     * @param array<string, string> $post
     */
    private function controller(?int $user = 10, array $permissions = [], bool $csrf = true, int $tenant = 1): OperatorHubController
    {
        $exports = new SseCoverExportRepository();
        $limiter = $this->createMock(FileRateLimiter::class);
        $limiter->method('tooManyAttempts')->willReturn(false);

        return new OperatorHubController(
            new OperatorHubRepository(),
            new OperatorIdentityService(null, null, null, null, null, null, $exports, new SseCoverExportService($exports, null, null, static function (): void {
            }, 'test-key'), static function (): void {
            }),
            static fn (): ?array => $user === null ? null : ['tenant' => $tenant, 'user' => $user, 'row' => ['callsign' => 'Viper 1-1', 'display_name' => 'Jake', 'steam_id' => '76561198000000010']],
            static fn (string $p): bool => in_array($p, $permissions, true),
            static fn (string $path): bool => true,
            function (array $data): Response {
                $this->rendered = $data;

                return (new Response())->setBody('rendu');
            },
            static fn (Request $r): bool => $csrf,
            function (string $type, string $message): void {
                $this->flashes[] = [$type, $message];
            },
            $limiter,
            static fn (int $t, int $u): array => [['status' => 'active', 'last_seen_at' => '2026-11-09 20:15:00'], ['status' => 'revoked', 'last_seen_at' => '2026-11-10 08:00:00']],
        );
    }

    /** @param array<string, string> $post */
    private function post(array $post = []): Request
    {
        $_POST = $post;
        $_SERVER['REQUEST_METHOD'] = 'POST';

        return new Request();
    }

    // ------------------------------------------------------------------ Liste des outils

    public function testToolsFollowPermissionsWithNoDeadLink(): void
    {
        $all = static fn (string $p): bool => true;
        $none = static fn (string $p): bool => false;
        $path = static fn (string $p): bool => true;
        $noRoute = static fn (string $r): bool => false;

        $member = array_column(OperatorHubCatalog::visible($none, $path, $noRoute), 'key');
        foreach (['atak', 'lier', 'terrain', 'identites', 'documents', 'couverture', 'materiel', 'kits', 'manoeuvres', 'guides', 'academy'] as $k) {
            self::assertContains($k, $member, $k);
        }
        // Sans droit du Bureau SSE ni du poste TOC : ni fichiers, ni SEEK, ni fiches, ni TOC.
        foreach (['fichiers', 'seek', 'telephones', 'photos', 'fiche', 'fiches', 'guide_sse', 'toc', 'plans'] as $k) {
            self::assertNotContains($k, $member, $k);
        }

        $sse = array_column(OperatorHubCatalog::visible(static fn (string $p): bool => $p === 'atak.sse.access', $path, $noRoute), 'key');
        self::assertContains('fichiers', $sse);
        self::assertContains('fiche', $sse);
        self::assertNotContains('toc', $sse);

        // TOC et Plans : seulement quand leurs routes existent (branche du poste TOC web) et avec le droit.
        self::assertNotContains('toc', array_column(OperatorHubCatalog::visible($all, $path, $noRoute), 'key'));
        $withToc = array_column(OperatorHubCatalog::visible($all, $path, static fn (string $r): bool => true), 'key');
        self::assertContains('toc', $withToc);
        self::assertContains('plans', $withToc);
        self::assertNotContains('toc', array_column(OperatorHubCatalog::visible($none, $path, static fn (string $r): bool => true), 'key'));

        // Module fermé à la communauté (profil, sanction) : l'outil disparaît.
        $noAtak = array_column(OperatorHubCatalog::visible($all, static fn (string $p): bool => !str_starts_with($p, 'atak'), $noRoute), 'key');
        self::assertNotContains('atak', $noAtak);
        self::assertNotContains('fichiers', $noAtak);
        self::assertContains('identites', $noAtak);
        self::assertContains('materiel', $noAtak, 'le chemin est testé sans sa chaîne de requête');
    }

    public function testCatalogIsDataEveryToolHasAGroupAnIconAndADeclaredRoute(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        $icons = (string) file_get_contents(dirname(__DIR__, 2) . '/views/operateur/_icons.php');
        $keys = [];
        foreach (OperatorHubCatalog::TOOLS as $tool) {
            foreach (['key', 'group', 'label', 'desc', 'path', 'icon'] as $field) {
                self::assertNotSame('', (string) ($tool[$field] ?? ''), $tool['key'] . '.' . $field);
            }
            self::assertArrayHasKey($tool['group'], OperatorHubCatalog::GROUPS, $tool['key']);
            self::assertStringContainsString("'" . $tool['icon'] . "' =>", $icons, 'icône ' . $tool['icon']);
            self::assertLessThanOrEqual(48, strlen($tool['key']));
            $keys[] = $tool['key'];
            if (!isset($tool['route'])) {
                // Les espaces de l'Academy sont servis par une route à paramètre.
                $route = $tool['path'] === 'academy/courses' ? '/academy/{space}' : '/' . strtok($tool['path'], '?');
                self::assertStringContainsString("'" . $route . "'", $routes, 'route de ' . $tool['key']);
            }
        }
        self::assertSame($keys, array_unique($keys), 'clés uniques (favoris)');
        self::assertTrue(OperatorHubCatalog::routeDeclared('operateur/identite'));
        self::assertFalse(OperatorHubCatalog::routeDeclared('operateur/inconnu'));
        self::assertTrue(OperatorHubCatalog::isKnown('couverture'));
        self::assertFalse(OperatorHubCatalog::isKnown('admin'));
    }

    public function testLayoutPinsFavouritesFirstAndDropsEmptyGroups(): void
    {
        $tools = OperatorHubCatalog::visible(static fn (string $p): bool => false, static fn (string $p): bool => true, static fn (string $r): bool => false);
        $layout = OperatorHubCatalog::layout($tools, ['couverture', 'fichiers', 'atak'], ['lier' => ['label' => 'À relier', 'tone' => 'warn']]);
        self::assertSame(['couverture', 'atak'], array_column($layout['favorites'], 'key'), 'ordre d’épinglage ; un favori devenu inaccessible est ignoré');
        self::assertNotContains('comptes_rendus', array_column($layout['groups'], 'key'), 'groupe sans outil : non rendu');
        self::assertSame(['operation', 'identite', 'materiel', 'preparation'], array_column($layout['groups'], 'key'));
        $operation = array_column($layout['groups'][0]['tools'], null, 'key');
        self::assertTrue($operation['atak']['favorite']);
        self::assertFalse($operation['lier']['favorite']);
        self::assertSame('À relier', $operation['lier']['badge']['label']);
        self::assertNull($operation['atak']['badge']);
    }

    public function testSpacesMenuTileIsForMembersOnlyAndBlue(): void
    {
        self::assertSame([], OperatorHubCatalog::spaceTiles(false), 'visiteur : pas de tuile');
        $tiles = OperatorHubCatalog::spaceTiles(true);
        self::assertCount(1, $tiles);
        self::assertSame('OPR', $tiles[0]['abbr']);
        self::assertSame('Outils opérateur', $tiles[0]['label']);
        self::assertSame('blue', $tiles[0]['tone']);
        self::assertSame('operateur', $tiles[0]['path']);
        self::assertStringEndsWith('/operateur', $tiles[0]['href']);

        $root = dirname(__DIR__, 2);
        $header = (string) file_get_contents($root . '/views/partials/athena_caverne_header.php');
        self::assertStringContainsString('OperatorHubCatalog::spaceTiles(', $header);
        self::assertSame(2, substr_count($header, "athena-header__espace-item--' . \$h(\$espaceTone)"), 'menu Espaces et menu mobile');
        self::assertStringContainsString('aria-current="page"', $header);
        $css = (string) file_get_contents($root . '/public/assets/css/athena-header.css');
        self::assertStringContainsString('.athena-header__espace-item--blue', $css);
        self::assertStringContainsString('#60a5fa', $css, 'bleu des jetons du site, pas un bleu inventé');
        // Le menu déroulant « Opérateur » ouvre la page en premier lien ; la carte ATAK y mène aussi.
        $nav = (string) file_get_contents($root . '/config/navigation.php');
        self::assertLessThan(strpos($nav, "'Situation tactique (ATAK web)'"), strpos($nav, "'label' => 'Tous mes outils', 'path' => 'operateur'"));
        self::assertStringContainsString('/operateur"', (string) file_get_contents($root . '/views/atak-overwatch-beta.php'));
    }

    // ------------------------------------------------------------------ Routes

    public function testEveryRouteRequiresAuthenticationAndWritesArePosts(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        preg_match_all('#\$router->(get|post)\(\'(/operateur[^\']*)\', \[(\$opHub|OperateurTerrainController::class), \'(\w+)\'\], \[AuthMiddleware::class\]\);#', $routes, $m, PREG_SET_ORDER);
        self::assertCount(20, $m, 'toutes les routes /operateur passent par AuthMiddleware');
        self::assertSame(20, preg_match_all('#\$router->\w+\(\'/operateur#', $routes));
        $writes = ['favorite', 'accessRequest', 'accessCancel', 'identitySave', 'identityPhoto', 'identityArchive', 'identityActivate', 'identityPrimary', 'documentRequest', 'documentAction', 'coverGenerate', 'coverAction'];
        foreach ($m as [, $verb, $path, , $action]) {
            self::assertTrue(method_exists(OperatorHubController::class, $action) || $action === 'index', $action);
            self::assertSame(in_array($action, $writes, true) ? 'post' : 'get', $verb, $path);
        }
        // Les routes à paramètre viennent après les routes fixes de même préfixe (sinon « photo » serait lu comme un id).
        self::assertLessThan(strpos($routes, "'/operateur/identite/identites/{id}', "), strpos($routes, "'/operateur/identite/identites/{id}/photo'"));
        self::assertStringContainsString("'/operateur/terrain'", $routes, 'l’ancienne page terrain reste servie');
    }

    public function testWritesNeedAMemberAndAValidToken(): void
    {
        $this->db();
        $anonymous = $this->controller(null);
        foreach (['identitySave', 'documentRequest', 'coverGenerate', 'identityActivate'] as $action) {
            $res = $anonymous->$action($this->post(['last_name' => 'X', 'first_name' => 'Y']), ['id' => '1']);
            self::assertSame(302, $res->statusCode(), $action);
            self::assertStringEndsWith('/login', (string) $res->headerValue('Location'));
        }
        self::assertSame(302, $anonymous->index(new Request())->statusCode());
        self::assertSame(302, $anonymous->identities(new Request())->statusCode());
        self::assertSame(302, $anonymous->coverDownload(new Request(), ['id' => '1'])->statusCode());

        $noToken = $this->controller(10, [], false);
        $res = $noToken->identitySave($this->post(['last_name' => 'Gyllen', 'first_name' => 'Jake']));
        self::assertSame(302, $res->statusCode());
        self::assertSame('error', $this->flashes[0][0]);
        self::assertStringContainsString('Session expirée', $this->flashes[0][1]);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_identities')->fetchColumn(), 'sans jeton : rien n’est écrit');
    }

    public function testAMemberManagesOnlyTheirOwnIdentityPapersAndCovers(): void
    {
        $this->db();
        $jake = $this->controller(10);
        $res = $jake->identitySave($this->post(['last_name' => 'Gyllen', 'first_name' => 'Jake', 'sex' => 'M', 'birth_date' => '1990-04-02', 'nationality' => 'ALT', 'height_cm' => '182']));
        self::assertSame('success', $this->flashes[0][0], $this->flashes[0][1]);
        self::assertStringContainsString('/operateur/identite#identite-', (string) $res->headerValue('Location'));
        $identityId = (int) $this->pdo->query('SELECT id FROM sse_identities WHERE user_id = 10')->fetchColumn();

        $jake->documentRequest($this->post(['identity_id' => (string) $identityId, 'doc_type' => 'passport', 'country_code' => 'alt']));
        self::assertSame('success', end($this->flashes)[0], end($this->flashes)[1]);
        $docId = (int) $this->pdo->query('SELECT id FROM sse_id_documents')->fetchColumn();
        self::assertSame('draft', (string) $this->pdo->query('SELECT workflow FROM sse_id_documents')->fetchColumn(), 'une demande : jamais un papier émis');

        $jake->coverGenerate($this->post(['source' => 'random', 'country' => 'ALT', 'sex' => 'F', 'lang' => 'fr']));
        self::assertSame('success', end($this->flashes)[0], end($this->flashes)[1]);
        $coverId = (int) $this->pdo->query('SELECT id FROM sse_cover_exports')->fetchColumn();

        // Un autre membre vise les identifiants de Jake : tout est refusé, rien ne change.
        $other = $this->controller(11);
        $before = count($this->flashes);
        $other->identitySave($this->post(['last_name' => 'Vole', 'first_name' => 'Nom']), ['id' => (string) $identityId]);
        $other->identityArchive($this->post(), ['id' => (string) $identityId]);
        $other->identityActivate($this->post(), ['id' => (string) $identityId]);
        $other->identityPhoto($this->post(['photo_remove' => '1']), ['id' => (string) $identityId]);
        $other->documentRequest($this->post(['identity_id' => (string) $identityId, 'doc_type' => 'id_card', 'country_code' => 'ALT']));
        $other->documentAction($this->post(['action' => 'cancel']), ['id' => (string) $docId]);
        foreach (['edit', 'duplicate', 'revoke', 'activate'] as $action) {
            $other->coverAction($this->post(['action' => $action, 'profession' => 'Espion']), ['id' => (string) $coverId]);
        }
        $other->coverDownload(new Request(), ['id' => (string) $coverId]);
        $other->documentSpecimen(new Request(), ['id' => (string) $docId]);
        foreach (array_slice($this->flashes, $before) as [$type, $message]) {
            self::assertSame('error', $type, $message);
        }
        self::assertCount(12, array_slice($this->flashes, $before));
        self::assertSame('Gyllen', (string) $this->pdo->query('SELECT last_name FROM sse_identities WHERE id = ' . $identityId)->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_id_documents')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_cover_exports')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query("SELECT COUNT(*) FROM operator_identity_states WHERE revoked_at IS NOT NULL OR archived_at IS NOT NULL")->fetchColumn());
        // Même identifiant de membre dans une autre communauté : rien non plus.
        $elsewhere = $this->controller(10, [], true, 2);
        $elsewhere->coverDownload(new Request(), ['id' => (string) $coverId]);
        self::assertSame('error', end($this->flashes)[0]);

        // Jake télécharge son fichier.
        $dl = $jake->coverDownload(new Request(), ['id' => (string) $coverId]);
        self::assertSame(200, $dl->statusCode());
        self::assertStringContainsString('attachment; filename="comspec-couverture-CVR-', (string) $dl->headerValue('Content-Disposition'));
        self::assertSame('no-store', $dl->headerValue('Cache-Control'));
        self::assertTrue(SseCoverExportService::parse($dl->body())['ok']);
        // Action inconnue : refusée.
        $jake->coverAction($this->post(['action' => 'delete_everything']), ['id' => (string) $coverId]);
        self::assertSame('error', end($this->flashes)[0]);
    }

    public function testFavouritesArePerMemberLimitedToVisibleTools(): void
    {
        $this->db();
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $jake = $this->controller(10);
        $res = $jake->favorite($this->post(['tool' => 'couverture']));
        self::assertSame(['ok' => true, 'favorite' => true], json_decode($res->body(), true));
        self::assertSame(404, $jake->favorite($this->post(['tool' => 'fichiers']))->statusCode(), 'outil non accessible : ne s’épingle pas');
        self::assertSame(404, $jake->favorite($this->post(['tool' => '../admin']))->statusCode());
        self::assertSame(419, $this->controller(10, [], false)->favorite($this->post(['tool' => 'atak']))->statusCode());
        self::assertSame(401, $this->controller(null)->favorite($this->post(['tool' => 'atak']))->statusCode());

        $repo = new OperatorHubRepository();
        self::assertSame(['couverture'], $repo->favorites(1, 10));
        self::assertSame([], $repo->favorites(1, 11), 'les épingles d’un membre ne sont pas celles d’un autre');
        self::assertSame([], $repo->favorites(2, 10));
        self::assertSame(['ok' => true, 'favorite' => false], json_decode($jake->favorite($this->post(['tool' => 'couverture']))->body(), true));
        self::assertSame([], $repo->favorites(1, 10));
        for ($n = 0; $n < OperatorHubRepository::MAX_FAVORITES; $n++) {
            self::assertTrue($repo->toggleFavorite(1, 10, 'outil' . $n));
        }
        self::assertNull($repo->toggleFavorite(1, 10, 'de-trop'), 'limite d’épingles');
        unset($_SERVER['HTTP_ACCEPT']);
    }

    // ------------------------------------------------------------------ Rendu

    public function testHubPageShowsFiguresCardsAndNoEyebrow(): void
    {
        $this->db();
        $c = $this->controller(10, ['atak.sse.access']);
        $c->identitySave($this->post(['last_name' => 'Gyllen', 'first_name' => 'Jake']));
        (new OperatorHubRepository())->toggleFavorite(1, 10, 'documents');
        self::assertSame('rendu', $c->index(new Request())->body());
        $data = $this->rendered;
        self::assertSame('operateur.hub', $data['content']);
        self::assertSame('Viper 1-1', $data['hubCallsign']);
        self::assertSame(['ATAK', 'Identité', 'Papiers', 'Couverture'], array_column($data['hubFigures'], 'label'));
        self::assertSame('Relié', $data['hubFigures'][0]['value']);
        self::assertStringContainsString('09/11/2026', $data['hubFigures'][0]['hint'], 'dernière session : le terminal révoqué ne compte pas');
        self::assertSame('8 à compléter', $data['hubFigures'][1]['value']);
        self::assertSame(['documents'], array_column($data['hubLayout']['favorites'], 'key'));

        $html = $this->view('hub', ['csrfToken' => 'jeton'] + $data);
        foreach (['Outils opérateur<span class="dash-hero__title-dot"', 'Viper 1-1', 'En opération', 'Mon identité', 'Mon matériel', 'Préparation', 'Comptes rendus', 'Épinglés',
            'data-opx-search-input', 'Passage aux fichiers', '8 à compléter', 'Relié', 'name="_csrf_token" value="jeton"', 'data-opx-tool="documents"',
            // Composants du tableau de bord, pas de style à part.
            'class="dash-zone', 'dash-zone__title', 'dash-quick__item', 'dash-quick__arrow', 'dash-hero__brief-item', 'cc-badge'] as $needle) {
            self::assertStringContainsString($needle, $html, $needle);
        }
        // Outil dont il manque le droit : tuile grisée, non cliquable, droit en clair.
        self::assertStringContainsString('Accès réservé : Poste TOC', $html);
        self::assertStringNotContainsString('href="/atak/toc"', $html);
        self::assertStringNotContainsString('kicker', $html, 'aucun petit libellé au-dessus des titres');
        self::assertStringNotContainsString('eyebrow', $html);
        self::assertSame(1, substr_count($html, '<h1'));

        // Membre sans droit SSE : pas de carte des fichiers, groupe « Comptes rendus » absent.
        $plain = $this->controller(11);
        $plain->index(new Request());
        $html = $this->view('hub', ['csrfToken' => 'jeton'] + $this->rendered);
        self::assertStringContainsString('Accès réservé : Bureau SSE', $html, 'outils du Bureau SSE montrés grisés');
        self::assertStringNotContainsString('/atak/sse/fichiers"', $html, 'aucun lien vers un outil fermé');
        self::assertSame(0, substr_count($html, 'data-opx-tool="fichiers"') - substr_count($html, 'opx-tile is-locked" data-opx-tool="fichiers"'));
        self::assertStringContainsString('Astuce : l’étoile', $html);
        self::assertStringContainsString('À créer', $html);
    }

    public function testIdentityAreaRendersItsThreeTabs(): void
    {
        $this->db();
        $c = $this->controller(10);
        $c->identitySave($this->post(['last_name' => 'Gyllen', 'first_name' => 'Jake', 'sex' => 'M', 'birth_date' => '1990-04-02', 'nationality' => 'ALT']));
        $identityId = (int) $this->pdo->query('SELECT id FROM sse_identities')->fetchColumn();
        $c->documentRequest($this->post(['identity_id' => (string) $identityId, 'doc_type' => 'id_card', 'country_code' => 'ALT']));
        $c->coverGenerate($this->post(['source' => 'random', 'country' => 'ALT', 'sex' => 'M', 'lang' => 'fr']));
        $base = ['csrfToken' => 'jeton', 'success' => 'Enregistré.', 'error' => null];

        $c->identities(new Request());
        self::assertSame('operateur.identite', $this->rendered['content']);
        $html = $this->view('identite', $base + $this->rendered);
        foreach (['Mon identité<span class="dash-hero__title-dot"', 'Simulation fictive', 'Jake GYLLEN', 'Identité principale', 'Active en jeu', 'Il manque 5 champs', 'Photo d’identité', 'Identité d’emprunt',
            'data-opx-open="dlg-photo-' . $identityId . '"', 'Ajouter une photo', 'Enregistré.', 'aria-current="page"', 'operateur/identite/identites/' . $identityId . '/photo',
            'class="cc-card opx-card', 'cc-btn cc-btn-primary', 'cc-progress', '<dialog class="opx-dialog" id="dlg-identite-' . $identityId . '"'] as $needle) {
            self::assertStringContainsString($needle, $html, $needle);
        }
        self::assertSame(1, substr_count($html, 'Simulation fictive'), 'dit une seule fois, discrètement');
        self::assertSame(1, substr_count($html, 'Active en jeu</span>'), 'une seule identité active en jeu');
        self::assertStringNotContainsString('<details', $html, 'de vrais boutons et des fenêtres, pas de triangles');
        self::assertStringNotContainsString('(s)', $html, 'singulier et pluriel écrits en entier');
        foreach (['Alias', 'Identité de couverture', 'falsifi', 'Identité civile'] as $secret) {
            self::assertStringNotContainsString($secret, $html, 'jamais la nature d’une identité : ' . $secret);
        }

        $c->documents(new Request());
        $html = $this->view('identite', $base + $this->rendered);
        foreach (['Carte d’identité', 'Demande en attente', 'Annuler ma demande', 'Voir le verso', 'SPÉCIMEN · SIMULATION', 'Télécharger le spécimen', 'Demander un papier', '<svg', 'Remise en jeu'] as $needle) {
            self::assertStringContainsString($needle, $html, $needle);
        }
        foreach (['Origine', 'Falsifi', 'st_origin'] as $secret) {
            self::assertStringNotContainsString($secret, $html, $secret);
        }

        $c->covers(new Request());
        $html = $this->view('identite', $base + $this->rendered);
        foreach (['Créer une couverture', 'Mes couvertures', 'Active', 'Jamais récupérée en jeu', 'Télécharger', 'Dupliquer', 'Révoquer', 'Ajuster la légende', 'Synchroniser avec Athena', 'Vous et les responsables habilités', 'CVR-'] as $needle) {
            self::assertStringContainsString($needle, $html, $needle);
        }

        // Spécimen : page seule, mention sur les deux faces, jamais mise en cache.
        $docId = (int) $this->pdo->query('SELECT id FROM sse_id_documents')->fetchColumn();
        $specimen = $c->documentSpecimen(new Request(), ['id' => (string) $docId]);
        self::assertSame('no-store', $specimen->headerValue('Cache-Control'));
        self::assertSame(2, substr_count($specimen->body(), 'SPÉCIMEN · SIMULATION'));
        self::assertStringContainsString('noindex', $specimen->body());
    }

    public function testPagesUseTheDashboardShellAndComponents(): void
    {
        $root = dirname(__DIR__, 2);
        $page = (string) file_get_contents($root . '/views/operateur/page.php');
        foreach (['dashboard-impact.css', 'dashboard-refresh.css', 'dashboard-rail.css', 'athena-header.css', 'portal-footer.css', 'dashboard_aside.php', 'header_dashboard.php',
            'portal_footer.php', 'dash-cc dash-cc--rail', 'dash-cc__main'] as $needle) {
            self::assertStringContainsString($needle, $page, $needle);
        }
        self::assertStringContainsString("Response::view('operateur.page'", (string) file_get_contents($root . '/app/Controllers/Web/OperatorHubController.php'));
        // La feuille de style propre aux outils ne redéfinit pas les composants du tableau de bord.
        $css = (string) file_get_contents($root . '/public/assets/css/operateur-hub.css');
        foreach (['.cc-card {', '.cc-btn {', '.cc-badge {', '.dash-zone {', '.dash-quick__item {', '.dash-zone__title {'] as $dup) {
            self::assertDoesNotMatchRegularExpression('/^' . preg_quote($dup, '/') . '/m', $css, $dup);
        }
        self::assertStringNotContainsString('#60a5fa', $css, 'accent vert du site ; le bleu ne sert qu’à la tuile du menu Espaces');
        foreach (glob($root . '/views/operateur/*.php') ?: [] as $view) {
            if (basename($view) === 'terrain.php') {
                continue;
            }
            $src = (string) file_get_contents($view);
            self::assertStringNotContainsString('kicker', $src, basename($view) . ' : aucun petit libellé au-dessus des titres');
            self::assertStringNotContainsString('eyebrow', $src, basename($view));
        }
    }

    public function testLockedToolsAreListedGreyedWithThePermissionToAsk(): void
    {
        $path = static fn (string $p): bool => true;
        $none = static fn (string $p): bool => false;
        $all = OperatorHubCatalog::withAccess($none, $path, static fn (string $r): bool => true);
        $byKey = array_column($all, null, 'key');
        self::assertCount(count(OperatorHubCatalog::TOOLS), $all, 'tous les outils sont montrés');
        self::assertTrue($byKey['fichiers']['locked']);
        self::assertSame('atak.sse.access', $byKey['fichiers']['needs']);
        self::assertSame('Bureau SSE', $byKey['fichiers']['needs_label']);
        self::assertSame('atak.toc.use', $byKey['toc']['needs']);
        self::assertFalse($byKey['atak']['locked']);
        self::assertSame('', $byKey['atak']['needs']);
        // Route absente : rien à demander, l'outil n'est pas listé. Module fermé à la communauté : pas listé non plus.
        self::assertArrayNotHasKey('toc', array_column(OperatorHubCatalog::withAccess($none, $path, static fn (string $r): bool => false), null, 'key'));
        $noAtak = array_column(OperatorHubCatalog::withAccess(static fn (string $p): bool => true, static fn (string $p): bool => !str_starts_with($p, 'atak'), static fn (string $r): bool => true), null, 'key');
        self::assertArrayNotHasKey('atak', $noAtak);
        self::assertArrayNotHasKey('fichiers', $noAtak);
        // Un outil grisé ne s'épingle pas et n'apparaît pas dans les épinglés.
        $layout = OperatorHubCatalog::layout($all, ['fichiers', 'atak']);
        self::assertSame(['atak'], array_column($layout['favorites'], 'key'));
        foreach (OperatorHubCatalog::TOOLS as $tool) {
            foreach ($tool['any_permissions'] ?? [] as $n => $p) {
                if ($n === 0) {
                    self::assertArrayHasKey($p, OperatorHubCatalog::PERMISSION_LABELS, 'libellé en clair du droit ' . $p);
                }
            }
        }
    }

    public function testAMemberChoosesTheirMainIdentity(): void
    {
        $this->db();
        $jake = $this->controller(10);
        $jake->identitySave($this->post(['last_name' => 'Georgiou', 'first_name' => 'Yannis']));
        $jake->identitySave($this->post(['last_name' => 'Gyllen', 'first_name' => 'Jake']));
        $ids = array_map('intval', $this->pdo->query('SELECT id FROM sse_identities ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
        // La seconde identité devient la principale ; l'ancienne devient une identité d'emprunt, rien n'est supprimé.
        $jake->identityPrimary($this->post(), ['id' => (string) $ids[1]]);
        self::assertSame('success', end($this->flashes)[0], end($this->flashes)[1]);
        $jake->identities(new Request());
        $list = array_column($this->rendered['idIdentities'], null, 'id');
        self::assertTrue($list[$ids[1]]['is_primary']);
        self::assertFalse($list[$ids[0]]['is_primary']);
        self::assertTrue($list[$ids[0]]['promotable']);
        self::assertFalse($list[$ids[1]]['promotable']);
        self::assertCount(2, $list);
        self::assertSame(1, count(array_filter($list, static fn (array $i): bool => $i['active'])), 'une seule identité active en jeu');
        // Un autre membre ne change pas l'identité principale de Jake ; une identité remise par un responsable non plus.
        $this->controller(11)->identityPrimary($this->post(), ['id' => (string) $ids[0]]);
        self::assertSame('error', end($this->flashes)[0]);
        $this->pdo->exec("UPDATE sse_identities SET kind = 'cover' WHERE id = " . $ids[0]);
        $jake->identityPrimary($this->post(), ['id' => (string) $ids[0]]);
        self::assertSame('error', end($this->flashes)[0]);
        self::assertSame('civil', (string) $this->pdo->query('SELECT kind FROM sse_identities WHERE id = ' . $ids[1])->fetchColumn());
        // Sans identité : formulaire prérempli depuis le profil du membre.
        $this->controller(11)->identities(new Request());
        self::assertArrayHasKey('last_name', $this->rendered['idProposal']);
        $html = $this->view('identite', ['csrfToken' => 'jeton', 'success' => null, 'error' => null] + $this->rendered);
        self::assertStringContainsString('Définir mon identité principale', $html);
    }

    /**
     * Régression (erreur 500 en ligne après la refonte) : la page est rendue dans le vrai gabarit, avec la gestion
     * d'erreurs de la production (App\Core\ExceptionHandler : tout avertissement PHP devient une ErrorException). Les
     * partiels du tableau de bord lisent des variables que ce contrôleur ne passe pas : views/operateur/page.php doit
     * les poser, pour un membre au profil vide comme pour un propriétaire qui a tous les droits.
     */
    public function testPagesRenderInTheRealShellWithEveryPhpWarningFatal(): void
    {
        $this->db();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_HOST'] = 'localhost';
        Session::set('user_id', 10);
        Session::set('tenant_id', 1);
        $exports = new SseCoverExportRepository();
        $limiter = $this->createMock(FileRateLimiter::class);
        $limiter->method('tooManyAttempts')->willReturn(false);
        $noop = static function (): void {
        };
        $make = static fn (bool $owner): OperatorHubController => new OperatorHubController(
            new OperatorHubRepository(),
            new OperatorIdentityService(null, null, null, null, null, null, $exports, new SseCoverExportService($exports, null, null, $noop, 'test-key'), $noop),
            // Profil vide : ni indicatif, ni Steam, ni unité.
            static fn (): ?array => ['tenant' => 1, 'user' => 10, 'row' => ['id' => 10, 'display_name' => '', 'steam_id' => null]],
            static fn (string $p): bool => $owner,
            static fn (string $path): bool => true,
            null,
            static fn (Request $r): bool => true,
            static function (string $type, string $message): void {
            },
            $limiter,
            static fn (int $t, int $u): array => [],
        );
        $level = ob_get_level();
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            foreach ([false, true] as $owner) {
                $c = $make($owner);
                foreach (['index' => 'Outils opérateur', 'identities' => 'Mon identité', 'documents' => 'Mon identité', 'covers' => 'Mes couvertures'] as $method => $needle) {
                    $_SERVER['REQUEST_URI'] = '/operateur';
                    $res = $c->$method(new Request());
                    self::assertSame(200, $res->statusCode(), $method);
                    self::assertStringContainsString($needle, $res->body(), $method);
                    self::assertStringContainsString('dash-cc dash-cc--rail', $res->body(), $method . ' : rendu dans le gabarit du tableau de bord');
                    self::assertStringContainsString('</html>', $res->body(), $method . ' : page rendue jusqu’au bout');
                }
            }
        } finally {
            restore_error_handler();
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
        // La page pose elle-même ce que les partiels du tableau de bord attendent de son contrôleur.
        $page = (string) file_get_contents(dirname(__DIR__, 2) . '/views/operateur/page.php');
        foreach (['$dashboard_tenant_label =', '$show_staff_enlistments =', '$currentUser ='] as $var) {
            self::assertStringContainsString($var, $page, $var);
        }
    }

    public function testDocumentPreviewEscapesTextAndOnlyAcceptsHexColours(): void
    {
        $opxDocSvg = null;
        require dirname(__DIR__, 2) . '/views/operateur/_document_svg.php';
        $doc = ['id' => 7, 'ref' => 'DOC-000007', 'doc_type' => 'passport', 'doc_type_label' => 'Passeport', 'country_name' => 'Altis <b>', 'country_code' => 'ALT',
            'last_name' => '"><script>alert(1)</script>', 'first_name' => 'Jake', 'sex' => 'M', 'birth_date' => '1990-04-02', 'birth_place' => 'Kavala', 'nationality' => 'ALT',
            'doc_number' => 'AB1234567', 'issued_on' => '2026-01-01', 'expires_on' => '2036-01-01', 'authority' => 'Préfecture', 'holder_name' => 'Jake',
            'color_primary' => 'url(javascript:alert(1))', 'color_secondary' => '#AABBCC', 'height_cm' => 182, 'eyes' => 'blue', 'hair' => 'brown', 'mrz' => ['P<ALT<<', 'AB1234567<']];
        $recto = $opxDocSvg($doc, 'recto', 'x" onload="alert(1)');
        self::assertStringNotContainsString('<script>', $recto);
        self::assertStringNotContainsString('javascript:', $recto);
        self::assertStringNotContainsString('" onload="', $recto);
        self::assertStringContainsString('#AABBCC', $recto);
        self::assertStringContainsString('SPÉCIMEN · SIMULATION', $recto);
        $verso = $opxDocSvg($doc, 'verso', null);
        self::assertStringContainsString('P&lt;ALT&lt;&lt;', $verso);
        self::assertStringContainsString('182 cm', $verso);
        self::assertNotFalse(simplexml_load_string($recto), 'SVG bien formé (recto)');
        self::assertNotFalse(simplexml_load_string($verso), 'SVG bien formé (verso)');
    }

    public function testPhotoDataUriNeverLeavesThePublicFolder(): void
    {
        $dir = sys_get_temp_dir() . '/opx-public-' . bin2hex(random_bytes(4));
        mkdir($dir . '/uploads', 0777, true);
        file_put_contents($dir . '/uploads/p.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
        file_put_contents($dir . '/uploads/notes.txt', 'secret');
        file_put_contents(dirname($dir) . '/opx-outside.png', 'x');
        try {
            self::assertStringStartsWith('data:image/png;base64,', (string) OperatorHubController::photoDataUri('uploads/p.png', $dir));
            self::assertNull(OperatorHubController::photoDataUri('uploads/notes.txt', $dir), 'pas une image');
            self::assertNull(OperatorHubController::photoDataUri('../opx-outside.png', $dir), 'hors du dossier public');
            self::assertNull(OperatorHubController::photoDataUri("uploads/p.png\0.txt", $dir));
            self::assertNull(OperatorHubController::photoDataUri('uploads/absent.png', $dir));
            self::assertNull(OperatorHubController::photoDataUri(null, $dir));
        } finally {
            @unlink($dir . '/uploads/p.png');
            @unlink($dir . '/uploads/notes.txt');
            @unlink(dirname($dir) . '/opx-outside.png');
            @rmdir($dir . '/uploads');
            @rmdir($dir);
        }
    }

    /** @param array<string, mixed> $data */
    private function view(string $name, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require dirname(__DIR__, 2) . '/views/operateur/' . $name . '.php';

        return (string) ob_get_clean();
    }

    // ------------------------------------------------------------------ Annonce

    private function alertsDb(): PDO
    {
        $pdo = $this->db();
        $pdo->exec('CREATE TABLE platform_alerts (id INTEGER PRIMARY KEY AUTOINCREMENT, kind TEXT, display_style TEXT, title TEXT, body TEXT, cta_label TEXT, cta_url TEXT,
            coupon_code TEXT, starts_at TEXT, ends_at TEXT, sort_order INTEGER DEFAULT 0, is_active INTEGER DEFAULT 1, dismissible INTEGER DEFAULT 1, audience_json TEXT, created_at TEXT, updated_at TEXT)');

        return $pdo;
    }

    /** @param array<int, true> $dismissed */
    private function alerts(array $dismissed = []): AlertPresentationService
    {
        $tenantAlerts = $this->createMock(TenantAlertRepository::class);
        $tenantAlerts->method('listActiveForTenantDisplay')->willReturn([]);
        $dismissals = $this->createMock(UserAlertDismissalRepository::class);
        $dismissals->method('dismissedSetsForUser')->willReturnCallback(
            static fn (int $userId): array => ['platform' => $userId === 10 ? $dismissed : [], 'tenant' => []]
        );
        $tenants = $this->createMock(TenantRepository::class);
        $tenants->method('findById')->willReturn(['id' => 1, 'subscription_status' => 'none', 'settings' => null]);
        (new \ReflectionProperty(AlertPresentationService::class, 'cachedForRequest'))->setValue(null, null);

        return new AlertPresentationService(new PlatformAlertRepository(), $tenantAlerts, $dismissals, $tenants);
    }

    public function testAnnouncementIsSeededOnceForTwoWeeksMembersOnly(): void
    {
        $pdo = $this->alertsDb();
        $seed = require dirname(__DIR__, 2) . '/bootstrap/operator_hub_announcement.php';
        $now = new \DateTimeImmutable('now');
        self::assertTrue($seed($pdo, $now));
        self::assertFalse($seed($pdo, $now->modify('+3 days')), 'rejouée : ni doublon ni prolongation');
        $rows = $pdo->query('SELECT * FROM platform_alerts')->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(1, $rows);
        $row = $rows[0];
        self::assertSame('novelty', $row['kind']);
        self::assertSame('Nouveau : vos outils opérateur', $row['title']);
        self::assertSame('/operateur?annonce=outils-operateur', $row['cta_url']);
        self::assertSame(1, (int) $row['dismissible']);
        self::assertSame($now->format('Y-m-d H:i:s'), $row['starts_at']);
        self::assertSame($now->modify('+14 days')->format('Y-m-d H:i:s'), $row['ends_at']);
        self::assertSame(['guest' => false, 'authenticated' => true, 'free' => true, 'paid' => true], json_decode((string) $row['audience_json'], true));
        foreach (['Espaces', 'Outils opérateur', 'identités', 'papiers', 'couvertures', 'fichiers', 'matériel'] as $word) {
            self::assertStringContainsStringIgnoringCase($word, $row['title'] . ' ' . $row['body'], $word);
        }

        // Membre connecté : affichée (elle compte aussi dans la cloche, même source). Visiteur : jamais.
        Session::set('user_id', 10);
        Session::set('tenant_id', 1);
        $shown = $this->alerts()->forCurrentRequest();
        self::assertCount(1, $shown);
        self::assertSame('platform', $shown[0]['scope']);
        self::assertTrue($shown[0]['dismissible']);
        // Français, ou anglais quand la langue du lecteur est l'anglais (traduction à l'affichage).
        self::assertContains($shown[0]['cta_label'], ['Ouvrir mes outils', 'Open my tools']);
        // Fermée par ce membre : elle ne revient pas ; les autres membres la voient encore.
        self::assertSame([], $this->alerts([(int) $row['id'] => true])->forCurrentRequest());
        Session::set('user_id', 11);
        self::assertCount(1, $this->alerts([(int) $row['id'] => true])->forCurrentRequest());
        Session::forgetMany(['user_id', 'tenant_id']);
        self::assertSame([], $this->alerts()->forCurrentRequest(), 'visiteur');
    }

    public function testAnnouncementOnlyShowsInsideItsDateWindow(): void
    {
        $seed = require dirname(__DIR__, 2) . '/bootstrap/operator_hub_announcement.php';
        foreach (['-20 days' => 0, '+2 days' => 0, '-13 days' => 1, '-1 minute' => 1] as $shift => $expected) {
            $pdo = $this->alertsDb();
            self::assertTrue($seed($pdo, (new \DateTimeImmutable('now'))->modify($shift)));
            self::assertCount($expected, (new PlatformAlertRepository())->listActiveForDisplay(), 'annonce publiée à ' . $shift);
            SseIdentitySqlite::close();
            $this->pdo = null;
        }
        // Base sans annonces de plateforme : l'étape ne fait rien, sans erreur.
        self::assertFalse($seed($this->db()));
    }

    public function testAnnouncementAndHubTextsHaveAnEnglishVersion(): void
    {
        $en = require dirname(__DIR__, 2) . '/lang/en/site_auto.php';
        foreach (['Nouveau : vos outils opérateur', 'Ouvrir mes outils', 'Outils opérateur', 'Mon identité', 'Simulation fictive', 'Créer une couverture', 'Demander un papier',
            'Vos identités, papiers, couvertures, fichiers et matériel sont réunis sur une seule page. Ouvrez le menu Espaces, puis la tuile bleue « Outils opérateur ».'] as $fr) {
            self::assertArrayHasKey($fr, $en, $fr);
            self::assertNotSame($fr, $en[$fr]);
        }
        $nav = require dirname(__DIR__, 2) . '/lang/en/nav.php';
        foreach (['Outils opérateur', 'Identité, papiers, couverture, fichiers', 'Tous mes outils'] as $fr) {
            self::assertArrayHasKey(i18n_slug($fr), $nav, $fr);
        }
        // Annonce traduite à l'affichage pour un lecteur anglophone (les bandeaux sont posés en JavaScript).
        self::assertStringContainsString('HtmlTranslator::translateText', (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Alerts/AlertPresentationService.php'));
    }
}
