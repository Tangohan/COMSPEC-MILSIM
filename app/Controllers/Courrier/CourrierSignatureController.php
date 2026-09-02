<?php

declare(strict_types=1);

namespace App\Controllers\Courrier;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Csrf;
use App\Repositories\Courrier\CourrierDocumentRepository;
use App\Repositories\Courrier\UserSignatureRepository;
use App\Services\Courrier\DocumentSignatureService;
use App\Services\Platform\FeatureGateService;
use App\Support\PlanFeatureDenial;

class CourrierSignatureController
{
    public function __construct(
        private CourrierDocumentRepository $documentRepository,
        private DocumentSignatureService $signatureService,
        private UserSignatureRepository $signatureRepository,
        private ?FeatureGateService $featureGate = null
    ) {
        $this->featureGate ??= \App\Core\Container::get(FeatureGateService::class);
    }

    private function denyIfCourrierLocked(int $tenantId): ?Response
    {
        if (!$this->featureGate->allows($tenantId, 'courrier')) {
            return PlanFeatureDenial::upgradeView('courrier', 'Standard');
        }

        return null;
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
            return Response::json(['success' => false, 'message' => 'La signature n’a pas pu être enregistrée. Recommencez.'], 400);
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

    /**
     * GET /courrier/signature — créer et enregistrer sa signature, hors d’un document.
     */
    public function manage(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        if (!$tenantId || !$userId) {
            return Response::redirect(url('login'));
        }
        $denied = $this->denyIfCourrierLocked($tenantId);
        if ($denied !== null) {
            return $denied;
        }

        $list = $this->signatureRepository->listByUser($userId, $tenantId);
        foreach ($list as &$row) {
            $row['url'] = url('courrier/signatures/' . (int) $row['id'] . '/image');
        }
        unset($row);

        return Response::view('layout.main', [
            'title' => 'Ma signature — Bureau Courrier',
            'content' => 'courrier/signature',
            'courrier' => [
                'signatures' => $list,
            ],
        ]);
    }

    /**
     * POST /courrier/signature
     */
    public function store(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        if (!$tenantId || !$userId) {
            return Response::redirect(url('login'));
        }
        $denied = $this->denyIfCourrierLocked($tenantId);
        if ($denied !== null) {
            return $denied;
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'La session a expiré. Recommencez.');
            return Response::redirect(url('courrier/signature'));
        }

        $image = trim((string) $request->input('image_base64', ''));
        $name = (string) $request->input('name', '');
        $asDefault = (bool) $request->input('is_default');
        if ($image === '') {
            Session::flash('error', 'Dessinez votre signature dans le cadre avant d’enregistrer.');
            return Response::redirect(url('courrier/signature'));
        }

        try {
            $this->signatureService->saveUserSignature($userId, $tenantId, $image, $name, $asDefault);
        } catch (\Throwable) {
            Session::flash('error', 'La signature n’a pas pu être enregistrée. Dessinez-la à nouveau.');
            return Response::redirect(url('courrier/signature'));
        }

        Session::flash('success', 'Votre signature est enregistrée. Vous pourrez la réutiliser pour signer un courrier.');
        return Response::redirect(url('courrier/signature'));
    }

    /**
     * POST /courrier/signature/{id}/default
     */
    public function setDefault(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        if (!$tenantId || !$userId) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'La session a expiré. Recommencez.');
            return Response::redirect(url('courrier/signature'));
        }
        $sig = $this->signatureRepository->findById($id, $userId, $tenantId);
        if (!$sig) {
            Session::flash('error', 'Signature introuvable.');
            return Response::redirect(url('courrier/signature'));
        }
        $this->signatureRepository->setDefault($id, $userId, $tenantId);
        Session::flash('success', 'Cette signature sera proposée en premier lorsque vous signerez un courrier.');
        return Response::redirect(url('courrier/signature'));
    }

    /**
     * POST /courrier/signature/{id}/delete
     */
    public function destroy(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        if (!$tenantId || !$userId) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'La session a expiré. Recommencez.');
            return Response::redirect(url('courrier/signature'));
        }

        try {
            $this->signatureService->deleteUserSignature($id, $userId, $tenantId);
        } catch (\Throwable) {
            Session::flash('error', 'Cette signature n’a pas pu être retirée.');
            return Response::redirect(url('courrier/signature'));
        }

        Session::flash('success', 'La signature a été retirée.');
        return Response::redirect(url('courrier/signature'));
    }
}
