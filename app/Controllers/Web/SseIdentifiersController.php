<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Sse\SseIdentifierAccess;
use App\Services\Sse\SseIdentifierService;

/**
 * Modification, par un responsable de la section, du nom de code, du nom de fiche et de l'IA reliée d'une donnée SSE.
 * Le numéro d'identification et le DSE ne se modifient pas. Chaque changement est journalisé.
 */
final class SseIdentifiersController
{
    public function update(Request $request, array $params = []): Response
    {
        $type = (string) ($params['type'] ?? '');
        $id = (int) ($params['id'] ?? 0);
        $known = isset(SseIdentifierService::TYPES[$type]);
        $back = $known ? url(sprintf(SseIdentifierService::TYPES[$type]['href'], $id)) : url('atak/sse');
        $tenantId = SseIdentifierAccess::tenantId();
        $entity = $known ? SseIdentifierAccess::entityRow($tenantId, $type, $id) : null;
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée, réessayez.');

            return Response::redirect($back);
        }
        if ($entity === null || !SseIdentifierAccess::canRead($type, $entity) || !SseIdentifierAccess::canManage($type, $id)) {
            Session::flash('error', 'Vous n’avez pas l’habilitation pour modifier ces identifiants.');

            return Response::redirect($back);
        }
        $service = new SseIdentifierService();
        $service->assign($tenantId, $type, $id);
        $userId = (int) Session::get('user_id') ?: null;
        $label = (string) (Session::get('display_name') ?? 'Responsable');

        $result = $service->rename(
            $tenantId,
            $type,
            $id,
            (string) $request->input('codename', ''),
            $request->input('sheet_name') === null ? null : (string) $request->input('sheet_name'),
            $userId,
            $label
        );
        if ($result['ok']) {
            $profile = trim((string) $request->input('ai_profile_id', ''));
            $result = $service->setAiProfile($tenantId, $type, $id, $profile === '' ? null : (int) $profile, $userId, $label);
        }
        $messages = [
            'invalid_codename' => 'Nom de code refusé : lettres, chiffres et tirets, sans mot interdit.',
            'codename_taken' => 'Ce nom de code est déjà pris dans la communauté.',
            'ai_profile_not_found' => 'Profil d’IA introuvable.',
        ];
        if ($result['ok']) {
            Session::flash('success', 'Identifiants enregistrés.');
        } else {
            Session::flash('error', $messages[$result['error'] ?? ''] ?? 'Enregistrement impossible.');
        }

        return Response::redirect($back);
    }
}
