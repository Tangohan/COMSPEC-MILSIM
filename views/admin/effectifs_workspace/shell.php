<?php
declare(strict_types=1);

require_once __DIR__ . '/partials/unit_code.php';

$active = (string) ($effectifsNav ?? 'roster');
$counts = is_array($rosterCounts ?? null) ? $rosterCounts : [];
$total = (int) ($counts['total'] ?? 0);
$activeCount = (int) ($counts['active'] ?? 0);
$inactiveCount = (int) ($counts['inactive'] ?? 0);
$attentionCount = (int) ($counts['no_unit'] ?? 0) + (int) ($counts['no_role'] ?? 0) + (int) ($rhInboxActionCount ?? 0);
$innerContent = (string) ($effectifsContent ?? 'admin.effectifs_workspace.roster');
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

// [identifiant, libellé, adresse, pastille, groupe du menu « Plus » (vide = toujours visible)]
$tabs = [
    ['rh_inbox', 'À traiter', 'a-traiter', (int) ($rhInboxActionCount ?? 0), ''],
    ['roster', 'Tableur', '', 0, ''],
    ['affectations', 'Affectations', 'affectations', (int) ($counts['no_unit'] ?? 0), ''],
    ['chaine', 'Chaîne', 'chaine', (int) ($commandChainMissingCount ?? 0), ''],
    ['qualifications', 'Qualifications', 'qualifications', (int) ($qualificationsExpiringCount ?? 0), ''],
    ['elevations', 'Élévations', 'elevations', (int) ($elevationOpenCount ?? 0), ''],
    ['roles', 'Accès', 'roles', 0, 'Organisation'],
    ['registre_droits', 'Registre des droits', 'registre-droits', (int) ($regCounts['expiring'] ?? 0), 'Organisation'],
    ['fonctions', 'Emplois', 'fonctions', 0, 'Organisation'],
    ['invitations', 'Invitations', 'invitations', 0, 'Suivi des dossiers'],
    ['sans_comptes', 'Sans compte', 'sans-comptes', 0, 'Suivi des dossiers'],
    ['tableaux', 'Tableaux de données', 'tableaux', 0, 'Registre'],
    ['rh_mobility', 'Mobilité', 'mobilite', (int) ($mobilityPendingCount ?? 0), 'Suivi des dossiers'],
    ['rh_succession', 'Vivier', 'vivier', 0, 'Suivi des dossiers'],
    ['rh_roleplay', 'Roleplay', 'roleplay', (int) ($roleplayDueCount ?? 0) + (int) ($phaseGateCount ?? 0), 'Suivi des dossiers'],
    ['rh_documents', 'Documents', 'documents-rh', 0, 'Suivi des dossiers'],
    ['duplicates', 'Fiches jumelles', 'doublons', (int) ($personnelDuplicateScan['group_count'] ?? 0), 'Registre'],
    ['departures', 'Anciens membres', 'departs', 0, 'Registre'],
    ['rh_settings', 'Réglages', 'reglages', 0, 'Registre'],
];
// Profils simplifiés : retirer les onglets sans usage (même liste que le menu latéral).
$effTenantType = function_exists('navigation_current_tenant_type') ? navigation_current_tenant_type() : \App\Services\Community\TenantTypeConfig::TYPE_FULL;
$effRosterOnly = \App\Services\Community\TenantTypeConfig::rosterWithoutAccounts($effTenantType);
$tabs = array_values(array_filter($tabs, static function (array $tab) use ($effTenantType): bool {
    return $tab[2] === '' || !\App\Services\Community\TenantTypeConfig::backOfficePathHidden($effTenantType, effectifs_workspace_path() . '/' . $tab[2]);
}));
// L’onglet actif reste toujours visible dans la barre, même s’il est rangé sous « Plus ».
$moreGroups = [];
$moreBadge = 0;
foreach ($tabs as $tab) {
    if ($tab[4] === '') {
        continue;
    }
    $moreGroups[$tab[4]][] = $tab;
    if ($tab[0] !== $active) {
        $moreBadge += (int) $tab[3];
    }
}
$isMember = $innerContent === 'admin.effectifs_workspace.member';
$lead = match ($active) {
    'roster' => 'Cliquez sur un nom pour ouvrir sa fiche, sur un crayon pour corriger sur place.',
    'rh_inbox' => 'Toutes les alertes RH rangées par urgence : le système propose, vous décidez.',
    'fonctions' => 'Organisez les emplois, les catégories et les responsabilités de votre communauté.',
    'affectations' => 'Qui tient quoi, dans quelle unité et avec quel rôle, avec le code de chaque unité.',
    'nouveau' => 'Créez la fiche, puis envoyez à la personne son lien pour compléter son profil.',
    'invitations' => 'Les liens envoyés pour compléter un profil : qui les a reçus, ouverts, et ce qu’il reste à faire.',
    'sans_comptes' => 'Les personnes qui n’ont pas (encore) de compte : leur fiche, leur invitation, leur rattachement à un compte.',
    'tableaux' => 'Choisissez une source, les colonnes, les filtres : votre tableau se construit comme dans Excel.',
    'registre_droits' => 'Qui détient quel droit, sur quel périmètre, accordé par qui, depuis quand et jusqu’à quand.',
    default => 'Dossiers des membres : tableur, accès, emplois et suivi.',
};
$showTools = empty($jobsReferenceWorkspace);
$hero = is_array($effHero ?? null) ? $effHero : null;
$heroCommunity = trim((string) ($communityName ?? ''));
$statusFilterNow = is_array($rosterFilters ?? null) ? (string) ($rosterFilters['status'] ?? '') : '';
// Raccourcis propres à la page ouverte.
$pageActions = match ($active) {
    'affectations', 'chaine' => [['Ouvrir l’ORBAT', url('back-office/organisation/structure'), '']],
    'rh_inbox' => [['Membres sans unité', effectifs_workspace_url() . '?sans_affectation=1', '']],
    default => $isMember ? [['Retour au tableur', effectifs_workspace_url(), '']] : [],
};
$searchPages = [];
foreach ($tabs as $tab) {
    $searchPages[] = ['label' => $tab[1], 'url' => effectifs_workspace_url($tab[2]), 'group' => $tab[4] !== '' ? $tab[4] : 'Effectifs'];
}
$memberUnitCode = is_array($effMemberUnitCode ?? null) ? $effMemberUnitCode : null;
$compColors = ['#f4f4f0', '#34d399', '#60a5fa', '#fbbf24', '#a78bfa', '#38bdf8'];
?>
<div class="bo-eff-workspace bo-eff-workspace--v2" data-effectifs-workspace>
    <?php if ($isMember): ?>
    <header class="bo-eff-head bo-eff-head--compact">
        <div class="bo-eff-head__copy">
            <h1 id="bo-eff-title">Effectifs</h1>
            <?php if ($memberUnitCode !== null): ?>
            <p class="bo-eff-head__unit">Unité <?= eff_unit_code_html((string) $memberUnitCode['code'], (string) $memberUnitCode['admin_id']) ?></p>
            <?php endif; ?>
        </div>
    </header>
    <?php else: ?>
    <header class="bo-eff-hero<?= $hero === null ? ' bo-eff-hero--compact' : '' ?>">
        <div class="bo-eff-hero__in">
            <div class="bo-eff-hero__copy">
                <p class="bo-eff-hero__kicker"><i aria-hidden="true"></i>Personnel<?= $heroCommunity !== '' ? ' · ' . $h($heroCommunity) : '' ?></p>
                <h1 id="bo-eff-title">Effectifs</h1>
                <p class="bo-eff-hero__lead">
                    <?php if ($hero !== null && $showTools): ?>
                        <b><?= $total ?> membre<?= $total > 1 ? 's' : '' ?></b> répartis en <b><?= (int) ($hero['units_with_members'] ?? 0) ?> unité<?= (int) ($hero['units_with_members'] ?? 0) > 1 ? 's' : '' ?></b>.
                    <?php endif; ?>
                    <?= $h($lead) ?>
                </p>
            </div>
            <?php if ($showTools): ?>
            <div class="bo-eff-hero__acts">
                <?php if ($effRosterOnly): ?>
                <button type="button" class="bo-eff-hb bo-eff-hb--ghost" data-eff-import-open>Importer un tableur</button>
                <?php endif; ?>
                <a class="bo-eff-hb bo-eff-hb--ghost" href="<?= $h(effectifs_workspace_url('export')) ?>" title="Télécharger le tableur des effectifs">
                    <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><path d="M8 2v8m-3.5-3.5L8 10l3.5-3.5M3 13h10" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>Exporter
                </a>
                <a class="bo-eff-hb" href="<?= $h(effectifs_workspace_url('nouveau')) ?>">
                    <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><path d="M8 3v10M3 8h10" fill="none" stroke="currentColor" stroke-width="1.8"/></svg><?= $effRosterOnly ? 'Ajouter une fiche' : 'Ajouter un membre' ?>
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($hero !== null && $showTools): ?>
            <?php $comp = is_array($hero['composition'] ?? null) ? $hero['composition'] : []; ?>
            <?php if ($comp !== []): ?>
            <div class="bo-eff-hero__comp">
                <p class="bo-eff-hero__label">Composition</p>
                <div class="bo-eff-hero__bar" aria-hidden="true">
                    <?php foreach ($comp as $ci => $part): ?>
                        <?php $color = $part['unit_id'] === -1 ? '#d97706' : ($part['unit_id'] === 0 ? '#94a3b8' : $compColors[$ci % count($compColors)]); ?>
                        <i style="flex:<?= (int) $part['count'] ?>;background:<?= $h($color) ?>"></i>
                    <?php endforeach; ?>
                </div>
                <ul class="bo-eff-hero__legend">
                    <?php foreach ($comp as $ci => $part): ?>
                        <?php
                        $color = $part['unit_id'] === -1 ? '#d97706' : ($part['unit_id'] === 0 ? '#94a3b8' : $compColors[$ci % count($compColors)]);
                        $href = $part['unit_id'] > 0 ? effectifs_workspace_url() . '?unite=' . (int) $part['unit_id'] : ($part['unit_id'] === -1 ? effectifs_workspace_url() . '?sans_affectation=1' : '');
                        ?>
                        <li>
                            <?php if ($href !== ''): ?><a href="<?= $h($href) ?>"><?php endif; ?>
                            <em style="background:<?= $h($color) ?>"></em><?= $h((string) $part['label']) ?> <b><?= (int) $part['count'] ?></b>
                            <?php if ($href !== ''): ?></a><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            <?php $kpi = is_array($effKpi ?? null) ? $effKpi : []; ?>
            <nav class="bo-eff-hero__sum" aria-label="Synthèse des effectifs">
                <a class="bo-eff-sv<?= $statusFilterNow === '' && empty($rosterFilters['sans_affectation']) && empty($rosterFilters['sans_role']) ? ' is-on' : '' ?>" href="<?= $h(effectifs_workspace_url()) ?>">
                    <span class="bo-eff-sv__t">Effectifs</span>
                    <span class="bo-eff-sv__v"><?= $total ?></span>
                    <span class="bo-eff-sv__h"><?= $activeCount ?> actif<?= $activeCount > 1 ? 's' : '' ?> · <?= $inactiveCount ?> inactif<?= $inactiveCount > 1 ? 's' : '' ?></span>
                </a>
                <a class="bo-eff-sv" href="<?= $h(effectifs_workspace_url('sans-comptes')) ?>">
                    <span class="bo-eff-sv__t">Sans compte<i style="background:#0284c7"></i></span>
                    <span class="bo-eff-sv__v"><?= (int) ($kpi['no_account'] ?? 0) ?></span>
                    <span class="bo-eff-sv__h">fiches à inviter ou rattacher</span>
                </a>
                <a class="bo-eff-sv" href="<?= $h(effectifs_workspace_url('a-traiter')) ?>">
                    <span class="bo-eff-sv__t">À traiter<i style="background:#d97706"></i></span>
                    <span class="bo-eff-sv__v"><?= $attentionCount ?></span>
                    <span class="bo-eff-sv__h">dossiers, affectations, échéances</span>
                </a>
                <a class="bo-eff-sv" href="<?= $h(url('back-office/integration-membres')) ?>">
                    <span class="bo-eff-sv__t">En intégration<i style="background:#0284c7"></i></span>
                    <span class="bo-eff-sv__v"><?= (int) ($integrationOpenCount ?? 0) ?></span>
                    <span class="bo-eff-sv__h">parcours d’intégration ouverts</span>
                </a>
                <a class="bo-eff-sv" href="<?= $h(effectifs_workspace_url('departs')) ?>">
                    <span class="bo-eff-sv__t">Arrivées / départs<i style="background:#34d399"></i></span>
                    <span class="bo-eff-sv__v"><?= (int) ($kpi['arrivals'] ?? 0) ?> / <?= (int) ($kpi['departures'] ?? 0) ?></span>
                    <span class="bo-eff-sv__h">sur les 30 derniers jours</span>
                </a>
                <a class="bo-eff-sv" href="<?= $h(effectifs_workspace_url('qualifications')) ?>">
                    <span class="bo-eff-sv__t">Qualifications<i style="background:#a78bfa"></i></span>
                    <span class="bo-eff-sv__v"><?= (int) ($qualificationsExpiringCount ?? 0) ?></span>
                    <span class="bo-eff-sv__h">expirées ou bientôt échues</span>
                </a>
            </nav>
        <?php endif; ?>
    </header>
    <?php endif; ?>

    <?php if ($effRosterOnly): ?>
    <aside class="bo-eff-bureau" aria-label="Bureau des effectifs">
        <p>Bureau des effectifs : vous tenez le registre comme un tableur. Vos membres n’ont pas besoin de compte, seuls ceux qui gèrent les fiches se connectent. Le recrutement se fait sur votre Discord, pas sur le site.</p>
    </aside>
    <?php require __DIR__ . '/_roster_import.php'; ?>
    <?php endif; ?>

    <div class="bo-eff-bar" data-eff-bar>
        <nav class="bo-eff-tabs" aria-label="Sections des effectifs">
            <div class="bo-eff-tabs__scroll" data-eff-tabs-scroll>
                <?php foreach ($tabs as [$id, $label, $suffix, $badge, $group]): ?>
                    <?php if ($group !== '' && $id !== $active) {
                        continue;
                    } ?>
                    <a href="<?= $h(effectifs_workspace_url($suffix)) ?>" class="bo-eff-tab<?= $id === $active ? ' is-active' : '' ?>"<?= $id === $active ? ' aria-current="page"' : '' ?>>
                        <span><?= $h($label) ?></span>
                        <?php if ($badge > 0): ?><span class="bo-eff-badge" title="<?= (int) $badge ?> à voir"><?= $badge > 99 ? '99+' : (int) $badge ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php if ($moreGroups !== []): ?>
            <details class="bo-eff-tabs__more" data-eff-menu>
                <summary aria-haspopup="true">
                    <span>Plus</span>
                    <?php if ($moreBadge > 0): ?><span class="bo-eff-badge" title="<?= $moreBadge ?> à voir"><?= $moreBadge > 99 ? '99+' : $moreBadge ?></span><?php endif; ?>
                    <svg class="bo-eff-tabs__chev" viewBox="0 0 12 12" width="10" height="10" aria-hidden="true"><path d="M2 4.5 6 8l4-3.5" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>
                </summary>
                <div class="bo-eff-tabs__menu" data-eff-menu-panel>
                    <?php foreach ($moreGroups as $groupLabel => $items): ?>
                    <div class="bo-eff-tabs__group" role="group" aria-label="<?= $h($groupLabel) ?>">
                        <p class="bo-eff-tabs__group-label" aria-hidden="true"><?= $h($groupLabel) ?></p>
                        <?php foreach ($items as [$id, $label, $suffix, $badge]): ?>
                        <a href="<?= $h(effectifs_workspace_url($suffix)) ?>" class="<?= $id === $active ? 'is-active' : '' ?>"<?= $id === $active ? ' aria-current="page"' : '' ?>>
                            <span><?= $h($label) ?></span>
                            <?php if ($badge > 0): ?><span class="bo-eff-badge"><?= $badge > 99 ? '99+' : (int) $badge ?></span><?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </details>
            <?php endif; ?>
        </nav>

        <?php if ($showTools): ?>
        <div class="bo-eff-quick" role="group" aria-label="Raccourcis des effectifs">
            <?php foreach ($pageActions as [$label, $href]): ?>
                <a class="bo-eff-quick__page" href="<?= $h($href) ?>"><?= $h($label) ?></a>
            <?php endforeach; ?>
            <button type="button" class="bo-eff-quick__search" data-eff-search-open aria-keyshortcuts="Control+K" title="Rechercher un membre, une unité, un emploi (Ctrl+K)">
                <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><circle cx="7" cy="7" r="4.6" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="m10.4 10.4 3.6 3.6" stroke="currentColor" stroke-width="1.6"/></svg>
                <span class="bo-eff-quick__search-text">Rechercher</span>
                <kbd>Ctrl K</kbd>
            </button>
            <?php if ($isMember): ?>
            <a class="bo-eff-quick__page bo-eff-quick__page--solid" href="<?= $h(effectifs_workspace_url('nouveau')) ?>"><?= $effRosterOnly ? 'Ajouter une fiche' : 'Ajouter un membre' ?></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php
    // Sous-pages refondues le 2026-10-26 : mise en page commune (effectifs-pages.css).
    $effRedesignedPages = ['rh_integration', 'elevations', 'qualifications', 'chaine', 'affectations', 'fonctions', 'rh_mobility', 'rh_succession', 'rh_roleplay', 'duplicates', 'departures', 'rh_settings'];
    $effPageClass = !$isMember && in_array($active, $effRedesignedPages, true) ? ' bo-eff-page' : '';
    ?>
    <section class="bo-eff-content<?= $isMember ? ' bo-eff-content--member' : '' ?><?= $effPageClass ?>">
        <?php
        $innerPath = base_path('views/' . str_replace('.', '/', $innerContent) . '.php');
        if (is_file($innerPath)) {
            require $innerPath;
        } else {
            echo '<div class="eff-panel"><p>Vue non trouvée.</p></div>';
        }
        ?>
    </section>

    <?php if ($showTools) {
        require __DIR__ . '/partials/search_palette.php';
    } ?>
</div>
<script src="<?= $h(asset_url('assets/js/eff-workspace-shell.js')) ?>" defer></script>
