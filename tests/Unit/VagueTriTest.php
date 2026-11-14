<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\Organization\CommunityMediaAdminController;
use PHPUnit\Framework\TestCase;
use Tests\Support\BackOfficeRouteMap as Map;

/**
 * Vague de tri : défauts relevés par l'audit du back-office (docs/audit-backoffice/rapports/lot7-non-regression.md)
 * corrigés, et code mort retiré. Chaque test fige une correction pour qu'elle ne revienne pas.
 */
final class VagueTriTest extends TestCase
{
    /** « Créer une collection » n'est plus prise pour la fiche du média « collections ». */
    public function testMediaCollectionRoutesReachTheirOwnAction(): void
    {
        $expected = [
            '/back-office/media/collections' => 'storeCollection',
            '/back-office/media/collections/7' => 'updateCollection',
            '/back-office/media/collections/7/delete' => 'deleteCollection',
            // Les fiches de média gardent leurs actions.
            '/back-office/media/7' => 'updateItem',
            '/back-office/media/7/delete' => 'deleteItem',
            '/back-office/media' => 'storeItem',
        ];
        foreach ($expected as $path => $action) {
            $route = Map::match('POST', $path);
            self::assertNotNull($route, $path);
            self::assertSame([CommunityMediaAdminController::class, $action], $route['handler'], $path);
            self::assertSame(['AuthMiddleware', 'OrganizationAdminMiddleware'], Map::middlewareNames($route), $path);
        }
        self::assertSame('show', Map::match('GET', '/back-office/media/7')['handler'][1]);
        // Le formulaire de la page poste bien sur cette adresse.
        $view = (string) file_get_contents(Map::root() . '/views/admin/organization/media_index.php');
        self::assertStringContainsString("back-office/media/collections", $view);
    }

    /** Une seule déclaration par adresse, et pas de route à barre finale (le chemin est normalisé sans elle). */
    public function testNoDuplicateNorTrailingSlashRoute(): void
    {
        $seen = [];
        $twice = [];
        $slash = [];
        foreach (Map::routes() as $route) {
            $key = Map::key($route);
            if (isset($seen[$key]) && Map::isBackOfficePath($route['path'])) {
                $twice[] = $key;
            }
            $seen[$key] = true;
            if ($route['path'] !== '/' && str_ends_with($route['path'], '/')) {
                $slash[] = $key;
            }
        }
        self::assertSame([], $twice, 'Route du back-office déclarée deux fois : la seconde ne répond jamais.');
        self::assertSame([], $slash, 'Route à barre finale : Request::normalizePathFromServer retire la barre, elle ne répond jamais.');

        // /admin/users reste la page des comptes de la plateforme ; les variantes à barre finale arrivent sur la même route.
        self::assertSame('index', Map::match('GET', '/admin/users')['handler'][1]);
        foreach (['/admin/' => '/admin', '/back-office/' => '/back-office', '/backoffice/' => '/backoffice'] as $typed => $served) {
            self::assertSame($served, \App\Core\Request::normalizePathFromServer(['REQUEST_URI' => $typed, 'SCRIPT_NAME' => '/index.php']));
            self::assertNotNull(Map::match('GET', $served), $served);
        }
    }

    /** Les quatre chaînes relevées par l'audit arrivent sur la page finale en un seul saut. */
    public function testFormerRedirectChainsLandInOneHop(): void
    {
        $expected = [
            '/backoffice/users' => [301, '/back-office/ressources/effectifs', ''],
            '/admin/users/create' => [301, '/back-office/ressources/effectifs/nouveau', ''],
            '/admin/atak/realism/config' => [301, '/back-office/atak/realisme', 'onglet=reglages'],
            // Adresse d'API ouverte dans le navigateur : pas une page déplacée, la redirection reste temporaire.
            '/api/training/progress/lesson' => [302, '/academy/catalogue', ''],
        ];
        foreach ($expected as $legacy => $target) {
            $route = Map::match('GET', $legacy);
            self::assertNotNull($route, $legacy);
            self::assertSame($target, Map::redirectOf($route), $legacy);
            $final = Map::match('GET', $target[1]);
            self::assertNotNull($final, $legacy . ' : cible sans route');
            self::assertFalse(Map::isRedirect($final), $legacy . ' : la cible redirige encore');
        }
        // L'envoi de progression (POST) n'a pas bougé.
        self::assertSame('progressLesson', Map::match('POST', '/api/training/progress/lesson')['handler'][1]);
    }

