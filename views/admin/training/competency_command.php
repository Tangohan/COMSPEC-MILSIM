<?php require base_path('views/admin/training/partials/command_shell_open.php'); ?>
<?php
/**
 * Pilotage des formations — vue commandement des compétences (charte Academy).
 * Où en sont les membres, les unités et les emplois sur les modules ALPHA → DELTA, quels écarts combler,
 * puis organisation de la formation (chaîne pédagogique) et groupes de suivi.
 *
 * @var array<string, mixed> $competencyBoard CompetencyCommandBoard::build
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$board = is_array($competencyBoard ?? null) ? $competencyBoard : [];
$kpi = (array) ($board['kpi'] ?? []);
$modules = (array) ($board['modules'] ?? []);
$gaps = (array) ($board['gaps'] ?? []);
$members = (array) ($board['members'] ?? []);
$unitsAgg = (array) ($board['units'] ?? []);
$jobsAgg = (array) ($board['jobs'] ?? []);
$gradesList = (array) ($board['grades'] ?? []);
$referenceAll = !empty($board['reference_all']);
$boardReady = !empty($board['schema_ready']) && empty($board['load_error']);
$canOpenMembers = !empty($competencyCanOpenMembers);

$competencyMatrices = $competencyMatrices ?? [];
$competencyUsers = $competencyUsers ?? [];
$competencyRoles = $competencyRoles ?? [];
$competencyRoleNameById = is_array($competencyRoleNameById ?? null) ? $competencyRoleNameById : [];
$competencyAssignmentsByMatrix = is_array($competencyAssignmentsByMatrix ?? null) ? $competencyAssignmentsByMatrix : [];
$competencyCandidateCountByMatrix = is_array($competencyCandidateCountByMatrix ?? null) ? $competencyCandidateCountByMatrix : [];
$competencyMatricesSchemaReady = !empty($competencyMatricesSchemaReady);
$pedagogyChainAssess = $pedagogyChainAssess ?? ['ok' => true, 'gaps' => []];
$pedagogySteps = is_array($pedagogySteps ?? null) ? $pedagogySteps : [];
$pedagogyDesignations = is_array($pedagogyDesignations ?? null) ? $pedagogyDesignations : [];
$matrixCount = count($competencyMatrices);
$assignedTotal = 0;
foreach ($competencyMatrices as $mRow) {
    $assignedTotal += (int) ($mRow['assignment_count'] ?? 0);
}

$statusLabels = [
    'COMPLETED' => 'Acquis',
    'IN_PROGRESS' => 'En cours',
    'NOT_STARTED' => 'Manquant',
    'FAILED' => 'À repasser',
    'EXPIRED' => 'Expiré',
];
$statusHints = [
    'COMPLETED' => 'Module validé par un encadrant et toujours en cours de validité.',
    'IN_PROGRESS' => 'Le membre a commencé le module, il n’est pas encore validé.',
    'NOT_STARTED' => 'Le membre n’a pas encore commencé ce module.',
    'FAILED' => 'Le module a été évalué mais pas validé : le membre doit le repasser.',
    'EXPIRED' => 'La validation a dépassé son échéance : le module est à renouveler.',
];
$statusIcons = ['COMPLETED' => '✓', 'IN_PROGRESS' => '◐', 'NOT_STARTED' => '', 'FAILED' => '✕', 'EXPIRED' => '!'];
$phaseLabels = \App\Services\Training\CompetencyModuleService::PHASE_LABELS;
$moduleById = [];
foreach ($modules as $m) {
    $moduleById[(int) $m['id']] = $m;
}
$unitByKey = [];
foreach ($unitsAgg as $u) {
    $unitByKey[(string) $u['key']] = $u;
}
$jobNameById = [];
foreach ($jobsAgg as $j) {
    $jobNameById[(int) $j['key']] = (string) $j['name'];
}
$groupNameById = [];
foreach ($competencyMatrices as $mRow) {
    $groupNameById[(int) ($mRow['id'] ?? 0)] = (string) ($mRow['name'] ?? '');
}
$unitLabel = static function (string $key) use ($unitByKey): string {
    if ($key === 'u0') {
        return 'Sans unité principale';
    }

    return (string) ($unitByKey[$key]['name'] ?? '');
};
$generatorUrl = url('academy/formations/competences');
$trackingUrl = static fn (int $id): string => training_lms_admin_url('competences/modules/' . $id . '/suivi');
$memberUrl = static fn (int $id): string => url('back-office/ressources/effectifs/membres/' . $id);
$qualificationsUrl = url('back-office/ressources/effectifs/qualifications');
$longDate = static function (string $ymd): string {
    $ts = strtotime($ymd);
    if ($ts === false) {
        return $ymd;
    }

    return (int) date('j', $ts) . ' ' . \App\Support\LocalizedCalendar::month((int) date('n', $ts)) . ' ' . date('Y', $ts);
};
$tone = static fn (int $p): string => $p >= 80 ? 'ok' : ($p >= 50 ? 'mid' : 'low');
$pct = (int) ($kpi['pct'] ?? 0);
$memberTotal = (int) ($kpi['members'] ?? 0);
$criticalModules = array_values(array_filter($gaps, static fn (array $g): bool => (int) $g['critical_gaps'] > 0));
$soonList = [];
foreach ($members as $r) {
    foreach ((array) $r['soon'] as $s) {
        $soonList[] = ['member' => $r, 'module' => $moduleById[(int) $s['module_id']] ?? null, 'date' => (string) $s['date']];
    }
}
usort($soonList, static fn (array $a, array $b): int => $a['date'] <=> $b['date']);

$parseMatrixRules = static function (array $m) use ($competencyRoleNameById): array {
    $rules = json_decode((string) ($m['auto_detect_rules_json'] ?? '{}'), true);
    if (!is_array($rules)) {
        $rules = [];
    }
    $rawRoleIds = $rules['role_ids_any'] ?? $rules['role_ids'] ?? [];
    $roleIds = array_values(array_unique(array_filter(array_map('intval', is_array($rawRoleIds) ? $rawRoleIds : []), static fn (int $v): bool => $v > 0)));
    $roleNames = [];
    foreach ($roleIds as $rid) {
        $roleNames[] = (string) ($competencyRoleNameById[$rid] ?? ('Rôle ' . $rid));
    }

    return ['role_names' => $roleNames, 'min_completed' => max(0, (int) ($rules['min_completed_courses'] ?? 0))];
};
?>
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/academy-workspace.css')) ?>">
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/competency-command.css')) ?>">
<script>
/* Mode nuit : même préférence que le back-office (Mon compte), appliquée avant l'affichage. */
(function () {
    var pref = 'auto';
    try { pref = localStorage.getItem('athena.bo.theme') || 'auto'; } catch (e) {}
    var dark = pref === 'dark' || (pref === 'auto' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.setAttribute('data-bo-theme', dark ? 'dark' : 'light');
})();
</script>

<div class="academy-workspace ccm" data-ccm>

<header class="ccm-hero" style="--img: url('<?= $h(asset_url('assets/images/academy/cover-night.webp')) ?>')">
    <div class="ccm-hero__in">
        <div class="ccm-hero__txt">
            <p class="ccm-kicker"><i aria-hidden="true"></i>Pilotage des formations · Vue commandement</p>
            <h1>Compétences de la communauté</h1>
            <p>Qui maîtrise quoi, unité par unité. Repérez les manques, prévoyez les renouvellements, puis envoyez les membres vers la bonne formation.</p>
        </div>
        <div class="ccm-hero__act">
            <a class="btn pri" href="#ccm-membres">Voir les membres</a>
            <a class="btn ccm-btn-ghost" href="<?= $h(training_lms_admin_url('competences/modules')) ?>">Gérer les modules</a>
        </div>
    </div>
    <dl class="ccm-kpis">
        <div title="Part des modules de référence validés, tous membres confondus.">
            <dt>Couverture</dt>
            <dd class="<?= $tone($pct) ?>"><?= $pct ?> %</dd>
            <small><?= (int) ($kpi['ref_done'] ?? 0) ?> validations sur <?= (int) ($kpi['ref_total'] ?? 0) ?> attendues</small>
        </div>
        <div title="Module de référence expiré ou à repasser, ou module critique pas encore acquis.">
            <dt>Écarts critiques</dt>
            <dd class="<?= (int) ($kpi['critical'] ?? 0) > 0 ? 'low' : 'ok' ?>"><?= (int) ($kpi['critical'] ?? 0) ?></dd>
            <small><?= (int) ($kpi['members_critical'] ?? 0) ?> membre(s) concerné(s)</small>
        </div>
        <div title="Membres ayant au moins un module commencé et pas encore validé.">
            <dt>En progression</dt>
            <dd><?= (int) ($kpi['members_progress'] ?? 0) ?></dd>
            <small>membre(s) sur <?= $memberTotal ?></small>
        </div>
        <div title="Validations qui arrivent à échéance dans les 30 prochains jours.">
            <dt>À renouveler bientôt</dt>
            <dd class="<?= (int) ($kpi['soon'] ?? 0) > 0 ? 'mid' : '' ?>"><?= (int) ($kpi['soon'] ?? 0) ?></dd>
            <small>dans les 30 jours</small>
        </div>
    </dl>
</header>

<details class="ccm-guide" id="ccm-guide">
    <summary>
        <span class="ccm-guide__ico" aria-hidden="true">?</span>
        <span><b>Comment lire cette page</b><small>À quoi elle sert, pour qui, et ce que veulent dire les chiffres.</small></span>
        <svg class="i" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
    </summary>
    <div class="ccm-guide__grid">
        <section>
            <h3>À quoi sert cette vue</h3>
            <ul>
                <li>Voir d’un coup d’œil si les membres ont les compétences attendues : les modules ALPHA → DELTA de la communauté.</li>
                <li>Trouver les manques à traiter en premier et la formation Academy qui les comble.</li>
                <li>Organiser qui conçoit, anime et valide les formations.</li>
            </ul>
        </section>
        <section>
            <h3>Pour qui</h3>
            <ul>
                <li>Le commandement et les chefs d’unité, pour décider des priorités.</li>
                <li>Le pôle formation, pour planifier les sessions et les renouvellements.</li>
                <li>Les encadrants, pour savoir qui valider ensuite.</li>
            </ul>
        </section>
        <section>
            <h3>Les mots utilisés</h3>
            <ul>
                <li><b>Module de référence</b> : <?= $referenceAll ? 'aucun module n’est marqué obligatoire, tous les modules actifs comptent.' : 'module marqué obligatoire pour toute la communauté.' ?></li>
                <li><b>Couverture</b> : part des modules de référence acquis. 100 % = tout le monde a tout validé.</li>
                <li><b>Écart critique</b> : module de référence expiré ou à repasser, ou module « critique » pas encore acquis.</li>
                <li><b>En progression</b> : au moins un module commencé, pas encore validé.</li>
            </ul>
        </section>
    </div>
    <p class="ccm-guide__note">Les chiffres viennent directement du suivi des modules : rien n’est estimé. Pour changer un statut, ouvrez le suivi du module et validez les membres concernés.</p>
</details>

<ul class="ccm-legend" aria-label="Légende des statuts">
    <?php foreach ($statusLabels as $st => $label): ?>
    <li class="ccm-legend__i" title="<?= $h($statusHints[$st]) ?>">
        <span class="ccm-dot ccm-st--<?= $h($st) ?>" aria-hidden="true"><?= $h($statusIcons[$st]) ?></span>
        <span><b><?= $h($label) ?></b><small><?= $h($statusHints[$st]) ?></small></span>
    </li>
    <?php endforeach; ?>
</ul>

<?php if (!$boardReady): ?>
<p class="ccm-notice ccm-notice--warn" role="status">Le suivi des modules n’est pas encore disponible sur cette installation : les indicateurs s’afficheront dès qu’il sera activé. L’organisation de la formation et les groupes de suivi restent utilisables plus bas.</p>
<?php elseif ($modules === []): ?>
<div class="ccm-empty">
    <b>Aucun module de compétence actif</b>
    <p>Activez au moins un module ALPHA → DELTA pour que les membres puissent être suivis ici.</p>
    <a class="btn pri" href="<?= $h(training_lms_admin_url('competences/modules')) ?>">Activer des modules</a>
</div>
<?php elseif ($members === []): ?>
<div class="ccm-empty"><b>Aucun membre actif dans la communauté</b><p>Les indicateurs apparaîtront dès que des membres auront rejoint la communauté.</p></div>
<?php else: ?>

<?php if ($criticalModules !== [] || $soonList !== []): ?>
<section class="ccm-sec" aria-labelledby="ccm-prio-t">
    <div class="ccm-sec__head">
        <div>
            <p class="k">À traiter en premier</p>
            <h2 id="ccm-prio-t">Priorités du moment</h2>
        </div>
    </div>
    <div class="ccm-prio">
        <?php if ($criticalModules !== []): ?>
        <article class="ccm-box ccm-box--danger">
            <h3>Écarts critiques par module</h3>
            <ul class="ccm-prio__list">
                <?php foreach (array_slice($criticalModules, 0, 5) as $g): ?>
                <li>
                    <span class="ccm-code ccm-ph--<?= $h($g['phase']) ?>" translate="no"><?= $h($g['code']) ?></span>
                    <span class="ccm-prio__name" translate="no"><?= $h($g['name']) ?></span>
                    <b title="Écarts critiques"><?= (int) $g['critical_gaps'] ?></b>
                    <a class="ccm-link" href="<?= $h($trackingUrl((int) $g['id'])) ?>">Ouvrir le suivi</a>
                </li>
                <?php endforeach; ?>
            </ul>
        </article>
        <?php endif; ?>
        <?php if ($soonList !== []): ?>
        <article class="ccm-box ccm-box--warn">
            <h3>Prochains renouvellements</h3>
            <ul class="ccm-prio__list">
                <?php foreach (array_slice($soonList, 0, 5) as $s): ?>
                <li>
                    <span class="ccm-prio__name"><span translate="no"><?= $h($s['member']['name']) ?></span> · <span translate="no"><?= $h($s['module']['code'] ?? '') ?></span></span>
                    <time datetime="<?= $h($s['date']) ?>"><?= $h($longDate($s['date'])) ?></time>
                    <?php if ($s['module'] !== null): ?><a class="ccm-link" href="<?= $h($trackingUrl((int) $s['module']['id'])) ?>">Ouvrir le suivi</a><?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php if (count($soonList) > 5): ?><p class="ccm-muted">Et <?= count($soonList) - 5 ?> autre(s) dans les 30 jours.</p><?php endif; ?>
        </article>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<section class="ccm-sec" aria-labelledby="ccm-units-t">
    <div class="ccm-sec__head">
        <div>
            <p class="k">Vue par unité</p>
            <h2 id="ccm-units-t">Où en est chaque unité</h2>
            <p>Couverture des modules de référence selon l’unité principale des membres. « Voir ses membres » filtre la liste plus bas.</p>
        </div>
    </div>
    <ul class="ccm-cards">
        <?php foreach ($unitsAgg as $u):
            $up = (int) $u['pct'];
            $name = $unitLabel((string) $u['key']);
            ?>
        <li class="ccm-card">
            <div class="ccm-card__head">
                <h3>
                    <?php if (!empty($u['confidential']) && str_starts_with((string) $u['key'], 'c-')): ?>
                        <?= \App\Support\ConfidentialDisplay::renderPayload((array) $u['confidential']) ?>
                    <?php else: ?>
                        <span<?= $u['key'] === 'u0' ? '' : ' translate="no"' ?>><?= $h($name) ?></span>
                        <?php if (!empty($u['confidential'])): ?><?= \App\Support\ConfidentialDisplay::renderPayload((array) $u['confidential'], true) ?><?php endif; ?>
                    <?php endif; ?>
                </h3>
                <span class="ccm-pct <?= $tone($up) ?>" title="Couverture des modules de référence"><?= $up ?> %</span>
            </div>
            <div class="ccm-meter" aria-hidden="true"><i style="width: <?= $up ?>%"></i></div>
            <dl class="ccm-facts">
                <div><dt>Membres</dt><dd><?= (int) $u['members'] ?></dd></div>
                <div><dt>Écarts critiques</dt><dd class="<?= (int) $u['critical'] > 0 ? 'low' : '' ?>"><?= (int) $u['critical'] ?></dd></div>
                <div><dt>En progression</dt><dd><?= (int) $u['members_progress'] ?></dd></div>
            </dl>
            <button type="button" class="btn ccm-card__btn" data-ccm-goto="unit" data-ccm-value="<?= $h($u['key']) ?>">Voir ses membres</button>
        </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($jobsAgg !== []): ?>
    <details class="ccm-fold" id="ccm-fonctions">
        <summary>
            <span><b>Vue par fonction (emploi)</b><small><?= count($jobsAgg) ?> emploi(s) occupé(s) · couverture la plus faible d’abord</small></span>
            <svg class="i" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
        </summary>
        <div class="ccm-table-wrap">
            <table class="ccm-table">
                <thead><tr><th scope="col">Emploi</th><th scope="col">Membres</th><th scope="col">Couverture</th><th scope="col">Écarts critiques</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                <?php foreach ($jobsAgg as $j): $jp = (int) $j['pct']; ?>
                    <tr>
                        <th scope="row" translate="no"><?= $h($j['name']) ?></th>
                        <td data-label="Membres"><?= (int) $j['members'] ?></td>
                        <td data-label="Couverture"><span class="ccm-inline-meter"><span class="ccm-meter" aria-hidden="true"><i style="width: <?= $jp ?>%"></i></span><b><?= $jp ?> %</b></span></td>
                        <td data-label="Écarts critiques" class="<?= (int) $j['critical'] > 0 ? 'low' : '' ?>"><?= (int) $j['critical'] ?></td>
                        <td class="ccm-table__act">
                            <button type="button" class="ccm-link" data-ccm-goto="job" data-ccm-value="<?= $h($j['key']) ?>">Membres</button>
                            <a class="ccm-link" href="<?= $h($generatorUrl . '?cible=' . rawurlencode('job_role:' . $j['key']) . '#generateur') ?>">Parcours</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </details>
    <?php endif; ?>
</section>

<section class="ccm-sec" aria-labelledby="ccm-gaps-t" id="ccm-ecarts">
    <div class="ccm-sec__head">
        <div>
            <p class="k">Vue par compétence</p>
            <h2 id="ccm-gaps-t">Écarts à combler</h2>
            <p>Chaque module avec la répartition des statuts. Les modules de référence avec le plus d’écarts critiques sont en tête.</p>
        </div>
        <?php if ($canOpenMembers): ?>
        <a class="btn" href="<?= $h($qualificationsUrl) ?>">Matrice des qualifications</a>
        <?php endif; ?>
    </div>
    <ul class="ccm-gaps">
        <?php foreach ($gaps as $g):
            $gid = (int) $g['id'];
            $counts = (array) $g['counts'];
            $gp = (int) $g['pct'];
            ?>
        <li class="ccm-gap ccm-ph--<?= $h($g['phase']) ?>" id="ccm-module-<?= $gid ?>">
            <div class="ccm-gap__head">
                <span class="ccm-code" translate="no"><?= $h($g['code']) ?></span>
                <div class="ccm-gap__title">
                    <h3 translate="no"><?= $h($g['name']) ?></h3>
                    <p class="ccm-tags">
                        <span class="ccm-tag"><?= $h($phaseLabels[$g['phase']]['label'] ?? $g['phase']) ?> · <?= $h($phaseLabels[$g['phase']]['subtitle'] ?? '') ?></span>
                        <?php if ($g['mandatory']): ?><span class="ccm-tag ccm-tag--ref" title="Compte dans la couverture de chaque membre.">Obligatoire</span><?php elseif ($g['reference']): ?><span class="ccm-tag ccm-tag--ref" title="Aucun module n’est obligatoire : tous les modules actifs comptent.">Référence</span><?php endif; ?>
                        <?php if ($g['critical']): ?><span class="ccm-tag ccm-tag--crit" title="Module marqué « critique » : tant qu’il n’est pas acquis, c’est un écart critique.">Critique</span><?php endif; ?>
                        <?php if ($g['recurrence_days'] !== null): ?><span class="ccm-tag" title="Une validation expire après cette durée.">Renouvelé tous les <?= (int) $g['recurrence_days'] ?> jours</span><?php endif; ?>
                    </p>
                </div>
                <span class="ccm-pct <?= $tone($gp) ?>" title="Part des membres ayant acquis ce module."><?= $gp ?> %</span>
            </div>
            <div class="ccm-stack" aria-hidden="true">
                <?php foreach (array_keys($statusLabels) as $st): if ((int) $counts[$st] < 1) { continue; } ?>
                <i class="ccm-st--<?= $h($st) ?>" style="flex-grow: <?= (int) $counts[$st] ?>" title="<?= $h($statusLabels[$st]) ?> : <?= (int) $counts[$st] ?>"></i>
                <?php endforeach; ?>
            </div>
            <ul class="ccm-counts">
                <?php foreach (array_keys($statusLabels) as $st): ?>
                <li><span class="ccm-dot ccm-dot--sm ccm-st--<?= $h($st) ?>" aria-hidden="true"></span><?= $h($statusLabels[$st]) ?> <b><?= (int) $counts[$st] ?></b></li>
                <?php endforeach; ?>
                <?php if ((int) $g['soon'] > 0): ?><li class="ccm-counts__soon">Échéance sous 30 jours <b><?= (int) $g['soon'] ?></b></li><?php endif; ?>
            </ul>
            <?php if ($g['courses'] !== [] || $g['qualifications'] !== []): ?>
            <div class="ccm-gap__links">
                <?php if ($g['courses'] !== []): ?>
                <p class="k">Formations qui comblent cet écart</p>
                <ul>
                    <?php foreach ($g['courses'] as $c): ?>
                    <li><a href="<?= $h($c['url']) ?>"><span class="ccm-tag"><?= $c['kind'] === 'academy_course' ? 'Academy' : 'Formation' ?></span> <span translate="no"><?= $h($c['title']) ?></span></a></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <?php if ($g['qualifications'] !== []): ?>
                <p class="ccm-muted">Qualification liée : <span translate="no"><?= $h(implode(', ', $g['qualifications'])) ?></span></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="ccm-gap__act">
                <a class="btn pri" href="<?= $h($trackingUrl($gid)) ?>">Valider des membres</a>
                <button type="button" class="btn" data-ccm-goto="module" data-ccm-value="<?= $gid ?>">Voir qui ne l’a pas</button>
                <a class="btn" href="<?= $h($generatorUrl . ((int) $g['competency_id'] > 0 ? '?cible=' . rawurlencode('competency:' . (int) $g['competency_id']) : '') . '#generateur') ?>"<?php if ((int) $g['competency_id'] < 1): ?> title="Ce module n’est relié à aucune compétence du référentiel : le générateur s’ouvre sans cible."<?php endif; ?>>Générer un parcours</a>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
</section>

<section class="ccm-sec" aria-labelledby="ccm-members-t" id="ccm-membres">
    <div class="ccm-sec__head">
        <div>
            <p class="k">Vue par membre</p>
            <h2 id="ccm-members-t">Membres</h2>
            <p>Une case par module, dans l’ordre ALPHA → DELTA, avec la couleur et le symbole de la légende. Survolez une case pour voir le module. Les cases pâles sont des modules facultatifs. Les membres avec le plus d’écarts sont en tête.</p>
        </div>
    </div>

    <form class="ccm-bar" data-ccm-filters role="search" aria-label="Filtrer les membres" onsubmit="return false">
        <label class="ccm-search">
            <svg class="i" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <span class="sr-only">Rechercher un membre</span>
            <input type="search" data-ccm-f="q" placeholder="Rechercher un membre…" autocomplete="off">
        </label>
        <label><span class="sr-only">Unité</span>
            <select data-ccm-f="unit">
                <option value="">Toutes les unités</option>
                <?php foreach ($unitsAgg as $u): ?><option value="<?= $h($u['key']) ?>"<?= $u['key'] === 'u0' || str_starts_with((string) $u['key'], 'c-') ? '' : ' translate="no"' ?>><?= $h($unitLabel((string) $u['key'])) ?></option><?php endforeach; ?>
            </select>
        </label>
        <?php if ($jobsAgg !== []): ?>
        <label><span class="sr-only">Fonction</span>
            <select data-ccm-f="job">
                <option value="">Toutes les fonctions</option>
                <?php foreach ($jobsAgg as $j): ?><option value="<?= $h($j['key']) ?>" translate="no"><?= $h($j['name']) ?></option><?php endforeach; ?>
            </select>
        </label>
        <?php endif; ?>
        <?php if ($gradesList !== []): ?>
        <label><span class="sr-only">Grade</span>
            <select data-ccm-f="grade">
                <option value="">Tous les grades</option>
                <?php foreach ($gradesList as $g): ?><option value="<?= (int) $g['id'] ?>" translate="no"><?= $h($g['label']) ?></option><?php endforeach; ?>
            </select>
        </label>
        <?php endif; ?>
        <?php if ($groupNameById !== []): ?>
        <label><span class="sr-only">Groupe de suivi</span>
            <select data-ccm-f="group">
                <option value="">Tous les groupes de suivi</option>
                <?php foreach ($groupNameById as $gid => $gname): if ($gid < 1) { continue; } ?><option value="<?= (int) $gid ?>" translate="no"><?= $h($gname) ?></option><?php endforeach; ?>
            </select>
        </label>
        <?php endif; ?>
        <label><span class="sr-only">Compétence</span>
            <select data-ccm-f="module">
                <option value="">Toutes les compétences</option>
                <?php foreach ($modules as $m): ?><option value="<?= (int) $m['id'] ?>" translate="no"><?= $h($m['code'] . ' — ' . $m['name']) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label><span class="sr-only">Statut</span>
            <select data-ccm-f="status">
                <option value="">Tous les statuts</option>
                <option value="GAP">Avec un écart (non acquis)</option>
                <option value="CRIT">Avec un écart critique</option>
                <?php foreach ($statusLabels as $st => $label): ?><option value="<?= $h($st) ?>"><?= $h($label) ?></option><?php endforeach; ?>
            </select>
        </label>
        <button type="reset" class="btn" data-ccm-reset>Effacer</button>
    </form>
    <p class="ccm-result" aria-live="polite"><b data-ccm-shown><?= count($members) ?></b> / <?= count($members) ?> <span>membres affichés</span></p>

    <ul class="ccm-members" data-ccm-list>
        <?php foreach ($members as $i => $r):
            $rp = (int) $r['pct'];
            $search = mb_strtolower($r['name'] . ' ' . $r['sub'] . ' ' . $r['grade_label']);
            $unitName = $unitLabel((string) $r['unit_key']);
            $unitInfo = $unitByKey[(string) $r['unit_key']] ?? null;
            $jobs = array_values(array_filter(array_map(static fn (int $j): string => $jobNameById[$j] ?? '', (array) $r['job_ids'])));
            ?>
        <li class="ccm-member" data-ccm-row
            data-unit="<?= $h($r['unit_key']) ?>"
            data-job=" <?= $h(implode(' ', $r['job_ids'])) ?> "
            data-grade="<?= (int) $r['grade_id'] ?>"
            data-group=" <?= $h(implode(' ', $r['group_ids'])) ?> "
            data-critical="<?= (int) $r['critical'] ?>"
            data-search="<?= $h($search) ?>"<?= $i >= 60 ? ' data-ccm-more hidden' : '' ?>>
            <div class="ccm-member__id">
                <span class="ccm-avatar" aria-hidden="true" translate="no"><?= $h(mb_strtoupper(mb_substr($r['name'], 0, 2))) ?></span>
                <div>
                    <b translate="no"><?= $h($r['name']) ?></b>
                    <small>
                        <?php if ($r['grade_label'] !== ''): ?><span translate="no"><?= $h($r['grade_label']) ?></span> · <?php endif; ?>
                        <?php if ($unitInfo !== null && !empty($unitInfo['confidential']) && str_starts_with((string) $r['unit_key'], 'c-')): ?>
                            <?= \App\Support\ConfidentialDisplay::renderPayload((array) $unitInfo['confidential'], true) ?>
                        <?php else: ?>
                            <span<?= $r['unit_key'] === 'u0' ? '' : ' translate="no"' ?>><?= $h($unitName) ?></span>
                        <?php endif; ?>
                    </small>
                    <?php if ($r['sub'] !== '' || $jobs !== []): ?>
                    <small class="ccm-member__jobs" translate="no"><?= $h(implode(' · ', array_filter(array_merge([$r['sub']], $jobs)))) ?></small>
                    <?php endif; ?>
                </div>
            </div>
            <ol class="ccm-strip" aria-label="Modules du membre">
                <?php foreach ($modules as $m):
                    $mid = (int) $m['id'];
                    $st = (string) ($r['cells'][$mid] ?? 'NOT_STARTED');
                    ?>
                <li class="ccm-dot ccm-st--<?= $h($st) ?><?= !empty($m['reference']) ? '' : ' is-opt' ?>" data-m="<?= $mid ?>" data-s="<?= $h($st) ?>" title="<?= $h($m['code'] . ' — ' . $m['name']) ?> : <?= $h($statusLabels[$st]) ?>"><span aria-hidden="true"><?= $h($statusIcons[$st]) ?></span><span class="sr-only"><span translate="no"><?= $h($m['code']) ?></span> : <?= $h($statusLabels[$st]) ?></span></li>
                <?php endforeach; ?>
            </ol>
            <div class="ccm-member__score">
                <span class="ccm-pct <?= $tone($rp) ?>"><?= $rp ?> %</span>
                <small><?= (int) $r['ref_done'] ?>/<?= (int) $r['ref_total'] ?> acquis</small>
                <?php if ((int) $r['critical'] > 0): ?><small class="low"><?= (int) $r['critical'] ?> écart(s) critique(s)</small><?php endif; ?>
            </div>
            <?php if ($canOpenMembers): ?>
            <a class="btn ccm-member__open" href="<?= $h($memberUrl((int) $r['id'])) ?>" title="Ouvrir la fiche du membre">Fiche</a>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <p class="ccm-empty ccm-empty--sm" data-ccm-none hidden>Aucun membre ne correspond à ces filtres.</p>
    <?php if (count($members) > 60): ?>
    <p class="ccm-more"><button type="button" class="btn" data-ccm-showall>Afficher tous les membres (<?= count($members) ?>)</button></p>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="ccm-sec" aria-labelledby="ccm-chain-t" id="ccm-organisation">
    <div class="ccm-sec__head">
        <div>
            <p class="k">Organisation</p>
            <h2 id="ccm-chain-t">Qui fait quoi dans la formation</h2>
            <p>Quatre étapes : à chacune, nommez des personnes. La personne nommée reçoit un e-mail qui lui explique ce qu’elle peut faire.</p>
        </div>
        <a href="<?= $h(training_lms_admin_url('competences/formateur')) ?>" class="btn pri">Nommer une personne à chaque étape</a>
    </div>

    <?php if ($pedagogySteps !== []): ?>
    <ol class="ccm-steps">
        <?php foreach ($pedagogySteps as $kind => $step):
            $named = $pedagogyDesignations[$kind] ?? [];
            $names = array_map(static fn (array $d): string => trim((string) ($d['display_name'] ?? '')) ?: trim((string) ($d['email'] ?? '')), $named);
            ?>
        <li class="ccm-step<?= $named !== [] ? ' is-ok' : ' is-todo' ?>">
            <p class="k">Étape <?= $h($step['step']) ?></p>
            <h3><?= $h($step['title']) ?></h3>
            <p><?= $h($step['lead']) ?></p>
            <p class="ccm-step__who">
                <?php if ($named !== []): ?>
                    <span translate="no"><?= $h(implode(', ', array_slice($names, 0, 4)) . (count($names) > 4 ? ' +' . (count($names) - 4) : '')) ?></span>
                <?php else: ?>
                    Personne n’est nommé.
                <?php endif; ?>
            </p>
            <a href="<?= $h(training_lms_admin_url('competences/formateur') . '#etape-' . $kind) ?>" class="ccm-link"><?= $named !== [] ? 'Modifier' : 'Nommer quelqu’un' ?></a>
        </li>
        <?php endforeach; ?>
    </ol>
    <?php endif; ?>

    <?php if (empty($pedagogyChainAssess['ok'])): ?>
    <ul class="ccm-notice ccm-notice--warn ccm-notice--list">
        <?php foreach ($pedagogyChainAssess['gaps'] as $gap): ?>
        <li><?= $h((string) $gap) ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <div class="ccm-split">
        <?php if ($competencyUsers !== []): ?>
        <div class="ccm-box">
            <h3>Raccourci : nommer le référent formation</h3>
            <p class="ccm-muted">Le référent couvre les étapes 3 et 4 : il valide les encadrants et supervise les concepteurs. Choisissez la personne par son nom ; aucun rôle n’est modifié.</p>
            <form method="post" class="ccm-form-row">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="action" value="designate_referent">
                <?php
                $pickerUsers = $competencyUsers;
                $pickerId = 'referent-target';
                $pickerExcludeIds = [];
                require base_path('views/admin/training/partials/pedagogy_member_picker.php');
                ?>
                <label class="ccm-check"><input type="checkbox" name="notify_email" value="1" checked> Prévenir par e-mail</label>
                <button type="submit" class="btn pri">Nommer ce référent</button>
            </form>
        </div>
        <?php endif; ?>
        <div class="ccm-box">
            <h3>Sections réservées</h3>
            <p class="ccm-muted">Vérifie que les sections « pilotage » et « bureau des compétences » existent, et les crée si besoin.</p>
            <form method="post">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="action" value="ensure_org_sections">
                <button type="submit" class="btn">Vérifier les sections</button>
            </form>
        </div>
    </div>
</section>

<section class="ccm-sec" aria-labelledby="ccm-groups-t" id="ccm-groupes">
    <div class="ccm-sec__head">
        <div>
            <p class="k">Classement</p>
            <h2 id="ccm-groups-t">Groupes de suivi</h2>
            <p>Des listes nominatives pour suivre une cohorte (cadres, animateurs, parcours confirmés…). Ils servent de filtre dans la vue par membre. <b>Ils ne bloquent ni n’ouvrent aucune formation et n’accordent aucun droit.</b></p>
        </div>
        <?php if ($competencyMatricesSchemaReady): ?>
        <p class="ccm-muted ccm-sec__meta"><?= (int) $matrixCount ?> groupe(s) · <?= (int) $assignedTotal ?> placement(s)</p>
        <?php endif; ?>
    </div>

    <?php if (!$competencyMatricesSchemaReady): ?>
    <p class="ccm-notice ccm-notice--warn">Cette fonction n’est pas encore activée sur votre installation. Contactez un administrateur technique pour la finaliser.</p>
    <?php else: ?>
    <div class="ccm-split">
        <details class="ccm-box ccm-fold-box"<?= $competencyMatrices === [] ? ' open' : '' ?>>
            <summary><b>Créer un groupe de suivi</b><svg class="i" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
            <form method="post" class="ccm-form">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="action" value="create_matrix">
                <label for="matrix_name">Nom du groupe</label>
                <input id="matrix_name" required name="matrix_name" placeholder="Ex. Encadrement terrain">
                <label for="matrix_description">À quoi sert ce groupe ?</label>
                <textarea id="matrix_description" name="matrix_description" rows="2" placeholder="Ex. Suivi des cadres pour le brief du week-end. Ne change pas les droits."></textarea>
                <label for="auto_min_completed">Formations terminées (minimum pour le remplissage auto)</label>
                <input id="auto_min_completed" type="number" min="0" name="auto_min_completed" value="0">
                <small>0 = pas de seuil. Ex. 3 = au moins trois formations terminées.</small>
                <fieldset>
                    <legend>Rôles pris en compte pour le remplissage automatique</legend>
                    <small>Un membre est retenu s’il a au moins l’un de ces rôles. Laissez vide pour ignorer le critère rôle.</small>
                    <div class="ccm-roles">
                        <?php if ($competencyRoles === []): ?>
                        <p class="ccm-muted">Aucun rôle organisationnel disponible.</p>
                        <?php else: ?>
                            <?php foreach ($competencyRoles as $role):
                                $rid = (int) ($role['id'] ?? 0);
                                if ($rid < 1) {
                                    continue;
                                }
                                ?>
                            <label class="ccm-check"><input type="checkbox" name="auto_role_ids[]" value="<?= $rid ?>"> <span translate="no"><?= $h((string) ($role['name'] ?? 'Rôle')) ?></span></label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </fieldset>
                <button class="btn pri" type="submit">Créer le groupe</button>
            </form>
        </details>
        <div class="ccm-box">
            <h3>Groupes suggérés</h3>
            <p class="ccm-muted">Encadrement, Animation et pédagogie, Parcours confirmé (au moins trois formations terminées). Ceux qui existent déjà ne sont pas recréés.</p>
            <form method="post">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="action" value="seed_preset_matrices">
                <button type="submit" class="btn">Ajouter les 3 groupes suggérés</button>
            </form>
        </div>
    </div>

    <?php if ($competencyMatrices === []): ?>
    <p class="ccm-empty ccm-empty--sm">Aucun groupe de suivi pour le moment. Créez-en un ci-dessus, ou ajoutez les trois groupes suggérés.</p>
    <?php else: ?>
    <ul class="ccm-groups">
        <?php foreach ($competencyMatrices as $m):
            $mid = (int) ($m['id'] ?? 0);
            if ($mid < 1) {
                continue;
            }
            $parsed = $parseMatrixRules($m);
            $groupMembers = $competencyAssignmentsByMatrix[$mid] ?? [];
            $candidateCount = (int) ($competencyCandidateCountByMatrix[$mid] ?? 0);
            $assignedIds = [];
            foreach ($groupMembers as $mem) {
                $assignedIds[(int) ($mem['user_id'] ?? 0)] = true;
            }
            ?>
        <li>
            <details class="ccm-group">
                <summary>
                    <span class="ccm-group__t"><b translate="no"><?= $h((string) ($m['name'] ?? 'Groupe')) ?></b>
                        <?php if (trim((string) ($m['description'] ?? '')) !== ''): ?><small translate="no"><?= $h((string) $m['description']) ?></small><?php endif; ?>
                    </span>
                    <span class="ccm-tag"><?= count($groupMembers) ?> membre(s)</span>
                    <svg class="i" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                </summary>
                <div class="ccm-group__body">
                    <div class="ccm-rule">
                        <p class="k">Critères de remplissage auto</p>
                        <p>
                            <?php if ($parsed['role_names'] === [] && $parsed['min_completed'] === 0): ?>
                                Tous les membres de la communauté (aucun critère particulier).
                            <?php else: ?>
                                <?php if ($parsed['role_names'] !== []): ?><span>Rôles :</span> <span translate="no"><?= $h(implode(', ', $parsed['role_names'])) ?></span><?php endif; ?>
                                <?php if ($parsed['min_completed'] > 0): ?><span>Au moins <?= (int) $parsed['min_completed'] ?> formation(s) terminée(s)</span><?php endif; ?>
                            <?php endif; ?>
                        </p>
                        <small><?= $candidateCount ?> membre(s) correspondent aujourd’hui · <?= count($groupMembers) ?> déjà dans le groupe</small>
                    </div>
                    <div class="ccm-form-row">
                        <form method="post">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="hidden" name="action" value="auto_detect">
                            <input type="hidden" name="matrix_id" value="<?= $mid ?>">
                            <button class="btn" type="submit"><span>Remplir selon les critères</span><?php if ($candidateCount > 0): ?> <span>(<?= $candidateCount ?>)</span><?php endif; ?></button>
                        </form>
                        <form method="post" class="ccm-form-row">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="hidden" name="action" value="assign_matrix">
                            <input type="hidden" name="matrix_id" value="<?= $mid ?>">
                            <label class="sr-only" for="assign-user-<?= $mid ?>">Ajouter un membre</label>
                            <select id="assign-user-<?= $mid ?>" name="user_id" required>
                                <option value="">Ajouter un membre…</option>
                                <?php foreach ($competencyUsers as $u):
                                    $uid = (int) ($u['id'] ?? 0);
                                    if ($uid < 1 || isset($assignedIds[$uid])) {
                                        continue;
                                    }
                                    ?>
                                <option value="<?= $uid ?>" translate="no"><?= $h((string) ($u['display_name'] ?? $u['email'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn pri" type="submit">Ajouter</button>
                        </form>
                        <?php if ($members !== []): ?>
                        <button type="button" class="btn" data-ccm-goto="group" data-ccm-value="<?= $mid ?>">Voir leurs compétences</button>
                        <?php endif; ?>
                    </div>
                    <?php if ($groupMembers === []): ?>
                    <p class="ccm-muted">Personne n’y est encore placé. Utilisez le remplissage automatique ou ajoutez un membre.</p>
                    <?php else: ?>
                    <ul class="ccm-group__list">
                        <?php foreach ($groupMembers as $mem):
                            $src = (string) ($mem['source'] ?? 'manual');
                            ?>
                        <li>
                            <span><b translate="no"><?= $h((string) ($mem['display_name'] ?? 'Membre')) ?></b><small><?= $src === 'auto_detect' ? 'Ajouté automatiquement' : 'Ajouté à la main' ?></small></span>
                            <form method="post">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="action" value="unassign_matrix">
                                <input type="hidden" name="matrix_id" value="<?= $mid ?>">
                                <input type="hidden" name="user_id" value="<?= (int) ($mem['user_id'] ?? 0) ?>">
                                <button type="submit" class="ccm-link ccm-link--danger">Retirer</button>
                            </form>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                    <form method="post" class="ccm-group__del" data-confirm="Supprimer ce groupe et retirer tous ses membres de la liste ?" onsubmit="return confirm(this.getAttribute('data-confirm'));">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="action" value="delete_matrix">
                        <input type="hidden" name="matrix_id" value="<?= $mid ?>">
                        <button type="submit" class="ccm-link ccm-link--danger">Supprimer ce groupe</button>
                    </form>
                </div>
            </details>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <?php endif; ?>
</section>

<nav class="ccm-sec ccm-more-links" aria-label="Autres espaces compétences">
    <p class="k">Autres espaces compétences</p>
    <div>
        <a class="btn" href="<?= $h(training_lms_admin_url('competences/modules')) ?>">Modules ALPHA → DELTA</a>
        <a class="btn" href="<?= $h($generatorUrl . '#generateur') ?>">Générateur de parcours</a>
        <?php if ($canOpenMembers): ?><a class="btn" href="<?= $h($qualificationsUrl) ?>">Matrice des qualifications</a><?php endif; ?>
        <a class="btn" href="<?= $h(training_lms_admin_url('competences/bureau-personnel')) ?>">Bureau du personnel</a>
        <a class="btn" href="<?= $h(training_lms_admin_url('competences/pole-formation')) ?>">Pôle formation</a>
        <a class="btn" href="<?= $h(training_lms_admin_url('competences/validation')) ?>">Validation / certification</a>
        <a class="btn" href="<?= $h(training_lms_admin_url('competences/sections')) ?>">Sections organisationnelles</a>
        <a class="btn" href="<?= $h(training_lms_admin_url('competences/formateur')) ?>">Espace formateur</a>
        <a class="btn" href="<?= $h(training_lms_admin_url('competences/instructeur')) ?>">Vue instructeur</a>
    </div>
</nav>

</div>
<script src="<?= $h(asset_url('assets/js/competency-command.js')) ?>" defer></script>
<?php require base_path('views/admin/training/partials/command_shell_close.php'); ?>
