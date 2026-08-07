<?php
$base = url('');
$title = $title ?? 'Carte ATAK — Athena';
$dashboard_tenant_label = $dashboard_tenant_label ?? null;
$dashboard_is_default_tenant = !empty($dashboard_is_default_tenant);
$atakModDownloadUrl = $atakModDownloadUrl ?? null;
$can_view_atak_operators = !empty($can_view_atak_operators);
$atak_operators_linked_count = $atak_operators_linked_count ?? null;
$can_manage_invitations = !empty($can_manage_invitations);
$pending_invitations_count = (int) ($pending_invitations_count ?? 0);
$unitLabel = ($dashboard_tenant_label !== null && $dashboard_tenant_label !== '')
    ? (string) $dashboard_tenant_label
    : 'Communauté';
$dashboardTenantType = \App\Services\Community\TenantTypeConfig::normalizeType(
    (string) ($dashboard_tenant_type ?? 'atak')
);
$dashboardTypeLabel = \App\Services\Community\TenantTypeConfig::label($dashboardTenantType);
$canAdjustTenantType = function_exists('can') && (can('admin.organization') || can('admin.access'));
?>
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
<?php
    $seo_og_title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $meta_description = $meta_description ?? 'Poste de suivi ATAK : carte tactique et coordination terrain.';
    require base_path('views/partials/seo_meta.php');
?>
    <?php $tailwindBaseUrl = $base; require base_path('views/partials/tailwind_cdn_or_build.php'); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=IBM+Plex+Mono:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600;700&family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <?php if (is_file(base_path('public/assets/css/design-system.css'))): ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/design-system.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endif; ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php if (is_file(base_path('public/assets/css/athena-header.css'))): ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/athena-header.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endif; ?>
    <?php if (is_file(base_path('public/assets/css/navbar-info-banners.css'))): ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/navbar-info-banners.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endif; ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/dashboard-atak.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
