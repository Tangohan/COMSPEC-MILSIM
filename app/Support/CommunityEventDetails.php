<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Enrichissements d’un créneau (déroulement, étiquettes, image) — côté affichage métier.
 */
final class CommunityEventDetails
{
    /** @return array<string, string> code => libellé */
    public static function tagOptions(): array
    {
        return [
            'sandbox' => 'Sandbox',
            'drill' => 'Drill',
            'evaluation' => 'Évaluation des compétences',
            'mise_a_niveau' => 'Mise à niveau',
            'sot' => 'SOT',
            'briefing' => 'Briefing',
            'operation' => 'Opération',
            'formation' => 'Formation',
        ];
    }

    /** @return array<string, string> tone => libellé */
    public static function scheduleToneOptions(): array
    {
        return [
            'red' => 'Rouge — regroupement / critique',
            'orange' => 'Orange — briefing',
            'yellow' => 'Jaune — préparation',
            'green' => 'Vert — top action',
            'black' => 'Noir — créneau différé',
            'white' => 'Blanc — équipement',
            'gray' => 'Gris — info',
        ];
    }

    public static function tagLabel(string $code): string
    {
        $opts = self::tagOptions();

        return $opts[$code] ?? $code;
    }

    /**
     * @param mixed $raw JSON string ou array
     * @return list<string>
     */
    public static function decodeTags(mixed $raw): array
    {
        $allowed = array_keys(self::tagOptions());
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $item) {
            $code = is_string($item) ? trim($item) : '';
            if ($code !== '' && in_array($code, $allowed, true) && !in_array($code, $out, true)) {
                $out[] = $code;
            }
        }

        return $out;
    }

    /**
     * @param mixed $raw
     * @return list<array{type:string,tone:?string,label:string,time:?string}>
     */
    public static function decodeSchedule(mixed $raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            return [];
        }
        $tones = array_keys(self::scheduleToneOptions());
        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $type = (string) ($row['type'] ?? 'phase');
            if ($type === 'section') {
                $label = trim((string) ($row['label'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $out[] = ['type' => 'section', 'tone' => null, 'label' => $label, 'time' => null];
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $tone = (string) ($row['tone'] ?? 'gray');
            if (!in_array($tone, $tones, true)) {
                $tone = 'gray';
            }
            $time = trim((string) ($row['time'] ?? ''));
            $out[] = [
                'type' => 'phase',
                'tone' => $tone,
                'label' => $label,
                'time' => $time !== '' ? $time : null,
            ];
        }

        return $out;
    }

    /**
     * @param list<string>|array<int|string, mixed> $tags
     */
    public static function encodeTags(array $tags): ?string
    {
        $clean = self::decodeTags($tags);

        return $clean === [] ? null : json_encode($clean, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public static function encodeSchedule(array $rows): ?string
    {
        $clean = self::decodeSchedule($rows);

        return $clean === [] ? null : json_encode($clean, JSON_UNESCAPED_UNICODE);
    }

    public static function publicCoverUrl(?string $relativePath): ?string
    {
        $relativePath = trim((string) $relativePath);
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            return null;
        }
        $norm = str_replace('\\', '/', $relativePath);
        if (!str_starts_with($norm, 'uploads/community-events/')) {
            return null;
        }

        return asset_url($norm);
    }

    /**
     * Parse les champs POST admin (étiquettes + déroulement).
     *
     * @return array{tags_json:?string, schedule_json:?string, conditions_general:?string, conditions_special:?string}
     */
    public static function fromRequestInput(callable $input): array
    {
        $tagsRaw = $input('event_tags');
        $tags = [];
        if (is_array($tagsRaw)) {
            foreach ($tagsRaw as $t) {
                if (is_string($t) || is_int($t)) {
                    $tags[] = (string) $t;
                }
            }
        }

        $schedule = [];
        $labels = $input('schedule_label');
        $times = $input('schedule_time');
        $tones = $input('schedule_tone');
        $types = $input('schedule_type');
        if (is_array($labels)) {
            $n = count($labels);
            for ($i = 0; $i < $n; $i++) {
                $label = trim((string) ($labels[$i] ?? ''));
                if ($label === '') {
                    continue;
                }
                $type = is_array($types) ? (string) ($types[$i] ?? 'phase') : 'phase';
                if ($type === 'section') {
                    $schedule[] = ['type' => 'section', 'label' => $label];
                    continue;
                }
                $schedule[] = [
                    'type' => 'phase',
                    'label' => $label,
                    'time' => is_array($times) ? trim((string) ($times[$i] ?? '')) : '',
                    'tone' => is_array($tones) ? (string) ($tones[$i] ?? 'gray') : 'gray',
                ];
            }
        }

        $cg = trim((string) $input('conditions_general', ''));
        $cs = trim((string) $input('conditions_special', ''));

        return [
            'tags_json' => self::encodeTags($tags),
            'schedule_json' => self::encodeSchedule($schedule),
            'conditions_general' => $cg !== '' ? $cg : null,
            'conditions_special' => $cs !== '' ? $cs : null,
        ];
    }
}
