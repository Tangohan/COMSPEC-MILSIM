<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\EnlistmentRepository;
use App\Repositories\TenantRepository;
use App\Services\Recruitment\EnlistmentPortalAttachmentService;
use App\Services\Recruitment\EnlistmentPortalMessagingNotificationService;

final class EnlistmentCandidatePortalController
{
    public function __construct(
        private EnlistmentRepository $enlistmentRepository,
        private TenantRepository $tenantRepository,
        private EnlistmentPortalMessagingNotificationService $portalMessagingNotificationService,
        private EnlistmentPortalAttachmentService $portalAttachmentService,
    ) {}

    public function show(Request $request, array $params = []): Response
    {
        $token = (string) ($params['token'] ?? '');
        $row = $this->enlistmentRepository->findByCandidatePortalToken($token);
        if (!$row) {
            return Response::view('enlistment.error', ['message' => 'Lien invalide ou expiré.', 'enlistmentRetryUrl' => url('enlistment/error')]);
        }
        $tenantId = (int) ($row['tenant_id'] ?? 0);
        $tenant = $tenantId > 0 ? $this->tenantRepository->findById($tenantId) : null;
        $enlistmentId = (int) ($row['id'] ?? 0);
        $messages = $this->enlistmentRepository->listCandidatePortalMessages($tenantId, $enlistmentId);
        $attachments = $this->enlistmentRepository->listCandidatePortalAttachments($tenantId, $enlistmentId);

        return Response::view('enlistment.candidate_portal', [
            'enlistment' => $row,
            'messages' => $messages,
            'attachments' => $attachments,
            'tenant' => $tenant,
            'token' => $token,
            'flashOk' => Session::getFlash('success'),
            'flashErr' => Session::getFlash('error'),
            'portalUploadsReady' => $this->enlistmentRepository->candidatePortalUploadsReady(),
            'allowPortalFiles' => !empty($row['candidate_portal_allow_files']),
            'allowPortalAudio' => !empty($row['candidate_portal_allow_audio']),
        ]);
    }

