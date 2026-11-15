<?php

declare(strict_types=1);

namespace App\Services\Effectifs\Dossier;

use Throwable;

/**
 * Rend l'historique du suivi lisible : qui (nom), ce qu'il a fait (phrase) et ce qui a changé (avant → après).
 */
final class DossierHistoryPresenter
{
    /** @return array<string, string> */
    public static function verbs(): array
    {
        return [
            DossierCatalog::ACTION_COMPLETE => 'a marqué terminée l’étape',
            DossierCatalog::ACTION_REOPEN => 'a remis à faire l’étape',
            DossierCatalog::ACTION_POSTPONE => 'a reporté l’échéance de l’étape',
            DossierCatalog::ACTION_REASSIGN => 'a changé le responsable de l’étape',
            DossierCatalog::ACTION_NOTE => 'a ajouté une note sur l’étape',
            DossierCatalog::ACTION_SKIP => 'a ignoré l’étape',
            DossierCatalog::ACTION_UNSKIP => 'a rétabli l’étape',
            DossierCatalog::ACTION_REMIND => 'a relancé le membre pour l’étape',
            DossierCatalog::ACTION_REMOVE => 'a retiré l’étape',
            DossierCatalog::ACTION_ADD => 'a ajouté l’étape',
        ];
    }

    /** @return array<string, string> formulation sans nom d'étape (liste propre à une étape) */
    public static function shortVerbs(): array
    {
        return [
            DossierCatalog::ACTION_COMPLETE => 'a marqué l’étape terminée',
            DossierCatalog::ACTION_REOPEN => 'a remis l’étape à faire',
            DossierCatalog::ACTION_POSTPONE => 'a reporté l’échéance',
            DossierCatalog::ACTION_REASSIGN => 'a changé le responsable',
            DossierCatalog::ACTION_NOTE => 'a ajouté une note',
            DossierCatalog::ACTION_SKIP => 'a ignoré l’étape',
            DossierCatalog::ACTION_UNSKIP => 'a rétabli l’étape',
            DossierCatalog::ACTION_REMIND => 'a relancé le membre',
            DossierCatalog::ACTION_REMOVE => 'a retiré l’étape',
            DossierCatalog::ACTION_ADD => 'a ajouté l’étape',
        ];
    }

    /**
     * @param list<array<string, mixed>> $events lignes de member_dossier_step_events
     * @param (\Closure(int, list<int>): array<int, string>)|null $names
     * @return list<array{id: int, step_key: string, step_title: string, action: string, actor: string, verb: string, short: string, change: string, reason: string, created_at: string}>
     */
    public static function present(int $tenantId, array $events, ?\Closure $names = null): array
    {
        $ids = [];
        foreach ($events as $e) {
            $ids[] = (int) ($e['actor_user_id'] ?? 0);
            foreach (['before_json', 'after_json'] as $col) {
                $v = self::decode($e[$col] ?? null);
                if ((int) ($v['owner_user_id'] ?? 0) > 0) {
                    $ids[] = (int) $v['owner_user_id'];
                }
            }
        }
        $ids = array_values(array_unique(array_filter($ids)));
        $labels = [];
        if ($ids !== []) {
            try {
                $labels = $names !== null ? $names($tenantId, $ids) : (new \App\Services\Personnel\MemberNameService())->labelsFor($tenantId, $ids, 'list');
            } catch (Throwable) {
                $labels = [];
            }
        }
        $who = static fn (int $id): string => $id > 0 ? (string) ($labels[$id] ?? 'Responsable n° ' . $id) : 'Responsable par défaut';
        $day = static fn (mixed $v): string => DossierCatalog::day($v) !== null ? date('d/m/Y', (int) strtotime((string) $v)) : 'sans échéance';
        $state = static fn (mixed $v): string => match ((string) $v) {
            DossierCatalog::STATE_DONE => 'terminée', DossierCatalog::STATE_SKIPPED => 'ignorée', DossierCatalog::STATE_BLOCKED => 'bloquée',
            DossierCatalog::STATE_IN_PROGRESS => 'en cours', default => 'à faire',
        };
        $out = [];
        foreach ($events as $e) {
            $action = (string) $e['action'];
            $before = self::decode($e['before_json'] ?? null);
            $after = self::decode($e['after_json'] ?? null);
            $change = match ($action) {
                DossierCatalog::ACTION_POSTPONE => $day($before['due_on'] ?? null) . ' → ' . $day($after['due_on'] ?? null),
                DossierCatalog::ACTION_REASSIGN => $who((int) ($before['owner_user_id'] ?? 0)) . ' → ' . $who((int) ($after['owner_user_id'] ?? 0)),
                DossierCatalog::ACTION_COMPLETE, DossierCatalog::ACTION_REOPEN, DossierCatalog::ACTION_SKIP, DossierCatalog::ACTION_UNSKIP
                    => $state($before['state'] ?? null) . ' → ' . $state($after['state'] ?? null),
                DossierCatalog::ACTION_NOTE => mb_strimwidth((string) ($after['note'] ?? ''), 0, 240, '…'),
                DossierCatalog::ACTION_ADD => 'échéance : ' . $day($after['due_on'] ?? null) . ' · responsable : ' . $who((int) ($after['owner_user_id'] ?? 0)),
                default => '',
            };
            $out[] = [
                'id' => (int) $e['id'],
                'step_key' => (string) $e['step_key'],
                'step_title' => (string) $e['step_title'],
                'action' => $action,
                'actor' => $who((int) ($e['actor_user_id'] ?? 0)) === 'Responsable par défaut' ? 'Athena' : $who((int) $e['actor_user_id']),
                'verb' => self::verbs()[$action] ?? 'a modifié l’étape',
                'short' => self::shortVerbs()[$action] ?? 'a modifié l’étape',
                'change' => $change,
                'reason' => (string) ($e['reason'] ?? ''),
                'created_at' => (string) $e['created_at'],
            ];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private static function decode(mixed $json): array
    {
        $v = is_string($json) && $json !== '' ? json_decode($json, true) : null;

        return is_array($v) ? $v : [];
    }
}
