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
    <div class="eff-pg__actions">
        <a class="eff-catalog__btn" href="<?= $h(url('back-office/integration-membres/modeles')) ?>">Modèles de parcours</a>
        <a class="eff-catalog__btn eff-catalog__btn--primary" href="<?= $h(url('back-office/integration-membres')) ?>">Ouvrir le suivi complet</a>
    </div>
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
        <article class="eff-rh-tile <?= $open > 0 ? 'eff-rh-tile--info' : 'eff-rh-tile--ok' ?>">
            <span class="eff-rh-tile__kicker">En cours</span>
            <strong class="eff-rh-tile__value"><?= $open ?></strong>
            <span class="eff-rh-tile__label">parcours ouvert<?= $open > 1 ? 's' : '' ?></span>
        </article>
        <article class="eff-rh-tile <?= $overdue > 0 ? 'eff-rh-tile--warn' : 'eff-rh-tile--ok' ?>">
            <span class="eff-rh-tile__kicker">Retards</span>
            <strong class="eff-rh-tile__value"><?= $overdue ?></strong>
            <span class="eff-rh-tile__label">parcours en retard</span>
        </article>
        <article class="eff-rh-tile eff-rh-tile--ok">
            <span class="eff-rh-tile__kicker">Terminés</span>
            <strong class="eff-rh-tile__value"><?= $done ?></strong>
            <span class="eff-rh-tile__label">accueil achevé</span>
        </article>
    </div>
</section>

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
                        <th>Arrivée</th>
                        <th>Référent</th>
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
                    $overdueCount = (int) ($row['overdue_count'] ?? 0);
                    $progress = max(0, min(100, (int) ($row['progress_percent'] ?? 0)));
                    $startedRaw = trim((string) ($row['created_at'] ?? $row['user_created_at'] ?? ''));
                    $startedTs = $startedRaw !== '' ? strtotime($startedRaw) : false;
                    $sinceDays = $startedTs ? max(0, (int) floor((time() - $startedTs) / 86400)) : null;
                    $referent = trim((string) ($row['referent_display_name'] ?? '')) ?: trim((string) ($row['referent_callsign'] ?? ''));
                    $sub = array_values(array_filter([
                        trim((string) ($row['callsign'] ?? '')),
                        trim((string) ($row['unit_name'] ?? '')),
                        trim((string) ($row['template_name'] ?? '')),
                    ], static fn (string $v): bool => $v !== ''));
                    $terminal = \App\Support\MemberIntegrationCatalog::isTerminalStatus($st);
                    ?>
                    <tr>
                        <td>
                            <strong class="eff-sheets__name"><?= $h($name) ?></strong>
                            <?php if ($sub !== []): ?><small><?= $h(implode(' · ', $sub)) ?></small><?php endif; ?>
                        </td>
                        <td>
                            <?= $startedTs ? $h(date('d/m/Y', $startedTs)) : '—' ?>
                            <?php if ($sinceDays !== null && !$terminal): ?><small>il y a <?= $sinceDays ?> j</small><?php endif; ?>
                        </td>
                        <td><?= $referent !== '' ? $h($referent) : '<span class="eff-rh-chip eff-rh-chip--warn">À désigner</span>' ?></td>
                        <td>
                            <span class="eff-rh-chip <?= $late ? 'eff-rh-chip--warn' : ($st === \App\Support\MemberIntegrationCatalog::STATUS_COMPLETED ? 'eff-rh-chip--ok' : '') ?>">
                                <?= $h((string) ($statusLabels[$st] ?? $st)) ?>
                            </span>
                            <?php if ($overdueCount > 0): ?><small class="eff-rh-late"><?= $overdueCount ?> étape<?= $overdueCount > 1 ? 's' : '' ?> en retard</small><?php endif; ?>
                            <?php if (isset($row['dossier_complete']) && !$terminal && (int) $row['dossier_complete'] === 0): ?><small>Dossier incomplet</small><?php endif; ?>
                        </td>
                        <td><?= $h(trim((string) ($row['current_step_title'] ?? '')) ?: '—') ?></td>
                        <td>
                            <span class="eff-progress" title="<?= $progress ?> %"><span class="eff-progress__bar"><i style="width: <?= $progress ?>%"></i></span><span class="eff-progress__val"><?= $progress ?> %</span></span>
                        </td>
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

<?php $rhShortcutCurrent = ''; require base_path('views/admin/effectifs_workspace/partials/rh_shortcuts.php'); ?>
