<?php

declare(strict_types=1);

namespace App\Services\Courrier;

use App\Repositories\Courrier\CourrierDocumentRepository;
use App\Repositories\Courrier\UserSignatureRepository;

/**
 * Signature de documents courrier : enregistrement image, hash d'authenticité, réutilisation des signatures utilisateur.
 */
class DocumentSignatureService
{
    private const SIGNATURES_BASE = 'storage/courrier/signatures';
    private const DOCUMENTS_SIGNATURE_BASE = 'storage/courrier/documents';

    public function __construct(
        private CourrierDocumentRepository $documentRepository,
        private UserSignatureRepository $signatureRepository
    ) {
    }

    /**
     * Signe un document : enregistre l'image (pad ou référence user_signature), tampons, option hash, option sauvegarde signature.
     *
     * @param array{stamp_original_signed?: string, stamp_name_signature?: string, stamp_grade?: string} $stamps
     */
    public function signDocument(
        int $documentId,
        int $userId,
        int $tenantId,
        ?string $imageBase64 = null,
        ?int $userSignatureId = null,
        array $stamps = [],
        bool $secureHash = false,
        bool $saveSignatureAsUser = false,
        string $savedSignatureName = 'Signature principale'
    ): void {
        $doc = $this->documentRepository->findById($documentId, $tenantId);
        if (!$doc) {
            throw new \RuntimeException('Document introuvable.');
        }

        $signedAt = date('Y-m-d H:i:s');
        $signatureImagePath = null;
        $signatureSource = 'pad';

        if ($userSignatureId !== null) {
            $userSig = $this->signatureRepository->findById($userSignatureId, $userId, $tenantId);
            if (!$userSig) {
                throw new \RuntimeException('Signature enregistrée introuvable.');
            }
            $signatureImagePath = $userSig['file_path'];
            $signatureSource = 'saved';
        } elseif ($imageBase64 !== null && $imageBase64 !== '') {
            $signatureImagePath = $this->storeDocumentSignatureImage($documentId, $tenantId, $imageBase64);
            if ($saveSignatureAsUser) {
                $this->saveUserSignature($userId, $tenantId, $signatureImagePath, $savedSignatureName, true);
            }
        } else {
            throw new \RuntimeException('Fournissez une image de signature (base64) ou une signature enregistrée.');
        }

        $verificationCode = $this->generateVerificationCode();

        $signatureData = [
            'signature_image_path' => $signatureImagePath,
            'stamp_original_signed' => $stamps['stamp_original_signed'] ?? '',
            'stamp_name_signature' => $stamps['stamp_name_signature'] ?? '',
            'stamp_grade' => $stamps['stamp_grade'] ?? '',
            'signature_source' => $signatureSource,
            'verification_code' => $verificationCode,
        ];

        $contentHash = null;
        if ($secureHash) {
            $docForHash = array_merge($doc, [
                'signed_by' => $userId,
                'signature_data_json' => $signatureData,
            ]);
            $contentHash = $this->computeContentHash($docForHash, $signedAt);
        }

        $this->documentRepository->update($documentId, [
            'signed_by' => $userId,
            'signed_at' => $signedAt,
            'signature_data_json' => $signatureData,
            'content_hash' => $contentHash,
            'status' => 'signed',
        ]);
    }

    /**
     * Vérifie l'authenticité du document (hash inchangé).
     */
    public function verifyDocument(int $documentId, ?int $tenantId = null): array
    {
        $doc = $this->documentRepository->findById($documentId, $tenantId);
        if (!$doc) {
            return ['valid' => false, 'message' => 'Document introuvable.', 'document_id' => null];
        }
        $storedHash = $doc['content_hash'] ?? null;
        if ($storedHash === null || $storedHash === '') {
            return [
                'valid' => null,
                'message' => 'Document non sécurisé par hash.',
                'signed_at' => $doc['signed_at'] ?? null,
                'document_id' => (int) $doc['id'],
                'verification_code' => null,
            ];
        }
        $expectedHash = $this->computeContentHash($doc, $doc['signed_at'] ?? '');
        $valid = hash_equals($storedHash, $expectedHash);
        $sig = $doc['signature_data_json'] ?? null;
        $sigArr = is_string($sig) ? json_decode($sig, true) : $sig;
        $verificationCode = is_array($sigArr) ? ($sigArr['verification_code'] ?? null) : null;

        return [
            'valid' => $valid,
            'message' => $valid ? 'Document authentique.' : 'Document altéré.',
            'signed_at' => $doc['signed_at'] ?? null,
            'content_hash' => $storedHash,
            'verification_code' => $verificationCode,
            'document_id' => (int) $doc['id'],
        ];
    }

    public function verifyDocumentByUuid(string $uuid): array
    {
        $doc = $this->documentRepository->findByUuid($uuid, null);
        if (!$doc) {
            return ['valid' => false, 'message' => 'Document introuvable.'];
        }
        return $this->verifyDocument((int) $doc['id'], (int) $doc['tenant_id']);
    }

    /**
     * Libellé affiché pour une signature enregistrée.
     */
    public static function displayName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if ($name === '') {
            return 'Signature principale';
        }
        if (mb_strlen($name) > 80) {
            return mb_substr($name, 0, 80);
        }

