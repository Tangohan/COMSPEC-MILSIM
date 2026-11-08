<?php

declare(strict_types=1);

namespace App\Services\Effectifs;

/**
 * Page « Chaîne de commandement » : alertes (quel problème, où, quoi faire) et propositions applicables.
 *
 * Calcul pur, sans accès base : mêmes données, mêmes résultats. Une proposition n’existe que si une donnée réelle
 * l’appuie (candidat au grade attendu fourni par OrgAdequacyEngine, seul membre d’une unité, chef sans affectation).
 * Sans donnée, rien n’est proposé.
 */
final class CommandChainAdvisor
{
    public const KIND_DESIGNATE = 'designate';

    public const KIND_REPLACE = 'replace';

    public const KIND_ASSIGN = 'assign';

    /**
     * @param list<array<string, mixed>> $units PersonnelCommandChainService::workspace()['units']
     * @param list<array<string, mixed>> $members PersonnelCommandChainService::workspace()['members']
     * @param array<string, mixed>|null $adequacy OrgAdequacyService::evaluate(), null si indisponible
     * @param list<int> $activeIds membres actifs de la communauté ; liste vide = activité inconnue, aucun contrôle
     * @return array{
     *   alerts: list<array<string, mixed>>,
     *   proposals: list<array<string, mixed>>,
     *   suggested: array<int, list<int>>
     * }
     */
    public static function analyze(array $units, array $members, ?array $adequacy, array $activeIds = []): array
    {
        $unitById = [];
        foreach ($units as $u) {
            $unitById[(int) $u['id']] = $u;
        }
        $memberById = [];
        foreach ($members as $m) {
            $memberById[(int) $m['user_id']] = $m;
        }
        $active = array_fill_keys(array_map('intval', $activeIds), true);
        $isActive = static fn (int $id): bool => $active === [] || isset($active[$id]);

        $hasChildren = [];
        foreach ($units as $u) {
            $pid = (int) ($u['parent_id'] ?? 0);
            if ($pid > 0) {
                $hasChildren[$pid] = true;
            }
        }
        $commandCount = [];
        foreach ($units as $u) {
            $cid = (int) ($u['commander_user_id'] ?? 0);
            if ($cid > 0) {
                $commandCount[$cid] = ($commandCount[$cid] ?? 0) + 1;
            }
        }

        return [
            'alerts' => self::alerts($units, $unitById, $members, $hasChildren, $active !== [], $isActive),
            'proposals' => self::proposals($units, $unitById, $memberById, $commandCount, $adequacy, $isActive),
            'suggested' => self::suggested($unitById, $adequacy, $isActive),
        ];
    }

