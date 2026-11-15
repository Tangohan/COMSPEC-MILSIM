<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseFileRecordRepository;
use App\Repositories\SseIdentityEventRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseWantedAttachmentRepository;
use App\Repositories\SseWantedNoticeRepository;
use App\Support\SseImageStorage;
use App\Support\SseWantedCatalog as C;

/**
 * Pièces jointes d'un avis de recherche : 0 à 6 images (portrait, signe distinctif, véhicule, lieu, autre), dont une
 * « principale » pour le PDF. Rangées dans l'espace PRIVÉ (SseImageStorage, dossier « sse-wanted », jamais sous public/), servies
 * seulement par la route contrôlée /atak/sse/avis/pieces/{id}/image.
 *
 * Contrôles à l'ajout : vrai type lu dans le contenu (JPEG, PNG, WebP), 5 Mo au plus, image réencodée (métadonnées EXIF, GPS et
 * XMP perdues), miniature, communauté de la session, droit de gérer les fichiers SSE. Si l'identité de l'avis est celle d'un
 * MEMBRE (TRÈS SECRET (TS), SseMemberImageGate), rien n'est joint, listé ni servi sans l'autorisation hiérarchique du lecteur.
 * Chaque ajout et chaque retrait est noté dans la chronologie de l'identité.
 *
 * Pour le PDF : {@see self::forNotice()} (lecteur de la session, mêmes règles). Les images ne vont jamais au jeu (Arma ne charge
 * pas d'image HTTP) : le jeu ne reçoit que {@see self::export()} (type et légende).
 */
final class SseWantedAttachments
{
    /** Types de pièce (clé => [français, anglais]). */
    public const KINDS = [
        'portrait' => ['Portrait', 'Portrait'],
        'mark' => ['Signe distinctif', 'Distinguishing mark'],
        'vehicle' => ['Véhicule', 'Vehicle'],
        'place' => ['Lieu', 'Place'],
        'other' => ['Autre', 'Other'],
    ];

    public const MAX_BYTES = 5 * 1024 * 1024;

    public function __construct(
        private ?SseWantedAttachmentRepository $repo = null,
        private ?SseIdImageService $images = null,
        private ?SseMemberImageGate $gate = null,
        private ?SseIdentityRepository $identities = null,
        private ?SseIdentityEventRepository $events = null,
        private ?SseFileRecordRepository $records = null,
        private ?string $privateDir = null,
    ) {
    }

    private function repo(): SseWantedAttachmentRepository
    {
        return $this->repo ??= new SseWantedAttachmentRepository();
    }

    private function images(): SseIdImageService
    {
        return $this->images ??= new SseIdImageService(null, null, $this->privateDir);
    }

    private function gate(): SseMemberImageGate
    {
        return $this->gate ??= SseMemberImageGate::forSession();
    }

    private function records(): SseFileRecordRepository
    {
        return $this->records ??= new SseFileRecordRepository();
    }

