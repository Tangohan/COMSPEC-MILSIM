<?php
declare(strict_types=1);

/** @var list<array<string,mixed>> $rows */
/** @var array<string,list<array<string,mixed>>> $byStep */
/** @var array<string,mixed> $filters */
/** @var string $viewMode */
/** @var array<string,string> $statusLabels */
/** @var list<array<string,mixed>> $units */
/** @var list<array<string,mixed>> $roles */
/** @var list<array<string,mixed>> $matrices */
/** @var list<array<string,mixed>> $staff */
/** @var bool $canManage */
/** @var bool $canAssign */
/** @var bool $canTemplates */

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$rows = is_array($rows ?? null) ? $rows : [];
$byStep = is_array($byStep ?? null) ? $byStep : [];
$filters = is_array($filters ?? null) ? $filters : [];
$viewMode = ($viewMode ?? 'tableau') === 'colonnes' ? 'colonnes' : 'tableau';
$statusLabels = is_array($statusLabels ?? null) ? $statusLabels : [];
?>
<link href="<?= $h(asset_url('assets/css/member-integration.css')) ?>" rel="stylesheet">

<p class="mi-muted" style="margin-bottom:1rem">Suivez l’arrivée des nouveaux membres : étapes, dossier personnel, rendez-vous et référent. Le parcours du portail (découverte de l’interface) reste distinct.</p>

<form class="mi-toolbar" method="get" action="<?= $h(url('back-office/integration-membres')) ?>">
    <label>Recherche
        <input type="search" name="q" value="<?= $h($filters['q'] ?? '') ?>" placeholder="Nom, indicatif…">
    </label>
    <label>Statut
        <select name="status">
            <option value="">Tous</option>
            <?php foreach ($statusLabels as $k => $lab): ?>
                <option value="<?= $h($k) ?>" <?= (($filters['status'] ?? '') === $k) ? 'selected' : '' ?>><?= $h($lab) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Unité
        <select name="unit_id">
            <option value="0">Toutes</option>
            <?php foreach ($units as $u): ?>
                <option value="<?= (int) ($u['id'] ?? 0) ?>" <?= ((int) ($filters['unit_id'] ?? 0) === (int) ($u['id'] ?? 0)) ? 'selected' : '' ?>><?= $h($u['name'] ?? $u['label'] ?? '') ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Rôle
        <select name="role_id">
            <option value="0">Tous</option>
            <?php foreach ($roles as $r): ?>
                <option value="<?= (int) ($r['id'] ?? 0) ?>" <?= ((int) ($filters['role_id'] ?? 0) === (int) ($r['id'] ?? 0)) ? 'selected' : '' ?>><?= $h($r['name'] ?? $r['display_name'] ?? '') ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Groupe de suivi
        <select name="matrix_id">
            <option value="0">Tous</option>
            <?php foreach ($matrices as $m): ?>
                <option value="<?= (int) ($m['id'] ?? 0) ?>" <?= ((int) ($filters['matrix_id'] ?? 0) === (int) ($m['id'] ?? 0)) ? 'selected' : '' ?>><?= $h($m['name'] ?? '') ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Référent
        <select name="referent_user_id">
            <option value="0">Tous</option>
            <?php foreach ($staff as $s): ?>
                <option value="<?= (int) ($s['id'] ?? 0) ?>" <?= ((int) ($filters['referent_user_id'] ?? 0) === (int) ($s['id'] ?? 0)) ? 'selected' : '' ?>><?= $h($s['display_name'] ?? $s['email'] ?? '') ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Arrivé depuis
        <input type="date" name="arrived_from" value="<?= $h($filters['arrived_from'] ?? '') ?>">
    </label>
    <label>Jusqu’au
        <input type="date" name="arrived_to" value="<?= $h($filters['arrived_to'] ?? '') ?>">
    </label>
    <label><input type="checkbox" name="dossier_incomplete" value="1" <?= !empty($filters['dossier_incomplete']) ? 'checked' : '' ?>> Dossier incomplet</label>
    <label><input type="checkbox" name="overdue" value="1" <?= !empty($filters['overdue']) ? 'checked' : '' ?>> En retard</label>
    <input type="hidden" name="vue" value="<?= $h($viewMode) ?>">
    <button type="submit">Filtrer</button>
