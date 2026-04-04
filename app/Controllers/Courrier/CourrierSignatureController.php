<?php

declare(strict_types=1);

namespace App\Controllers\Courrier;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\Courrier\CourrierDocumentRepository;
use App\Repositories\Courrier\UserSignatureRepository;
use App\Services\Courrier\DocumentSignatureService;

class CourrierSignatureController
{
    public function __construct(
        private CourrierDocumentRepository $documentRepository,
        private DocumentSignatureService $signatureService,
        private UserSignatureRepository $signatureRepository
    ) {
    }

    /**
     * POST /courrier/documents/{id}/sign
     */
    public function sign(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        if (!$tenantId || !$userId) {
            return Response::json(['success' => false, 'message' => 'Non autorisé.'], 403);
        }

        $document = $this->documentRepository->findById($id, $tenantId);
        if (!$document) {
            return Response::json(['success' => false, 'message' => 'Document introuvable.'], 404);
        }

        $raw = file_get_contents('php://input');
        $body = is_string($raw) ? (json_decode($raw, true) ?? []) : [];
        if (!\App\Core\Csrf::validate((string) ($body['_csrf_token'] ?? ''))) {
            return Response::json(['success' => false, 'message' => 'Jeton de sécurité invalide.'], 403);
        }
        $imageBase64 = $body['image_base64'] ?? null;
        $userSignatureId = isset($body['user_signature_id']) ? (int) $body['user_signature_id'] : null;
        $stamps = [
            'stamp_original_signed' => $body['stamp_original_signed'] ?? '',
            'stamp_name_signature' => $body['stamp_name_signature'] ?? '',
            'stamp_grade' => $body['stamp_grade'] ?? '',
        ];
        $secureHash = !empty($body['secure_hash']);
        $saveSignatureAsUser = !empty($body['save_signature_as_user']);
        $savedSignatureName = (string) ($body['saved_signature_name'] ?? 'Signature principale');

        try {
            $this->signatureService->signDocument(
                $id,
                $userId,
                $tenantId,
                $imageBase64,
                $userSignatureId,
                $stamps,
                $secureHash,
                $saveSignatureAsUser,
                $savedSignatureName
            );
        } catch (\Throwable $e) {
            return Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }

        return Response::json(['success' => true, 'message' => 'Document signé.']);
    }

    /**
     * GET /courrier/documents/{id}/verify — vérification authentifié
     */
    public function verify(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }

        $result = $this->signatureService->verifyDocument($id, $tenantId);
        $document = $this->documentRepository->findById($id, $tenantId);
        return Response::view('layout.main', [
            'title' => 'Vérification du document — Bureau Courrier',
            'content' => 'courrier/verify',
            'courrier' => [
                'document' => $document,
                'verify' => $result,
                'document_id' => $id,
            ],
        ]);
    }

    /**
     * GET /courrier/verify?uuid=... — page de vérification publique (par UUID)
     */
    public function verifyByUuid(Request $request, array $params = []): Response
    {
        $uuid = $request->query('uuid') ?? $params['uuid'] ?? '';
        if ($uuid === '') {
            return Response::view('layout.main', [
                'title' => 'Vérification de document',
                'content' => 'courrier/verify',
                'courrier' => ['document' => null, 'verify' => ['valid' => false, 'message' => 'UUID manquant.'], 'document_id' => null],
            ]);
        }

        $result = $this->signatureService->verifyDocumentByUuid($uuid);
        $document = null;
        if (isset($result['document_id'])) {
            $document = $this->documentRepository->findById((int) $result['document_id'], null);
        } else {
            $doc = $this->documentRepository->findByUuid($uuid, null);
            if ($doc) {
                $document = $doc;
                $result['document_id'] = (int) $doc['id'];
            }
        }

        return Response::view('layout.main', [
            'title' => 'Vérification de document — Bureau Courrier',
            'content' => 'courrier/verify',
            'courrier' => [
                'document' => $document,
                'verify' => $result,
                'document_id' => $document ? (int) $document['id'] : null,
                'uuid' => $uuid,
            ],
        ]);
    }

    /**
     * GET /courrier/my-signatures — liste des signatures enregistrées de l'utilisateur (JSON pour l'éditeur)
     */
    public function mySignatures(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        if (!$tenantId || !$userId) {
            return Response::json([], 403);
        }

        $list = $this->signatureRepository->listByUser($userId, $tenantId);
        foreach ($list as &$row) {
            $row['url'] = url('courrier/signatures/' . $row['id'] . '/image');
        }
        return Response::json($list);
    }

    /**
     * GET /courrier/signatures/{id}/image — image d'une signature utilisateur (auth: propre signature uniquement)
     */
    public function signatureImage(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        if (!$tenantId || !$userId) {
            return Response::json(['error' => 'Non autorisé'], 403);
        }

        $sig = $this->signatureRepository->findById($id, $userId, $tenantId);
        if (!$sig) {
            return Response::json(['error' => 'Signature introuvable'], 404);
        }

        $fullPath = $this->signatureService->getSignatureFilePath($sig['file_path'], true);
        if (!is_file($fullPath)) {
            return Response::json(['error' => 'Fichier introuvable'], 404);
        }

        $response = new \App\Core\Response();
        $response->setStatusCode(200)->header('Content-Type', 'image/png')->header('Cache-Control', 'private, max-age=3600');
        $response->setBodyStream(static function () use ($fullPath): void {
            readfile($fullPath);
        });
        return $response;
    }

    /**
     * GET /courrier/documents/{id}/signature-image — image de la signature du document (auth: accès document tenant)
     */
    public function documentSignatureImage(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if (!$tenantId) {
            return Response::json(['error' => 'Non autorisé'], 403);
        }

        $doc = $this->documentRepository->findById($id, $tenantId);
        if (!$doc) {
            return Response::json(['error' => 'Document introuvable'], 404);
        }

        $sigData = $doc['signature_data_json'] ?? null;
        if (empty($sigData)) {
            return Response::json(['error' => 'Document non signé'], 404);
        }
        $data = is_string($sigData) ? json_decode($sigData, true) : $sigData;
        $path = $data['signature_image_path'] ?? null;
        $source = $data['signature_source'] ?? 'pad';
        if (empty($path)) {
            return Response::json(['error' => 'Image de signature absente'], 404);
        }

        $isUser = $source === 'saved';
        $fullPath = $this->signatureService->getSignatureFilePath($path, $isUser);
        if (!is_file($fullPath)) {
            return Response::json(['error' => 'Fichier introuvable'], 404);
        }

        $response = new \App\Core\Response();
        $response->setStatusCode(200)->header('Content-Type', 'image/png')->header('Cache-Control', 'private, max-age=3600');
        $response->setBodyStream(static function () use ($fullPath): void {
            readfile($fullPath);
        });
        return $response;
    }
}
