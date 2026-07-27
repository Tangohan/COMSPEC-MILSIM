<?php
declare(strict_types=1);

/**
 * Blocs tableau de bord ATHENA (graphique activité + panneau opération + files RH).
 *
 * @var list<array{id?: string, label?: string, value?: string|null, hint?: string|null, error?: string|null}> $adminKpis
 * @var list<array<string, mixed>> $adminRecentActivity
 * @var array<string, mixed> $orgWorkQueue
 * @var array{days?:list<string>, portal?:list<int>, atak?:list<int>, max?:int, period_days?:int}|null $orgActivityChart
 * @var array<string, mixed>|null $orgNextOperation
 * @var list<array<string, mixed>> $orgElevationOpen
 * @var int $orgElevationOpenCount
 * @var array<string, string> $orgElevationKindLabels
 * @var list<array<string, mixed>> $orgEnlistmentRecent
 * @var list<array<string, mixed>> $orgMessagesRecent
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
$elevOpenCount = (int) ($orgElevationOpenCount ?? 0);
if ($elevOpenCount > 0) {
    $athAlerts[] = [
        'tag' => 'ÉLÉVATION',
        'dot' => '#0b8a5c',
        'msg' => $elevOpenCount . ' demande(s) d’élévation à traiter',
        'time' => 'en cours',
        'cta' => 'Examiner',
        'href' => effectifs_workspace_url('elevations'),
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

$chart = is_array($orgActivityChart ?? null) ? $orgActivityChart : [];
$chartPortal = is_array($chart['portal'] ?? null) ? $chart['portal'] : [];
$chartAtak = is_array($chart['atak'] ?? null) ? $chart['atak'] : [];
$chartDays = is_array($chart['days'] ?? null) ? $chart['days'] : [];
$chartMax = max(1, (int) ($chart['max'] ?? 1));
$chartPeriod = (int) ($chart['period_days'] ?? max(count($chartDays), 14));
$chartBars = [];
$nBars = max(count($chartPortal), count($chartAtak), count($chartDays));
for ($i = 0; $i < $nBars; $i++) {
    $p = (int) ($chartPortal[$i] ?? 0);
    $a = (int) ($chartAtak[$i] ?? 0);
    $chartBars[] = [
        (int) round($p / $chartMax * 100),
        (int) round($a / $chartMax * 100),
        $p,
        $a,
    ];
}
if ($chartBars === []) {
    $chartDays = [];
    for ($i = $chartPeriod - 1; $i >= 0; $i--) {
        $chartDays[] = date('d', strtotime('-' . $i . ' days') ?: time());
        $chartBars[] = [0, 0, 0, 0];
    }
}

$nextOp = is_array($orgNextOperation ?? null) ? $orgNextOperation : null;
$nextTitle = $nextOp !== null ? (string) ($nextOp['title'] ?? 'Manœuvre') : 'À planifier';
$nextMeta = $nextOp !== null
    ? ((string) ($nextOp['starts_label'] ?? '') !== ''
        ? (string) $nextOp['starts_label']
        : 'Consultez l’agenda pour le détail')
    : 'Consultez l’agenda pour les manœuvres à venir';
$nextHref = $nextOp !== null && !empty($nextOp['href'])
    ? (string) $nextOp['href']
    : url('back-office/events');
$rsvp = is_array($nextOp['rsvp'] ?? null) ? $nextOp['rsvp'] : null;
$fmtN = static function (?int $n) use ($nextOp): string {
    if ($nextOp === null) {
        return '—';
    }

    return (string) max(0, (int) $n);
};

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

$elevations = is_array($orgElevationOpen ?? null) ? $orgElevationOpen : [];
$elevKindLabels = is_array($orgElevationKindLabels ?? null) ? $orgElevationKindLabels : [];
$recruitments = is_array($orgEnlistmentRecent ?? null) ? array_slice($orgEnlistmentRecent, 0, 5) : [];
$messages = is_array($orgMessagesRecent ?? null) ? array_slice($orgMessagesRecent, 0, 2) : [];

$enlistStatusLabel = static function (string $status): string {
    return match ($status) {
        'submitted' => 'À instruire',
        'interview_scheduled' => 'Entretien planifié',
        'on_hold' => 'En attente',
        'accepted', 'reviewed' => 'Acceptée',
        'rejected' => 'Refusée',
        'blocked' => 'Non admis',
        'cancelled' => 'Annulée',
        default => 'En cours',
    };
};
$elevStatusLabel = static function (string $status): string {
    return match ($status) {
        'pending' => 'En attente',
        'in_review' => 'Examen',
        'approved' => 'Acceptée',
        'rejected' => 'Refusée',
        default => 'En cours',
    };
};
$personName = static function (?string $display, ?string $email, string $fallback = 'Membre'): string {
    $display = trim((string) $display);
    if ($display !== '') {
        return $display;
    }
    $email = trim((string) $email);

    return $email !== '' ? $email : $fallback;
};
$previewClip = static function (string $text, int $max = 96): string {
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    if ($text === '') {
        return 'Aucun aperçu';
    }
    if (mb_strlen($text) <= $max) {
        return $text;
    }

    return mb_substr($text, 0, $max - 1) . '…';
};
?>

<?php require base_path('views/partials/ath_alerts.php'); ?>
<?php require base_path('views/partials/ath_kpis.php'); ?>

<div class="ath-dash-grid ath-rise">
    <div class="ath-dash-chart ath-card">
        <div class="ath-dash-chart__head">
            <div>
                <div class="ath-dash-chart__kicker">ACTIVITÉ · <?= (int) $chartPeriod ?> DERNIERS JOURS</div>
                <div class="ath-dash-chart__title">Connexions portail vs. sessions ATAK</div>
            </div>
            <div class="ath-dash-chart__legend">
                <span><span class="ath-dash-chart__swatch ath-dash-chart__swatch--portal" aria-hidden="true"></span>Portail</span>
                <span><span class="ath-dash-chart__swatch ath-dash-chart__swatch--atak" aria-hidden="true"></span>ATAK</span>
            </div>
        </div>
        <div class="ath-dash-chart__bars" role="img" aria-label="Graphique d’activité sur <?= (int) $chartPeriod ?> jours">
            <?php foreach ($chartBars as $bar): ?>
            <?php
                $portalPct = max(0, min(100, (int) $bar[0]));
                $atakPct = max(0, min(100, (int) $bar[1]));
                $groupPct = max($portalPct, $atakPct, 4);
            ?>
            <div class="ath-dash-chart__bar-group ath-grow" style="height:<?= $groupPct ?>%" title="Portail <?= (int) $bar[2] ?> · ATAK <?= (int) $bar[3] ?>">
                <div class="ath-dash-chart__bar ath-dash-chart__bar--portal" style="height:<?= $groupPct > 0 ? (int) round($portalPct / $groupPct * 100) : 0 ?>%"></div>
                <div class="ath-dash-chart__bar ath-dash-chart__bar--atak" style="height:<?= $groupPct > 0 ? (int) round($atakPct / $groupPct * 100) : 0 ?>%"></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="ath-dash-chart__days">
            <?php foreach ($chartDays as $day): ?>
            <span><?= $h((string) $day) ?></span>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="ath-dash-ops ath-panel-dark ath-rise">
        <div class="ath-panel-dark__kicker">PROCHAINE OPÉRATION</div>
        <div class="ath-dash-ops__title"><?= $h($nextTitle) ?></div>
        <div class="ath-dash-ops__meta"><?= $h($nextMeta) ?></div>
        <div class="ath-dash-ops__rsvp">
            <div class="ath-dash-ops__rsvp-card">
                <div class="ath-dash-ops__rsvp-label" style="color:#12d18e">CONFIRMÉS</div>
                <div class="ath-dash-ops__rsvp-n"><?= $h($fmtN(isset($rsvp['yes']) ? (int) $rsvp['yes'] : null)) ?></div>
            </div>
            <div class="ath-dash-ops__rsvp-card">
                <div class="ath-dash-ops__rsvp-label" style="color:#e0a233">EN ATTENTE</div>
                <div class="ath-dash-ops__rsvp-n"><?= $h($fmtN(isset($rsvp['maybe']) ? (int) $rsvp['maybe'] : null)) ?></div>
            </div>
            <div class="ath-dash-ops__rsvp-card">
                <div class="ath-dash-ops__rsvp-label" style="color:#7f8a8f">DÉCLINÉS</div>
                <div class="ath-dash-ops__rsvp-n"><?= $h($fmtN(isset($rsvp['no']) ? (int) $rsvp['no'] : null)) ?></div>
            </div>
            <div class="ath-dash-ops__rsvp-card">
                <div class="ath-dash-ops__rsvp-label" style="color:#e0603f">SANS RÉPONSE</div>
                <div class="ath-dash-ops__rsvp-n"><?= $h($fmtN(isset($rsvp['no_reply']) ? (int) $rsvp['no_reply'] : null)) ?></div>
            </div>
        </div>
        <div class="ath-dash-ops__foot">
            <?= $nextOp !== null
                ? 'Compteurs basés sur les réponses des membres actifs.'
                : 'Les indicateurs RSVP s’affichent lorsqu’une opération est programmée.' ?>
        </div>
        <a href="<?= $h($nextHref) ?>" class="ath-dash-ops__cta ath-btn">
            <?= $nextOp !== null ? 'OUVRIR LA FICHE' : 'OUVRIR L’AGENDA' ?>
        </a>
    </div>
</div>

<div class="ath-dash-feeds ath-rise">
    <section class="ath-dash-feed ath-card" aria-labelledby="ath-feed-elev-title">
        <div class="ath-dash-feed__head">
            <div>
                <p class="ath-dash-feed__kicker">Ressources humaines</p>
                <h2 id="ath-feed-elev-title" class="ath-dash-feed__title">Demandes d’élévation</h2>
            </div>
            <a class="ath-dash-feed__link" href="<?= $h(effectifs_workspace_url('elevations')) ?>">Tout voir →</a>
        </div>
        <?php if ($elevations === []): ?>
            <p class="ath-dash-feed__empty">Aucune demande en attente pour le moment.</p>
        <?php else: ?>
            <ul class="ath-dash-feed__list">
                <?php foreach ($elevations as $elev): ?>
                    <?php
                    $target = $personName(
                        isset($elev['target_display_name']) ? (string) $elev['target_display_name'] : null,
                        isset($elev['target_email']) ? (string) $elev['target_email'] : null
                    );
                    $kind = (string) ($elev['kind'] ?? 'general');
                    $kindLabel = (string) ($elevKindLabels[$kind] ?? 'Situation RH');
                    $status = (string) ($elev['status'] ?? 'pending');
                    $created = trim((string) ($elev['created_at'] ?? ''));
                    $when = $created !== '' ? date('d/m H:i', strtotime($created) ?: time()) : '';
                    ?>
                    <li class="ath-dash-feed__item">
                        <div class="ath-dash-feed__item-main">
                            <strong><?= $h($target) ?></strong>
                            <span><?= $h($kindLabel) ?><?= $when !== '' ? ' · ' . $h($when) : '' ?></span>
                        </div>
                        <span class="ath-dash-feed__badge"><?= $h($elevStatusLabel($status)) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="ath-dash-feed ath-card" aria-labelledby="ath-feed-rec-title">
        <div class="ath-dash-feed__head">
            <div>
                <p class="ath-dash-feed__kicker">Recrutement</p>
                <h2 id="ath-feed-rec-title" class="ath-dash-feed__title">Derniers dossiers</h2>
            </div>
            <a class="ath-dash-feed__link" href="<?= $h(url('back-office/recruitments')) ?>">Tout voir →</a>
        </div>
        <?php if ($recruitments === []): ?>
            <p class="ath-dash-feed__empty">Aucun dossier de recrutement récent.</p>
        <?php else: ?>
            <ul class="ath-dash-feed__list">
                <?php foreach ($recruitments as $rec): ?>
                    <?php
                    $fn = trim((string) ($rec['first_name'] ?? ''));
                    $ln = trim((string) ($rec['last_name'] ?? ''));
                    $cs = trim((string) ($rec['callsign'] ?? ''));
                    $name = trim($fn . ' ' . $ln);
                    if ($name === '') {
                        $name = $cs !== '' ? $cs : $personName(null, isset($rec['email']) ? (string) $rec['email'] : null, 'Candidat');
                    } elseif ($cs !== '') {
                        $name .= ' (« ' . $cs . ' »)';
                    }
                    $status = (string) ($rec['status'] ?? '');
                    $created = trim((string) ($rec['updated_at'] ?? $rec['created_at'] ?? ''));
                    $when = $created !== '' ? date('d/m H:i', strtotime($created) ?: time()) : '';
                    $rid = (int) ($rec['id'] ?? 0);
                    $href = $rid > 0 ? url('back-office/recruitments/' . $rid) : url('back-office/recruitments');
                    ?>
                    <li class="ath-dash-feed__item">
                        <a class="ath-dash-feed__item-main ath-dash-feed__item-main--link" href="<?= $h($href) ?>">
                            <strong><?= $h($name) ?></strong>
                            <span><?= $when !== '' ? $h($when) : 'Récent' ?></span>
                        </a>
                        <span class="ath-dash-feed__badge"><?= $h($enlistStatusLabel($status)) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="ath-dash-feed ath-card" aria-labelledby="ath-feed-msg-title">
        <div class="ath-dash-feed__head">
            <div>
                <p class="ath-dash-feed__kicker">Messagerie</p>
                <h2 id="ath-feed-msg-title" class="ath-dash-feed__title">Derniers messages</h2>
            </div>
            <a class="ath-dash-feed__link" href="<?= $h(url('messages')) ?>">Ouvrir →</a>
        </div>
        <?php if ($messages === []): ?>
            <p class="ath-dash-feed__empty">Aucune conversation récente dans votre messagerie.</p>
        <?php else: ?>
            <ul class="ath-dash-feed__list">
                <?php foreach ($messages as $msg): ?>
                    <?php
                    $subject = trim((string) ($msg['subject'] ?? ''));
                    if ($subject === '') {
                        $subject = 'Conversation';
                    }
                    $preview = $previewClip((string) ($msg['last_preview'] ?? ''));
                    $updated = trim((string) ($msg['updated_at'] ?? ''));
                    $when = $updated !== '' ? date('d/m H:i', strtotime($updated) ?: time()) : '';
                    $tid = (int) ($msg['id'] ?? 0);
                    $unread = !empty($msg['thread_unread']);
                    $href = $tid > 0 ? url('messages/' . $tid) : url('messages');
                    ?>
                    <li class="ath-dash-feed__item">
                        <a class="ath-dash-feed__item-main ath-dash-feed__item-main--link" href="<?= $h($href) ?>">
                            <strong><?= $h($subject) ?><?= $unread ? ' · Non lu' : '' ?></strong>
                            <span><?= $h($preview) ?><?= $when !== '' ? ' · ' . $h($when) : '' ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
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
