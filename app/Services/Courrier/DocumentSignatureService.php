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

        $contentHash = null;
        if ($secureHash) {
            $contentHash = $this->computeContentHash($doc, $signedAt);
        }

        $signatureData = [
            'signature_image_path' => $signatureImagePath,
            'stamp_original_signed' => $stamps['stamp_original_signed'] ?? '',
            'stamp_name_signature' => $stamps['stamp_name_signature'] ?? '',
            'stamp_grade' => $stamps['stamp_grade'] ?? '',
            'signature_source' => $signatureSource,
        ];

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
            return ['valid' => false, 'message' => 'Document introuvable.'];
        }
        $storedHash = $doc['content_hash'] ?? null;
        if ($storedHash === null || $storedHash === '') {
            return ['valid' => null, 'message' => 'Document non sécurisé par hash.', 'signed_at' => $doc['signed_at'] ?? null];
        }
        $expectedHash = $this->computeContentHash($doc, $doc['signed_at'] ?? '');
        $valid = hash_equals($storedHash, $expectedHash);
        return [
            'valid' => $valid,
            'message' => $valid ? 'Document authentique.' : 'Document altéré.',
            'signed_at' => $doc['signed_at'] ?? null,
            'content_hash' => $storedHash,
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
     * Enregistre une image de signature pour réutilisation (user_signatures + fichier).
     */
    public function saveUserSignature(int $userId, int $tenantId, string $sourcePathOrBase64, string $name = 'Signature principale', bool $isDefault = true): int
    {
        $relativePath = $this->storeUserSignatureImage($userId, $tenantId, $sourcePathOrBase64);
        return $this->signatureRepository->create($userId, $tenantId, $name, $relativePath, $isDefault);
    }

    /**
     * Hash canonique : body_rendered + reference_number + subject + issuer_label + destination_label + signed_at.
     */
    public function computeContentHash(array $document, string $signedAt): string
    {
        $parts = [
            $document['body_rendered'] ?? '',
            $document['reference_number'] ?? '',
            $document['subject'] ?? '',
            $document['issuer_label'] ?? '',
            $document['destination_label'] ?? '',
            $signedAt,
        ];
        $canonical = implode("\n", $parts);
        return hash('sha256', $canonical);
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
        }
        if ($data === null || $data === '') {
            throw new \RuntimeException('Image de signature invalide.');
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
