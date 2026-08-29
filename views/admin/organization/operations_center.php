<?php

declare(strict_types=1);

/**
 * Centre des opérations — charte ATHENA.
 *
 * L’en-tête de page est rendu par la coque back-office. La page fusionne la file
 * d’alertes actionnables, les playbooks, le mur opérationnel et les files de travail
 * (candidatures, événements, anomalies, alertes locales).
 *
 * @var string $operationsProfile
 * @var list<string> $operationsProfiles
 * @var int $operationsModerationOpen
 * @var list<array<string, mixed>> $operationsPendingRecruitments
 * @var list<array<string, mixed>> $operationsEventsJ1
 * @var list<array<string, mixed>> $operationsEventsJ7
 * @var list<array<string, mixed>> $operationsActiveAlerts
 * @var array<string, int> $operationsOnboardingAnomalies
 * @var array<string, list<array<string, mixed>>> $operationsOpsBoardItemsByType
 * @var array<string, mixed> $operationsOpsBoardFilters
 * @var list<array<string, mixed>> $operationsActionableAlerts
 * @var list<array<string, mixed>> $operationsPlaybookCatalog
 * @var list<array<string, mixed>> $operationsAuditScenarios
 * @var list<array<string, mixed>> $operationsWeeklyGoals
 * @var list<array<string, mixed>> $operationsKpiSnapshot
 */

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$profile = (string) ($operationsProfile ?? 'commandement');
$profiles = is_array($operationsProfiles ?? null) ? $operationsProfiles : ['commandement', 'rh', 'moderation', 'formation'];
$moderationOpen = (int) ($operationsModerationOpen ?? 0);
$pendingRecruitments = is_array($operationsPendingRecruitments ?? null) ? $operationsPendingRecruitments : [];
$pendingRecruitmentsError = $operationsPendingRecruitmentsError ?? null;
$eventsJ1 = is_array($operationsEventsJ1 ?? null) ? $operationsEventsJ1 : [];
$eventsJ7 = is_array($operationsEventsJ7 ?? null) ? $operationsEventsJ7 : [];
$eventsError = $operationsEventsError ?? null;
$activeAlerts = is_array($operationsActiveAlerts ?? null) ? $operationsActiveAlerts : [];
$alertsError = $operationsAlertsError ?? null;
$anomalies = is_array($operationsOnboardingAnomalies ?? null) ? $operationsOnboardingAnomalies : [];
$opsByType = is_array($operationsOpsBoardItemsByType ?? null) ? $operationsOpsBoardItemsByType : [];
$opsFilters = is_array($operationsOpsBoardFilters ?? null) ? $operationsOpsBoardFilters : [];
$opsError = $operationsOpsBoardError ?? null;
$actionableAlerts = is_array($operationsActionableAlerts ?? null) ? $operationsActionableAlerts : [];
$playbookCatalog = is_array($operationsPlaybookCatalog ?? null) ? $operationsPlaybookCatalog : [];
$auditScenarios = is_array($operationsAuditScenarios ?? null) ? $operationsAuditScenarios : [];
$weeklyGoals = is_array($operationsWeeklyGoals ?? null) ? $operationsWeeklyGoals : [];
$kpiSnapshot = is_array($operationsKpiSnapshot ?? null) ? $operationsKpiSnapshot : [];

$profileLabels = [
    'commandement' => 'Commandement',
    'rh' => 'RH',
    'moderation' => 'Modération',
    'formation' => 'Formation',
];

$priorityLabels = [
    'critical' => 'Critique',
    'high' => 'Élevée',
    'normal' => 'Normale',
    'low' => 'Informationnelle',
];

/** Une priorité se lit comme un état : on la fait passer par la tonalité du tableau. */
$priorityAsState = static function (string $prio) use ($priorityLabels): string {
    return $priorityLabels[$prio] ?? ($prio !== '' ? ucfirst($prio) : 'Normale');
};

$blockTypeLabels = [
    'permanence_speciale' => 'Permanence',
    'info_pratique' => 'Info pratique',
    'manifestation' => 'Manifestation',
    'flash_info' => 'Flash info',
    'flash_info_detailed' => 'Flash info détaillé',
];

