<?php
declare(strict_types=1);
$jobRoles = is_array($jobRoles ?? null) ? $jobRoles : [];
$canManageAssignments = (bool) ($canManageAssignments ?? false);
?>
<section class="eff-page-head">
    <p class="eff-page-kicker">Emplois métier</p>
    <h1 class="eff-page-title">Fonctions</h1>
    <p class="eff-page-lead">
        Les fonctions (emplois métier) figurent sur les dossiers personnel — radio, médic, logistique, etc. —
        distinctes des rôles d’administration.
    </p>
</section>

<?php if ($canManageAssignments): ?>
<p style="margin-bottom:1rem">
    <a class="eff-btn eff-btn--primary" href="<?= htmlspecialchars(url('back-office/personnel-job-roles'), ENT_QUOTES, 'UTF-8') ?>">Référentiel des emplois</a>
    <a class="eff-btn eff-btn--ghost" href="<?= htmlspecialchars(url('back-office/personnel-job-roles/assignments'), ENT_QUOTES, 'UTF-8') ?>">Attributions</a>
</p>
<?php endif; ?>

<?php if ($jobRoles === []): ?>
    <div class="eff-empty">
        <p>Aucun emploi métier n’est encore défini pour cette communauté.</p>
        <?php if ($canManageAssignments): ?>
            <p style="margin-top:1rem"><a class="eff-btn eff-btn--primary" href="<?= htmlspecialchars(url('back-office/personnel-job-roles'), ENT_QUOTES, 'UTF-8') ?>">Créer le référentiel</a></p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="eff-table-wrap">
        <table class="eff-table" style="min-width:640px">
            <thead>
                <tr>
                    <th>Fonction</th>
                    <th>Catégorie</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($jobRoles as $jr): ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($jr['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($jr['category_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td style="white-space:normal;max-width:28rem">
                        <?= htmlspecialchars(trim((string) ($jr['description'] ?? '')) ?: '—', ENT_QUOTES, 'UTF-8') ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
