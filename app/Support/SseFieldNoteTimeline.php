<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Intel\IntelIntakeService;

/**
 * Historique de suivi d'une fiche de renseignement, tel que la page de lecture du portail SSE l'affiche.
 *
 * Les événements viennent du suivi des remontées (IntelIntakeService::events). Les commentaires internes du back-office
 * ne sortent que pour les lecteurs qui ont accès au back-office Remontées ; les autres lignes ne portent que le fait
 * (qui, quand, quel changement) et, pour un changement d'état, la note qui l'accompagne.
 */
final class SseFieldNoteTimeline
{
    /**
     * @param list<array<string, mixed>> $events du plus ancien au plus récent
     * @return list<array{at: string, actor: string, label: string, from: string, to: string, body: string}> du plus récent au plus ancien
     */
    public static function fromEvents(array $events, bool $withComments): array
    {
        $out = [];
        foreach ($events as $e) {
            $kind = (string) ($e['kind'] ?? '');
            if (!isset(IntelIntakeService::EVENT_LABELS[$kind]) || ($kind === 'comment' && !$withComments)) {
                continue;
            }
            $meta = is_array($e['meta'] ?? null) ? $e['meta'] : [];
            $ts = strtotime((string) ($e['at'] ?? ''));
            $out[] = [
                'at' => $ts !== false ? date('d/m/Y', $ts) . ' à ' . date('H:i', $ts) : '',
                'actor' => trim((string) ($e['actor'] ?? '')) !== '' ? (string) $e['actor'] : 'Système',
                'label' => IntelIntakeService::EVENT_LABELS[$kind],
                'from' => $kind === 'state' ? (string) (IntelIntakeService::STATES[(string) ($meta['from'] ?? '')] ?? '') : '',
                'to' => $kind === 'state' ? (string) (IntelIntakeService::STATES[(string) ($meta['to'] ?? '')] ?? '') : '',
                'body' => in_array($kind, ['state', 'comment'], true) ? trim((string) ($e['body'] ?? '')) : '',
            ];
        }

        return array_reverse($out);
    }
}