</head>
<body class="dashboard-shell dashboard-atak-shell antialiased">
<script defer src="<?= htmlspecialchars(asset_url('assets/js/portal-alerts.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php if (is_file(base_path('public/assets/js/athena-header.js'))): ?>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/athena-header.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>
<?php
$alertBanners = [];
require base_path('views/partials/alert_banners.php');
?>
<div class="atak-dash">
<main class="atak-dash">
    <?php require base_path('views/partials/layout_flash_toasts.php'); ?>
    <?php require base_path('views/partials/header_dashboard.php'); ?>
    <?php require base_path('views/partials/media_reupload_notice.php'); ?>

    <section class="atak-dash__hero" aria-labelledby="atak-dash-title">
        <div class="atak-dash__hero-inner">
            <p class="atak-dash__eyebrow">Profil <?= htmlspecialchars($dashboardTypeLabel, ENT_QUOTES, 'UTF-8') ?></p>
            <h1 id="atak-dash-title" class="atak-dash__title">
                <?= htmlspecialchars($unitLabel, ENT_QUOTES, 'UTF-8') ?><span class="atak-dash__title-dot">.</span>
            </h1>
            <p class="atak-dash__lead">
                Poste de suivi terrain : ouvrez la carte, vérifiez les opérateurs en liaison et ajustez les réglages essentiels.
                <?php
                $selfPlaytimeAtak = trim((string) ($arma_playtime_label ?? ''));
                if ($selfPlaytimeAtak !== ''):
                ?>
                <span class="atak-dash__playtime">Votre temps en mission : <?= htmlspecialchars($selfPlaytimeAtak, ENT_QUOTES, 'UTF-8') ?>.</span>
                <?php endif; ?>
            </p>
            <p class="atak-dash__scope">
                <span class="atak-dash__scope-kicker">Périmètre actif</span>
                <span>Carte tactique, forum public et administration — formations, effectifs et recrutement restent hors périmètre.</span>
                <?php if ($canAdjustTenantType): ?>
                <a href="<?= htmlspecialchars(url('back-office/organisation/parametres') . '#org-profil', ENT_QUOTES, 'UTF-8') ?>">
                    Vérifier ou réappliquer le profil
                </a>
                <?php endif; ?>
            </p>
            <div class="atak-dash__actions">
                <a href="<?= htmlspecialchars(url('atak'), ENT_QUOTES, 'UTF-8') ?>" class="atak-dash__btn atak-dash__btn--primary">
                    Ouvrir la carte
                </a>
                <?php if ($can_manage_invitations): ?>
                <a href="<?= htmlspecialchars(url('back-office/invitations'), ENT_QUOTES, 'UTF-8') ?>" class="atak-dash__btn atak-dash__btn--ghost">
                    Inviter un membre<?= $pending_invitations_count > 0 ? ' (' . $pending_invitations_count . ')' : '' ?>
                </a>
                <a href="<?= htmlspecialchars(url('back-office/users/create'), ENT_QUOTES, 'UTF-8') ?>" class="atak-dash__btn atak-dash__btn--outline">
                    Créer un compte
                </a>
                <?php endif; ?>
                <a href="<?= htmlspecialchars(url('overwatch'), ENT_QUOTES, 'UTF-8') ?>" class="atak-dash__btn atak-dash__btn--ghost">
                    Overwatch
                </a>
                <?php if ($atakModDownloadUrl): ?>
                <a href="<?= htmlspecialchars((string) $atakModDownloadUrl, ENT_QUOTES, 'UTF-8') ?>" class="atak-dash__btn atak-dash__btn--outline">
                    Télécharger le mod
                </a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="atak-dash__modules" aria-label="Accès rapides">
        <div class="atak-dash__modules-inner">
            <a href="<?= htmlspecialchars(url('atak'), ENT_QUOTES, 'UTF-8') ?>" class="atak-dash__module">
                <p class="atak-dash__module-kicker">Situation</p>
                <h2 class="atak-dash__module-title">Carte tactique</h2>
                <p class="atak-dash__module-copy">Vue opérationnelle en temps réel pour la cellule terrain.</p>
            </a>
            <a href="<?= htmlspecialchars(url('back-office/atak/operateurs'), ENT_QUOTES, 'UTF-8') ?>" class="atak-dash__module atak-dash__module--sky">
                <p class="atak-dash__module-kicker">Liaison</p>
                <h2 class="atak-dash__module-title">Effectifs en liaison</h2>
                <p class="atak-dash__module-copy">
                    <?php if ($can_view_atak_operators && $atak_operators_linked_count !== null): ?>
                        <?= (int) $atak_operators_linked_count ?> opérateur<?= (int) $atak_operators_linked_count > 1 ? 's' : '' ?> connecté<?= (int) $atak_operators_linked_count > 1 ? 's' : '' ?>.
                    <?php else: ?>
                        Tableur des opérateurs connectés à la carte.
                    <?php endif; ?>
                </p>
            </a>
            <a href="<?= htmlspecialchars(url('back-office/community'), ENT_QUOTES, 'UTF-8') ?>" class="atak-dash__module atak-dash__module--amber">
                <p class="atak-dash__module-kicker">Administration</p>
                <h2 class="atak-dash__module-title">Paramètres</h2>
                <p class="atak-dash__module-copy">Identité, invitations<?= $can_manage_invitations && $pending_invitations_count > 0 ? ' (' . $pending_invitations_count . ' en attente)' : '' ?> et profil de la communauté.</p>
            </a>
        </div>
    </section>

    <section class="atak-dash__tools" aria-label="Outils carte">
        <div class="atak-dash__tools-inner">
            <?php if ($can_manage_invitations): ?>
            <a href="<?= htmlspecialchars(url('back-office/invitations'), ENT_QUOTES, 'UTF-8') ?>" class="atak-dash__tool">Invitations<?= $pending_invitations_count > 0 ? ' (' . $pending_invitations_count . ')' : '' ?></a>
            <a href="<?= htmlspecialchars(url('back-office/users/create'), ENT_QUOTES, 'UTF-8') ?>" class="atak-dash__tool">Créer un compte</a>
            <a href="<?= htmlspecialchars(url('back-office/users'), ENT_QUOTES, 'UTF-8') ?>" class="atak-dash__tool">Membres</a>
            <?php endif; ?>
            <a href="<?= htmlspecialchars(url('forum'), ENT_QUOTES, 'UTF-8') ?>" class="atak-dash__tool">Forum</a>
            <a href="<?= htmlspecialchars(url('atak/passerelle'), ENT_QUOTES, 'UTF-8') ?>" class="atak-dash__tool">Passerelle inter-équipes</a>
            <a href="<?= htmlspecialchars(url('atak/premiere-liaison'), ENT_QUOTES, 'UTF-8') ?>" class="atak-dash__tool">Première liaison</a>
            <a href="<?= htmlspecialchars(url('tacmap'), ENT_QUOTES, 'UTF-8') ?>" class="atak-dash__tool">TACMAP</a>
            <a href="<?= htmlspecialchars(url('back-office/atak/fire-teams'), ENT_QUOTES, 'UTF-8') ?>" class="atak-dash__tool">Équipes de feu</a>
            <a href="<?= htmlspecialchars(url('admin/atak-config'), ENT_QUOTES, 'UTF-8') ?>" class="atak-dash__tool">Réglages carte</a>
        </div>
    </section>
</main>
</div>
</body>
</html>
