<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseAuditLogRepository;
use App\Repositories\SseTriageEventRepository;
use App\Repositories\SseTriageRuleRepository;
use App\Repositories\SseTriageStateRepository;

/**
 * Actions humaines sur « À trier » : acquitter, mettre en veille, rappeler, assigner, et leur annulation.
 *
 * Une demande porte sur des clés d'éléments que l'appelant a déjà reconnues dans la liste de la session (anti-IDOR, communauté de la
 * session). Chaque lot a un identifiant : chaque élément garde son état d'avant dans le journal, ce qui permet d'annuler le lot
 * d'un coup (le bouton « Annuler » des 15 secondes). Une ligne du journal d'audit SSE est écrite par lot.
 */
final class SseTriageActions
{
    public const MAX_KEYS = 200;
    public const SNOOZE_MAX_DAYS = 365;
    public const ACTIONS = ['acknowledge', 'snooze', 'recall', 'assign'];

    /**
     * @param (\Closure(array<string, mixed>): void)|null $audit écrit une ligne du journal d'audit SSE
     * @param (\Closure(): string)|null $clock « Y-m-d H:i:s » en UTC
     */
    public function __construct(
        private SseTriageStateRepository $state,
        private SseTriageEventRepository $events,
        private ?SseTriageRuleRepository $rules = null,
        private ?\Closure $audit = null,
        private ?\Closure $clock = null,
    ) {
    }

    private function now(): string
    {
        return $this->clock !== null ? (string) ($this->clock)() : gmdate('Y-m-d H:i:s');
    }

    /**
     * @param list<string> $keys
     * @param array{days?: int, assignee?: int, note?: string} $opts
     * @return array{ok: bool, batch: string, changed: int, error?: string}
     */
    public function apply(int $tenantId, string $action, array $keys, int $userId, string $actorLabel, array $opts = []): array
    {
        $keys = array_slice(array_values(array_unique(array_filter(array_map('strval', $keys), static fn (string $k): bool => $k !== '' && strlen($k) <= 100))), 0, self::MAX_KEYS);
        if (!in_array($action, self::ACTIONS, true) || $keys === [] || $tenantId < 1 || $userId < 1) {
            return ['ok' => false, 'batch' => '', 'changed' => 0, 'error' => 'Demande incomplète.'];
        }
        $days = (int) ($opts['days'] ?? 0);
        if ($action === 'snooze' && ($days < 1 || $days > self::SNOOZE_MAX_DAYS)) {
            return ['ok' => false, 'batch' => '', 'changed' => 0, 'error' => 'Durée de veille invalide.'];
        }
        $note = mb_substr(trim((string) ($opts['note'] ?? '')), 0, 500);
        $now = $this->now();
        $batch = gmdate('ymdHis') . substr(bin2hex(random_bytes(6)), 0, 12);
        $changed = 0;
        foreach ($keys as $key) {
            $before = $this->state->find($tenantId, $key);
            $b = SseTriageStateRepository::snapshot($before) ?? [];
            $after = match ($action) {
                'acknowledge' => ['status' => SseTriageStateRepository::ACKNOWLEDGED, 'snoozed_until' => null, 'acknowledged_by' => $userId, 'acknowledged_at' => $now, 'note' => $note, 'rule_id' => null],
                'snooze' => ['status' => SseTriageStateRepository::SNOOZED, 'snoozed_until' => gmdate('Y-m-d H:i:s', strtotime($now . ' UTC') + $days * 86400), 'acknowledged_by' => null, 'acknowledged_at' => null, 'note' => $note, 'rule_id' => null],
                'recall' => ['status' => SseTriageStateRepository::OPEN, 'snoozed_until' => null, 'acknowledged_by' => null, 'acknowledged_at' => null, 'note' => $note, 'rule_id' => null],
                default => ['assigned_to' => max(0, (int) ($opts['assignee'] ?? 0)) ?: null] + ($b === [] ? ['status' => SseTriageStateRepository::OPEN] : []),
            };
            $merged = $after + $b;
            $this->state->save($tenantId, $key, $merged);
            $this->events->record($tenantId, $batch, $key, $action, $userId, $actorLabel, null, $b === [] ? null : $b, $merged, $note);
            $changed++;
        }
        $this->audit($tenantId, 'sse.triage.' . $action, $userId, $actorLabel, $note, ['batch' => $batch, 'count' => $changed, 'keys' => array_slice($keys, 0, 50), 'days' => $days > 0 ? $days : null, 'assignee' => $opts['assignee'] ?? null]);

        return ['ok' => true, 'batch' => $batch, 'changed' => $changed];
    }

