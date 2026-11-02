<?php

declare(strict_types=1);

namespace App\Support;

use App\Controllers\Admin\AdminAarReportsController;
use App\Controllers\Admin\AdminAtakDebriefController;
use App\Controllers\Admin\AdminAtakRealismConfigController;
use App\Controllers\Admin\AdminAtakRealismController;
use App\Controllers\Admin\AdminAtakSquadsController;
use App\Controllers\Admin\AdminFireTeamsController;
use App\Controllers\Admin\AdminPlayerMetricsController;
use App\Controllers\Admin\Organization\AdminDoctrineController;
use App\Controllers\Admin\Organization\ConfigurationUpdateController;
use App\Controllers\Admin\Organization\DoctrineAdminController;
use App\Controllers\Admin\Organization\MissionPlanningController;
use App\Controllers\Admin\Organization\MissionsPortalController;
use App\Controllers\Admin\Organization\PersonnelPhaseAdminController;
use App\Controllers\Admin\Organization\RoleplayFollowupAdminController;
use App\Controllers\Admin\RhDossierWorkspaceController;
use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Middleware\AuthMiddleware;
use App\Middleware\OrganizationAdminMiddleware;
use App\Middleware\TenantResourceAdminMiddleware;
use App\Services\Community\TenantTypeConfig;

/**
 * Pages du back-office regroupées en onglets (« ?onglet=… ») sur une page d’accueil commune.
 *
 * Chaque onglet réutilise tel quel le contrôleur et la vue de l’ancienne page : il est servi
 * comme si l’ancienne adresse était demandée (mêmes middlewares, même chemin vu par le contrôleur),
 * si bien que les droits et le comportement ne changent pas. L’ancienne adresse répond 301
 * vers l’onglet (voir BackOfficeLegacyRedirects) ; ses formulaires POST gardent leurs adresses.
 */
final class BackOfficeTabs
{
    private const ORG = [AuthMiddleware::class, OrganizationAdminMiddleware::class];
    private const RES = [AuthMiddleware::class, TenantResourceAdminMiddleware::class];

