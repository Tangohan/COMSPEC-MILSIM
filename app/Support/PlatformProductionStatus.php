<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Instantané production (portail + mods) pour bannières back-office.
 * Source : storage/app_version.json (+ date Git si le dépôt est disponible). Les versions
 * de l’ATAK natif et de COMSPEC Link viennent d’AtakReleaseInfo (fichiers du mod), pour
 * suivre chaque release sans retoucher le fichier de version.
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
     *   atak_version: string,
     *   link_version: string,
     *   pack_label: string,
     *   git_sha: string,
     *   bulletin_title: string,
     *   bulletin_url: string,
     *   codename: string,
     *   codename_label: string,
     *   codename_state: string,
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
        $native = AtakReleaseInfo::latest();
        $atak = self::cleanVersion($native['atak_native'] ?? '');
        if ($atak === '') {
            $atak = self::cleanVersion($pack['atak'] ?? '');
        }
        $link = self::cleanVersion($native['link'] ?? '');
        if ($link === '') {
            $link = self::cleanVersion($pack['link'] ?? $pack['extension'] ?? $file['extension_version'] ?? '');
        }
        $extension = $link;

        $fileAt = self::parseDate($file['updated_at'] ?? null);
        $git = self::gitHeadMeta();
        $deployedAt = $fileAt;
        $source = 'fichier';
        $gitAt = $git['at'] instanceof \DateTimeImmutable ? $git['at'] : self::gitDeployTime();
        if ($gitAt instanceof \DateTimeImmutable) {
            $git['at'] = $gitAt;
            if ($deployedAt === null || $git['at'] >= $deployedAt) {
                $deployedAt = $git['at'];
                $source = 'dépôt';
            }
        }

        $sha = trim((string) ($file['git_sha'] ?? ''));
        if ($sha === '' && $git['sha'] !== '') {
            $sha = $git['sha'];
        }
        if ($sha === '') {
            $sha = BuildVersion::gitHead(base_path('.git'));
        }

        $bulletin = DevDispatchCatalog::forProductionBanner();
        $bulletinTitle = is_array($bulletin) ? trim((string) ($bulletin['title'] ?? '')) : '';
        $bulletinUrl = '';
        if (is_array($bulletin) && ($bulletin['kind'] ?? '') !== '' && ($bulletin['number_pad'] ?? '') !== '') {
            $bulletinUrl = DevDispatchCatalog::href((string) $bulletin['kind'], (string) $bulletin['number_pad']);
        }

        $packParts = [];
        if ($atak !== '') {
            $packParts[] = 'ATAK ' . $atak;
        }
        if ($link !== '') {
            $packParts[] = 'Link ' . $link;
        }
        if ($overwatch !== '') {
            $packParts[] = 'Overwatch ' . $overwatch;
        }

        // Le nom de ville suit la version du portail (X.Y), plus celle du pack Overwatch.
        $codeMeta = PackCodenameCatalog::forVersion($platform);
        $fromFile = trim((string) ($file['codename'] ?? ''));
        $codename = (string) ($codeMeta['name'] ?? '') !== '' ? (string) $codeMeta['name'] : $fromFile;
        $codenameState = $codename === (string) ($codeMeta['name'] ?? '') ? (string) ($codeMeta['state'] ?? '') : '';

        return [
            'platform_version' => $platform,
            'deployed_at' => $deployedAt?->format(\DateTimeInterface::ATOM),
            'deployed_at_label' => $deployedAt ? self::formatFrenchDateTime($deployedAt) : 'Non renseignée',
            'deployed_source' => $source,
            'overwatch_version' => $overwatch,
            'athena_version' => $athena,
            'extension_version' => $extension,
            'atak_version' => $atak,
            'link_version' => $link,
            'pack_label' => $packParts !== [] ? implode(' · ', $packParts) : 'Pack non renseigné',
            'git_sha' => $sha !== '' ? substr($sha, 0, 7) : '',
            'bulletin_title' => $bulletinTitle,
            'bulletin_url' => $bulletinUrl,
            'codename' => $codename,
            'codename_label' => $codename,
            'codename_state' => $codenameState,
            'major' => (int) ($codeMeta['major'] ?? PackCodenameCatalog::majorOf($platform)),
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

    /**
     * Heure du dernier déploiement sans lancer git (PHP-FPM ne peut pas toujours) :
     * `git pull` réécrit la référence de la branche et FETCH_HEAD.
     */
    private static function gitDeployTime(): ?\DateTimeImmutable
    {
        $gitDir = base_path('.git');
        if (!is_dir($gitDir)) {
            return null;
        }
        $candidates = [$gitDir . '/FETCH_HEAD', $gitDir . '/ORIG_HEAD', $gitDir . '/HEAD'];
        $head = trim((string) @file_get_contents($gitDir . '/HEAD'));
        if (str_starts_with($head, 'ref:')) {
            $ref = trim(substr($head, 4));
            if ($ref !== '' && !str_contains($ref, '..')) {
                array_unshift($candidates, $gitDir . '/' . $ref);
            }
        }
        $latest = 0;
        foreach ($candidates as $file) {
            $t = is_file($file) ? (int) @filemtime($file) : 0;
            $latest = max($latest, $t);
        }

        return $latest > 0 ? (new \DateTimeImmutable('@' . $latest)) : null;
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
