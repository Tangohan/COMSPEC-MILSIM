<?php

declare(strict_types=1);

namespace App\Services\Organization;

/**
 * Extraits du résultat d’adéquation pour une personne (fiche), une unité (fiche unité) ou l’ORBAT (pastilles).
 * Les libellés de personnes passent par $labelFor : l’appelant y applique la confidentialité de son lecteur.
 */
final class OrgAdequacyView
{
    /**
     * @param array<string, mixed> $result OrgAdequacyService::evaluate()
     * @param (callable(int, string): string)|null $labelFor identifiant, libellé connu → libellé affichable
     * @return array<string, mixed>|null
     */
    public static function forPerson(array $result, int $userId, ?callable $labelFor = null): ?array
    {
        if (empty($result['enabled']) || !is_array($result['people'][$userId] ?? null)) {
            return null;
        }
        $p = $result['people'][$userId];
        $label = static function (int $id) use ($result, $labelFor): string {
            $known = (string) ($result['people'][$id]['label'] ?? 'Membre');

            return $labelFor !== null ? $labelFor($id, $known) : $known;
        };
        $issues = OrgAdequacyService::issuesFor($result, 'person', $userId);
        $people = [];
        foreach ($issues as $i) {
            foreach ($i['candidates'] ?? [] as $c) {
                $people[(int) $c] = ['label' => $label((int) $c)];
            }
        }
        $unitId = (int) ($p['unit_id'] ?? 0);
        $chief = (int) ($p['chief_id'] ?? 0);
        $deputy = (int) ($p['deputy_id'] ?? 0);

        return [
            'user_id' => $userId,
            'place' => (string) $p['place'],
            'status' => (string) $p['status'],
            'roles' => is_array($p['roles'] ?? null) ? $p['roles'] : [],
            'unit_id' => $unitId,
            'unit_name' => $unitId > 0 ? (string) ($result['units'][$unitId]['name'] ?? '') : '',
            'chief_id' => $chief,
            'chief_label' => $chief > 0 ? $label($chief) : '',
            'deputy_id' => $deputy,
            'deputy_label' => $deputy > 0 ? $label($deputy) : '',
            'issues' => $issues,
            'people' => $people,
        ];
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>|null
     */
    public static function forUnit(array $result, int $unitId): ?array
    {
        if (empty($result['enabled']) || !is_array($result['units'][$unitId] ?? null)) {
            return null;
        }
        $u = $result['units'][$unitId];
        $issues = OrgAdequacyService::issuesFor($result, 'unit', $unitId);
        $people = [];
        foreach ($issues as $i) {
            foreach ($i['candidates'] ?? [] as $c) {
                $people[(int) $c] = ['label' => (string) ($result['people'][(int) $c]['label'] ?? 'Membre')];
            }
        }
        $deputy = (int) ($u['deputy_id'] ?? 0);

        return $u + [
            'issues_list' => $issues,
            'people' => $people,
            'deputy_label' => $deputy > 0 ? (string) ($result['people'][$deputy]['label'] ?? '') : '',
        ];
    }

    /**
     * Pastilles de l’ORBAT, par unité : la plus urgente des familles d’écarts et le nombre d’écarts.
     *
     * @param array<string, mixed> $result
     * @return array<int, array{status: string, tone: string, label: string, count: int, texts: list<string>}>
     */
    public static function orbatBadges(array $result): array
    {
        if (empty($result['enabled'])) {
            return [];
        }
        $out = [];
        foreach ($result['units'] ?? [] as $unitId => $u) {
            $issues = OrgAdequacyService::issuesFor($result, 'unit', (int) $unitId);
            if ($issues === []) {
                continue;
            }
            $tone = 'info';
            $label = 'Écart d’adéquation';
            foreach ($issues as $i) {
                if (($i['kind'] ?? '') === OrgAdequacyEngine::KIND_UNDER) {
                    $label = 'Sous-capacité';
                    $tone = 'warn';
                    break;
                }
            }
            if ($tone !== 'warn') {
                foreach ($issues as $i) {
                    if (($i['tone'] ?? '') === 'warn') {
                        $label = (string) $i['title'];
                        $tone = 'warn';
                        break;
                    }
                    if (($i['kind'] ?? '') === OrgAdequacyEngine::KIND_OVER) {
                        $label = 'Surqualification';
                    }
                }
            }
            $out[(int) $unitId] = [
                'status' => (string) ($u['status'] ?? ''),
                'tone' => $tone,
                'label' => $label,
                'count' => count($issues),
                'texts' => array_values(array_map(static fn (array $i): string => (string) $i['text'], array_slice($issues, 0, 4))),
            ];
        }

        return $out;
    }
}