    /**
     * Page d’accueil (chemin sans slash) → onglet par défaut + onglets repris d’anciennes pages.
     * « bar » = afficher la barre d’onglets commune (false quand la page a déjà la sienne).
     *
     * @var array<string, array{label: string, handler: array{0: class-string, 1: string}, middleware: list<class-string>, bar: bool, tabs: array<string, array{label: string, legacy: string, handler: array{0: class-string, 1: string}, middleware: list<class-string>}>}>
     */
    public const FAMILIES = [
        'back-office/missions' => [
            'label' => 'Portail missions',
            'handler' => [MissionsPortalController::class, 'index'],
            'middleware' => self::ORG,
            'bar' => true,
            'tabs' => [
                'planification' => ['label' => 'Planification', 'legacy' => 'back-office/planification', 'handler' => [MissionPlanningController::class, 'index'], 'middleware' => self::ORG],
            ],
        ],
        'back-office/atak/comptes-rendus' => [
            'label' => 'Comptes rendus post-op',
            'handler' => [AdminAarReportsController::class, 'index'],
            'middleware' => self::RES,
            'bar' => true,
            'tabs' => [
                'suivi' => ['label' => 'CR des joueurs (fin d’op)', 'legacy' => 'back-office/atak/suivi-cr', 'handler' => [AdminAtakDebriefController::class, 'index'], 'middleware' => self::RES],
            ],
        ],
        'back-office/roleplay-followup' => [
            'label' => 'Bureau de suivi',
            'handler' => [RoleplayFollowupAdminController::class, 'index'],
            'middleware' => self::ORG,
            'bar' => true,
            'tabs' => [
                'dossiers' => ['label' => 'Dossiers roleplay', 'legacy' => 'back-office/ressources/effectifs/roleplay', 'handler' => [RhDossierWorkspaceController::class, 'roleplay'], 'middleware' => self::ORG],
                'immersion' => ['label' => 'Parcours d’immersion', 'legacy' => 'back-office/roleplay/immersion', 'handler' => [RoleplayFollowupAdminController::class, 'immersionSettings'], 'middleware' => self::ORG],
                'phases' => ['label' => 'Parcours RH', 'legacy' => 'back-office/roleplay/regles-phases', 'handler' => [PersonnelPhaseAdminController::class, 'index'], 'middleware' => self::ORG],
            ],
        ],
        'back-office/metriques-joueurs' => [
            'label' => 'Vue d’ensemble',
            'handler' => [AdminPlayerMetricsController::class, 'index'],
            'middleware' => self::RES,
            'bar' => true,
            'tabs' => [
                'temps-ecran' => ['label' => 'Temps d’écran et rôles', 'legacy' => 'back-office/atak/temps-ecran', 'handler' => [AdminAtakSquadsController::class, 'screenTime'], 'middleware' => self::RES],
            ],
        ],
        'back-office/atak/escouades' => [
            'label' => 'Escouades en jeu',
            'handler' => [AdminAtakSquadsController::class, 'index'],
            'middleware' => self::RES,
            'bar' => true,
            'tabs' => [
                'fire-teams' => ['label' => 'Équipes de feu', 'legacy' => 'back-office/atak/fire-teams', 'handler' => [AdminFireTeamsController::class, 'index'], 'middleware' => self::RES],
            ],
        ],
        'back-office/atak/realisme' => [
            'label' => 'Parc de terminaux',
            'handler' => [AdminAtakRealismController::class, 'index'],
            'middleware' => self::RES,
            'bar' => true,
            'tabs' => [
                'reglages' => ['label' => 'Réglages réalisme', 'legacy' => 'back-office/ressources/atak-realism/config', 'handler' => [AdminAtakRealismConfigController::class, 'index'], 'middleware' => self::RES],
            ],
        ],
        'back-office/doctrine' => [
            'label' => 'Doctrine et SOP',
            'handler' => [DoctrineAdminController::class, 'index'],
            'middleware' => self::ORG,
            // Le module Doctrine a déjà ses onglets (dct_head, views/admin/documents/partials/doctrine_ui.php).
            'bar' => false,
            'tabs' => [
                'conformite' => ['label' => 'Conformité', 'legacy' => 'back-office/documents/compliance', 'handler' => [AdminDoctrineController::class, 'compliance'], 'middleware' => self::ORG],
                'nomenclature' => ['label' => 'Nomenclature', 'legacy' => 'back-office/documents/nomenclature', 'handler' => [AdminDoctrineController::class, 'nomenclature'], 'middleware' => self::ORG],
                'types' => ['label' => 'Types de documents', 'legacy' => 'back-office/documents/types', 'handler' => [AdminDoctrineController::class, 'types'], 'middleware' => self::ORG],
            ],
        ],
        'back-office/mise-a-niveau' => [
            'label' => 'Mises à jour',
            'handler' => [ConfigurationUpdateController::class, 'index'],
            'middleware' => self::ORG,
            'bar' => true,
            'tabs' => [
                'nouveautes' => ['label' => 'Nouveautés', 'legacy' => 'back-office/nouveautes-organisation', 'handler' => [ConfigurationUpdateController::class, 'novelties'], 'middleware' => self::ORG],
            ],
        ],
        // Effectifs : la barre d’onglets du bureau existe déjà (views/admin/effectifs_workspace/shell.php).
        'back-office/ressources/effectifs/a-traiter' => [
            'label' => 'À traiter',
            'handler' => [RhDossierWorkspaceController::class, 'inbox'],
            'middleware' => self::ORG,
            'bar' => false,
            'tabs' => [
                'alertes' => ['label' => 'Alertes', 'legacy' => 'back-office/ressources/effectifs/alertes', 'handler' => [RhDossierWorkspaceController::class, 'alerts'], 'middleware' => self::ORG],
            ],
        ],
    ];

    /** Adresse d’un onglet (chemin relatif, à passer à url()). */
    public static function tabPath(string $base, string $key): string
    {
        return $key === '' ? $base : $base . '?onglet=' . rawurlencode($key);
    }

    /**
     * Anciennes adresses → onglet qui les remplace.
     *
     * @return array<string, string>
     */
    public static function legacyRedirects(): array
    {
        $out = [];
        foreach (self::FAMILIES as $base => $family) {
            foreach ($family['tabs'] as $key => $tab) {
                $out[$tab['legacy']] = self::tabPath($base, $key);
            }
        }

        return $out;
    }

