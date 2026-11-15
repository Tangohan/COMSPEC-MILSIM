<?php

declare(strict_types=1);

namespace App\Services\PhotoLibrary;

use App\Repositories\PhotoLibraryRepository;
use App\Repositories\SseAuditLogRepository;
use App\Repositories\SseFileRecordRepository;
use App\Repositories\SseIdentityRepository;
use App\Support\ReconImageStorage;

/**
 * « Verser au dossier SSE » : la photo entre dans la photothèque des fichiers SSE (sse_file_photos, source « atak »,
 * référence « lib:<photo> »), rattachée à la fiche d'un sujet quand on la connaît (celle choisie, sinon l'unique sujet SSE
 * étiqueté sur un visage). Le cliché de la photothèque devient confidentiel (rangé hors racine web) et marqué SSE : il
 * n'est plus servi qu'aux lecteurs qui passent la porte SSE. Le retrait défait tout : copie SSE supprimée, drapeaux et
 * rangement d'avant rétablis. Les contrôles de portes sont faits par le service avant d'arriver ici ; chaque versement et
 * chaque retrait est écrit au journal d'audit SSE.
 *
 * Non repris : le rattachement à un dossier d'enquête (sse_cases, preuve) reste dans le lecteur de l'ATAK.
 */
class PhotoSseTransfer
{
    public function __construct(
        private ?PhotoLibraryRepository $photos = null,
        private ?SseFileRecordRepository $records = null,
        private ?SseIdentityRepository $identities = null,
        private ?SseAuditLogRepository $audit = null,
        private ?string $publicDir = null,
    ) {
        $this->publicDir ??= base_path('public');
    }

    private function photos(): PhotoLibraryRepository
    {
        return $this->photos ??= new PhotoLibraryRepository();
    }

    private function records(): SseFileRecordRepository
    {
        return $this->records ??= new SseFileRecordRepository();
    }

    private function identities(): SseIdentityRepository
    {
        return $this->identities ??= new SseIdentityRepository();
    }

    /** Une fiche SSE existe et n'est pas une fausse identité (comme SseFileRecordController::identityExists). */
    public function identityUsable(int $tenantId, int $identityId): bool
    {
        if ($identityId < 1) {
            return false;
        }
        $i = $this->identities()->find($identityId, $tenantId);

        return $i !== null && ($i['kind'] ?? '') !== 'forged';
    }

    /**
     * @param array<string, mixed> $row ligne recon_images
     * @param list<array<string, mixed>> $tags visages de la photo
     * @return array{ok: bool, error?: string, identity_id?: ?int}
     */
    public function transfer(array $row, array $tags, ?int $identityId, int $userId, string $actorLabel, PhotoVault $vault, ?string $originalRelative): array
    {
        $tenantId = (int) ($row['tenant_id'] ?? 0);
        $photoId = (int) ($row['id'] ?? 0);
        if ($identityId === null || $identityId < 1) {
            $identityId = $this->singleSubject($tags);
        }
        if ($identityId !== null && !$this->identityUsable($tenantId, $identityId)) {
            return ['ok' => false, 'error' => 'Fiche SSE introuvable.'];
        }
        $bytes = $vault->readOriginal($row, $originalRelative);
        if ($bytes === null) {
            return ['ok' => false, 'error' => 'Fichier de la photo introuvable.'];
        }
        $mime = $this->sniff($bytes);
        if ($mime === null) {
            return ['ok' => false, 'error' => 'Image non reconnue.'];
        }
        $sseRef = 'lib:' . $photoId;
        if ($this->records()->photoBySource($tenantId, 'atak', $sseRef) !== null) {
            return ['ok' => false, 'error' => 'Cette photo est déjà dans la photothèque SSE.'];
        }
        $relative = $this->storeCopy($tenantId, $bytes, $mime);
        if ($relative === null) {
            return ['ok' => false, 'error' => 'Enregistrement de la copie SSE impossible.'];
        }
        $size = @getimagesizefromstring($bytes);
        $caption = trim((string) ($row['title'] ?? '')) !== '' ? (string) $row['title'] : (string) ($row['caption'] ?? '');
        $ssePhotoId = $this->records()->addPhoto($tenantId, [
            'identity_id' => $identityId,
            'path' => $relative,
            'mime' => $mime,
            'width' => is_array($size) ? (int) $size[0] : null,
            'height' => is_array($size) ? (int) $size[1] : null,
            'bytes' => strlen($bytes),
            'sha1' => sha1($bytes),
            'kind' => 'field',
            'source' => 'atak',
            'source_ref' => $sseRef,
            'caption' => $caption !== '' ? mb_substr($caption, 0, 255) : null,
        ], $userId);

        $prevConfidential = !empty($row['is_confidential']) || ReconImageStorage::isPrivatePath((string) ($row['image_path'] ?? ''));
        $prevSse = !empty($row['is_sse']);
        $newPath = ReconImageStorage::relocate((string) ($row['image_path'] ?? ''), true);
        if ($newPath === null) {
            $this->records()->deletePhoto($tenantId, $ssePhotoId);
            @unlink($this->publicDir . '/' . $relative);

            return ['ok' => false, 'error' => 'Impossible de mettre la photo à l’abri.'];
        }
        $this->photos()->updatePhotoColumns($tenantId, $photoId, ['is_confidential' => 1, 'is_sse' => 1, 'image_path' => $newPath, 'thumb_path' => null]);
        $vault->purgeDerived($tenantId, $photoId);
        $this->photos()->addTransfer($tenantId, $photoId, $ssePhotoId, $identityId, $userId, $prevConfidential, $prevSse);
        $this->log($tenantId, $userId, $actorLabel, 'photo_library.sse_transfer', $photoId, null, ['sse_photo_id' => $ssePhotoId, 'identity_id' => $identityId]);

        return ['ok' => true, 'identity_id' => $identityId];
    }

