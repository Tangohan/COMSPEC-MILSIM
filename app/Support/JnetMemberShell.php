<?php

declare(strict_types=1);

namespace App\Support;

use App\Controllers\Web\JnetPortalController;
use App\Core\Container;
use App\Core\Request;
use App\Core\Response;

/**
 * Habillage des pages « Ma situation » du membre. Un contrôleur qui rendait « Response::view('layout.main', $vars) » rend
 * « JnetMemberShell::view($request, $vars) » avec les MÊMES variables :
 *   - sous /jnet/moi/*, la page s'affiche dans la coque JNET (thème noir Athena, pastille de compte, rail « Mon espace ») ;
 *   - ailleurs (vue instructeur du carnet de vol, /atak/premiere-liaison, /personnel/{id}, /evenements…), le rendu
 *     d'avant est conservé tel quel.
 *
 * Variables lues par la coque : content (vue du corps), boPageTitle ou title, boPageSubtitle (phrase sous le titre),
 * backOfficePageCss (feuilles du contenu, assets/css/), jnetPageJs (scripts du contenu, assets/js/). Les drapeaux propres
 * au back-office (isBackOfficeShell, boPageGroup, boSkip*) restent sans effet dans la coque JNET.
 */
final class JnetMemberShell
{
    /** La requête vise-t-elle une page du membre dans JNET ? */
    public static function wants(Request $request): bool
    {
        return JnetMemberPaths::isMemberPath($request->path());
    }

    /**
     * @param array<string, mixed> $vars variables que la page donnait à layout.main
     */
    public static function view(Request $request, array $vars): Response
    {
        if (!self::wants($request)) {
            return Response::view('layout.main', $vars);
        }

        return self::controller()->memberPage($request, $vars);
    }

    private static function controller(): JnetPortalController
    {
        try {
            return Container::get(JnetPortalController::class);
        } catch (\InvalidArgumentException $e) {
            if ($e->getMessage() !== 'Unknown service: ' . JnetPortalController::class) {
                throw $e;
            }

            return new JnetPortalController();
        }
    }
}
