<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\Courrier\UserSignatureRepository;
use App\Repositories\PersonnelAdminDataRepository;
use App\Services\Auth\AuthService;

final class DossierOperateurAccreditationApiController
{
    private const ACCREDITATION_PANEL_ID = 9101;

    public function __construct(
        private AuthService $authService,
        private PersonnelAdminDataRepository $personnelAdminDataRepository,
        private UserSignatureRepository $userSignatureRepository,
    ) {}

    public function state(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::json(['success' => false, 'message' => 'Authentification requise'], 401);
        }

        $userId = (int) ($user['id'] ?? 0);
        $tenantId = (int) Session::get('tenant_id');
        $state = $this->personnelAdminDataRepository->getForUserAndPanel($userId, self::ACCREDITATION_PANEL_ID);
        $notes = is_array($state['notes'] ?? null) ? array_values($state['notes']) : [];
        $reviews = is_array($state['reviews'] ?? null) ? array_values($state['reviews']) : [];
        $signatureRequired = isset($state['signature_required']) ? (bool) $state['signature_required'] : true;

        $hasDefaultSignature = false;
        $signatures = $this->userSignatureRepository->listByUser($userId, $tenantId);
        foreach ($signatures as $signature) {
            if ((int) ($signature['is_default'] ?? 0) === 1) {
                $hasDefaultSignature = true;
                break;
            }
        }

        return Response::json([
            'success' => true,
            'data' => [
                'notes' => $notes,
                'reviews' => $reviews,
                'signature_required' => $signatureRequired,
                'has_default_signature' => $hasDefaultSignature,
            ],
        ]);
    }

    public function addNote(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::json(['success' => false, 'message' => 'Authentification requise'], 401);
        }

        $payload = $this->jsonPayload();
        if (!Csrf::validate((string) ($payload['_csrf_token'] ?? $request->input('_csrf_token', '')))) {
            return Response::json(['success' => false, 'message' => 'Session expirée, rechargez la page.'], 403);
        }

        $title = trim((string) ($payload['title'] ?? ''));
        $classification = trim((string) ($payload['classification'] ?? 'Interne'));
        $body = trim((string) ($payload['body'] ?? ''));
        if ($title === '' || $body === '') {
            return Response::json(['success' => false, 'message' => 'Titre et contenu sont obligatoires.'], 422);
        }

        $userId = (int) ($user['id'] ?? 0);
        $state = $this->personnelAdminDataRepository->getForUserAndPanel($userId, self::ACCREDITATION_PANEL_ID);
        $notes = is_array($state['notes'] ?? null) ? array_values($state['notes']) : [];
        $notes[] = [
            'id' => bin2hex(random_bytes(6)),
            'reference' => 'ACR-OPS-' . date('ymd') . '-' . str_pad((string) (count($notes) + 1), 2, '0', STR_PAD_LEFT),
            'title' => mb_substr($title, 0, 120),
            'author' => (string) ($user['display_name'] ?? 'Opérateur'),
            'classification' => mb_substr($classification, 0, 80),
            'summary' => mb_substr($body, 0, 800),
            'result' => 'En attente de revue.',
            'stamp' => date('d/m/Y H:i'),
            'created_at' => date('c'),
        ];

        $state['notes'] = array_slice($notes, -25);
        $state['signature_required'] = isset($state['signature_required']) ? (bool) $state['signature_required'] : true;
        $state['reviews'] = is_array($state['reviews'] ?? null) ? array_values($state['reviews']) : [];
        $this->personnelAdminDataRepository->setForUserAndPanel($userId, self::ACCREDITATION_PANEL_ID, $state);

        return Response::json(['success' => true, 'notes' => $state['notes']]);
    }

    public function addReview(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::json(['success' => false, 'message' => 'Authentification requise'], 401);
        }

        $payload = $this->jsonPayload();
        if (!Csrf::validate((string) ($payload['_csrf_token'] ?? $request->input('_csrf_token', '')))) {
            return Response::json(['success' => false, 'message' => 'Session expirée, rechargez la page.'], 403);
        }

        $title = trim((string) ($payload['title'] ?? ''));
        $status = trim((string) ($payload['status'] ?? 'en attente'));
        $detail = trim((string) ($payload['detail'] ?? ''));
        if ($title === '' || $detail === '') {
            return Response::json(['success' => false, 'message' => 'Intitulé et détail sont obligatoires.'], 422);
        }

        $allowedStatuses = ['validé', 'à corriger', 'en attente', 'bloquant'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'en attente';
        }

        $userId = (int) ($user['id'] ?? 0);
        $state = $this->personnelAdminDataRepository->getForUserAndPanel($userId, self::ACCREDITATION_PANEL_ID);
        $reviews = is_array($state['reviews'] ?? null) ? array_values($state['reviews']) : [];
        $reviews[] = [
            'id' => bin2hex(random_bytes(6)),
            'title' => mb_substr($title, 0, 120),
            'status' => $status,
            'detail' => mb_substr($detail, 0, 320),
            'reviewer' => (string) ($user['display_name'] ?? 'Opérateur'),
            'reviewed_at' => date('d/m/Y H:i'),
        ];

        $state['reviews'] = array_slice($reviews, -25);
        $state['signature_required'] = isset($state['signature_required']) ? (bool) $state['signature_required'] : true;
        $state['notes'] = is_array($state['notes'] ?? null) ? array_values($state['notes']) : [];
        $this->personnelAdminDataRepository->setForUserAndPanel($userId, self::ACCREDITATION_PANEL_ID, $state);

        return Response::json(['success' => true, 'reviews' => $state['reviews']]);
    }

    public function policy(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::json(['success' => false, 'message' => 'Authentification requise'], 401);
        }

        $payload = $this->jsonPayload();
        if (!Csrf::validate((string) ($payload['_csrf_token'] ?? $request->input('_csrf_token', '')))) {
            return Response::json(['success' => false, 'message' => 'Session expirée, rechargez la page.'], 403);
        }

        $signatureRequired = (bool) ($payload['signature_required'] ?? true);
        $userId = (int) ($user['id'] ?? 0);
        $state = $this->personnelAdminDataRepository->getForUserAndPanel($userId, self::ACCREDITATION_PANEL_ID);
        $state['signature_required'] = $signatureRequired;
        $state['notes'] = is_array($state['notes'] ?? null) ? array_values($state['notes']) : [];
        $state['reviews'] = is_array($state['reviews'] ?? null) ? array_values($state['reviews']) : [];
        $this->personnelAdminDataRepository->setForUserAndPanel($userId, self::ACCREDITATION_PANEL_ID, $state);

        return Response::json(['success' => true, 'signature_required' => $signatureRequired]);
    }

    private function jsonPayload(): array
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
