<?php
declare(strict_types=1);

use App\Services\Rbac\RolePermissionMatrixCatalog;

$rows = is_array($matrixRows ?? null) ? $matrixRows : [];
$stats = is_array($matrixStats ?? null) ? $matrixStats : [];
$filters = is_array($matrixFilters ?? null) ? $matrixFilters : [];
$moduleLabels = is_array($moduleLabels ?? null) ? $moduleLabels : RolePermissionMatrixCatalog::moduleLabelsFr();
$accessLevelLabels = is_array($accessLevelLabels ?? null) ? $accessLevelLabels : RolePermissionMatrixCatalog::accessLevelLabelsFr();
$csrfToken = (string) ($csrfToken ?? \App\Core\Csrf::token());
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$successFlash = \App\Core\Session::getFlash('success');
$errorFlash = \App\Core\Session::getFlash('error');

$reviewLabel = (string) ($stats['access_review_label'] ?? '—');
$reviewUpToDate = !empty($stats['access_review_up_to_date']);
$athKpis = [
    ['label' => 'RÔLES DÉFINIS', 'value' => (string) (int) ($stats['roles_defined'] ?? 0), 'delta' => '', 'tone' => '#1e4f80', 'pct' => '60%', 'note' => ((int) ($stats['technician_roles'] ?? 0)) . ' techniques'],
    ['label' => 'TITULAIRES ADMIN', 'value' => (string) (int) ($stats['admin_holders'] ?? 0), 'delta' => '', 'tone' => '#c98a12', 'pct' => '12%', 'note' => 'accès total'],
    ['label' => 'PERMISSIONS', 'value' => (string) (int) ($stats['permission_cells'] ?? 0), 'delta' => '', 'tone' => '#0b8a5c', 'pct' => '80%', 'note' => 'granularité par module'],
    ['label' => 'REVUE D’ACCÈS', 'value' => $reviewLabel, 'delta' => '', 'tone' => $reviewUpToDate ? '#0b8a5c' : '#c98a12', 'pct' => $reviewUpToDate ? '100%' : '50%', 'note' => $reviewUpToDate ? 'à jour' : 'revue conseillée'],
];
require base_path('views/partials/ath_kpis.php');
?>

<form method="get" action="<?= $h(url('back-office/roles-permissions')) ?>" class="ath-users-filters ath-rise">
    <div>
        <label class="ath-users-filters__label" for="rp-q">Recherche</label>
        <input id="rp-q" type="search" name="q" value="<?= $h((string) ($filters['q'] ?? '')) ?>" class="bo-select" style="height:40px;" placeholder="Code, rôle…">
    </div>
    <div>
        <label class="ath-users-filters__label" for="rp-scope">Périmètre</label>
        <select id="rp-scope" name="scope" class="bo-select">
            <option value="">Tous</option>
            <option value="admin" <?= ($filters['scope'] ?? '') === 'admin' ? 'selected' : '' ?>>Accès complet</option>
            <option value="section" <?= ($filters['scope'] ?? '') === 'section' ? 'selected' : '' ?>>Sa section</option>
            <option value="read" <?= ($filters['scope'] ?? '') === 'read' ? 'selected' : '' ?>>Lecture</option>
        </select>
    </div>
    <div>
        <label class="ath-users-filters__label" for="rp-level">Niveau</label>
        <select id="rp-level" name="level" class="bo-select">
            <option value="">Tous</option>
            <?php for ($lvl = 0; $lvl <= 5; $lvl++): ?>
            <option value="<?= $lvl ?>" <?= (string) ($filters['level'] ?? '') === (string) $lvl ? 'selected' : '' ?>><?= $lvl ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <div>
        <label class="ath-users-filters__label" for="rp-active">État</label>
        <select id="rp-active" name="active" class="bo-select">
            <option value="">Tous</option>
            <option value="1" <?= ($filters['active'] ?? '') === '1' ? 'selected' : '' ?>>Actif</option>
            <option value="0" <?= ($filters['active'] ?? '') === '0' ? 'selected' : '' ?>>Inactif</option>
        </select>
    </div>
    <button type="submit" class="ath-btn ath-btn--solid">Appliquer</button>
    <a href="<?= $h(url('back-office/roles-permissions/export') . '?' . http_build_query(array_filter($filters))) ?>" class="ath-btn">Exporter CSV</a>
</form>

<form method="post" action="<?= $h(url('back-office/roles-permissions/revue')) ?>" class="ath-rise" style="display:flex;justify-content:flex-end;">
    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
    <button type="submit" class="ath-btn">Marquer la revue trimestrielle</button>
</form>

