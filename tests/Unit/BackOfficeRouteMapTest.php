<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\BackOfficeTabs;
use PHPUnit\Framework\TestCase;
use Tests\Support\BackOfficeRouteMap as Map;

/**
 * Audit du back-office, lot 7.1 : carte des routes, lue dans routes/web.php (sans base ni serveur).
 *
 * Les écarts déjà connus sont figés ci-dessous, chacun avec sa raison : le test empêche les NOUVEAUX écarts
 * (une route de gestion sans connexion, sans middleware de droit, ou un contrôleur qui s'en remet entièrement
 * au middleware). Quand un écart figé est corrigé, la ligne devient inutile : on la retire.
 */
final class BackOfficeRouteMapTest extends TestCase
{
    /** Middlewares qui vérifient un droit d'administration ou de gestion (en plus de la connexion). */
    private const GUARDS = [
        'OrganizationAdminMiddleware',
        'TenantResourceAdminMiddleware',
        'SystemAdminMiddleware',
        'OperationalBoardEditMiddleware',
        'ModerationConsoleMiddleware',
        'InvitationSenderMiddleware',
        'TrainingTenantResourceMiddleware',
        'InterteamMissionsAccessMiddleware',
        'TenantMemberModerationMiddleware',
    ];

    /**
     * Contrôleurs du back-office servis avec la seule connexion : le droit est vérifié dans le contrôleur
     * (une méthode de refus appelée au début de chaque action). Nouvelle entrée = décision à justifier.
     *
     * @var array<string, string>
     */
    private const AUTH_ONLY_CONTROLLERS = [
        'PersonnelCorrectionController' => 'canStaffManage / canBatch dans le contrôleur (file de corrections, 01 F35)',
        'AdminSseAccessController' => 'denyUnlessGrant() : habilitation SSE (renseignement)',
        'SsePortalController' => 'staffEnter() : porte du portail SSE',
        'AdminSseFilesController' => 'deny() : habilitation SSE (fichiers)',
        'BackOfficeMenuController' => 'pastilles et favoris : données du menu de l’utilisateur lui-même',
        'DashboardPinsAdminController' => 'denyUnlessManage() à chaque action',
        'DashboardWardrobePinsAdminController' => 'denyUnlessManage() à chaque action (create() passe par formView())',
        'AdminIntelIntakeController' => 'guard() à chaque action (remontées de renseignement)',
        'AdminAarReportsController' => 'ModuleFeatureAccess::guardAtak() à chaque action (synthèses)',
        'SystemTenantInterventionController' => 'leave() : sortie de sa propre session d’intervention, jeton CSRF',
    ];

    /**
     * Routes qui n'appellent pas de méthode de refus dans leur propre corps (la garde est dans une méthode privée
     * appelée plus loin) : 'MÉTHODE chemin' => raison.
     *
     * @var array<string, string>
     */
    private const AUTH_ONLY_GUARD_ELSEWHERE = [
        'GET /back-office/dashboard-tenues/create' => 'formView() commence par denyUnlessManage()',
        'POST /admin/system/tenant-intervention/leave' => 'agit sur la session d’intervention de l’appelant (CSRF)',
    ];

    /**
     * Contrôleurs derrière « Auth + OrganizationAdminMiddleware » dont le code n'a AUCUNE vérification de droit :
     * tout repose sur le middleware, qui laisse passer `site.support` sur tout /back-office (lecture ET écriture).
     * C'est l'écart S6 resté ouvert après le lot 0 (qui n'a corrigé que OrganizationSettingsController).
     * Ne pas ajouter de contrôleur ici : ajouter un contrôle de droit dans le contrôleur.
     *
     * @var list<string>
     */
    private const KNOWN_ORG_ONLY_CONTROLLERS = [
        'MemberCoverExportController',
        'MemberSituationController',
        'MemberAdvancementController',
        'OrganizationSeniorityAdminController',
        'PersonnelPassAdminController',
        'TenantInitialSetupController',
        'OrganizationCommunityController',
        'LegacyPersonnelRedirectController',
        'OrganizationAuditController',
        'MissionsPortalController',
        'CategoryAdminController',
        'GradeReferentielController',
        'RankCatalogAdminController',
        'QualificationRulesController',
        'QualificationPrerequisiteController',
        'AwardReferentielController',
        'EquipmentReferentielController',
        'CompetencyMatrixController',
        'GroupAdminController',
        'AdminUnitsController',
        'TeamAdminController',
        'PersonnelPhaseAdminController',
        'AtakMarkerDetectionAdminController',
        'RoleplayGameSessionAdminController',
        'TenantMiniArticlesController',
        'TenantAlertsController',
        'AdminRecruitmentDiscordQuestionsController',
        'RecruitmentInviteCodesController',
        'RecruitmentOffersController',
    ];