    /**
     * Annule un lot : chaque élément retrouve l'état d'avant. Un lot ne s'annule qu'une fois ; seul son auteur l'annule (un
     * gestionnaire peut annuler le lot d'une règle automatique, qui n'a pas d'auteur humain).
     *
     * @return array{ok: bool, restored: int, error?: string}
     */
    public function undoBatch(int $tenantId, string $batch, int $userId, string $actorLabel, bool $manager = false): array
    {
        $events = $this->events->openBatch($tenantId, $batch);
        if ($events === []) {
            return ['ok' => false, 'restored' => 0, 'error' => 'Rien à annuler : ce lot est déjà annulé ou introuvable.'];
        }
        $restored = 0;
        foreach (array_reverse($events) as $event) {
            if ($this->canUndo($event, $userId, $manager)) {
                $this->restore($tenantId, $event, $userId, $actorLabel);
                $restored++;
            }
        }

        return $restored > 0 ? ['ok' => true, 'restored' => $restored] : ['ok' => false, 'restored' => 0, 'error' => 'Vous ne pouvez annuler que vos propres actions.'];
    }

    /**
     * Annule une seule ligne du journal (bouton « Annuler » du journal des règles).
     *
     * @return array{ok: bool, error?: string}
     */
    public function undoEvent(int $tenantId, int $eventId, int $userId, string $actorLabel, bool $manager): array
    {
        $event = $this->events->find($tenantId, $eventId);
        if ($event === null || $event['undone_at'] !== null || $event['action'] === 'undo') {
            return ['ok' => false, 'error' => 'Cette action est déjà annulée ou introuvable.'];
        }
        if (!$this->canUndo($event, $userId, $manager)) {
            return ['ok' => false, 'error' => 'Vous ne pouvez annuler que vos propres actions.'];
        }
        $this->restore($tenantId, $event, $userId, $actorLabel);

        return ['ok' => true];
    }

    /** @param array<string, mixed> $event */
    private function canUndo(array $event, int $userId, bool $manager): bool
    {
        $actor = (int) ($event['actor_user_id'] ?? 0);

        return $actor > 0 ? $actor === $userId : $manager;
    }

    /** @param array<string, mixed> $event */
    private function restore(int $tenantId, array $event, int $userId, string $actorLabel): void
    {
        $key = (string) $event['key'];
        $auto = (int) ($event['actor_user_id'] ?? 0) === 0;
        $before = is_array($event['before']) ? $event['before'] : null;
        $current = SseTriageStateRepository::snapshot($this->state->find($tenantId, $key));
        if ($before !== null) {
            $this->state->save($tenantId, $key, $before);
        } elseif ($auto) {
            // Annulation d'une règle : l'élément garde une ligne « open » pour que la règle ne le reprenne pas.
            $this->state->save($tenantId, $key, ['status' => SseTriageStateRepository::OPEN, 'rule_id' => (int) ($event['rule_id'] ?? 0), 'note' => 'Annulé']);
        } else {
            $this->state->delete($tenantId, $key);
        }
        $this->events->markUndone($tenantId, (int) $event['id']);
        $this->events->record($tenantId, (string) $event['batch_id'], $key, 'undo', $userId, $actorLabel, ((int) ($event['rule_id'] ?? 0)) ?: null, $current, $before);
        if ((int) ($event['rule_id'] ?? 0) > 0 && $this->rules !== null) {
            $this->rules->recordUndone($tenantId, (int) $event['rule_id'], 1);
        }
        $this->audit($tenantId, 'sse.triage.undo', $userId, $actorLabel, '', ['event' => (int) $event['id'], 'key' => $key, 'undone_action' => (string) $event['action']]);
    }

    /** @param array<string, mixed> $after */
    private function audit(int $tenantId, string $action, int $userId, string $actorLabel, string $reason, array $after): void
    {
        $row = ['tenant_id' => $tenantId, 'actor_user_id' => $userId, 'actor_label' => $actorLabel, 'action' => $action, 'object_type' => 'triage', 'reason' => $reason, 'after' => $after];
        try {
            $this->audit !== null ? ($this->audit)($row) : (new SseAuditLogRepository())->write($row);
        } catch (\Throwable $e) {
            error_log('À trier : journal d’audit indisponible : ' . $e->getMessage());
        }
    }
}
