<?php

declare(strict_types=1);

namespace App\Services\Organization;

use App\Services\Effectifs\PersonnelCommandChainService;

/**
 * Adéquation de l’organisation : chaque personne à sa place, chaque poste tenu au niveau attendu.
 *
 * Moteur pur et déterministe (aucun accès base) : mêmes données → mêmes écarts, dans le même ordre. Chaque écart
 * porte une explication en clair et des actions. Il décrit la capacité de commandement d’un poste au regard de
 * l’échelon tenu, jamais une appréciation de la personne.
 *
 * Familles d’écarts :
 * - sous-capacité de commandement : grade, qualification de commandement ou emploi du chef (ou de l’adjoint)
 *   inférieurs à ce qu’attend l’échelon ;
 * - surqualification : grade nettement au-dessus de la plage attendue (marge réglable), ou qualifications de
 *   commandement d’un échelon supérieur ;
 * - structure : poste de chef vacant, adjoint attendu manquant, effectif hors plage, sous-unités manquantes,
 *   échelon non renseigné ;
 * - place : personne sans place dans l’organisation, ou sans chef direct.
 */
final class OrgAdequacyEngine
{
    public const PLACE_LEADER = 'chef';

    public const PLACE_DEPUTY = 'adjoint';

    public const PLACE_MEMBER = 'membre';

    public const PLACE_NONE = 'sans_place';

    public const STATUS_OK = 'ok';

    public const STATUS_UNDER = 'sous_capacite';

    public const STATUS_OVER = 'surqualifie';

    public const STATUS_NO_PLACE = 'sans_place';

    public const STATUS_NO_CHIEF = 'sans_chef';

    public const STATUS_UNKNOWN = 'non_evalue';

    public const KIND_UNDER = 'under';

    public const KIND_OVER = 'over';

    public const KIND_STRUCTURE = 'structure';

    public const KIND_PLACE = 'place';

    public const KIND_INFO = 'info';

    /** Ordre d’affichage des écarts (le plus urgent d’abord). */
    private const ORDER = [
        'leader_vacant' => 10, 'leader_grade_below' => 20, 'leader_qualification_missing' => 21, 'leader_function_missing' => 22,
        'deputy_missing' => 30, 'deputy_grade_below' => 31, 'without_place' => 40, 'without_chief' => 41,
        'leader_overqualified' => 50, 'deputy_overqualified' => 51, 'member_overqualified' => 52, 'qualified_above' => 53,
        'under_strength' => 60, 'over_strength' => 61, 'subunits_missing' => 62,
        'leader_grade_unknown' => 70, 'grade_not_comparable' => 71, 'echelon_unknown' => 72,
    ];

    private const TITLES = [
        'leader_vacant' => 'Chef à désigner',
        'leader_grade_below' => 'Sous-capacité de commandement',
        'leader_qualification_missing' => 'Sous-capacité de commandement',
        'leader_function_missing' => 'Sous-capacité de commandement',
        'deputy_missing' => 'Adjoint manquant',
        'deputy_grade_below' => 'Sous-capacité de commandement',
        'without_place' => 'Sans place',
        'without_chief' => 'Sans chef',
        'leader_overqualified' => 'Surqualification',
        'deputy_overqualified' => 'Surqualification',
        'member_overqualified' => 'Surqualification',
        'qualified_above' => 'Surqualification',
        'under_strength' => 'Sous-effectif',
        'over_strength' => 'Sureffectif',
        'subunits_missing' => 'Sous-unités manquantes',
        'leader_grade_unknown' => 'Grade à renseigner',
        'grade_not_comparable' => 'Grade à renseigner',
        'echelon_unknown' => 'Échelon à renseigner',
    ];

    private const KINDS = [
        'leader_vacant' => self::KIND_STRUCTURE, 'leader_grade_below' => self::KIND_UNDER, 'leader_qualification_missing' => self::KIND_UNDER,
        'leader_function_missing' => self::KIND_UNDER, 'deputy_missing' => self::KIND_STRUCTURE, 'deputy_grade_below' => self::KIND_UNDER,
        'without_place' => self::KIND_PLACE, 'without_chief' => self::KIND_PLACE, 'leader_overqualified' => self::KIND_OVER,
        'deputy_overqualified' => self::KIND_OVER, 'member_overqualified' => self::KIND_OVER, 'qualified_above' => self::KIND_OVER,
        'under_strength' => self::KIND_STRUCTURE, 'over_strength' => self::KIND_STRUCTURE, 'subunits_missing' => self::KIND_STRUCTURE,
        'leader_grade_unknown' => self::KIND_INFO, 'grade_not_comparable' => self::KIND_INFO, 'echelon_unknown' => self::KIND_INFO,
    ];

