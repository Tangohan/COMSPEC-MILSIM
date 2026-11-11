<?php

declare(strict_types=1);

namespace App\Services\AtakToc;

use App\Repositories\AtakPlanRepository;

/**
 * Plans d'opération : enregistrement (éléments, phases, réglages), versions, duplication, import et export JSON,
 * envoi en jeu et import depuis le jeu. Les droits sont vérifiés par l'appelant (TocAccess).
 */
final class PlanService
{
    public const PUBLISH_TTL = 1800;

    /** @var callable(int, int, string, string, array<string, scalar>, int, string, ?int): ?int */
    private $enqueue;

    /**
     * @param callable(int, int, string, string, array<string, scalar>, int, string, ?int): ?int|null $enqueue file des commandes web (genre « toc »)
     */
    public function __construct(private AtakPlanRepository $repo, ?callable $enqueue = null)
    {
        $this->enqueue = $enqueue ?? static function (int $tenantId, int $mapId, string $cmd, string $ref, array $args, int $ttl, string $who, ?int $userId): ?int {
            $row = (new \App\Repositories\AtakWebCommandRepository())->enqueue($tenantId, $mapId, 'toc', $cmd, '*', $ref, 'Plan web', ['plan' => $ref], $args, $who, $userId, $ttl);

            return is_array($row) ? (int) $row['id'] : null;
        };
    }

    public function repo(): AtakPlanRepository
    {
        return $this->repo;
    }

    /**
     * Plan complet au format d'échange (export JSON, versions).
     *
     * @param array<string, mixed> $plan
     * @return array<string, mixed>
     */
    public function document(array $plan): array
    {
        $strip = static fn (array $el): array => array_diff_key($el, ['deleted' => 1, 'updated_at' => 1, 'updated_by' => 1]);

        return [
            'format' => 'athena-plan',
            'version' => 1,
            'title' => $plan['title'],
            'world' => $plan['world'],
            'map_id' => $plan['map_id'],
            'rev' => $plan['rev'],
            'meta' => $plan['meta'],
            'phases' => array_map(static fn (array $p): array => array_diff_key($p, ['deleted' => 1, 'updated_at' => 1]), $this->repo->phases((int) $plan['id'])),
            'elements' => array_map($strip, $this->repo->elements((int) $plan['id'])),
            'exported_at' => gmdate('c'),
        ];
    }

    /**
     * Changements d'éléments envoyés par un éditeur : enregistrés un par un, le dernier gagne.
     *
     * @param array<string, mixed> $plan
     * @param list<mixed> $upserts
     * @param list<mixed> $deletes
     * @param array<string, int> $base
     * @return array<string, mixed>
     */
    public function saveElements(array $plan, array $upserts, array $deletes, ?int $userId, array $base, bool $canUnlock): array
    {
        $planId = (int) $plan['id'];
        $phaseUids = array_column($this->repo->phases($planId), 'uid');
        $clean = [];
        $rejected = [];
        foreach (array_slice($upserts, 0, 500) as $raw) {
            [$el, $why] = PlanValidator::element($raw, $phaseUids);
            if ($el === null) {
                $rejected[] = ['uid' => is_array($raw) ? (string) ($raw['uid'] ?? '') : '', 'reason' => $why];
                continue;
            }
            $clean[] = $el;
        }
        $count = $this->repo->countElements($planId);
        if ($count + count($clean) > PlanValidator::MAX_ELEMENTS) {
            // Plafond : les mises à jour passent, les créations au-delà sont refusées.
            $existing = array_flip(array_column($this->repo->elements($planId), 'uid'));
            $room = max(0, PlanValidator::MAX_ELEMENTS - $count);
            $kept = [];
            foreach ($clean as $el) {
                if (isset($existing[$el['uid']]) || $room-- > 0) {
                    $kept[] = $el;
                } else {
                    $rejected[] = ['uid' => $el['uid'], 'reason' => 'too_many_elements'];
                }
            }
            $clean = $kept;
        }
        $res = $this->repo->upsertElements((int) $plan['tenant_id'], $planId, $clean, $userId, $base, $canUnlock);
        $delUids = array_values(array_filter(array_map(static fn (mixed $u): ?string => PlanValidator::uid($u), array_slice($deletes, 0, 500))));
        $del = $delUids !== [] ? $this->repo->deleteElements($planId, $delUids, $userId, $canUnlock) : ['deleted' => [], 'refused' => []];
        $rev = ($res['saved'] !== [] || $del['deleted'] !== []) ? $this->repo->bumpRev((int) $plan['tenant_id'], $planId) : (int) $plan['rev'];

        return [
            'ok' => true,
            'rev' => $rev,
            'saved' => $res['saved'],
            'deleted' => $del['deleted'],
            'overwritten' => $res['overwritten'],
            'refused' => array_values(array_merge($res['refused'], $del['refused'])),
            'rejected' => $rejected,
        ];
    }

