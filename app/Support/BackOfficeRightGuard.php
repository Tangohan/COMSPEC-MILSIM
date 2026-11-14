<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Contrôle de droit interne des contrôleurs du back-office (audit du back-office, S6).
 *
 * OrganizationAdminMiddleware laisse entrer `site.support` partout et certains droits fins sur quelques préfixes : il ne
 * dit donc pas qui peut LIRE ou ÉCRIRE dans un contrôleur donné. (Le slug `site.support` est réservé : Gate::allows ne le
 * confirme qu'à un administrateur de la plateforme. La garde ne doit pourtant pas reposer sur cette seule subtilité.) Chaque contrôleur déclare ici le droit métier qu'il exige
 * (par défaut celui de la navigation : administrer la communauté ou les accès) et refuse le reste, lecture comme écriture.
 * Un droit de plus se déclare dans le contrôleur (voir {@see GuardsBackOfficeRight}), jamais ici.
 */
final class BackOfficeRightGuard
{
    /** Administrer la communauté ou ses accès : le droit que le menu exige par défaut pour une page de gestion. */
    public const MANAGERS = ['admin.organization', 'admin.access'];

    /** @param list<string> $rights un seul suffit */
    public static function allows(array $rights): bool
    {
        $gate = Gate::getInstance();
        foreach ($rights as $right) {
            if ($gate->allows($right)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Null si l'un des droits est détenu ; sinon la réponse de refus : 403 JSON pour un appel de script (fetch, API),
     * message et retour à l'accueil du back-office pour un navigateur (même conduite que les autres refus de cette zone).
     *
     * @param list<string> $rights un seul suffit
     */
    public static function deniedUnless(Request $request, array $rights = self::MANAGERS, ?string $redirectTo = null): ?Response
    {
        if (self::allows($rights)) {
            return null;
        }
        $message = 'Vous n’avez pas le droit d’utiliser cette page. Si vous pensez devoir y accéder, contactez un administrateur de la communauté.';
        if (!$request->isBrowserNavigation()) {
            return Response::json(['ok' => false, 'message' => $message], 403);
        }
        Session::flash('error', $message);

        return Response::redirect($redirectTo ?? url('back-office'));
    }
}
