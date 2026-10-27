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
$expiring = is_array($rhExpiringQualifications ?? null) ? $rhExpiringQualifications : [];
$vacant = is_array($rhVacantBillets ?? null) ? $rhVacantBillets : [];
$countById = [];
foreach ($items as $item) {
    $countById[(string) ($item['id'] ?? '')] = (int) ($item['count'] ?? 0);
}
$today = strtotime(date('Y-m-d'));

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
    <div class="eff-pg__actions">
        <a class="eff-catalog__btn" href="<?= $h(effectifs_workspace_url('a-traiter')) ?>">À traiter</a>
        <a class="eff-catalog__btn" href="<?= $h(effectifs_workspace_url('reglages')) ?>">Régler les seuils</a>
    </div>
    <p class="eff-page-kicker">Dossier RH</p>
    <h2 class="eff-page-title">Alertes RH</h2>
    <p class="eff-page-lead">
        Vue consolidée de ce qui demande un suivi : qualifications à renouveler, absences prolongées,
        inactivité, mobilité en attente et postes non pourvus.
    </p>
    <div class="eff-rh-tiles" aria-label="Synthèse des alertes">
        <article class="eff-rh-tile <?= $total > 0 ? 'eff-rh-tile--warn' : 'eff-rh-tile--ok' ?>">
            <span class="eff-rh-tile__kicker">Total</span>
            <strong class="eff-rh-tile__value"><?= $total ?></strong>
            <span class="eff-rh-tile__label"><?= locale() === 'en' ? 'active alert' . ($total > 1 ? 's' : '') : 'signalement' . ($total > 1 ? 's' : '') . ' actif' . ($total > 1 ? 's' : '') ?></span>
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
            <span class="eff-rh-list-card__count<?= $inactive !== [] ? ' is-warn' : '' ?>"><?= count($inactive) ?></span>
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
            <span class="eff-rh-list-card__count<?= $absences !== [] ? ' is-warn' : '' ?>"><?= count($absences) ?></span>
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
                        <span>Depuis le <?= $h($rhWhen((string) ($a['starts_on'] ?? ''))) ?> · <?= (int) ($a['days_open'] ?? 0) ?> j<?php if (trim((string) ($a['ends_on'] ?? '')) !== ''): ?> · retour prévu le <?= $h($rhWhen((string) $a['ends_on'])) ?><?php else: ?> · sans date de retour<?php endif; ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
    <section class="eff-rh-list-card">
        <div class="eff-rh-list-card__head">
            <h2 class="eff-rh-list-card__title">Qualifications à renouveler</h2>
            <span class="eff-rh-list-card__count<?= $expiring !== [] ? ' is-warn' : '' ?>"><?= count($expiring) ?></span>
            <?php $rhTip('tip-alert-qualif-list', 'À propos des échéances', $tipById['qualif_expiring']); ?>
        </div>
        <p class="eff-rh-list-card__lead">Échues ou expirant dans les 60 prochains jours, les plus urgentes d’abord.</p>
        <?php if ($expiring === []): ?>
            <p class="eff-rh-list-card__empty">Aucune qualification n’arrive à échéance.</p>
        <?php else: ?>
            <ul class="eff-rh-people">
                <?php foreach ($expiring as $q): ?>
                    <?php
                    $uid = (int) ($q['user_id'] ?? 0);
                    $exp = trim((string) ($q['expires_at'] ?? ''));
                    $expTs = $exp !== '' ? strtotime(substr($exp, 0, 10)) : false;
                    $daysLeft = $expTs ? (int) round(($expTs - $today) / 86400) : null;
                    $who = trim((string) ($q['display_name'] ?? '')) ?: (trim((string) ($q['callsign'] ?? '')) ?: 'Membre');
                    ?>
                    <li>
                        <a href="<?= $h(effectifs_workspace_url('membres/' . $uid)) ?>"><?= $h($who) ?><small><?= $h((string) ($q['qualification_name'] ?? 'Qualification')) ?><?= trim((string) ($q['level'] ?? '')) !== '' ? ' · ' . $h((string) $q['level']) : '' ?></small></a>
                        <?php if ($daysLeft === null): ?>
                            <span>—</span>
                        <?php elseif ($daysLeft < 0): ?>
                            <span class="is-late">Échue depuis <?= -$daysLeft ?> j</span>
                        <?php else: ?>
                            <span class="<?= $daysLeft <= 14 ? 'is-soon' : '' ?>">Expire le <?= $h($rhWhen($exp)) ?> · <?= $daysLeft ?> j</span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (($countById['qualif_expiring'] ?? 0) > count($expiring)): ?>
                <p class="eff-rh-list-card__more"><a href="<?= $h(effectifs_workspace_url('qualifications')) ?>">Voir les <?= (int) $countById['qualif_expiring'] ?> échéances →</a></p>
            <?php endif; ?>
        <?php endif; ?>
    </section>
    <section class="eff-rh-list-card">
        <div class="eff-rh-list-card__head">
            <h2 class="eff-rh-list-card__title">Postes à pourvoir</h2>
            <span class="eff-rh-list-card__count<?= $vacant !== [] ? ' is-warn' : '' ?>"><?= count($vacant) ?></span>
            <?php $rhTip('tip-alert-billets-list', 'À propos des postes', $tipById['vacant_billets']); ?>
        </div>
        <p class="eff-rh-list-card__lead">Postes de l’organigramme avec moins de titulaires que prévu, les critiques d’abord.</p>
        <?php if ($vacant === []): ?>
            <p class="eff-rh-list-card__empty">Tous les postes de l’organigramme sont pourvus.</p>
        <?php else: ?>
            <?php
            // Liste longue : les 6 premiers postes (critiques d'abord), le reste replié pour garder les cartes alignées.
            $vacantShown = array_slice($vacant, 0, 6);
            $vacantRest = array_slice($vacant, 6);
            $vacantRow = static function (array $b) use ($h): void {
                $held = (int) ($b['held'] ?? 0);
                $authorized = (int) ($b['authorized'] ?? 0);
                ?>
                    <li>
                        <a href="<?= $h(url('back-office/organisation/structure') . ((int) ($b['unit_id'] ?? 0) > 0 ? '?unite=' . (int) $b['unit_id'] : '')) ?>"><?= $h((string) ($b['title'] ?? 'Poste')) ?><small><?= $h(trim((string) ($b['unit_name'] ?? '')) ?: 'Unité') ?><?= trim((string) ($b['code'] ?? '')) !== '' ? ' · ' . $h((string) $b['code']) : '' ?></small></a>
                        <span class="eff-rh-fill<?= !empty($b['critical']) ? ' is-late' : '' ?>">
                            <b><?= $held ?> / <?= $authorized ?></b>
                            <?php if (!empty($b['critical'])): ?><em class="eff-rh-pill eff-rh-pill--bad">Critique</em><?php endif; ?>
                        </span>
                    </li>
                <?php
            };
            ?>
            <p class="eff-rh-fill-legend">Titulaires / prévus</p>
            <ul class="eff-rh-people">
                <?php foreach ($vacantShown as $b) { $vacantRow($b); } ?>
            </ul>
            <?php if ($vacantRest !== []): ?>
            <details class="eff-rh-more">
                <summary>Afficher les <?= count($vacantRest) ?> autres postes</summary>
                <ul class="eff-rh-people">
                    <?php foreach ($vacantRest as $b) { $vacantRow($b); } ?>
                </ul>
            </details>
            <?php endif; ?>
            <p class="eff-rh-list-card__more"><a href="<?= $h(effectifs_workspace_url('vivier')) ?>">Préparer une relève dans le vivier →</a></p>
        <?php endif; ?>
    </section>
</div>
<?php if (($countById['mobility_pending'] ?? 0) > 0): ?>
<p class="bo-eff-jump">
    <strong>Mobilité</strong>
    <a href="<?= $h(effectifs_workspace_url('mobilite')) ?>"><?= (int) $countById['mobility_pending'] ?> demande<?= (int) $countById['mobility_pending'] > 1 ? 's' : '' ?> de mobilité en attente de décision</a>
</p>
<?php endif; ?>

<?php $rhShortcutCurrent = 'alertes'; require base_path('views/admin/effectifs_workspace/partials/rh_shortcuts.php'); ?>
