<?php
declare(strict_types=1);

require base_path('views/admin/effectifs_workspace/partials/rh_ui_helpers.php');

$summary = is_array($rhAlertSummary ?? null) ? $rhAlertSummary : ['items' => [], 'total' => 0];
$items = is_array($summary['items'] ?? null) ? $summary['items'] : [];
$inactive = is_array($rhInactiveMembers ?? null) ? $rhInactiveMembers : [];
$absences = is_array($rhProlongedAbsences ?? null) ? $rhProlongedAbsences : [];
$inactivityDays = (int) ($rhInactivityDays ?? 45);
$absenceDays = (int) ($rhProlongedAbsenceDays ?? 14);
$total = (int) ($summary['total'] ?? 0);

$toneLabel = [
    'warn' => 'À traiter',
    'info' => 'À suivre',
    'ok' => 'Rien à signaler',
];
$tipById = [
    'qualif_expiring' => 'Qualifications dont l’échéance tombe dans les soixante prochains jours. Le renouvellement passe par une formation certifiante.',
    'prolonged_absence' => 'Absences encore ouvertes depuis au moins ' . $absenceDays . ' jours. Vérifiez si un retour ou une décision est attendue.',
    'inactive_members' => 'Comptes actifs sans connexion depuis ' . $inactivityDays . ' jours. Ce n’est pas un départ : c’est un signal de suivi.',
    'mobility_pending' => 'Demandes de mobilité interne encore sans décision.',
    'vacant_billets' => 'Postes de l’organigramme sans titulaire. Ouvrez le vivier ou la structure pour préparer une relève.',
];
?>
<section class="eff-rh-hero">
    <p class="eff-page-kicker">Dossier RH</p>
    <h1 class="eff-page-title">Alertes RH</h1>
    <p class="eff-page-lead">
        Vue consolidée de ce qui demande un suivi : qualifications à renouveler, absences prolongées,
        inactivité, mobilité en attente et postes non pourvus.
    </p>
    <div class="eff-rh-tiles" aria-label="Synthèse des alertes">
        <article class="eff-rh-tile <?= $total > 0 ? 'eff-rh-tile--warn' : 'eff-rh-tile--ok' ?>">
            <span class="eff-rh-tile__kicker">À suivre</span>
            <strong class="eff-rh-tile__value"><?= $total ?></strong>
            <span class="eff-rh-tile__label">signalement<?= $total > 1 ? 's' : '' ?> actif<?= $total > 1 ? 's' : '' ?></span>
        </article>
        <?php foreach ($items as $item): ?>
            <?php
            $itemCount = (int) ($item['count'] ?? 0);
            $href = (string) ($item['href'] ?? effectifs_workspace_url('alertes'));
            $tone = (string) ($item['tone'] ?? 'ok');
            $id = (string) ($item['id'] ?? '');
            $tip = $tipById[$id] ?? '';
            ?>
            <article class="eff-rh-tile <?= $tone === 'warn' ? 'eff-rh-tile--warn' : ($tone === 'info' ? 'eff-rh-tile--info' : 'eff-rh-tile--ok') ?>">
                <span class="eff-rh-tile__kicker">
                    <?= $h((string) ($item['severity'] ?? '')) ?>
                    <?php if ($tip !== ''): ?>
                        <?php $rhTip('tip-alert-' . $id, 'Précision', $tip); ?>
                    <?php endif; ?>
                </span>
                <a class="eff-rh-tile__hit" href="<?= $h($href) ?>">
                    <strong class="eff-rh-tile__value"><?= $itemCount ?></strong>
                    <span class="eff-rh-tile__label"><?= $h((string) ($item['label'] ?? '')) ?></span>
                    <em class="eff-rh-tile__tone"><?= $h($toneLabel[$tone] ?? '') ?></em>
                </a>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<div class="eff-rh-split">
    <section class="eff-rh-list-card">
        <div class="eff-rh-list-card__head">
            <h2 class="eff-rh-list-card__title">Sans activité</h2>
            <?php $rhTip('tip-alert-inact-list', 'À propos de l’inactivité', 'Membres au compte actif qui ne se sont pas connectés depuis ' . $inactivityDays . ' jours. Ouvrez la fiche pour relancer ou noter une absence.'); ?>
        </div>
        <p class="eff-rh-list-card__lead">Aucune connexion depuis au moins <?= $inactivityDays ?> jours.</p>
        <?php if ($inactive === []): ?>
            <p class="eff-rh-list-card__empty">Aucun membre actif concerné.</p>
        <?php else: ?>
            <ul class="eff-rh-people">
                <?php foreach ($inactive as $m): ?>
                    <?php
                    $uid = (int) ($m['id'] ?? 0);
                    $lastRaw = trim((string) ($m['last_login_at'] ?? ''));
                    $lastLabel = $lastRaw === '' ? 'jamais' : $rhWhen($lastRaw);
                    ?>
                    <li>
                        <a href="<?= $h(effectifs_workspace_url('membres/' . $uid)) ?>"><?= $h(trim((string) ($m['display_name'] ?? '')) ?: (string) ($m['email'] ?? 'Membre')) ?></a>
                        <span>Dernière connexion : <?= $h($lastLabel) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
    <section class="eff-rh-list-card">
        <div class="eff-rh-list-card__head">
            <h2 class="eff-rh-list-card__title">Absences prolongées</h2>
            <?php $rhTip('tip-alert-abs-list', 'À propos des absences', 'Absences encore ouvertes depuis au moins ' . $absenceDays . ' jours. Vérifiez la date de retour ou une décision de suivi.'); ?>
        </div>
        <p class="eff-rh-list-card__lead">Absences ouvertes depuis au moins <?= $absenceDays ?> jours.</p>
        <?php if ($absences === []): ?>
            <p class="eff-rh-list-card__empty">Aucune absence prolongée en cours.</p>
        <?php else: ?>
            <ul class="eff-rh-people">
                <?php foreach ($absences as $a): ?>
                    <?php $uid = (int) ($a['user_id'] ?? 0); ?>
                    <li>
                        <a href="<?= $h(effectifs_workspace_url('membres/' . $uid)) ?>"><?= $h(trim((string) ($a['user_display_name'] ?? '')) ?: (string) ($a['user_email'] ?? 'Membre')) ?></a>
                        <span>Depuis le <?= $h($rhWhen((string) ($a['starts_on'] ?? ''))) ?> · <?= (int) ($a['days_open'] ?? 0) ?> j</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<?php $rhShortcutCurrent = 'alertes'; require base_path('views/admin/effectifs_workspace/partials/rh_shortcuts.php'); ?>