$formatDate = static function (mixed $raw, string $format = 'd/m/Y H:i'): string {
    $s = trim((string) ($raw ?? ''));
    if ($s === '') {
        return '—';
    }
    $ts = strtotime($s);

    return $ts ? date($format, $ts) : $s;
};

$formatRange = static function (mixed $from, mixed $to) use ($formatDate): string {
    $f = trim((string) ($from ?? ''));
    $t = trim((string) ($to ?? ''));
    if ($f === '' && $t === '') {
        return '—';
    }

    return $formatDate($f, 'd/m/Y') . ' → ' . $formatDate($t, 'd/m/Y');
};

$profileUrl = static fn (string $p): string => url('back-office/centre-operations') . '?profile=' . urlencode($p);
?>
<nav class="ath-periods" aria-label="Profil de pilotage">
    <span class="ath-periods__label">Profil</span>
    <?php foreach ($profiles as $p): ?>
    <a href="<?= $h($profileUrl((string) $p)) ?>" class="ath-btn"<?= $profile === (string) $p ? ' aria-current="true"' : '' ?>><?= $h($profileLabels[(string) $p] ?? (string) $p) ?></a>
    <?php endforeach; ?>
    <span class="ath-table-toolbar__spacer" aria-hidden="true"></span>
    <a href="<?= $h(url('back-office/tableau-operationnel')) ?>" class="ath-btn">Mur opérationnel</a>
    <a href="<?= $h(url('back-office/audit')) ?>" class="ath-btn">Journal d’audit</a>
</nav>

<h2 class="ath-section-title">Files de travail</h2>

<?php
$athKpis = [
    ['label' => 'SIGNALEMENTS FORUM', 'value' => (string) $moderationOpen, 'delta' => '', 'tone' => $moderationOpen === 0 ? '#0b8a5c' : '#c72e2e', 'pct' => $moderationOpen === 0 ? '0%' : '100%', 'note' => 'dossiers en attente'],
    ['label' => 'CANDIDATURES', 'value' => (string) count($pendingRecruitments), 'delta' => '', 'tone' => count($pendingRecruitments) === 0 ? '#0b8a5c' : '#c98a12', 'pct' => count($pendingRecruitments) === 0 ? '0%' : '100%', 'note' => 'à instruire'],
    ['label' => 'ÉVÉNEMENTS J+1', 'value' => (string) count($eventsJ1), 'delta' => '', 'tone' => '#c98a12', 'pct' => count($eventsJ1) === 0 ? '0%' : '100%', 'note' => 'à préparer'],
    ['label' => 'ÉVÉNEMENTS J+7', 'value' => (string) count($eventsJ7), 'delta' => '', 'tone' => '#1e4f80', 'pct' => count($eventsJ7) === 0 ? '0%' : '100%', 'note' => 'à planifier'],
    ['label' => 'ALERTES LOCALES', 'value' => (string) count($activeAlerts), 'delta' => '', 'tone' => count($activeAlerts) === 0 ? '#0b8a5c' : '#c98a12', 'pct' => count($activeAlerts) === 0 ? '0%' : '100%', 'note' => 'actives en ce moment'],
];
require base_path('views/partials/ath_kpis.php');
?>

<div class="ath-form__actions" style="border-top:0;margin:0 0 16px;padding-top:0;">
    <a href="<?= $h(url('back-office/forum-moderation')) ?>" class="ath-btn">Console forum</a>
    <?php if (\App\Core\Gate::getInstance()->allows('admin.members.moderate')): ?>
    <a href="<?= $h(url('back-office/moderation')) ?>" class="ath-btn">Restrictions membres</a>
    <?php endif; ?>
    <a href="<?= $h(url('back-office/recruitments')) ?>" class="ath-btn">Candidatures</a>
    <a href="<?= $h(url('back-office/events')) ?>" class="ath-btn">Événements</a>
    <a href="<?= $h(url('back-office/alerts')) ?>" class="ath-btn">Annonces &amp; alertes</a>
</div>

<?php if ($kpiSnapshot !== []): ?>
<h2 class="ath-section-title">Indicateurs de pilotage admin</h2>
<div class="ath-stat-grid ath-rise">
    <?php foreach ($kpiSnapshot as $kpi): ?>
    <div class="ath-stat">
        <p class="ath-stat__value"><?= $h((string) ($kpi['value'] ?? 'N/D')) ?></p>
        <p class="ath-stat__label"><?= $h((string) ($kpi['label'] ?? 'Indicateur')) ?></p>
        <p class="ath-field__help" style="margin-top:5px;"><?= $h((string) ($kpi['trend'] ?? '—')) ?></p>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