    /**
     * Pièces d'un avis pour le PDF et les écrans : chemins disque (image et miniature), adresses des routes contrôlées. Liste vide
     * si l'identité est celle d'un membre que le lecteur n'a pas le droit de voir, ou si la table n'existe pas encore.
     *
     * @param int|null $tenantId communauté ; sans elle, celle de l'avis lui-même (appel interne, déjà autorisé)
     * @return list<array<string, mixed>>
     */
    public static function forNotice(int $noticeId, ?int $tenantId = null, ?self $service = null): array
    {
        $service ??= new self();
        try {
            $notice = $tenantId !== null
                ? (new SseWantedNoticeRepository())->find($tenantId, $noticeId)
                : $service->noticeAnyTenant($noticeId);
            if ($notice === null || !SseWantedAttachmentRepository::ready()) {
                return [];
            }

            return $service->listFor($notice['tenant_id'], $notice);
        } catch (\Throwable $e) {
            error_log('Avis de recherche : pièces illisibles : ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Pièces de l'avis (lecteur de la session). Identité de membre non autorisée : liste vide.
     *
     * @param array<string, mixed> $notice ligne d'avis (id, tenant_id, identity_id)
     * @return list<array<string, mixed>>
     */
    public function listFor(int $tenantId, array $notice): array
    {
        if (!$this->identityVisible($tenantId, (int) $notice['identity_id'])['allowed']) {
            return [];
        }
        $out = [];
        foreach ($this->repo()->forNotice($tenantId, (int) $notice['id']) as $att) {
            $full = SseImageStorage::absolute($att['path'], null, $this->privateDir);
            if ($full === null) {
                continue;
            }
            $thumb = $att['thumb_path'] !== null ? SseImageStorage::absolute($att['thumb_path'], null, $this->privateDir) : null;
            $out[] = $att + [
                'kind_fr' => C::label(self::KINDS, $att['kind'], 'fr'), 'kind_en' => C::label(self::KINDS, $att['kind'], 'en'),
                'file' => $full, 'thumb_file' => $thumb ?? $full,
                'url' => self::imageUrl((int) $att['id'], false), 'thumb_url' => self::imageUrl((int) $att['id'], true),
            ];
        }

        return $out;
    }

    /** Adresse de la route contrôlée d'une pièce (jamais un fichier public). */
    public static function imageUrl(int $id, bool $thumb): string
    {
        $path = 'atak/sse/avis/pieces/' . $id . '/image' . ($thumb ? '?v=mini' : '');

        return function_exists('url') ? (string) url($path) : '/' . $path;
    }

    /**
     * Ce que le jeu et les listes reçoivent : type et légende seulement (aucune image, aucun chemin).
     *
     * @param list<array<string, mixed>> $attachments
     * @return list<array{id: int, kind: string, caption: string, main: bool}>
     */
    public static function export(array $attachments): array
    {
        $out = [];
        foreach ($attachments as $a) {
            $out[] = ['id' => (int) $a['id'], 'kind' => (string) $a['kind'], 'caption' => (string) ($a['caption'] ?? ''), 'main' => !empty($a['is_main'])];
        }

        return $out;
    }

    /**
     * Le lecteur peut-il voir les images de cette identité ? Une identité de membre est TRÈS SECRET (TS).
     *
     * @return array{allowed: bool, confidential: bool, reason: string, owner: ?int}
     */
    public function identityVisible(int $tenantId, int $identityId): array
    {
        $r = $this->gate()->forIdentity($tenantId, $identityId);

        return ['allowed' => (bool) $r['allowed'], 'confidential' => (bool) $r['confidential'], 'reason' => (string) $r['reason'], 'owner' => $r['owner'] ?? null];
    }

    /**
     * Ajoute une image envoyée (fichier déjà sur le disque et reçu par le formulaire).
     *
     * @param array<string, mixed> $notice ligne d'avis
     * @param array{kind?: mixed, caption?: mixed, main?: mixed} $meta
     * @return array{ok: true, id: int}|array{ok: false, error: string}
     */
    public function addFile(int $tenantId, array $notice, string $source, array $meta, SseWantedAccess $access): array
    {
        $size = @filesize($source);
        if ($size === false || $size <= 0 || $size > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'L’image dépasse 5 Mo ou n’a pas pu être reçue.'];
        }

        return $this->addBytes($tenantId, $notice, (string) @file_get_contents($source), $meta, $access, null);
    }

    /**
     * Joint à l'avis une photo déjà rattachée à la même identité dans la photothèque (copie : l'avis garde sa propre image).
     *
     * @param array<string, mixed> $notice
     * @param array{kind?: mixed, caption?: mixed, main?: mixed} $meta
     * @return array{ok: true, id: int}|array{ok: false, error: string}
     */
    public function addFromPhoto(int $tenantId, array $notice, int $photoId, array $meta, SseWantedAccess $access): array
    {
        $photo = $this->records()->photo($tenantId, $photoId);
        if ($photo === null || (int) ($photo['identity_id'] ?? 0) !== (int) $notice['identity_id'] || ($photo['visibility'] ?? '') === 'hidden') {
            return ['ok' => false, 'error' => 'Photo introuvable parmi celles de cette identité.'];
        }
        if (!$this->gate()->forPhoto($tenantId, $photo)['allowed']) {
            return ['ok' => false, 'error' => 'Cette photo est classée TRÈS SECRET (TS) : vous n’y avez pas accès.'];
        }
        $abs = (new SseSeekPhotoService($this->records()))->absolutePath($photo, $tenantId) ?? SseMemberImageGate::publicAbsolutePath($photo, null, $this->privateDir);
        $size = $abs !== null ? @filesize($abs) : false;
        if ($abs === null || $size === false || $size <= 0 || $size > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'Cette photo n’est pas lisible ou dépasse 5 Mo.'];
        }
        $meta['caption'] ??= $photo['caption'] ?? null;

        return $this->addBytes($tenantId, $notice, (string) @file_get_contents($abs), $meta, $access, $photoId);
    }

    /**
     * @param array<string, mixed> $notice
     * @param array<string, mixed> $meta
     * @return array{ok: true, id: int}|array{ok: false, error: string}
     */
    private function addBytes(int $tenantId, array $notice, string $bytes, array $meta, SseWantedAccess $access, ?int $sourcePhotoId): array
    {
        if (!$access->canManage() || $access->tenantId() !== $tenantId || (int) ($notice['tenant_id'] ?? 0) !== $tenantId) {
            return ['ok' => false, 'error' => 'Action non autorisée.'];
        }
        if (!SseWantedAttachmentRepository::ready()) {
            return ['ok' => false, 'error' => 'Les pièces jointes ne sont pas encore disponibles (étape de base de données en attente).'];
        }
        $identityId = (int) $notice['identity_id'];
        if (!$this->identityVisible($tenantId, $identityId)['allowed']) {
            return ['ok' => false, 'error' => 'L’identité de cet avis est celle d’un membre (TRÈS SECRET (TS)) : aucune image n’y est jointe sans autorisation hiérarchique.'];
        }
        if (count($this->repo()->forNotice($tenantId, (int) $notice['id'])) >= SseWantedAttachmentRepository::MAX_PER_NOTICE) {
            return ['ok' => false, 'error' => 'Six images au plus par avis.'];
        }
        // Mêmes contrôles que partout dans le portail : vrai type, taille, réencodage sans métadonnées, rangement privé.
        $stored = $this->images()->storeBytes($bytes, 'wanted', $tenantId);
        if (!$stored['ok'] || !isset($stored['path'])) {
            return ['ok' => false, 'error' => (string) ($stored['error'] ?? 'Image refusée.')];
        }
        $thumb = $this->images()->storeBytes($bytes, 'wanted_thumb', $tenantId);
        $abs = (string) SseImageStorage::absolute($stored['path'], null, $this->privateDir);
        $info = $abs !== '' ? @getimagesize($abs) : false;
        $kind = isset(self::KINDS[(string) ($meta['kind'] ?? '')]) ? (string) $meta['kind'] : 'other';
        $caption = C::line($meta['caption'] ?? '', 160);
        $id = $this->repo()->add($tenantId, (int) $notice['id'], $identityId, [
            'kind' => $kind, 'caption' => $caption !== '' ? $caption : null, 'path' => $stored['path'],
            'thumb_path' => ($thumb['ok'] ?? false) ? ($thumb['path'] ?? null) : null,
            'mime' => is_array($info) ? (string) ($info['mime'] ?? 'image/jpeg') : 'image/jpeg',
            'width' => is_array($info) ? (int) $info[0] : null, 'height' => is_array($info) ? (int) $info[1] : null,
            'bytes' => $abs !== '' ? (int) filesize($abs) : null, 'sha1' => $abs !== '' ? (string) sha1_file($abs) : null,
            'is_main' => !empty($meta['main']), 'source_photo_id' => $sourcePhotoId,
        ], $access->userId());
        $this->trace($tenantId, $identityId, (int) $notice['id'], 'notice.attachment_added', sprintf(
            'Image jointe à l’avis %s : %s.',
            (string) ($notice['ref'] ?? ''),
            mb_strtolower(C::label(self::KINDS, $kind))
        ), ['ref' => $notice['ref'] ?? '', 'attachment_id' => $id, 'kind' => $kind, 'source_photo_id' => $sourcePhotoId], $access);

        return ['ok' => true, 'id' => $id];
    }

    /**
     * Retire une pièce (ligne et fichiers).
     *
     * @param array<string, mixed> $notice
     * @return array{ok: true}|array{ok: false, error: string}
     */
    public function remove(int $tenantId, array $notice, int $attachmentId, SseWantedAccess $access): array
    {
        if (!$access->canManage() || $access->tenantId() !== $tenantId) {
            return ['ok' => false, 'error' => 'Action non autorisée.'];
        }
        $att = $this->repo()->find($tenantId, $attachmentId);
        if ($att === null || (int) $att['notice_id'] !== (int) $notice['id']) {
            return ['ok' => false, 'error' => 'Pièce introuvable.'];
        }
        if (!$this->identityVisible($tenantId, (int) $notice['identity_id'])['allowed']) {
            return ['ok' => false, 'error' => 'Action non autorisée.'];
        }
        $this->repo()->delete($tenantId, $attachmentId);
        foreach ([$att['path'], $att['thumb_path']] as $p) {
            $abs = $p !== null ? SseImageStorage::absolute((string) $p, null, $this->privateDir) : null;
            if ($abs !== null) {
                @unlink($abs);
            }
        }
        $this->trace($tenantId, (int) $notice['identity_id'], (int) $notice['id'], 'notice.attachment_removed', sprintf(
            'Image retirée de l’avis %s : %s.',
            (string) ($notice['ref'] ?? ''),
            mb_strtolower(C::label(self::KINDS, (string) $att['kind']))
        ), ['ref' => $notice['ref'] ?? '', 'attachment_id' => $attachmentId, 'kind' => $att['kind']], $access);

        return ['ok' => true];
    }

    /**
     * @param array<string, mixed> $notice
     * @return array{ok: true}|array{ok: false, error: string}
     */
    public function makeMain(int $tenantId, array $notice, int $attachmentId, SseWantedAccess $access): array
    {
        $att = $this->repo()->find($tenantId, $attachmentId);
        if (!$access->canManage() || $access->tenantId() !== $tenantId || $att === null || (int) $att['notice_id'] !== (int) $notice['id']
            || !$this->identityVisible($tenantId, (int) $notice['identity_id'])['allowed']) {
            return ['ok' => false, 'error' => 'Action non autorisée.'];
        }
        $this->repo()->setMain($tenantId, $attachmentId);

        return ['ok' => true];
    }

    /**
     * Pièce et fichier à servir à ce lecteur (null : introuvable, autre communauté ou identité de membre non autorisée).
     *
     * @return array{attachment: array<string, mixed>, file: string, mime: string, confidential: bool, reason: string, owner: ?int}|null
     */
    public function resolve(int $tenantId, int $attachmentId, bool $thumb): ?array
    {
        $att = $this->repo()->find($tenantId, $attachmentId);
        if ($att === null) {
            return null;
        }
        $visible = $this->identityVisible($tenantId, (int) $att['identity_id']);
        if (!$visible['allowed']) {
            $this->gate()->recordDenied($tenantId, $this->gate()->viewerId(), 'wanted_attachment', $attachmentId, $visible['owner']);

            return null;
        }
        $path = $thumb && $att['thumb_path'] !== null ? $att['thumb_path'] : $att['path'];
        $abs = SseImageStorage::absolute($path, null, $this->privateDir);
        $mime = $abs !== null ? SseImageStorage::verifiedMime($abs) : null;
        if ($abs === null || $mime === null) {
            return null;
        }

        return ['attachment' => $att, 'file' => $abs, 'mime' => $mime, 'confidential' => $visible['confidential'], 'reason' => $visible['reason'], 'owner' => $visible['owner']];
    }

    /**
     * Supprime les fichiers de toutes les pièces d'un avis (avant la suppression d'un avis ou pour un nettoyage).
     */
    public function purgeFiles(int $tenantId, int $noticeId): int
    {
        $n = 0;
        foreach ($this->repo()->forNotice($tenantId, $noticeId) as $att) {
            foreach ([$att['path'], $att['thumb_path']] as $p) {
                $abs = $p !== null ? SseImageStorage::absolute((string) $p, null, $this->privateDir) : null;
                if ($abs !== null && @unlink($abs)) {
                    $n++;
                }
            }
        }

        return $n;
    }

    /** @return array<string, mixed>|null */
    private function noticeAnyTenant(int $noticeId): ?array
    {
        $row = \App\Core\Database::getInstance()->fetchOne('SELECT tenant_id FROM sse_wanted_notices WHERE id = :id', ['id' => $noticeId]);

        return $row === null ? null : (new SseWantedNoticeRepository())->find((int) $row['tenant_id'], $noticeId);
    }

    /** @param array<string, mixed> $details */
    private function trace(int $tenantId, int $identityId, int $noticeId, string $type, string $summary, array $details, SseWantedAccess $access): void
    {
        try {
            ($this->events ??= new SseIdentityEventRepository())->add($tenantId, $identityId, $type, $summary, $details, $access->userId(), $access->actorLabel(), 'base', 'web', $noticeId);
        } catch (\Throwable $e) {
            error_log('Avis de recherche : chronologie indisponible : ' . $e->getMessage());
        }
    }
}
