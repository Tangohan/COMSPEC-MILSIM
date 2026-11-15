<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SsePersonRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseAccessPolicy;
use App\Services\Sse\SsePersonRegistryLinkService;
use App\Support\SseAccessCatalog;

/**
 * Fiche personne : crée l'identité du registre d'une personne qui n'en a pas encore, pour pouvoir y poser un avis de
 * recherche, en tirer la notice PDF et suivre son historique. Écrire dans « papiers » (comme la création d'une identité),
 * jeton CSRF, personne de la communauté de la session ; jamais deux identités pour une même personne.
 */
final class SsePersonRegistryController
{
    public function __construct(
        private ?SseAccessCodeService $access = null,
        private ?SsePersonRepository $persons = null,
        private ?SsePersonRegistryLinkService $links = null,
    ) {
        $this->access ??= new SseAccessCodeService();
        $this->persons ??= new SsePersonRepository();
        $this->links ??= new SsePersonRegistryLinkService();
    }

    public function create(Request $request, array $params = []): Response
    {
        $personId = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/identites/' . $personId) . '?fiche=personne';
        $tenantId = $this->access->tenantId();
        $tenantId = $tenantId > 0 ? $tenantId : (int) Session::get('tenant_id');
        $token = $request->input('_csrf_token');
        $guest = $this->access->isGuest();
        if (!is_string($token) || $token === '' || !Csrf::validate($token)
            || !SseAccessPolicy::sessionAllows('papiers', SseAccessCatalog::LEVEL_WRITE, ['guest' => $guest, 'tenant_id' => $tenantId])) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect($back);
        }
        $person = $this->persons->findById($personId, $tenantId);
        if ($person === null) {
            Session::flash('error', 'Identité introuvable.');

            return Response::redirect(url('atak/sse/identites'));
        }
        $userId = (int) Session::get('user_id');
        $id = $this->links->createFor($tenantId, $person, $userId > 0 ? $userId : null);
        if ($id === null) {
            Session::flash('error', 'Une identité du registre est déjà rattachée à cette personne, ou la fiche n’a ni nom ni prénom.');

            return Response::redirect($back);
        }
        Session::flash('success', 'Identité du registre créée : vous pouvez y placer un avis de recherche.');

        return Response::redirect(url('atak/sse/papiers/identites/' . $id));
    }
}
