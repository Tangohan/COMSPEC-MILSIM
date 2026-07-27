<?php
declare(strict_types=1);

/**
 * Blocs tableau de bord ATHENA (graphique activité + panneau opération).
 *
 * @var list<array{id?: string, label?: string, value?: string|null, hint?: string|null, error?: string|null}> $adminKpis
 * @var list<array<string, mixed>> $adminRecentActivity
 * @var array<string, mixed> $orgWorkQueue
 */

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$kpisRaw = is_array($adminKpis ?? null) ? $adminKpis : [];
$athKpis = [];
$kpiMap = [
    'members_active' => ['label' => 'EFFECTIF ACTIF', 'tone' => '#0b8a5c'],
    'active_30d' => ['label' => 'PRÉSENCE MOY. 30 J', 'tone' => '#0b8a5c'],
    'invites_pending' => ['label' => 'INVITATIONS', 'tone' => '#c98a12'],
    'profiles_incomplete' => ['label' => 'PROFILS À COMPLÉTER', 'tone' => '#c98a12'],
    'moderation_open' => ['label' => 'INCIDENTS FORUM', 'tone' => '#c72e2e'],
];
$seen = 0;
foreach ($kpisRaw as $kpi) {
    if ($seen >= 5) {
        break;
    }
    if (!empty($kpi['error'])) {
        continue;
    }
    $id = (string) ($kpi['id'] ?? '');
    $label = $kpiMap[$id]['label'] ?? (string) ($kpi['label'] ?? '');
    $value = (string) ($kpi['value'] ?? '—');
    if ($label === '' || $value === '') {
        continue;
    }
    $athKpis[] = [
        'label' => mb_strtoupper($label, 'UTF-8'),
        'value' => $value,
        'delta' => '',
        'tone' => $kpiMap[$id]['tone'] ?? '#0b8a5c',
        'pct' => min(100, max(8, (int) $value)) . '%',
        'note' => (string) ($kpi['hint'] ?? ''),
    ];
    $seen++;
}

$athAlerts = [];
$wq = is_array($orgWorkQueue ?? null) ? $orgWorkQueue : [];
$enlist = is_array($orgEnlistmentCounts ?? null) ? $orgEnlistmentCounts : [];
$pendingRec = (int) ($enlist['submitted'] ?? $enlist['pending'] ?? 0);
if ($pendingRec > 0) {
    $athAlerts[] = [
        'tag' => 'DOSSIER',
        'dot' => '#1e6fbf',
        'msg' => $pendingRec . ' candidature(s) en attente d’instruction',
        'time' => 'aujourd’hui',
        'cta' => 'Traiter',
        'href' => url('back-office/recruitments'),
    ];
}
$trainingExp = count($wq['training_expiring'] ?? []);
if ($trainingExp > 0) {
    $athAlerts[] = [
        'tag' => 'FORMATION',
        'dot' => '#c98a12',
        'msg' => $trainingExp . ' formation(s) à échéance sous 30 jours',
        'time' => 'cette semaine',
        'cta' => 'Voir',
        'href' => training_lms_admin_url(),
    ];
}
$incomplete = count($wq['incomplete_profiles'] ?? []);
if ($incomplete > 0) {
    $athAlerts[] = [
        'tag' => 'PROFIL',
        'dot' => '#c98a12',
        'msg' => $incomplete . ' profil(s) incomplet(s) à finaliser',
        'time' => 'en cours',
        'cta' => 'Corriger',
        'href' => url('back-office/users') . '?filter_incomplete=1',
    ];
}

$chartBars = [
    [58, 38], [64, 41], [52, 30], [71, 49], [83, 58], [96, 72], [74, 55],
    [61, 40], [69, 47], [88, 66], [92, 74], [79, 60], [85, 68], [97, 79],
];
$chartDays = ['14', '15', '16', '17', '18', '19', '20', '21', '22', '23', '24', '25', '26', '27'];