        return $name;
    }

    /**
     * Enregistre une image de signature pour réutilisation (user_signatures + fichier).
     */
    public function saveUserSignature(int $userId, int $tenantId, string $sourcePathOrBase64, string $name = 'Signature principale', bool $isDefault = true): int
    {
        $relativePath = $this->storeUserSignatureImage($userId, $tenantId, $sourcePathOrBase64);
        $existing = $this->signatureRepository->listByUser($userId, $tenantId);
        if ($existing === []) {
            $isDefault = true;
        }

        return $this->signatureRepository->create($userId, $tenantId, self::displayName($name), $relativePath, $isDefault);
    }

    public function deleteUserSignature(int $id, int $userId, int $tenantId): void
    {
        $sig = $this->signatureRepository->findById($id, $userId, $tenantId);
        if (!$sig) {
            throw new \RuntimeException('Signature introuvable.');
        }
        $fullPath = $this->getSignatureFilePath((string) $sig['file_path'], true);
        $this->signatureRepository->delete($id, $userId, $tenantId);
        if (is_file($fullPath)) {
            unlink($fullPath);
        }
    }

    /**
     * Hash canonique : contenu, métadonnées, uuid, code de vérification, signataire, horodatage.
     * Documents signés avant l’introduction du code SIG-* : ancien format (5 champs + date) pour compatibilité.
     */
    public function computeContentHash(array $document, string $signedAt): string
    {
        $sigData = $document['signature_data_json'] ?? null;
        $verification = '';
        if ($sigData !== null) {
            $d = is_array($sigData) ? $sigData : (is_string($sigData) ? json_decode($sigData, true) : null);
            $verification = is_array($d) ? (string) ($d['verification_code'] ?? '') : '';
        }
        if ($verification === '') {
            $parts = [
                $document['body_rendered'] ?? '',
                $document['reference_number'] ?? '',
                $document['subject'] ?? '',
                $document['issuer_label'] ?? '',
                $document['destination_label'] ?? '',
                $signedAt,
            ];

            return hash('sha256', implode("\n", $parts));
        }
        $parts = [
            $document['body_rendered'] ?? '',
            $document['reference_number'] ?? '',
            $document['subject'] ?? '',
            $document['issuer_label'] ?? '',
            $document['destination_label'] ?? '',
            $document['classification_level'] ?? '',
            $document['uuid'] ?? '',
            $verification,
            (string) ($document['signed_by'] ?? ''),
            $signedAt,
        ];

        return hash('sha256', implode("\n", $parts));
    }

    private function generateVerificationCode(): string
    {
        return 'SIG-' . gmdate('Y-m-d') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    }

    private function storeDocumentSignatureImage(int $documentId, int $tenantId, string $base64): string
    {
        $dir = base_path(self::DOCUMENTS_SIGNATURE_BASE . '/' . $tenantId . '/' . $documentId);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $data = $this->decodeBase64Image($base64);
        if ($data === null) {
            throw new \RuntimeException('Image de signature invalide.');
        }
        $path = $dir . '/signature.png';
        if (file_put_contents($path, $data) === false) {
            throw new \RuntimeException('Impossible d\'enregistrer l\'image de signature.');
        }
        return $tenantId . '/' . $documentId . '/signature.png';
    }

    private function storeUserSignatureImage(int $userId, int $tenantId, string $pathOrBase64): string
    {
        $dir = base_path(self::SIGNATURES_BASE . '/' . $tenantId);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $data = null;
        if (str_starts_with($pathOrBase64, 'data:')) {
            $data = $this->decodeBase64Image($pathOrBase64);
        } elseif (is_file(base_path(self::DOCUMENTS_SIGNATURE_BASE . '/' . $pathOrBase64)) || is_file(base_path(self::SIGNATURES_BASE . '/' . $pathOrBase64))) {
            $full = base_path(self::DOCUMENTS_SIGNATURE_BASE . '/' . $pathOrBase64);
            if (!is_file($full)) {
                $full = base_path(self::SIGNATURES_BASE . '/' . $pathOrBase64);
            }
            $data = file_get_contents($full);
            if ($data === false) {
                $data = null;
            }
        }
        if ($data === null || $data === '') {
            throw new \RuntimeException('Le dessin de signature n’a pas pu être enregistré. Recommencez.');
        }
        if (!str_starts_with($data, "\x89PNG")) {
            throw new \RuntimeException('Le dessin de signature n’a pas pu être enregistré. Recommencez.');
        }
        if (strlen($data) > 800000) {
            throw new \RuntimeException('La signature est trop lourde. Dessinez-la à nouveau, plus simplement.');
        }
        $filename = $userId . '_' . uniqid('', true) . '.png';
        $relativePath = $tenantId . '/' . $filename;
        $fullPath = $dir . '/' . $filename;
        if (file_put_contents($fullPath, $data) === false) {
            throw new \RuntimeException('Impossible d\'enregistrer la signature utilisateur.');
        }
        return $relativePath;
    }

    private function decodeBase64Image(string $base64): ?string
    {
        if (preg_match('/^data:image\/(\w+);base64,(.+)$/s', $base64, $m)) {
            $raw = base64_decode(str_replace(["\r", "\n"], '', $m[2]), true);
            return $raw !== false ? $raw : null;
        }
        $raw = base64_decode(str_replace(["\r", "\n"], '', $base64), true);
        return $raw !== false ? $raw : null;
    }

    /**
     * Chemin physique complet pour une signature (document ou user).
     */
    public function getSignatureFilePath(string $relativePath, bool $isUserSignature = true): string
    {
        $base = $isUserSignature ? self::SIGNATURES_BASE : self::DOCUMENTS_SIGNATURE_BASE;
        return base_path($base . '/' . $relativePath);
    }
}