<?php
$athTableRows = [];
$athTableRowHrefs = [];
foreach ($rows as $row) {
    $cells = [
        (string) ($row['code'] ?? ''),
        (string) ($row['name'] ?? ''),
        (string) (int) ($row['level'] ?? 0),
        (string) (int) ($row['holders_count'] ?? 0),
    ];
    foreach (RolePermissionMatrixCatalog::moduleKeys() as $moduleKey) {
        $cells[] = (string) ($row['modules'][$moduleKey]['access_label'] ?? '—');
    }
    $cells[] = (string) ($row['can_delete_label'] ?? 'Non');
    $cells[] = (string) ($row['can_export_label'] ?? 'Non');
    $cells[] = (string) ($row['last_reviewed_label'] ?? '—');
    $cells[] = (string) ($row['status_label'] ?? 'Actif');
    $athTableRows[] = $cells;
    $athTableRowHrefs[] = null;
}

$cols = ['CODE|m', 'RÔLE', 'NIVEAU|r', 'TITULAIRES|r'];
foreach ($moduleLabels as $label) {
    $cols[] = mb_strtoupper((string) $label, 'UTF-8');
}
$cols = array_merge($cols, ['SUPPRESSION', 'EXPORT', 'DERNIÈRE REVUE|m', 'ÉTAT|b']);

$athTableTitle = 'Matrice des rôles';
$athTableCount = (int) ($stats['filtered_count'] ?? count($rows));
$athTableCols = $cols;
$athTableFilters = ['Périmètre', 'Niveau', 'Actif'];
$athTableMinWidth = '1620px';
$athTableFoot = 'Affichage ' . count($rows) . ' sur ' . (int) ($stats['total_count'] ?? count($rows)) . ' · ' . date('d/m/Y H:i');
$athTableExportUrl = url('back-office/roles-permissions/export') . '?' . http_build_query(array_filter($filters));
require base_path('views/partials/ath_table.php');
?>

<?php if ($rows !== []): ?>
<div class="ath-rise ath-roles-edit-stack">
    <div class="ath-card" style="padding:16px 18px;">
        <div style="font-size:9px;font-weight:800;letter-spacing:0.18em;color:#8c979b;margin-bottom:12px;">MODIFIER UN RÔLE</div>
        <div style="display:flex;flex-direction:column;gap:12px;">
            <?php foreach ($rows as $row): ?>
            <details class="ath-roles-edit-item">
                <summary class="ath-btn" style="justify-content:space-between;width:100%;">
                    <span><?= $h((string) ($row['name'] ?? '')) ?></span>
                    <span style="font-family:var(--ath-mono);font-size:10px;color:#8c979b;"><?= $h((string) ($row['code'] ?? '')) ?></span>
                </summary>
                <form method="post" action="<?= $h(url('back-office/roles-permissions/save')) ?>" class="ath-roles-edit-form">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                    <input type="hidden" name="role_id" value="<?= (int) ($row['id'] ?? 0) ?>">
                    <label>Code</label>
                    <input name="code" value="<?= $h((string) ($row['code'] ?? '')) ?>" class="bo-select" style="height:40px;">
                    <label>Niveau (0–5)</label>
                    <input name="level" type="number" min="0" max="5" value="<?= (int) ($row['level'] ?? 0) ?>" class="bo-select" style="height:40px;">
                    <?php foreach (RolePermissionMatrixCatalog::moduleKeys() as $moduleKey): ?>
                    <label><?= $h((string) ($moduleLabels[$moduleKey] ?? $moduleKey)) ?></label>
                    <select name="module_<?= $h($moduleKey) ?>" class="bo-select">
                        <?php foreach ($accessLevelLabels as $levelKey => $levelLabel): ?>
                        <option value="<?= $h($levelKey) ?>" <?= (($row['modules'][$moduleKey]['access_level'] ?? '') === $levelKey) ? 'selected' : '' ?>><?= $h((string) $levelLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php endforeach; ?>
                    <label class="ath-users-filters__check"><input type="checkbox" name="can_delete" value="1" <?= !empty($row['can_delete']) ? 'checked' : '' ?>> Suppression autorisée</label>
                    <label class="ath-users-filters__check"><input type="checkbox" name="can_export" value="1" <?= !empty($row['can_export']) ? 'checked' : '' ?>> Export autorisé</label>
                    <label class="ath-users-filters__check"><input type="checkbox" name="is_active" value="1" <?= !empty($row['is_active']) ? 'checked' : '' ?>> Rôle actif</label>
                    <label class="ath-users-filters__check"><input type="checkbox" name="mark_reviewed" value="1"> Marquer revue aujourd’hui</label>
                    <button type="submit" class="ath-btn ath-btn--solid">Enregistrer</button>
                </form>
            </details>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>
