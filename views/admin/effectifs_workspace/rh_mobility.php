<?php
declare(strict_types=1);

require base_path('views/admin/effectifs_workspace/partials/rh_ui_helpers.php');

$rows = is_array($mobilityRequests ?? null) ? $mobilityRequests : [];
$typeLabels = is_array($mobilityTypeLabels ?? null) ? $mobilityTypeLabels : [];
$users = is_array($orgUsers ?? null) ? $orgUsers : [];
$units = is_array($orgUnits ?? null) ? $orgUnits : [];
$jobRoles = is_array($orgJobRoles ?? null) ? $orgJobRoles : [];
$schemaReady = !empty($mobilitySchemaReady);
$canManage = !empty($canManage);
$statusFilter = (string) ($mobilityStatusFilter ?? 'pending');
$pendingCount = (int) ($mobilityPendingCount ?? 0);
$csrf = htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8');

$statusLabels = [
    'pending' => 'En attente',
    'approved' => 'Approuvée',
    'rejected' => 'Refusée',
    'cancelled' => 'Annulée',
    'applied' => 'Appliquée',
    'all' => 'Toutes',
];
$statusTone = [
    'pending' => 'warn',
    'approved' => 'ok',
    'rejected' => 'danger',
    'cancelled' => '',
    'applied' => 'info',
];
?>
<section class="eff-rh-hero">
    <p class="eff-page-kicker">Dossier RH</p>
    <h1 class="eff-page-title">Mobilité interne</h1>
    <p class="eff-page-lead">
        Changements d’unité, candidatures à un poste et souhaits d’évolution.
        Les demandes en attente restent visibles jusqu’à décision.
    </p>
    <div class="eff-rh-tiles" aria-label="Aperçu de la mobilité">
        <article class="eff-rh-tile <?= $pendingCount > 0 ? 'eff-rh-tile--warn' : 'eff-rh-tile--ok' ?>">
            <span class="eff-rh-tile__kicker">
                File
                <?php $rhTip('tip-mob-pending', 'À propos des demandes en attente', 'Ces demandes n’ont pas encore été approuvées ni refusées. Traitez-les pour débloquer le mouvement.'); ?>
            </span>
            <a class="eff-rh-tile__hit" href="<?= $h(effectifs_workspace_url('mobilite') . '?statut=pending') ?>">
                <strong class="eff-rh-tile__value"><?= $pendingCount ?></strong>
                <span class="eff-rh-tile__label">demande<?= $pendingCount > 1 ? 's' : '' ?> en attente</span>
            </a>
        </article>
        <article class="eff-rh-tile">
            <span class="eff-rh-tile__kicker">Vue</span>
            <strong class="eff-rh-tile__value"><?= count($rows) ?></strong>
            <span class="eff-rh-tile__label"><?= $h($statusLabels[$statusFilter] ?? 'Filtre') ?></span>
        </article>
        <a class="eff-rh-tile" href="<?= $h(effectifs_workspace_url('vivier')) ?>">
            <span class="eff-rh-tile__kicker">Suite</span>
            <strong class="eff-rh-tile__value">Vivier</strong>
            <span class="eff-rh-tile__label">Préparer les successions</span>
        </a>
    </div>
</section>

<?php if (!$schemaReady): ?>
    <div class="eff-catalog">
        <div class="eff-catalog__empty">
            <strong>Mobilité indisponible</strong>
            <?= $h($rhUnavailable) ?>
        </div>
    </div>
