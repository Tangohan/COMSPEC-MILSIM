<?php

declare(strict_types=1);

namespace App\Controllers\Courrier;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\Courrier\CourrierDocumentNotificationRepository;
use App\Repositories\Courrier\CourrierDocumentRepository;

final class CourrierNotificationController
{
    public function __construct(
        private CourrierDocumentRepository $documentRepository,
        private CourrierDocumentNotificationRepository $notificationRepository
    ) {
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        if (!$tenantId || !$userId) {
            return Response::redirect(url('login'));
        }
        $items = $this->notificationRepository->listRecentForUser($tenantId, $userId, 80);

        return Response::view('layout.main', [
            'title' => 'Notifications courrier',
            'content' => 'courrier.notifications',
            'courrier_notifications' => $items,
        ]);
    }

    public function notify(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        if (!$tenantId || !$userId) {
            return Response::redirect(url('login'));
        }
        if ($request->method() !== 'POST') {
            return Response::redirect(url('courrier'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('courrier'));
        }
        $docId = (int) ($params['id'] ?? 0);
        $document = $this->documentRepository->findById($docId, $tenantId);
        if (!$document) {
            Session::flash('error', 'Document introuvable.');

            return Response::redirect(url('courrier'));
        }
        $raw = $request->input('notify_user_ids', []);
        $ids = is_array($raw) ? $raw : [];
        $recipientIds = [];
        foreach ($ids as $id) {
            $recipientIds[] = (int) $id;
        }
        $n = $this->notificationRepository->createForRecipients($tenantId, $docId, $userId, $recipientIds);
        if ($n > 0) {
            Session::flash('success', $n . ' notification(s) envoyée(s).');
        } else {
            Session::flash('error', 'Sélectionnez au moins un autre utilisateur, ou vérifiez que la table de notifications est migrée.');
        }

        return Response::redirect(url('courrier/read/' . $docId));
    }
}