    /** Pages définitivement déplacées : 301. Les POST des anciennes adresses de formation gardent 307. */
    public function testMovedBackOfficePagesAnswerPermanently(): void
    {
        foreach ([
            '/backoffice', '/backoffice/roles', '/admin/system', '/admin/organization', '/admin/units', '/admin/modpacks',
            '/admin/atak-config', '/admin/ops-center', '/admin/training', '/admin/training/studio', '/admin/interteam-missions',
            '/back-office/evenements', '/back-office/units', '/back-office/groups/create', '/back-office/forum-moderation',
            '/back-office/ressources/interteam-missions', '/back-office/ressources/training/courses',
        ] as $legacy) {
            $route = Map::match('GET', $legacy);
            self::assertNotNull($route, $legacy);
            $red = Map::redirectOf($route);
            self::assertNotNull($red, $legacy);
            self::assertSame(301, $red[0], $legacy);
        }
        // Entrée de module vers sa page par défaut : ce n'est pas une page déplacée.
        self::assertSame(302, Map::redirectOf(Map::match('GET', '/back-office/renseignement'))[0]);

        $server = $_SERVER;
        try {
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_SERVER['REQUEST_URI'] = '/back-office/ressources/training/studio';
            $response = training_lms_redirect_legacy_bo_training_to_formation(new \App\Core\Request());
            self::assertSame(307, $response->statusCode());
            self::assertStringEndsWith('/formation/studio', (string) $response->headerValue('Location'));
        } finally {
            $_SERVER = $server;
        }
    }

    /**
     * Liens du menu latéral pour un jeu de droits, sur une page donnée : [libellé, chemin (requête comprise), page courante].
     *
     * @param list<string> $permissions
     * @return list<array{0:string,1:string,2:bool}>
     */
    private static function menuLinks(array $permissions, string $uri = '/back-office'): array
    {
        $server = $_SERVER;
        $get = $_GET;
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_HOST'] = 'localhost';
        parse_str((string) parse_url($uri, PHP_URL_QUERY), $_GET);
        $root = Map::root();
        require_once $root . '/app/Support/helpers.php';
        if (is_file($root . '/app/Support/navigation_menu.php')) {
            require_once $root . '/app/Support/navigation_menu.php';
        }
        \App\Core\Session::actAs(['tenant_id' => 0, 'user_id' => 0, 'display_name' => 'Profil de test']);
        \App\Core\Gate::getInstance()->setPermissions($permissions);
        // Le menu pose un jeton CSRF : pas de vraie session PHP dans un test (les en-têtes sont déjà partis).
        $started = new \ReflectionProperty(\App\Core\Session::class, 'started');
        $wasStarted = $started->getValue();
        $started->setValue(null, true);
        try {
            $groups = (static function (string $root): array {
                $isBackOfficeShell = true;
                ob_start();
                try {
                    require $root . '/views/partials/back_office_sidebar.php';
                } finally {
                    ob_end_clean();
                }

                return $athNavGroups;
            })($root);
        } finally {
            $started->setValue(null, $wasStarted);
            \App\Core\Session::actAs([]);
            \App\Core\Gate::getInstance()->setPermissions([]);
            $_SERVER = $server;
            $_GET = $get;
        }
        $out = [];
        $walk = static function (array $nodes) use (&$walk, &$out): void {
            foreach ($nodes as $node) {
                $children = is_array($node['children'] ?? null) ? $node['children'] : [];
                $href = (string) ($node['href'] ?? '');
                if ($href !== '' && $children === []) {
                    $query = (string) parse_url($href, PHP_URL_QUERY);
                    $out[] = [(string) ($node['label'] ?? ''), Map::localPath($href) . ($query !== '' ? '?' . $query : ''), !empty($node['current'])];
                }
                $walk($children);
            }
        };
        foreach ($groups as $group) {
            $walk($group['items'] ?? []);
        }

        return $out;
    }

    /** Une seule entrée « Demandes RH » remplace Corrections de fiches, Demandes d'accès élevé et Mobilité. */
    public function testMenuHasOneHrRequestsEntryInsteadOfThree(): void
    {
        $rh = ['personnel.status.manage', 'personnel.profile.update', 'personnel.assignments.manage', 'personnel.grades.manage', 'organization.effectifs.hub.view'];
        foreach (['gestionnaire' => ['admin.organization', 'admin.access'], 'rh' => $rh, 'corrections seules' => ['personnel.profile.update']] as $profile => $permissions) {
            $links = self::menuLinks($permissions);
            $paths = array_column($links, 1);
            self::assertSame(1, count(array_keys($paths, '/back-office/demandes-rh', true)), $profile);
            self::assertContains(['Demandes RH', '/back-office/demandes-rh', false], $links, $profile);
            foreach (['/back-office/personnel/corrections', '/back-office/ressources/effectifs/elevations', '/back-office/ressources/effectifs/mobilite'] as $old) {
                self::assertNotContains($old, $paths, $profile . ' : ancienne entrée ' . $old);
            }
            foreach ($paths as $path) {
                self::assertStringNotContainsString('onglet=surveiller', $path, $profile);
            }
        }
        // Sans aucun des droits des trois anciens écrans : pas d'entrée.
        foreach ([[], ['organization.recruitment.manage'], ['admin.members.moderate']] as $permissions) {
            self::assertNotContains('/back-office/demandes-rh', array_column(self::menuLinks($permissions), 1));
        }
        // La page reste ouverte à ces profils : la route existe et n'est pas une redirection.
        $route = Map::match('GET', '/back-office/demandes-rh');
        self::assertNotNull($route);
        self::assertFalse(Map::isRedirect($route));
    }