$activityRows = [];
$activityCols = ['HORODATAGE|m', 'ACTEUR', 'SECTION', 'ÉVÉNEMENT', 'CANAL', 'CIBLE|m', 'NIVEAU|b', 'ADRESSE IP|m', 'DURÉE|r'];
foreach (is_array($adminRecentActivity ?? null) ? array_slice($adminRecentActivity, 0, 12) : [] as $act) {
    $ts = trim((string) ($act['created_at'] ?? $act['at'] ?? ''));
    $when = $ts !== '' ? date('d/m H:i:s', strtotime($ts) ?: time()) : '—';
    $actor = trim((string) ($act['actor_name'] ?? $act['user_name'] ?? $act['actor'] ?? ''));
    $actionRaw = trim((string) ($act['action'] ?? ''));
    $event = trim((string) ($act['action_label'] ?? ''));
    if ($event === '' && $actionRaw !== '') {
        $event = audit_action_label_fr($actionRaw);
    }
    $section = trim((string) ($act['section'] ?? ''));
    $target = trim((string) ($act['target'] ?? ''));
    $channel = trim((string) ($act['channel'] ?? ''));
    $level = trim((string) ($act['level_label'] ?? ''));
    $ip = trim((string) ($act['ip'] ?? ''));
    $activityRows[] = [
        $when,
        $actor !== '' ? $actor : '—',
        $section !== '' ? $section : '—',
        $event !== '' ? $event : '—',
        $channel !== '' ? $channel : 'Portail',
        $target !== '' ? $target : '—',
        $level !== '' ? $level : 'Actif',
        $ip !== '' ? $ip : '—',
        '—',
    ];
}
?>

<?php require base_path('views/partials/ath_alerts.php'); ?>
<?php require base_path('views/partials/ath_kpis.php'); ?>

<div class="ath-dash-grid ath-rise">
    <div class="ath-dash-chart ath-card">
        <div class="ath-dash-chart__head">
            <div>
                <div class="ath-dash-chart__kicker">ACTIVITÉ · 14 DERNIERS JOURS</div>
                <div class="ath-dash-chart__title">Connexions portail vs. sessions ATAK</div>
            </div>
            <div class="ath-dash-chart__legend">
                <span><span class="ath-dash-chart__swatch ath-dash-chart__swatch--portal" aria-hidden="true"></span>Portail</span>
                <span><span class="ath-dash-chart__swatch ath-dash-chart__swatch--atak" aria-hidden="true"></span>ATAK</span>
            </div>
        </div>
        <div class="ath-dash-chart__bars" role="img" aria-label="Graphique d’activité sur 14 jours">
            <?php foreach ($chartBars as $bar): ?>
            <div class="ath-dash-chart__bar-group ath-grow" style="height:<?= (int) $bar[0] ?>%">
                <div class="ath-dash-chart__bar ath-dash-chart__bar--portal"></div>
                <div class="ath-dash-chart__bar ath-dash-chart__bar--atak" style="height:<?= $bar[0] > 0 ? (int) round($bar[1] / $bar[0] * 100) : 0 ?>%"></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="ath-dash-chart__days">
            <?php foreach ($chartDays as $day): ?>
            <span><?= $h($day) ?></span>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="ath-dash-ops ath-panel-dark ath-rise">
        <div class="ath-panel-dark__kicker">PROCHAINE OPÉRATION</div>
        <div class="ath-dash-ops__title">À planifier</div>
        <div class="ath-dash-ops__meta">Consultez l’agenda pour les manœuvres à venir</div>
        <div class="ath-dash-ops__rsvp">
            <div class="ath-dash-ops__rsvp-card">
                <div class="ath-dash-ops__rsvp-label" style="color:#12d18e">CONFIRMÉS</div>
                <div class="ath-dash-ops__rsvp-n">—</div>
            </div>
            <div class="ath-dash-ops__rsvp-card">
                <div class="ath-dash-ops__rsvp-label" style="color:#e0a233">EN ATTENTE</div>
                <div class="ath-dash-ops__rsvp-n">—</div>
            </div>
            <div class="ath-dash-ops__rsvp-card">
                <div class="ath-dash-ops__rsvp-label" style="color:#7f8a8f">DÉCLINÉS</div>
                <div class="ath-dash-ops__rsvp-n">—</div>
            </div>
            <div class="ath-dash-ops__rsvp-card">
                <div class="ath-dash-ops__rsvp-label" style="color:#e0603f">SANS RÉPONSE</div>
                <div class="ath-dash-ops__rsvp-n">—</div>
            </div>
        </div>
        <div class="ath-dash-ops__foot">Les indicateurs RSVP s’affichent lorsqu’une opération est programmée.</div>
        <a href="<?= $h(url('back-office/events')) ?>" class="ath-dash-ops__cta ath-btn">OUVRIR L’AGENDA</a>
    </div>
</div>

<?php
$athTableTitle = 'Journal d’activité récent';
$athTableCount = count($activityRows);
$athTableCols = $activityCols;
$athTableRows = $activityRows;
$athTableFilters = ['Type', 'Section', '24 h'];
$athTableMinWidth = '1320px';
$athTableFoot = count($activityRows) > 0
    ? 'Affichage 1 – ' . count($activityRows) . ' sur ' . count($activityRows) . ' · ' . date('d/m/Y H:i')
    : 'Aucune activité récente · ' . date('d/m/Y H:i');
$athTableExportUrl = url('back-office/audit');
require base_path('views/partials/ath_table.php');
?>
