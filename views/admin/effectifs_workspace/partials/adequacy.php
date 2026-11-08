<?php
declare(strict_types=1);

/*
 * Adéquation de l’organisation : pastilles et listes d’écarts communes (chaîne, fiche membre, alertes RH, fiche unité).
 * Résultat : App\Services\Organization\OrgAdequacyService::evaluate(). Styles : assets/css/org-adequacy.css.
 * Ton : capacité de commandement d’un poste au regard de l’échelon, jamais un jugement sur la personne.
 */

use App\Services\Organization\OrgAdequacyEngine;
use App\Services\Organization\OrgAdequacyService;

if (!function_exists('adq_e')) {
    function adq_e(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    }

    /** Feuille de styles, une seule fois par page. */
    function adq_styles(): string
    {
        static $done = false;
        if ($done) {
            return '';
        }
        $done = true;
        $href = function_exists('asset_url') ? asset_url('assets/css/org-adequacy.css') : 'assets/css/org-adequacy.css';

        return '<link rel="stylesheet" href="' . adq_e($href) . '">';
    }

    /** @return array{0: string, 1: string} libellé, ton */
    function adq_status_meta(string $status): array
    {
        return match ($status) {
            OrgAdequacyEngine::STATUS_OK => ['Adéquat', 'ok'],
            OrgAdequacyEngine::STATUS_UNDER => ['Sous-capacité de commandement', 'warn'],
            OrgAdequacyEngine::STATUS_OVER => ['Surqualifié pour le poste', 'info'],
            OrgAdequacyEngine::STATUS_NO_PLACE => ['Sans place', 'warn'],
            OrgAdequacyEngine::STATUS_NO_CHIEF => ['Sans chef', 'info'],
            OrgAdequacyEngine::STATUS_UNKNOWN => ['À évaluer', 'muted'],
            default => ['Écart à traiter', 'info'],
        };
    }

    function adq_place_label(string $place): string
    {
        return match ($place) {
            OrgAdequacyEngine::PLACE_LEADER => 'Chef',
            OrgAdequacyEngine::PLACE_DEPUTY => 'Adjoint',
            OrgAdequacyEngine::PLACE_MEMBER => 'Membre',
            default => 'Sans place',
        };
    }

    /**
     * Rôles tenus (« Chef de groupe · Groupe 1 ») : libellé et unité dans des éléments séparés.
     *
     * @param list<array{label?: string, unit?: string}|string> $roles
     */
    function adq_roles_html(array $roles): string
    {
        $out = [];
        foreach ($roles as $r) {
            $label = is_array($r) ? trim((string) ($r['label'] ?? '')) : trim((string) $r);
            $unit = is_array($r) ? trim((string) ($r['unit'] ?? '')) : '';
            if ($label === '') {
                continue;
            }
            $out[] = '<span>' . adq_e($label) . '</span>' . ($unit !== '' ? ' · <span>' . adq_e($unit) . '</span>' : '');
        }

        return implode(', ', $out);
    }

    function adq_badge(string $status, bool $compact = false): string
    {
        [$label, $tone] = adq_status_meta($status);
        if ($compact) {
            $label = match ($status) {
                OrgAdequacyEngine::STATUS_UNDER => 'Sous-capacité',
                OrgAdequacyEngine::STATUS_OVER => 'Surqualifié',
                default => $label,
            };
        }

        return '<span class="adq-badge is-' . adq_e($tone) . '" data-adq-status="' . adq_e($status) . '">' . adq_e($label) . '</span>';
    }

    /**
     * Un écart : titre, explication, candidats et actions.
     *
     * @param array<string, mixed> $issue
     * @param array<int, array<string, mixed>> $people OrgAdequacyService::evaluate()['people']
     * @param callable(int): string $memberUrl lien vers la fiche d’une personne
     */
    function adq_issue_html(array $issue, array $people, callable $memberUrl, bool $withActions = true): string
    {
        $tone = (string) ($issue['tone'] ?? 'info');
        $out = '<li class="adq-issue is-' . adq_e($tone) . '" data-adq-code="' . adq_e((string) ($issue['code'] ?? '')) . '" data-adq-unit="' . (int) ($issue['unit_id'] ?? 0) . '">'
            . '<b class="adq-issue__t">' . adq_e((string) ($issue['title'] ?? '')) . '</b>'
            . '<span class="adq-issue__x">' . adq_e((string) ($issue['text'] ?? '')) . '</span>';
        $cands = array_values(array_filter(array_map('intval', is_array($issue['candidates'] ?? null) ? $issue['candidates'] : [])));
        if ($withActions && in_array((string) ($issue['code'] ?? ''), ['leader_vacant', 'leader_grade_below', 'deputy_missing', 'deputy_grade_below'], true)) {
            if ($cands === []) {
                $out .= '<span class="adq-issue__c">Aucun candidat du grade attendu pour le moment.</span>';
            } else {
                $links = [];
                foreach ($cands as $cid) {
                    $links[] = '<a href="' . adq_e($memberUrl($cid)) . '">' . adq_e((string) ($people[$cid]['label'] ?? 'Membre')) . '</a>';
                }
                $out .= '<span class="adq-issue__c"><span>Candidats possibles :</span> ' . implode(', ', $links) . '</span>';
            }
        }
        if ($withActions) {
            $acts = [];
            foreach (is_array($issue['actions'] ?? null) ? $issue['actions'] : [] as $a) {
                $link = OrgAdequacyService::actionLink(is_array($a) ? $a : []);
                if ($link !== null) {
                    $acts[] = '<a class="adq-act" href="' . adq_e($link['href']) . '">' . adq_e($link['label']) . '</a>';
                }
            }
            if ($acts !== []) {
                $out .= '<span class="adq-issue__a">' . implode('', $acts) . '</span>';
            }
        }

        return $out . '</li>';
    }

    /**
     * @param list<array<string, mixed>> $issues
     * @param array<int, array<string, mixed>> $people
     */
    function adq_issue_list(array $issues, array $people, callable $memberUrl, bool $withActions = true, int $limit = 0): string
    {
        if ($issues === []) {
            return '';
        }
        $shown = $limit > 0 ? array_slice($issues, 0, $limit) : $issues;
        $out = '<ul class="adq-issues">';
        foreach ($shown as $issue) {
            $out .= adq_issue_html($issue, $people, $memberUrl, $withActions);
        }
        $out .= '</ul>';
        if ($limit > 0 && count($issues) > $limit) {
            $out .= '<p class="adq-more">+' . (count($issues) - $limit) . ' autres écarts</p>';
        }

        return $out;
    }

    /**
     * Pastilles de synthèse (compteurs non nuls).
     *
     * @param array<string, int> $summary
     * @return list<array{0: string, 1: int, 2: string}> libellé, nombre, ton
     */
    function adq_summary_items(array $summary): array
    {
        $items = [
            ['Sous-capacités de commandement', (int) ($summary['under'] ?? 0), 'warn'],
            ['Chefs à désigner', (int) ($summary['vacant'] ?? 0), 'warn'],
            ['Adjoints manquants', (int) ($summary['deputy'] ?? 0), 'warn'],
            ['Personnes sans place', (int) ($summary['no_place'] ?? 0), 'warn'],
            ['Surqualifications', (int) ($summary['over'] ?? 0), 'info'],
            ['Personnes sans chef', (int) ($summary['no_chief'] ?? 0), 'info'],
            ['Effectifs hors plage', (int) ($summary['strength'] ?? 0), 'info'],
            ['Sous-unités manquantes', (int) ($summary['subunits'] ?? 0), 'info'],
            ['Points à renseigner', (int) ($summary['info'] ?? 0), 'muted'],
        ];

        return array_values(array_filter($items, static fn (array $i): bool => $i[1] > 0));
    }
}