<?php else: ?>
    <div class="eff-rh-pills" role="tablist" aria-label="Filtrer les demandes">
        <?php foreach (['pending', 'approved', 'rejected', 'all'] as $st): ?>
            <a href="<?= $h(effectifs_workspace_url('mobilite') . '?statut=' . rawurlencode($st)) ?>"
               class="eff-rh-pill <?= $statusFilter === $st ? 'is-on' : '' ?> <?= $st === 'pending' ? 'eff-rh-pill--warn' : '' ?>">
                <?= $h($statusLabels[$st] ?? $st) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($canManage): ?>
    <section class="eff-rh-form" aria-labelledby="eff-mob-add-title">
        <div class="eff-rh-form__head">
            <h2 id="eff-mob-add-title" class="eff-rh-form__title">Enregistrer une demande</h2>
            <p class="eff-rh-form__lead">Indiquez le membre, le type de mouvement, puis la cible (unité, fonction, ou les deux).</p>
        </div>
        <form method="post" action="<?= $h(effectifs_workspace_url('mobilite')) ?>" class="eff-rh-form__grid">
            <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
            <div class="eff-rh-field">
                <span class="eff-rh-field__label">Membre</span>
                <select name="user_id" required aria-label="Membre">
                    <option value="">Choisir un membre…</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int) ($u['id'] ?? 0) ?>"><?= $h(trim((string) ($u['display_name'] ?? '')) ?: (string) ($u['email'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="eff-rh-field">
                <span class="eff-rh-field__label">
                    Type de mouvement
                    <?php $rhTip('tip-mob-type', 'À propos du type', 'Changement d’unité, de spécialité, candidature à un poste, ou simple souhait d’évolution à noter au dossier.'); ?>
                </span>
                <select name="request_type" aria-label="Type de mouvement">
                    <?php foreach ($typeLabels as $k => $lab): ?>
                        <option value="<?= $h((string) $k) ?>"><?= $h((string) $lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="eff-rh-field">
                <span class="eff-rh-field__label">
                    Unité d’accueil
                    <?php $rhTip('tip-mob-unit', 'À propos de l’unité', 'Unité visée par le mouvement. Laissez « Aucune » si seule la fonction change.'); ?>
                </span>
                <select name="target_unit_id" aria-label="Unité d’accueil">
                    <option value="0">Aucune</option>
                    <?php foreach ($units as $u): ?>
                        <option value="<?= (int) ($u['id'] ?? 0) ?>"><?= $h((string) ($u['name'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="eff-rh-field">
                <span class="eff-rh-field__label">
                    Fonction visée
                    <?php $rhTip('tip-mob-job', 'À propos de la fonction', 'Fonction visée dans l’organisation. Laissez « Aucune » si seul le rattachement d’unité change.'); ?>
                </span>
                <select name="target_job_role_id" aria-label="Fonction visée">
                    <option value="0">Aucune</option>
                    <?php foreach ($jobRoles as $jr): ?>
                        <option value="<?= (int) ($jr['id'] ?? 0) ?>"><?= $h((string) ($jr['label'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="eff-rh-field">
                <span class="eff-rh-field__label">Intitulé libre</span>
                <input type="text" name="target_label" maxlength="200" placeholder="Poste, spécialité, mention…" aria-label="Intitulé libre">
            </div>
            <div class="eff-rh-field eff-rh-field--wide">
                <span class="eff-rh-field__label">Motivation</span>
                <input type="text" name="motivation" maxlength="500" placeholder="Pourquoi ce mouvement ?" aria-label="Motivation">
            </div>
            <div class="eff-rh-form__actions">
                <button type="submit" class="eff-rh-btn eff-rh-btn--primary">Enregistrer la demande</button>
            </div>
        </form>
    </section>
    <?php endif; ?>

    <div class="eff-catalog">
        <div class="eff-catalog__head">
            <div class="min-w-0">
                <p class="eff-catalog__kicker">Registre</p>
                <h2 class="eff-catalog__title">Demandes</h2>
                <p class="eff-catalog__lead">Approuvez ou refusez les dossiers en attente, puis ouvrez la fiche du membre.</p>
            </div>
        </div>
        <?php if ($rows === []): ?>
            <div class="eff-catalog__empty"><strong>Aucune demande<?= $statusFilter === 'pending' ? ' en attente' : '' ?>.</strong></div>
        <?php else: ?>
            <div class="eff-sheets" role="region" aria-label="Mobilité interne" tabindex="0">
                <table class="eff-sheets__table">
                    <thead>
                        <tr>
                            <th>Membre</th>
                            <th>Type</th>
                            <th>Cible</th>
                            <th>Statut</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <?php
                        $rid = (int) ($r['id'] ?? 0);
                        $uid = (int) ($r['user_id'] ?? 0);
                        $name = trim((string) ($r['user_display_name'] ?? '')) ?: (string) ($r['user_email'] ?? 'Membre');
                        $type = (string) ($r['request_type'] ?? '');
                        $st = (string) ($r['status'] ?? '');
                        $target = trim((string) ($r['target_label'] ?? ''));
                        if ($target === '') {
                            $target = trim((string) ($r['target_unit_name'] ?? ''));
                        }
                        $mot = trim((string) ($r['motivation'] ?? ''));
                        $tone = $statusTone[$st] ?? '';
                        ?>
                        <tr>
                            <td><strong class="eff-sheets__name"><?= $h($name) ?></strong></td>
                            <td><?= $h((string) ($typeLabels[$type] ?? $type)) ?></td>
                            <td><?= $h($target !== '' ? $target : '—') ?><?php if ($mot !== ''): ?><br><span class="eff-sheets__meta"><?= $h($mot) ?></span><?php endif; ?></td>
                            <td><span class="eff-rh-chip<?= $tone !== '' ? ' eff-rh-chip--' . $h($tone) : '' ?>"><?= $h($statusLabels[$st] ?? $st) ?></span></td>
                            <td><?= $h($rhWhen((string) ($r['created_at'] ?? ''))) ?></td>
                            <td>
                                <div class="eff-rh-row-actions">
                                    <?php if ($uid > 0): ?>
                                        <a class="is-primary" href="<?= $h(effectifs_workspace_url('membres/' . $uid)) ?>">Fiche</a>
                                    <?php endif; ?>
                                    <?php if ($canManage && $st === 'pending' && $rid > 0): ?>
                                        <form method="post" action="<?= $h(effectifs_workspace_url('mobilite/' . $rid . '/statut')) ?>">
                                            <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                                            <input type="hidden" name="status" value="approved">
                                            <button type="submit" class="eff-rh-btn eff-rh-btn--ok">Approuver</button>
                                        </form>
                                        <form method="post" action="<?= $h(effectifs_workspace_url('mobilite/' . $rid . '/statut')) ?>">
                                            <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                                            <input type="hidden" name="status" value="rejected">
                                            <button type="submit" class="eff-rh-btn eff-rh-btn--danger">Refuser</button>
                                        </form>
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
<?php endif; ?>

<?php $rhShortcutCurrent = 'mobilite'; require base_path('views/admin/effectifs_workspace/partials/rh_shortcuts.php'); ?>
