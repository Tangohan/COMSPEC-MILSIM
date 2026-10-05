<?php

declare(strict_types=1);

namespace App\Services\Intel;

use App\Repositories\SseCaseRepository;
use App\Services\Sse\SseRedactionService;

/**
 * Caviardage de passages d'une remontée (compte rendu ou fiche de renseignement).
 *
 * Un passage caviardé est une phrase exacte (casse ignorée) d'un champ. Il reste lisible en clair :
 *  - à partir de son niveau d'habilitation (échelle SSE : interne < encadrement < confidentiel < très restreint),
 *  - ou par les membres nommés sur le caviardage.
 * Tous les autres lecteurs (portail SSE, téléphone en jeu, back-office) voient une barre ███.
 */
final class IntelRedaction
{
    /** Champs caviardables par source. */
    public const FIELDS = [
        'fiche' => ['title' => 'Titre', 'body' => 'Texte', 'place_label' => 'Lieu'],
        'cr' => ['summary' => 'Résumé', 'details' => 'Détails', 'remarks' => 'Remarques', 'location_description' => 'Lieu'],
    ];

    /**
     * Le lecteur peut-il lire ce passage en clair ?
     *
     * @param array<string, mixed> $redaction
     */
    public static function readable(array $redaction, string $viewerLevel, int $viewerUserId): bool
    {
        $allowed = self::allowedIds($redaction['allowed_user_ids'] ?? []);
        if ($viewerUserId > 0 && in_array($viewerUserId, $allowed, true)) {
            return true;
        }
        $need = (string) ($redaction['clear_level'] ?? SseCaseRepository::CLASS_RESTRICTED);
        if (!isset(SseRedactionService::LEVELS[$need])) {
            $need = SseCaseRepository::CLASS_RESTRICTED;
        }
        $have = SseRedactionService::LEVELS[$viewerLevel] ?? 0;

        return $have >= SseRedactionService::LEVELS[$need];
    }

    /**
     * @return list<int>
     */
    public static function allowedIds(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $raw), static fn (int $id): bool => $id > 0)));
    }

    /**
     * Découpe un texte en morceaux : [texte, caviardage qui le couvre ou null].
     *
     * @param list<array<string, mixed>> $redactions déjà filtrés sur le champ
     * @return list<array{0: string, 1: array<string, mixed>|null}>
     */
    public static function split(string $text, array $redactions): array
    {
        $phrases = [];
        foreach ($redactions as $r) {
            $p = trim((string) ($r['phrase'] ?? ''));
            if ($p !== '') {
                $phrases[] = [$p, $r];
            }
        }
        if ($text === '' || $phrases === []) {
            return [[$text, null]];
        }
        // Les plus longues d'abord : une phrase qui en contient une autre l'emporte.
        usort($phrases, static fn (array $a, array $b): int => mb_strlen($b[0]) <=> mb_strlen($a[0]));
        $out = [];
        $offset = 0;
        $len = mb_strlen($text);
        while ($offset < $len) {
            $best = null;
            foreach ($phrases as [$p, $r]) {
                $pos = mb_stripos($text, $p, $offset);
                if ($pos !== false && ($best === null || $pos < $best[0])) {
                    $best = [$pos, mb_strlen($p), $r];
                }
            }
            if ($best === null) {
                $out[] = [mb_substr($text, $offset), null];
                break;
            }
            if ($best[0] > $offset) {
                $out[] = [mb_substr($text, $offset, $best[0] - $offset), null];
            }
            $out[] = [mb_substr($text, $best[0], $best[1]), $best[2]];
            $offset = $best[0] + $best[1];
        }

        return $out;
    }

    /**
     * Texte tel que ce lecteur a le droit de le lire.
     *
     * @param list<array<string, mixed>> $redactions déjà filtrés sur le champ
     */
    public static function apply(string $text, array $redactions, string $viewerLevel, int $viewerUserId): string
    {
        $s = '';
        foreach (self::split($text, $redactions) as [$chunk, $r]) {
            $s .= ($r === null || self::readable($r, $viewerLevel, $viewerUserId)) ? $chunk : SseRedactionService::bar($chunk);
        }

        return $s;
    }

    /**
     * Applique tous les caviardages d'une remontée à sa ligne (champs connus de la source).
     *
     * @param array<string, mixed> $row
     * @param list<array<string, mixed>> $redactions de cette remontée
     * @return array<string, mixed>
     */
    public static function applyRow(string $source, array $row, array $redactions, string $viewerLevel, int $viewerUserId): array
    {
        if ($redactions === []) {
            return $row;
        }
        foreach (array_keys(self::FIELDS[$source] ?? []) as $field) {
            if (!isset($row[$field]) || !is_string($row[$field])) {
                continue;
            }
            $mine = array_values(array_filter($redactions, static fn (array $r): bool => ($r['field'] ?? 'body') === $field));
            if ($mine !== []) {
                $row[$field] = self::apply($row[$field], $mine, $viewerLevel, $viewerUserId);
            }
        }

        return $row;
    }
}
