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
        // Envoi depuis la fenêtre des suggestions (fetch) : réponse JSON, la fenêtre reste ouverte.
        $json = str_contains(strtolower((string) $request->header('Accept')), 'application/json');
        $reply = static function (bool $ok, string $message, string $to) use ($json): Response {
            if ($json) {
                return Response::json(['ok' => $ok, 'message' => $message], $ok ? 200 : 422);
            }
            Session::flash($ok ? 'success' : 'error', $message);

            return Response::redirect($to);
        };
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return $json ? Response::json(['ok' => false, 'message' => 'Session expirée. Reconnectez-vous.'], 401) : Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return $reply(false, 'Session expirée. Réessayez.', $back);
        }
        if (!self::canDismiss(Gate::getInstance())) {
            return $reply(false, 'Vous n’avez pas le droit de modifier les suggestions de ce membre.', $back);
        }
        if ($userId < 1 || !Container::get(UserRepository::class)->isMemberOfTenant($userId, $tenantId)) {
            return $reply(false, 'Membre introuvable.', effectifs_workspace_url());
        }
        $restore = (string) $request->input('restore', '') === '1';
        $ok = Container::get(MemberQualificationSuggestionService::class)->dismiss($tenantId, $userId, (string) $request->input('key', ''), (int) Session::get('user_id'), $restore);

        return $reply($ok, $ok ? ($restore ? 'Suggestion reprise.' : 'Suggestion ignorée pour ce membre.') : 'Suggestion non enregistrée : la mise à jour du site n’est peut-être pas encore faite.', $back);
    }
}