    /**
     * @param list<array<string, mixed>> $units
     * @param array<int, array<string, mixed>> $unitById
     * @param list<array<string, mixed>> $members
     * @param array<int, bool> $hasChildren
     * @param callable(int): bool $isActive
     * @return list<array<string, mixed>>
     */
    private static function alerts(array $units, array $unitById, array $members, array $hasChildren, bool $checkActive, callable $isActive): array
    {
        $orphans = [];
        foreach ($members as $m) {
            $unitId = (int) ($m['unit_id'] ?? 0);
            if ($unitId > 0 && (int) ($m['reports_to_id'] ?? 0) < 1) {
                $orphans[$unitId] = ($orphans[$unitId] ?? 0) + 1;
            }
        }

        $inactive = [];
        $vacant = [];
        foreach ($units as $u) {
            $id = (int) $u['id'];
            $name = self::unitName($u);
            $cid = (int) ($u['commander_user_id'] ?? 0);
            if ($cid > 0) {
                if ($checkActive && !$isActive($cid)) {
                    $inactive[] = [
                        'code' => 'commander_inactive',
                        'tone' => 'danger',
                        'title' => 'Chef d’unité inactif : ' . $name,
                        'where' => self::where($u, $unitById),
                        'text' => 'Le compte désigné comme chef n’est plus actif : les membres remontent à une personne absente. Désignez un remplaçant.',
                        'unit_id' => $id,
                        'action' => 'Remplacer le chef',
                    ];
                }
                continue;
            }
            $count = (int) ($u['member_count'] ?? 0);
            $without = $orphans[$id] ?? 0;
            $above = self::nearestChief($u, $unitById);
            if ($without > 1) {
                $text = str_replace('%1', (string) $without, '%1 membres n’ont aucun chef au-dessus d’eux. Désignez un chef pour rétablir leur chaîne.');
            } elseif ($without === 1) {
                $text = 'Un membre n’a aucun chef au-dessus de lui. Désignez un chef pour rétablir sa chaîne.';
            } elseif ($count > 0 || isset($hasChildren[$id])) {
                $text = $above !== ''
                    ? str_replace('%1', $above, 'Cette branche remonte directement à %1, sans échelon intermédiaire. Désignez un chef pour cette unité.')
                    : 'Personne ne commande cette branche. Désignez un chef pour cette unité.';
            } else {
                $text = 'Unité sans membre pour le moment. Désignez un chef quand elle sera active.';
            }
            $vacant[] = [
                'code' => 'chief_vacant',
                'tone' => $count > 0 || isset($hasChildren[$id]) ? 'warn' : 'info',
                'title' => 'Chef à désigner : ' . $name,
                'where' => self::where($u, $unitById),
                'text' => $text,
                'unit_id' => $id,
                'action' => 'Désigner un chef',
                '_rank' => [$without > 0 ? 0 : ($count > 0 ? 1 : 2), -$without, -$count],
            ];
        }
        usort($vacant, static fn (array $a, array $b): int => $a['_rank'] <=> $b['_rank']);
        $vacant = array_map(static function (array $row): array {
            unset($row['_rank']);

            return $row;
        }, $vacant);

        $people = [];
        foreach ($members as $m) {
            $id = (int) $m['user_id'];
            if ((int) ($m['unit_id'] ?? 0) < 1 && ($m['commands'] ?? []) === [] && $isActive($id)) {
                $people[] = ['id' => $id, 'label' => (string) $m['label']];
            }
        }
        $outside = [];
        if ($people !== []) {
            $total = count($people);
            $outside[] = [
                'code' => 'members_without_unit',
                'tone' => 'warn',
                'title' => $total > 1 ? str_replace('%1', (string) $total, '%1 membres sans unité') : 'Un membre sans unité',
                'where' => 'Hors de l’organigramme',
                'text' => $total > 1
                    ? 'Ils n’apparaissent dans aucune chaîne. Affectez-les à une unité pour qu’ils aient un chef.'
                    : 'Il n’apparaît dans aucune chaîne. Affectez-le à une unité pour qu’il ait un chef.',
                'unit_id' => 0,
                'people' => $people,
            ];
        }

        return array_values(array_merge($inactive, $vacant, $outside));
    }