    /** L'entrée est surlignée sur la page et sur chacun de ses onglets. */
    public function testHrRequestsEntryIsCurrentOnThePageAndItsTabs(): void
    {
        foreach (['/back-office/demandes-rh', '/back-office/demandes-rh?onglet=elevations', '/back-office/demandes-rh?onglet=corrections', '/back-office/demandes-rh?onglet=mobilite'] as $uri) {
            $current = array_values(array_filter(self::menuLinks(['admin.organization', 'admin.access'], $uri), static fn (array $l): bool => $l[2]));
            self::assertSame([['Demandes RH', '/back-office/demandes-rh', true]], $current, $uri);
        }
        // Ailleurs, elle ne l'est pas.
        $elsewhere = array_filter(self::menuLinks(['admin.organization', 'admin.access'], '/back-office/ressources/effectifs/a-traiter'), static fn (array $l): bool => $l[2]);
        self::assertSame(['À traiter'], array_column($elsewhere, 0));
    }

    /** Les trois pastilles (corrections, élévations, mobilité) s'additionnent sur l'entrée unique. */
    public function testHrRequestBadgesFollowTheSingleEntry(): void
    {
        $badges = (require Map::root() . '/config/back_office_menu.php')['badges'];
        foreach (['corrections', 'elevations', 'mobilite'] as $key) {
            self::assertSame('back-office/demandes-rh', $badges[$key]['path'], $key);
        }
        $rule = back_office_nav_rule_for_path('back-office/demandes-rh');
        self::assertIsArray($rule);
        foreach (\App\Services\Personnel\PersonnelCorrectionRequestService::STAFF_PERMISSIONS as $permission) {
            self::assertContains($permission, $rule['any_permissions']);
        }
        self::assertContains('organization.effectifs.hub.view', $rule['any_permissions']);
    }

    /** Demandes RH : une seule barre d'onglets par écran (Élévations et Mobilité ont déjà celle de la coque Effectifs). */
    public function testHrRequestsTabsShowOneTabBar(): void
    {
        $base = 'back-office/demandes-rh';
        foreach (['elevations', 'mobilite'] as $tab) {
            self::assertNull(\App\Support\BackOfficeTabs::barFor($base, $tab), $tab . ' : la coque Effectifs porte déjà sa barre');
        }
        $shell = (string) file_get_contents(Map::root() . '/views/admin/effectifs_workspace/shell.php');
        self::assertStringContainsString("['elevations', 'Élévations', 'elevations'", $shell);
        self::assertStringContainsString("['rh_mobility', 'Mobilité', 'mobilite'", $shell);
        // L'accueil et Corrections (hors coque Effectifs) gardent la barre commune, avec les quatre écrans.
        foreach (['', 'corrections'] as $tab) {
            $bar = \App\Support\BackOfficeTabs::barFor($base, $tab);
            self::assertIsArray($bar, $tab);
            self::assertSame(['Demandes RH', 'Élévations', 'Corrections', 'Mobilité'], array_column($bar, 'label'), $tab);
        }
        // Les autres familles ne changent pas.
        self::assertCount(2, \App\Support\BackOfficeTabs::barFor('back-office/missions', 'planification'));
    }

    /**
     * Fichiers retirés parce que plus rien ne les servait : chemin => mention qui ne doit plus apparaître dans le code.
     *
     * @var array<string, string>
     */
    private const REMOVED_FILES = [
        // Anciennes pages « Membres » du back-office : GET /back-office/users… redirigent vers Effectifs depuis longtemps.
        'views/admin/organization/users/index.php' => 'admin.organization.users.index',
        'views/admin/organization/users/show.php' => 'admin.organization.users.show',
        'views/admin/organization/users/edit.php' => 'admin.organization.users.edit',
        'views/partials/member_hub_nav.php' => 'member_hub_nav',
        'public/assets/css/back-office-users.css' => 'back-office-users.css',
        // Ancienne interface ATAK web : AtakController::index rend atak-overwatch-beta, plus rien ne rendait cette vue.
        'views/atak.php' => 'views/atak.php',
    ];