    private const WARN = ['leader_vacant', 'leader_grade_below', 'leader_qualification_missing', 'leader_function_missing', 'deputy_missing', 'deputy_grade_below', 'without_place'];

    /** @return array<string, string> */
    public static function titles(): array
    {
        return self::TITLES;
    }

    /**
     * @param array<string, mixed> $input voir la doc de la classe et OrgAdequacyService::loadInput()
     * @return array{
     *   enabled: bool, profile: string,
     *   units: array<int, array<string, mixed>>,
     *   people: array<int, array<string, mixed>>,
     *   issues: list<array<string, mixed>>,
     *   summary: array<string, int>
     * }
     */
    public static function evaluate(array $input): array
    {
        $settings = OrgAdequacySettings::sanitize($input['settings'] ?? []);
        $profile = EchelonStandards::sanitizeProfile($input['profile'] ?? EchelonStandards::PROFILE_FR);
        $standards = is_array($input['standards'] ?? null) ? $input['standards'] : OrgAdequacySettings::effectiveStandards($settings, $profile);
        $empty = [
            'enabled' => (bool) $settings['enabled'],
            'profile' => $profile,
            'units' => [],
            'people' => [],
            'issues' => [],
            'summary' => self::summarize([], []),
        ];
        if (empty($settings['enabled'])) {
            return $empty;
        }
        $rules = $settings['rules'];
        $margin = (int) $settings['overqualification_margin'];

        $unitsById = [];
        foreach (is_array($input['units'] ?? null) ? $input['units'] : [] as $u) {
            $id = (int) ($u['id'] ?? 0);
            if ($id > 0) {
                $unitsById[$id] = $u;
            }
        }
        $people = [];
        foreach (is_array($input['people'] ?? null) ? $input['people'] : [] as $uid => $p) {
            if ((int) $uid > 0 && is_array($p)) {
                $people[(int) $uid] = $p;
            }
        }
        $membersByUnit = [];
        foreach (is_array($input['members_by_unit'] ?? null) ? $input['members_by_unit'] : [] as $unitId => $ids) {
            $membersByUnit[(int) $unitId] = array_values(array_unique(array_map('intval', is_array($ids) ? $ids : [])));
        }
        $primary = array_map('intval', is_array($input['primary_unit'] ?? null) ? $input['primary_unit'] : []);
        $deputies = [];
        foreach (is_array($input['deputies'] ?? null) ? $input['deputies'] : [] as $unitId => $ids) {
            $deputies[(int) $unitId] = array_values(array_unique(array_map('intval', is_array($ids) ? $ids : [])));
        }
        $quals = is_array($input['qualifications'] ?? null) ? $input['qualifications'] : [];
        $jobs = is_array($input['job_roles'] ?? null) ? $input['job_roles'] : [];
        $qualLabels = is_array($input['qualification_labels'] ?? null) ? $input['qualification_labels'] : [];
        $jobLabels = is_array($input['job_role_labels'] ?? null) ? $input['job_role_labels'] : [];
        $gradeLabels = is_array($input['grade_labels_by_level'] ?? null) ? $input['grade_labels_by_level'] : [];
        $roleNames = is_array($input['role_names'] ?? null) ? $input['role_names'] : [];
        // Toute personne citée (chef, adjoint, membre) est évaluée, même absente de la liste fournie.
        foreach ($unitsById as $u) {
            $cid = (int) ($u['commander_user_id'] ?? 0);
            if ($cid > 0) {
                $people[$cid] ??= ['label' => 'Membre'];
            }
        }
        foreach ($membersByUnit as $ids) {
            foreach ($ids as $mid) {
                $people[$mid] ??= ['label' => 'Membre'];
            }
        }
        foreach ($deputies as $ids) {
            foreach ($ids as $did) {
                $people[$did] ??= ['label' => 'Membre'];
            }
        }
        ksort($people);
        ksort($unitsById);

        $children = [];
        foreach ($unitsById as $id => $u) {
            $pid = (int) ($u['parent_id'] ?? 0);
            if ($pid > 0 && isset($unitsById[$pid]) && $pid !== $id) {
                $children[$pid][] = $id;
            }
        }
        $stdOf = [];
        foreach ($unitsById as $id => $u) {
            $key = EchelonStandards::standardKey((string) ($u['echelon'] ?? ''));
            $std = $key !== null ? ($standards[$key] ?? null) : null;
            $stdOf[$id] = is_array($std) && !empty($std['enabled']) ? $std : null;
        }
        $subtree = [];
        $collect = static function (int $id, array $seen = []) use (&$collect, &$subtree, $unitsById, $children, $membersByUnit, $deputies): array {
            if (isset($subtree[$id])) {
                return $subtree[$id];
            }
            if (isset($seen[$id]) || count($seen) > 32) {
                return [];
            }
            $seen[$id] = true;
            $set = [];
            foreach ($membersByUnit[$id] ?? [] as $m) {
                $set[$m] = true;
            }
            foreach ($deputies[$id] ?? [] as $d) {
                $set[$d] = true;
            }
            $cid = (int) ($unitsById[$id]['commander_user_id'] ?? 0);
            if ($cid > 0) {
                $set[$cid] = true;
            }
            foreach ($children[$id] ?? [] as $c) {
                $set += $collect($c, $seen);
            }

            return $subtree[$id] = $set;
        };
        foreach (array_keys($unitsById) as $id) {
            $collect($id);
        }
        $commandedByUser = PersonnelCommandChainService::commandedUnitIdsByUser($unitsById);
        $deputyOf = [];
        foreach ($deputies as $unitId => $ids) {
            $cid = (int) ($unitsById[$unitId]['commander_user_id'] ?? 0);
            $deputies[$unitId] = array_values(array_filter($ids, static fn (int $d): bool => $d !== $cid && $d > 0));
            foreach ($deputies[$unitId] as $d) {
                $deputyOf[$d][] = $unitId;
            }
        }

        $levelOf = static fn (int $uid): ?int => isset($people[$uid]['grade_level']) && $people[$uid]['grade_level'] !== null
            ? (int) $people[$uid]['grade_level'] : null;
        $label = static fn (int $uid): string => trim((string) ($people[$uid]['label'] ?? '')) !== '' ? trim((string) $people[$uid]['label']) : 'Membre';
        $gradeText = static function (int $uid) use ($people): string {
            $g = trim((string) ($people[$uid]['grade_label'] ?? ''));

            return $g !== '' ? $g : 'sans grade';
        };
        $holdsAll = static function (int $uid, array $ids) use ($quals): bool {
            $held = array_map('intval', is_array($quals[$uid] ?? null) ? $quals[$uid] : []);
            foreach ($ids as $id) {
                if (!in_array((int) $id, $held, true)) {
                    return false;
                }
            }

            return true;
        };
        $candidates = static function (array $range, array $exclude, int $unitId, array $qualIds) use ($people, $commandedByUser, $levelOf, $label, $subtree, $holdsAll): array {
            $list = [];
            foreach ($people as $uid => $_p) {
                if (isset($exclude[$uid]) || ($commandedByUser[$uid] ?? []) !== []) {
                    continue;
                }
                $lv = $levelOf($uid);
                if ($lv === null || $lv < $range[0] || $lv > $range[1]) {
                    continue;
                }
                if ($qualIds !== [] && !$holdsAll($uid, $qualIds)) {
                    continue;
                }
                $list[] = [$uid, isset($subtree[$unitId][$uid]) ? 0 : 1, -$lv, mb_strtolower($label($uid))];
            }
            usort($list, static fn (array $a, array $b): int => [$a[1], $a[2], $a[3], $a[0]] <=> [$b[1], $b[2], $b[3], $b[0]]);

            return array_map(static fn (array $r): int => $r[0], array_slice($list, 0, 5));
        };

        $issues = [];
        $add = static function (string $code, array $values, int $unitId, int $userId, array $actions = [], array $candidateIds = []) use (&$issues): void {
            $issues[] = [
                'code' => $code,
                'kind' => self::KINDS[$code],
                'tone' => in_array($code, self::WARN, true) ? 'warn' : 'info',
                'title' => self::TITLES[$code],
                'text' => self::text($code, $values),
                'unit_id' => $unitId,
                'user_id' => $userId,
                'candidates' => $candidateIds,
                'actions' => $actions,
            ];
        };

        $unitOut = [];
        foreach ($unitsById as $id => $u) {
            $std = $stdOf[$id];
            $name = trim((string) ($u['name'] ?? '')) !== '' ? trim((string) $u['name']) : 'Unité';
            $cid = (int) ($u['commander_user_id'] ?? 0);
            $strength = count($subtree[$id] ?? []);
            $deputyId = 0;
            $deputySource = '';
            $leaderLabel = $std !== null ? (string) $std['leader_label'] : 'Chef';
            if ($std === null) {
                if (!empty($rules['echelon_unknown']) && trim((string) ($u['echelon'] ?? '')) === '' && $strength > 0) {
                    $add('echelon_unknown', [$name], $id, 0, [['key' => 'structure', 'unit_id' => $id]]);
                }
                if (!empty($rules['vacancy']) && $cid < 1) {
                    $add('leader_vacant', [$leaderLabel, $name], $id, 0, [['key' => 'designate', 'unit_id' => $id]]);
                }
            } else {
                $range = $std['leader_grade'];
                $people4 = GradeLevel::rangeLabel((int) $range[0], (int) $range[1], $gradeLabels);
                $strengthText = self::rangeText($std['strength']);
                if ($cid < 1) {
                    if (!empty($rules['vacancy'])) {
                        $cands = $candidates($range, [], $id, $std['qualification_ids']);
                        $add('leader_vacant', [$leaderLabel, $name], $id, 0, [['key' => 'designate', 'unit_id' => $id]], $cands);
                    }
                } else {
                    $lv = $levelOf($cid);
                    $hasGrade = !empty($people[$cid]['has_grade']);
                    if (!$hasGrade) {
                        if (!empty($rules['grade_below'])) {
                            $add('leader_grade_unknown', [$leaderLabel, $label($cid)], $id, $cid, [['key' => 'member', 'user_id' => $cid]]);
                        }
                    } elseif ($lv === null) {
                        if (!empty($rules['grade_below'])) {
                            $add('grade_not_comparable', [$leaderLabel, $gradeText($cid), $label($cid)], $id, $cid, [['key' => 'grades']]);
                        }
                    } elseif ($lv < (int) $range[0]) {
                        if (!empty($rules['grade_below'])) {
                            $cands = $candidates($range, [$cid => true], $id, $std['qualification_ids']);
                            $add('leader_grade_below', [$leaderLabel, $label($cid), $gradeText($cid), $people4, $strengthText], $id, $cid, [
                                ['key' => 'advancement', 'user_id' => $cid],
                                ['key' => 'reassign', 'unit_id' => $id, 'user_id' => $cid],
                            ], $cands);
                        }
                    } elseif ($lv >= (int) $range[1] + $margin) {
                        if (!empty($rules['grade_above'])) {
                            $add('leader_overqualified', [$leaderLabel, $label($cid), $gradeText($cid), $people4], $id, $cid, [
                                ['key' => 'reassign', 'unit_id' => $id, 'user_id' => $cid],
                            ]);
                        }
                    }
                    if (!empty($rules['qualifications']) && $std['qualification_ids'] !== [] && !$holdsAll($cid, $std['qualification_ids'])) {
                        $held = array_map('intval', is_array($quals[$cid] ?? null) ? $quals[$cid] : []);
                        $missing = [];
                        foreach ($std['qualification_ids'] as $qid) {
                            if (!in_array((int) $qid, $held, true)) {
                                $missing[] = (string) ($qualLabels[(int) $qid] ?? ('#' . (int) $qid));
                            }
                        }
                        $add('leader_qualification_missing', [$leaderLabel, $label($cid), implode(', ', $missing)], $id, $cid, [
                            ['key' => 'qualification', 'user_id' => $cid],
                        ]);
                    }
                    if (!empty($rules['functions']) && $std['job_role_ids'] !== []) {
                        $heldJobs = array_map('intval', is_array($jobs[$cid] ?? null) ? $jobs[$cid] : []);
                        if (array_intersect($heldJobs, array_map('intval', $std['job_role_ids'])) === []) {
                            $names = array_map(static fn (int $j): string => (string) ($jobLabels[$j] ?? ('#' . $j)), array_map('intval', $std['job_role_ids']));
                            $add('leader_function_missing', [$leaderLabel, $label($cid), implode(' / ', $names)], $id, $cid, [
                                ['key' => 'job', 'user_id' => $cid],
                            ]);
                        }
                    }
                }

                // Adjoint : poste d’adjoint pourvu, sinon (groupe) chef de sous-unité le plus ancien, adjoint de fait.
                $explicit = $deputies[$id] ?? [];
                usort($explicit, static fn (int $a, int $b): int => [-(int) ($levelOf($a) ?? 0), $a] <=> [-(int) ($levelOf($b) ?? 0), $b]);
                $deputyRange = is_array($std['deputy_grade'] ?? null) ? $std['deputy_grade'] : null;
                $deputyLabel = (string) $std['deputy_label'];
                if ($explicit !== []) {
                    $deputyId = $explicit[0];
                    $deputySource = 'post';
                    $dl = $levelOf($deputyId);
                    if ($deputyRange !== null && $dl !== null) {
                        $dText = GradeLevel::rangeLabel((int) $deputyRange[0], (int) $deputyRange[1], $gradeLabels);
                        if ($dl < (int) $deputyRange[0] && !empty($rules['grade_below'])) {
                            $add('deputy_grade_below', [$deputyLabel, $label($deputyId), $gradeText($deputyId), $dText], $id, $deputyId, [
                                ['key' => 'advancement', 'user_id' => $deputyId],
                                ['key' => 'deputy', 'unit_id' => $id],
                            ], $candidates($deputyRange, [$deputyId => true, $cid => true], $id, []));
                        } elseif ($dl >= (int) $deputyRange[1] + $margin && !empty($rules['grade_above'])) {
                            $add('deputy_overqualified', [$deputyLabel, $label($deputyId), $gradeText($deputyId), $dText], $id, $deputyId, [
                                ['key' => 'reassign', 'unit_id' => $id, 'user_id' => $deputyId],
                            ]);
                        }
                    }
                } elseif (!empty($std['deputy_required'])) {
                    $best = 0;
                    if (!empty($std['deputy_from_subunit'])) {
                        foreach ($children[$id] ?? [] as $c) {
                            $sub = (int) ($unitsById[$c]['commander_user_id'] ?? 0);
                            if ($sub > 0 && $sub !== $cid && ($best === 0 || [(int) ($levelOf($sub) ?? 0), -$sub] > [(int) ($levelOf($best) ?? 0), -$best])) {
                                $best = $sub;
                            }
                        }
                    }
                    if ($best > 0) {
                        $deputyId = $best;
                        $deputySource = 'subunit';
                    } elseif (!empty($rules['deputy'])) {
                        $add('deputy_missing', [$deputyLabel, $name], $id, 0, [['key' => 'deputy', 'unit_id' => $id]],
                            $deputyRange !== null ? $candidates($deputyRange, [$cid => true], $id, []) : []);
                    }
                }

                if (!empty($rules['strength'])) {
                    [$min, $max] = $std['strength'];
                    if ($min > 0 && $strength < $min) {
                        $add('under_strength', [(string) $strength, (string) $std['label'], $strengthText], $id, 0, [['key' => 'reassign', 'unit_id' => $id]]);
                    } elseif ($max > 0 && $strength > $max) {
                        $add('over_strength', [(string) $strength, (string) $std['label'], $strengthText], $id, 0, [['key' => 'structure', 'unit_id' => $id]]);
                    }
                }
                if (!empty($rules['subunits']) && (int) $std['subunits'][0] > 0) {
                    $expected = $std['subunit_echelons'];
                    $have = 0;
                    foreach ($children[$id] ?? [] as $c) {
                        $ck = EchelonStandards::standardKey((string) ($unitsById[$c]['echelon'] ?? ''));
                        if ($ck !== null && in_array($ck, $expected, true)) {
                            $have++;
                        }
                    }
                    if ($have < (int) $std['subunits'][0]) {
                        $names = [];
                        foreach ($expected as $ek) {
                            $names[] = (string) ($standards[$ek]['label'] ?? $ek);
                        }
                        $add('subunits_missing', [(string) $have, self::rangeText($std['subunits']), implode(' / ', $names)], $id, 0, [['key' => 'structure', 'unit_id' => $id]]);
                    }
                }
            }
            $unitOut[$id] = [
                'unit_id' => $id,
                'name' => $name,
                'echelon' => (string) ($u['echelon'] ?? ''),
                'standard_key' => $std !== null ? (string) $std['key'] : '',
                'standard_label' => $std !== null ? (string) $std['label'] : '',
                'leader_label' => $leaderLabel,
                'deputy_label' => $std !== null ? (string) $std['deputy_label'] : '',
                'commander_id' => $cid,
                'deputy_id' => $deputyId,
                'deputy_source' => $deputySource,
                'strength' => $strength,
                'strength_expected' => $std !== null ? self::rangeText($std['strength']) : '',
                'issues' => [],
                'status' => self::STATUS_OK,
            ];
        }

        $personOut = [];
        foreach ($people as $uid => $p) {
            $commands = $commandedByUser[$uid] ?? [];
            $unitId = (int) ($primary[$uid] ?? 0);
            if ($commands !== []) {
                $place = self::PLACE_LEADER;
            } elseif (($deputyOf[$uid] ?? []) !== []) {
                $place = self::PLACE_DEPUTY;
            } elseif ($unitId > 0 && isset($unitsById[$unitId])) {
                $place = self::PLACE_MEMBER;
            } else {
                $place = self::PLACE_NONE;
                $unitId = 0;
            }
            $chief = (int) (PersonnelCommandChainService::resolveImmediateSuperiorId($uid, $unitsById, $primary, $commandedByUser) ?? 0);
            // Adjoint qui concerne la personne : celui de l’unité qu’elle commande, sinon celui de son unité.
            $deputyFor = 0;
            $refUnit = $commands !== [] ? (int) $commands[0] : $unitId;
            if ($place === self::PLACE_DEPUTY) {
                $refUnit = (int) $deputyOf[$uid][0];
            }
            if ($refUnit > 0 && isset($unitOut[$refUnit])) {
                $d = (int) $unitOut[$refUnit]['deputy_id'];
                $deputyFor = $d !== $uid ? $d : 0;
            }
            // Rôles tenus : libellé du poste et unité, séparés pour l’affichage (et la traduction).
            $roles = [];
            foreach ($commands as $c) {
                $roles[] = ['label' => (string) ($unitOut[$c]['leader_label'] ?? 'Chef'), 'unit' => (string) ($unitOut[$c]['name'] ?? '')];
            }
            foreach ($deputyOf[$uid] ?? [] as $c) {
                $dl = trim((string) ($unitOut[$c]['deputy_label'] ?? ''));
                $roles[] = ['label' => $dl !== '' ? $dl : 'Adjoint', 'unit' => (string) ($unitOut[$c]['name'] ?? '')];
            }
            if ($roles === [] && $place === self::PLACE_MEMBER) {
                $rn = trim((string) ($roleNames[$uid] ?? ''));
                $roles[] = ['label' => $rn !== '' ? $rn : 'Membre', 'unit' => (string) ($unitOut[$unitId]['name'] ?? '')];
            }

            if (!empty($rules['placement'])) {
                if ($place === self::PLACE_NONE) {
                    $add('without_place', [], 0, $uid, [['key' => 'assign', 'user_id' => $uid]]);
                } elseif ($place !== self::PLACE_LEADER && $chief < 1) {
                    $add('without_chief', [(string) ($unitOut[$refUnit]['name'] ?? 'son unité')], $refUnit, $uid, [['key' => 'designate', 'unit_id' => $refUnit]]);
                }
            }
            $lv = $levelOf($uid);
            if (!empty($rules['grade_above']) && $lv !== null && $place === self::PLACE_MEMBER) {
                $std = $stdOf[$unitId] ?? null;
                if ($std !== null && $lv >= (int) $std['leader_grade'][1] + $margin) {
                    $add('member_overqualified', [(string) $unitOut[$unitId]['name'], $label($uid), $gradeText($uid),
                        GradeLevel::rangeLabel((int) $std['leader_grade'][0], (int) $std['leader_grade'][1], $gradeLabels)], $unitId, $uid, [
                        ['key' => 'reassign', 'unit_id' => $unitId, 'user_id' => $uid],
                    ]);
                }
            }
            // Qualifications de commandement d’un échelon nettement supérieur à celui tenu.
            if (!empty($rules['grade_above']) && $place !== self::PLACE_NONE) {
                $held = 0;
                foreach ($commands as $c) {
                    $held = max($held, (int) (($stdOf[$c]['level'] ?? 0)));
                }
                if ($commands === []) {
                    $held = max(0, (int) (($stdOf[$refUnit]['level'] ?? 1)) - 1);
                }
                $best = null;
                foreach ($standards as $s) {
                    if (empty($s['enabled']) || ($s['qualification_ids'] ?? []) === [] || (int) $s['level'] < $held + 2) {
                        continue;
                    }
                    if ($holdsAll($uid, $s['qualification_ids']) && ($best === null || (int) $s['level'] > (int) $best['level'])) {
                        $best = $s;
                    }
                }
                if ($best !== null) {
                    $names = array_map(static fn (int $q): string => (string) ($qualLabels[$q] ?? ('#' . $q)), array_map('intval', $best['qualification_ids']));
                    $add('qualified_above', [$label($uid), implode(', ', $names), (string) $best['label']], $refUnit, $uid, [
                        ['key' => 'reassign', 'unit_id' => $refUnit, 'user_id' => $uid],
                    ]);
                }
            }
            $personOut[$uid] = [
                'user_id' => $uid,
                'label' => $label($uid),
                'grade_label' => trim((string) ($p['grade_label'] ?? '')),
                'place' => $place,
                'unit_id' => $unitId,
                'commands' => array_values(array_map('intval', $commands)),
                'deputy_of' => array_values(array_map('intval', $deputyOf[$uid] ?? [])),
                'chief_id' => $chief,
                'deputy_id' => $deputyFor,
                'roles' => $roles,
                'issues' => [],
                'status' => self::STATUS_OK,
            ];
        }

        usort($issues, static fn (array $a, array $b): int => [self::ORDER[$a['code']], $a['unit_id'], $a['user_id']] <=> [self::ORDER[$b['code']], $b['unit_id'], $b['user_id']]);
        foreach ($issues as $i => $issue) {
            $issues[$i]['id'] = $i + 1;
            if ($issue['unit_id'] > 0 && isset($unitOut[$issue['unit_id']])) {
                $unitOut[$issue['unit_id']]['issues'][] = $i;
            }
            if ($issue['user_id'] > 0 && isset($personOut[$issue['user_id']])) {
                $personOut[$issue['user_id']]['issues'][] = $i;
            }
        }
        foreach ($personOut as $uid => $row) {
            $personOut[$uid]['status'] = self::statusFor(array_map(static fn (int $i): array => $issues[$i], $row['issues']), $row['place']);
        }
        foreach ($unitOut as $id => $row) {
            $unitOut[$id]['status'] = self::statusFor(array_map(static fn (int $i): array => $issues[$i], $row['issues']), self::PLACE_MEMBER);
        }

        return [
            'enabled' => true,
            'profile' => $profile,
            'units' => $unitOut,
            'people' => $personOut,
            'issues' => $issues,
            'summary' => self::summarize($issues, $personOut),
        ];
    }

