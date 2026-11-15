<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;
use App\Repositories\SseFileRecordRepository;
use App\Repositories\SseFileRepository;
use App\Support\SseFacePortrait;
use App\Support\StoredPhotoCompressor;
use App\Support\TerrainUploadedImage;

/**
 * Images des fiches SEEK II : le cliché du visage pris au terminal SEEK II, rangé à la photothèque SSE (sse_file_photos,
 * source « seek », source_ref = n° REC de la fiche) et relié à l'identité de la fiche.
 *
 * Réception (POST /api/sse/persons/REC-…/photos, même envoi que la photo d'une fiche personne) :
 *  - vrai type lu dans le contenu (PNG, JPEG ou WebP), jamais l'extension ni le type annoncé ;
 *  - taille bornée (TerrainUploadedImage::MAX_FILE_BYTES), image ramenée à 1920 px, recadrée en portrait si le jeu l'a
 *    demandé, métadonnées retirées ;
 *  - rangement HORS du dossier public : storage/private/sse-seek/<communauté>/<aléa>.<ext>, servi seulement par la route
 *    protégée du portail SSE (GET /atak/sse/fichiers/photos/{id}/image) ;
 *  - idempotent : le même cliché renvoyé pour la même fiche (même empreinte) n'est rangé qu'une fois.
 *
 * L'ordre d'arrivée n'a pas d'importance : la photo peut précéder la fiche (file hors ligne du jeu). Le lien à
 * l'identité se fait à la réception de la fiche (attachForRec) et, sinon, à la lecture (la fiche SEEK II qui porte le
 * même REC).
 */
final class SseSeekPhotoService
{
    public const SOURCE = 'seek';

    /** Dossier de rangement, relatif à la racine du dépôt. */
    public const PRIVATE_DIR = 'storage/private/sse-seek';

    /** Préfixe des chemins gardés dans sse_file_photos.path pour une image rangée hors du dossier public. */
    public const PATH_PREFIX = 'sse-seek/';

    private const REC_PATTERN = '/^[A-Z0-9_.:\-]{3,40}$/';

    public function __construct(
        private ?SseFileRecordRepository $records = null,
        private ?SseFileRepository $files = null,
        private ?string $baseDir = null,
    ) {
        $this->baseDir ??= base_path(self::PRIVATE_DIR);
    }

    private function records(): SseFileRecordRepository
    {
        return $this->records ??= new SseFileRecordRepository();
    }

    private function files(): SseFileRepository
    {
        return $this->files ??= new SseFileRepository();
    }

    private function db(): Database
    {
        return $this->records()->db();
    }

    /** Une référence de fiche SEEK II (REC-…) et non un identifiant numérique de fiche personne. */
    public static function isRecReference(string $ref): bool
    {
        $ref = strtoupper(trim($ref));

        return $ref !== '' && !ctype_digit($ref) && preg_match(self::REC_PATTERN, $ref) === 1;
    }

    public static function normalizeRec(string $ref): string
    {
        return strtoupper(trim($ref));
    }

    /** Le chemin gardé en base désigne-t-il une image rangée hors du dossier public ? */
    public static function isPrivatePath(string $path): bool
    {
        return preg_match('#^' . preg_quote(self::PATH_PREFIX, '#') . '\d{1,10}/[a-f0-9]{16}\.(jpg|png|webp)$#', $path) === 1;
    }

    /**
     * Adresse d'une photo de la photothèque : la route protégée pour une image rangée à l'abri, le dossier public pour
     * les photos plus anciennes (envois de membres, ATAK).
     *
     * @param array<string, mixed> $photo ligne hydratée de sse_file_photos
     */
    public static function photoUrl(array $photo): ?string
    {
        $path = (string) ($photo['path'] ?? '');
        if ($path === '') {
            return null;
        }
        if (self::isPrivatePath($path)) {
            return function_exists('url') ? url('atak/sse/fichiers/photos/' . (int) ($photo['id'] ?? 0) . '/image') : null;
        }

        return function_exists('user_media_public_url') ? user_media_public_url($path) : null;
    }