// ---- File unique des alertes actionnables ----
$athTableTitle = 'File unique des alertes actionnables';
$athTableCount = count($actionableAlerts);
$athTableCols = ['ALERTE', 'TYPE', 'VOLUME|r', 'IMPACT|r', 'DÉLAI VISÉ', 'URGENCE|b'];
$athTableRows = [];
$athTableRowActions = [];
foreach ($actionableAlerts as $alert) {
    $impact = (int) ($alert['impact_score'] ?? 0);
    $athTableRows[] = [
        (string) ($alert['title'] ?? 'Alerte'),
        (string) ($alert['type'] ?? '—'),
        (string) (int) ($alert['count'] ?? 0),
        (string) $impact,
        (string) ($alert['sla_label'] ?? '—'),
        $impact >= 80 ? 'Critique' : ($impact >= 60 ? 'En attente' : 'Actif'),
    ];
    // Balisage d’action construit ici, échappements compris (cf. contrat de ath_table.php).
    $link = trim((string) ($alert['link'] ?? ''));
    $cta = trim((string) ($alert['cta'] ?? 'Traiter'));
    $athTableRowActions[] = $link !== ''
        ? '<a href="' . $h($link) . '" class="ath-row-action ath-row-action--accent">' . $h($cta) . '</a>'
        : null;
}
$athTableActionsLabel = 'ACTION';
$athTableFilters = [];
$athTableMinWidth = '1180px';
$athTableShowCheckbox = false;
$athTableExportUrl = null;
$athTablePager = null;
$athTableRowHrefs = null;
$athTableFoot = $actionableAlerts === []
    ? 'Aucune alerte actionnable : rien ne requiert d’arbitrage immédiat.'
    : 'Triée par score d’impact décroissant.';
require base_path('views/partials/ath_table.php');
?>

<?php if ($playbookCatalog !== []): ?>
<h2 class="ath-section-title">Playbooks incidents</h2>
<div class="ath-rise">
    <?php foreach ($playbookCatalog as $playbook): ?>
        <?php $steps = is_array($playbook['steps'] ?? null) ? $playbook['steps'] : []; ?>
    <details class="ath-disclosure">
        <summary>
            <span><?= $h((string) ($playbook['title'] ?? 'Playbook')) ?> <span class="ath-disclosure__count">(<?= (int) ($playbook['resolved_count'] ?? 0) ?> résolu<?= (int) ($playbook['resolved_count'] ?? 0) > 1 ? 's' : '' ?>)</span></span>
            <span aria-hidden="true">▼</span>
        </summary>
        <div style="padding:10px 12px 12px;">
            <?php if (trim((string) ($playbook['summary'] ?? '')) !== ''): ?>
            <p class="ath-panel__lead" style="margin:0 0 8px;"><?= $h((string) $playbook['summary']) ?></p>
            <?php endif; ?>
            <?php if ($steps !== []): ?>
            <ol style="margin:0;padding-left:18px;font-size:11.5px;line-height:1.7;">
                <?php foreach ($steps as $step): ?>
                <li><?= $h((string) $step) ?></li>
                <?php endforeach; ?>
            </ol>
            <?php else: ?>
            <p class="ath-field__help" style="margin:0;">Aucune étape enregistrée pour ce playbook.</p>
            <?php endif; ?>
        </div>
    </details>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
if ($auditScenarios !== []) {
    $athTableTitle = 'Journal d’audit par scénario';
    $athTableCount = count($auditScenarios);
    $athTableCols = ['SCÉNARIO', 'DESCRIPTION', 'ÉVÉNEMENTS|r'];
    $athTableRows = [];
    $athTableRowActions = null;
    foreach ($auditScenarios as $scenario) {
        $athTableRows[] = [
            (string) ($scenario['label'] ?? '—'),
            (string) ($scenario['description'] ?? '—'),
            (string) (int) ($scenario['count'] ?? 0),
        ];
    }
    $athTableMinWidth = '980px';
    $athTableFoot = 'Regroupements prédéfinis du journal d’audit, pour retrouver une situation sans écrire de filtre.';
    require base_path('views/partials/ath_table.php');
}