    /** @param list<array<string, mixed>> $issues */
    private static function statusFor(array $issues, string $place): string
    {
        $codes = array_map(static fn (array $i): string => (string) $i['code'], $issues);
        if ($place === self::PLACE_NONE || in_array('without_place', $codes, true)) {
            return self::STATUS_NO_PLACE;
        }
        foreach ($issues as $i) {
            if ($i['kind'] === self::KIND_UNDER) {
                return self::STATUS_UNDER;
            }
        }
        foreach ($issues as $i) {
            if ($i['kind'] === self::KIND_OVER) {
                return self::STATUS_OVER;
            }
        }
        if (in_array('without_chief', $codes, true)) {
            return self::STATUS_NO_CHIEF;
        }
        foreach ($issues as $i) {
            if ($i['kind'] === self::KIND_INFO && in_array($i['code'], ['leader_grade_unknown', 'grade_not_comparable'], true)) {
                return self::STATUS_UNKNOWN;
            }
        }

        return $issues === [] ? self::STATUS_OK : 'ecart';
    }

    /**
     * @param list<array<string, mixed>> $issues
     * @param array<int, array<string, mixed>> $people
     * @return array<string, int>
     */
    private static function summarize(array $issues, array $people): array
    {
        $out = ['total' => count($issues), 'warn' => 0, 'under' => 0, 'over' => 0, 'vacant' => 0, 'deputy' => 0, 'strength' => 0,
            'subunits' => 0, 'no_place' => 0, 'no_chief' => 0, 'info' => 0, 'people' => count($people), 'people_with_issues' => 0];
        foreach ($issues as $i) {
            if ($i['tone'] === 'warn') {
                $out['warn']++;
            }
            match ($i['code']) {
                'leader_vacant' => $out['vacant']++,
                'deputy_missing' => $out['deputy']++,
                'under_strength', 'over_strength' => $out['strength']++,
                'subunits_missing' => $out['subunits']++,
                'without_place' => $out['no_place']++,
                'without_chief' => $out['no_chief']++,
                default => null,
            };
            if ($i['kind'] === self::KIND_UNDER) {
                $out['under']++;
            } elseif ($i['kind'] === self::KIND_OVER) {
                $out['over']++;
            } elseif ($i['kind'] === self::KIND_INFO) {
                $out['info']++;
            }
        }
        foreach ($people as $p) {
            if (($p['issues'] ?? []) !== []) {
                $out['people_with_issues']++;
            }
        }

        return $out;
    }

