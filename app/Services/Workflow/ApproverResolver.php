<?php

declare(strict_types=1);

namespace App\Services\Workflow;

use App\Services\Effectifs\PersonnelCommandChainService;

/**
 * Qui donne son avis sur une demande : le chef de l'unité du membre, pris dans la chaîne de commandement des effectifs.
 *
 * On remonte la chaîne (chef d'unité, puis chef de l'unité parente…) en sautant le membre concerné et l'auteur de la
 * demande : un chef qui demande pour un de ses subordonnés fait remonter l'avis à l'échelon supérieur. Sans chaîne
 * saisie, personne n'est trouvé et la demande va directement aux responsables habilités, comme avant.
 */
class ApproverResolver
{
    public function __construct(private ?PersonnelCommandChainService $chain = null)
    {
    }

    /** @var array<int, array{labels: array<int, string>, members: array<int, array<string, mixed>>}> */
    private array $workspaces = [];

    /**
     * @return array{user_id: int, label: string, unit_id: int, member_label: string}|null
     */
    public function commanderFor(int $tenantId, int $memberUserId, int $requesterUserId = 0): ?array
    {
        return $this->resolveStep($tenantId, ['kind' => WorkflowCircuits::KIND_COMMANDER], $memberUserId, $requesterUserId);
    }

    /**
     * Personne qui donne l'avis d'une étape de circuit (lot 4).
     *
     * - commander : premier chef de la chaîne du membre, hors membre, demandeur et personnes déjà sollicitées ;
     * - upper : le chef qui suit, dans la chaîne, la dernière personne sollicitée (sinon le deuxième échelon) ;
     * - user : la personne désignée, si ce n'est ni le membre ni le demandeur.
     *
     * @param array<string, mixed> $step
     * @param list<int> $alreadyAsked
     * @return array{user_id: int, label: string, unit_id: int, member_label: string}|null
     */
    public function resolveStep(int $tenantId, array $step, int $memberUserId, int $requesterUserId = 0, array $alreadyAsked = []): ?array
    {
        if ($tenantId < 1 || $memberUserId < 1) {
            return null;
        }
        $ws = $this->workspace($tenantId);
        $member = $ws['members'][$memberUserId] ?? null;
        $memberLabel = is_array($member) ? (string) ($member['label'] ?? '') : '';
        $unitId = is_array($member) ? (int) ($member['unit_id'] ?? 0) : 0;
        $skip = static fn (int $id): bool => $id < 1 || $id === $memberUserId || $id === $requesterUserId || in_array($id, $alreadyAsked, true);
        $kind = (string) ($step['kind'] ?? '');

        if ($kind === WorkflowCircuits::KIND_USER) {
            $uid = (int) ($step['user_id'] ?? 0);
            if ($skip($uid) || !isset($ws['labels'][$uid])) {
                return null;
            }

            return ['user_id' => $uid, 'label' => $ws['labels'][$uid], 'unit_id' => $unitId, 'member_label' => $memberLabel];
        }
        if (!is_array($member)) {
            return null;
        }
        $chain = array_values(array_filter(
            array_map('intval', is_array($member['chain_ids'] ?? null) ? $member['chain_ids'] : []),
            static fn (int $id): bool => $id > 0 && $id !== $memberUserId && $id !== $requesterUserId
        ));
        $start = 0;
        if ($kind === WorkflowCircuits::KIND_UPPER) {
            $start = 1;
            foreach ($chain as $pos => $id) {
                if (in_array($id, $alreadyAsked, true)) {
                    $start = $pos + 1;
                }
            }
        } elseif ($kind !== WorkflowCircuits::KIND_COMMANDER) {
            return null;
        }
        for ($i = $start, $n = count($chain); $i < $n; $i++) {
            if (!$skip($chain[$i])) {
                return ['user_id' => $chain[$i], 'label' => $ws['labels'][$chain[$i]] ?? '', 'unit_id' => $unitId, 'member_label' => $memberLabel];
            }
        }

        return null;
    }

    /**
     * Membres de la communauté avec leur libellé (choix d'une personne désignée).
     *
     * @return array<int, string> user_id => libellé
     */
    public function memberLabels(int $tenantId): array
    {
        return $tenantId > 0 ? $this->workspace($tenantId)['labels'] : [];
    }

    /** @return array{labels: array<int, string>, members: array<int, array<string, mixed>>} */
    private function workspace(int $tenantId): array
    {
        if (!isset($this->workspaces[$tenantId])) {
            $this->chain ??= new PersonnelCommandChainService();
            $data = $this->chain->workspace($tenantId);
            $labels = [];
            $members = [];
            foreach ($data['members'] ?? [] as $row) {
                $uid = (int) ($row['user_id'] ?? 0);
                if ($uid < 1) {
                    continue;
                }
                $labels[$uid] = (string) ($row['label'] ?? '');
                $members[$uid] = $row;
            }
            $this->workspaces[$tenantId] = ['labels' => $labels, 'members' => $members];
        }

        return $this->workspaces[$tenantId];
    }
}