    /**
     * Lien vers l’onglet qui remplace une ancienne page (ou url() inchangé si ce n’en est pas une).
     */
    public static function urlFor(string $legacyPath): string
    {
        $legacyPath = trim($legacyPath, '/');
        $map = self::legacyRedirects();

        return url($map[$legacyPath] ?? $legacyPath);
    }

    /**
     * Chemin de la page réellement affichée : l’ancienne adresse de l’onglet ouvert, sinon le chemin courant.
     * Sert à retrouver l’en-tête et les feuilles de style de la page d’origine (config/back_office_pages.php).
     */
    public static function contentPath(string $currentPath, string $onglet): string
    {
        $currentPath = trim($currentPath, '/');
        if ($onglet === '') {
            return $currentPath;
        }

        return self::FAMILIES[$currentPath]['tabs'][$onglet]['legacy'] ?? $currentPath;
    }

    /** Sert la page d’accueil ou l’onglet demandé (?onglet=…), avec les droits de la page d’origine. */
    public static function dispatch(string $base, Request $request, array $params = []): Response
    {
        $family = self::FAMILIES[$base] ?? null;
        if ($family === null) {
            return Response::redirect(url('back-office'));
        }
        $key = $request->queryString('onglet', '');
        $tab = $key !== '' ? ($family['tabs'][$key] ?? null) : null;
        if ($tab === null) {
            return self::run($request, $params, $family['handler'], $family['middleware']);
        }
        // Le profil de communauté filtre les modules sur le chemin réellement demandé (la page d’accueil) :
        // on reprend le contrôle sur l’ancienne adresse de l’onglet.
        if (function_exists('navigation_current_tenant_type')
            && !TenantTypeConfig::uriAllowed(navigation_current_tenant_type(), $tab['legacy'])) {
            return Response::redirect(url($base));
        }

        return self::run(self::requestAt($request, '/' . $tab['legacy']), $params, $tab['handler'], $tab['middleware']);
    }

    /**
     * Onglets à afficher sur la page courante (null si la page n’en a pas).
     *
     * @return list<array{label: string, href: string, active: bool}>|null
     */
    public static function barFor(string $currentPath, string $onglet, ?callable $allowed = null): ?array
    {
        $family = self::FAMILIES[trim($currentPath, '/')] ?? null;
        if ($family === null || empty($family['bar'])) {
            return null;
        }
        $base = trim($currentPath, '/');
        $known = isset($family['tabs'][$onglet]);
        $items = [['label' => $family['label'], 'href' => url($base), 'active' => !$known]];
        foreach ($family['tabs'] as $key => $tab) {
            if ($allowed !== null && !$allowed($tab['legacy'])) {
                continue;
            }
            $items[] = ['label' => $tab['label'], 'href' => url(self::tabPath($base, $key)), 'active' => $known && $onglet === $key];
        }

        return count($items) > 1 ? $items : null;
    }

    /** Même requête, vue depuis un autre chemin (ce que voyaient middlewares et contrôleur sur l’ancienne page). */
    private static function requestAt(Request $request, string $path): Request
    {
        $clone = clone $request;
        (new \ReflectionProperty(Request::class, 'path'))->setValue($clone, $path);

        return $clone;
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param list<class-string> $middleware
     */
    private static function run(Request $request, array $params, array $handler, array $middleware): Response
    {
        [$class, $action] = $handler;
        $runner = static function (Request $req) use ($class, $action, $params): Response {
            try {
                $controller = Container::get($class);
            } catch (\InvalidArgumentException $e) {
                if ($e->getMessage() !== 'Unknown service: ' . $class) {
                    throw $e;
                }
                $controller = new $class();
            }
            $response = $controller->$action($req, $params);

            return $response instanceof Response ? $response : new Response();
        };
        foreach (array_reverse($middleware) as $m) {
            $next = $runner;
            $instance = new $m();
            $runner = static fn (Request $req): Response => $instance($req, $next);
        }

        return $runner($request);
    }
}
