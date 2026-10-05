<?php
declare(strict_types=1);

/**
 * Navigation plateforme, sur la même charte visuelle que le back-office ATHENA.
 *
 * Chaque entrée déclare son niveau d’accès (aligné sur le middleware de la route) :
 * - system : administrateur plateforme (SystemAdminMiddleware)
 * - hub    : administrateur ou assistance plateforme (PlatformHubMiddleware)
 * - org    : accès au back-office d’une communauté
 * - forum  : console de modération des fichiers
 *
 * Expose aussi $platformAdminCrumbGroup (rubrique de la page courante) pour le fil d’Ariane.
 */
$p = function_exists('back_office_path_suffix') ? back_office_path_suffix() : '';
$gate = \App\Core\Gate::getInstance();
$isPlatformAdmin = $gate->allows('admin.system');
$isSupportHub = $gate->allows('site.support') && !$isPlatformAdmin;
$hasOrgPath = $gate->allows('admin.organization') || $gate->allows('admin.access') || $gate->allows('site.support');
$canForumModConsole = function_exists('forum_user_can_moderate') && forum_user_can_moderate();
$h = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$accessOk = [
    'system' => $isPlatformAdmin,
    'hub' => $isPlatformAdmin || $isSupportHub,
    'org' => $hasOrgPath,
    'forum' => $canForumModConsole,
];

$icons = [
    'dash' => 'M3 13h8V3H3zM13 21h8V11h-8zM13 3v6h8V3zM3 21h8v-6H3z',
    'chart' => 'M4 20V10M10 20V4M16 20v-7M22 20H2',
    'ops' => 'M12 2v3M12 19v3M2 12h3M19 12h3M12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10',
    'chat' => 'M4 5h16v11H9l-5 4zM8 9h8M8 12h5',
    'star' => 'M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z',
    'globe' => 'M12 3a9 9 0 1 0 .01 0M3 12h18M12 3c2.5 2.5 3.5 5.5 3.5 9s-1 6.5-3.5 9c-2.5-2.5-3.5-5.5-3.5-9s1-6.5 3.5-9',
    'lifebuoy' => 'M12 3a9 9 0 1 0 .01 0M12 8a4 4 0 1 0 .01 0M5.6 5.6l3.6 3.6M14.8 14.8l3.6 3.6M18.4 5.6l-3.6 3.6M9.2 14.8l-3.6 3.6',
    'layers' => 'M12 3l9 5-9 5-9-5zM3 13l9 5 9-5M3 17l9 5 9-5',
    'key' => 'M14 10a4 4 0 1 0-1.2 2.8L21 21M17 17l2-2M19 19l2-2',
    'users' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8M22 21v-2a4 4 0 0 0-3-3.9',
    'crown' => 'M3 8l4 4 5-7 5 7 4-4-2 11H5zM5 21h14',
    'edit' => 'M4 20h4L19 9l-4-4L4 16zM14 6l4 4',
    'shield' => 'M12 3l8 4v6c0 5-3.5 7.5-8 9-4.5-1.5-8-4-8-9V7z',
    'badge' => 'M4 6h16v13H4zM9 3h6v4H9zM8 12h8M8 15h5',
    'ban' => 'M12 3a9 9 0 1 0 .01 0M5.6 5.6l12.8 12.8',
    'gavel' => 'M14 4l6 6M11 7l6 6M8 10l6-6M3 21l8-8M13 17l4-4',
    'tool' => 'M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z',
    'megaphone' => 'M3 10v4h4l9 5V5L7 10zM19 9a4 4 0 0 1 0 6',
    'mail' => 'M3 6h18v12H3zM3 7l9 6 9-6',
    'book' => 'M4 4h13a2 2 0 0 1 2 2v14H6a2 2 0 0 1-2-2zM4 18h15',
    'coop' => 'M8 12h8M10 8l-4 4 4 4M14 8l4 4-4 4M5 5h14v14H5z',
    'gear' => 'M12 9a3 3 0 1 0 .01 0M20 12l2-1-2-3.5-2.3.6a6 6 0 0 0-1.6-.9L15.5 5h-4l-.6 2.2a6 6 0 0 0-1.6.9L7 7.5 5 11l2 1-2 1 2 3.5 2.3-.6c.5.4 1 .7 1.6.9l.6 2.2h4l.6-2.2c.6-.2 1.1-.5 1.6-.9l2.3.6L22 13z',
    'orbat' => 'M12 3v4M6 21v-4M18 21v-4M4 7h16M6 17h12M12 7v10',
    'clock' => 'M12 3a9 9 0 1 0 .01 0M12 7v5l3 2',
    'rocket' => 'M12 2c3 2 5 5.5 5 9.5L12 16l-5-4.5C7 7.5 9 4 12 2M9 17l-2 4 5-2 5 2-2-4',
    'flag' => 'M5 21V4M5 4h11l-2 4 2 4H5',
    'flask' => 'M9 3h6M10 3v6L4 19a1.5 1.5 0 0 0 1.3 2h13.4a1.5 1.5 0 0 0 1.3-2L14 9V3M7 15h10',
    'disk' => 'M4 6c0-1.7 3.6-3 8-3s8 1.3 8 3v12c0 1.7-3.6 3-8 3s-8-1.3-8-3zM4 6c0 1.7 3.6 3 8 3s8-1.3 8-3M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3',
    'wrench' => 'M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z',
    'audit' => 'M9 3h6M4 6h16v15H4zM8 11h8M8 15h5',
    'file-check' => 'M6 3h9l4 4v14H6zM9 14l2 2 4-4',
    'home' => 'M4 11l8-7 8 7v9H4zM10 20v-6h4v6',
];
$icon = static fn (string $key): string => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . ($icons[$key] ?? $icons['gear']) . '"></path></svg>';
$under = static fn (string $path): bool => $p === $path || str_starts_with($p, $path . '/');

