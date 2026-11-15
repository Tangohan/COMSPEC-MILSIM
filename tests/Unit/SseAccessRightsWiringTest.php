<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\SseAccessRightsController;
use App\Core\Request;
use App\Core\Session;
use App\Middleware\SseSectionAccessMiddleware;
use App\Services\Security\FileRateLimiter;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseAccessPolicy;
use App\Support\SseAccessCatalog as C;
use App\Support\SsePortalNav;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseAccessFixture;

/**
 * Droits d'accès SSE : câblage (routes, CSRF, gardes remplacées, migration, vues, traductions, documentation) et
 * comportement de l'onglet (réservé aux gestionnaires, CSRF, identifiants de la communauté, pas d'IDOR) et de la garde de section.
 */
final class SseAccessRightsWiringTest extends TestCase
{
    private SseAccessFixture $fx;
    /** @var array<string, mixed> */
    private array $savedSession = [];
    private bool $savedHadSession = false;
    private mixed $savedStarted = false;
    /** @var array<string, mixed> */
    private array $savedServer = [];

    protected function setUp(): void
    {
        $this->savedServer = $_SERVER;
        $this->clearLimits();
        $this->fx = new SseAccessFixture();
        SseAccessPolicy::useForSession($this->fx->policy);
        $started = new \ReflectionProperty(Session::class, 'started');
        $this->savedStarted = $started->getValue();
        $started->setValue(null, true);
        $this->savedHadSession = isset($_SESSION);
        $this->savedSession = $_SESSION ?? [];
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
    }

