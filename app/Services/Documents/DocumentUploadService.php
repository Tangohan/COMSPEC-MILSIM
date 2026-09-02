<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Repositories\DocumentRepository;
use App\Repositories\DocumentVersionRepository;
use App\Repositories\ModerationArtifactRepository;
use App\Services\Moderation\ContentModerationConfig;
use App\Services\Moderation\ContentModerationOrchestrator;
use App\Services\Moderation\ModerationArtifactState;
use App\Services\Moderation\ModerationBlockedException;
use App\Services\Moderation\ModerationQuarantineException;
use App\Services\Moderation\ModerationSourceType;
use App\Support\DocumentAttachedFile;

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
        private DocumentVersionRepository $versionRepository,
        private ContentModerationOrchestrator $moderationOrchestrator,
        private ModerationArtifactRepository $moderationArtifactRepository,
        private ContentModerationConfig $moderationConfig
    ) {
    }

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

    public function computeChecksum(string $relativePath): string
    {
        $fullPath = base_path('storage/documents/' . $relativePath);
        if (!is_file($fullPath)) {
            return '';
        }

        return hash_file('sha256', $fullPath) ?: '';
    }

    /**
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
        $mime = $this->getMime($file['tmp_name']);
        $ext = self::MIME_TO_EXT[$mime] ?? 'bin';
        $originalName = isset($file['name']) ? basename((string) $file['name']) : 'file';

        $relativeDir = $tenantId . '/' . $documentId;
        $filename = 'v' . $nextVersion . '.' . $ext;
        $finalRelative = $relativeDir . '/' . $filename;

        $quarantineRelative = 'quarantine/' . $tenantId . '/doc_' . $documentId . '_v' . $nextVersion . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $fullQuarantine = base_path('storage/' . $quarantineRelative);
        $dir = dirname($fullQuarantine);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (!move_uploaded_file($file['tmp_name'], $fullQuarantine)) {
            throw new \RuntimeException('Impossible d\'enregistrer le fichier.');
        }

        $scan = $this->moderationOrchestrator->scanBinaryFile($fullQuarantine, $mime, $originalName);

        if ($scan->state === ModerationArtifactState::REJECTED) {
            @unlink($fullQuarantine);
            throw new ModerationBlockedException(
                'Le fichier a été refusé par la modération automatique (score trop élevé ou analyse antivirus).'
            );
        }

        if ($scan->state === ModerationArtifactState::QUARANTINED) {
            $checksum = hash_file('sha256', $fullQuarantine) ?: '';
            $expires = (new \DateTimeImmutable())->modify('+' . $this->moderationConfig->quarantineTtlDays . ' days');
            $scanLog = $scan->scanLog;
            $scanLog['pending_document'] = [
                'document_id' => $documentId,
                'version_number' => $nextVersion,
                'target_relative_path' => $finalRelative,
            ];
            $artifactId = $this->moderationArtifactRepository->insert($tenantId, [
                'user_id' => $userId,
                'source_type' => ModerationSourceType::DOCUMENT_VERSION,
                'source_id' => 0,
                'source_key' => 'document:' . $documentId . ':v:' . $nextVersion,
                'file_path' => $quarantineRelative,
                'original_name' => $originalName,
                'mime' => $mime,
                'sha256' => $checksum,
                'state' => ModerationArtifactState::QUARANTINED,
                'risk_score' => $scan->riskScore,
                'reason_codes' => $scan->reasonCodes,
                'scan_log' => $scanLog,
                'ruleset_version' => $this->moderationConfig->rulesetVersion,
                'expires_at' => $expires->format('Y-m-d H:i:s'),
            ]);
            throw new ModerationQuarantineException(
                'Fichier mis en quarantaine en attente de validation par un modérateur.',
                $artifactId
            );
        }

        $baseDoc = base_path('storage/documents/' . $relativeDir);
        if (!is_dir($baseDoc)) {
            mkdir($baseDoc, 0755, true);
        }
        $fullFinal = base_path('storage/documents/' . $finalRelative);
        if (!rename($fullQuarantine, $fullFinal)) {
            @unlink($fullQuarantine);
            throw new \RuntimeException('Impossible de finaliser le fichier.');
        }

        $checksum = hash_file('sha256', $fullFinal) ?: '';
        $size = (int) filesize($fullFinal);

        $versionId = $this->versionRepository->create($documentId, [
            'version_number' => $nextVersion,
            'file_path' => $finalRelative,
            'original_name' => $originalName,
            'checksum' => $checksum,
            'mime_type' => $mime,
            'size' => $size,
            'created_by' => $userId,
            'change_notes' => $changeNotes,
        ]);
        $this->versionRepository->setCurrentVersion($documentId, $versionId);
        $this->documentRepository->update($documentId, $tenantId, ['current_file_id' => $versionId]);

        if ($this->moderationArtifactRepository->tableExists()) {
            $this->moderationArtifactRepository->insert($tenantId, [
                'user_id' => $userId,
                'source_type' => ModerationSourceType::DOCUMENT_VERSION,
                'source_id' => $versionId,
                'source_key' => null,
                'file_path' => 'documents/' . $finalRelative,
                'original_name' => $originalName,
                'mime' => $mime,
                'sha256' => $checksum,
                'state' => ModerationArtifactState::CLEAN,
                'risk_score' => $scan->riskScore,
                'reason_codes' => $scan->reasonCodes,
                'scan_log' => $scan->scanLog,
                'ruleset_version' => $this->moderationConfig->rulesetVersion,
                'expires_at' => null,
            ]);
        }

        return [
            'version_id' => $versionId,
            'version_number' => $nextVersion,
            'file_path' => $finalRelative,
        ];
    }

    /**
     * Approuve un fichier document en quarantaine : crée la version et déplace le fichier.
     *
     * @return array{version_id: int, version_number: int, file_path: string}
     */
    public function approveQuarantinedDocumentArtifact(array $artifact, int $tenantId, int $moderatorUserId, ?string $changeNotes = null): array
    {
        $documentId = (int) ($artifact['scan_log']['pending_document']['document_id'] ?? 0);
        if ($documentId <= 0) {
            throw new \RuntimeException('Artefact invalide pour promotion document.');
        }
        $doc = $this->documentRepository->findById($documentId, $tenantId);
        if (!$doc) {
            throw new \RuntimeException('Document introuvable.');
        }
        $quarantineRel = (string) ($artifact['file_path'] ?? '');
        $fullQ = base_path('storage/' . $quarantineRel);
        if (!is_file($fullQ)) {
            throw new \RuntimeException('Fichier en quarantaine introuvable.');
        }
        $nextVersion = $this->versionRepository->getNextVersionNumber($documentId);
        $mime = $this->getMime($fullQ);
        $ext = self::MIME_TO_EXT[$mime] ?? 'bin';
        $orig = (string) ($artifact['original_name'] ?? '');
        if ($ext === 'bin' && $orig !== '') {
            $pe = pathinfo($orig, PATHINFO_EXTENSION);
            if (is_string($pe) && $pe !== '') {
                $ext = strtolower($pe);
            }
        }
        $relativeDir = $tenantId . '/' . $documentId;
        $target = $relativeDir . '/v' . $nextVersion . '.' . $ext;
        $baseDoc = base_path('storage/documents/' . $relativeDir);
        if (!is_dir($baseDoc)) {
            mkdir($baseDoc, 0755, true);
        }
        $fullFinal = base_path('storage/documents/' . $target);
        if (!rename($fullQ, $fullFinal)) {
            throw new \RuntimeException('Impossible de déplacer le fichier vers le stockage documentaire.');
        }

        $mime = $this->getMime($fullFinal);
        $checksum = hash_file('sha256', $fullFinal) ?: '';
        $size = (int) filesize($fullFinal);
        $versionNumber = $nextVersion;
        $originalName = $artifact['original_name'] ?? basename($target);

        $versionId = $this->versionRepository->create($documentId, [
            'version_number' => $versionNumber,
            'file_path' => $target,
            'original_name' => $originalName,
            'checksum' => $checksum,
            'mime_type' => $mime,
            'size' => $size,
            'created_by' => $moderatorUserId,
            'change_notes' => $changeNotes,
        ]);
        $this->versionRepository->setCurrentVersion($documentId, $versionId);
        $this->documentRepository->update($documentId, $tenantId, ['current_file_id' => $versionId]);

        return [
            'version_id' => $versionId,
            'version_number' => $versionNumber,
            'file_path' => $target,
        ];
    }

    /**
     * Retire la pièce jointe courante : la fiche reste, le pointeur est vidé.
     * Si le fichier existe encore, il est rangé à part (pas de pièce inventée).
     *
     * @return array{had_file: bool, archived: bool}
     */
    public function detachCurrentFile(int $tenantId, int $documentId): array
    {
        $doc = $this->documentRepository->findById($documentId, $tenantId);
        if (!$doc) {
            throw new \RuntimeException('Document introuvable.');
        }
        $relative = trim((string) ($doc['file_path'] ?? ''));
        $archived = false;
        if (DocumentAttachedFile::hasPointer($relative)) {
            $full = base_path('storage/documents/' . $relative);
            if (is_file($full)) {
                $archiveRel = DocumentAttachedFile::archiveRelativePath($tenantId, $documentId, $relative);
                $dest = base_path('storage/documents/' . $archiveRel);
                $archived = DocumentAttachedFile::moveAsideIfPresent($full, $dest);
            }
        }
        $versionId = (int) ($doc['version_id'] ?? 0);
        if ($versionId > 0) {
            $this->versionRepository->clearFilePointer($versionId);
        }
        $this->documentRepository->update($documentId, $tenantId, ['current_file_id' => null]);

        return [
            'had_file' => DocumentAttachedFile::hasPointer($relative),
            'archived' => $archived,
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