    public function message(Request $request, array $params = []): Response
    {
        $token = (string) ($params['token'] ?? '');
        $row = $this->enlistmentRepository->findByCandidatePortalToken($token);
        if (!$row || !$request->isPost()) {
            return Response::redirect(url('enlistment/error'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('enlistment/suivi/' . rawurlencode($token)));
        }

        $body = trim((string) $request->input('candidate_message', ''));
        if (mb_strlen($body) < 2) {
            Session::flash('error', 'Message trop court.');

            return Response::redirect(url('enlistment/suivi/' . rawurlencode($token)));
        }

        $ok = $this->enlistmentRepository->appendCandidatePortalMessage(
            (int) ($row['tenant_id'] ?? 0),
            (int) ($row['id'] ?? 0),
            'candidate',
            $body
        );
        if ($ok) {
            $tenantId = (int) ($row['tenant_id'] ?? 0);
            $tenantRow = $tenantId > 0 ? $this->tenantRepository->findById($tenantId) : null;
            $tenantName = trim((string) (is_array($tenantRow) ? ($tenantRow['name'] ?? '') : ''));
            if ($tenantName === '') {
                $tenantName = 'Communauté';
            }
            try {
                $this->portalMessagingNotificationService->notifyStaffOfCandidatePortalMessage($tenantId, $tenantName, $row, $body);
            } catch (\Throwable) {
            }
        }
        Session::flash($ok ? 'success' : 'error', $ok ? 'Votre message a été transmis.' : 'Impossible d’enregistrer le message.');

        return Response::redirect(url('enlistment/suivi/' . rawurlencode($token)));
    }

    public function uploadAttachment(Request $request, array $params = []): Response
    {
        $token = (string) ($params['token'] ?? '');
        $row = $this->enlistmentRepository->findByCandidatePortalToken($token);
        if (!$row || !$request->isPost()) {
            return Response::redirect(url('enlistment/error'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('enlistment/suivi/' . rawurlencode($token)));
        }
        $tenantId = (int) ($row['tenant_id'] ?? 0);
        $enlistmentId = (int) ($row['id'] ?? 0);
        if (!$this->enlistmentRepository->candidatePortalUploadsReady()) {
            Session::flash('error', 'L’envoi de fichiers n’est pas disponible pour le moment.');

            return Response::redirect(url('enlistment/suivi/' . rawurlencode($token)));
        }
        $allowFiles = !empty($row['candidate_portal_allow_files']);
        $allowAudio = !empty($row['candidate_portal_allow_audio']);
        if (!$allowFiles && !$allowAudio) {
            Session::flash('error', 'L’équipe n’a pas activé l’envoi de pièces pour ce dossier.');

            return Response::redirect(url('enlistment/suivi/' . rawurlencode($token)));
        }
        $file = isset($_FILES['portal_upload']) && is_array($_FILES['portal_upload']) ? $_FILES['portal_upload'] : null;
        $result = $this->portalAttachmentService->storeCandidateUpload($tenantId, $enlistmentId, $allowFiles, $allowAudio, $file);
        if (!($result['ok'] ?? false)) {
            Session::flash('error', (string) ($result['error'] ?? 'Envoi impossible.'));

            return Response::redirect(url('enlistment/suivi/' . rawurlencode($token)));
        }
        $kind = (string) ($result['kind'] ?? 'file');
        $orig = (string) ($result['original_name'] ?? 'fichier');
        $line = ($kind === 'audio' ? 'Enregistrement audio transmis' : 'Document transmis') . ' : ' . $orig;
        $this->enlistmentRepository->appendCandidatePortalMessage($tenantId, $enlistmentId, 'candidate', $line);
        $tenantRow = $tenantId > 0 ? $this->tenantRepository->findById($tenantId) : null;
        $tenantName = trim((string) (is_array($tenantRow) ? ($tenantRow['name'] ?? '') : ''));
        if ($tenantName === '') {
            $tenantName = 'Communauté';
        }
        try {
            $this->portalMessagingNotificationService->notifyStaffOfCandidatePortalUpload($tenantId, $tenantName, $row, $kind, $orig);
        } catch (\Throwable) {
        }
        Session::flash('success', 'Votre pièce jointe a bien été transmise.');

        return Response::redirect(url('enlistment/suivi/' . rawurlencode($token)));
    }

    public function downloadAttachment(Request $request, array $params = []): Response
    {
        $token = (string) ($params['token'] ?? '');
        $attachmentId = (int) ($params['attachmentId'] ?? 0);
        $row = $this->enlistmentRepository->findByCandidatePortalToken($token);
        if (!$row || $attachmentId < 1) {
            return Response::view('enlistment.error', ['message' => 'Lien invalide ou expiré.', 'enlistmentRetryUrl' => url('enlistment/error')]);
        }
        $tenantId = (int) ($row['tenant_id'] ?? 0);
        $enlistmentId = (int) ($row['id'] ?? 0);
        $att = $this->enlistmentRepository->findCandidatePortalAttachment($tenantId, $enlistmentId, $attachmentId);
        if (!$att) {
            return Response::view('enlistment.error', ['message' => 'Pièce jointe introuvable.', 'enlistmentRetryUrl' => url('enlistment/error')]);
        }
        $path = $this->portalAttachmentService->absolutePathForStorage((string) ($att['storage_path'] ?? ''));
        if ($path === '' || !is_file($path)) {
            return Response::view('enlistment.error', ['message' => 'Fichier introuvable.', 'enlistmentRetryUrl' => url('enlistment/error')]);
        }
        $mime = trim((string) ($att['mime'] ?? ''));
        if ($mime === '') {
            $mime = 'application/octet-stream';
        }
        $name = trim((string) ($att['original_name'] ?? 'piece-jointe'));
        $disp = 'attachment; filename="' . str_replace(['"', "\r", "\n"], '', $name) . '"';
        $response = new Response();
        $response->setStatusCode(200)
            ->header('Content-Type', $mime)
            ->header('Content-Disposition', $disp)
            ->setBodyStream(static function () use ($path): void {
                $h = fopen($path, 'rb');
                if ($h !== false) {
                    fpassthru($h);
                    fclose($h);
                }
            });

        return $response;
    }
}