    /**
     * @param array<string, mixed> $plan
     * @param list<mixed> $phases
     */
    public function savePhases(array $plan, array $phases): int
    {
        $clean = [];
        foreach (array_slice($phases, 0, PlanValidator::MAX_PHASES) as $raw) {
            [$ph] = PlanValidator::phase($raw);
            if ($ph !== null) {
                $clean[$ph['uid']] = $ph;
            }
        }
        $this->repo->replacePhases((int) $plan['tenant_id'], (int) $plan['id'], array_values($clean));

        return $this->repo->bumpRev((int) $plan['tenant_id'], (int) $plan['id']);
    }

    /** @param array<string, mixed> $plan */
    public function saveVersion(array $plan, string $label, ?int $userId): int
    {
        $fresh = $this->repo->find((int) $plan['tenant_id'], (int) $plan['id']) ?? $plan;

        return $this->repo->saveVersion((int) $plan['tenant_id'], (int) $plan['id'], (int) $fresh['rev'], PlanValidator::text($label, 120), $this->document($fresh), $userId);
    }

    /**
     * Remet le plan dans l'état d'une version (la version actuelle est gardée avant).
     *
     * @param array<string, mixed> $plan
     */
    public function restore(array $plan, int $versionId, ?int $userId): bool
    {
        $snap = $this->repo->version((int) $plan['id'], $versionId);
        if ($snap === null) {
            return false;
        }
        $this->saveVersion($plan, 'Avant restauration', $userId);
        $this->applyDocument($plan, $snap, $userId, true);

        return true;
    }

    /**
     * Écrit un document (version, import) dans un plan. $replace : les éléments absents du document sont retirés.
     *
     * @param array<string, mixed> $plan
     * @param array<string, mixed> $doc
     */
    public function applyDocument(array $plan, array $doc, ?int $userId, bool $replace): int
    {
        $planId = (int) $plan['id'];
        $tenantId = (int) $plan['tenant_id'];
        $phases = [];
        foreach (array_slice(is_array($doc['phases'] ?? null) ? $doc['phases'] : [], 0, PlanValidator::MAX_PHASES) as $raw) {
            [$ph] = PlanValidator::phase($raw);
            if ($ph !== null) {
                $phases[] = $ph;
            }
        }
        if ($replace || $phases !== []) {
            $this->repo->replacePhases($tenantId, $planId, $phases);
        }
        $phaseUids = array_column($phases, 'uid');
        $els = [];
        foreach (array_slice(is_array($doc['elements'] ?? null) ? $doc['elements'] : [], 0, PlanValidator::MAX_ELEMENTS) as $raw) {
            [$el] = PlanValidator::element($raw, $phaseUids);
            if ($el !== null) {
                $els[$el['uid']] = $el;
            }
        }
        $this->repo->upsertElements($tenantId, $planId, array_values($els), $userId, [], true);
        if ($replace) {
            $gone = array_values(array_diff(array_column($this->repo->elements($planId), 'uid'), array_keys($els)));
            if ($gone !== []) {
                $this->repo->deleteElements($planId, $gone, $userId, true);
            }
            if (is_array($doc['meta'] ?? null)) {
                $this->repo->updatePlan($tenantId, $planId, ['meta' => PlanValidator::meta($doc['meta'])]);
            }
        }

        return $this->repo->bumpRev($tenantId, $planId);
    }

    /**
     * Copie d'un plan (titre « … (copie) »), privée, à l'auteur de la copie.
     *
     * @param array<string, mixed> $plan
     */
    public function duplicate(array $plan, int $userId, string $title = '', bool $template = false): int
    {
        $doc = $this->document($plan);
        $newId = $this->repo->create(
            (int) $plan['tenant_id'],
            $userId,
            $title !== '' ? $title : mb_substr($plan['title'] . ' (copie)', 0, 120),
            (int) $plan['map_id'],
            (string) $plan['world'],
            $template ? 'community' : 'private',
            null,
            PlanValidator::meta($plan['meta']),
            $template
        );
        $copy = $this->repo->find((int) $plan['tenant_id'], $newId);
        if ($copy !== null) {
            $this->applyDocument($copy, $doc, $userId, false);
        }

        return $newId;
    }

