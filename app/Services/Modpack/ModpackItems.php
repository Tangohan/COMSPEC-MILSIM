<?php

declare(strict_types=1);

namespace App\Services\Modpack;

/**
 * Manifeste versionné des mods téléchargeables un par un (config/modpack_items.json).
 *
 * Liste blanche stricte : un mod n'est servi que s'il figure au manifeste, et seuls les fichiers listés
 * (chemin relatif, taille, SHA-256) peuvent entrer dans une archive. Aucune entrée utilisateur ne forme un chemin.
 */
final class ModpackItems
{
    public const ID_PATTERN = '/\A[a-z0-9_]{1,32}\z/';
    private const PATH_PATTERN = '/\A[A-Za-z0-9_@][A-Za-z0-9_@.\-]*(?:\/[A-Za-z0-9_@][A-Za-z0-9_@.\-]*)*\z/';
    private const WORKSHOP_PATTERN = '/\Ahttps:\/\/steamcommunity\.com\/sharedfiles\/filedetails\/\?id=\d{1,12}\z/';

    /** @var array<string, array<string, mixed>>|null */
    private ?array $items = null;

    public function __construct(private readonly string $manifest, private readonly string $root)
    {
    }

    public static function default(): self
    {
        return new self(base_path('config/modpack_items.json'), base_path());
    }

    /** @return array<string, array<string, mixed>> mods du manifeste, par identifiant, dans l'ordre d'affichage */
    public function all(): array
    {
        if ($this->items !== null) {
            return $this->items;
        }
        $data = json_decode((string) @file_get_contents($this->manifest), true);
        $items = [];
        foreach (is_array($data) ? ($data['items'] ?? []) : [] as $raw) {
            $item = $this->normalize($raw);
            if ($item !== null) {
                $items[$item['id']] = $item;
            }
        }
        return $this->items = $items;
    }

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array
    {
        return preg_match(self::ID_PATTERN, $id) === 1 ? ($this->all()[$id] ?? null) : null;
    }

    /** Chemin disque d'un fichier du manifeste, ou null si le fichier est absent, remplacé ou hors du dossier source. */
    public function filePath(array $item, array $file): ?string
    {
        $base = realpath($this->root . '/' . $item['source']);
        $link = $this->root . '/' . $item['source'] . '/' . $file['path'];
        $path = $base === false ? false : realpath($link);
        if ($path === false || !str_starts_with($path, $base . DIRECTORY_SEPARATOR) || !is_file($path) || is_link($link)) {
            return null;
        }
        return @filesize($path) === $file['size'] ? $path : null;
    }

    /** Vrai si le mod a des fichiers au manifeste et que chacun est présent avec la taille attendue. */
    public function isDownloadable(array $item): bool
    {
        if ($item['files'] === []) {
            return false;
        }
        foreach ($item['files'] as $file) {
            if ($this->filePath($item, $file) === null) {
                return false;
            }
        }
        return true;
    }

    public static function bytes(array $item): int
    {
        return (int) array_sum(array_column($item['files'], 'size'));
    }

    /** @return array<string, mixed>|null */
    private function normalize(mixed $raw): ?array
    {
        if (!is_array($raw) || !is_string($raw['id'] ?? null) || preg_match(self::ID_PATTERN, $raw['id']) !== 1
            || !is_string($raw['name'] ?? null) || preg_match('/\A[\p{L}\p{N} .\-]{1,48}\z/u', $raw['name']) !== 1) {
            return null;
        }
        $files = [];
        $folder = $raw['folder'] ?? null;
        $source = $raw['source'] ?? null;
        if (is_string($folder) && is_string($source) && preg_match(self::PATH_PATTERN, $folder) === 1
            && preg_match(self::PATH_PATTERN, $source) === 1 && str_starts_with($source, 'mod/')) {
            foreach (is_array($raw['files'] ?? null) ? $raw['files'] : [] as $file) {
                if (is_array($file) && is_string($file['path'] ?? null) && preg_match(self::PATH_PATTERN, $file['path']) === 1
                    && is_int($file['size'] ?? null) && $file['size'] >= 0
                    && is_string($file['sha256'] ?? null) && preg_match('/\A[a-f0-9]{64}\z/', $file['sha256']) === 1) {
                    $files[] = ['path' => $file['path'], 'size' => $file['size'], 'sha256' => $file['sha256']];
                }
            }
        }
        $version = $raw['version'] ?? null;
        $workshop = $raw['workshop_url'] ?? null;
        $requires = is_array($raw['requires'] ?? null) ? $raw['requires'] : [];
        return [
            'id' => $raw['id'],
            'name' => $raw['name'],
            'ico' => is_string($raw['ico'] ?? null) && preg_match('/\A[A-Z0-9]{2,4}\z/', $raw['ico']) === 1 ? $raw['ico'] : '',
            'version' => is_string($version) && ModpackVersion::isValid($version) ? $version : null,
            'folder' => $files === [] ? null : $folder,
            'source' => $files === [] ? null : $source,
            'requires' => array_values(array_filter($requires, static fn ($name): bool => is_string($name) && preg_match('/\A[\p{L}\p{N} _.\-]{1,32}\z/u', $name) === 1)),
            'in_pack' => ($raw['in_pack'] ?? false) === true,
            'workshop_url' => is_string($workshop) && preg_match(self::WORKSHOP_PATTERN, $workshop) === 1 ? $workshop : null,
            'files' => $files,
        ];
    }
}
