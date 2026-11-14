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
}
