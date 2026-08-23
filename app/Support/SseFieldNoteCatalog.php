<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Référentiel des fiches de renseignement simplifiées.
 *
 * Une fiche porte un type (badge bleu), un ou plusieurs thèmes (badges colorés),
 * un degré d'urgence et un état de suivi. Les libellés servent l'affichage —
 * côté terrain comme côté bureau, pour que l'ATAK et le portail parlent pareil.
 */
final class SseFieldNoteCatalog
{
    public const BODY_MAX_LENGTH = 1000;
    public const BODY_MIN_LENGTH = 10;
    public const ATTACHMENTS_MAX = 4;
    public const THEMES_MAX = 4;
    public const CUSTOM_THEME_LABEL_MAX = 40;

    /** @var list<string> */
    public const TONES = ['critical', 'warning', 'info', 'neutral'];

    public const DEFAULT_KIND = 'FRM';
    public const DEFAULT_URGENCY = 'routine';
    public const DEFAULT_STATUS = 'transmise';

    /**
     * Types de fiche. La clé est le sigle affiché en badge.
     *
     * @var array<string, array{label: string, hint: string}>
     */
    public const KINDS = [
        'FRM' => [
            'label' => 'Fiche de renseignement de mission',
            'hint' => 'Ce que vous avez constaté pendant la mission en cours.',
        ],
        'FRO' => [
            'label' => 'Fiche d’observation',
            'hint' => 'Un fait observé, sans lien direct avec la mission du jour.',
        ],
        'FRC' => [
            'label' => 'Fiche de contact',
            'hint' => 'Un échange avec une personne, un groupe ou une autorité locale.',
        ],
        'FRA' => [
            'label' => 'Fiche d’ambiance',
            'hint' => 'Le climat général d’un secteur : attitude de la population, tensions.',
        ],
        'FRT' => [
            'label' => 'Fiche technique',
            'hint' => 'Matériel, véhicule, installation, marquage ou signe distinctif relevé.',
        ],
    ];

    /**
     * Thèmes. « tone » sert uniquement à colorer le badge.
     *
     * @var array<string, array{label: string, tone: string}>
     */
    public const THEMES = [
        'securite_publique' => ['label' => 'Sécurité publique', 'tone' => 'critical'],
        'menace_armee' => ['label' => 'Menace armée', 'tone' => 'critical'],
        'engins_explosifs' => ['label' => 'Engins explosifs', 'tone' => 'critical'],
        'ordre_public' => ['label' => 'Ordre public', 'tone' => 'warning'],
        'trafics' => ['label' => 'Trafics', 'tone' => 'warning'],
        'mouvements' => ['label' => 'Mouvements et flux', 'tone' => 'warning'],
        'population' => ['label' => 'Population et attitude', 'tone' => 'info'],
        'infrastructures' => ['label' => 'Infrastructures', 'tone' => 'info'],
        'communications' => ['label' => 'Communications', 'tone' => 'info'],
        'logistique' => ['label' => 'Logistique adverse', 'tone' => 'info'],
        'environnement' => ['label' => 'Environnement et terrain', 'tone' => 'neutral'],
        'divers' => ['label' => 'Divers', 'tone' => 'neutral'],
    ];

    /** @var array<string, array{label: string, hint: string, tone: string}> */
    public const URGENCIES = [
        'routine' => [
            'label' => 'Courant',
            'hint' => 'À exploiter dans le cours normal du travail.',
            'tone' => 'neutral',
        ],
        'priorite' => [
            'label' => 'Prioritaire',
            'hint' => 'À regarder dans la journée.',
            'tone' => 'warning',
        ],
        'immediate' => [
            'label' => 'Immédiat',
            'hint' => 'Doit remonter tout de suite au poste de commandement.',
            'tone' => 'critical',
        ],
    ];

    /** @var array<string, string> */
    public const STATUSES = [
        'brouillon' => 'Brouillon',
        'transmise' => 'Transmise',
        'prise_en_compte' => 'Prise en compte',
        'exploitee' => 'Exploitée',
        'sans_suite' => 'Classée sans suite',
    ];

    /** @var array<string, string> */
    public const ORIGINS = [
        'web' => 'Bureau SSE',
        'atak' => 'ATAK',
        'arma' => 'Terrain (Arma)',
    ];

    /** @var array<string, string> */
    public const ATTACHMENT_KINDS = [
        'photo' => 'Photographie',
        'capture' => 'Capture d’écran',
        'document' => 'Document',
        'croquis' => 'Croquis',
    ];

    /** @return array<string, string> */
    public static function kindOptions(): array
    {
        $out = [];
        foreach (self::KINDS as $code => $def) {
            $out[$code] = $def['label'];
        }

        return $out;
    }

    /** @return array<string, string> */
    public static function themeOptions(): array
    {
        $out = [];
        foreach (self::THEMES as $code => $def) {
            $out[$code] = $def['label'];
        }

        return $out;
    }

    /** @return array<string, string> */
    public static function urgencyOptions(): array
    {
        $out = [];
        foreach (self::URGENCIES as $code => $def) {
            $out[$code] = $def['label'];
        }

        return $out;
    }

    public static function normalizeKind(mixed $value): string
    {
        $code = strtoupper(trim((string) $value));

        return isset(self::KINDS[$code]) ? $code : self::DEFAULT_KIND;
    }

    public static function kindLabel(string $code): string
    {
        return self::KINDS[strtoupper($code)]['label'] ?? self::KINDS[self::DEFAULT_KIND]['label'];
    }

    public static function normalizeUrgency(mixed $value): string
    {
        $code = strtolower(trim((string) $value));

        return isset(self::URGENCIES[$code]) ? $code : self::DEFAULT_URGENCY;
    }