</form>

<p class="mi-actions">
    <a class="mi-btn mi-btn--ghost" href="<?= $h(url('back-office/integration-membres') . '?vue=tableau') ?>">Tableau</a>
    <a class="mi-btn mi-btn--ghost" href="<?= $h(url('back-office/integration-membres') . '?vue=colonnes') ?>">Colonnes par étape</a>
    <?php if (!empty($canTemplates)): ?>
        <a class="mi-btn mi-btn--ghost" href="<?= $h(url('back-office/integration-membres/modeles')) ?>">Modèles de parcours</a>
    <?php endif; ?>
    <?php if (!empty($canManage)): ?>
        <a class="mi-btn mi-btn--ghost" href="<?= $h(url('back-office/integration-membres/reprise')) ?>">Reprise des arrivées</a>
    <?php endif; ?>
</p>

<?php if ($viewMode === 'colonnes'): ?>
    <div class="mi-kanban">
        <?php foreach ($byStep as $label => $cards): ?>
            <section class="mi-kanban__col">
                <h3><?= $h($label) ?> · <?= count($cards) ?></h3>
                <?php foreach ($cards as $row): ?>
                    <a class="mi-card" href="<?= $h(url('back-office/integration-membres/' . (int) $row['id'])) ?>">
                        <strong><?= $h($row['display_name'] ?? $row['callsign'] ?? 'Membre') ?></strong>
                        <span class="mi-muted"><?= $h($statusLabels[(string) ($row['status'] ?? '')] ?? ($row['status'] ?? '')) ?></span>
                        <div class="mi-progress" style="margin-top:.4rem"><span style="width:<?= max(0, min(100, (int) ($row['progress_percent'] ?? 0))) ?>%"></span></div>
                    </a>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
        <?php if ($byStep === []): ?>
            <p class="mi-muted">Aucun parcours pour ces filtres.</p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <table class="mi-table">
        <thead>
            <tr>
                <th>Membre</th>
                <th>Statut</th>
                <th>Étape en cours</th>
                <th>Progression</th>
                <th>Dossier</th>
                <th>Retards</th>
                <th>Référent</th>
                <th>Prochain rendez-vous</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><a href="<?= $h(url('back-office/integration-membres/' . (int) $row['id'])) ?>"><?= $h($row['display_name'] ?? $row['callsign'] ?? 'Membre') ?></a></td>
                <td><?= $h($statusLabels[(string) ($row['status'] ?? '')] ?? ($row['status'] ?? '')) ?></td>
                <td><?= $h($row['current_step_title'] ?? '—') ?></td>
                <td><?= (int) ($row['progress_percent'] ?? 0) ?> %</td>
                <td><?= !empty($row['dossier_complete']) ? 'Complet' : 'À compléter' ?></td>
                <td><?= (int) ($row['overdue_count'] ?? 0) ?></td>
                <td><?= $h($row['referent_display_name'] ?? $row['referent_callsign'] ?? '—') ?></td>
                <td><?= $h($row['next_appointment_at'] ?? '—') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($rows === []): ?>
            <tr><td colspan="8" class="mi-muted">Aucun parcours d’intégration pour le moment.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php if (!empty($canAssign)): ?>
    <form class="mi-form mi-panel" style="margin-top:1.5rem" method="post" action="<?= $h(url('back-office/integration-membres/ouvrir')) ?>">
        <?= \App\Core\Csrf::field() ?>
        <h2>Ouvrir un parcours manuellement</h2>
        <label>Membre
            <select name="user_id" required>
                <option value="">Choisir…</option>
                <?php foreach ($staff as $s): ?>
                    <option value="<?= (int) ($s['id'] ?? 0) ?>"><?= $h($s['display_name'] ?? $s['email'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="mi-actions"><button class="mi-btn" type="submit">Ouvrir le parcours</button></div>
    </form>
<?php endif; ?>
