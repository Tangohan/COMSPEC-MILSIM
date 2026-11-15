<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Gate;
use App\Core\Session;
use App\Support\BackOfficeLegacyRedirects;
use App\Support\BackOfficeTabs;
use PHPUnit\Framework\TestCase;
use Tests\Support\BackOfficeRouteMap as Map;

/**
 * Audit du back-office, lot 7.4 : le menu latéral calculé pour quelques profils, sans base de données.
 *
 * Le menu est celui que rend views/partials/back_office_sidebar.php pour un jeu de droits (Gate) donné, hors
 * communauté chargée (identifiant 0 : aucune lecture en base, formule complète). On en tire :
 *  - un instantané par profil (tests/fixtures/audit-backoffice/menu-<profil>.txt : groupe, libellé, chemin) ;
 *    pour le régénérer après un changement voulu du menu :  UPDATE_MENU_SNAPSHOTS=1 php vendor/bin/phpunit tests/Unit/BackOfficeMenuProfilesTest.php
 *  - des contrôles qui ne dépendent pas de l'instantané : chaque lien mène à une route, pas de doublon, pas
 *    d'onglet inconnu, pas de lien vers une ancienne adresse.
 */
final class BackOfficeMenuProfilesTest extends TestCase
{
    /**
     * Profils : nom => droits détenus. Le jeu de droits est celui d'un compte réel du profil, pas un rôle de test.
     *
     * @var array<string, list<string>>
     */
    private const PROFILES = [
        'gestionnaire' => ['admin.organization', 'admin.access'],
        'assistance' => ['site.support'],
        'rh' => ['personnel.status.manage', 'personnel.profile.update', 'personnel.assignments.manage', 'personnel.grades.manage', 'organization.effectifs.hub.view'],
        'recruteur' => ['organization.recruitment.manage'],
        'acces' => ['admin.roles.manage', 'admin.permissions.manage'],
        'moderateur' => ['admin.members.moderate'],
        'membre' => [],
    ];

    /**
     * Liens présents deux fois dans le menu d'un même profil : 'libellé | chemin' => raison.
     * Vide depuis la vague de tri : « Overwatch » n'est plus répété dans « Mon espace » d'un cadre délégué.
     *
     * @var array<string, string>
     */
    private const KNOWN_DUPLICATES = [];

    /**
     * Liens du menu dont la cible n'est pas une page directe : 'libellé | href' => raison.
     * Chacun est un écart réel à corriger (pointer vers l'onglet qui existe).
     * Vide depuis la vague de tri : « Dossiers RH » ouvre « À traiter », comme sa première page.
     *
     * @var array<string, string>
     */
    private const KNOWN_BAD_LINKS = [];

    /**
     * Liens montrés à un profil que la porte de leur route refuse : 'profil | chemin' => raison.
     * Chacun est un écart réel (menu et OrganizationAdminMiddleware ne sont pas en phase).
     *
     * @var array<string, string>
     */
    private const KNOWN_DEAD_LINKS = [
        'moderateur | /back-office/moderation' => 'Sanctions & absences est montrée à qui a admin.members.moderate, mais OrganizationAdminMiddleware n’a pas d’exception pour /back-office/moderation : le modérateur seul est renvoyé au portail',
    ];

    private static ?array $savedServer = null;

    protected function setUp(): void
    {
        self::$savedServer = $_SERVER;
        $_SERVER['REQUEST_URI'] = '/back-office';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_GET = [];
        $root = Map::root();
        require_once $root . '/app/Support/helpers.php';
        if (is_file($root . '/app/Support/navigation_menu.php')) {
            require_once $root . '/app/Support/navigation_menu.php';
        }
    }

    protected function tearDown(): void
    {
        Session::actAs([]);
        Gate::getInstance()->setPermissions([]);
        if (self::$savedServer !== null) {
            $_SERVER = self::$savedServer;
        }
    }

    /**
     * Arbre du menu pour un jeu de droits : groupes → entrées → pages.
     *
     * @param list<string> $permissions
     * @return list<array<string, mixed>>
     */
    private static function tree(array $permissions): array
    {
        Session::actAs(['tenant_id' => 0, 'user_id' => 0, 'display_name' => 'Profil de test']);
        Gate::getInstance()->setPermissions($permissions);
        $render = static function (string $root): array {
            $isBackOfficeShell = true;
            ob_start();
            try {
                require $root . '/views/partials/back_office_sidebar.php';
            } finally {
                ob_end_clean();
            }

            return $athNavGroups;
        };

        return $render(Map::root());
    }

