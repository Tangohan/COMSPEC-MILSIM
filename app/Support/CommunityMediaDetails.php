<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Libellés métier et helpers d’affichage pour les médias communauté.
 */
final class CommunityMediaDetails
{
    public const KIND_IMAGE = 'image';
    public const KIND_SHORT_VIDEO = 'short_video';
    public const KIND_LONG_VIDEO = 'long_video';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    public const BLUR_NONE = 'none';
    public const BLUR_MANUAL = 'manual';
    public const BLUR_AUTO_FACE = 'auto_face';

    /** @return array<string, string> */
    public static function kindLabels(): array
    {
        return [
            self::KIND_IMAGE => 'Image',
            self::KIND_SHORT_VIDEO => 'Vidéo courte',
            self::KIND_LONG_VIDEO => 'Vidéo longue',
        ];
    }

    public static function kindLabel(string $kind): string
    {
        return self::kindLabels()[$kind] ?? 'Média';
    }

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_DRAFT => 'Brouillon',
            self::STATUS_PUBLISHED => 'Publié',
            self::STATUS_ARCHIVED => 'Archivé',
        ];
    }

    public static function statusLabel(string $status): string
    {
        return self::statusLabels()[$status] ?? 'Brouillon';
    }

    /** @return array<string, string> */
    public static function blurModeLabels(): array
    {
        return [
            self::BLUR_NONE => 'Sans floutage',
            self::BLUR_MANUAL => 'Floutage manuel',
            self::BLUR_AUTO_FACE => 'Floutage de visage (détection)',
        ];
    }

    public static function blurModeLabel(string $mode): string
    {
        return self::blurModeLabels()[$mode] ?? 'Sans floutage';
    }

    public static function publicUrl(?string $storagePath): ?string
    {
        $storagePath = trim((string) $storagePath);
        if ($storagePath === '' || str_contains($storagePath, '..')) {
            return null;
        }
        $norm = ltrim(str_replace('\\', '/', $storagePath), '/');
        if (!str_starts_with($norm, 'uploads/community-media/')) {
            return null;
        }

        return asset_url($norm);
    }

    /**
     * Convertit une URL vidéo longue (YouTube / Vimeo / lien direct) en URL d’intégration si possible.
     */
    public static function embedUrl(?string $externalUrl): ?string
    {
        $externalUrl = trim((string) $externalUrl);
        if ($externalUrl === '') {
            return null;
        }
        if (!preg_match('#^https?://#i', $externalUrl)) {
            return null;
        }

        if (preg_match('~(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/)([A-Za-z0-9_-]{6,})~', $externalUrl, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }
        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $externalUrl, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }

        return $externalUrl;
    }

    /**
     * @return list<array{x:float,y:float,w:float,h:float}>
     */
    public static function parseBlurRegions(mixed $raw): array
    {
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            $raw = $decoded;
        }
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $region) {
            if (!is_array($region)) {
                continue;
            }
            $x = (float) ($region['x'] ?? 0);
            $y = (float) ($region['y'] ?? 0);
            $w = (float) ($region['w'] ?? 0);
            $h = (float) ($region['h'] ?? 0);
            if ($w <= 0 || $h <= 0) {
                continue;
            }
            $out[] = [
                'x' => max(0.0, min(100.0, $x)),
                'y' => max(0.0, min(100.0, $y)),
                'w' => max(0.0, min(100.0, $w)),
                'h' => max(0.0, min(100.0, $h)),
            ];
        }

        return $out;
    }
}
