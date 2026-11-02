<?php
declare(strict_types=1);

/**
 * Onglets des pages du back-office regroupées (App\Support\BackOfficeTabs) : la page d’accueil et
 * les anciennes pages devenues onglets. Seuls les onglets autorisés (profil de communauté, droits) apparaissent.
 */
$boTabsPath = function_exists('back_office_path_suffix') ? back_office_path_suffix() : '';
$boTabsCurrent = isset($_GET['onglet']) && is_scalar($_GET['onglet']) ? (string) $_GET['onglet'] : '';
$boTabs = \App\Support\BackOfficeTabs::barFor(
    $boTabsPath,
    $boTabsCurrent,
    static fn (string $legacyPath): bool => !function_exists('portal_nav_href_permission_allowed')
        || portal_nav_href_permission_allowed($legacyPath)
);
if ($boTabs === null) {
    return;
}
$boTabsH = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$boTabsLabel = static fn (string $v): string => function_exists('i18n_phrase') ? i18n_phrase('nav', $v) : $v;
?>
<nav class="ath-periods ath-rise bo-page-tabs" aria-label="<?= $boTabsH($boTabsLabel('Sections de la page')) ?>">
    <?php foreach ($boTabs as $boTab): ?>
    <a href="<?= $boTabsH($boTab['href']) ?>" class="ath-btn"<?= $boTab['active'] ? ' aria-current="true"' : '' ?>><?= $boTabsH($boTabsLabel($boTab['label'])) ?></a>
    <?php endforeach; ?>
</nav>
