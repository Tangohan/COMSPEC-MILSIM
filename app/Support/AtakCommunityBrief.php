<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Consignes, ordres, tips et kit obligatoire de la communauté, lus par l'app Communauté du téléphone
 * (GET /api/atak/community-brief, commande GetCommunityBrief de COMSPEC Link).
 *
 * Saisis en texte dans Ressources › Configuration ATAK et rangés avec l'expérience de la communauté
 * (community_notes, community_kit). Une ligne par note : « genre | titre | texte ». Kit : une entrée par ligne
 * ou séparée par une virgule : « classe », « classe*2 », « classeA/classeB*2 », suivie de « : libellé » au besoin.
 * Texte brut seulement : le jeu l'affiche sans interpréter de balise.
 */
final class AtakCommunityBrief
{
    public const KINDS = ['consigne', 'ordre', 'tip'];

    public const MAX_NOTES = 40;

    public const MAX_TITLE = 60;

    public const MAX_BODY = 600;

    public const MAX_KIT = 40;

    public const MAX_COUNT = 99;

    /** Taille du texte gardé pour chaque champ de saisie. */
    public const MAX_SOURCE = 30000;

    /**
     * @param array<string, mixed> $settings
     * @return array{notes: list<array{id: int, kind: string, title: string, body: string, updated_at: string}>, kit: list<array{classes: list<string>, count: int, label: string}>, updated_at: string}
     */
    public static function payload(array $settings, string $updatedAt): array
    {
        $notes = [];
        foreach (self::parseNotes((string) ($settings['community_notes'] ?? '')) as $i => $note) {
            $notes[] = ['id' => $i + 1] + $note + ['updated_at' => $updatedAt];
        }

        return [
            'notes' => $notes,
            'kit' => self::parseKit((string) ($settings['community_kit'] ?? '')),
            'updated_at' => $updatedAt,
        ];
    }

    /**
     * @return list<array{kind: string, title: string, body: string}>
     */
    public static function parseNotes(string $text): array
    {
        $out = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $parts = array_map(self::clean(...), explode('|', $line));
            $kind = 'consigne';
            if (count($parts) > 1 && in_array(mb_strtolower($parts[0]), self::KINDS, true)) {
                $kind = mb_strtolower((string) array_shift($parts));
            }
            $title = count($parts) > 1 ? (string) array_shift($parts) : '';
            $body = trim(implode(' | ', $parts));
            if ($title === '' && $body === '') {
                continue;
            }
            $out[] = ['kind' => $kind, 'title' => mb_substr($title, 0, self::MAX_TITLE), 'body' => mb_substr($body, 0, self::MAX_BODY)];
            if (count($out) >= self::MAX_NOTES) {
                break;
            }
        }

        return $out;
    }

    /**
     * @return list<array{classes: list<string>, count: int, label: string}>
     */
    public static function parseKit(string $text): array
    {
        $out = [];
        foreach (preg_split('/[\r\n,;]+/u', $text) ?: [] as $entry) {
            [$spec, $label] = array_pad(explode(':', $entry, 2), 2, '');
            $spec = trim($spec);
            $count = 1;
            if (preg_match('/^(.*)\*\s*(\d+)$/u', $spec, $m) === 1) {
                $spec = trim($m[1]);
                $count = max(1, min(self::MAX_COUNT, (int) $m[2]));
            }
            $classes = array_map('trim', explode('/', $spec));
            foreach ($classes as $class) {
                if (preg_match('/^[A-Za-z0-9_]{1,64}$/', $class) !== 1) {
                    continue 2;
                }
            }
            $out[] = ['classes' => $classes, 'count' => $count, 'label' => mb_substr(self::clean($label), 0, self::MAX_TITLE)];
            if (count($out) >= self::MAX_KIT) {
                break;
            }
        }

        return $out;
    }

    private static function clean(string $value): string
    {
        return trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', strip_tags($value)));
    }
}
