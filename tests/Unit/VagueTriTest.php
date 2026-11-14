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

    /** Un cadre délégué ne voit « Overwatch » qu'une fois ; un simple membre le garde dans son espace. */
    public function testOverwatchAppearsOnceForEveryProfile(): void
    {
        foreach ([[], ['admin.organization', 'admin.access'], ['personnel.profile.update'], ['admin.members.moderate'], ['organization.recruitment.manage'], ['admin.roles.manage']] as $permissions) {
            $overwatch = array_filter(self::menuLinks($permissions), static fn (array $l): bool => $l[1] === '/atak');
            self::assertCount(1, $overwatch, implode(',', $permissions));
        }
    }
}