if ($weeklyGoals !== []) {
    $athTableTitle = 'Objectifs hebdomadaires';
    $athTableCount = count($weeklyGoals);
    $athTableCols = ['OBJECTIF', 'INDICATEUR', 'VALEUR|r', 'VARIATION|r', 'ÉTAT|b'];
    $athTableRows = [];
    $athTableRowActions = null;
    foreach ($weeklyGoals as $goal) {
        $athTableRows[] = [
            (string) ($goal['title'] ?? '—'),
            (string) ($goal['kpi'] ?? '—'),
            (string) ($goal['value'] ?? 'N/D'),
            (string) ($goal['variation'] ?? '—'),
            (string) ($goal['state'] ?? 'En cours'),
        ];
    }
    $athTableMinWidth = '1080px';
    $athTableFoot = 'Suivi de la semaine en cours.';
    require base_path('views/partials/ath_table.php');
}
?>

<h2 class="ath-section-title">Mur opérationnel</h2>

<form method="get" action="<?= $h(url('back-office/centre-operations')) ?>" class="ath-form ath-rise">
    <div class="ath-form__head">
        <span class="ath-form__title">Filtrer la diffusion</span>
        <span class="ath-form__hint">Unité, période, type, visibilité et priorité.</span>
    </div>
    <input type="hidden" name="profile" value="<?= $h($profile) ?>">
    <div class="ath-form__grid">
        <label class="ath-field">
            <span class="ath-field__label">Unité</span>
            <input type="text" name="unit" value="<?= $h((string) ($opsFilters['unit_id'] ?? '')) ?>" class="ath-field__input" placeholder="Identifiant d’unité">
        </label>
        <label class="ath-field">
            <span class="ath-field__label">Depuis</span>
            <input type="date" name="from" value="<?= $h((string) ($opsFilters['period_start'] ?? '')) ?>" class="ath-field__input">
        </label>
        <label class="ath-field">
            <span class="ath-field__label">Jusqu’au</span>
            <input type="date" name="to" value="<?= $h((string) ($opsFilters['period_end'] ?? '')) ?>" class="ath-field__input">
        </label>
        <label class="ath-field">
            <span class="ath-field__label">Type</span>
            <select name="type" class="ath-field__select">
                <option value="">Tous les types</option>
                <?php foreach ($blockTypeLabels as $value => $label): ?>
                <option value="<?= $h((string) $value) ?>"<?= ((string) ($opsFilters['block_type'] ?? '')) === (string) $value ? ' selected' : '' ?>><?= $h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="ath-field">
            <span class="ath-field__label">Visibilité</span>
            <input type="text" name="visibility" value="<?= $h((string) ($opsFilters['visibility_level'] ?? '')) ?>" class="ath-field__input" placeholder="tenant, unit, role…">
        </label>
        <label class="ath-field">
            <span class="ath-field__label">Priorité</span>
            <select name="priority" class="ath-field__select">
                <option value="">Toutes les priorités</option>
                <?php foreach ($priorityLabels as $value => $label): ?>
                <option value="<?= $h((string) $value) ?>"<?= ((string) ($opsFilters['priority'] ?? '')) === (string) $value ? ' selected' : '' ?>><?= $h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <div class="ath-form__actions">
        <button type="submit" class="ath-btn ath-btn--solid">Filtrer</button>
        <a href="<?= $h($profileUrl($profile)) ?>" class="ath-btn">Réinitialiser</a>
        <a href="<?= $h(url('back-office/events')) ?>" class="ath-btn">Lier à un événement</a>
    </div>
</form>

<?php if ($opsError): ?>
<p class="ath-flash ath-flash--err" role="alert"><?= $h((string) $opsError) ?></p>
<?php endif; ?>

<?php
$pinned = static fn (array $item): string => !empty($item['is_pinned']) ? ' · épinglé' : '';