    /**
     * Routes masquées par une route enregistrée avant elles (le Router retient la première qui correspond).
     * Vide depuis la vague de tri (media/collections déclarée avant media/{id}, doublon GET /admin/users retiré).
     *
     * @var array<string, string>
     */
    private const KNOWN_SHADOWED = [];

    /** Chemins cités par l'inventaire qui ne sont qu'un préfixe, sans page. */
    private const INVENTORY_PREFIXES = [
        '/back-office/ressources',
    ];

    private const GUARD_TOKENS = '/(deny|guard|forbidden|allows\w*\(|\bcan[A-Z]\w*\(|abort|403|Gate::|requirePerm\w*\(|isAllowed\(|mayAccess\w*\(|authoriz|ModuleFeatureAccess)/i';

    /** @return list<array{method:string,path:string,pattern:string,handler:mixed,middleware:array}> */
    private static function backOfficeRoutes(): array
    {
        return array_values(array_filter(
            Map::routes(),
            static fn (array $r): bool => Map::isBackOfficePath($r['path']) && !Map::isRedirect($r)
        ));
    }

    /** Page d'accueil d'une famille d'onglets (BackOfficeTabs) : la garde est dans la famille. */
    private static function isTabDispatcher(array $route): bool
    {
        return $route['method'] === 'GET'
            && isset(BackOfficeTabs::FAMILIES[ltrim($route['path'], '/')])
            && $route['middleware'] === [];
    }

    private static function short(string $class): string
    {
        return substr($class, (int) strrpos($class, '\\') + 1);
    }

    /** @return list<string> */
    private static function guardsOf(array $route): array
    {
        return array_values(array_intersect(Map::middlewareNames($route), self::GUARDS));
    }

    public function testTheRouteTableIsLoadedAndHasTheBackOffice(): void
    {
        $routes = self::backOfficeRoutes();
        self::assertGreaterThan(1000, count($routes), 'carte des routes du back-office (hors redirections)');
        self::assertNotNull(Map::match('GET', '/back-office'));
        self::assertNotNull(Map::match('GET', '/back-office/ressources/effectifs'));
    }

    /** Toute route de gestion exige une session ; seules les familles d'onglets s'en remettent à leur famille. */
    public function testEveryBackOfficeRouteRequiresALogin(): void
    {
        $bad = [];
        foreach (self::backOfficeRoutes() as $route) {
            if (self::isTabDispatcher($route)) {
                continue;
            }
            if ((Map::middlewareNames($route)[0] ?? '') !== 'AuthMiddleware') {
                $bad[] = Map::key($route) . ' [' . implode(',', Map::middlewareNames($route)) . ']';
            }
        }
        self::assertSame([], $bad, 'Routes du back-office sans AuthMiddleware en premier.');
    }

    /** Toute route de gestion a un middleware de droit, ou un contrôleur connu qui vérifie le droit lui-même. */
    public function testEveryBackOfficeRouteHasARightMiddlewareOrAKnownGuardedController(): void
    {
        $bad = [];
        foreach (self::backOfficeRoutes() as $route) {
            if (self::isTabDispatcher($route) || self::guardsOf($route) !== []) {
                continue;
            }
            $controller = is_array($route['handler']) ? self::short($route['handler'][0]) : '';
            if (!isset(self::AUTH_ONLY_CONTROLLERS[$controller])) {
                $bad[] = Map::key($route) . ' [' . implode(',', Map::middlewareNames($route)) . '] ' . ($controller === '' ? 'fonction anonyme' : $controller);
            }
        }
        self::assertSame([], $bad, 'Routes du back-office sans middleware de droit et hors liste des contrôleurs qui vérifient le droit eux-mêmes.');
    }

    /** La plateforme (/admin) est réservée à l'administrateur de la plateforme. */
    public function testPlatformAdminRoutesNeedTheSystemAdminMiddleware(): void
    {
        $bad = [];
        foreach (self::backOfficeRoutes() as $route) {
            if (!str_starts_with($route['path'], '/admin') || isset(self::AUTH_ONLY_GUARD_ELSEWHERE[Map::key($route)])) {
                continue;
            }
            if (!in_array('SystemAdminMiddleware', Map::middlewareNames($route), true)) {
                $bad[] = Map::key($route) . ' [' . implode(',', Map::middlewareNames($route)) . ']';
            }
        }
        self::assertSame([], $bad);
    }