    /**
     * @param list<array<string, mixed>> $units
     * @param array<int, array<string, mixed>> $unitById
     * @param array<int, array<string, mixed>> $memberById
     * @param array<int, int> $commandCount
     * @param array<string, mixed>|null $adequacy
     * @param callable(int): bool $isActive
     * @return list<array<string, mixed>>
     */
    private static function proposals(array $units, array $unitById, array $memberById, array $commandCount, ?array $adequacy, callable $isActive): array
    {
        $issues = is_array($adequacy['issues'] ?? null) && !empty($adequacy['enabled']) ? $adequacy['issues'] : [];
        $people = is_array($adequacy['people'] ?? null) ? $adequacy['people'] : [];
        $label = static fn (int $id): string => trim((string) ($memberById[$id]['label'] ?? ($people[$id]['label'] ?? ''))) !== ''
            ? trim((string) ($memberById[$id]['label'] ?? $people[$id]['label']))
            : 'Membre';
        $grade = static fn (int $id): string => trim((string) ($people[$id]['grade_label'] ?? ''));

        // Une même personne n’est proposée qu’une fois : on ne peut pas la nommer partout à la fois.
        $used = [];
        $pick = static function (array $issue) use (&$used, $isActive, $grade): int {
            foreach (is_array($issue['candidates'] ?? null) ? $issue['candidates'] : [] as $cand) {
                $cand = (int) $cand;
                if ($cand > 0 && !isset($used[$cand]) && $isActive($cand) && $grade($cand) !== '') {
                    $used[$cand] = true;

                    return $cand;
                }
            }

            return 0;
        };

        $designate = [];
        $replace = [];
        foreach ($issues as $issue) {
            $unitId = (int) ($issue['unit_id'] ?? 0);
            $code = (string) ($issue['code'] ?? '');
            $unit = $unitById[$unitId] ?? null;
            if ($unit === null) {
                continue;
            }
            if ($code === 'leader_vacant') {
                $cand = $pick($issue);
                if ($cand < 1) {
                    continue;
                }
                $homeId = (int) ($memberById[$cand]['unit_id'] ?? 0);
                $inUnit = in_array($cand, array_map('intval', is_array($unit['member_ids'] ?? null) ? $unit['member_ids'] : []), true);
                if ($inUnit) {
                    $reason = str_replace('%1', $grade($cand), 'Grade %1, attendu pour ce poste, et déjà membre de l’unité.');
                } elseif ($homeId > 0 && isset($unitById[$homeId])) {
                    $reason = strtr('Grade %1, attendu pour ce poste. Affectation actuelle : %2.', ['%1' => $grade($cand), '%2' => self::unitName($unitById[$homeId])]);
                } else {
                    $reason = str_replace('%1', $grade($cand), 'Grade %1, attendu pour ce poste. Sans unité pour le moment.');
                }
                $designate[] = self::designation($unit, $cand, $label($cand), $reason);
            } elseif ($code === 'leader_grade_below') {
                $current = (int) ($unit['commander_user_id'] ?? 0);
                $cand = $pick($issue);
                if ($cand < 1 || $current < 1) {
                    continue;
                }
                $name = self::unitName($unit);
                $replace[] = [
                    'id' => 'replace-' . $unitId . '-' . $cand,
                    'kind' => self::KIND_REPLACE,
                    'unit_id' => $unitId,
                    'user_id' => $cand,
                    'current_id' => $current,
                    'title' => strtr('Proposer %1 pour commander %2', ['%1' => $label($cand), '%2' => $name]),
                    'reason' => strtr('Poste tenu au grade %1, en dessous de celui attendu. %2 a le grade %3, attendu pour ce poste.', [
                        '%1' => $grade($current) !== '' ? $grade($current) : 'non renseigné',
                        '%2' => $label($cand),
                        '%3' => $grade($cand),
                    ]),
                    'action' => 'Remplacer le chef',
                    'confirm' => strtr('%1 remplacera %2 à la tête de %3. %2 garde son affectation et n’est plus chef de cette unité.', [
                        '%1' => $label($cand),
                        '%2' => $label($current),
                        '%3' => $name,
                    ]),
                ];
            }
        }

        // Sans référentiel de grades pour l’unité : le seul membre d’une unité sans chef, s’il ne commande rien d’autre.
        foreach ($units as $u) {
            $id = (int) $u['id'];
            if ((int) ($u['commander_user_id'] ?? 0) > 0 || self::hasProposalFor($designate, $id)) {
                continue;
            }
            $ids = array_values(array_filter(array_map('intval', is_array($u['member_ids'] ?? null) ? $u['member_ids'] : [])));
            if (count($ids) !== 1) {
                continue;
            }
            $only = $ids[0];
            if (isset($used[$only]) || ($commandCount[$only] ?? 0) > 0 || !$isActive($only)) {
                continue;
            }
            $used[$only] = true;
            $designate[] = self::designation($u, $only, $label($only), 'Seul membre de l’unité, et ne commande aucune autre unité.');
        }

        // Chef sans affectation : sa seule unité connue est celle qu’il commande.
        $assign = [];
        $commandedBy = [];
        foreach ($units as $u) {
            $cid = (int) ($u['commander_user_id'] ?? 0);
            if ($cid > 0) {
                $commandedBy[$cid][] = (int) $u['id'];
            }
        }
        foreach ($commandedBy as $cid => $list) {
            if (count($list) !== 1 || !isset($memberById[$cid]) || (int) ($memberById[$cid]['unit_id'] ?? 0) > 0 || !$isActive($cid)) {
                continue;
            }
            $unit = $unitById[$list[0]];
            $name = self::unitName($unit);
            $assign[] = [
                'id' => 'assign-' . $cid . '-' . (int) $unit['id'],
                'kind' => self::KIND_ASSIGN,
                'unit_id' => (int) $unit['id'],
                'user_id' => $cid,
                'current_id' => 0,
                'title' => strtr('Affecter %1 à %2', ['%1' => $label($cid), '%2' => $name]),
                'reason' => 'Chef de cette unité, mais sans affectation : les listes le montrent sans unité.',
                'action' => 'Affecter à cette unité',
                'confirm' => strtr('%1 sera affecté à %2, l’unité qu’il commande. Le changement rejoint son historique d’affectation.', ['%1' => $label($cid), '%2' => $name]),
            ];
        }

        return array_values(array_merge($designate, $assign, $replace));
    }

