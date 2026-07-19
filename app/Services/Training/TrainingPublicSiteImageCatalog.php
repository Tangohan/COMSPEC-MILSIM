<?php

declare(strict_types=1);

namespace App\Services\Training;

/**
 * Catalogue des images publiques livrées avec le portail (public/assets/images).
 * Sert de médiathèque légère pour le Studio formation — sans table dédiée.
 */
final class TrainingPublicSiteImageCatalog
{
    private const REL_DIR = 'assets/images';

    private const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /**
     * Images souvent utiles en formation (mises en tête de liste si présentes).
     *
     * @var list<string>
     */
    private const PRIORITY = [
        'formation-de-specialite.jpg',
        'home.jpg',
        'fog-team.jpg',
        'fog-banner.jpg',
        'night-team.jpg',
        'hero-explosion.jpg',
        'les-etpes-de-recrutement.jpg',
        'armee-de-terre-recrute-secretaire-assistant.jpg',
        'armee-de-terre-recrute-chef-equipe-specialiste-terrain-infrastructure.jpg',
        'mutations.jpg',
        'Banner_Artstation.webp',
        'Athena_Graphique.png',
    ];

    /**
     * @return list<array{path: string, label: string, url: string, priority: bool}>
     */
    public function listImages(): array
    {
        $dir = base_path('public/' . self::REL_DIR);
        if (!is_dir($dir)) {
            return [];
        }

        $prioritySet = array_fill_keys(self::PRIORITY, true);
        $items = [];
        $dh = @opendir($dir);
        if ($dh === false) {
            return [];
        }
        while (($file = readdir($dh)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (!in_array($ext, self::EXTENSIONS, true)) {
                continue;
            }
            $abs = $dir . DIRECTORY_SEPARATOR . $file;
            if (!is_file($abs)) {
                continue;
            }
            $path = self::REL_DIR . '/' . $file;
            $items[] = [
                'path' => $path,
                'label' => $this->humanLabel($file),
                'url' => function_exists('training_media_url') ? training_media_url($path) : ('/' . $path),
                'priority' => isset($prioritySet[$file]),
            ];
        }
        closedir($dh);

        usort($items, static function (array $a, array $b): int {
            if ($a['priority'] !== $b['priority']) {
                return $a['priority'] ? -1 : 1;
            }

            return strnatcasecmp($a['label'], $b['label']);
        });

        return $items;
    }

    /**
     * Accepte uniquement un chemin relatif vers assets/images/… déjà présent sur le disque.
     */
    public function normalizePickedPath(?string $raw): ?string
    {
        $path = trim(str_replace('\\', '/', (string) $raw));
        if ($path === '' || preg_match('#^https?://#i', $path) === 1) {
            return null;
        }
        $path = ltrim($path, '/');
        if (str_contains($path, '..')) {
            return null;
        }
        if (!str_starts_with($path, self::REL_DIR . '/')) {
            return null;
        }
        $base = basename($path);
        if ($base === '' || $base === '.' || $base === '..') {
            return null;
        }
        $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
        if (!in_array($ext, self::EXTENSIONS, true)) {
            return null;
        }
        $abs = base_path('public/' . self::REL_DIR . '/' . $base);
        if (!is_file($abs)) {
            return null;
        }

        return self::REL_DIR . '/' . $base;
    }

    private function humanLabel(string $filename): string
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $base = str_replace(['_', '-', '.'], ' ', $base);
        $base = preg_replace('/\s+/', ' ', $base) ?? $base;
        $base = trim($base);
        if ($base === '') {
            return $filename;
        }
        if (function_exists('mb_convert_case')) {
            return mb_convert_case($base, MB_CASE_TITLE, 'UTF-8');
        }

        return ucwords(strtolower($base));
    }
}
