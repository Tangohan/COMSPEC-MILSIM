<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Repositories\DocumentRepository;
use App\Repositories\DocumentVersionRepository;

class DocumentUploadService
{
    private const MAX_SIZE = 10 * 1024 * 1024; // 10 MB
    private const ALLOWED_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'video/mp4',
    ];
    private const MIME_TO_EXT = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'video/mp4' => 'mp4',
    ];

    public function __construct(
        private DocumentRepository $documentRepository,
        private DocumentVersionRepository $versionRepository
    ) {}

    /**
     * @param array{tmp_name: string, size: int, name: string} $file
     * @throws \InvalidArgumentException
     */
    public function validateFile(array $file): void
    {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new \InvalidArgumentException('Fichier invalide ou absent.');
        }
        if (($file['size'] ?? 0) > self::MAX_SIZE) {
            throw new \InvalidArgumentException('Fichier trop volumineux (max 10 Mo).');
        }
        $mime = $this->getMime($file['tmp_name']);
        if (!in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new \InvalidArgumentException('Type de fichier non autorisé.');
        }
        $ext = self::MIME_TO_EXT[$mime] ?? null;
        if ($ext === null) {
            throw new \InvalidArgumentException('Extension non gérée.');
        }
    }

    /**
     * Stocke le fichier et retourne le chemin relatif (tenant_id/document_id/vN.ext).
     * @param array{tmp_name: string, size: int, name: string} $file
     */
    public function storeFile(int $tenantId, int $documentId, array $file, int $versionNumber): string
    {
        $mime = $this->getMime($file['tmp_name']);
        $ext = self::MIME_TO_EXT[$mime] ?? 'bin';
        $relativeDir = $tenantId . '/' . $documentId;
        $baseDir = base_path('storage/documents/' . $relativeDir);
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0755, true);
        }
        $filename = 'v' . $versionNumber . '.' . $ext;
        $fullPath = $baseDir . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            throw new \RuntimeException('Impossible d\'enregistrer le fichier.');
        }
        return $relativeDir . '/' . $filename;
    }

    public function computeChecksum(string $relativePath): string
    {
        $fullPath = base_path('storage/documents/' . $relativePath);
        if (!is_file($fullPath)) {
            return '';
        }
        return hash_file('sha256', $fullPath) ?: '';
    }

    /**
     * Crée une nouvelle version en base et la définit comme courante.
     * @param array{tmp_name: string, size: int, name: string} $file
     * @return array{version_id: int, version_number: int, file_path: string}
     */
    public function uploadNewVersion(
        int $tenantId,
        int $documentId,
        array $file,
        ?string $changeNotes,
        int $userId
    ): array {
        $this->validateFile($file);
        $nextVersion = $this->versionRepository->getNextVersionNumber($documentId);
        $relativePath = $this->storeFile($tenantId, $documentId, $file, $nextVersion);
        $checksum = $this->computeChecksum($relativePath);
        $mime = $this->getMime(base_path('storage/documents/' . $relativePath));
        $size = (int) (is_file(base_path('storage/documents/' . $relativePath)) ? filesize(base_path('storage/documents/' . $relativePath)) : $file['size'] ?? 0);

        $originalName = isset($file['name']) ? basename((string) $file['name']) : null;

        $versionId = $this->versionRepository->create($documentId, [
            'version_number' => $nextVersion,
            'file_path' => $relativePath,
            'original_name' => $originalName,
            'checksum' => $checksum,
            'mime_type' => $mime,
            'size' => $size,
            'created_by' => $userId,
            'change_notes' => $changeNotes,
        ]);
        $this->versionRepository->setCurrentVersion($documentId, $versionId);

        $this->documentRepository->update($documentId, $tenantId, ['current_file_id' => $versionId]);

        return [
            'version_id' => $versionId,
            'version_number' => $nextVersion,
            'file_path' => $relativePath,
        ];
    }

    private function getMime(string $path): string
    {
        if (!is_file($path)) {
            return '';
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $path) ?: '';
        finfo_close($finfo);
        return $mime;
    }
}
