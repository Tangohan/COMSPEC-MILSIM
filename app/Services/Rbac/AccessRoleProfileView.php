<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Core\Request;
use App\Repositories\RoleRepository;
use App\Services\Organization\GradeLevel;

/**
 * Données d'écran de la liste des niveaux (Effectifs > Accès) : cartes ordonnées et filtrées, chiffres clés de la bande,
 * catalogues de la fenêtre « Fiche du niveau ». Aucune lecture en base ici : tout vient de l'appelant.
 */
final class AccessRoleProfileView
{
    /** Champs de la fenêtre repris après un refus (jamais le jeton ni le fichier). */
    public const OLD_KEYS = ['role_id', 'name', 'description', 'role_color', 'profile_form', 'abbreviation', 'details', 'icon', 'tags',
        'qualifications', 'jobs', 'grade_min', 'grade_max', 'sort_order', 'logo_confirm', 'logo_remove', 'visual', 'template', 'copy_from', 'copy_profile'];

    /** @return array<string, mixed> */
    public static function oldInput(Request $request): array
    {
        $out = [];
        foreach (self::OLD_KEYS as $key) {
            $value = $request->input($key);
            if ($value !== null) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /** @return array{q: string, tag: int, qualif: string, archives: bool, incomplete: bool} */
    public static function filters(Request $request): array
    {
        $qualif = $request->queryString('qualif');

        return [
            'q' => mb_substr(trim($request->queryString('q')), 0, 80),
            'tag' => max(0, (int) $request->queryString('etiquette')),
            'qualif' => in_array($qualif, ['avec', 'sans'], true) ? $qualif : '',
            'archives' => $request->queryString('archives') === '1',
            'incomplete' => $request->queryString('incomplet') === '1',
        ];
    }

    /**
     * @param list<array<string, mixed>> $roles niveaux de la communauté (forTenantAccessProfiles)
     * @param array<int, array<string, mixed>> $profiles fiches (AccessRoleProfileService::profiles)
     * @param array<int, list<int>> $membersByRole titulaires par niveau
     * @param array<string, mixed> $ctx ready, tags_ready, qualifications, jobs, tags, grade_labels, old
     * @return array<string, mixed>
     */
    public static function data(Request $request, array $roles, array $profiles, array $membersByRole, array $ctx): array
    {
        $filters = self::filters($request);
        $quals = is_array($ctx['qualifications'] ?? null) ? $ctx['qualifications'] : [];
        $jobs = is_array($ctx['jobs'] ?? null) ? $ctx['jobs'] : [];
        $gradeLabels = is_array($ctx['grade_labels'] ?? null) ? $ctx['grade_labels'] : [];
        $cards = [];
        foreach (array_values($roles) as $i => $row) {
            $rid = (int) ($row['id'] ?? 0);
            if ($rid < 1) {
                continue;
            }
            $p = $profiles[$rid] ?? AccessRoleProfileService::emptyProfile();
            $qualLabels = [];
            $qualCount = 0;
            foreach (AccessRoleProfileService::KINDS as $kind => $_) {
                foreach ((array) ($p['qualifications'][$kind] ?? []) as $qid) {
                    if (isset($quals[(int) $qid])) {
                        $qualLabels[$kind][] = $quals[(int) $qid]['name'];
                        $qualCount++;
                    }
                }
            }
            $jobLabels = [];
            foreach ((array) $p['jobs'] as $jid) {
                if (isset($jobs[(int) $jid])) {
                    $jobLabels[] = $jobs[(int) $jid];
                }
            }
            $slug = strtolower((string) ($row['slug'] ?? ''));
            $cards[] = [
                'id' => $rid,
                'order' => [(int) $p['sort_order'], $i],
                'name' => (string) ($row['name'] ?? ''),
                'description' => trim((string) ($row['description'] ?? '')),
                'kind' => match (true) {
                    $slug === CommunityAccessProfiles::SLUG_MEMBER => 'member',
                    $slug === CommunityAccessProfiles::SLUG_MANAGER => 'manager',
                    CommunityAccessProfiles::isHideablePresetSlug($slug) => 'hideable',
                    default => 'custom',
                },
                'color' => AccessRoleColor::hexFor($row['badge_style'] ?? null),
                'color_input' => AccessRoleColor::inputFor($row['badge_style'] ?? null),
                'hidden' => RoleRepository::isHiddenAccessRow($row),
                'profile' => $p,
                'completeness' => AccessRoleProfileService::completeness($row, $p),
                'members' => count($membersByRole[$rid] ?? []),
                'qualification_labels' => $qualLabels,
                'qualification_count' => $qualCount,
                'job_labels' => $jobLabels,
                'grade_label' => AccessRoleProfileService::gradeRangeLabel($p['grade_min'], $p['grade_max'], $gradeLabels),
                'logo_url' => $p['logo'] !== null ? \App\Controllers\Admin\AccessRoleProfileController::logoUrl($rid, (int) $p['logo_version']) : null,
            ];
        }
        usort($cards, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        $covered = [];
        $kpis = ['levels' => 0, 'covered' => 0, 'to_complete' => 0, 'no_description' => 0, 'no_visual' => 0, 'no_qualification' => 0, 'archived' => 0];
        foreach ($cards as $c) {
            if (!empty($c['profile']['archived'])) {
                $kpis['archived']++;
                continue;
            }
            $kpis['levels']++;
            foreach ($membersByRole[$c['id']] ?? [] as $uid) {
                $covered[(int) $uid] = true;
            }
            $kpis['to_complete'] += $c['completeness']['done'] < $c['completeness']['total'] ? 1 : 0;
            $kpis['no_description'] += $c['description'] === '' ? 1 : 0;
            $kpis['no_visual'] += empty($c['profile']['icon']) && empty($c['profile']['logo']) ? 1 : 0;
            $kpis['no_qualification'] += $c['qualification_count'] === 0 ? 1 : 0;
        }
        $kpis['covered'] = count($covered);

        $visible = 0;
        foreach ($cards as &$c) {
            $c['visible'] = self::matches($c, $filters);
            $visible += $c['visible'] ? 1 : 0;
        }
        unset($c);

        $gradeOptions = [];
        for ($l = GradeLevel::MIN; $l <= GradeLevel::MAX; $l++) {
            $gradeOptions[$l] = GradeLevel::rangeLabel($l, $l, $gradeLabels);
        }
        $open = '';
        if ($request->queryString('nouveau') === '1') {
            $open = 'new';
        } elseif ($request->queryString('fiche') === '1') {
            $open = 'edit';
        }

        return [
            'rolReady' => !empty($ctx['ready']),
            'rolTagsReady' => !empty($ctx['tags_ready']),
            'rolCards' => $cards,
            'rolVisibleCount' => $visible,
            'rolKpis' => $kpis,
            'rolFilters' => $filters,
            'rolQualifications' => $quals,
            'rolJobs' => $jobs,
            'rolTags' => is_array($ctx['tags'] ?? null) ? $ctx['tags'] : [],
            'rolGradeOptions' => $gradeOptions,
            'rolOld' => is_array($ctx['old'] ?? null) ? $ctx['old'] : null,
            'rolOpen' => $open,
        ];
    }

    /**
     * @param array<string, mixed> $card
     * @param array{q: string, tag: int, qualif: string, archives: bool, incomplete: bool} $f
     */
    public static function matches(array $card, array $f): bool
    {
        if (!empty($card['profile']['archived']) && !$f['archives']) {
            return false;
        }
        if ($f['tag'] > 0 && !in_array($f['tag'], array_map(static fn (array $t): int => (int) $t['id'], (array) $card['profile']['tags']), true)) {
            return false;
        }
        if ($f['qualif'] === 'avec' && $card['qualification_count'] === 0) {
            return false;
        }
        if ($f['qualif'] === 'sans' && $card['qualification_count'] > 0) {
            return false;
        }
        if ($f['incomplete'] && $card['completeness']['done'] >= $card['completeness']['total']) {
            return false;
        }
        return $f['q'] === '' || str_contains(self::searchText($card), mb_strtolower($f['q'], 'UTF-8'));
    }

    /**
     * Texte cherché par le filtre (serveur et filtre instantané de la page) : nom, description, abrégé, étiquettes.
     *
     * @param array<string, mixed> $card
     */
    public static function searchText(array $card): string
    {
        return mb_strtolower(implode(' ', [
            $card['name'], $card['description'], (string) ($card['profile']['abbreviation'] ?? ''),
            implode(' ', array_map(static fn (array $t): string => (string) $t['name'], (array) $card['profile']['tags'])),
        ]), 'UTF-8');
    }
}
