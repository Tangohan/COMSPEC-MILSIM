<?php

declare(strict_types=1);

namespace App\Services\Sse;

/**
 * Regroupement automatique de « À trier » : les éléments de même type (ou de même sujet, source, ancienneté) tiennent en une ligne
 * dépliable « Photographie manquante · 34 dossiers » au lieu de 34 lignes. Pur : prend la liste déjà triée par priorité, rend des groupes
 * triés par leur élément le plus prioritaire.
 */
final class SseTriageGrouping
{
    public const MODES = ['type' => 'Type', 'sujet' => 'Sujet', 'source' => 'Source', 'anciennete' => 'Ancienneté'];

    /** Seuil d'âge (jours) → libellé, du plus récent au plus ancien. */
    public const AGE_BUCKETS = [
        ['max' => 1, 'key' => 'a0', 'title' => 'Aujourd’hui et hier'],
        ['max' => 7, 'key' => 'a1', 'title' => 'De 2 à 7 jours'],
        ['max' => 30, 'key' => 'a2', 'title' => 'De 8 à 30 jours'],
        ['max' => PHP_INT_MAX, 'key' => 'a3', 'title' => 'Plus de 30 jours'],
    ];

    public static function mode(string $requested): string
    {
        return isset(self::MODES[$requested]) ? $requested : 'type';
    }

    /**
     * @param list<array<string, mixed>> $items triés par priorité décroissante
     * @return list<array{key: string, mode: string, title: string, noun: string, count: int, items: list<array<string, mixed>>, oldest_days: int, top_score: int, reason: string, tone: string}>
     */
    public static function group(array $items, string $mode, int $now): array
    {
        $mode = self::mode($mode);
        $groups = [];
        foreach ($items as $it) {
            $days = SseTriageInbox::ageDays((string) ($it['at'] ?? ''), $now);
            [$key, $title] = match ($mode) {
                'sujet' => isset($it['subject']['key'])
                    ? [(string) $it['subject']['key'], (string) $it['subject']['label']]
                    : ['sans-sujet', 'Sans sujet identifié'],
                'source' => [(string) ($it['source'] ?? ''), (string) ($it['source_label'] ?? '')],
                'anciennete' => self::bucket($days),
                default => [(string) ($it['type_key'] ?? $it['source'] ?? ''), (string) ($it['type_label'] ?? '')],
            };
            $groups[$key] ??= ['key' => $key, 'mode' => $mode, 'title' => $title, 'noun' => '', 'count' => 0, 'items' => [], 'oldest_days' => 0, 'top_score' => 0, 'reason' => '', 'tone' => 'neutral'];
            $groups[$key]['items'][] = $it;
            $groups[$key]['count']++;
            $groups[$key]['oldest_days'] = max($groups[$key]['oldest_days'], $days);
            $groups[$key]['top_score'] = max($groups[$key]['top_score'], (int) ($it['score'] ?? 0));
        }
        foreach ($groups as &$g) {
            $g['noun'] = $mode === 'type' ? self::noun($g['items']) : 'élément';
            $g['reason'] = self::commonReason($g['items']);
            $g['tone'] = SseTriageInbox::tone($g['items'], $now);
        }
        unset($g);
        $out = array_values($groups);
        usort($out, static fn (array $a, array $b): int => ($b['top_score'] <=> $a['top_score']) ?: strcmp($a['title'], $b['title']));

        return $out;
    }

    /** @return array{0: string, 1: string} */
    private static function bucket(int $days): array
    {
        foreach (self::AGE_BUCKETS as $b) {
            if ($days <= $b['max']) {
                return [$b['key'], $b['title']];
            }
        }

        return ['a3', 'Plus de 30 jours'];
    }

    /**
     * Ce que les éléments du groupe comptent : dossiers, identités, ou éléments.
     *
     * @param list<array<string, mixed>> $items
     */
    private static function noun(array $items): string
    {
        $kinds = [];
        foreach ($items as $it) {
            $kinds[(string) ($it['subject']['kind'] ?? '')] = true;
        }
        if (count($kinds) === 1) {
            return match (array_key_first($kinds)) {
                'case' => 'dossier',
                'identity' => 'identité',
                default => 'élément',
            };
        }

        return 'élément';
    }

    /**
     * Raison la plus fréquente du groupe (celle qui le décrit) : vide si les éléments n'en portent pas.
     *
     * @param list<array<string, mixed>> $items
     */
    private static function commonReason(array $items): string
    {
        $count = [];
        foreach ($items as $it) {
            $r = trim((string) ($it['reason'] ?? ''));
            if ($r !== '') {
                $count[$r] = ($count[$r] ?? 0) + 1;
            }
        }
        if ($count === []) {
            return '';
        }
        arsort($count);

        return (string) array_key_first($count);
    }

    /** « 34 dossiers », « 1 identité » : à passer au dictionnaire (modèles « %1 dossiers »). */
    public static function countLabel(int $count, string $noun): string
    {
        $plural = match ($noun) {
            'dossier' => 'dossiers',
            'identité' => 'identités',
            default => 'éléments',
        };

        return $count . ' ' . ($count === 1 ? $noun : $plural);
    }
}