// A. Permanences particulières
$permanences = is_array($opsByType['permanence_speciale'] ?? null) ? $opsByType['permanence_speciale'] : [];
$athTableTitle = 'Permanences particulières';
$athTableCount = count($permanences);
$athTableCols = ['INTITULÉ', 'PERSONNELS', 'VALIDITÉ|m', 'VISIBILITÉ', 'PRIORITÉ|b'];
$athTableRows = [];
$athTableRowActions = null;
foreach ($permanences as $item) {
    $athTableRows[] = [
        (string) ($item['title'] ?? 'Permanence') . $pinned($item),
        (string) ($item['assignment_summary'] ?? '—'),
        $formatRange($item['start_date'] ?? null, $item['end_date'] ?? null),
        (string) ($item['visibility_level'] ?? 'tenant'),
        $priorityAsState((string) ($item['priority'] ?? 'normal')),
    ];
}
$athTableMinWidth = '1180px';
$athTableFoot = $permanences === [] ? 'Aucune permanence spéciale publiée.' : null;
require base_path('views/partials/ath_table.php');

// B. Infos pratiques
$infos = is_array($opsByType['info_pratique'] ?? null) ? $opsByType['info_pratique'] : [];
$athTableTitle = 'Infos pratiques';
$athTableCount = count($infos);
$athTableCols = ['LIBELLÉ', 'VISIBILITÉ', 'DÉBUT|m', 'FIN|m', 'PRIORITÉ|b'];
$athTableRows = [];
foreach ($infos as $item) {
    $athTableRows[] = [
        (string) ($item['title'] ?? 'Info') . $pinned($item),
        (string) ($item['visibility_level'] ?? 'tenant'),
        $formatDate($item['start_date'] ?? null, 'd/m/Y'),
        $formatDate($item['end_date'] ?? null, 'd/m/Y'),
        $priorityAsState((string) ($item['priority'] ?? 'normal')),
    ];
}
$athTableMinWidth = '1080px';
$athTableFoot = $infos === [] ? 'Aucune info pratique publiée.' : null;
require base_path('views/partials/ath_table.php');

// C. Manifestations particulières
$manifs = is_array($opsByType['manifestation'] ?? null) ? $opsByType['manifestation'] : [];
$athTableTitle = 'Manifestations particulières';
$athTableCount = count($manifs);
$athTableCols = ['TITRE', 'DÉBUT|m', 'FIN|m', 'VISIBILITÉ', 'PRIORITÉ|b'];
$athTableRows = [];
foreach ($manifs as $item) {
    $athTableRows[] = [
        (string) ($item['title'] ?? 'Manifestation') . $pinned($item),
        $formatDate($item['start_date'] ?? null, 'd/m/Y'),
        $formatDate($item['end_date'] ?? null, 'd/m/Y'),
        (string) ($item['visibility_level'] ?? 'tenant'),
        $priorityAsState((string) ($item['priority'] ?? 'normal')),
    ];
}
$athTableMinWidth = '1080px';
$athTableFoot = $manifs === [] ? 'Aucune manifestation publiée.' : null;
require base_path('views/partials/ath_table.php');
?>

<?php
// D. Flash infos — gardés en fiches : le corps du message doit rester lisible en entier.
$flashes = array_merge(
    is_array($opsByType['flash_info'] ?? null) ? $opsByType['flash_info'] : [],
    is_array($opsByType['flash_info_detailed'] ?? null) ? $opsByType['flash_info_detailed'] : [],
);
?>
<h2 class="ath-section-title">Flash infos</h2>
<?php if ($flashes === []): ?>
<div class="ath-card" style="padding:16px 18px;">
    <p class="ath-panel__lead" style="margin:0;">Aucun flash info actif.</p>
</div>
<?php else: ?>
<div class="ath-stack">
    <?php foreach ($flashes as $item): ?>
        <?php
        $prio = (string) ($item['priority'] ?? 'normal');
        $tone = match ($prio) {
            'critical' => 'ath-tag--bad',
            'high' => 'ath-tag--warn',
            'low' => 'ath-tag--info',
            default => 'ath-tag--neut',
        };
        ?>
    <article class="ath-item ath-rise">
        <div class="ath-item__head">
            <div style="min-width:0;">
                <p class="ath-item__name"><?= $h((string) ($item['title'] ?? 'Annonce')) ?></p>
                <p class="ath-item__meta">
                    Affichage <?= $h($formatRange($item['start_date'] ?? null, $item['end_date'] ?? null)) ?>
                    · cible <?= $h((string) ($item['visibility_level'] ?? 'tenant')) ?>
                    <?= !empty($item['is_pinned']) ? ' · épinglé' : '' ?>
                </p>
            </div>
            <span class="ath-tag <?= $tone ?>"><?= $h($priorityAsState($prio)) ?></span>
        </div>
        <?php if (trim((string) ($item['summary'] ?? '')) !== ''): ?>
        <p class="ath-panel__lead" style="margin-top:10px;"><?= nl2br($h((string) $item['summary'])) ?></p>
        <?php endif; ?>
        <?php if (trim((string) ($item['content'] ?? '')) !== ''): ?>
        <div class="ath-meter" style="margin-top:10px;">
            <p style="font-size:11.5px;line-height:1.6;margin:0;"><?= nl2br($h((string) $item['content'])) ?></p>
        </div>
        <?php endif; ?>
    </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<h2 class="ath-section-title">À traiter maintenant</h2>