    /**
     * Pages (liens avec adresse) du menu, dans l'ordre : [groupe, libellé, href, profondeur, a-des-enfants].
     *
     * @param list<array<string, mixed>> $groups
     * @return list<array{0:string,1:string,2:string,3:int,4:bool}>
     */
    private static function entries(array $groups): array
    {
        $out = [];
        $walk = static function (array $nodes, string $group, int $depth) use (&$walk, &$out): void {
            foreach ($nodes as $node) {
                $children = is_array($node['children'] ?? null) ? $node['children'] : [];
                $href = (string) ($node['href'] ?? '');
                if ($href !== '' || $children !== []) {
                    $out[] = [$group, (string) ($node['label'] ?? ''), $href === '' ? '' : self::normalize($href), $depth, $children !== []];
                }
                if ($children !== []) {
                    $walk($children, $group, $depth + 1);
                }
            }
        };
        foreach ($groups as $group) {
            $walk($group['items'] ?? [], (string) ($group['label'] ?? $group['key'] ?? ''), 0);
        }

        return $out;
    }

    /** Adresse sans domaine ni préfixe de déploiement, requête conservée. */
    private static function normalize(string $href): string
    {
        $query = (string) parse_url($href, PHP_URL_QUERY);

        return Map::localPath($href) . ($query !== '' ? '?' . $query : '');
    }

    private static function snapshot(array $groups): string
    {
        $lines = [];
        $current = '';
        foreach (self::entries($groups) as [$group, $label, $href, $depth]) {
            if ($group !== $current) {
                $lines[] = '# ' . $group;
                $current = $group;
            }
            $lines[] = str_repeat('  ', $depth) . $label . ($href === '' ? '' : ' | ' . $href);
        }

        return implode("\n", $lines) . "\n";
    }

    private static function fixture(string $profile): string
    {
        return Map::root() . '/tests/fixtures/audit-backoffice/menu-' . $profile . '.txt';
    }

    public function testMenuSnapshotMatchesForEachProfile(): void
    {
        $update = getenv('UPDATE_MENU_SNAPSHOTS') === '1';
        $bad = [];
        foreach (self::PROFILES as $profile => $permissions) {
            $actual = self::snapshot(self::tree($permissions));
            $file = self::fixture($profile);
            if ($update) {
                if (!is_dir(dirname($file))) {
                    mkdir(dirname($file), 0775, true);
                }
                file_put_contents($file, $actual);
                continue;
            }
            if (!is_file($file)) {
                $bad[] = $profile . ' : instantané absent (UPDATE_MENU_SNAPSHOTS=1 pour le créer)';
                continue;
            }
            $expected = str_replace("\r\n", "\n", (string) file_get_contents($file));
            if ($expected !== $actual) {
                $diff = array_values(array_diff(explode("\n", $actual), explode("\n", $expected)));
                $gone = array_values(array_diff(explode("\n", $expected), explode("\n", $actual)));
                $bad[] = $profile . ' : menu changé. Ajouté : ' . json_encode(array_slice($diff, 0, 5), JSON_UNESCAPED_UNICODE)
                    . ' ; retiré : ' . json_encode(array_slice($gone, 0, 5), JSON_UNESCAPED_UNICODE);
            }
        }
        self::assertSame([], $bad, 'Le menu a changé : relire la différence, puis régénérer les instantanés si le changement est voulu.');
    }

    public function testEachProfileSeesAMenuAndTheManagerSeesTheMost(): void
    {
        $count = [];
        foreach (self::PROFILES as $profile => $permissions) {
            $links = array_filter(self::entries(self::tree($permissions)), static fn (array $e): bool => $e[2] !== '');
            self::assertNotSame([], $links, $profile . ' : menu vide');
            $count[$profile] = count($links);
        }
        foreach ($count as $profile => $n) {
            if ($profile !== 'gestionnaire') {
                self::assertLessThan($count['gestionnaire'], $n, $profile . ' voit autant de pages que le gestionnaire');
            }
        }
        // Le membre simple n'a aucune page de gestion.
        $member = array_map(static fn (array $e): string => $e[2], self::entries(self::tree([])));
        foreach ($member as $href) {
            self::assertStringNotContainsString('/back-office/ressources/effectifs', $href);
            self::assertStringNotContainsString('/back-office/recruitments', $href);
        }
    }

    /** Aucun lien du menu ne mène à un chemin sans route, ni à une ancienne adresse, ni à un onglet inconnu. */
    public function testEveryMenuLinkResolvesToARouteInOneHop(): void
    {
        $bad = [];
        foreach (self::PROFILES as $profile => $permissions) {
            foreach (self::entries(self::tree($permissions)) as [, $label, $href]) {
                if ($href === '' || str_starts_with($href, '#') || preg_match('#^https?://#', $href) === 1) {
                    continue;
                }
                if (isset(self::KNOWN_BAD_LINKS[$label . ' | ' . $href])) {
                    continue;
                }
                $path = (string) strtok($href, '?');
                $route = Map::match('GET', $path);
                if ($route === null) {
                    $bad[] = $profile . ' : « ' . $label . ' » → ' . $href . ' : aucune route';
                    continue;
                }
                if (Map::isRedirect($route) || isset(BackOfficeLegacyRedirects::map()[trim($path, '/')])) {
                    $bad[] = $profile . ' : « ' . $label . ' » → ' . $href . ' : ancienne adresse (redirection)';
                    continue;
                }
                parse_str((string) parse_url($href, PHP_URL_QUERY), $query);
                $tab = (string) ($query['onglet'] ?? '');
                $family = BackOfficeTabs::FAMILIES[trim($path, '/')] ?? null;
                if ($tab !== '' && $family !== null && !isset($family['tabs'][$tab])) {
                    $bad[] = $profile . ' : « ' . $label . ' » → ' . $href . ' : onglet inconnu';
                }
            }
        }
        self::assertSame([], $bad);
    }

