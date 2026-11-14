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

    /**
     * @return array{user_id: int, label: string, unit_id: int, member_label: string}|null
     */
    public function commanderFor(int $tenantId, int $memberUserId, int $requesterUserId = 0): ?array
    {
        if ($tenantId < 1 || $memberUserId < 1) {
            return null;
        }
        $this->chain ??= new PersonnelCommandChainService();
        $data = $this->chain->workspace($tenantId);
        $labels = [];
        $member = null;
        foreach ($data['members'] ?? [] as $row) {
            $uid = (int) ($row['user_id'] ?? 0);
            $labels[$uid] = (string) ($row['label'] ?? '');
            if ($uid === $memberUserId) {
                $member = $row;
            }
        }
        if (!is_array($member)) {
            return null;
        }
        $chainIds = is_array($member['chain_ids'] ?? null) ? $member['chain_ids'] : [];
        foreach ($chainIds as $candidate) {
            $candidate = (int) $candidate;
            if ($candidate < 1 || $candidate === $memberUserId || $candidate === $requesterUserId) {
                continue;
            }

            return [
                'user_id' => $candidate,
                'label' => $labels[$candidate] ?? '',
                'unit_id' => (int) ($member['unit_id'] ?? 0),
                'member_label' => (string) ($member['label'] ?? ''),
            ];
        }

        return null;
    }
}