<?php
// ---- Candidatures à traiter ----
$athTableTitle = 'Candidatures à traiter';
$athTableCount = count($pendingRecruitments);
$athTableCols = ['DOSSIER', 'ADRESSE E-MAIL|m', 'DÉPOSÉ LE|m'];
$athTableRows = [];
$athTableRowHrefs = [];
$athTableRowActions = null;
foreach ($pendingRecruitments as $row) {
    $name = trim((string) ($row['display_name'] ?? ''));
    if ($name === '') {
        $name = trim((string) ($row['email'] ?? '')) !== '' ? (string) $row['email'] : 'Dossier';
    }
    $athTableRows[] = [
        $name,
        (string) ($row['email'] ?? '—'),
        $formatDate($row['created_at'] ?? null),
    ];
    $athTableRowHrefs[] = url('back-office/recruitments/' . (int) ($row['id'] ?? 0) . '?dossier=1');
}
$athTableMinWidth = '900px';
$athTableFoot = $pendingRecruitmentsError !== null
    ? 'Indicateur indisponible : ' . (string) $pendingRecruitmentsError
    : ($pendingRecruitments === [] ? 'Aucune candidature en attente.' : 'Cliquez une ligne pour ouvrir le dossier.');
require base_path('views/partials/ath_table.php');

// ---- Événements imminents ----
$athTableTitle = 'Événements des 7 prochains jours';
$athTableCount = count($eventsJ7);
$athTableCols = ['ÉVÉNEMENT', 'DÉBUT|m'];
$athTableRows = [];
$athTableRowHrefs = [];
foreach ($eventsJ7 as $event) {
    $athTableRows[] = [
        (string) ($event['title'] ?? 'Événement'),
        $formatDate($event['starts_at'] ?? null),
    ];
    $athTableRowHrefs[] = url('back-office/events/' . (int) ($event['id'] ?? 0));
}
$athTableMinWidth = '760px';
$athTableFoot = $eventsError !== null
    ? 'Indicateur indisponible : ' . (string) $eventsError
    : ($eventsJ7 === [] ? 'Aucun événement sur les 7 prochains jours.' : null);
require base_path('views/partials/ath_table.php');

// ---- Anomalies onboarding / configuration ----
$anomalyRows = [
    ['Profils incomplets', (int) ($anomalies['profils_incomplets'] ?? 0), url('back-office/users') . '?filter_incomplete=1', 'Assigner'],
    ['Membres sans unité', (int) ($anomalies['membres_sans_unite'] ?? 0), url('back-office/users') . '?filter_incomplete=1', 'Affecter'],
    ['Membres sans rôle', (int) ($anomalies['membres_sans_role'] ?? 0), url('back-office/users') . '?filter_no_role=1', 'Traiter'],
    ['Invitations expirées', (int) ($anomalies['invitations_expirees'] ?? 0), url('back-office/invitations'), 'Relancer'],
    ['Photos manquantes (disque)', (int) ($anomalies['medias_manquants'] ?? 0), url('back-office/operations') . '#anomalies-medias', 'Voir'],
];
$anomalyTotal = 0;
foreach ($anomalyRows as $a) {
    $anomalyTotal += $a[1];
}
$athTableTitle = 'Anomalies d’onboarding et de configuration';
$athTableCount = $anomalyTotal . ' cas';
$athTableCols = ['ANOMALIE', 'MEMBRES CONCERNÉS|r', 'ÉTAT|b'];
$athTableRows = [];
$athTableRowActions = [];
$athTableRowHrefs = null;
foreach ($anomalyRows as [$label, $count, $href, $cta]) {
    $athTableRows[] = [
        $label,
        (string) $count,
        $count === 0 ? 'À jour' : 'En attente',
    ];
    // Balisage d’action construit ici, échappements compris (cf. contrat de ath_table.php).
    $athTableRowActions[] = $count === 0
        ? '<button type="button" class="ath-row-action" disabled>Rien à faire</button>'
        : '<a href="' . $h($href) . '" class="ath-row-action ath-row-action--accent">' . $h($cta) . '</a>';
}
$athTableActionsLabel = 'CORRECTION';
$athTableMinWidth = '900px';
$athTableFoot = $anomalyTotal === 0
    ? 'Aucune anomalie : profils, affectations, rôles, invitations et médias locaux sont à jour.'
    : 'Chaque ligne renvoie vers l’écran qui permet de corriger. Les photos manquantes signalent des chemins encore en base après migration.';