    /**
     * Chemin disque d'une image rangée à l'abri (null si le chemin est étranger au dossier, absent ou d'une autre communauté).
     *
     * @param array<string, mixed> $photo ligne hydratée de sse_file_photos
     */
    public function absolutePath(array $photo, int $tenantId): ?string
    {
        $path = (string) ($photo['path'] ?? '');
        if (!self::isPrivatePath($path) || explode('/', $path)[1] !== (string) $tenantId) {
            return null;
        }
        $root = realpath((string) $this->baseDir);
        $abs = realpath(rtrim((string) $this->baseDir, '/\\') . DIRECTORY_SEPARATOR . substr($path, strlen(self::PATH_PREFIX)));
        if ($root === false || $abs === false || !is_file($abs) || !str_starts_with($abs, $root . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $abs;
    }

    /**
     * Range le cliché reçu sur la fiche SEEK II.
     *
     * @param array{name?: string, tmp_name?: string, size?: int, error?: int} $file envoi multipart
     * @param array{angle?: string, author?: string, grid?: string, caption?: string, user_id?: ?int} $meta
     * @return array{id: int, rec: string, identity_id: ?int, duplicate: bool}|array{error: string, status: int}
     */
    public function store(int $tenantId, string $recRef, array $file, array $meta = []): array
    {
        $rec = self::normalizeRec($recRef);
        if ($tenantId < 1 || !self::isRecReference($rec)) {
            return ['error' => 'invalid_rec', 'status' => 422];
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || $tmp === '' || !is_file($tmp)) {
            return ['error' => 'upload_failed', 'status' => 400];
        }
        if ($size > TerrainUploadedImage::MAX_FILE_BYTES || (int) @filesize($tmp) > TerrainUploadedImage::MAX_FILE_BYTES) {
            return ['error' => 'file_too_large', 'status' => 400];
        }
        // Le vrai type se lit dans les premiers octets : jamais l'extension ni le type annoncé par l'envoi.
        if (TerrainUploadedImage::detectExtension($tmp, '') === null) {
            return ['error' => 'invalid_image', 'status' => 400];
        }

        $hash = (string) @sha1_file($tmp);
        $existing = $this->db()->fetchOne(
            'SELECT id, identity_id FROM sse_file_photos WHERE tenant_id = :t AND source = :s AND source_ref = :r AND sha1 = :h LIMIT 1',
            ['t' => $tenantId, 's' => self::SOURCE, 'r' => $rec, 'h' => $hash]
        );
        if ($existing !== null && $hash !== '') {
            return ['id' => (int) $existing['id'], 'rec' => $rec, 'identity_id' => $existing['identity_id'] !== null ? (int) $existing['identity_id'] : null, 'duplicate' => true];
        }

        $angle = SseFacePortrait::parseAngle((string) ($meta['angle'] ?? 'face'));
        // Capture brute Arma : allégée (1920 px, JPEG) sur place, puis recadrée en portrait 3:4 si le jeu l'a demandé.
        StoredPhotoCompressor::compressUploadToJpeg($tmp);
        $work = $tmp;
        $cropped = null;
        if ($angle['portrait']) {
            $cropped = $tmp . '.portrait';
            $work = SseFacePortrait::writePortrait($tmp, $cropped) ? $cropped : $tmp;
        }
        $bytes = (string) @file_get_contents($work);
        if ($cropped !== null) {
            @unlink($cropped);
        }
        $mime = SseIdImageService::sniff($bytes);
        $clean = $mime !== null ? SseIdImageService::stripMetadata($bytes, $mime) : null;
        if ($mime === null || $clean === null || $clean === '') {
            return ['error' => 'invalid_image', 'status' => 400];
        }
        $ext = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'][$mime];

        $dir = rtrim((string) $this->baseDir, '/\\') . DIRECTORY_SEPARATOR . $tenantId;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['error' => 'storage', 'status' => 503];
        }
        $name = bin2hex(random_bytes(8)) . '.' . $ext;
        if (@file_put_contents($dir . DIRECTORY_SEPARATOR . $name, $clean) === false) {
            return ['error' => 'storage', 'status' => 503];
        }

        $seek = $this->safeSeek($tenantId, $rec);
        $identityId = $seek !== null && ($seek['identity_id'] ?? null) !== null ? (int) $seek['identity_id'] : null;
        $kind = match ($angle['angle']) {
            'profil', 'profile' => 'profile',
            default => 'front',
        };
        $author = trim((string) ($meta['author'] ?? ''));
        $grid = trim((string) ($meta['grid'] ?? ''));
        $caption = trim((string) ($meta['caption'] ?? ''));
        if ($caption === '') {
            $caption = 'Photo du visage · fiche SEEK II ' . $rec . ($grid !== '' ? ' · carroyage ' . $grid : '') . ($author !== '' ? ' · ' . $author : '');
        }
        $size = @getimagesizefromstring($clean);
        $id = $this->records()->addPhoto($tenantId, [
            'identity_id' => $identityId,
            'path' => self::PATH_PREFIX . $tenantId . '/' . $name,
            'mime' => $mime,
            'width' => is_array($size) ? (int) $size[0] : null,
            'height' => is_array($size) ? (int) $size[1] : null,
            'bytes' => strlen($clean),
            'sha1' => $hash !== '' ? $hash : sha1($clean),
            'kind' => $kind,
            'source' => self::SOURCE,
            'source_ref' => $rec,
            'caption' => $caption,
        ], isset($meta['user_id']) && (int) $meta['user_id'] > 0 ? (int) $meta['user_id'] : null);

        return ['id' => $id, 'rec' => $rec, 'identity_id' => $identityId, 'duplicate' => false];
    }

    /**
     * À la réception (ou à la mise à jour) d'une fiche SEEK II rattachée à une identité : les clichés déjà reçus pour
     * ce REC et restés sans fiche rejoignent l'identité.
     */
    public function attachForRec(int $tenantId, string $rec, ?int $identityId): int
    {
        if ($identityId === null || $identityId < 1 || !self::isRecReference($rec)) {
            return 0;
        }

        return $this->db()->execute(
            'UPDATE sse_file_photos SET identity_id = :i WHERE tenant_id = :t AND source = :s AND source_ref = :r AND identity_id IS NULL',
            ['i' => $identityId, 't' => $tenantId, 's' => self::SOURCE, 'r' => self::normalizeRec($rec)]
        );
    }

    /**
     * Clichés SEEK II d'une identité : ceux rangés sur sa fiche, plus ceux dont la fiche SEEK II porte son identité ou sa
     * clé biométrique (photo arrivée avant la fiche). Chaque cliché porte les infos de sa fiche (REC, verdict, lieu,
     * date, contrôleur).
     *
     * @return list<array<string, mixed>>
     */
    public function forIdentity(int $tenantId, int $identityId, ?string $bioKey = null): array
    {
        if ($tenantId < 1 || $identityId < 1) {
            return [];
        }
        try {
            $recs = [];
            foreach ($this->files()->seekFor($tenantId, ['identity_ids' => [$identityId], 'bio_key' => trim((string) $bioKey), 'limit' => 100]) as $s) {
                $recs[(string) $s['rec']] = $s;
            }
            $params = ['t' => $tenantId, 's' => self::SOURCE, 'i' => $identityId];
            $or = 'identity_id = :i';
            foreach (array_keys($recs) as $n => $rec) {
                $params['r' . $n] = $rec;
                $or .= ' OR source_ref = :r' . $n;
            }
            $rows = $this->db()->fetchAll(
                'SELECT * FROM sse_file_photos WHERE tenant_id = :t AND source = :s AND (' . $or . ') ORDER BY id DESC LIMIT 60',
                $params
            );
            $out = [];
            foreach ($rows as $row) {
                $photo = SseFileRecordRepository::hydratePhoto($row);
                $seek = $recs[(string) ($photo['source_ref'] ?? '')] ?? $this->safeSeek($tenantId, (string) ($photo['source_ref'] ?? ''));
                $out[] = $photo + ['seek' => $seek, 'url' => self::photoUrl($photo)];
            }

            return $out;
        } catch (\Throwable $e) {
            // Table absente (ancienne communauté) ou base indisponible : l'écran dit « aucune image », il ne casse pas.
            error_log('Images SEEK II illisibles : ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Clichés par REC (liste des fiches SEEK II).
     *
     * @param list<string> $recs
     * @return array<string, list<array<string, mixed>>>
     */
    public function forRecs(int $tenantId, array $recs): array
    {
        $recs = array_slice(array_values(array_unique(array_filter(array_map('strval', $recs), static fn (string $r): bool => $r !== ''))), 0, 200);
        if ($tenantId < 1 || $recs === []) {
            return [];
        }
        try {
            $params = ['t' => $tenantId, 's' => self::SOURCE];
            $marks = [];
            foreach ($recs as $n => $rec) {
                $marks[] = ':r' . $n;
                $params['r' . $n] = $rec;
            }
            $out = [];
            foreach ($this->db()->fetchAll('SELECT * FROM sse_file_photos WHERE tenant_id = :t AND source = :s AND source_ref IN (' . implode(', ', $marks) . ') ORDER BY id DESC', $params) as $row) {
                $photo = SseFileRecordRepository::hydratePhoto($row);
                $out[(string) $photo['source_ref']][] = $photo + ['url' => self::photoUrl($photo)];
            }

            return $out;
        } catch (\Throwable $e) {
            error_log('Images SEEK II illisibles : ' . $e->getMessage());

            return [];
        }
    }

    /** @return array<string, mixed>|null */
    private function safeSeek(int $tenantId, string $rec): ?array
    {
        if ($rec === '') {
            return null;
        }
        try {
            return $this->files()->findSeekByRec($tenantId, $rec);
        } catch (\Throwable) {
            return null;
        }
    }
}
