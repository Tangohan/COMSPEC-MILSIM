<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseTriageEventRepository;
use App\Repositories\SseTriageRuleRepository;
use App\Repositories\SseTriageStateRepository;
use App\Support\SseTriagePresets;

/**
 * Règles automatiques « Cerbère-Tri » : condition (file, type, mot du titre, ancienneté, gravité, portée) puis action.
 *
 * - matches() : une règle concerne-t-elle un élément ? La même fonction sert l'aperçu (« cette règle concernerait N éléments »)
 *   et le déclenchement : l'aperçu est donc exactement le nombre d'éléments traités.
 * - fire() : applique les règles actives aux éléments qui n'ont encore AUCUN état (ni acquittement, ni veille, ni rappel, ni
 *   annulation) ; chaque action est écrite dans le journal avec l'état d'avant, par lot et par règle, donc annulable.
 */
final class SseTriageRules
{
    public const ACTOR_LABEL = 'Cerbère-Tri';

    /**
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $item
     */
    public static function matches(array $rule, array $item, int $now): bool
    {
        if ((string) ($rule['source_key'] ?? '') !== '' && (string) ($item['source'] ?? '') !== (string) $rule['source_key']) {
            return false;
        }
        $wanted = (string) ($rule['type_key'] ?? '');
        $type = (string) ($item['type_key'] ?? $item['source'] ?? '');
        // Un type se désigne en entier (signal:gap_auto:photographie manquante) ou par son début (signal:gap_auto : toutes les lacunes).
        if ($wanted !== '' && $type !== $wanted && !str_starts_with($type, $wanted . ':')) {
            return false;
        }
        $keyword = trim((string) ($rule['keyword'] ?? ''));
        if ($keyword !== '' && mb_stripos((string) ($item['title'] ?? '') . ' ' . (string) ($item['type_label'] ?? ''), $keyword) === false) {
            return false;
        }
        $min = (int) ($rule['min_age_days'] ?? 0);
        if ($min > 0 && SseTriageInbox::ageDays((string) ($item['at'] ?? ''), $now) < $min) {
            return false;
        }
        $rank = SseTriagePresets::severityRank(SseTriagePriority::severityOf($item));
        if (($rule['min_severity'] ?? null) !== null && $rank < SseTriagePresets::severityRank((string) $rule['min_severity'])) {
            return false;
        }
        if (($rule['max_severity'] ?? null) !== null && $rank > SseTriagePresets::severityRank((string) $rule['max_severity'])) {
            return false;
        }
        if ((string) ($rule['scope'] ?? 'all') === 'cover' && empty($item['subject']['cover'])) {
            return false;
        }

        return true;
    }

    /**
     * Éléments que la règle traiterait maintenant : ceux sans état, qui correspondent.
     *
     * @param array<string, mixed> $rule
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    public static function affected(array $rule, array $items, int $now): array
    {
        return array_values(array_filter($items, static fn (array $it): bool => empty($it['triage']['exists']) && self::matches($rule, $it, $now)));
    }

    /**
     * Applique les règles actives. Retourne les clés traitées par règle.
     *
     * @param list<array<string, mixed>> $rules règles actives
     * @param list<array<string, mixed>> $items éléments avec leur état (« triage »)
     * @return array<string, int> clé d'élément => identifiant de la règle qui l'a traité
     */
    public static function fire(
        int $tenantId,
        array $rules,
        array $items,
        int $now,
        SseTriageStateRepository $state,
        SseTriageEventRepository $events,
        SseTriageRuleRepository $ruleRepo,
        ?string $nowSql = null,
    ): array {
        $nowSql ??= gmdate('Y-m-d H:i:s', $now);
        $done = [];
        foreach ($rules as $rule) {
            if ((string) ($rule['action'] ?? '') === '') {
                continue;
            }
            $batch = 'rule' . (int) $rule['id'] . '-' . substr(bin2hex(random_bytes(4)), 0, 8);
            $n = 0;
            foreach (self::affected($rule, $items, $now) as $it) {
                $key = (string) $it['key'];
                if (isset($done[$key])) {
                    continue;
                }
                $fields = self::fieldsFor($rule, $nowSql);
                if ($fields === null) {
                    continue;
                }
                $state->save($tenantId, $key, $fields);
                $events->record($tenantId, $batch, $key, 'auto_' . (string) $rule['action'], null, self::ACTOR_LABEL, (int) $rule['id'], null, $fields, (string) ($rule['name'] ?? ''));
                $done[$key] = (int) $rule['id'];
                $n++;
            }
            if ($n > 0) {
                $ruleRepo->recordFired($tenantId, (int) $rule['id'], $n);
            }
        }

        return $done;
    }

    /**
     * État posé par l'action d'une règle ; null si l'action est inconnue ou incomplète (assigner sans personne).
     *
     * @param array<string, mixed> $rule
     * @return array<string, mixed>|null
     */
    public static function fieldsFor(array $rule, string $nowSql): ?array
    {
        $base = ['status' => SseTriageStateRepository::OPEN, 'rule_id' => (int) $rule['id'], 'note' => (string) ($rule['name'] ?? '')];

        return match ((string) ($rule['action'] ?? '')) {
            'acknowledge' => ['status' => SseTriageStateRepository::ACKNOWLEDGED, 'acknowledged_at' => $nowSql] + $base,
            'snooze' => ['status' => SseTriageStateRepository::SNOOZED, 'snoozed_until' => gmdate('Y-m-d H:i:s', strtotime($nowSql . ' UTC') + max(1, (int) ($rule['action_value'] ?? 7)) * 86400)] + $base,
            'priority' => ['priority_boost' => max(1, (int) ($rule['action_value'] ?? 10))] + $base,
            'assign' => (int) ($rule['action_value'] ?? 0) > 0 ? ['assigned_to' => (int) $rule['action_value']] + $base : null,
            default => null,
        };
    }
}