require base_path('views/partials/ath_table.php');

$missingMediaUsers = is_array($operationsMissingMediaUsers ?? null) ? $operationsMissingMediaUsers : [];
if ($missingMediaUsers !== []):
?>
<section id="anomalies-medias" class="ath-form ath-rise" style="margin-top:1.25rem">
    <div class="ath-form__head">
        <span class="ath-form__title">Photos / portraits à re-téléverser</span>
        <span class="ath-form__hint">
            Après la migration, certains fichiers d’uploads ne sont plus sur le serveur alors que le chemin reste en base.
            Demandez aux membres de recharger leur photo via Mon compte → Image / Portrait.
        </span>
    </div>
    <div class="ath-note" style="background:#fff7ed;border-color:#fed7aa;margin-bottom:12px">
        <p class="ath-note__title" style="color:#9a3412"><?= (int) count($missingMediaUsers) ?> compte(s) concerné(s)</p>
        <p class="ath-note__text" style="color:#9a3412">
            Une alerte apparaît aussi dans la cloche du membre pour l’inviter à re-téléverser. Les URLs externes (ex. Steam) ne sont pas signalées.
        </p>
    </div>
    <div class="ath-table-panel">
        <table class="ath-table" style="width:100%">
            <thead>
                <tr>
                    <th>Membre</th>
                    <th>Manque</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach (array_slice($missingMediaUsers, 0, 40) as $mu): ?>
                <?php
                $mid = (int) ($mu['id'] ?? 0);
                $kinds = is_array($mu['missing'] ?? null) ? $mu['missing'] : [];
                $kindLabels = [
                    'avatar' => 'Photo de compte',
                    'portrait' => 'Portrait personnage',
                    'banner' => 'Bannière',
                ];
                $kindTxt = [];
                foreach ($kinds as $k) {
                    $kindTxt[] = $kindLabels[(string) $k] ?? (string) $k;
                }
                ?>
                <tr>
                    <td>
                        <strong><?= $h((string) ($mu['display_name'] ?? '')) ?></strong>
                        <div style="font-size:12px;color:#64748b"><?= $h((string) ($mu['email'] ?? '')) ?></div>
                    </td>
                    <td><?= $h(implode(' · ', $kindTxt)) ?></td>
                    <td style="text-align:right">
                        <?php if ($mid > 0): ?>
                        <a class="ath-btn" href="<?= $h(url('back-office/users/' . $mid . '/edit')) ?>">Fiche</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php
endif;
?>

// ---- Alertes locales ----
$athTableTitle = 'Alertes locales actives';
$athTableCount = count($activeAlerts);
$athTableCols = ['ALERTE', 'ACTIVE DEPUIS|m'];
$athTableRows = [];
$athTableRowActions = null;
foreach ($activeAlerts as $alert) {
    $athTableRows[] = [
        (string) ($alert['title'] ?? 'Alerte'),
        $formatDate($alert['start_at'] ?? $alert['created_at'] ?? null),
    ];
}
$athTableMinWidth = '760px';
$athTableFoot = $alertsError !== null
    ? 'Indicateur indisponible : ' . (string) $alertsError
    : ($activeAlerts === [] ? 'Aucune alerte locale active.' : 'Gérées depuis Annonces & alertes.');
require base_path('views/partials/ath_table.php');