    public static function urgencyLabel(string $code): string
    {
        return self::URGENCIES[strtolower($code)]['label'] ?? self::URGENCIES[self::DEFAULT_URGENCY]['label'];
    }

    public static function normalizeStatus(mixed $value): string
    {
        $code = strtolower(trim((string) $value));

        return isset(self::STATUSES[$code]) ? $code : self::DEFAULT_STATUS;
    }

    public static function statusLabel(string $code): string
    {
        return self::STATUSES[strtolower($code)] ?? self::STATUSES[self::DEFAULT_STATUS];
    }

    public static function originLabel(string $code): string
    {
        return self::ORIGINS[strtolower($code)] ?? self::ORIGINS['web'];
    }

    public static function themeLabel(string $code): string
    {
        $parsed = self::parseTheme($code);

        return $parsed['label'] ?? $code;
    }

    public static function themeTone(string $code): string
    {
        $parsed = self::parseTheme($code);

        return $parsed['tone'] ?? 'neutral';
    }

    /**
     * @return array{code: string, label: string, tone: string, custom: bool}|null
     */
    public static function parseTheme(mixed $value): ?array
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $known = strtolower($raw);
        if (isset(self::THEMES[$known])) {
            return [
                'code' => $known,
                'label' => self::THEMES[$known]['label'],
                'tone' => self::THEMES[$known]['tone'],
                'custom' => false,
            ];
        }

        if (!str_starts_with($raw, 'c:')) {
            return null;
        }

        $parts = explode(':', $raw, 3);
        if (count($parts) < 3) {
            return null;
        }
        $tone = strtolower($parts[1]);
        if (!in_array($tone, self::TONES, true)) {
            $tone = 'neutral';
        }
        $label = self::sanitizeCustomLabel($parts[2]);
        if ($label === '') {
            return null;
        }

        return [
            'code' => 'c:' . $tone . ':' . $label,
            'label' => $label,
            'tone' => $tone,
            'custom' => true,
        ];
    }

    public static function encodeCustomTheme(string $label, string $tone = 'neutral'): ?string
    {
        $parsed = self::parseTheme('c:' . $tone . ':' . $label);

        return $parsed['code'] ?? null;
    }

    public static function sanitizeCustomLabel(string $label): string
    {
        $label = trim(strip_tags($label));
        $label = str_replace([':', '[', ']', '"', "\n", "\r", "\t"], ' ', $label);
        $label = preg_replace('/\s+/u', ' ', $label) ?? $label;
        $label = trim($label);
        if (mb_strlen($label) > self::CUSTOM_THEME_LABEL_MAX) {
            $label = mb_substr($label, 0, self::CUSTOM_THEME_LABEL_MAX);
        }
        if (mb_strlen($label) < 2) {
            return '';
        }

        return $label;
    }

    public static function attachmentKindLabel(string $code): string
    {
        return self::ATTACHMENT_KINDS[strtolower($code)] ?? self::ATTACHMENT_KINDS['photo'];
    }

    /**
     * Garde les thèmes du référentiel et les thèmes libres, sans doublon, dans la limite fixée.
     *
     * @param mixed $value liste, chaîne JSON ou liste séparée par des virgules
     * @return list<string>
     */
    public static function normalizeThemes(mixed $value): array
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return [];
            }
            $decoded = json_decode($trimmed, true);
            $value = is_array($decoded) ? $decoded : explode(',', $trimmed);
        }
        if (!is_array($value)) {
            return [];
        }

        $out = [];
        $seenLabels = [];
        foreach ($value as $raw) {
            $parsed = self::parseTheme($raw);
            if ($parsed === null) {
                continue;
            }
            $code = $parsed['code'];
            $labelKey = mb_strtolower($parsed['label']);
            if (in_array($code, $out, true) || isset($seenLabels[$labelKey])) {
                continue;
            }
            $seenLabels[$labelKey] = true;
            $out[] = $code;
            if (count($out) >= self::THEMES_MAX) {
                break;
            }
        }

        return $out;
    }

    /**
     * Coupe le texte à la limite affichée dans les rédacteurs (web et ATAK).
     */
    public static function normalizeBody(mixed $value): string
    {
        $body = trim((string) $value);
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        if (mb_strlen($body) > self::BODY_MAX_LENGTH) {
            $body = mb_substr($body, 0, self::BODY_MAX_LENGTH);
        }

        return $body;
    }

    /**
     * Référentiel complet, tel qu'exposé au client ATAK pour afficher les mêmes
     * libellés que le portail.
     *
     * @return array<string, mixed>
     */
    public static function clientCatalog(): array
    {
        $kinds = [];
        foreach (self::KINDS as $code => $def) {
            $kinds[] = ['code' => $code, 'label' => $def['label'], 'hint' => $def['hint']];
        }
        $themes = [];
        foreach (self::THEMES as $code => $def) {
            $themes[] = ['code' => $code, 'label' => $def['label'], 'tone' => $def['tone']];
        }
        $urgencies = [];
        foreach (self::URGENCIES as $code => $def) {
            $urgencies[] = ['code' => $code, 'label' => $def['label'], 'hint' => $def['hint']];
        }

        return [
            'body_max_length' => self::BODY_MAX_LENGTH,
            'body_min_length' => self::BODY_MIN_LENGTH,
            'attachments_max' => self::ATTACHMENTS_MAX,
            'themes_max' => self::THEMES_MAX,
            'kinds' => $kinds,
            'themes' => $themes,
            'urgencies' => $urgencies,
        ];
    }
}