/*
 * Carte de l’administration plateforme : rubrique → pages.
 * 'also' : autres préfixes d’URL rattachés à l’entrée ; 'not' : sous-pages qui ont leur propre entrée.
 */
$platformNav = [
    ['key' => 'pilotage', 'label' => 'Pilotage', 'items' => [
        ['path' => 'admin', 'label' => 'Tableau de bord', 'icon' => 'dash', 'access' => 'hub', 'exact' => true, 'desc' => 'Vue d’ensemble du site'],
        ['path' => 'admin/analytics', 'label' => 'Indicateurs transverses', 'icon' => 'chart', 'access' => 'hub', 'desc' => 'Usage, communautés actives, comptes'],
        ['path' => 'admin/ops-center', 'label' => 'Synthèse opérationnelle', 'icon' => 'ops', 'access' => 'hub', 'desc' => 'Modération, support, incidents'],
        ['path' => 'admin/system/retours-interface', 'label' => 'Retours interface', 'icon' => 'chat', 'access' => 'system', 'desc' => 'Remarques des membres sur l’interface'],
        ['path' => 'admin/system/avis-plateforme', 'label' => 'Avis et traductions', 'icon' => 'star', 'access' => 'system', 'desc' => 'Avis des communautés et corrections de traduction'],
    ]],
    ['key' => 'communautes', 'label' => 'Communautés', 'items' => [
        ['path' => 'admin/tenants', 'label' => 'Annuaire des communautés', 'icon' => 'globe', 'access' => 'system', 'also' => ['admin/system/tenants'], 'desc' => 'Fiches, formules, interventions'],
        ['path' => 'admin/system/tenant-recovery', 'label' => 'Récupération communauté', 'icon' => 'lifebuoy', 'access' => 'system', 'desc' => 'Rendre la main à une communauté bloquée'],
        ['path' => 'admin/system/subscription-plans', 'label' => 'Formules d’accès', 'icon' => 'layers', 'access' => 'system', 'desc' => 'Paliers, modules et plafonds'],
        ['path' => 'admin/system/demo-nda', 'label' => 'Accès démo du site', 'icon' => 'key', 'access' => 'system', 'desc' => 'Codes et engagements de confidentialité'],
    ]],
    ['key' => 'securite', 'label' => 'Comptes & accès', 'items' => [
        ['path' => 'admin/users', 'label' => 'Comptes utilisateurs', 'icon' => 'users', 'access' => 'system', 'desc' => 'Toutes les personnes, toutes communautés'],
        ['path' => 'admin/system/administrateurs-site', 'label' => 'Administrateurs du site', 'icon' => 'crown', 'access' => 'system', 'desc' => 'Liste fermée des administrateurs plateforme'],
        ['path' => 'admin/system/advanced-fiche-edit', 'label' => 'Édition avancée de fiche', 'icon' => 'edit', 'access' => 'system', 'desc' => 'Autorisations temporaires de modification'],
        ['path' => 'admin/roles', 'label' => 'Rôles système', 'icon' => 'shield', 'access' => 'system', 'desc' => 'Rôles et droits de la plateforme'],
        ['path' => 'admin/site-roles', 'label' => 'Affectations rôles site', 'icon' => 'badge', 'access' => 'system', 'desc' => 'Qui porte quel rôle de site'],
        ['path' => 'admin/system/recruitment-portal-tools', 'label' => 'Outils du portail candidatures', 'icon' => 'tool', 'access' => 'system', 'desc' => 'Diagnostic et réparation des candidatures'],
    ]],
    ['key' => 'moderation', 'label' => 'Modération & sécurité', 'items' => [
        ['path' => 'admin/system/blocklist', 'label' => 'Liste de restriction', 'icon' => 'ban', 'access' => 'system', 'desc' => 'E-mails et réseaux bloqués sur tout le site'],
        ['path' => 'admin/system/member-sanctions', 'label' => 'Sanctions du site', 'icon' => 'gavel', 'access' => 'system', 'desc' => 'Restrictions de compte, forum, messagerie'],
        ['path' => 'admin/content-moderation', 'label' => 'Modération des fichiers', 'icon' => 'file-check', 'access' => 'forum', 'desc' => 'Pièces jointes en quarantaine'],
    ]],
    ['key' => 'communication', 'label' => 'Communication', 'items' => [
        ['path' => 'admin/system/alerts', 'label' => 'Alertes plateforme', 'icon' => 'megaphone', 'access' => 'hub', 'desc' => 'Bandeaux et annonces sur le portail'],
        ['path' => 'admin/newsletter', 'label' => 'Lettre d’information', 'icon' => 'mail', 'access' => 'system', 'desc' => 'Inscrits depuis la page d’accueil'],
        ['path' => 'admin/system/brief', 'label' => 'Brief membres', 'icon' => 'book', 'access' => 'system', 'desc' => 'Message de bienvenue des membres'],
        ['path' => 'admin/system/cooperation/catalog', 'label' => 'Types de coopération', 'icon' => 'coop', 'access' => 'system', 'desc' => 'Catalogue des coopérations entre unités'],
        ['path' => 'admin/system/cooperation/announcements', 'label' => 'Annonces de coopération', 'icon' => 'megaphone', 'access' => 'system', 'desc' => 'Messages diffusés aux communautés'],
    ]],
    ['key' => 'configuration', 'label' => 'Configuration', 'items' => [
        ['path' => 'admin/settings', 'label' => 'Paramètres système', 'icon' => 'gear', 'access' => 'system', 'desc' => 'Configuration effective, en lecture seule'],
        ['path' => 'admin/system/military-referential', 'label' => 'Référentiel militaire', 'icon' => 'orbat', 'access' => 'system', 'desc' => 'Organisations, commandements, unités'],
        ['path' => 'admin/system/cron', 'label' => 'Tâches automatiques', 'icon' => 'clock', 'access' => 'system', 'desc' => 'Travaux récurrents et leur dernier passage'],
    ]],
    ['key' => 'publication', 'label' => 'Versions & publication', 'items' => [
        ['path' => 'admin/system/updates', 'label' => 'Mises à jour plateforme', 'icon' => 'rocket', 'access' => 'system', 'desc' => 'Paquets de mise à jour et santé'],
        ['path' => 'admin/system/deployment', 'label' => 'Publications & canaux', 'icon' => 'layers', 'access' => 'system', 'not' => ['admin/system/deployment/communities', 'admin/system/deployment/campaigns'], 'desc' => 'Versions proposées par canal'],
        ['path' => 'admin/system/deployment/campaigns', 'label' => 'Campagnes de publication', 'icon' => 'flag', 'access' => 'system', 'desc' => 'Déploiements progressifs en cours'],
        ['path' => 'admin/system/deployment/communities', 'label' => 'Communautés de test', 'icon' => 'flask', 'access' => 'system', 'desc' => 'Communautés qui reçoivent les préversions'],
    ]],
    ['key' => 'exploitation', 'label' => 'Exploitation', 'items' => [
        ['path' => 'admin/system/storage', 'label' => 'Espace disque', 'icon' => 'disk', 'access' => 'system', 'desc' => 'Occupation et vidage des historiques'],
        ['path' => 'admin/maintenance', 'label' => 'Maintenance des données', 'icon' => 'wrench', 'access' => 'hub', 'desc' => 'Fenêtres de maintenance planifiées'],
        ['path' => 'admin/audit', 'label' => 'Journal d’audit', 'icon' => 'audit', 'access' => 'hub', 'desc' => 'Toutes les actions sensibles'],
        ['path' => 'back-office', 'label' => 'Back-office communauté', 'icon' => 'home', 'access' => 'org', 'desc' => 'Administration de votre communauté'],
    ]],
];

