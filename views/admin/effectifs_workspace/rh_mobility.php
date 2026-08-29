<?php
declare(strict_types=1);

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
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$statusLabels = [
    'pending' => 'En attente',
    'approved' => 'Approuvée',
    'rejected' => 'Refusée',
    'cancelled' => 'Annulée',
    'applied' => 'Appliquée',
    'all' => 'Toutes',
];
?>
<div class="eff-catalog">
    <div class="eff-catalog__head">
        <div class="min-w-0">
            <p class="eff-catalog__kicker">Dossier individuel</p>
            <h1 class="eff-catalog__title">Mobilité interne</h1>
            <p class="eff-catalog__lead">
                Demandes de changement d’unité ou de spécialité, candidatures à un poste, souhaits d’évolution.
            </p>
        </div>
        <div class="eff-catalog__tools">
            <span class="eff-catalog__btn"><?= $pendingCount ?> en attente</span>
        </div>
    </div>

    <?php if (!$schemaReady): ?>
        <div class="eff-catalog__empty"><strong>Schéma non migrée.</strong> Relancez les migrations pour activer la mobilité.</div>
    <?php else: ?>
        <div class="eff-catalog__tools" style="margin-bottom:1rem">
            <?php foreach (['pending', 'approved', 'rejected', 'all'] as $st): ?>
                <a href="<?= $h(effectifs_workspace_url('mobilite') . '?statut=' . rawurlencode($st)) ?>"
                   class="eff-catalog__btn <?= $statusFilter === $st ? 'eff-catalog__btn--primary' : '' ?>">
                    <?= $h($statusLabels[$st] ?? $st) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($canManage): ?>
        <form method="post" action="<?= $h(effectifs_workspace_url('mobilite')) ?>" class="eff-catalog__tools" style="flex-wrap:wrap;gap:.75rem;margin-bottom:1.25rem;align-items:end">
            <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
            <label>
                <span class="eff-section-label">Membre</span>
                <select name="user_id" required>
                    <option value="">Choisir…</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int) ($u['id'] ?? 0) ?>"><?= $h(trim((string) ($u['display_name'] ?? '')) ?: (string) ($u['email'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span class="eff-section-label">Type</span>
                <select name="request_type">
                    <?php foreach ($typeLabels as $k => $lab): ?>
                        <option value="<?= $h((string) $k) ?>"><?= $h((string) $lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span class="eff-section-label">Unité cible</span>
                <select name="target_unit_id">
                    <option value="0">—</option>
                    <?php foreach ($units as $u): ?>
                        <option value="<?= (int) ($u['id'] ?? 0) ?>"><?= $h((string) ($u['name'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span class="eff-section-label">Fonction cible</span>
                <select name="target_job_role_id">
                    <option value="0">—</option>
                    <?php foreach ($jobRoles as $jr): ?>
                        <option value="<?= (int) ($jr['id'] ?? 0) ?>"><?= $h((string) ($jr['label'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span class="eff-section-label">Libellé libre</span>
                <input type="text" name="target_label" maxlength="200" placeholder="Poste / spécialité…">
            </label>
            <label style="flex:1;min-width:12rem">
                <span class="eff-section-label">Motivation</span>
                <input type="text" name="motivation" maxlength="500" placeholder="Pourquoi ce mouvement ?">
            </label>
            <button type="submit" class="eff-catalog__btn eff-catalog__btn--primary">Enregistrer</button>
        </form>
        <?php endif; ?>

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
                        ?>
                        <tr>
                            <td><strong class="eff-sheets__name"><?= $h($name) ?></strong></td>
                            <td><?= $h((string) ($typeLabels[$type] ?? $type)) ?></td>
                            <td><?= $h($target !== '' ? $target : '—') ?><?php if ($mot !== ''): ?><br><span class="eff-sheets__meta"><?= $h($mot) ?></span><?php endif; ?></td>
                            <td><?= $h($statusLabels[$st] ?? $st) ?></td>
                            <td><?= $h((string) ($r['created_at'] ?? '')) ?></td>
                            <td>
                                <?php if ($uid > 0): ?>
                                    <a class="is-primary" href="<?= $h(effectifs_workspace_url('membres/' . $uid)) ?>">Fiche</a>
                                <?php endif; ?>
                                <?php if ($canManage && $st === 'pending' && $rid > 0): ?>
                                    <form method="post" action="<?= $h(effectifs_workspace_url('mobilite/' . $rid . '/statut')) ?>" style="display:inline">
                                        <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="status" value="approved">
                                        <button type="submit" class="eff-catalog__btn">Approuver</button>
                                    </form>
                                    <form method="post" action="<?= $h(effectifs_workspace_url('mobilite/' . $rid . '/statut')) ?>" style="display:inline">
                                        <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="status" value="rejected">
                                        <button type="submit" class="eff-catalog__btn">Refuser</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