    /** Liste des routes de gestion qui n'ont que la connexion : chacune appelle une méthode de refus dans son corps. */
    public function testAuthOnlyRoutesCheckARightInTheController(): void
    {
        $bad = [];
        $seen = [];
        foreach (self::backOfficeRoutes() as $route) {
            if (Map::middlewareNames($route) !== ['AuthMiddleware'] || !is_array($route['handler'])) {
                continue;
            }
            $seen[self::short($route['handler'][0])] = true;
            if (isset(self::AUTH_ONLY_GUARD_ELSEWHERE[Map::key($route)])) {
                continue;
            }
            if (preg_match(self::GUARD_TOKENS, Map::handlerSource($route)) !== 1) {
                $bad[] = Map::key($route) . ' ' . self::short($route['handler'][0]) . '::' . $route['handler'][1];
            }
        }
        self::assertSame([], $bad, 'Route servie avec la seule connexion dont la méthode ne vérifie aucun droit.');
        // Les contrôleurs figés doivent encore exister dans la carte : sinon la liste est périmée.
        self::assertSame([], array_values(array_diff(array_keys($seen), array_keys(self::AUTH_ONLY_CONTROLLERS))), 'Contrôleur à connexion seule non déclaré dans AUTH_ONLY_CONTROLLERS.');
    }

    /**
     * Familles dont chaque onglet garde la garde de SON ancienne page (elles diffèrent d'un onglet à l'autre) et dont
     * l'accueil vérifie le droit dans le contrôleur : 'famille' => ['onglet' => middlewares attendus].
     *
     * @var array<string, array<string, list<string>>>
     */
    private const MIXED_GUARD_FAMILIES = [
        // Demandes RH : l'accueil (HrRequestsController) ne liste que les types lisibles (HrRequestsDesk::visibleTypes) ;
        // Corrections reste à connexion seule comme son ancienne page (PersonnelCorrectionController, AUTH_ONLY_CONTROLLERS).
        'back-office/demandes-rh' => [
            'elevations' => ['AuthMiddleware', 'OrganizationAdminMiddleware'],
            'corrections' => ['AuthMiddleware'],
            'mobilite' => ['AuthMiddleware', 'OrganizationAdminMiddleware'],
        ],
    ];

    /** Chaque onglet réutilise la garde de sa famille : connexion puis droit de gestion, jamais plus lâche. */
    public function testTabFamiliesKeepTheirGuards(): void
    {
        $bad = [];
        foreach (BackOfficeTabs::FAMILIES as $base => $family) {
            $route = Map::match('GET', '/' . $base);
            if ($route === null || !self::isTabDispatcher($route)) {
                $bad[] = $base . ' : pas de route d’accueil servie par BackOfficeTabs::dispatch';
                continue;
            }
            if (isset(self::MIXED_GUARD_FAMILIES[$base])) {
                $home = new \ReflectionMethod($family['handler'][0], $family['handler'][1]);
                $homeSrc = implode('', array_slice(file((string) $home->getFileName()) ?: [], $home->getStartLine() - 1, $home->getEndLine() - $home->getStartLine() + 1));
                if (array_map(self::short(...), $family['middleware']) !== ['AuthMiddleware'] || !str_contains($homeSrc, 'typesForViewer')) {
                    $bad[] = $base . ' : l’accueil doit exiger la connexion et filtrer les types lisibles';
                }
                $tabs = [];
                foreach ($family['tabs'] as $key => $tab) {
                    $tabs[$key] = array_map(self::short(...), $tab['middleware']);
                    if ($tabs[$key] === ['AuthMiddleware'] && !isset(self::AUTH_ONLY_CONTROLLERS[self::short($tab['handler'][0])])) {
                        $bad[] = $base . '?onglet=' . $key . ' : connexion seule hors contrôleur déclaré';
                    }
                }
                if ($tabs !== self::MIXED_GUARD_FAMILIES[$base]) {
                    $bad[] = $base . ' : gardes d’onglets différentes de celles déclarées';
                }
                continue;
            }
            $mw = array_map(self::short(...), $family['middleware']);
            if (($mw[0] ?? '') !== 'AuthMiddleware' || array_intersect($mw, self::GUARDS) === []) {
                $bad[] = $base . ' : middlewares de famille ' . implode(',', $mw);
            }
            foreach ($family['tabs'] as $key => $tab) {
                if (array_map(self::short(...), $tab['middleware']) !== $mw) {
                    $bad[] = $base . '?onglet=' . $key . ' : garde différente de la famille';
                }
            }
        }
        self::assertSame([], $bad);
    }