    /**
     * @param array<string, mixed> $row ligne recon_images
     * @param array<string, mixed> $transfer ligne photo_library_sse active
     * @return array{ok: bool, error?: string}
     */
    public function withdraw(array $row, array $transfer, int $userId, string $actorLabel, PhotoVault $vault): array
    {
        $tenantId = (int) ($row['tenant_id'] ?? 0);
        $photoId = (int) ($row['id'] ?? 0);
        $ssePhotoId = (int) ($transfer['sse_photo_id'] ?? 0);
        $restoreConfidential = !empty($transfer['prev_confidential']);
        $path = (string) ($row['image_path'] ?? '');
        if (!$restoreConfidential) {
            $moved = ReconImageStorage::relocate($path, false);
            if ($moved === null) {
                return ['ok' => false, 'error' => 'Impossible de rendre la photo à son rangement d’origine.'];
            }
            $path = $moved;
        }
        if ($ssePhotoId > 0) {
            $copy = $this->records()->photo($tenantId, $ssePhotoId);
            if ($copy !== null && ($copy['source'] ?? '') === 'atak' && ($copy['source_ref'] ?? '') === 'lib:' . $photoId) {
                $this->records()->deletePhoto($tenantId, $ssePhotoId);
                $copyPath = (string) ($copy['path'] ?? '');
                if (preg_match('#^uploads/sse-id-photos/[0-9]{1,10}_[a-f0-9]{16}\.(?:jpg|png|webp)$#', $copyPath) === 1) {
                    @unlink($this->publicDir . '/' . $copyPath);
                }
            }
        }
        $this->photos()->updatePhotoColumns($tenantId, $photoId, [
            'is_confidential' => $restoreConfidential ? 1 : 0,
            'is_sse' => !empty($transfer['prev_sse']) ? 1 : 0,
            'image_path' => $path,
        ]);
        $vault->purgeDerived($tenantId, $photoId);
        $this->photos()->withdrawTransfer($tenantId, (int) $transfer['id'], $userId);
        $this->log($tenantId, $userId, $actorLabel, 'photo_library.sse_withdraw', $photoId, ['sse_photo_id' => $ssePhotoId], null);

        return ['ok' => true];
    }

    /** Le seul sujet SSE étiqueté sur les visages, sinon null. @param list<array<string, mixed>> $tags */
    public function singleSubject(array $tags): ?int
    {
        $ids = [];
        foreach ($tags as $t) {
            if (($t['target_type'] ?? '') === 'sse' && (int) ($t['target_id'] ?? 0) > 0) {
                $ids[(int) $t['target_id']] = true;
            }
        }

        return count($ids) === 1 ? (int) array_key_first($ids) : null;
    }

    private function sniff(string $bytes): ?string
    {
        return match (true) {
            str_starts_with($bytes, "\x89PNG\r\n\x1a\n") => 'image/png',
            str_starts_with($bytes, "\xFF\xD8\xFF") => 'image/jpeg',
            strlen($bytes) > 12 && substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP' => 'image/webp',
            default => null,
        };
    }

    /** Copie sous public/uploads/sse-id-photos, comme les photos envoyées depuis l'écran SSE (nom imprévisible). */
    private function storeCopy(int $tenantId, string $bytes, string $mime): ?string
    {
        $ext = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'][$mime] ?? null;
        if ($ext === null || $tenantId < 1) {
            return null;
        }
        $dir = rtrim((string) $this->publicDir, '/\\') . '/uploads/sse-id-photos';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return null;
        }
        $name = $tenantId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (@file_put_contents($dir . '/' . $name, $bytes) === false) {
            return null;
        }

        return 'uploads/sse-id-photos/' . $name;
    }

    /** @param array<string, mixed>|null $before @param array<string, mixed>|null $after */
    private function log(int $tenantId, int $userId, string $actorLabel, string $action, int $photoId, ?array $before, ?array $after): void
    {
        try {
            ($this->audit ??= new SseAuditLogRepository())->write([
                'tenant_id' => $tenantId,
                'actor_user_id' => $userId,
                'actor_label' => $actorLabel !== '' ? $actorLabel : 'Membre',
                'action' => $action,
                'object_type' => 'recon_image',
                'object_id' => $photoId,
                'before' => $before,
                'after' => $after,
            ]);
        } catch (\Throwable $e) {
            error_log('Photothèque : audit SSE non écrit : ' . $e->getMessage());
        }
    }
}
