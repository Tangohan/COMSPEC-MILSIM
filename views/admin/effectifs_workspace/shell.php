<?php
declare(strict_types=1);

$active = (string) ($effectifsNav ?? 'roster');
$counts = is_array($rosterCounts ?? null) ? $rosterCounts : [];
$total = (int) ($counts['total'] ?? 0);
$activeCount = (int) ($counts['active'] ?? 0);
$attentionCount = (int) ($counts['no_unit'] ?? 0) + (int) ($counts['no_role'] ?? 0) + (int) ($rhInboxActionCount ?? $rhAlertTotalCount ?? 0) + (int) ($phaseAutoErrorCount ?? 0);
$innerContent = (string) ($effectifsContent ?? 'admin.effectifs_workspace.roster');

$tabs = [
    ['rh_inbox', 'À traiter', 'a-traiter', (int) ($rhInboxActionCount ?? 0)],
    ['roster', 'Tableur', '', 0],
    ['roles', 'Accès', 'roles', 0],
    ['fonctions', 'Emplois', 'fonctions', 0],
    ['affectations', 'Affectations', 'affectations', (int) ($counts['no_unit'] ?? 0)],
    ['chaine', 'Chaîne', 'chaine', (int) ($commandChainMissingCount ?? 0)],
    ['qualifications', 'Qualifications', 'qualifications', (int) ($qualificationsExpiringCount ?? 0)],
    ['elevations', 'Élévations', 'elevations', (int) ($elevationOpenCount ?? 0)],
    ['rh_documents', 'Documents', 'documents-rh', 0],
    ['rh_mobility', 'Mobilité', 'mobilite', (int) ($mobilityPendingCount ?? 0)],
    ['rh_succession', 'Vivier', 'vivier', 0],
    ['rh_alerts', 'Alertes', 'alertes', (int) ($rhAlertTotalCount ?? 0)],
    ['rh_roleplay', 'Roleplay', 'roleplay', (int) ($roleplayDueCount ?? 0) + (int) ($phaseGateCount ?? 0)],
    ['rh_integration', 'Intégration', 'integration', (int) ($integrationOpenCount ?? 0)],
    ['duplicates', 'Fiches jumelles', 'doublons', (int) ($personnelDuplicateScan['group_count'] ?? 0)],
    ['departures', 'Anciens membres', 'departs', 0],
    ['rh_settings', 'Réglages', 'reglages', 0],
];
// Profils simplifiés : retirer les onglets sans usage (même liste que le menu latéral).
$effTenantType = function_exists('navigation_current_tenant_type') ? navigation_current_tenant_type() : \App\Services\Community\TenantTypeConfig::TYPE_FULL;
$effRosterOnly = \App\Services\Community\TenantTypeConfig::rosterWithoutAccounts($effTenantType);
$tabs = array_values(array_filter($tabs, static function (array $tab) use ($effTenantType): bool {
    return $tab[2] === '' || !\App\Services\Community\TenantTypeConfig::backOfficePathHidden($effTenantType, effectifs_workspace_path() . '/' . $tab[2]);
}));
// Onglets rangés sous « Plus » : utiles mais moins fréquents. L’onglet actif reste toujours visible.
$moreTabs = ['roles', 'fonctions', 'rh_documents', 'rh_mobility', 'rh_succession', 'rh_roleplay', 'duplicates', 'departures', 'rh_settings'];
$moreBadge = 0;
$moreActive = false;
foreach ($tabs as [$id, , , $badge]) {
    if (in_array($id, $moreTabs, true)) {
        $moreBadge += (int) $badge;
        $moreActive = $moreActive || $id === $active;
    }
}
$isMember = $innerContent === 'admin.effectifs_workspace.member';
$lead = match ($active) {
    'roster' => 'Qui est là, où il est affecté, quel accès il a et ce qu’il reste à compléter.',
    'rh_inbox' => 'Corrections, élévations, échéances et dossiers incomplets : le système propose, vous décidez.',
    default => 'Dossiers des membres : tableur, accès, emplois et suivi.',
};
?>
<div class="bo-eff-workspace bo-eff-workspace--v2" data-effectifs-workspace>
    <header class="bo-eff-head<?= $isMember ? ' bo-eff-head--compact' : '' ?>">
        <div class="bo-eff-head__copy">
            <h1 id="bo-eff-title">Effectifs</h1>
            <?php if (!$isMember): ?><p><?= htmlspecialchars($lead, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
        </div>
        <p class="bo-eff-head__meta" aria-label="État des effectifs">
            <span><strong><?= $activeCount ?></strong> actifs</span>
            <span><strong><?= $total ?></strong> dossiers</span>
            <a href="<?= htmlspecialchars(effectifs_workspace_url('a-traiter'), ENT_QUOTES, 'UTF-8') ?>" class="<?= $attentionCount > 0 ? 'is-alert' : '' ?>">
                <strong><?= $attentionCount ?></strong> à traiter
            </a>
        </p>
        <div class="bo-eff-head__actions">
            <a class="ath-btn ath-btn--solid" href="<?= htmlspecialchars(effectifs_workspace_url('nouveau'), ENT_QUOTES, 'UTF-8') ?>"><?= $effRosterOnly ? 'Ajouter une fiche' : 'Ajouter un membre' ?></a>
            <?php if ($effRosterOnly): ?>
            <button type="button" class="ath-btn" data-eff-import-open>Importer un tableur</button>
            <?php endif; ?>
            <a class="ath-btn" href="<?= htmlspecialchars(effectifs_workspace_url('export'), ENT_QUOTES, 'UTF-8') ?>">Exporter</a>
        </div>
    </header>

    <?php if ($effRosterOnly): ?>
    <aside class="bo-eff-bureau" aria-label="Bureau des effectifs">
        <p>Bureau des effectifs : vous tenez le registre comme un tableur. Vos membres n’ont pas besoin de compte, seuls ceux qui gèrent les fiches se connectent. Le recrutement se fait sur votre Discord, pas sur le site.</p>
    </aside>
    <dialog class="bo-eff-import" id="bo-eff-import" aria-labelledby="bo-eff-import-title">
        <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('importer'), ENT_QUOTES, 'UTF-8') ?>" enctype="multipart/form-data">
            <?= \App\Core\Csrf::field() ?>
            <h2 id="bo-eff-import-title">Importer un tableur</h2>
            <p>Une ligne par membre, la première ligne donne les colonnes. Chaque ligne devient une fiche sans compte.</p>
            <ul>
                <li>Colonnes reconnues : Prénom, Nom (ou Nom affiché), Indicatif, Grade, Affectation, Steam ID.</li>
                <li>Le fichier « Exporter » se réimporte tel quel : retouchez-le dans Excel, ajoutez des lignes, enregistrez en CSV.</li>
                <li>Un membre déjà présent (même nom ou même indicatif) n’est pas créé deux fois.</li>
            </ul>
            <label class="bo-eff-import__file">
                <span>Fichier CSV</span>
                <input type="file" name="roster_file" accept=".csv,text/csv" required>
            </label>
            <div class="bo-eff-import__actions">
                <a class="ath-btn" href="<?= htmlspecialchars(effectifs_workspace_url('modele-tableur'), ENT_QUOTES, 'UTF-8') ?>">Télécharger le modèle</a>
                <span class="bo-eff-import__spacer"></span>
                <button type="button" class="ath-btn" data-eff-import-close>Annuler</button>
                <button type="submit" class="ath-btn ath-btn--solid">Importer</button>
            </div>
        </form>
    </dialog>
    <style>
    .bo-eff-bureau{margin:0 0 14px;padding:10px 14px;border:1px solid color-mix(in srgb, var(--ath-accent, #0d9f6e) 35%, transparent);border-radius:10px;background:color-mix(in srgb, var(--ath-accent, #0d9f6e) 7%, transparent);font-size:13px;line-height:1.5}
    .bo-eff-bureau p{margin:0}
    .bo-eff-import{max-width:560px;width:calc(100% - 32px);border:1px solid var(--ath-line, #d8ded9);border-radius:14px;padding:22px;background:var(--ath-surface, #fff);color:inherit}
    .bo-eff-import::backdrop{background:rgba(8,12,10,.55)}
    .bo-eff-import h2{margin:0 0 8px;font-size:18px}
    .bo-eff-import p,.bo-eff-import li{font-size:13px;line-height:1.5}
    .bo-eff-import ul{margin:8px 0 14px;padding-left:18px}
    .bo-eff-import__file{display:grid;gap:6px;font-size:13px;font-weight:600;margin-bottom:16px}
    .bo-eff-import__actions{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
    .bo-eff-import__spacer{flex:1}
    </style>
    <script>
    (function () {
        var dlg = document.getElementById('bo-eff-import');
        if (!dlg || typeof dlg.showModal !== 'function') { return; }
        document.querySelectorAll('[data-eff-import-open]').forEach(function (b) { b.addEventListener('click', function () { dlg.showModal(); }); });
        dlg.querySelectorAll('[data-eff-import-close]').forEach(function (b) { b.addEventListener('click', function () { dlg.close(); }); });
    })();
    </script>
    <?php endif; ?>

    <nav class="bo-eff-tabs" aria-label="Sections des effectifs">
        <?php foreach ($tabs as [$id, $label, $suffix, $badge]): ?>
            <?php if (in_array($id, $moreTabs, true) && $id !== $active) {
                continue;
            } ?>
            <a href="<?= htmlspecialchars(effectifs_workspace_url($suffix), ENT_QUOTES, 'UTF-8') ?>" class="<?= $id === $active ? 'is-active' : '' ?>" <?= $id === $active ? 'aria-current="page"' : '' ?>>
                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                <?php if ($badge > 0): ?><span class="bo-eff-badge"><?= (int) $badge ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
        <details class="bo-eff-tabs__more">
            <summary>Plus<?php if ($moreBadge > 0 && !$moreActive): ?> <span class="bo-eff-badge"><?= $moreBadge ?></span><?php endif; ?></summary>
            <div class="bo-eff-tabs__menu">
                <?php foreach ($tabs as [$id, $label, $suffix, $badge]): ?>
                    <?php if (!in_array($id, $moreTabs, true)) {
                        continue;
                    } ?>
                    <a href="<?= htmlspecialchars(effectifs_workspace_url($suffix), ENT_QUOTES, 'UTF-8') ?>" class="<?= $id === $active ? 'is-active' : '' ?>">
                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        <?php if ($badge > 0): ?><span class="bo-eff-badge"><?= (int) $badge ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </details>
    </nav>

    <section class="bo-eff-content<?= $innerContent === 'admin.effectifs_workspace.member' ? ' bo-eff-content--member' : '' ?>">
        <?php
        $innerPath = base_path('views/' . str_replace('.', '/', $innerContent) . '.php');
        if (is_file($innerPath)) {
            require $innerPath;
        } else {
            echo '<div class="eff-panel"><p>Vue non trouvée.</p></div>';
        }
        ?>
    </section>
</div>
