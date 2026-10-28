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
    public const CHANGELOG = 'CHANGELOG-ATAK.md';
    private const SUMMARY_MAX = 300;

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

    /**
     * « Résumé joueur » de l'entrée du changelog dont le titre porte « ATAK natif <version> » : titre puis puces,
     * en une ligne par langue (notification « mise à jour disponible » du téléphone). Vide si l'entrée n'en a pas.
     *
     * @return array{fr: string, en: string}
     */
    public static function playerSummary(string $version, ?string $root = null): array
    {
        $out = ['fr' => '', 'en' => ''];
        foreach (self::playerNotes($version, $root) as $k => $notes) {
            $lines = $notes['title'] === '' ? [] : [rtrim($notes['title'], ' .') . '.'];
            $text = trim(str_replace(['|', '`', '**'], ['', '', ''], implode(' ', [...$lines, ...$notes['bullets']])));
            if (mb_strlen($text) > self::SUMMARY_MAX) {
                $text = rtrim(mb_substr($text, 0, self::SUMMARY_MAX - 1)) . '…';
            }
            $out[$k] = $text;
        }

        return $out;
    }

    /**
     * Même « Résumé joueur », gardé en titre + puces (page publique /atak-natif, bloc « Nouveautés »).
     *
     * @return array{fr: array{title: string, bullets: list<string>}, en: array{title: string, bullets: list<string>}}
     */
    public static function playerNotes(string $version, ?string $root = null): array
    {
        $out = ['fr' => ['title' => '', 'bullets' => []], 'en' => ['title' => '', 'bullets' => []]];
        if (preg_match('/^[0-9]+(?:\.[0-9]+){1,3}$/', $version) !== 1) {
            return $out;
        }
        $md = str_replace("\r\n", "\n", self::read(rtrim($root ?? base_path(), '/') . '/' . self::CHANGELOG, 1048576));
        if ($md === '') {
            return $out;
        }
        $heading = '/^## [^\n]*ATAK natif ' . preg_quote($version, '/') . '(?![0-9]|\.[0-9])[^\n]*$/m';
        if (preg_match($heading, $md, $m, PREG_OFFSET_CAPTURE) !== 1) {
            return $out;
        }
        $start = $m[0][1] + strlen($m[0][0]);
        $next = strpos($md, "\n## ", $start);
        $entry = substr($md, $start, $next === false ? null : $next - $start);
        if (preg_match('/^### Résumé joueur[ \t]*\n(.*?)(?=^### |\z)/ms', $entry, $b) !== 1) {
            return $out;
        }
        $lang = '';
        foreach (explode("\n", $b[1]) as $line) {
            $line = trim($line);
            if (preg_match('/^(FR|EN)\s*:\s*(.+)$/u', $line, $l) === 1) {
                $lang = $l[1] === 'FR' ? 'fr' : 'en';
                $out[$lang]['title'] = $l[2];
            } elseif ($lang !== '' && str_starts_with($line, '- ')) {
                $out[$lang]['bullets'][] = trim(substr($line, 2));
            }
        }

        return $out;
    }

    /** Nom affiché de la version publiée (VERSION_NAME, ex. « 2.1.0.AB - Denver »), null s'il manque. */
    public static function versionName(?string $root = null): ?string
    {
        $hpp = self::read(rtrim($root ?? base_path(), '/') . '/' . self::SCRIPT_COMPONENT);

        return self::match('/^#define\s+VERSION_NAME\s+"([^"\n]{1,60})"/m', $hpp);
    }

    private static function read(string $path, int $max = 65536): string
    {
        if (!is_file($path) || !is_readable($path)) {
            return '';
        }
        $raw = @file_get_contents($path, false, null, 0, $max);

        return is_string($raw) ? $raw : '';
    }

    private static function match(string $pattern, string $text): ?string
    {
        return $text !== '' && preg_match($pattern, $text, $m) === 1 ? $m[1] : null;
    }
}