    /** Une même destination n'apparaît pas deux fois dans le menu d'un profil. */
    public function testNoDuplicateEntryInAProfileMenu(): void
    {
        $bad = [];
        foreach (self::PROFILES as $profile => $permissions) {
            $seen = [];
            foreach (self::entries(self::tree($permissions)) as [, $label, $href, , $hasChildren]) {
                // Une entrée qui ouvre un sous-menu partage l'adresse de sa première page : seule une page feuille compte.
                if ($href === '' || $hasChildren) {
                    continue;
                }
                $key = $label . ' | ' . $href;
                $seen[$key] = ($seen[$key] ?? 0) + 1;
            }
            foreach ($seen as $key => $n) {
                if ($n > 1 && !isset(self::KNOWN_DUPLICATES[$key])) {
                    $bad[] = $profile . ' : ' . $key . ' (x' . $n . ')';
                }
            }
        }
        self::assertSame([], $bad);
    }

    /**
     * Un lien du menu doit s'ouvrir pour le profil à qui on le montre : la porte de la route (OrganizationAdminMiddleware,
     * TenantResourceAdminMiddleware) est évaluée avec les droits du profil. Un lien refusé est un lien mort.
     */
    public function testEveryMenuLinkOpensForTheProfileThatSeesIt(): void
    {
        $bad = [];
        foreach (self::PROFILES as $profile => $permissions) {
            $links = self::entries(self::tree($permissions));
            foreach ($links as [, $label, $href]) {
                if ($href === '') {
                    continue;
                }
                $path = (string) strtok($href, '?');
                $route = Map::match('GET', $path);
                if ($route === null) {
                    continue;
                }
                $names = Map::middlewareNames($route);
                if ($names === [] && isset(BackOfficeTabs::FAMILIES[trim($path, '/')])) {
                    $names = array_map(static fn (string $c): string => substr($c, (int) strrpos($c, '\\') + 1), BackOfficeTabs::FAMILIES[trim($path, '/')]['middleware']);
                }
                if (self::gateDenies($path, $permissions, $names) && !isset(self::KNOWN_DEAD_LINKS[$profile . ' | ' . $path])) {
                    $bad[] = $profile . ' : « ' . $label . ' » → ' . $path . ' [' . implode(',', $names) . ']';
                }
            }
        }
        self::assertSame([], $bad, 'Lien du menu refusé par la porte de sa route pour le profil qui le voit.');
    }

    /**
     * Vrai si OrganizationAdminMiddleware ou TenantResourceAdminMiddleware refuse ce profil sur cette page
     * (redirection vers le portail ; une communauté absente, elle, redirige ailleurs : ce n'est pas un refus de droit).
     *
     * @param list<string> $permissions
     * @param list<string> $middlewareNames
     */
    private static function gateDenies(string $path, array $permissions, array $middlewareNames): bool
    {
        $server = $_SERVER;
        $_SERVER['REQUEST_URI'] = $path;
        $_SESSION = [];
        Session::actAs(['tenant_id' => 0, 'user_id' => 5]);
        Gate::getInstance()->setPermissions($permissions);
        try {
            $request = new \App\Core\Request();
            foreach ($middlewareNames as $name) {
                if (!in_array($name, ['OrganizationAdminMiddleware', 'TenantResourceAdminMiddleware'], true)) {
                    continue;
                }
                $class = 'App\\Middleware\\' . $name;
                $response = (new $class())($request, static fn (): \App\Core\Response => new \App\Core\Response());
                $location = (string) $response->headerValue('Location');
                if ($location !== '') {
                    return str_ends_with($location, '/dashboard');
                }
            }

            return false;
        } finally {
            $_SERVER = $server;
            $_SESSION = [];
            Session::actAs([]);
        }
    }

    /** Liens rapides des en-têtes de page (config/back_office_pages.php) : chaque adresse a une route. */
    public function testQuickLinksOfPageHeadsHaveRoutes(): void
    {
        $bad = [];
        $pages = (require Map::root() . '/config/back_office_pages.php')['pages'];
        foreach ($pages as $page) {
            foreach ($page['quick'] ?? [] as $quick) {
                $href = is_array($quick) ? (string) ($quick['href'] ?? '') : '';
                $path = '/' . trim((string) strtok($href, '?#'), '/');
                if ($href === '' || str_contains($path, '{') || preg_match('#^/https?:#', $path) === 1) {
                    continue;
                }
                if (Map::match('GET', $path) === null) {
                    $bad[] = $page['path'] . ' → ' . $href;
                }
            }
        }
        self::assertSame([], $bad, 'Lien rapide d’en-tête vers un chemin sans route.');
    }
}
