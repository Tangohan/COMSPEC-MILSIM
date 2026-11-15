<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SsePersonRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SsePersonRegistryLinkService;

/**
 * Fiche personne : crée l'identité du registre d'une personne qui n'en a pas encore, pour pouvoir y poser un avis de
 * recherche, en tirer la notice PDF et suivre son historique. Écrire dans « papiers » (comme la création d'une identité),
 * jeton CSRF, personne de la communauté de la session ; jamais deux identités pour une même personne.
 */
final class SsePersonRegistryController
{
    public const TOKEN_EXPIRED = 'Votre page est restée ouverte trop longtemps ou la session a changé : rechargez la fiche puis recommencez.';

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
        // Chaque refus dit sa raison : jeton périmé, droit manquant (lequel), personne introuvable, déjà rattachée, fiche sans nom.
        if (!Csrf::verify($request)) {
            Session::flash('error', self::TOKEN_EXPIRED);

            return Response::redirect($back);
        }
        $denial = $this->links->createDenial($tenantId, $this->access->isGuest());
        if ($denial !== null) {
            Session::flash('error', $denial);

            return Response::redirect($back);
        }
        $person = $this->persons->findById($personId, $tenantId);
        if ($person === null) {
            Session::flash('error', 'Cette personne est introuvable dans votre communauté.');

            return Response::redirect(url('atak/sse/identites'));
        }
        if ($this->links->hasAny($tenantId, $personId)) {
            Session::flash('error', 'Une identité du registre est déjà rattachée à cette personne.');

            return Response::redirect(url('atak/sse/identites/' . $personId));
        }
        $userId = (int) Session::get('user_id');
        $id = $this->links->createFor($tenantId, $person, $userId > 0 ? $userId : null);
        if ($id === null) {
            Session::flash('error', 'La fiche n’a ni nom ni prénom : renseignez-en un avant de créer l’identité du registre.');

            return Response::redirect($back);
        }
        Session::flash('success', 'Identité du registre créée : vous pouvez y placer un avis de recherche.');

        return Response::redirect(url('atak/sse/papiers/identites/' . $id));
    }
}
