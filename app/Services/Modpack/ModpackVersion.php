<?php

declare(strict_types=1);

namespace App\Services\Modpack;

final class ModpackVersion
{
    public const MAX_LENGTH = 48;
    public const PATTERN = '[0-9][a-zA-Z0-9._+\x2D]*(?: [\p{L}\p{M}0-9._+\x2D]+)*';
    public const EXAMPLE = '4.31.0 Washington';

    public static function isValid(string $version): bool
    {
        return mb_strlen($version, 'UTF-8') <= self::MAX_LENGTH
            && preg_match('/\A(?:' . self::PATTERN . ')\z/u', $version) === 1;
    }

    public static function isOlder(string $version, string $reference): bool
    {
        return version_compare(self::identifier($version), self::identifier($reference), '<');
    }

    public static function downloadDisposition(string $version): string
    {
        $fallback = 'COMSPEC-Modpack-' . self::identifier($version) . '.rar';
        $filename = 'COMSPEC-Modpack-' . $version . '.rar';
        return "attachment; filename=\"$fallback\"; filename*=UTF-8''" . rawurlencode($filename);
    }

    private static function identifier(string $version): string
    {
        return explode(' ', $version, 2)[0];
    }
}
