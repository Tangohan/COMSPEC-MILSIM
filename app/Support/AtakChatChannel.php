<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Canaux radio ATAK (système + custom) — clés stables et libellés métier.
 */
final class AtakChatChannel
{
    public const KIND_SYSTEM = 'system';
    public const KIND_CUSTOM = 'custom';

    /** @var array<string, string> channel_key => label FR */
    public const SYSTEM = [
        'groupe' => 'Groupe',
        'commandement' => 'Commandement',
        'general' => 'Général',
        'jtac' => 'JTAC',
        'air' => 'Air',
    ];

    /** Anciens préfixes radio → clé système */
    private const LEGACY_MAP = [
        'SQUAD' => 'general',
        'GLOBAL' => 'general',
        'COMMAND' => 'commandement',
        'JTAC' => 'jtac',
        'AIR' => 'air',
        'GROUPE' => 'groupe',
        'GROUP' => 'groupe',
        'HQ' => 'commandement',
        'C2' => 'commandement',
    ];

    public static function isSystemKey(string $key): bool
    {
        return isset(self::SYSTEM[self::normalizeKey($key)]);
    }

    public static function normalizeKey(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return 'general';
        }
        $upper = mb_strtoupper($raw);
        if (isset(self::LEGACY_MAP[$upper])) {
            return self::LEGACY_MAP[$upper];
        }
        $slug = self::slugify($raw);
        if ($slug === '') {
            return 'general';
        }
        if (isset(self::SYSTEM[$slug])) {
            return $slug;
        }
        if (isset(self::LEGACY_MAP[mb_strtoupper($slug)])) {
            return self::LEGACY_MAP[mb_strtoupper($slug)];
        }

        return $slug;
    }

    public static function slugify(string $label): string
    {
        $s = mb_strtolower(trim($label));
        $s = preg_replace('/[àáâãäå]/u', 'a', $s) ?? $s;
        $s = preg_replace('/[èéêë]/u', 'e', $s) ?? $s;
        $s = preg_replace('/[ìíîï]/u', 'i', $s) ?? $s;
        $s = preg_replace('/[òóôõö]/u', 'o', $s) ?? $s;
        $s = preg_replace('/[ùúûü]/u', 'u', $s) ?? $s;
        $s = preg_replace('/[ç]/u', 'c', $s) ?? $s;
        $s = preg_replace('/[^a-z0-9]+/u', '_', $s) ?? $s;
        $s = trim($s, '_');
        if (strlen($s) > 48) {
            $s = substr($s, 0, 48);
            $s = rtrim($s, '_');
        }

        return $s;
    }

    public static function labelFor(string $key, ?string $fallbackLabel = null): string
    {
        $key = self::normalizeKey($key);
        if (isset(self::SYSTEM[$key])) {
            return self::SYSTEM[$key];
        }
        $fb = trim((string) $fallbackLabel);
        if ($fb !== '') {
            return $fb;
        }

        return mb_convert_case(str_replace('_', ' ', $key), MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Déduit channel_key depuis le corps d’un message (compat historique).
     */
    public static function inferFromBody(?string $body): string
    {
        $body = trim((string) $body);
        if ($body === '') {
            return 'general';
        }
        $upper = mb_strtoupper($body);
        if (str_starts_with($upper, 'GROUPE|') || $upper === 'GROUPE') {
            return 'groupe';
        }
        if (str_contains($upper, '[HQ]') || str_contains($upper, '][COMMAND]')) {
            return 'commandement';
        }
        if (preg_match(
            '/^\[\d{1,2}:\d{2}:\d{2}\]\[([A-Za-z0-9_]+)\]\[[A-Za-z0-9_]+\]\[[A-Za-z0-9_]+\]/u',
            $body,
            $m
        )) {
            return self::normalizeKey((string) ($m[1] ?? 'general'));
        }

        return 'general';
    }

    /**
     * @return list<array{channel_key: string, label: string, kind: string}>
     */
    public static function systemRows(): array
    {
        $out = [];
        foreach (self::SYSTEM as $key => $label) {
            $out[] = [
                'channel_key' => $key,
                'label' => $label,
                'kind' => self::KIND_SYSTEM,
            ];
        }

        return $out;
    }

    /** Jeton radio court pour le préfixe [HH:MM:SS][KEY]… (jeu). */
    public static function radioToken(string $key): string
    {
        $key = self::normalizeKey($key);

        return match ($key) {
            'groupe' => 'GROUPE',
            'commandement' => 'COMMAND',
            'general' => 'SQUAD',
            'jtac' => 'JTAC',
            'air' => 'AIR',
            default => mb_strtoupper(substr(preg_replace('/[^A-Za-z0-9_]/', '', $key) ?: 'CUSTOM', 0, 12)),
        };
    }
}
