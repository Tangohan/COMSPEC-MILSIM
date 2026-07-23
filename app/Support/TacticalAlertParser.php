<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Alertes tactiques structurées (inspiré Iceman ATAK_Alerts : TIC / CLEAR / FRAGO / SALUTE / Eagle Down).
 * Préfixe messagerie : « ALERTE TACTIQUE|KIND|callsign|grid|x|y|summary »
 */
final class TacticalAlertParser
{
    private const ALERT_PREFIX = 'ALERTE TACTIQUE';

    /** Fenêtre d’affichage des alertes actives (secondes). */
    public const ACTIVE_WINDOW_SECONDS = 4 * 60 * 60;

    /** @var list<string> */
    public const KINDS = [
        'tic',
        'tic_clear',
        'frago',
        'salute',
        'eagle_down',
        'bda',
    ];

    /**
     * @return array{
     *   is_tactical: bool,
     *   kind: string,
     *   kind_label: string,
     *   call_sign: string,
     *   grid: string,
     *   pos_x: ?float,
     *   pos_y: ?float,
     *   summary: string,
     *   severity: string
     * }|null
     */
    public static function parse(?string $body): ?array
    {
        $body = trim((string) $body);
        if ($body === '') {
            return null;
        }

        $body = self::stripCommsPrefix($body);
        $upper = mb_strtoupper($body);

        if (!str_starts_with($upper, 'ALERTE TACTIQUE') && !str_starts_with($upper, 'ALERTE TACTIQUE|')) {
            // Réglages d’affichage camps (pas une alerte, mais même canal)
            return null;
        }

        $parts = array_map('trim', explode('|', $body));
        // [0]=préfixe [1]=kind [2]=callsign [3]=grid [4]=x [5]=y [6]=summary…
        $kindRaw = strtoupper((string) ($parts[1] ?? 'TIC'));
        $kind = self::normalizeKind($kindRaw);
        $callSign = (string) ($parts[2] ?? '');
        $grid = (string) ($parts[3] ?? '');
        $posX = self::parseCoord($parts[4] ?? null);
        $posY = self::parseCoord($parts[5] ?? null);
        $summary = trim(implode(' — ', array_slice($parts, 6)));
        if ($summary === '') {
            $summary = self::kindLabelFr($kind) . ($callSign !== '' ? ' — ' . $callSign : '');
            if ($grid !== '') {
                $summary .= ' — Grille ' . $grid;
            }
        }

        $salute = null;
        if ($kind === 'salute') {
            $salute = self::parseSaluteFields(array_slice($parts, 6));
            if ($salute !== null) {
                $built = self::formatSaluteSummary($salute);
                if ($built !== '') {
                    $summary = $built;
                }
            }
        }

        $out = [
            'is_tactical' => true,
            'kind' => $kind,
            'kind_label' => self::kindLabelFr($kind),
            'call_sign' => $callSign,
            'grid' => $grid,
            'pos_x' => $posX,
            'pos_y' => $posY,
            'summary' => $summary,
            'severity' => self::severityForKind($kind),
        ];
        if ($salute !== null) {
            $out['salute'] = $salute;
        }

        return $out;
    }

    /**
     * Construit le corps messagerie pour un SALUTE structuré.
     *
     * @param array{size?:string,activity?:string,location?:string,unit?:string,time?:string,equipment?:string} $fields
     */
    public static function buildSaluteBody(array $fields): string
    {
        $map = [
            'S' => trim((string) ($fields['size'] ?? $fields['S'] ?? '')),
            'A' => trim((string) ($fields['activity'] ?? $fields['A'] ?? '')),
            'L' => trim((string) ($fields['location'] ?? $fields['L'] ?? '')),
            'U' => trim((string) ($fields['unit'] ?? $fields['U'] ?? '')),
            'T' => trim((string) ($fields['time'] ?? $fields['T'] ?? '')),
            'E' => trim((string) ($fields['equipment'] ?? $fields['E'] ?? '')),
        ];
        $chunks = [];
        foreach ($map as $k => $v) {
            $chunks[] = $k . '=' . str_replace(['|', "\n", "\r"], ['/', ' ', ''], $v);
        }

        return implode('|', $chunks);
    }

