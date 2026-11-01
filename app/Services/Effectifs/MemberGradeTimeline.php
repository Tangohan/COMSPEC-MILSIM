<?php

declare(strict_types=1);

namespace App\Services\Effectifs;

/**
 * Frise des grades d’un membre.
 *
 * Deux sources :
 * - les changements enregistrés depuis la mise en place de l’historique (member_grade_changes),
 *   avec grade avant / après ;
 * - la reconstitution de l’existant, à partir des traces déjà présentes (journal des modifications
 *   « grade » de la fiche, demandes d’élévation acceptées avec un grade proposé). Ces traces ne
 *   donnent que le grade obtenu : le grade précédent n’est déduit que d’une trace antérieure,
 *   jamais inventé. Seules les traces antérieures au premier changement enregistré sont reprises,
 *   pour ne pas compter deux fois un même changement.
 */
final class MemberGradeTimeline
{
    /**
     * @param list<array<string, mixed>> $recorded lignes member_grade_changes (ordre quelconque)
     * @param list<array{date:string,to_grade_id:?int,source:string,actor_label?:string}> $derived traces reconstituées
     * @param array<int, string> $gradeLabels libellés des grades de la communauté
     * @return array{entries: list<array{date:string,from_grade_id:?int,to_grade_id:?int,from_label:string,to_label:string,from_known:bool,source:string,actor_label:string,reconstructed:bool}>, current_since: ?string}
     */
    public static function build(array $recorded, array $derived, ?int $currentGradeId, array $gradeLabels): array
    {
        $label = static function (?int $gradeId, ?string $snapshot) use ($gradeLabels): string {
            $snapshot = trim((string) $snapshot);
            if ($snapshot !== '') {
                return $snapshot;
            }
            if ($gradeId === null) {
                return 'Sans grade';
            }

            return $gradeLabels[$gradeId] ?? 'Grade retiré du référentiel';
        };
        $intOrNull = static fn (mixed $v): ?int => $v === null || $v === '' || (int) $v < 1 ? null : (int) $v;

        $live = [];
        foreach ($recorded as $row) {
            $date = trim((string) ($row['changed_at'] ?? ''));
            if ($date === '') {
                continue;
            }
            $from = $intOrNull($row['from_grade_id'] ?? null);
            $to = $intOrNull($row['to_grade_id'] ?? null);
            $live[] = [
                'date' => $date,
                'from_grade_id' => $from,
                'to_grade_id' => $to,
                'from_label' => $label($from, isset($row['from_label']) ? (string) $row['from_label'] : null),
                'to_label' => $label($to, isset($row['to_label']) ? (string) $row['to_label'] : null),
                'from_known' => true,
                'source' => (string) ($row['source'] ?? 'manual'),
                'actor_label' => trim((string) ($row['actor_display_name'] ?? $row['actor_label'] ?? '')),
                'reconstructed' => false,
            ];
        }
        usort($live, static fn (array $a, array $b): int => strcmp($a['date'], $b['date']));
        $firstLive = $live[0]['date'] ?? null;

        usort($derived, static fn (array $a, array $b): int => strcmp((string) $a['date'], (string) $b['date']));
        $rebuilt = [];
        $previousTo = null;
        $hasPrevious = false;
        foreach ($derived as $trace) {
            $date = trim((string) ($trace['date'] ?? ''));
            if ($date === '' || ($firstLive !== null && strcmp($date, $firstLive) >= 0)) {
                continue;
            }
            $to = $intOrNull($trace['to_grade_id'] ?? null);
            if ($hasPrevious && $previousTo === $to) {
                continue;
            }
            $rebuilt[] = [
                'date' => $date,
                'from_grade_id' => $hasPrevious ? $previousTo : null,
                'to_grade_id' => $to,
                'from_label' => $hasPrevious ? $label($previousTo, null) : '',
                'to_label' => $label($to, null),
                'from_known' => $hasPrevious,
                'source' => (string) ($trace['source'] ?? 'manual'),
                'actor_label' => trim((string) ($trace['actor_label'] ?? '')),
                'reconstructed' => true,
            ];
            $previousTo = $to;
            $hasPrevious = true;
        }

        $entries = array_merge($rebuilt, $live);
        $currentSince = null;
        // Date d’obtention du grade actuel : seulement si le dernier changement connu y mène.
        $last = $entries === [] ? null : $entries[count($entries) - 1];
        if ($currentGradeId !== null && $currentGradeId > 0 && $last !== null && $last['to_grade_id'] === $currentGradeId) {
            $currentSince = $last['date'];
        }

        return ['entries' => array_reverse($entries), 'current_since' => $currentSince];
    }
}