    protected function tearDown(): void
    {
        SseAccessPolicy::useForSession(null);
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->savedStarted);
        if ($this->savedHadSession) {
            $_SESSION = $this->savedSession;
        } else {
            unset($_SESSION);
        }
        $_POST = [];
        $_GET = [];
        $_SERVER = $this->savedServer;
        $this->clearLimits();
    }

    /** Les compteurs de changements par heure sont des fichiers : on les remet à zéro pour que le test ne dépende pas des essais précédents. */
    private function clearLimits(): void
    {
        $limiter = new FileRateLimiter();
        foreach (['w', 'p'] as $kind) {
            foreach ([10, 11, 12, 13] as $user) {
                $limiter->clear('sse_access_rules|' . $kind . '|1|' . $user);
            }
        }
    }

    private static function read(string $relative): string
    {
        $path = dirname(__DIR__, 2) . '/' . $relative;
        self::assertFileExists($path, $relative);

        return (string) file_get_contents($path);
    }

    private static function methodBody(string $src, string $method): string
    {
        $start = strpos($src, 'function ' . $method . '(');
        self::assertNotFalse($start, $method);
        $next = strpos($src, "\n    public function ", $start + 10);
        $next2 = strpos($src, "\n    private function ", $start + 10);
        $end = min($next === false ? PHP_INT_MAX : $next, $next2 === false ? PHP_INT_MAX : $next2);

        return substr($src, (int) $start, $end === PHP_INT_MAX ? null : $end - (int) $start);
    }

    private function sessionAs(int $user, int $tenant = 1, bool $guest = false): void
    {
        $_SESSION = ['user_id' => $user, 'tenant_id' => $tenant, '_csrf_token' => 'jeton', SseAccessCodeService::SESSION_TENANT => $tenant, SseAccessCodeService::SESSION_GUEST => $guest];
    }

    /** @param array<string, mixed> $post */
    private function post(string $uri, array $post): Request
    {
        $_POST = $post;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $uri;

        return new Request();
    }

    private function controller(): SseAccessRightsController
    {
        return new SseAccessRightsController($this->fx->policy, $this->fx->service);
    }

    private function location(\App\Core\Response $r): string
    {
        return (string) $r->headerValue('Location');
    }

    // ------------------------------------------------------------------ Câblage statique

    public function testRoutesAreBehindThePortalAndEveryPostGoesThroughTheWriteGuard(): void
    {
        $routes = self::read('routes/web.php');
        self::assertStringContainsString('$mwSsePortal = [SsePortalAccessMiddleware::class, SseConfidentialityMiddleware::class, SseSectionAccessMiddleware::class];', $routes);
        preg_match_all('/\$router->(get|post)\(\'(\/atak\/sse\/droits[^\']*)\', \[\$sseRights, \'(\w+)\'\], ([^)]+)\);/', $routes, $m, PREG_SET_ORDER);
        self::assertGreaterThanOrEqual(9, count($m), 'routes de l’onglet déclarées');
        $src = self::read('app/Controllers/Web/SseAccessRightsController.php');
        foreach ($m as [, $verb, $path, $method, $mw]) {
            self::assertSame('$mwSsePortal', trim($mw), $path . ' : derrière le portail SSE');
            $body = self::methodBody($src, $method);
            // La recherche de personnes répond en JSON : même garde, sans redirection (guardJson).
            $expected = $verb === 'post' ? '$this->guardWrite(' : ($method === 'people' ? '$this->guardJson()' : '$this->guard()');
            self::assertStringContainsString($expected, $body, $path . ' : garde du droit de gestion' . ($verb === 'post' ? ', jeton CSRF et plafond' : ''));
        }
        $guard = self::methodBody($src, 'guardWrite');
        self::assertStringContainsString('Csrf::validate(', $guard);
        self::assertStringContainsString('isPost()', $guard);
        self::assertStringContainsString('FileRateLimiter', $guard);
        self::assertStringContainsString('canManageRights(', self::methodBody($src, 'guard'));
        self::assertStringContainsString('canManageRights(', self::methodBody($src, 'guardJson'));
    }

    public function testTheTabShowsNoSseContentAndNoEyebrow(): void
    {
        $files = array_merge(glob(dirname(__DIR__, 2) . '/views/atak/sse/rights/*.php') ?: [], [
            dirname(__DIR__, 2) . '/app/Controllers/Web/SseAccessRightsController.php',
            dirname(__DIR__, 2) . '/app/Services/Sse/SseAccessMatrix.php',
            dirname(__DIR__, 2) . '/app/Services/Sse/SseAccessDirectory.php',
        ]);
        self::assertGreaterThanOrEqual(8, count($files));
        foreach ($files as $file) {
            $src = (string) file_get_contents($file);
            foreach (['SseIdentityRepository', 'SseCoverExport', 'SseIdDocumentRepository', 'SsePersonRepository', 'SseFileRecordRepository', 'sse_identities', 'sse_id_documents'] as $content) {
                self::assertStringNotContainsString($content, $src, basename($file) . ' : l’onglet parle de droits, pas de contenus');
            }
            self::assertDoesNotMatchRegularExpression('/kicker|eyebrow|tracking-widest/i', $src, basename($file) . ' : aucun surtitre');
            self::assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{1F000}-\x{1F2FF}]/u', $src, basename($file) . ' : pas d’emoji');
        }
        self::assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', self::read('public/assets/css/sse-access-rights.css'));
    }

    public function testEveryClassCalledByAViewIsImported(): void
    {
        $files = array_merge(glob(dirname(__DIR__, 2) . '/views/atak/sse/rights/*.php') ?: [], [dirname(__DIR__, 2) . '/views/admin/sse_access/index.php']);
        foreach ($files as $file) {
            $src = (string) file_get_contents($file);
            $imported = [];
            if (preg_match_all('/^use\s+([\w\\\\]+)(?:\s+as\s+(\w+))?;/m', $src, $uses, PREG_SET_ORDER) > 0) {
                foreach ($uses as $u) {
                    $parts = explode('\\', $u[1]);
                    $imported[$u[2] ?? end($parts)] = true;
                }
            }
            // Appels statiques et instanciations par nom court (hors noms qualifiés complets, self/static/parent et fonctions).
            preg_match_all('/(?<![\\\\\w$>])([A-Z][A-Za-z0-9_]*)::/', $src, $calls);
            preg_match_all('/\bnew\s+(?!\\\\)([A-Z][A-Za-z0-9_]*)/', $src, $news);
            foreach (array_unique(array_merge($calls[1], $news[1])) as $short) {
                if (in_array($short, ['DateTime', 'DateTimeImmutable', 'Throwable', 'Exception', 'ArrayObject'], true)) {
                    continue;
                }
                self::assertArrayHasKey($short, $imported, basename($file) . ' appelle ' . $short . ' sans use');
            }
        }
    }

    public function testGuardsAreRoutedThroughThePolicyAndKeepTheirGameContracts(): void
    {
        // Gardes « gestion des dossiers » remplacées : plus aucune lecture directe du droit dans ces contrôleurs.
        foreach (['SseFileController', 'SseCampaignController', 'SseFieldNoteController', 'SseArmaModelsController', 'SseIntelligenceWorkspaceController', 'SseDigitalLabController', 'SseIdentityRegistryController', 'SsePortalController', 'SseIdWorkshopController', 'SseFilesContext'] as $c) {
            $src = self::read('app/Controllers/Web/' . $c . '.php');
            self::assertStringNotContainsString("can('atak.sse.case.manage')", $src, $c . ' : la garde passe par SseAccessPolicy');
            self::assertStringContainsString('SseAccessPolicy', $src, $c);
        }
        foreach (['Admin/MemberIdentityPapersController', 'Admin/AdminSseFilesController', 'Admin/AdminIntelIntakeController', 'Web/PhotoLibraryController', 'Api/SseIntelApiController'] as $c) {
            self::assertStringContainsString('SseAccessPolicy', self::read('app/Controllers/' . $c . '.php'), $c);
        }
        self::assertStringContainsString('SseAccessPolicy', self::read('app/Services/Jnet/JnetDashboardService.php'));
        self::assertStringContainsString('SseAccessPolicy', self::read('app/Support/SsePortalNav.php'));
        // L'API du jeu garde sa clé : le contrat /api/sse/v1 ne change pas pour un appelant autorisé aujourd'hui.
        $api = self::read('app/Controllers/Api/SseIntelApiController.php');
        self::assertStringContainsString('ComspecApiKeyAuth::armaInlineAuthOk()', self::methodBody($api, 'canWrite'));
        self::assertStringContainsString("'legacy' => true", self::methodBody($api, 'readDenied'), 'en lecture, seule une règle de refus ferme');
        self::assertStringContainsString("|| can('atak.access')", $api, 'garde d’origine de l’API conservée');
        // Les trois routes de photothèque du site passent par la politique de la section.
        self::assertStringContainsString('$section, SseAccessCatalog::LEVEL_ADD', self::read('app/Controllers/Web/PhotoLibraryController.php'));
    }

    public function testMigrationRollbackAndRegistryAreInPlace(): void
    {
        $sql = self::read('migrations/2026_11_15_100_sse_access_rules.sql');
        foreach (['CREATE TABLE IF NOT EXISTS sse_access_rules', 'CREATE TABLE IF NOT EXISTS sse_access_rule_history'] as $stmt) {
            self::assertStringContainsString($stmt, $sql);
        }
        foreach (['tenant_id', 'section', 'level', 'subject_type', 'subject_id', 'effect', 'starts_at', 'ends_at', 'reason', 'created_by', 'created_at', 'revoked_at'] as $col) {
            self::assertStringContainsString($col . ' ', $sql, $col);
        }
        self::assertStringContainsString('KEY idx_sse_access_rules_tenant (tenant_id', $sql);
        self::assertStringContainsString('KEY idx_sse_access_rules_subject (tenant_id, subject_type, subject_id', $sql);
        $down = self::read('migrations/rollback/sse_access_rules_down.sql');
        self::assertStringContainsString('DROP TABLE IF EXISTS sse_access_rules;', $down);
        self::assertStringContainsString('DROP TABLE IF EXISTS sse_access_rule_history;', $down);
        $registry = self::read('database/steps/registry.php');
        self::assertStringContainsString("'file' => '4590_sse_access_rules.php'", $registry);
        self::assertStringContainsString("'id' => 'sse_access_rules'", $registry);
        $step = self::read('database/steps/4590_sse_access_rules.php');
        self::assertStringContainsString('tableExists', $step, 'étape rejouable : rien n’est refait si les tables existent');
        self::assertStringContainsString('2026_11_15_100_sse_access_rules.sql', $step);
        self::assertStringNotContainsString('INSERT', $step, 'aucune règle n’est ajoutée : le comportement par défaut reste inchangé');
    }

    public function testEveryVisibleTextHasItsEnglishEntry(): void
    {
        $en = require dirname(__DIR__, 2) . '/lang/en/site_auto.php';
        self::assertIsArray($en);
        $texts = [];
        foreach (C::LEVELS as $def) {
            $texts[] = $def['label'];
            $texts[] = $def['hint'];
        }
        foreach (C::SECTIONS as $def) {
            $texts[] = $def['label'];
            $texts[] = $def['hint'];
        }
        foreach (C::PROFILES as $def) {
            $texts[] = $def['label'];
        }
        foreach (C::TEMPLATES as $def) {
            $texts[] = $def['label'];
            $texts[] = $def['hint'];
        }
        foreach (C::SUBJECT_TYPES as $label) {
            $texts[] = $label;
        }
        foreach ($texts as $t) {
            self::assertArrayHasKey($t, $en, 'traduction anglaise manquante : ' . $t);
        }
    }

    public function testDocumentationDescribesTheModel(): void
    {
        $doc = self::read('docs/sse-droits-acces.md');
        foreach (array_keys(C::SECTIONS) as $key) {
            self::assertStringContainsString('`' . $key . '`', $doc, $key);
        }
        foreach (C::levelKeys() as $level) {
            self::assertStringContainsString('`' . $level . '`', $doc, $level);
        }
        self::assertStringContainsString('refus explicite', $doc);
        self::assertStringContainsString('SseAccessPolicy', $doc);
    }

    public function testNavigationOnlyShowsTheRightsTabToManagersAndFiltersRestrictedSections(): void
    {
        $ids = static function (array $sections): array {
            $out = [];
            foreach ($sections as $s) {
                foreach ($s['items'] as $i) {
                    $out[] = $i['id'];
                }
            }

            return $out;
        };
        self::assertContains('droits', $ids(SsePortalNav::sections(true, true)));
        self::assertNotContains('droits', $ids(SsePortalNav::sections(true, false)));
        self::assertNotContains('droits', $ids(SsePortalNav::sections(true, true, true)), 'jamais en session invitée');
        // Section refusée : l'élément quitte la navigation ; section dont l'existence reste visible : il passe dans les espaces restreints.
        $filtered = SsePortalNav::withAccessRules(
            SsePortalNav::sections(true, true),
            static fn (string $section, string $level): bool => !($section === 'photos' && $level === 'read') && !($section === 'journal')
        );
        self::assertNotContains('fichiers-photos', $ids($filtered['sections']));
        self::assertNotContains('fichiers-journal', $ids($filtered['sections']));
        self::assertContains('fichiers', $ids($filtered['sections']));
        self::assertContains('Photothèque', array_column($filtered['restricted'], 'label'), 'l’existence reste visible');
        $all = SsePortalNav::withAccessRules(SsePortalNav::sections(true, true), static fn (string $s, string $l): bool => true);
        self::assertSame($ids(SsePortalNav::sections(true, true)), $ids($all['sections']), 'sans règle, rien ne change');
        self::assertSame([], $all['restricted']);
    }

    // ------------------------------------------------------------------ Comportement de l'onglet

    public function testOnlyTheManagementRightOpensTheTabAndGuestsAreSentHome(): void
    {
        foreach ([[11, false], [12, false], [13, false], [10, true]] as [$user, $guest]) {
            $this->sessionAs($user, 1, $guest);
            $r = $this->controller()->index(new Request());
            self::assertSame(302, $r->statusCode(), "compte $user");
            self::assertStringContainsString('atak/sse/accueil', $this->location($r), "compte $user : renvoyé à l'accueil, sans explication");
        }
        // Une écriture non autorisée ne crée rien.
        $this->sessionAs(11);
        $req = $this->post('/atak/sse/droits/section/dossiers/regle', ['_csrf_token' => 'jeton', 'do' => 'save', 'subject_ref' => 'role:2', 'effect' => 'allow', 'levels' => ['write']]);
        self::assertStringContainsString('atak/sse/accueil', $this->location($this->controller()->ruleStore($req, ['section' => 'dossiers'])));
        self::assertSame(0, $this->fx->rules->countLive(1));
    }

    public function testWritesNeedAValidCsrfTokenAndAPostRequest(): void
    {
        $this->sessionAs(10);
        $good = ['do' => 'save', 'subject_ref' => 'role:2', 'effect' => 'allow', 'levels' => ['write']];
        foreach ([['_csrf_token' => 'faux'] + $good, $good] as $post) {
            $r = $this->controller()->ruleStore($this->post('/atak/sse/droits/section/dossiers/regle', $post), ['section' => 'dossiers']);
            self::assertStringContainsString('atak/sse/droits', $this->location($r));
            self::assertSame(0, $this->fx->rules->countLive(1), 'sans jeton valide rien n’est écrit');
        }
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/atak/sse/droits/section/dossiers/regle';
        $get = new Request();
        $this->controller()->ruleStore($get, ['section' => 'dossiers']);
        self::assertSame(0, $this->fx->rules->countLive(1), 'une requête GET n’écrit jamais');
        $revoke = $this->fx->addRule('dossiers', 'write', 'role', 2, 'allow');
        $this->controller()->ruleRevoke($this->post('/atak/sse/droits/regle/' . $revoke . '/retirer', ['_csrf_token' => 'faux']), ['id' => $revoke]);
        self::assertSame(1, $this->fx->rules->countLive(1), 'retrait sans jeton : refusé');
    }

    public function testASavedRuleIsWrittenWithItsHistoryAndAuditAndIdentifiersOfOtherCommunitiesAreRefused(): void
    {
        $this->sessionAs(10);
        $ok = $this->post('/atak/sse/droits/section/dossiers/regle', ['_csrf_token' => 'jeton', 'do' => 'save', 'subject_ref' => 'role:2', 'effect' => 'allow', 'levels' => ['write'], 'reason' => '']);
        $r = $this->controller()->ruleStore($ok, ['section' => 'dossiers']);
        self::assertStringContainsString('atak/sse/droits/section/dossiers', $this->location($r));
        self::assertSame(1, $this->fx->rules->countLive(1));
        self::assertCount(1, $this->fx->rules->history(1));
        self::assertSame('access_rule.create', $this->fx->audit[0]['action']);
        self::assertSame(10, $this->fx->audit[0]['actor_user_id']);
        // Niveau d'accès, unité et membre d'une autre communauté : refusés, rien d'écrit.
        foreach (['role:9', 'unit:900', 'unit_tree:900', 'user:20', 'role:999'] as $ref) {
            $_SESSION['_flash'] = [];
            $bad = $this->post('/atak/sse/droits/section/dossiers/regle', ['_csrf_token' => 'jeton', 'do' => 'save', 'subject_ref' => $ref, 'effect' => 'allow', 'levels' => ['read'], 'reason' => 'motif valable']);
            $this->controller()->ruleStore($bad, ['section' => 'dossiers']);
            self::assertSame(1, $this->fx->rules->countLive(1), $ref . ' : refusé');
            self::assertSame('Bénéficiaire inconnu.', $_SESSION['_flash']['error'] ?? null, $ref);
        }
        // Retirer la règle d'une autre communauté (IDOR) : sans effet.
        $foreign = $this->fx->addRule('dossiers', 'read', 'user', 20, 'allow', 2);
        $_SESSION['_flash'] = [];
        $this->controller()->ruleRevoke($this->post('/atak/sse/droits/regle/' . $foreign . '/retirer', ['_csrf_token' => 'jeton']), ['id' => $foreign]);
        self::assertNotNull($this->fx->rules->find(2, $foreign));
        self::assertNull($this->fx->rules->find(2, $foreign)['revoked_at']);
        self::assertSame('Règle introuvable ou déjà retirée.', $_SESSION['_flash']['error'] ?? null);
    }

    public function testBackAddressesNeverLeaveTheTab(): void
    {
        $this->sessionAs(10);
        $id = $this->fx->addRule('dossiers', 'write', 'role', 2, 'allow');
        foreach (['https://exemple.test/', '//exemple.test', 'atak/sse/fichiers', 'atak/sse/droits/../../acces', 'javascript:alert(1)'] as $back) {
            $r = $this->controller()->ruleRevoke($this->post('/atak/sse/droits/regle/' . $id . '/retirer', ['_csrf_token' => 'jeton', 'back' => $back]), ['id' => $id]);
            self::assertStringEndsWith('atak/sse/droits', rtrim($this->location($r), '/'), $back . ' : retour à l’onglet');
            $id = $this->fx->addRule('dossiers', 'write', 'role', 2, 'allow');
        }
        $ok = $this->controller()->ruleRevoke($this->post('/atak/sse/droits/regle/' . $id . '/retirer', ['_csrf_token' => 'jeton', 'back' => 'atak/sse/droits/section/dossiers']), ['id' => $id]);
        self::assertStringEndsWith('atak/sse/droits/section/dossiers', $this->location($ok), 'un retour vers une page de l’onglet est gardé');
    }

    public function testASectionTheManagerCannotSeeIsHiddenFromTheTab(): void
    {
        $this->sessionAs(10);
        $this->fx->addRule('photos', 'exists', 'user', 10, 'deny');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/atak/sse/droits/section/photos';
        $r = $this->controller()->section(new Request(), ['section' => 'photos']);
        self::assertStringEndsWith('atak/sse/droits', rtrim($this->location($r), '/'), 'la section masquée renvoie à la grille');
        $plan = $this->fx->service->plan(1, 10, ['section' => 'photos', 'levels' => ['read'], 'subject_type' => 'role', 'subject_id' => 2, 'effect' => 'allow']);
        self::assertSame(['Section inconnue.'], $plan['errors'], 'aucune règle ne se pose sur une section qu’on ne voit pas');
        $other = $this->fx->service->plan(1, 10, ['section' => 'dossiers', 'levels' => ['read'], 'subject_type' => 'role', 'subject_id' => 2, 'effect' => 'allow']);
        self::assertSame([], $other['errors']);
    }

    // ------------------------------------------------------------------ Garde de section

    public function testTheSectionMiddlewareOnlyClosesTheSectionOfARefusedReader(): void
    {
        $mw = new SseSectionAccessMiddleware();
        $next = static fn (Request $r): \App\Core\Response => (new \App\Core\Response())->setStatusCode(200)->setBody('ouvert');
        $this->sessionAs(12);
        $_SESSION[SseAccessCodeService::SESSION_UNTIL] = time() + 3600;
        // Sans règle : la porte d'origine décide, la section est ouverte (la porte est simulée par la politique injectée).
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/atak/sse/fichiers/photos';
        self::assertSame(200, $mw(new Request(), $next)->statusCode());
        $this->fx->addRule('photos', 'read', 'user', 12, 'deny');
        $_SERVER['REQUEST_URI'] = '/atak/sse/fichiers/photos/3';
        $closed = $mw(new Request(), $next);
        self::assertSame(302, $closed->statusCode());
        self::assertStringContainsString('atak/sse/accueil', $this->location($closed));
        $_SERVER['REQUEST_URI'] = '/atak/sse/fichiers';
        self::assertSame(200, $mw(new Request(), $next)->statusCode(), 'une autre section reste ouverte');
        $_SERVER['REQUEST_URI'] = '/atak/sse/accueil';
        self::assertSame(200, $mw(new Request(), $next)->statusCode(), 'une page hors section n’est jamais bloquée');
        $_SERVER['REQUEST_URI'] = '/atak/sse/droits';
        self::assertSame(200, $mw(new Request(), $next)->statusCode(), 'l’onglet des droits n’est pas une section : son garde est le droit de gestion');
    }
}
