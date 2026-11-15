<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Motifs de capture biométrique envoyés par le SEEK II (champs facultatifs, jamais crus sur parole) : qualité et détail
 * par modalité (empreintes, iris), motifs lisibles (« main droite : non capturable (garrot posé) », « relevé post-mortem »…).
 * Tout est borné et nettoyé : texte brut sans balise ni caractère de contrôle, au plus 8 motifs de 120 caractères, au plus
 * 10 valeurs de détail entières de -2 à 100.
 */
final class SseCaptureNotes
{
    public const MODALITIES = ['empreintes', 'iris', 'visage'];
    public const MAX_NOTES = 8;
    public const NOTE_LENGTH = 120;
    public const MAX_DETAIL = 10;

    /** @return list<string> */
    public static function notes(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $note) {
            if (!is_string($note)) {
                continue;
            }
            $clean = trim((string) preg_replace('/[\x00-\x1F\x7F<>]+/u', ' ', strip_tags($note)));
            $clean = mb_substr($clean, 0, self::NOTE_LENGTH);
            if ($clean !== '' && !in_array($clean, $out, true)) {
                $out[] = $clean;
            }
            if (count($out) >= self::MAX_NOTES) {
                break;
            }
        }

        return $out;
    }

    /** @return list<int> */
    public static function detail(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach (array_slice(array_values($raw), 0, self::MAX_DETAIL) as $v) {
            if (is_int($v) || is_float($v) || (is_string($v) && is_numeric($v))) {
                $out[] = (int) max(-2, min(100, (int) round((float) $v)));
            }
        }

        return $out;
    }

    /**
     * Champ « capture » d'une fiche SEEK II : { empreintes: {quality, detail, notes}, iris: {...} }.
     *
     * @return array<string, array{quality: int|null, detail: list<int>, notes: list<string>}>
     */
    public static function forSeek(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach (self::MODALITIES as $mod) {
            $entry = $raw[$mod] ?? null;
            if (!is_array($entry)) {
                continue;
            }
            $q = $entry['quality'] ?? null;
            $row = [
                'quality' => is_numeric($q) ? max(0, min(100, (int) round((float) $q))) : null,
                'detail' => self::detail($entry['detail'] ?? null),
                'notes' => self::notes($entry['notes'] ?? null),
            ];
            if ($row['quality'] !== null || $row['detail'] !== [] || $row['notes'] !== []) {
                $out[$mod] = $row;
            }
        }

        return $out;
    }

    /** JSON stocké (null si rien à garder). */
    public static function encode(mixed $raw): ?string
    {
        $clean = self::forSeek($raw);

        return $clean === [] ? null : (string) json_encode($clean, JSON_UNESCAPED_UNICODE);
    }

    /** @return array<string, array{quality: int|null, detail: list<int>, notes: list<string>}> */
    public static function decode(mixed $json): array
    {
        if (!is_string($json) || $json === '') {
            return [];
        }
        $data = json_decode($json, true);

        return self::forSeek($data);
    }

    /** Libellé court d'une modalité pour l'affichage. */
    public static function label(string $modality): string
    {
        return match ($modality) {
            'empreintes' => 'Empreintes',
            'iris' => 'Iris',
            'visage' => 'Visage',
            default => $modality,
        };
    }
}
