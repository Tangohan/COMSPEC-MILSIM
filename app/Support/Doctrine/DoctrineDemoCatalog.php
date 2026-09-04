<?php

declare(strict_types=1);

namespace App\Support\Doctrine;

/**
 * Catalogue des doctrines de démonstration historiques (plus de seed).
 * Correspondance stricte : référence connue + titre ou slug du seed.
 */
final class DoctrineDemoCatalog
{
    public const ATAK_REFERENCE = 'SIC/ATAK/2026-001';

    /**
     * @return array{
     *     remove: list<array{reference: string, title: string, slug: string}>,
     *     keep: list<array{reference: string, title: string, slug: string}>
     * }
     */
    public static function load(): array
    {
        $path = dirname(__DIR__, 3) . '/bootstrap/doctrine_demo_seed.php';
        if (!is_file($path)) {
            return ['remove' => [], 'keep' => []];
        }
        $data = require $path;
        if (!is_array($data)) {
            return ['remove' => [], 'keep' => []];
        }

        return [
            'remove' => self::normalizeRows($data['remove'] ?? []),
            'keep' => self::normalizeRows($data['keep'] ?? []),
        ];
    }

    /**
     * @return list<array{reference: string, title: string, slug: string}>
     */
    public static function removeTargets(): array
    {
        return self::load()['remove'];
    }

    /**
     * @return list<string>
     */
    public static function keepReferences(): array
    {
        $refs = [];
        foreach (self::load()['keep'] as $row) {
            $refs[] = strtoupper($row['reference']);
        }
        $refs[] = self::ATAK_REFERENCE;

        return array_values(array_unique($refs));
    }

    public static function isKeptReference(string $referenceCode): bool
    {
        $ref = strtoupper(trim($referenceCode));
        if ($ref === '') {
            return false;
        }

        return in_array($ref, self::keepReferences(), true);
    }

    public static function isRemoveTarget(string $referenceCode, string $title, string $slug = ''): bool
    {
        $ref = strtoupper(trim($referenceCode));
        if ($ref === '' || self::isKeptReference($ref)) {
            return false;
        }
        $normTitle = self::normalizeTitle($title);
        $normSlug = strtolower(trim($slug));
        foreach (self::removeTargets() as $row) {
            if (strtoupper($row['reference']) !== $ref) {
                continue;
            }
            if (self::normalizeTitle($row['title']) === $normTitle) {
                return true;
            }
            if ($normSlug !== '' && strtolower($row['slug']) === $normSlug) {
                return true;
            }
        }

        return false;
    }

    public static function looksLikeDemoPlaceholder(string $summary, string $filePath = ''): bool
    {
        $summary = trim($summary);
        $filePath = str_replace('\\', '/', $filePath);

        return str_starts_with($summary, 'Document de démonstration')
            || str_contains($filePath, 'storage/documents/demo/')
            || str_contains($filePath, '/documents/demo/');
    }

    public static function normalizeTitle(string $title): string
    {
        $title = trim($title);
        $title = str_replace(["\u{2019}", "\u{2018}", '`', '´'], "'", $title);

        return preg_replace('/\s+/u', ' ', $title) ?? $title;
    }

    /**
     * @param mixed $rows
     * @return list<array{reference: string, title: string, slug: string}>
     */
    private static function normalizeRows(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $reference = strtoupper(trim((string) ($row['reference'] ?? '')));
            $title = trim((string) ($row['title'] ?? ''));
            $slug = strtolower(trim((string) ($row['slug'] ?? '')));
            if ($reference === '' || $title === '') {
                continue;
            }
            $out[] = [
                'reference' => $reference,
                'title' => $title,
                'slug' => $slug !== '' ? $slug : strtolower(str_replace(['/', ' '], '-', $reference)),
            ];
        }

        return $out;
    }
}
