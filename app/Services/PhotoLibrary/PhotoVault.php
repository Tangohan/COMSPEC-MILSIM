<?php

declare(strict_types=1);

namespace App\Services\PhotoLibrary;

use App\Support\ReconImageStorage;

/**
 * Fichiers de la photothèque : le fichier servi d'une photo (recon_images.image_path) devient, dès qu'une zone est
 * masquée, la version masquée ; l'original passe dans storage/private/photo-originals (hors racine web, sous un nom
 * aléatoire) et n'est lu que par PhotoVault::readOriginal, pour ceux qui ont le droit de voir sous les masques. Ainsi
 * aucune adresse ancienne (/uploads/recon/…, route du poste ATAK) ne donne l'original à un lecteur sans droit.
 * Quand le dernier masque est retiré, l'original reprend sa place.
 *
 * Dérivées (miniatures, grandes vues) : storage/cache/photo-library/<communauté>/<photo>/<empreinte>-<variante>.<ext>,
 * la clé contient l'empreinte des zones et la date du fichier source ; toute modification des zones vide le dossier.
 */
final class PhotoVault
{
    public const THUMB_SIDE = 480;
    public const FULL_SIDE = 2000;

    /** @param string|null $reconDir tests : dossier des photos terrain à la place de public/uploads/recon et storage/private/recon */
    public function __construct(private ?string $originalsRoot = null, private ?string $cacheRoot = null, private ?string $reconDir = null)
    {
        $this->originalsRoot ??= base_path('storage/private/photo-originals');
        $this->cacheRoot ??= base_path('storage/cache/photo-library');
    }

    /** Fichier actuellement servi pour cette ligne recon_images (chemin absolu), ou null. */
    public function currentFile(array $row): ?string
    {
        $path = trim((string) ($row['image_path'] ?? ''));
        if ($path === '') {
            return null;
        }
        if ($this->reconDir !== null) {
            $base = basename(str_replace('\\', '/', $path));
            $abs = rtrim($this->reconDir, '/\\') . '/' . $base;

            return ReconImageStorage::isSafeFileName($base) && is_file($abs) ? $abs : null;
        }

        return ReconImageStorage::absoluteReadable($path);
    }

    /** Chemin absolu d'un original rangé (chemin relatif de photo_library_state.original_path), ou null s'il manque. */
    public function originalFile(?string $relative): ?string
    {
        $relative = trim((string) $relative);
        if ($relative === '' || preg_match('#^[0-9]{1,10}/[0-9]{1,10}-[a-f0-9]{32}\.(?:jpg|jpeg|png|webp|gif)$#', $relative) !== 1) {
            return null;
        }
        $abs = rtrim((string) $this->originalsRoot, '/\\') . '/' . $relative;

        return is_file($abs) && is_readable($abs) ? $abs : null;
    }

    /**
     * Pose (ou retire, si $zones est vide) les masques d'une photo : l'original est rangé la première fois, le fichier
     * servi est réécrit masqué, les dérivées sont jetées.
     *
     * @param array<string, mixed> $row ligne recon_images
     * @param list<array{x: float|string, y: float|string, w: float|string, h: float|string, mode: string}> $zones
     * @return array{ok: bool, error?: string, original_path?: ?string, zones_hash?: ?string}
     */
    public function applyZones(array $row, ?string $originalRelative, array $zones): array
    {
        $current = $this->currentFile($row);
        if ($current === null) {
            return ['ok' => false, 'error' => 'Fichier de la photo introuvable.'];
        }
        $tenantId = (int) ($row['tenant_id'] ?? 0);
        $photoId = (int) ($row['id'] ?? 0);
        if ($tenantId < 1 || $photoId < 1) {
            return ['ok' => false, 'error' => 'Photo introuvable.'];
        }
        $storedOriginal = $this->originalFile($originalRelative);

        if ($zones === []) {
            if ($storedOriginal !== null) {
                if (!$this->replaceFile($current, (string) file_get_contents($storedOriginal))) {
                    return ['ok' => false, 'error' => 'Impossible de rendre l’original.'];
                }
                @unlink($storedOriginal);
            }
            $this->purgeDerived($tenantId, $photoId);

            return ['ok' => true, 'original_path' => null, 'zones_hash' => null];
        }
        if (!PhotoMaskRenderer::available()) {
            return ['ok' => false, 'error' => 'Le masquage demande l’extension GD du serveur, absente : aucun masque n’est posé.'];
        }
        $justStored = false;
        if ($storedOriginal === null) {
            $relative = $this->keepOriginal($tenantId, $photoId, $current);
            if ($relative === null) {
                return ['ok' => false, 'error' => 'Impossible de ranger l’original à l’abri.'];
            }
            $originalRelative = $relative;
            $storedOriginal = $this->originalFile($relative);
            $justStored = true;
        }
        $bytes = $storedOriginal !== null ? (string) file_get_contents($storedOriginal) : '';
        $masked = PhotoMaskRenderer::apply($bytes, $zones, PhotoMaskRenderer::formatFor($current), 92);
        if ($masked === null || !$this->replaceFile($current, $masked)) {
            if ($justStored && $storedOriginal !== null) {
                @unlink($storedOriginal);
            }

            return ['ok' => false, 'error' => 'Le masque n’a pas pu être appliqué à l’image.'];
        }
        $this->purgeDerived($tenantId, $photoId);

        return ['ok' => true, 'original_path' => $originalRelative, 'zones_hash' => PhotoGeometry::hash($zones)];
    }

