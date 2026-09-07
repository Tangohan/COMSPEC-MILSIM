<?php
declare(strict_types=1);

require base_path('views/admin/effectifs_workspace/partials/rh_ui_helpers.php');

$rows = is_array($roleplayDueItems ?? null) ? $roleplayDueItems : [];
$cfg = is_array($roleplayConfig ?? null) ? $roleplayConfig : [];
$enabled = !empty($cfg['enabled']);
$overdue = 0;
$upcoming = 0;
foreach ($rows as $row) {
    if (($row['urgency'] ?? '') === 'overdue') {
        $overdue++;
    } else {
        $upcoming++;
    }
}
$typeLabels = [
    'entretien' => 'Entretien',
    'medical' => 'Visite médicale',
    'rotation' => 'Rotation',
    'bilan' => 'Bilan',
];
?>
<section class="eff-rh-hero">
    <p class="eff-page-kicker">Dossier RH</p>
    <h2 class="eff-page-title">Suivi roleplay</h2>
    <p class="eff-page-lead">
        Échéances d’entretien, de visite médicale et de rotation. Le détail et la saisie restent dans le suivi d’immersion.
    </p>
    <div class="eff-rh-tiles" aria-label="Aperçu du suivi roleplay">
        <article class="eff-rh-tile <?= $enabled ? 'eff-rh-tile--ok' : 'eff-rh-tile--warn' ?>">
            <span class="eff-rh-tile__kicker">Suivi</span>
            <strong class="eff-rh-tile__value"><?= $enabled ? 'Actif' : 'En pause' ?></strong>
            <span class="eff-rh-tile__label"><?= $enabled ? 'Les échéances sont suivies' : 'Activez-le dans Réglages' ?></span>
        </article>
        <article class="eff-rh-tile <?= $overdue > 0 ? 'eff-rh-tile--warn' : 'eff-rh-tile--ok' ?>">
            <span class="eff-rh-tile__kicker">En retard</span>
            <strong class="eff-rh-tile__value"><?= $overdue ?></strong>
            <span class="eff-rh-tile__label">échéance<?= $overdue > 1 ? 's' : '' ?> dépassée<?= $overdue > 1 ? 's' : '' ?></span>
        </article>
        <article class="eff-rh-tile">
            <span class="eff-rh-tile__kicker">À venir</span>
            <strong class="eff-rh-tile__value"><?= $upcoming ?></strong>
            <span class="eff-rh-tile__label">dans les 14 prochains jours</span>
        </article>
    </div>
</section>

<p class="bo-eff-jump">
    <strong>Aller plus loin</strong>
    <a href="<?= $h(url('back-office/roleplay-followup')) ?>">Ouvrir le suivi complet</a>
    <a href="<?= $h(effectifs_workspace_url('reglages')) ?>">Réglages du bureau</a>
</p>

<div class="eff-catalog">
    <div class="eff-catalog__head">
        <div class="min-w-0">
            <p class="eff-catalog__kicker">Échéances</p>
            <h2 class="eff-catalog__title">À traiter</h2>
            <p class="eff-catalog__lead">Les retards d’abord, puis les échéances des deux prochaines semaines.</p>
        </div>
    </div>
    <?php if (!$enabled): ?>
        <div class="eff-catalog__empty">
            <strong>Le suivi roleplay n’est pas activé.</strong>
            Activez-le dans Réglages, puis planifiez les entretiens depuis le suivi complet.
        </div>
    <?php elseif ($rows === []): ?>
        <div class="eff-catalog__empty"><strong>Aucune échéance dans les 14 prochains jours.</strong></div>
    <?php else: ?>
        <div class="eff-sheets" role="region" aria-label="Échéances roleplay" tabindex="0">
            <table class="eff-sheets__table">
                <thead>
                    <tr>
                        <th>Membre</th>
                        <th>Échéance</th>
                        <th>Date</th>
                        <th>État</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $uid = (int) ($row['user_id'] ?? 0);
                    $type = (string) ($row['event_type'] ?? '');
                    $urgent = ($row['urgency'] ?? '') === 'overdue';
                    ?>
                    <tr>
                        <td><strong class="eff-sheets__name"><?= $h((string) ($row['member_label'] ?? 'Membre')) ?></strong></td>
                        <td><?= $h((string) ($row['item_label'] ?? ($typeLabels[$type] ?? 'Échéance'))) ?></td>
                        <td><?= $h($rhWhen((string) ($row['due_date'] ?? ''))) ?></td>
                        <td><span class="eff-rh-chip <?= $urgent ? 'eff-rh-chip--warn' : 'eff-rh-chip--info' ?>"><?= $urgent ? 'En retard' : 'À venir' ?></span></td>
                        <td>
                            <?php if ($uid > 0): ?>
                                <a href="<?= $h(effectifs_workspace_url('membres/' . $uid)) ?>">Fiche</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