    /**
     * Ratchet S6 : un contrôleur qui n'a aucune vérification de droit et qui est servi derrière le seul
     * « Auth + OrganizationAdminMiddleware » (qui admet `site.support`) ne doit pas apparaître de nouveau.
     */
    public function testNoNewControllerReliesOnlyOnTheOrganizationMiddleware(): void
    {
        $byController = [];
        foreach (self::backOfficeRoutes() as $route) {
            if (!is_array($route['handler']) || !str_starts_with($route['path'], '/back-office') && !str_starts_with($route['path'], '/backoffice')) {
                continue;
            }
            if (Map::middlewareNames($route) === ['AuthMiddleware', 'OrganizationAdminMiddleware']) {
                $byController[$route['handler'][0]] = true;
            }
        }
        $unguarded = [];
        foreach (array_keys($byController) as $class) {
            $file = (new \ReflectionClass($class))->getFileName();
            if ($file !== false && preg_match(self::GUARD_TOKENS, (string) file_get_contents($file)) !== 1) {
                $unguarded[] = self::short($class);
            }
        }
        sort($unguarded);
        $new = array_values(array_diff($unguarded, self::KNOWN_ORG_ONLY_CONTROLLERS));
        self::assertSame([], $new, 'Nouveau contrôleur de gestion sans aucun contrôle de droit interne (il ne repose que sur OrganizationAdminMiddleware, qui admet site.support).');
    }

    /** Une route masquée par une autre ne répond jamais : c'est un écart figé, pas une nouveauté. */
    public function testNoRouteIsShadowedByAnEarlierOne(): void
    {
        $found = [];
        $routes = Map::routes();
        foreach ($routes as $i => $route) {
            $sample = Map::samplePath($route['path']);
            foreach ($routes as $j => $other) {
                if ($other['method'] !== $route['method'] || preg_match($other['pattern'], $sample) !== 1) {
                    continue;
                }
                if ($j < $i) {
                    $found[Map::key($route)] = Map::key($other);
                }
                break;
            }
        }
        $new = array_diff_key($found, self::KNOWN_SHADOWED);
        self::assertSame([], $new, 'Nouvelle route masquée par une route enregistrée avant elle (clé = route masquée, valeur = celle qui répond).');
    }

    /** @return list<string> chemins du back-office cités dans l'inventaire (document 1) */
    private static function inventoryPaths(): array
    {
        $doc = (string) file_get_contents(Map::root() . '/docs/audit-backoffice/01-inventaire.md');
        preg_match_all('/`([^`]+)`/', $doc, $m);
        $out = [];
        foreach ($m[1] as $token) {
            if (preg_match('#^(?:GET/POST |GET |POST )?((?:EFF|BO)?/[A-Za-z0-9_\-/{}.]*|EFF|BO)$#', trim($token), $mm) !== 1) {
                continue;
            }
            $p = $mm[1];
            if ($p === 'EFF') {
                $p = '/back-office/ressources/effectifs';
            } elseif ($p === 'BO') {
                $p = '/back-office';
            } elseif (str_starts_with($p, 'EFF/')) {
                $p = '/back-office/ressources/effectifs' . substr($p, 3);
            } elseif (str_starts_with($p, 'BO/')) {
                $p = '/back-office' . substr($p, 2);
            }
            if (Map::isBackOfficePath($p)) {
                $out[$p] = true;
            }
        }

        return array_keys($out);
    }

    /** Chaque page citée par l'inventaire (document 1) a encore une route. */
    public function testEveryInventoryPathStillHasARoute(): void
    {
        $paths = self::inventoryPaths();
        self::assertGreaterThan(200, count($paths), 'l’inventaire doit citer plus de 200 chemins du back-office');
        $missing = [];
        foreach ($paths as $path) {
            if (in_array($path, self::INVENTORY_PREFIXES, true)) {
                continue;
            }
            $sample = Map::samplePath($path);
            $found = false;
            foreach (Map::routes() as $route) {
                if (preg_match($route['pattern'], $sample) === 1) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $missing[] = $path;
            }
        }
        self::assertSame([], $missing, 'Chemins de l’inventaire (docs/audit-backoffice/01-inventaire.md) sans route.');
    }

    /** Chaque page de l'inventaire répond avec une connexion et un droit (ou une redirection, ou une famille d'onglets). */
    public function testEveryInventoryPageIsGuarded(): void
    {
        $bad = [];
        foreach (self::inventoryPaths() as $path) {
            if (in_array($path, self::INVENTORY_PREFIXES, true)) {
                continue;
            }
            foreach (['GET', 'POST'] as $method) {
                $route = Map::match($method, Map::samplePath($path));
                if ($route === null || Map::isRedirect($route) || self::isTabDispatcher($route)) {
                    continue;
                }
                $controller = is_array($route['handler']) ? self::short($route['handler'][0]) : '';
                $names = Map::middlewareNames($route);
                $ok = ($names[0] ?? '') === 'AuthMiddleware'
                    && (self::guardsOf($route) !== [] || isset(self::AUTH_ONLY_CONTROLLERS[$controller]));
                if (!$ok) {
                    $bad[] = Map::key($route) . ' [' . implode(',', $names) . ']';
                }
            }
        }
        self::assertSame([], array_values(array_unique($bad)));
    }
}
