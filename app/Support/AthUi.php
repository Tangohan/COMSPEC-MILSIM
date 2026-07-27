<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Rendu cellules / badges du design system ATHENA (maquette Back-Office.dc.html).
 */
final class AthUi
{
    /** @var array<string, array{fg: string, bg: string, bd: string}> */
    private const TONE = [
        'ok' => ['fg' => '#0b6b47', 'bg' => '#e6f8f0', 'bd' => '#bfe9d8'],
        'warn' => ['fg' => '#8a5a06', 'bg' => '#fdf3e2', 'bd' => '#f2ddb4'],
        'bad' => ['fg' => '#a32222', 'bg' => '#fdecec', 'bd' => '#f6cccc'],
        'info' => ['fg' => '#1e4f80', 'bg' => '#eaf2fb', 'bd' => '#c9dcf0'],
        'neut' => ['fg' => '#3c474c', 'bg' => '#f2f5f6', 'bd' => '#e3e8ea'],
    ];

  /** @var list<string> */
    private const WORDS_OK = [
        'actif', 'validé', 'validée', 'présent', 'connecté', 'à jour', 'confirmé', 'conforme',
        'signé', 'en ligne', 'succès', 'ouvert', 'oui', 'clos', 'approuvé', 'terminé',
        'opérationnel', 'payé', 'complet',
    ];

  /** @var list<string> */
    private const WORDS_WARN = [
        'en attente', 'en relecture', 'partiel', 'peut-être', 'retard', 'expire', 'à renouveler', 'en cours',
        'brouillon', 'révision', 'différé', 'planifié', 'test', 'en attente de vérification',
    ];

  /** @var list<string> */
    private const WORDS_BAD = [
        'inactif', 'refusé', 'absent', 'échec', 'expiré', 'bloqué', 'hors ligne', 'non',
        'manquant', 'critique', 'suspendu', 'annulé', 'perdu', 'erreur',
    ];

    /**
     * @return array{label: string, kind: string, align: string}
     */
    public static function parseColumn(string $col): array
    {
        $parts = explode('|', $col, 2);
        $kind = $parts[1] ?? '';

        return [
            'label' => $parts[0],
            'kind' => $kind,
            'align' => $kind === 'r' ? 'right' : 'left',
        ];
    }

    /**
     * @return array{fg: string, bg: string, bd: string}
     */
    public static function toneOf(string $value): array
    {
        $s = mb_strtolower(trim($value), 'UTF-8');
        if ($s === '' || $s === '—') {
            return self::TONE['neut'];
        }
        foreach (self::WORDS_OK as $w) {
            if ($s === $w || str_starts_with($s, $w)) {
                return self::TONE['ok'];
            }
        }
        foreach (self::WORDS_WARN as $w) {
            if ($s === $w || str_starts_with($s, $w)) {
                return self::TONE['warn'];
            }
        }
        foreach (self::WORDS_BAD as $w) {
            if ($s === $w || str_starts_with($s, $w)) {
                return self::TONE['bad'];
            }
        }

        return self::TONE['info'];
    }

    public static function tagClass(string $value): string
    {
        $tone = self::toneOf($value);

        return match ($tone) {
            self::TONE['ok'] => 'ath-tag--ok',
            self::TONE['warn'] => 'ath-tag--warn',
            self::TONE['bad'] => 'ath-tag--bad',
            self::TONE['info'] => 'ath-tag--info',
            default => 'ath-tag--neut',
        };
    }

    /**
     * @return array{
     *   align: string,
     *   mono: bool,
     *   weight: int,
     *   fg: string,
     *   bg: string,
     *   bd: string,
     *   pad: string,
     *   badge: bool
     * }
     */
    public static function cellMeta(string $value, string $kind = ''): array
    {
        $meta = [
            'align' => 'left',
            'mono' => false,
            'weight' => 600,
            'fg' => '#20282c',
            'bg' => 'transparent',
            'bd' => 'transparent',
            'pad' => '0',
            'badge' => false,
        ];

        if ($kind === 'm') {
            $meta['mono'] = true;
            $meta['weight'] = 500;
            $meta['fg'] = '#3c474c';
        }
        if ($kind === 'r') {
            $meta['align'] = 'right';
            $meta['mono'] = true;
            $meta['weight'] = 700;
        }
        if ($kind === 'b') {
            $tone = self::toneOf($value);
            $meta['badge'] = true;
            $meta['weight'] = 800;
            $meta['fg'] = $tone['fg'];
            $meta['bg'] = $tone['bg'];
            $meta['bd'] = $tone['bd'];
            $meta['pad'] = '1px 8px';
        }

        return $meta;
    }

    /**
     * @param list<array{label: string, value: string, delta?: string, tone?: string, pct?: string, note?: string}> $items
     * @return list<array{label: string, value: string, delta: string, tone: string, pct: string, note: string}>
     */
    public static function normalizeKpis(array $items, int $max = 5): array
    {
        $out = [];
        foreach ($items as $item) {
            if (count($out) >= $max) {
                break;
            }
            $out[] = [
                'label' => (string) ($item['label'] ?? ''),
                'value' => (string) ($item['value'] ?? '—'),
                'delta' => (string) ($item['delta'] ?? ''),
                'tone' => (string) ($item['tone'] ?? '#0b8a5c'),
                'pct' => (string) ($item['pct'] ?? '0%'),
                'note' => (string) ($item['note'] ?? ''),
            ];
        }

        return $out;
    }
}
