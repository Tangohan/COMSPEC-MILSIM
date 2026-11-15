<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserRepository;
use App\Services\Auth\AuthService;
use App\Services\Jnet\JnetCommonRules;
use App\Services\Jnet\JnetCommonSpaceService;
use App\Services\Jnet\JnetSpaceService;
use App\Services\Rbac\RbacService;
use App\Support\PortalAccessChoice;

/**
 * Écritures de l'espace commun JNET : épingler, désépingler et ordonner les éléments épinglés. Toutes en POST avec jeton CSRF ;
 * le droit (publier au niveau de l'organisation) et la visibilité de l'élément sont revérifiés côté serveur par
 * JnetPinService. L'adresse de retour est toujours construite par le serveur (le salon est validé contre la liste des salons),
 * jamais reprise de la requête. Une action envoyée sans rechargement (en-tête Accept: application/json) reçoit un JSON
 * {ok, message}.
 */
final class JnetCommonController
{
    public function __construct(
        private ?AuthService $authService = null,
        private ?RbacService $rbacService = null,
        private ?UserRepository $userRepository = null,
        private ?JnetSpaceService $spaces = null,
        private ?JnetCommonSpaceService $common = null,
    ) {
        $this->authService ??= \App\Core\Container::get(AuthService::class);
        $this->rbacService ??= \App\Core\Container::get(RbacService::class);
        $this->userRepository ??= \App\Core\Container::get(UserRepository::class);
        $this->spaces ??= new JnetSpaceService();
        $this->common ??= new JnetCommonSpaceService($this->spaces);
    }

    /** Épingle un élément (type + identifiant). */
    public function pin(Request $request, array $params = []): Response
    {
        $g = $this->guard($request);
        if ($g instanceof Response) {
            return $g;
        }
        $error = $this->common->pinService($g['tenant'], $g['user'])->pin(
            strtolower(trim((string) $request->input('type', ''))),
            trim((string) $request->input('id', ''))
        );

        return $this->done($request, $g['back'], $error, 'Élément épinglé.');
    }

    /** Retire une épingle. */
    public function unpin(Request $request, array $params = []): Response
    {
        $g = $this->guard($request);
        if ($g instanceof Response) {
            return $g;
        }
        $error = $this->common->pinService($g['tenant'], $g['user'])->unpin((int) ($params['id'] ?? 0));

        return $this->done($request, $g['back'], $error, 'Épingle retirée.');
    }

    /** Monte (« haut ») ou descend (« bas ») une épingle. */
    public function move(Request $request, array $params = []): Response
    {
        $g = $this->guard($request);
        if ($g instanceof Response) {
            return $g;
        }
        $dir = (string) $request->input('sens', '') === 'haut' ? -1 : 1;
        $error = $this->common->pinService($g['tenant'], $g['user'])->move((int) ($params['id'] ?? 0), $dir);

        return $this->done($request, $g['back'], $error, 'Ordre enregistré.');
    }

    /** Applique l'ordre complet des épingles (glisser-déposer) : « ids[] » dans l'ordre voulu. */
    public function reorder(Request $request, array $params = []): Response
    {
        $g = $this->guard($request);
        if ($g instanceof Response) {
            return $g;
        }
        $ids = $request->input('ids', []);
        $error = $this->common->pinService($g['tenant'], $g['user'])->reorder(is_array($ids) ? array_values($ids) : []);

        return $this->done($request, $g['back'], $error, 'Ordre enregistré.');
    }

    // ——— Outils ———

    /**
     * Connexion, jeton CSRF et salon de retour validé.
     *
     * @return array{tenant: int, user: int, back: string}|Response
     */
    private function guard(Request $request): array|Response
    {
        $salon = JnetCommonRules::salon((string) $request->input('salon', 'epingles'));
        $back = url(JnetCommonRules::path($salon));
        if (!$this->authService->check()) {
            return $this->wantsJson($request) ? Response::json(['ok' => false, 'message' => 'Connexion requise.'], 401) : Response::redirect(url('login'));
        }
        $tenant = (int) Session::get('tenant_id');
        $user = (int) Session::get('user_id');
        if ($tenant <= 0 || $user <= 0 || PortalAccessChoice::isNoOrganizationContext()) {
            return $this->wantsJson($request) ? Response::json(['ok' => false, 'message' => 'Connexion requise.'], 401) : Response::redirect(url('login'));
        }
        $row = $this->authService->user();
        if ($row) {
            $this->rbacService->setPermissionsForGateFromUserRow($row, $this->userRepository);
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            return $this->done($request, $back, 'Votre session a expiré, rien n’a été enregistré.', null, 419);
        }

        return ['tenant' => $tenant, 'user' => $user, 'back' => $back];
    }

    private function wantsJson(Request $request): bool
    {
        return str_contains(strtolower((string) $request->header('Accept')), 'application/json');
    }

    /** Fin d'une écriture : redirection avec message en ligne, ou JSON pour une action sans rechargement. */
    private function done(Request $request, string $to, ?string $error, ?string $ok, int $status = 422): Response
    {
        if ($this->wantsJson($request)) {
            return Response::json(['ok' => $error === null, 'message' => (string) ($error ?? $ok ?? '')], $error === null ? 200 : $status);
        }
        if ($error !== null) {
            Session::flash('error', $error);
        } elseif ($ok !== null) {
            Session::flash('success', $ok);
        }

        return Response::redirect($to);
    }
}
