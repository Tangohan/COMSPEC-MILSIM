<?php

declare(strict_types=1);

namespace App\Support;

/** Vocabulaire de lecture commun au catalogue, aux emplois et au dossier RH. */
final class MilitarySpecialtyLabels
{
    public static function kind(string $kind): string
    {
        return match ($kind) {
            'domain' => 'Domaine ou famille de métiers',
            'entry_option' => 'Option d’engagement / candidature',
            'trade' => 'Métier (trade)',
            'trade_group' => 'Groupe de métiers (trade group)',
            default => 'Spécialité professionnelle',
        };
    }

    public static function verification(string $status): string
    {
        return match ($status) {
            'verified' => 'Référence vérifiée',
            'historical' => 'Référence historique — actualité à confirmer',
            default => 'À vérifier avant confirmation',
        };
    }

    public static function status(string $status): string
    {
        return match ($status) {
            'confirmed' => 'Confirmée par les RH',
            'archived' => 'Archivée',
            default => 'Déclarée — validation RH attendue',
        };
    }

    public static function title(array $row): string
    {
        $code = trim((string) ($row['awarded_code'] ?? $row['code'] ?? ''));
        $label = trim((string) ($row['label_fr'] ?? $row['official_title'] ?? ''));

        return ($code !== '' ? $code . ' · ' : '') . $label;
    }

    public static function sourceUrl(mixed $raw): string
    {
        $url = is_string($raw) ? trim($raw) : '';
        return filter_var($url, FILTER_VALIDATE_URL) && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['https', 'http'], true) ? $url : '';
    }

    /** Résumé d’affichage sans preuve ni information de validation personnelle. */
    public static function summary(array $rows, bool $compact = false): string
    {
        $parts = [];
        foreach ($rows as $row) {
            $part = $compact ? trim((string) ($row['awarded_code'] ?? $row['code'] ?? '')) : self::title($row);
            if ($part === '') $part = (string) ($row['label_fr'] ?? $row['official_title'] ?? '');
            if (($row['level_code'] ?? '') !== '' && $row['level_code'] !== null) $part .= ' (niveau ' . $row['level_code'] . ')';
            if ($part !== '') $parts[] = $part;
        }
        $suffix = $compact && count($parts) > 3 ? ' · +' . (count($parts) - 3) : '';
        return implode(' · ', $compact ? array_slice($parts, 0, 3) : $parts) . $suffix;
    }
}
