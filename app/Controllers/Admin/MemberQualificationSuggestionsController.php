<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserRepository;
use App\Services\Personnel\MemberQualificationSuggestionService;
use App\Support\EffectifsLmsAccess;

/**
 * Fiche membre › Qualifications et brevets suggérés : ignorer une suggestion pour ce membre, ou la reprendre.
 * Les autres actions rapides (importer dans le référentiel, inscrire au cours Academy, délivrer ou mettre en
 * formation) passent par les formulaires existants, avec leurs droits et leurs règles.
 */
final class MemberQualificationSuggestionsController
{
    /** Responsables qui voient les actions rapides des suggestions (référentiel des qualifications). */
    public static function canManageReferential(Gate $gate): bool
    {
        return $gate->allows('admin.organization') || $gate->allows('admin.access') || $gate->allows('site.support');
    }

    public static function canDismiss(Gate $gate): bool
    {
        return self::canManageReferential($gate) || EffectifsLmsAccess::canEditProfiles($gate);
    }

    public function dismiss(Request $request, array $params = []): Response
    {
        $userId = (int) ($params['id'] ?? 0);
        $back = url('back-office/ressources/effectifs/membres/' . $userId) . '#qualifs-suggerees';
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        if (!self::canDismiss(Gate::getInstance())) {
            Session::flash('error', 'Vous n’avez pas le droit de modifier les suggestions de ce membre.');

            return Response::redirect($back);
        }
        if ($userId < 1 || !Container::get(UserRepository::class)->isMemberOfTenant($userId, $tenantId)) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(effectifs_workspace_url());
        }
        $restore = (string) $request->input('restore', '') === '1';
        $ok = Container::get(MemberQualificationSuggestionService::class)->dismiss($tenantId, $userId, (string) $request->input('key', ''), (int) Session::get('user_id'), $restore);
        if ($ok) {
            Session::flash('success', $restore ? 'Suggestion reprise.' : 'Suggestion ignorée pour ce membre.');
        } else {
            Session::flash('error', 'Suggestion non enregistrée : la mise à jour du site n’est peut-être pas encore faite.');
        }

        return Response::redirect($back);
    }
}
