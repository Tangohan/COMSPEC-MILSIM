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
    <div style="margin-bottom:.85rem;display:flex;align-items:center;gap:.6rem;flex-wrap:wrap">
        <input
            type="search"
            placeholder="Rechercher une fonction…"
            aria-label="Rechercher une fonction"
            data-eff-search-table="eff-fonctions-table"
            data-eff-search-count="eff-fonctions-count"
            style="flex:1;min-width:14rem;border:1px solid rgba(242,244,243,.14);background:#0a0d0c;color:#e8eeec;padding:.55rem .8rem;border-radius:.5rem;font-size:13px"
        >
        <span style="font-size:11px;color:rgba(242,244,243,.5)"><span id="eff-fonctions-count"><?= count($jobRoles) ?></span> / <?= count($jobRoles) ?></span>
    </div>
    <div class="eff-table-wrap">
        <table class="eff-table" id="eff-fonctions-table" data-eff-sortable style="min-width:640px">
            <thead>
                <tr>
                    <th data-eff-sort>Fonction</th>
                    <th data-eff-sort>Catégorie</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($jobRoles as $jr): ?>
                <?php
                $jrName = (string) ($jr['name'] ?? '');
                $jrCategory = (string) ($jr['category_name'] ?? '—');
                ?>
                <tr data-eff-search="<?= htmlspecialchars($jrName . ' ' . $jrCategory, ENT_QUOTES, 'UTF-8') ?>">
                    <td><?= htmlspecialchars($jrName, ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($jrCategory, ENT_QUOTES, 'UTF-8') ?></td>
                    <td style="white-space:normal;max-width:28rem">
                        <?= htmlspecialchars(trim((string) ($jr['description'] ?? '')) ?: '—', ENT_QUOTES, 'UTF-8') ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/eff-table-filter.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
