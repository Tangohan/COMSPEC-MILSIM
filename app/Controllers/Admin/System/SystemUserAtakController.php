<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Admin\AdminUserAtakService;
use App\Services\Audit\AuditService;

/**
 * Panneau « ATAK & liaison jeu » de /admin/users/{id}/edit (administration du site).
 *
 *   POST /admin/users/{id}/atak/valider      valider une demande de liaison (code Arma ou demande listée)
 *   POST /admin/users/{id}/atak/code         générer un code de liaison pour ce compte
 *   POST /admin/users/{id}/atak/forcer       forcer la liaison d'un Steam (et d'un ATAK) sur ce compte
 *   POST /admin/users/{id}/atak/sessions     fermer les sessions de jeu (reconnexion forcée)
 *   POST /admin/users/{id}/atak/debloquer    lever les blocages ATAK actifs
 *   POST /admin/users/{id}/atak/delier       retirer le Steam et fermer les sessions
 */
final class SystemUserAtakController
{
    public function __construct(private ?AdminUserAtakService $service = null)
    {
        $this->service ??= new AdminUserAtakService();
    }

    public function action(Request $request, array $params = []): Response
    {
        $userId = (int) ($params['id'] ?? 0);
        $back = url('admin/users/' . $userId . '/edit') . '#atak';
        if ($userId < 1) {
            return Response::redirect(url('admin/users'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée : rechargez la page puis recommencez.');

            return Response::redirect($back);
        }
        $actorId = (int) Session::get('user_id');
        $actorLabel = (string) (Session::get('display_name') ?? Session::get('callsign') ?? ('Admin #' . $actorId));
        $action = (string) ($params['action'] ?? '');

        $result = match ($action) {
            'valider' => $this->service->approveChallenge(
                $userId,
                ((int) $request->input('challenge_id', 0)) ?: null,
                (string) $request->input('user_code', ''),
                $actorId,
                $actorLabel
            ),
            'code' => $this->service->generateLinkCode($userId, $actorId, $actorLabel),
            'forcer' => $this->service->forceLink(
                $userId,
                (string) $request->input('steam_id', ''),
                (string) $request->input('terminal_uid', ''),
                (string) $request->input('lift_blocks', '') === '1',
                $actorId,
                $actorLabel
            ),
            'sessions' => $this->service->revokeSessions($userId, $actorId, $actorLabel),
            'debloquer' => $this->service->liftBlocks($userId, $actorId, $actorLabel),
            'delier' => $this->service->unlinkSteam($userId, $actorId, $actorLabel),
            default => ['ok' => false, 'message' => 'Action inconnue.'],
        };

        if (!empty($result['ok'])) {
            try {
                (new AuditService())->log('admin.user.atak.' . $action, null, $actorId ?: null, 'user', $userId, null, (string) $result['message'], (string) ($_SERVER['REMOTE_ADDR'] ?? ''), (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
            } catch (\Throwable) {
            }
        }
        Session::flash(!empty($result['ok']) ? 'success' : 'error', (string) $result['message']);

        return Response::redirect($back);
    }
}
