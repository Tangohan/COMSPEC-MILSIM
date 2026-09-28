<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Instantané production (portail + pack jeu) pour bannières back-office.
 * Source : storage/app_version.json (+ date Git si le dépôt est disponible).
 */
final class PlatformProductionStatus
{
    /**
     * @return array{
     *   platform_version: string,
     *   deployed_at: ?string,
     *   deployed_at_label: string,
     *   deployed_source: string,
     *   overwatch_version: string,
     *   athena_version: string,
     *   extension_version: string,
     *   pack_label: string,
     *   git_sha: string,
     *   bulletin_title: string,
     *   bulletin_url: string,
     *   codename: string,
     *   codename_label: string,
     *   major: int
     * }
     */
    public static function snapshot(): array
    {
        $file = self::readVersionFile();
        $platform = trim((string) ($file['version'] ?? ''));
        if ($platform === '' || !preg_match('/^\d+\.\d+\.\d+/', $platform)) {
            $platform = function_exists('platform_app_version') ? platform_app_version() : '1.0.0';
        }

        $pack = is_array($file['pack'] ?? null) ? $file['pack'] : [];
        $overwatch = self::cleanVersion($pack['overwatch'] ?? $file['overwatch_version'] ?? '');
        $athena = self::cleanVersion($pack['athena'] ?? $file['athena_version'] ?? '');
        $extension = self::cleanVersion($pack['extension'] ?? $file['extension_version'] ?? '');

        $fileAt = self::parseDate($file['updated_at'] ?? null);
        $git = self::gitHeadMeta();
        $deployedAt = $fileAt;
        $source = 'fichier';
        if ($git['at'] instanceof \DateTimeImmutable) {
            if ($deployedAt === null || $git['at'] >= $deployedAt) {
                $deployedAt = $git['at'];
                $source = 'dépôt';
            }
        }

        $sha = trim((string) ($file['git_sha'] ?? ''));
        if ($sha === '' && $git['sha'] !== '') {
            $sha = $git['sha'];
        }

        $bulletin = DevDispatchCatalog::forProductionBanner();
        $bulletinTitle = is_array($bulletin) ? trim((string) ($bulletin['title'] ?? '')) : '';
        $bulletinUrl = '';
        if (is_array($bulletin) && ($bulletin['kind'] ?? '') !== '' && ($bulletin['number_pad'] ?? '') !== '') {
            $bulletinUrl = DevDispatchCatalog::href((string) $bulletin['kind'], (string) $bulletin['number_pad']);
        }

        $packParts = [];
        if ($overwatch !== '') {
            $packParts[] = 'Overwatch ' . $overwatch;
        }
        if ($athena !== '') {
            $packParts[] = 'Athena ' . $athena;
        }
        if ($extension !== '') {
            $packParts[] = 'Extension ' . $extension;
        }

        $codeVersion = $overwatch !== '' ? $overwatch : $platform;
        $fromFile = trim((string) ($file['codename'] ?? ''));
        $codeMeta = PackCodenameCatalog::forVersion($codeVersion);
        $codename = $fromFile !== '' ? $fromFile : (string) ($codeMeta['name'] ?? '');
        $codenameLabel = $codename !== '' ? ('Opération ' . $codename) : (string) ($codeMeta['label'] ?? '');

        return [
            'platform_version' => $platform,
            'deployed_at' => $deployedAt?->format(\DateTimeInterface::ATOM),
            'deployed_at_label' => $deployedAt ? self::formatFrenchDateTime($deployedAt) : 'Non renseignée',
            'deployed_source' => $source,
            'overwatch_version' => $overwatch,
            'athena_version' => $athena,
            'extension_version' => $extension,
            'pack_label' => $packParts !== [] ? implode(' · ', $packParts) : 'Pack non renseigné',
            'git_sha' => $sha !== '' ? substr($sha, 0, 7) : '',
            'bulletin_title' => $bulletinTitle,
            'bulletin_url' => $bulletinUrl,
            'codename' => $codename,
            'codename_label' => $codenameLabel,
            'major' => (int) ($codeMeta['major'] ?? PackCodenameCatalog::majorOf($codeVersion)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function readVersionFile(): array
    {
        $path = base_path('storage/app_version.json');
        if (!is_file($path)) {
            return [];
        }
        $raw = json_decode((string) file_get_contents($path), true);

        return is_array($raw) ? $raw : [];
    }

    private static function cleanVersion(mixed $value): string
    {
        $v = trim((string) $value);
        if ($v === '') {
            return '';
        }

        return preg_match('/^\d+\.\d+(\.\d+)?/', $v) ? $v : '';
    }

    private static function parseDate(mixed $raw): ?\DateTimeImmutable
    {
        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }
        try {
            return new \DateTimeImmutable($s);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{at: ?\DateTimeImmutable, sha: string}
     */
    private static function gitHeadMeta(): array
    {
        $empty = ['at' => null, 'sha' => ''];
        $gitDir = base_path('.git');
        if (!is_dir($gitDir) && !is_file($gitDir)) {
            return $empty;
        }
        $sha = trim((string) @shell_exec('git -C ' . escapeshellarg(base_path()) . ' rev-parse --short HEAD 2>/dev/null'));
        $iso = trim((string) @shell_exec('git -C ' . escapeshellarg(base_path()) . ' log -1 --format=%cI 2>/dev/null'));
        if ($sha === '' && $iso === '') {
            return $empty;
        }

        return [
            'at' => self::parseDate($iso),
            'sha' => preg_match('/^[0-9a-f]{4,40}$/i', $sha) ? $sha : '',
        ];
    }

    private static function formatFrenchDateTime(\DateTimeImmutable $dt): string
    {
        $months = [
            1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
            5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
            9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
        ];
        $local = $dt->setTimezone(new \DateTimeZone('Europe/Paris'));
        $m = (int) $local->format('n');

        return sprintf(
            '%d %s %s à %s',
            (int) $local->format('j'),
            $months[$m] ?? $local->format('m'),
            $local->format('Y'),
            $local->format('H:i')
        );
    }
}