    /** @param array{0:int,1:int}|array<int,int> $range */
    public static function rangeText(array $range): string
    {
        $a = (int) ($range[0] ?? 0);
        $b = (int) ($range[1] ?? $a);

        return $a === $b ? (string) $a : $a . '–' . $b;
    }

    /**
     * Explication en clair d’un écart. Les modèles (%1, %2…) sont repris tels quels dans lang/en/site_auto.php.
     *
     * @param list<string> $values
     */
    public static function text(string $code, array $values): string
    {
        $template = match ($code) {
            'leader_vacant' => '%1 : poste à pourvoir, aucun chef n’est désigné pour %2.',
            'leader_grade_below' => '%1 : poste tenu par %2 (%3) ; %4 attendu pour commander %5 personnes.',
            'leader_overqualified' => '%1 : poste tenu par %2 (%3) ; ce grade correspond plutôt au commandement d’un échelon supérieur (%4 attendu ici).',
            'leader_grade_unknown' => '%1 : poste tenu par %2, sans grade enregistré ; l’adéquation au poste ne peut pas être évaluée.',
            'grade_not_comparable' => '%1 : le grade %2 de %3 n’a pas d’équivalence OTAN dans le référentiel des grades ; l’adéquation ne peut pas être évaluée.',
            'leader_qualification_missing' => '%1 : qualification de commandement attendue non détenue par %2 (%3).',
            'leader_function_missing' => '%1 : emploi attendu non attribué à %2 (%3).',
            'deputy_missing' => '%1 : adjoint attendu non désigné pour %2 ; en l’absence du chef, personne n’est identifié pour reprendre le commandement.',
            'deputy_grade_below' => '%1 : poste tenu par %2 (%3) ; %4 attendu à ce niveau.',
            'deputy_overqualified' => '%1 : poste tenu par %2 (%3) ; grade nettement au-dessus de celui attendu (%4).',
            'member_overqualified' => 'Membre de %1 : %2 (%3) a un grade nettement au-dessus de celui attendu pour le chef de cet échelon (%4) ; un poste à sa mesure reste à proposer.',
            'qualified_above' => 'Qualifications : %1 détient %2, attendu pour commander un échelon %3 ; son expérience pourrait servir plus haut.',
            'under_strength' => 'Effectif : %1 pour un échelon %2, %3 attendus ; l’unité reste utilisable avec moins de capacité.',
            'over_strength' => 'Effectif : %1 pour un échelon %2, %3 attendus ; le chef commande plus de monde que l’échelon ne le prévoit.',
            'subunits_missing' => 'Sous-unités : %1 sur %2 attendues (%3).',
            'echelon_unknown' => 'Échelon non renseigné pour %1 ; choisissez-le dans l’ORBAT pour vérifier chef, adjoint et effectif.',
            'without_place' => 'Sans place dans l’organisation : ni affectation, ni poste de chef ou d’adjoint.',
            'without_chief' => 'Sans chef direct : aucun chef n’est désigné pour %1 ni pour les unités au-dessus.',
            default => '',
        };
        $repl = [];
        foreach (array_values($values) as $i => $v) {
            $repl['%' . ($i + 1)] = (string) $v;
        }
        krsort($repl, SORT_NATURAL);

        return strtr($template, $repl);
    }
}
