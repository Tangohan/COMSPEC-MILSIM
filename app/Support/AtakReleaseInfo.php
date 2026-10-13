<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Dernières versions publiées de l'ATAK natif et de la DLL COMSPEC Link.
 *
 * Source unique : les fichiers que chaque montée de version modifie déjà (le VPS déploie le dépôt entier).
 *  - ATAK natif : VERSION_STR de mod/COMSPEC_ATAK_Native/Sources/addons/main/script_component.hpp
 *  - COMSPEC Link : InformationalVersion de mod/UptoDate/COMSPECExtension/COMSPECExtension.csproj
 *    (c'est ce que la DLL renvoie à GetExtensionVersion)
 * Le mod compare sa version à celles-ci (GET /api/atak/version, fn_integrityCheck) et prévient le joueur.
 */
final class AtakReleaseInfo
{
    public const SCRIPT_COMPONENT = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/script_component.hpp';
    public const LINK_CSPROJ = 'mod/UptoDate/COMSPECExtension/COMSPECExtension.csproj';

    /** @var array{atak_native: ?string, link: ?string, link_expected: ?string}|null */
    private static ?array $cache = null;

    /**
     * @return array{atak_native: ?string, link: ?string, link_expected: ?string}
     *   link_expected : DLL attendue par le main.pbo (LINK_VERSION_STR), alignée sur link par un test.
     */
    public static function latest(?string $root = null): array
    {
        if ($root === null && self::$cache !== null) {
            return self::$cache;
        }
        $base = rtrim($root ?? base_path(), '/');
        $hpp = self::read($base . '/' . self::SCRIPT_COMPONENT);
        $csproj = self::read($base . '/' . self::LINK_CSPROJ);
        $info = [
            'atak_native' => self::match('/^#define\s+VERSION_STR\s+"([0-9]+(?:\.[0-9]+){1,3})"/m', $hpp),
            'link' => self::match('#<InformationalVersion>\s*([0-9]+(?:\.[0-9]+){1,3})\s*</InformationalVersion>#', $csproj)
                ?? self::match('#<Version>\s*([0-9]+(?:\.[0-9]+){1,3})\s*</Version>#', $csproj),
            'link_expected' => self::match('/^#define\s+LINK_VERSION_STR\s+"([0-9]+(?:\.[0-9]+){1,3})"/m', $hpp),
        ];
        if ($root === null) {
            self::$cache = $info;
        }

        return $info;
    }

    private static function read(string $path): string
    {
        if (!is_file($path) || !is_readable($path)) {
            return '';
        }
        $raw = @file_get_contents($path, false, null, 0, 65536);

        return is_string($raw) ? $raw : '';
    }

    private static function match(string $pattern, string $text): ?string
    {
        return $text !== '' && preg_match($pattern, $text, $m) === 1 ? $m[1] : null;
    }
}