$platformAdminCrumbGroup = 'Administration plateforme';
$navGroups = [];
foreach ($platformNav as $group) {
    $items = [];
    foreach ($group['items'] as $item) {
        if (empty($accessOk[$item['access']])) {
            continue;
        }
        $isActive = !empty($item['exact']) ? $p === $item['path'] : $under($item['path']);
        foreach ($item['also'] ?? [] as $alsoPath) {
            $isActive = $isActive || $under($alsoPath);
        }
        foreach ($item['not'] ?? [] as $notPath) {
            $isActive = $isActive && !$under($notPath);
        }
        if ($isActive) {
            $platformAdminCrumbGroup = $group['label'];
        }
        $item['active'] = $isActive;
        $items[] = $item;
    }
    if ($items !== []) {
        $navGroups[] = ['key' => $group['key'], 'label' => $group['label'], 'items' => $items];
    }
}

$userName = trim((string) (\App\Core\Session::get('display_name') ?? \App\Core\Session::get('callsign') ?? 'Administrateur')) ?: 'Administrateur';
$words = preg_split('/\s+/u', $userName) ?: [];
$initials = count($words) > 1
    ? mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1))
    : mb_strtoupper(mb_substr($userName, 0, 2));
?>
<nav class="ath-sidebar ath-sidebar--platform" id="ath-sidebar" aria-label="Navigation administration plateforme">
    <div class="ath-sidebar__head">
        <a href="<?= $h(url('admin')) ?>" class="ath-sidebar__logo" title="Tableau de bord plateforme" aria-label="Tableau de bord plateforme">A</a>
        <div class="ath-sidebar__brand">
            <a href="<?= $h(url('admin')) ?>" class="ath-sidebar__brand-name">ATHENA<span>.</span></a>
            <div class="ath-sidebar__brand-sub">ADMINISTRATION · PLATEFORME</div>
        </div>
        <button type="button" class="ath-sidebar__toggle" data-ath-sidebar-toggle title="Plier le menu" aria-label="Plier le menu">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg>
        </button>
    </div>

    <div class="ath-sidebar__filter">
        <label for="ath-menu-search" class="ath-sr-only">Filtrer le menu</label>
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.2-3.2"></path></svg>
        <input id="ath-menu-search" type="search" placeholder="Filtrer le menu…" autocomplete="off" spellcheck="false">
    </div>

    <div class="ath-sidebar__nav" id="ath-sidebar-nav">
        <?php foreach ($navGroups as $group) { ?>
        <div class="ath-sidebar__group is-open" data-ath-nav-group="pa-<?= $h($group['key']) ?>">
            <button type="button" class="ath-sidebar__group-head" data-ath-group-toggle aria-expanded="true"><svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2"><path d="m9 18 6-6-6-6"></path></svg><span class="ath-sidebar__group-label"><?= $h(mb_strtoupper($group['label'], 'UTF-8')) ?></span></button>
            <div class="ath-sidebar__group-body">
                <?php foreach ($group['items'] as $item) { ?>
                <div data-ath-nav-item data-ath-desc="<?= $h($item['desc']) ?>">
                    <a href="<?= $h(url($item['path'])) ?>" class="ath-sidebar__item<?= $item['active'] ? ' is-active' : '' ?>"<?= $item['active'] ? ' aria-current="page"' : '' ?> title="<?= $h($item['label'] . ' · ' . $item['desc']) ?>">
                        <?= $icon($item['icon']) ?><span class="ath-sidebar__item-label"><?= $h($item['label']) ?></span>
                    </a>
                </div>
                <?php } ?>
            </div>
        </div>
        <?php } ?>
    </div>

    <a href="<?= $h(url('dashboard')) ?>" class="ath-sidebar__portal" title="Retour au portail">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"></path></svg>
        <span class="ath-sidebar__portal-label">Retour au tableau de bord</span>
    </a>
    <div class="ath-sidebar__foot">
        <?php $paPortrait = \App\Support\OperatorPortraits::forUser((int) \App\Core\Session::get('tenant_id'), (int) \App\Core\Session::get('user_id')); ?>
        <div class="ath-sidebar__avatar<?= $paPortrait !== null ? ' ath-sidebar__avatar--photo' : '' ?>" aria-hidden="true">
            <?= $paPortrait !== null
                ? '<img src="' . $h($paPortrait) . '" alt="" width="30" height="30" decoding="async" data-img-fallback="avatar" data-img-initials="' . $h($initials) . '">'
                : $h($initials) ?>
        </div>
        <div class="ath-sidebar__user-meta">
            <div class="ath-sidebar__user-name"><?= $h(mb_strtoupper($userName)) ?></div>
            <div class="ath-sidebar__user-role"><?= $isSupportHub ? 'ASSISTANCE PLATEFORME' : 'ADMINISTRATEUR PLATEFORME' ?></div>
        </div>
    </div>
</nav>
