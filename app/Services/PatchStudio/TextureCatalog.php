<?php

declare(strict_types=1);

namespace App\Services\PatchStudio;

/** Bibliothèque commune immuable, distincte des ressources privées importées. */
final class TextureCatalog
{
    private static ?array $items = null;

    public static function all(): array
    {
        if (self::$items !== null) return self::$items;
        $raw = file_get_contents(base_path('public/assets/textures/patch-studio/catalog.json'));
        $catalog = json_decode((string) $raw, true, 16, JSON_THROW_ON_ERROR);
        if (($catalog['version'] ?? 0) !== 1 || !is_array($catalog['textures'] ?? null)) throw new \RuntimeException('Catalogue de textures invalide.');
        $items = [];
        foreach ($catalog['textures'] as $texture) {
            $id = $texture['id'] ?? '';
            if (!is_string($id) || preg_match('/^[a-z0-9-]{1,64}$/D', $id) !== 1
                || preg_match('/^[a-z0-9-]+\.jpg$/D', $texture['file'] ?? '') !== 1
                || preg_match('/^[a-f0-9]{64}$/D', $texture['sha256'] ?? '') !== 1
                || ($texture['width'] ?? 0) < 1 || ($texture['height'] ?? 0) < 1 || isset($items[$id])) {
                throw new \RuntimeException('Entrée de texture invalide.');
            }
            $items[$id] = $texture;
        }
        return self::$items = $items;
    }

    public static function find(mixed $id): ?array
    {
        return is_string($id) ? (self::all()[$id] ?? null) : null;
    }

    public static function used(array $document): array
    {
        $ids = [];
        $walk = static function (array $values) use (&$walk, &$ids): void {
            if (($values['type'] ?? '') === 'texture' && is_string($values['textureId'] ?? null)) $ids[$values['textureId']] = true;
            foreach ($values as $value) if (is_array($value)) $walk($value);
        };
        $walk($document);
        return array_keys($ids);
    }

    public static function read(string $id): array
    {
        $texture = self::find($id);
        if (!$texture) throw new \InvalidArgumentException('Texture inconnue.');
        $bytes = file_get_contents(base_path('public/assets/textures/patch-studio/' . $texture['file']));
        if (!is_string($bytes) || !hash_equals($texture['sha256'], hash('sha256', $bytes))) throw new \RuntimeException('Texture absente ou corrompue.');
        return $texture + ['data' => $bytes];
    }
}
