<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Personnel\QualificationDisplayService;
use App\Support\EffectifsLmsAccess;

/**
 * Interrupteurs « Visible sur Arma » et « Visible sur le profil » des qualifications d'un membre.
 *
 *  - `toggle` : depuis la fiche Effectifs d'un membre (responsable, ou le membre sur sa propre fiche) ;
 *  - `toggleMine` : depuis « Mes qualifications » (JNET) ; le membre visé est toujours celui de la session.
 *
 * Réponse JSON dans tous les cas (les interrupteurs enregistrent sans recharger la page) : `ok`, `code`, et l'état
 * enregistré de chaque qualification touchée, pour que la page revienne à l'état réel si le serveur refuse.
 * Aucun droit nouveau : un responsable bascule s'il gère déjà les fiches ou le référentiel des qualifications.
 */
final class MemberQualificationDisplayController
{
    public function __construct(private ?QualificationDisplayService $display = null)
    {
    }

    /** Responsables qui basculent pour un autre membre : gestion des fiches ou du référentiel des qualifications. */
    public static function canManage(Gate $gate): bool
    {
        return EffectifsLmsAccess::canEditProfiles($gate)
            || MemberQualificationSuggestionsController::canManageReferential($gate);
    }

    public function toggle(Request $request, array $params = []): Response
    {
        return $this->handle($request, (int) ($params['id'] ?? 0));
    }

    public function toggleMine(Request $request, array $params = []): Response
    {
        return $this->handle($request, (int) Session::get('user_id'));
    }

    private function handle(Request $request, int $memberId): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $actorId = (int) Session::get('user_id');
        if ($tenantId < 1 || $actorId < 1) {
            return Response::json(['ok' => false, 'code' => 'session'], 401);
        }
        if (!Csrf::verify($request)) {
            return Response::json(['ok' => false, 'code' => 'csrf'], 403);
        }
        $rawAward = trim((string) $request->input('qualification_id', ''));
        // « all » : toute la colonne (Tout afficher / Tout masquer). Sinon l'identifiant d'une attribution.
        $awardId = $rawAward === 'all' ? null : (ctype_digit($rawAward) ? (int) $rawAward : 0);
        $visible = (string) $request->input('visible', '') === '1';

        $result = ($this->display ?? new QualificationDisplayService())->apply(
            $tenantId,
            $actorId,
            self::canManage(Gate::getInstance()),
            $memberId,
            $awardId,
            (string) $request->input('surface', ''),
            $visible
        );

        return Response::json([
            'ok' => $result['ok'],
            'code' => $result['code'],
            'changed' => $result['changed'],
            'states' => $result['states'],
        ], $result['status']);
    }
}
