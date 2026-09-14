<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\ArmaMarkerLabel;

/**
 * Règles communautaires : reconnaître un marqueur posé en jeu.
 */
final class AtakMarkerDetection
{
    public const MATCH_LABEL_PREFIX = 'label_prefix';
    public const MATCH_LABEL_EQUALS = 'label_equals';
    public const MATCH_LABEL_CONTAINS = 'label_contains';
    public const MATCH_MARKER_TYPE = 'marker_type';

    public const RADII_M = [10, 20, 50, 100, 200, 500];

    /**
     * @return array<string, string>
     */
    public static function matchModes(): array
    {
        return [
            self::MATCH_LABEL_PREFIX => 'Le libellé commence par',
            self::MATCH_LABEL_EQUALS => 'Le libellé est exactement',
            self::MATCH_LABEL_CONTAINS => 'Le libellé contient',
            self::MATCH_MARKER_TYPE => 'Le symbole est',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function markerTypes(): array
    {
        return [
            'mil_dot' => 'Point',
            'mil_objective' => 'Objectif',
            'mil_warning' => 'Avertissement',
            'mil_destroy' => 'Détruire',
            'mil_circle' => 'Cercle',
            'mil_unknown' => 'Inconnu',
            'mil_marker' => 'Repère',
            'mil_flag' => 'Drapeau',
            'mil_pickup' => 'Ramassage',
            'mil_start' => 'Départ',
            'mil_end' => 'Arrivée',
            'mil_join' => 'Jonction',
            'hd_warning' => 'Danger',
            'hd_objective' => 'Objectif (HD)',
            'hd_dot' => 'Point (HD)',
            'b_inf' => 'Infanterie amie',
            'o_inf' => 'Infanterie hostile',
            'n_inf' => 'Infanterie inconnue',
        ];
    }

    public static function normalizeMode(string $mode): string
    {
        $mode = strtolower(trim($mode));

        return array_key_exists($mode, self::matchModes()) ? $mode : self::MATCH_LABEL_PREFIX;
    }

    public static function normalizeRadius(int $radius): int
    {
        if (in_array($radius, self::RADII_M, true)) {
            return $radius;
        }

        return 20;
    }

    /**
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $data
     */
    public static function matches(array $rule, array $data, string $armaName = ''): bool
    {
        $mode = self::normalizeMode((string) ($rule['match_mode'] ?? ''));
        $needle = trim((string) ($rule['match_value'] ?? ''));
        if ($needle === '') {
            return false;
        }
        $label = AtakPoMarker::labelOf($data, $armaName);
        if ($label === '') {
            $label = ArmaMarkerLabel::displayLabel($armaName, $data);
        }
        $type = strtolower(trim(str_replace([' ', '-'], '_', (string) ($data['type'] ?? $data['icon'] ?? ''))));

        return match ($mode) {
            self::MATCH_LABEL_EQUALS => mb_strtolower($label) === mb_strtolower($needle),
            self::MATCH_LABEL_CONTAINS => $label !== '' && mb_stripos($label, $needle) !== false,
            self::MATCH_MARKER_TYPE => $type !== '' && $type === strtolower(str_replace([' ', '-'], '_', $needle)),
            default => $label !== '' && mb_stripos($label, $needle) === 0,
        };
    }

    /**
     * @param list<array<string, mixed>> $rules
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public static function firstMatch(array $rules, array $data, string $armaName = ''): ?array
    {
        foreach ($rules as $rule) {
            if (empty($rule['is_active'])) {
                continue;
            }
            if (self::matches($rule, $data, $armaName)) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $rule
     * @return array<string, mixed>
     */
    public static function annotate(array $data, array $rule): array
    {
        $radius = self::normalizeRadius((int) ($rule['radius_m'] ?? 20));
        $data['detection'] = true;
        $data['detection_rule_id'] = (int) ($rule['id'] ?? 0);
        $data['detection_label'] = trim((string) ($rule['label'] ?? 'Marqueur suivi'));
        $data['detection_radius_m'] = $radius;
        $data['detection_confirm'] = !empty($rule['confirm_arrival']);
        if (empty($data['po']) && !empty($rule['confirm_arrival'])) {
            $data['po_radius_m'] = $data['po_radius_m'] ?? $radius;
        }

        return $data;
    }
}