    /**
     * « Envoyer en jeu » : retire en jeu les éléments déjà envoyés par ce plan, puis pose ceux du plan (ou d'une
     * phase) dans le plan partagé du camp. Rien d'autre que le plan n'est envoyé.
     *
     * @param array<string, mixed> $plan
     * @return array{ok: bool, reason: string, sent: int, skipped: int, degraded: array<string, int>, commands: list<int>}
     */
    public function publish(array $plan, string $side, ?string $phaseUid, ?int $userId, string $who): array
    {
        if (!in_array($side, PlanValidator::SIDES, true)) {
            return ['ok' => false, 'reason' => 'side_invalid', 'sent' => 0, 'skipped' => 0, 'degraded' => [], 'commands' => []];
        }
        $planId = (int) $plan['id'];
        $tenantId = (int) $plan['tenant_id'];
        $mapId = (int) $plan['map_id'];
        $items = PlanGameMapper::toGameItems($planId, $this->repo->elements($planId), $phaseUid);
        if ($items === []) {
            return ['ok' => false, 'reason' => 'nothing_to_send', 'sent' => 0, 'skipped' => 0, 'degraded' => [], 'commands' => []];
        }
        if (count($items) > 300) {
            return ['ok' => false, 'reason' => 'too_many_for_game', 'sent' => 0, 'skipped' => 0, 'degraded' => [], 'commands' => []];
        }
        $this->saveVersion($plan, 'Envoyé en jeu (' . $side . ')', $userId);
        $commands = [];
        $who = PlanGameMapper::ascii($who, 40);
        $clear = ($this->enqueue)($tenantId, $mapId, 'plnclr', PlanGameMapper::itemPrefix($planId), ['s' => $side, 'pl' => base_convert((string) $planId, 10, 36), 'by' => $who, 'n' => count($items)], self::PUBLISH_TTL, $who, $userId);
        if ($clear === null) {
            return ['ok' => false, 'reason' => 'queue_unavailable', 'sent' => 0, 'skipped' => 0, 'degraded' => [], 'commands' => []];
        }
        $commands[] = $clear;
        $sent = 0;
        $skipped = 0;
        $degraded = [];
        foreach ($items as $item) {
            $args = PlanGameMapper::wireArgs($item, $side, $planId);
            if ($args === null) {
                $skipped++;
                continue;
            }
            $id = ($this->enqueue)($tenantId, $mapId, 'plnadd', $item['id'], $args, self::PUBLISH_TTL, $who, $userId);
            if ($id === null) {
                $skipped++;
                continue;
            }
            $commands[] = $id;
            $sent++;
            if ($item['degraded'] !== '') {
                $degraded[$item['degraded']] = ($degraded[$item['degraded']] ?? 0) + 1;
            }
        }
        $this->repo->markPublished($tenantId, $planId, $side, (int) $plan['rev'], $sent);

        return ['ok' => $sent > 0, 'reason' => $sent > 0 ? '' : 'nothing_sent', 'sent' => $sent, 'skipped' => $skipped, 'degraded' => $degraded, 'commands' => $commands];
    }

    /**
     * Plan du camp dessiné en jeu (app Plan, PC du poste) => éléments web, prêts à ajouter au plan.
     *
     * @param list<array<string, mixed>> $gamePlans atak_briefing_plans récents (AtakBriefingPlanRepository::recent)
     * @return list<array<string, mixed>>
     */
    public static function elementsFromGamePlans(array $gamePlans, string $side, int $planId = 0): array
    {
        foreach ($gamePlans as $gp) {
            if (($gp['side'] ?? '') === $side && empty($gp['personal'])) {
                $items = is_array($gp['items'] ?? null) ? $gp['items'] : [];
                // Les éléments posés par CE plan web ne reviennent pas (ils sont déjà dans le plan).
                if ($planId > 0) {
                    $prefix = PlanGameMapper::itemPrefix($planId);
                    $items = array_values(array_filter($items, static fn (mixed $it): bool => !is_array($it) || !str_starts_with((string) ($it['id'] ?? ''), $prefix)));
                }

                return PlanGameMapper::fromGameItems($items, 'g' . strtolower(substr($side, 0, 1)));
            }
        }

        return [];
    }
}
