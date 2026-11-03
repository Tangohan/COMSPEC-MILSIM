<?php

declare(strict_types=1);

namespace App\Services\Academy;

/**
 * Parcours sur une ancienne version d’un cours (copie state_json.snapshot antérieure au cours intégré courant, ou
 * cours du Studio remplacé par une nouvelle version) : détection pour le badge « Ancienne version » et passage à la
 * version courante à la demande, progression et validations gardées.
 *
 * Un parcours terminé reste valide : sa licence n’est jamais révoquée (gardée dans `license` et copiée dans
 * `license_history`). Le passage ouvre la nouvelle version en complément : les chapitres déjà couverts (même titre)
 * restent acquis, les nouveaux chapitres sont à suivre.
 */
final class AcademyVersionUpgrade
{
    /**
     * Version courante de chaque cours suivi : cours intégré (même clé) ou dernière version publiée du Studio
     * (lignée `replaces`).
     *
     * @param list<array<string, mixed>> $courses cours publiés
     * @param array<string, list<string>> $lineage clé d’une version => clés des versions qu’elle remplace
     * @return array<string, array<string, mixed>> clé suivie => cours courant
     */
    public static function currentMap(array $courses, array $lineage): array
    {
        $map = [];
        foreach ($courses as $course) {
            if (empty($course['published']) && empty($course['built_in'])) {
                continue;
            }
            $key = (string) ($course['key'] ?? '');
            $map[$key] = $course;
            foreach ($lineage[$key] ?? [] as $older) {
                $map[(string) $older] = $course;
            }
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed>|null $current
     * @return array{from: string, to: string, key: string, new: int, removed: int, note: string, completed: bool}|null
     */
    public static function outdated(array $state, string $key, ?array $current): ?array
    {
        $snapshot = $state['snapshot'] ?? null;
        if ($current === null || !is_array($snapshot) || !is_array($current['chapters'] ?? null)) {
            return null;
        }
        $from = (string) ($snapshot['version'] ?? '1.0');
        $to = (string) ($current['version'] ?? '1.0');
        $currentKey = (string) ($current['key'] ?? $key);
        $newer = $currentKey !== $key || ($from !== $to && version_compare($from, $to, '<'));
        if (!$newer) {
            return null;
        }
        $old = array_map(self::identity(...), $snapshot['chapters'] ?? []);
        $new = array_map(self::identity(...), $current['chapters']);
        $note = $current['version_note'] ?? null;

        return [
            'from' => $from, 'to' => $to, 'key' => $currentKey,
            'new' => count(array_diff($new, $old)), 'removed' => count(array_diff($old, $new)),
            'note' => is_array($note) ? (string) ($note[locale() === 'en' ? 'en' : 'fr'] ?? ($note['fr'] ?? '')) : (is_string($note) ? $note : ''),
            'completed' => !empty($state['completed']),
        ];
    }

    /**
     * Passe le parcours sur la version courante. Chapitres repérés par leur titre : validations, débriefings et
     * tentatives suivent leur chapitre ; le stagiaire reprend au chapitre en cours (même étape) ou au premier
     * chapitre non acquis. La licence n’est jamais retirée.
     *
     * @param array<string, mixed> $state
     * @param array<string, mixed> $current
     * @return array<string, mixed>
     */
    public static function upgrade(array $state, array $current, int $by, int $now): array
    {
        $old = $state['snapshot']['chapters'] ?? [];
        $newIndex = [];
        foreach ($current['chapters'] as $index => $chapter) {
            $newIndex[self::identity($chapter)] ??= $index;
        }
        $map = [];
        foreach ($old as $index => $chapter) {
            if (isset($newIndex[self::identity($chapter)])) {
                $map[$index] = $newIndex[self::identity($chapter)];
            }
        }
        $completed = !empty($state['completed']);
        $validations = [];
        foreach ($state['validations'] ?? [] as $index => $validation) {
            if (isset($map[$index])) {
                $validations[$map[$index]] = $validation + ($completed ? ['carried_from' => (string) ($state['snapshot']['version'] ?? '1.0')] : []);
            }
        }
        $debriefs = [];
        foreach ($state['debriefs'] ?? [] as $index => $debrief) {
            if (isset($map[$index])) {
                $debriefs[$map[$index]] = $debrief;
            }
        }
        $attempts = [];
        foreach ($state['attempts'] ?? [] as $attempt) {
            if (isset($map[$attempt['chapter'] ?? -1])) {
                $attempts[] = ['chapter' => $map[$attempt['chapter']]] + $attempt;
            }
        }
        $chapter = (int) ($state['chapter'] ?? 0);
        $stage = (int) ($state['stage'] ?? 0);
        if (!$completed && isset($map[$chapter]) && !isset($validations[$map[$chapter]])) {
            $chapter = $map[$chapter];
        } else {
            $chapter = AcademyCurriculum::nextUnvalidated($validations, count($current['chapters'])) ?? max(0, count($current['chapters']) - 1);
            $stage = 0;
        }
        if (!empty($state['license'])) {
            $state['license_history'][] = $state['license'];
        }
        $state['upgrades'][] = ['from' => (string) ($state['snapshot']['version'] ?? '1.0'), 'to' => (string) ($current['version'] ?? '1.0'), 'by' => $by, 'at' => $now, 'completed' => $completed];
        unset($state['exam'], $state['feedback'], $state['last_practice_feedback']);
        $state['snapshot'] = $current;
        $state['validations'] = $validations;
        $state['debriefs'] = $debriefs;
        $state['attempts'] = $attempts;
        $state['chapter'] = $chapter;
        $state['stage'] = $stage;
        $state['completed'] = AcademyCurriculum::nextUnvalidated($validations, count($current['chapters'])) === null;
        $state['updated'] = $now;

        return $state;
    }

    /** @param array<string, mixed> $chapter */
    private static function identity(mixed $chapter): string
    {
        $title = is_array($chapter) ? ($chapter['title']['fr'] ?? ($chapter['title'] ?? '')) : '';

        return mb_strtolower(trim(is_string($title) ? $title : ''));
    }
}
