<?php

declare(strict_types=1);

namespace App\Services\Effectifs\Availability;

use Closure;
use Throwable;

/**
 * Rend lisibles les vérifications de disponibilité enregistrées : date, auteur, conclusion (et la proposée si elle a été
 * corrigée), note et suite. Sert à l'onglet Disponibilité (dernière vérification et historique court) et à la
 * chronologie de la fiche.
 */
final class AvailabilityCheckHistory
{
    /**
     * @param list<array<string, mixed>> $rows lignes de member_availability_checks, plus récentes d'abord
     * @param (Closure(int, list<int>): array<int, string>)|null $names
     * @return list<array{id: int, created_at: string, date_label: string, conclusion: string, conclusion_label: string, proposed_label: string, corrected: bool, note: string, follow_up: string, follow_up_label: string, by: string, steps: int}>
     */
    public static function present(int $tenantId, array $rows, ?Closure $names = null): array
    {
        $ids = array_values(array_unique(array_filter(array_map(static fn (array $r): int => (int) ($r['checked_by'] ?? 0), $rows))));
        $labels = [];
        if ($ids !== []) {
            try {
                $labels = $names !== null ? $names($tenantId, $ids) : (new \App\Services\Personnel\MemberNameService())->labelsFor($tenantId, $ids, 'list');
            } catch (Throwable) {
                $labels = [];
            }
        }
        $follow = AvailabilityCheckRecorder::followUpLabels();
        $out = [];
        foreach ($rows as $r) {
            $by = (int) ($r['checked_by'] ?? 0);
            $created = (string) ($r['created_at'] ?? '');
            $ts = strtotime($created);
            $facts = json_decode((string) ($r['facts_json'] ?? ''), true);
            $followUp = (string) ($r['follow_up'] ?? AvailabilityCheckRecorder::FOLLOW_NONE);
            $out[] = [
                'id' => (int) ($r['id'] ?? 0),
                'created_at' => $created,
                'date_label' => $ts !== false ? date('d/m/Y H:i', $ts) : '',
                'conclusion' => (string) ($r['conclusion'] ?? ''),
                'conclusion_label' => AvailabilityCheck::conclusionLabel((string) ($r['conclusion'] ?? '')),
                'proposed_label' => AvailabilityCheck::conclusionLabel((string) ($r['proposed'] ?? '')),
                'corrected' => (int) ($r['corrected'] ?? 0) === 1,
                'note' => trim((string) ($r['note'] ?? '')),
                'follow_up' => $followUp,
                'follow_up_label' => $follow[$followUp] ?? '',
                'by' => $by > 0 ? (string) ($labels[$by] ?? ('Responsable n° ' . $by)) : 'Athena',
                'steps' => is_array($facts['steps'] ?? null) ? count($facts['steps']) : 0,
            ];
        }

        return $out;
    }
}