    /**
     * Candidats du moteur d’adéquation par unité, mis en avant dans le choix du chef.
     *
     * @param array<int, array<string, mixed>> $unitById
     * @param array<string, mixed>|null $adequacy
     * @param callable(int): bool $isActive
     * @return array<int, list<int>>
     */
    private static function suggested(array $unitById, ?array $adequacy, callable $isActive): array
    {
        $out = [];
        if (empty($adequacy['enabled']) || !is_array($adequacy['issues'] ?? null)) {
            return $out;
        }
        foreach ($adequacy['issues'] as $issue) {
            $unitId = (int) ($issue['unit_id'] ?? 0);
            if (!isset($unitById[$unitId]) || !in_array((string) ($issue['code'] ?? ''), ['leader_vacant', 'leader_grade_below'], true)) {
                continue;
            }
            $ids = array_values(array_filter(array_map('intval', is_array($issue['candidates'] ?? null) ? $issue['candidates'] : []), $isActive));
            if ($ids !== []) {
                $out[$unitId] = $ids;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $unit
     * @return array<string, mixed>
     */
    private static function designation(array $unit, int $userId, string $userLabel, string $reason): array
    {
        $name = self::unitName($unit);

        return [
            'id' => 'designate-' . (int) $unit['id'] . '-' . $userId,
            'kind' => self::KIND_DESIGNATE,
            'unit_id' => (int) $unit['id'],
            'user_id' => $userId,
            'current_id' => 0,
            'title' => strtr('Nommer %1 chef de %2', ['%1' => $userLabel, '%2' => $name]),
            'reason' => $reason,
            'action' => 'Nommer chef',
            'confirm' => strtr('%1 deviendra chef de %2. Les membres de l’unité remonteront à lui.', ['%1' => $userLabel, '%2' => $name]),
        ];
    }

    /** @param list<array<string, mixed>> $proposals */
    private static function hasProposalFor(array $proposals, int $unitId): bool
    {
        foreach ($proposals as $p) {
            if ((int) $p['unit_id'] === $unitId) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $unit */
    private static function unitName(array $unit): string
    {
        $name = trim((string) ($unit['name'] ?? ''));

        return $name !== '' ? $name : 'Unité';
    }

    /**
     * @param array<string, mixed> $unit
     * @param array<int, array<string, mixed>> $unitById
     */
    private static function where(array $unit, array $unitById): string
    {
        $trail = [];
        $seen = [];
        $pid = (int) ($unit['parent_id'] ?? 0);
        while ($pid > 0 && isset($unitById[$pid]) && !isset($seen[$pid]) && count($trail) < 4) {
            $seen[$pid] = true;
            array_unshift($trail, self::unitName($unitById[$pid]));
            $pid = (int) ($unitById[$pid]['parent_id'] ?? 0);
        }

        return $trail === [] ? 'Sommet de l’organigramme' : 'Rattachée à ' . implode(' › ', $trail);
    }

    /**
     * Libellé du premier chef au-dessus de l’unité, chaîne vide s’il n’y en a pas.
     *
     * @param array<string, mixed> $unit
     * @param array<int, array<string, mixed>> $unitById
     */
    private static function nearestChief(array $unit, array $unitById): string
    {
        $seen = [];
        $pid = (int) ($unit['parent_id'] ?? 0);
        while ($pid > 0 && isset($unitById[$pid]) && !isset($seen[$pid])) {
            $seen[$pid] = true;
            if ((int) ($unitById[$pid]['commander_user_id'] ?? 0) > 0) {
                return trim((string) ($unitById[$pid]['commander_label'] ?? ''));
            }
            $pid = (int) ($unitById[$pid]['parent_id'] ?? 0);
        }

        return '';
    }
}