    /**
     * @param list<string> $parts
     * @return array{size:string,activity:string,location:string,unit:string,time:string,equipment:string}|null
     */
    public static function parseSaluteFields(array $parts): ?array
    {
        $out = [
            'size' => '',
            'activity' => '',
            'location' => '',
            'unit' => '',
            'time' => '',
            'equipment' => '',
        ];
        $keyMap = [
            'S' => 'size', 'SIZE' => 'size', 'TAILLE' => 'size',
            'A' => 'activity', 'ACTIVITY' => 'activity', 'ACTIVITE' => 'activity', 'ACTIVITÉ' => 'activity',
            'L' => 'location', 'LOCATION' => 'location', 'LOCALISATION' => 'location',
            'U' => 'unit', 'UNIT' => 'unit', 'UNITE' => 'unit', 'UNITÉ' => 'unit',
            'T' => 'time', 'TIME' => 'time', 'HEURE' => 'time',
            'E' => 'equipment', 'EQUIPMENT' => 'equipment', 'EQUIPEMENT' => 'equipment', 'ÉQUIPEMENT' => 'equipment',
        ];
        $found = false;
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }
            if (preg_match('/^([A-Za-zÀ-ü]+)\s*[:=]\s*(.*)$/u', $part, $m)) {
                $k = mb_strtoupper(trim($m[1]));
                $v = trim($m[2]);
                if (isset($keyMap[$k])) {
                    $out[$keyMap[$k]] = $v;
                    $found = true;
                }
            }
        }
        // Ancien gabarit brut « Taille — Activité — … » sans valeurs → pas de structure.
        if (!$found) {
            $joined = implode(' — ', $parts);
            if (preg_match('/^Taille\s*[—\-]/s*Activit/iu', $joined)) {
                return [
                    'size' => '',
                    'activity' => '',
                    'location' => '',
                    'unit' => '',
                    'time' => '',
                    'equipment' => '',
                ];
            }

            return null;
        }

        return $out;
    }

    /**
     * @param array{size?:string,activity?:string,location?:string,unit?:string,time?:string,equipment?:string} $salute
     */
    public static function formatSaluteSummary(array $salute): string
    {
        $labels = [
            'size' => 'Taille',
            'activity' => 'Activité',
            'location' => 'Localisation',
            'unit' => 'Unité',
            'time' => 'Heure',
            'equipment' => 'Équipement',
        ];
        $bits = [];
        foreach ($labels as $k => $lab) {
            $v = trim((string) ($salute[$k] ?? ''));
            if ($v !== '') {
                $bits[] = $lab . ' : ' . $v;
            }
        }

        return implode(' · ', $bits);
    }

    private static function parseCoord(mixed $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_int($raw) || is_float($raw)) {
            $f = (float) $raw;

            return is_finite($f) ? $f : null;
        }
        $s = trim(str_replace(',', '.', (string) $raw));
        if ($s === '' || !is_numeric($s)) {
            return null;
        }
        $f = (float) $s;

        return is_finite($f) ? $f : null;
    }

    /**
     * Parse « REGLAGES AFFICHAGE|adversaire=1|independants=1|civils=1 »
     *
     * @return array{show_east: bool, show_guer: bool, show_civ: bool}|null
     */
    public static function parseFactionSettings(?string $body): ?array
    {
        $body = trim((string) $body);
        if ($body === '') {
            return null;
        }
        $body = self::stripCommsPrefix($body);
        $upper = mb_strtoupper($body);
        if (!str_starts_with($upper, 'REGLAGES AFFICHAGE')) {
            return null;
        }

        $showEast = true;
        $showGuer = true;
        $showCiv = true;
        foreach (explode('|', $body) as $chunk) {
            $chunk = trim($chunk);
            if (!str_contains($chunk, '=')) {
                continue;
            }
            [$k, $v] = array_map('trim', explode('=', $chunk, 2));
            $k = mb_strtolower($k);
            $on = in_array($v, ['1', 'true', 'oui', 'yes'], true);
            if (in_array($k, ['adversaire', 'east', 'opfor', 'showeast'], true)) {
                $showEast = $on;
            }
            if (in_array($k, ['independants', 'guer', 'independent', 'showguer'], true)) {
                $showGuer = $on;
            }
            if (in_array($k, ['civils', 'civ', 'civilian', 'showciv'], true)) {
                $showCiv = $on;
            }
        }

        return [
            'show_east' => $showEast,
            'show_guer' => $showGuer,
            'show_civ' => $showCiv,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    public static function enrichChatRow(array $row): ?array
    {
        $parsed = self::parse(isset($row['body']) ? (string) $row['body'] : null);
        if ($parsed === null) {
            return null;
        }

        return array_merge($parsed, [
            'id' => (int) ($row['id'] ?? 0),
            'author' => (string) ($row['author'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'map_id' => (int) ($row['map_id'] ?? 0),
        ]);
    }

    public static function kindLabelFr(string $kind): string
    {
        return match (self::normalizeKind($kind)) {
            'tic' => 'Contact',
            'tic_clear' => 'Fin de contact',
            'frago' => 'Ordre fragmentaire',
            'salute' => 'Compte rendu SALUTE',
            'eagle_down' => 'Opérateur à terre',
            'bda' => 'Bilan des dégâts',
            default => 'Alerte',
        };
    }

    public static function severityForKind(string $kind): string
    {
        return match (self::normalizeKind($kind)) {
            'eagle_down', 'tic' => 'critical',
            'frago', 'bda' => 'high',
            'salute' => 'medium',
            'tic_clear' => 'info',
            default => 'medium',
        };
    }

    public static function isWithinActiveWindow(?string $createdAt): bool
    {
        if ($createdAt === null || $createdAt === '') {
            return true;
        }
        $ts = strtotime($createdAt);
        if ($ts === false) {
            return true;
        }

        return (time() - $ts) <= self::ACTIVE_WINDOW_SECONDS;
    }

    public static function normalizeKind(string $raw): string
    {
        $k = strtoupper(trim(str_replace([' ', '-'], '_', $raw)));
        return match ($k) {
            'TIC' => 'tic',
            'CLEAR', 'TIC_CLEAR', 'TICCLEAR' => 'tic_clear',
            'FRAGO' => 'frago',
            'SALUTE' => 'salute',
            'EAGLE_DOWN', 'EAGLEDOWN', 'PANIC' => 'eagle_down',
            'BDA', 'BDA_REPORT', 'BDAREPORT' => 'bda',
            default => 'tic',
        };
    }

    private static function stripCommsPrefix(string $body): string
    {
        if (preg_match(
            '/^\[\d{1,2}:\d{2}:\d{2}\]\[[A-Za-z0-9_]+\]\[[A-Za-z0-9_]+\]\[[A-Za-z0-9_]+\]\s*([\s\S]+)$/u',
            $body,
            $m
        )) {
            return trim((string) ($m[1] ?? $body));
        }

        return $body;
    }
}