    /** Octets de l'original rangé (pour qui a le droit de voir sous les masques), ou du fichier servi s'il n'y a pas de masque. */
    public function readOriginal(array $row, ?string $originalRelative): ?string
    {
        $abs = $this->originalFile($originalRelative) ?? $this->currentFile($row);
        if ($abs === null) {
            return null;
        }
        $bytes = @file_get_contents($abs);

        return is_string($bytes) && $bytes !== '' ? $bytes : null;
    }

    /**
     * Dérivée en cache (miniature ou grande vue), recalculée si absente. $underMask : vue sans masque (original rangé).
     *
     * @return array{bytes: string, mime: string}|null
     */
    public function derived(array $row, ?string $originalRelative, ?string $zonesHash, string $variant, bool $underMask): ?array
    {
        $side = $variant === 'thumb' ? self::THUMB_SIDE : self::FULL_SIDE;
        $source = $underMask ? ($this->originalFile($originalRelative) ?? $this->currentFile($row)) : $this->currentFile($row);
        if ($source === null) {
            return null;
        }
        $mtime = (int) @filemtime($source);
        $key = substr(sha1(($zonesHash ?? '-') . '|' . $mtime . '|' . ($underMask ? 'o' : 'm') . '|' . $side), 0, 20);
        $dir = $this->derivedDir((int) ($row['tenant_id'] ?? 0), (int) ($row['id'] ?? 0));
        $cached = $dir . '/' . $key . '-' . ($underMask ? 'o' : 'm') . '-' . $variant . '.jpg';
        if (is_file($cached)) {
            $bytes = @file_get_contents($cached);
            if (is_string($bytes) && $bytes !== '') {
                return ['bytes' => $bytes, 'mime' => 'image/jpeg'];
            }
        }
        if (!PhotoMaskRenderer::available()) {
            return null;
        }
        $raw = @file_get_contents($source);
        $out = is_string($raw) ? PhotoMaskRenderer::apply($raw, [], 'jpeg', $variant === 'thumb' ? 78 : 88, $side) : null;
        if ($out === null) {
            return null;
        }
        if (is_dir($dir) || @mkdir($dir, 0775, true) || is_dir($dir)) {
            @file_put_contents($cached, $out);
        }

        return ['bytes' => $out, 'mime' => 'image/jpeg'];
    }

    public function purgeDerived(int $tenantId, int $photoId): void
    {
        $dir = $this->derivedDir($tenantId, $photoId);
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*') ?: [] as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
        @rmdir($dir);
    }

    private function derivedDir(int $tenantId, int $photoId): string
    {
        return rtrim((string) $this->cacheRoot, '/\\') . '/' . max(0, $tenantId) . '/' . max(0, $photoId);
    }

    /** Copie le fichier servi dans le dossier des originaux ; retourne son chemin relatif. */
    private function keepOriginal(int $tenantId, int $photoId, string $current): ?string
    {
        $ext = strtolower((string) preg_replace('/[^a-z0-9]/i', '', pathinfo($current, PATHINFO_EXTENSION)));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $ext = 'jpg';
        }
        $dir = rtrim((string) $this->originalsRoot, '/\\') . '/' . $tenantId;
        if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
            return null;
        }
        foreach ([rtrim((string) $this->originalsRoot, '/\\'), $dir] as $d) {
            $ht = $d . '/.htaccess';
            if (!is_file($ht)) {
                @file_put_contents($ht, "Require all denied\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n");
            }
        }
        $relative = $tenantId . '/' . $photoId . '-' . bin2hex(random_bytes(16)) . '.' . $ext;
        if (!@copy($current, rtrim((string) $this->originalsRoot, '/\\') . '/' . $relative)) {
            return null;
        }

        return $relative;
    }

    /** Remplace le fichier servi d'un bloc (écriture à côté puis renommage). */
    private function replaceFile(string $target, string $bytes): bool
    {
        if ($bytes === '') {
            return false;
        }
        $tmp = $target . '.tmp' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, $bytes) === false) {
            @unlink($tmp);

            return false;
        }
        if (!@rename($tmp, $target)) {
            @unlink($tmp);

            return false;
        }

        return true;
    }
}