    /** Mentions tolérées, par fichier : étiquette passée à EmailPrivacy (aucun fichier lu), vérifications d'absence, commentaires. */
    private const REMOVED_MENTION_ALLOWED = [
        'tests/Unit/EmailPrivacyTest.php' => ['admin.organization.users.show'],
        // Tests qui vérifient justement l'absence de la vue, et commentaires de deux scripts de l'ancienne interface.
        'tests/Unit/AtakOverwatchBetaAssetTest.php' => ['views/atak.php'],
        'tests/Unit/AuditLot4Test.php' => ['views/atak.php'],
        'public/assets/js/map/atak-c2-bridge.js' => ['views/atak.php'],
        'public/assets/js/terrain3d/initTerrain3D.js' => ['views/atak.php'],
        'tests/Unit/VagueTriTest.php' => true,
    ];

    /** @return list<string> fichiers de code (PHP, JS, CSS, JSON) du site, hors dépendances et hors mod */
    private static function codeFiles(): array
    {
        $root = Map::root();
        $out = [];
        foreach (['app', 'bootstrap', 'config', 'database', 'lang', 'routes', 'views', 'tests', 'tools', 'scripts', 'public/assets/js', 'public/assets/css'] as $dir) {
            if (!is_dir($root . '/' . $dir)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if ($file->isFile() && in_array(strtolower($file->getExtension()), ['php', 'js', 'css', 'json', 'py'], true)) {
                    $out[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                }
            }
        }
        foreach (['index.php', 'public/index.php'] as $front) {
            if (is_file($root . '/' . $front)) {
                $out[] = $front;
            }
        }

        return $out;
    }

    public function testRemovedDeadFilesAreGoneWithTheirReferences(): void
    {
        $root = Map::root();
        foreach (array_keys(self::REMOVED_FILES) as $path) {
            self::assertFileDoesNotExist($root . '/' . $path);
        }
        $needles = array_values(array_unique(self::REMOVED_FILES));
        $found = [];
        foreach (self::codeFiles() as $rel) {
            $allowed = self::REMOVED_MENTION_ALLOWED[$rel] ?? [];
            if ($allowed === true) {
                continue;
            }
            $src = (string) file_get_contents($root . '/' . $rel);
            foreach ($needles as $needle) {
                if (!in_array($needle, $allowed, true) && str_contains($src, $needle)) {
                    $found[] = $rel . ' : ' . $needle;
                }
            }
        }
        self::assertSame([], $found, 'Référence à un fichier retiré par la vague de tri.');
    }

    /** Les méthodes de page sans route ont quitté UserAdminController ; ses actions (POST) et leurs routes restent. */
    public function testUserAdminControllerKeepsOnlyRoutedActions(): void
    {
        $class = \App\Controllers\Admin\Organization\UserAdminController::class;
        foreach (['index', 'show', 'edit'] as $gone) {
            self::assertFalse(method_exists($class, $gone), $gone);
        }
        $routed = [];
        foreach (Map::routes() as $route) {
            if (is_array($route['handler']) && $route['handler'][0] === $class) {
                $routed[$route['handler'][1]] = $route['method'];
            }
        }
        ksort($routed);
        self::assertSame([
            'applyDutyPosition' => 'POST', 'applyRoleSet' => 'POST', 'assignPosition' => 'POST', 'backfillDutyPositions' => 'POST',
            'deactivate' => 'POST', 'notifyProfileIncomplete' => 'POST', 'operatorPhoto' => 'POST', 'requestPurge' => 'POST',
            'resendVerificationEmail' => 'POST', 'store' => 'POST', 'update' => 'POST',
        ], $routed);
        foreach (array_keys($routed) as $action) {
            self::assertTrue(method_exists($class, $action), $action);
        }
        // Les anciennes adresses de page répondent toujours (301 vers Effectifs).
        foreach (['/back-office/users', '/back-office/users/7', '/back-office/users/7/edit', '/back-office/users/create'] as $legacy) {
            self::assertSame(301, Map::redirectOf(Map::match('GET', $legacy))[0], $legacy);
        }
    }

    /** Un cadre délégué ne voit « Overwatch » qu'une fois ; un simple membre le garde dans son espace. */
    public function testOverwatchAppearsOnceForEveryProfile(): void
    {
        foreach ([[], ['admin.organization', 'admin.access'], ['personnel.profile.update'], ['admin.members.moderate'], ['organization.recruitment.manage'], ['admin.roles.manage']] as $permissions) {
            $overwatch = array_filter(self::menuLinks($permissions), static fn (array $l): bool => $l[1] === '/atak');
            self::assertCount(1, $overwatch, implode(',', $permissions));
        }
    }
}
