<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use App\Repositories\EnlistmentRepository;

/**
 * Réception des fichiers / audio déposés par le candidat sur le portail de suivi.
 */
final class EnlistmentPortalAttachmentService
{
    private const MAX_FILE_BYTES = 15 * 1024 * 1024;

    private const MAX_AUDIO_BYTES = 25 * 1024 * 1024;

    /** @var array<string, true> */
    private const FILE_MIMES = [
        'application/pdf' => true,
        'image/jpeg' => true,
        'image/png' => true,
        'image/webp' => true,
        'text/plain' => true,
    ];

    /** @var array<string, true> */
    private const AUDIO_MIMES = [
        'audio/webm' => true,
        'audio/mpeg' => true,
        'audio/mp4' => true,
        'audio/wav' => true,
        'audio/x-wav' => true,
        'audio/ogg' => true,
        'audio/m4a' => true,
    ];

    public function __construct(
        private EnlistmentRepository $enlistmentRepository,
    ) {}

    /**
     * @param array<string, mixed>|null $file champ unique type $_FILES['…']
     * @return array{ok:true, id:int, kind:string, mime:string, original_name:string}|array{ok:false, error:string}
     */
    public function storeCandidateUpload(
        int $tenantId,
        int $enlistmentId,
        bool $allowFiles,
        bool $allowAudio,
        ?array $file
    ): array {
        if (!$this->enlistmentRepository->hasPortalAttachmentsTable()) {
            return ['ok' => false, 'error' => 'Les pièces jointes ne sont pas encore disponibles sur cette installation.'];
        }
        if (!$allowFiles && !$allowAudio) {
            return ['ok' => false, 'error' => 'L’équipe n’a pas activé l’envoi de fichiers pour ce dossier.'];
        }
        if ($file === null || !isset($file['tmp_name'], $file['error'], $file['name'])) {
            return ['ok' => false, 'error' => 'Aucun fichier reçu.'];
        }
        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Transfert interrompu. Réessayez avec un fichier plus léger si besoin.'];
        }
        $tmp = (string) $file['tmp_name'];
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'error' => 'Envoi invalide.'];
        }
        $size = (int) ($file['size'] ?? 0);
        $original = trim((string) ($file['name'] ?? 'fichier'));
        if ($original === '') {
            $original = 'fichier';
        }

        $mime = $this->detectMime($tmp);
        $isAudio = str_starts_with($mime, 'audio/');
        if ($isAudio) {
            if (!$allowAudio) {
                return ['ok' => false, 'error' => 'L’équipe n’a pas activé l’envoi d’enregistrements audio pour ce dossier.'];
            }
            if (!isset(self::AUDIO_MIMES[$mime])) {
                return ['ok' => false, 'error' => 'Format audio non pris en charge (formats courants : MP3, WAV, WebM, OGG).'];
            }
            if ($size > self::MAX_AUDIO_BYTES) {
                return ['ok' => false, 'error' => 'Fichier audio trop volumineux (limite 25 Mo).'];
            }
            $kind = 'audio';
        } else {
            if (!$allowFiles) {
                return ['ok' => false, 'error' => 'L’équipe n’a pas activé l’envoi de documents pour ce dossier.'];
            }
            if (!isset(self::FILE_MIMES[$mime])) {
                return ['ok' => false, 'error' => 'Type de fichier non accepté (PDF, images JPEG/PNG/WebP ou texte simple).'];
            }
            if ($size > self::MAX_FILE_BYTES) {
                return ['ok' => false, 'error' => 'Fichier trop volumineux (limite 15 Mo).'];
            }
            $kind = 'file';
        }

        $ext = $this->guessExtension($mime, $original);
        $rand = bin2hex(random_bytes(16));
        $relDir = 'enlistment-portal/' . $tenantId . '/' . $enlistmentId;
        $absDir = base_path('storage/uploads/' . $relDir);
        if (!is_dir($absDir) && !@mkdir($absDir, 0775, true)) {
            return ['ok' => false, 'error' => 'Impossible d’enregistrer le fichier pour le moment.'];
        }
        $fileName = $rand . ($ext !== '' ? '.' . $ext : '');
        $absPath = $absDir . DIRECTORY_SEPARATOR . $fileName;
        if (!@move_uploaded_file($tmp, $absPath)) {
            return ['ok' => false, 'error' => 'Enregistrement du fichier impossible.'];
        }
        @chmod($absPath, 0644);

        $storagePath = $relDir . '/' . $fileName;
        $id = $this->enlistmentRepository->insertCandidatePortalAttachment(
            $tenantId,
            $enlistmentId,
            $kind,
            $original,
            $mime,
            $size,
            $storagePath
        );
        if ($id < 1) {
            @unlink($absPath);

            return ['ok' => false, 'error' => 'Impossible d’enregistrer la pièce jointe.'];
        }

        return ['ok' => true, 'id' => $id, 'kind' => $kind, 'mime' => $mime, 'original_name' => $original];
    }

    public function absolutePathForStorage(string $storagePath): string
    {
        $storagePath = str_replace(['..', '\\'], ['', '/'], $storagePath);

        return base_path('storage/uploads/' . ltrim($storagePath, '/'));
    }

    private function detectMime(string $tmpPath): string
    {
        if (function_exists('finfo_open')) {
            $f = finfo_open(FILEINFO_MIME_TYPE);
            if ($f !== false) {
                $m = finfo_file($f, $tmpPath) ?: '';
                finfo_close($f);
                $m = strtolower(trim((string) $m));
                if ($m !== '') {
                    return $m;
                }
            }
        }

        return 'application/octet-stream';
    }

    private function guessExtension(string $mime, string $originalName): string
    {
        $fromMime = match ($mime) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'text/plain' => 'txt',
            'audio/webm' => 'webm',
            'audio/mpeg' => 'mp3',
            'audio/mp4' => 'm4a',
            'audio/m4a' => 'm4a',
            'audio/wav' => 'wav',
            'audio/x-wav' => 'wav',
            'audio/ogg' => 'ogg',
            default => '',
        };
        if ($fromMime !== '') {
            return $fromMime;
        }
        $base = basename($originalName);
        if (str_contains($base, '.')) {
            $e = strtolower(pathinfo($base, PATHINFO_EXTENSION));

            return preg_match('/^[a-z0-9]{1,8}$/', $e) ? $e : '';
        }

        return '';
    }
}
