<?php
declare(strict_types=1);

require base_path('views/admin/effectifs_workspace/partials/rh_ui_helpers.php');

$rows = is_array($integrationRows ?? null) ? $integrationRows : [];
$statusLabels = is_array($integrationStatusLabels ?? null) ? $integrationStatusLabels : [];
$hasTemplate = !empty($integrationHasTemplate);
$open = 0;
$overdue = 0;
$done = 0;
foreach ($rows as $row) {
    $st = (string) ($row['status'] ?? '');
    if (\App\Support\MemberIntegrationCatalog::isTerminalStatus($st)) {
        if ($st === \App\Support\MemberIntegrationCatalog::STATUS_COMPLETED) {
            $done++;
        }
        continue;
    }
    $open++;
    if ((int) ($row['overdue_count'] ?? 0) > 0) {
        $overdue++;
    }
}
?>
<section class="eff-rh-hero">
    <p class="eff-page-kicker">Dossier RH</p>
    <h2 class="eff-page-title">Intégration des nouveaux membres</h2>
    <p class="eff-page-lead">
        Parcours d’arrivée : étapes, référent, retards. Les avancements d’étapes se font tout seuls dès que le dossier ou le rendez-vous est en règle.
    </p>
    <div class="eff-rh-tiles" aria-label="Aperçu de l’intégration">
        <article class="eff-rh-tile <?= $hasTemplate ? 'eff-rh-tile--ok' : 'eff-rh-tile--warn' ?>">
            <span class="eff-rh-tile__kicker">Modèle</span>
            <strong class="eff-rh-tile__value"><?= $hasTemplate ? 'Prêt' : 'À préparer' ?></strong>
            <span class="eff-rh-tile__label"><?= $hasTemplate ? 'Un parcours d’accueil est en place' : 'Créez un modèle d’accueil' ?></span>
        </article>
        <article class="eff-rh-tile <?= $open > 0 ? 'eff-rh-tile--info' : '' ?>">
            <span class="eff-rh-tile__kicker">En cours</span>
            <strong class="eff-rh-tile__value"><?= $open ?></strong>
            <span class="eff-rh-tile__label">parcours ouvert<?= $open > 1 ? 's' : '' ?></span>
        </article>
        <article class="eff-rh-tile <?= $overdue > 0 ? 'eff-rh-tile--warn' : 'eff-rh-tile--ok' ?>">
            <span class="eff-rh-tile__kicker">Retards</span>
            <strong class="eff-rh-tile__value"><?= $overdue ?></strong>
            <span class="eff-rh-tile__label">parcours en retard</span>
        </article>
        <article class="eff-rh-tile">
            <span class="eff-rh-tile__kicker">Terminés</span>
            <strong class="eff-rh-tile__value"><?= $done ?></strong>
            <span class="eff-rh-tile__label">accueil achevé</span>
        </article>
    </div>
</section>

<p class="bo-eff-jump">
    <strong>Aller plus loin</strong>
    <a href="<?= $h(url('back-office/integration-membres')) ?>">Ouvrir le suivi complet</a>
    <a href="<?= $h(url('back-office/integration-membres/modeles')) ?>">Modèles de parcours</a>
    <a href="<?= $h(effectifs_workspace_url('reglages')) ?>">Réglages du bureau</a>
</p>

<div class="eff-catalog">
    <div class="eff-catalog__head">
        <div class="min-w-0">
            <p class="eff-catalog__kicker">Parcours</p>
            <h2 class="eff-catalog__title">Arrivées suivies</h2>
            <p class="eff-catalog__lead">Les parcours ouverts d’abord. Ouvrez la fiche pour le dossier, ou le suivi complet pour valider une étape.</p>
        </div>
    </div>
    <?php if ($rows === []): ?>
        <div class="eff-catalog__empty">
            <strong>Aucun parcours pour l’instant.</strong>
            Un nouveau membre ouvre un suivi dès que le modèle d’accueil est en place.
        </div>
    <?php else: ?>
        <div class="eff-sheets" role="region" aria-label="Parcours d’intégration" tabindex="0">
            <table class="eff-sheets__table">
                <thead>
                    <tr>
                        <th>Membre</th>
                        <th>État</th>
                        <th>Étape en cours</th>
                        <th>Avancement</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $uid = (int) ($row['user_id'] ?? 0);
                    $st = (string) ($row['status'] ?? '');
                    $id = (int) ($row['id'] ?? 0);
                    $late = (int) ($row['overdue_count'] ?? 0) > 0;
                    $name = trim((string) ($row['display_name'] ?? $row['user_display_name'] ?? ''))
                        ?: (string) ($row['email'] ?? 'Membre');
                    ?>
                    <tr>
                        <td><strong class="eff-sheets__name"><?= $h($name) ?></strong></td>
                        <td>
                            <span class="eff-rh-chip <?= $late ? 'eff-rh-chip--warn' : '' ?>">
                                <?= $h((string) ($statusLabels[$st] ?? $st)) ?>
                            </span>
                        </td>
                        <td><?= $h(trim((string) ($row['current_step_title'] ?? '')) ?: '—') ?></td>
                        <td><?= (int) ($row['progress_percent'] ?? 0) ?> %</td>
                        <td>
                            <div class="eff-sheets__actions">
                                <?php if ($id > 0): ?>
                                    <a class="is-primary" href="<?= $h(url('back-office/integration-membres/' . $id)) ?>">Parcours</a>
                                <?php endif; ?>
                                <?php if ($uid > 0): ?>
                                    <a href="<?= $h(effectifs_workspace_url('membres/' . $uid)) ?>">Fiche</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
