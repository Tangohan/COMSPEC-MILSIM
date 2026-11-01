<?php
declare(strict_types=1);

/**
 * Shell commun de l’espace compte (bandeau, recherche + navigation latérale).
 *
 * Variables attendues (optionnelles sauf $accountNavKey / $accountTitle) :
 * - string $accountNavKey
 * - string $accountTitle
 * - string $accountLead
 * - string|null $success
 * - string|null $error
 * - bool $accountHideDefaultActions
 *
 * Pages du menu et index de recherche : App\Support\AccountSpaceCatalog.
 */
$accountNavKey = (string) ($accountNavKey ?? 'overview');
$accountTitle = (string) ($accountTitle ?? 'Mon compte');
$accountLead = (string) ($accountLead ?? '');
$accountSuccess = $success ?? null;
$accountError = $error ?? null;
$accountHideDefaultActions = !empty($accountHideDefaultActions);
/* Lien « Voir ma page publique » : fourni par le contrôleur seulement quand la page est ouverte. */
$accountPublicCvLink = isset($accountPublicCvUrl) && is_string($accountPublicCvUrl) && $accountPublicCvUrl !== '' ? $accountPublicCvUrl : null;

$ctx = function_exists('portal_header_context') ? portal_header_context() : [];
$displayName = trim((string) ($ctx['display_name'] ?? ''));
$tenantLabel = trim((string) ($ctx['tenant_label'] ?? ''));
$roleLabel = trim((string) ($ctx['role_label'] ?? ''));

$accountUserForShell = is_array($accountUser ?? null)
    ? $accountUser
    : (is_array($user ?? null) ? $user : []);
$accountShellCallsign = trim((string) ($accountUserForShell['callsign'] ?? ''));

/* Règle du site : seul le portrait opérateur représente la personne (initiales à défaut). */
$accountAvatarSrc = null;
if (!empty($accountUserForShell['id'])) {
    try {
        $accountAvatarSrc = \App\Support\OperatorPortraits::forUser(
            (int) ($accountUserForShell['tenant_id'] ?? 0),
            (int) $accountUserForShell['id']
        );
    } catch (\Throwable) {
        $accountAvatarSrc = null;
    }
}
$accountInitials = $displayName !== '' && function_exists('user_display_initials')
    ? user_display_initials($displayName)
    : ($displayName !== ''
        ? htmlspecialchars(mb_strtoupper(mb_substr($displayName, 0, 1)), ENT_QUOTES, 'UTF-8')
        : '·');

$accountIcon = static function (string $path): string {
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
};

$navGroups = \App\Support\AccountSpaceCatalog::navGroups();

/* Réglages qui n’existent pas pour ce compte : absents de la recherche plutôt que de mener à une page vide. */
$accountSearchHidden = [];
try {
    $discordShell = new \App\Services\Integrations\DiscordLinkedRoles();
    $discordLinksShell = new \App\Repositories\UserDiscordLinkRepository();
    $discordLinkedShell = !empty($accountUserForShell['id']) && $discordLinksShell->schemaReady()
        && $discordLinksShell->find((int) $accountUserForShell['id']) !== null;
    if (!$discordShell->configured() && !$discordLinkedShell) {
        $accountSearchHidden[] = 'section-discord';
    }
} catch (\Throwable) {
    $accountSearchHidden[] = 'section-discord';
}
try {
    $shellTenantId = (int) (\App\Core\Session::get('tenant_id') ?? 0);
    if ($shellTenantId < 1 || !\App\Core\Container::get(\App\Services\Platform\FeatureGateService::class)->allows($shellTenantId, 'training')) {
        $accountSearchHidden[] = 'charter';
    }
} catch (\Throwable) {
    $accountSearchHidden[] = 'charter';
}
$accountSearchEntries = \App\Support\AccountSpaceCatalog::searchEntries($accountSearchHidden);

if (function_exists('i18n_translate_nav_item')) {
    $navGroups = array_map('i18n_translate_nav_item', $navGroups);
}
if (function_exists('i18n_phrase')) {
    $accountTitle = i18n_phrase('nav', $accountTitle);
    if ($accountLead !== '') {
        $accountLead = i18n_phrase('nav', $accountLead);
    }
}
$accountPhrase = static fn (string $s): string => function_exists('i18n_phrase') ? i18n_phrase('nav', $s) : $s;

?>
<div class="account-hub">
    <header class="account-hub__hero">
        <div class="account-hub__hero-inner">
            <div class="account-hub__hero-id">
                <span class="account-hub__hero-portrait<?= $accountAvatarSrc ? '' : ' is-empty' ?>" aria-hidden="true">
                    <?php if ($accountAvatarSrc): ?>
                    <img src="<?= htmlspecialchars($accountAvatarSrc, ENT_QUOTES, 'UTF-8') ?>" alt="">
                    <?php else: ?>
                    <?= $accountInitials ?>
                    <?php endif; ?>
                </span>
                <div class="account-hub__hero-text">
                    <p class="account-hub__eyebrow"><?= htmlspecialchars($accountPhrase('Espace personnel'), ENT_QUOTES, 'UTF-8') ?></p>
                    <h1 class="account-hub__title"><?= htmlspecialchars($accountTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                    <?php if ($accountLead !== ''): ?>
                    <p class="account-hub__lead"><?= htmlspecialchars($accountLead, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <div class="account-hub__hero-meta">
                        <?php if ($displayName !== ''): ?>
                        <span class="account-hub__pill account-hub__pill--name">
                            <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>
                            <?php if ($accountShellCallsign !== '' && stripos($displayName, $accountShellCallsign) === false): ?>
                            <span class="account-hub__pill-sub">« <?= htmlspecialchars($accountShellCallsign, ENT_QUOTES, 'UTF-8') ?> »</span>
                            <?php endif; ?>
                        </span>
                        <?php endif; ?>
                        <?php if ($tenantLabel !== ''): ?>
                        <span class="account-hub__pill account-hub__pill--tenant"><?= htmlspecialchars($tenantLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                        <?php if ($roleLabel !== ''): ?>
                        <span class="account-hub__pill account-hub__pill--role"><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php if (!$accountHideDefaultActions): ?>
            <div class="account-hub__hero-actions">
                <?php if ($accountPublicCvLink !== null): ?>
                <a href="<?= htmlspecialchars($accountPublicCvLink, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="account-hub__btn account-hub__btn--ghost"><?= htmlspecialchars($accountPhrase('Voir ma page publique'), ENT_QUOTES, 'UTF-8') ?></a>
                <?php endif; ?>
                <a href="<?= htmlspecialchars(url('personnel/me'), ENT_QUOTES, 'UTF-8') ?>" class="account-hub__btn account-hub__btn--ghost"><?= htmlspecialchars($accountPhrase('Ma fiche'), ENT_QUOTES, 'UTF-8') ?></a>
                <a href="<?= htmlspecialchars(url('dashboard'), ENT_QUOTES, 'UTF-8') ?>" class="account-hub__btn account-hub__btn--ghost">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <?= htmlspecialchars($accountPhrase('Tableau de bord'), ENT_QUOTES, 'UTF-8') ?>
                </a>
            </div>
            <?php endif; ?>
        </div>
        <div class="account-hub__hero-search">
            <?php require base_path('views/partials/account/search.php'); ?>
        </div>
    </header>

    <div class="account-hub__body">
        <nav class="account-hub__nav" aria-label="<?= htmlspecialchars($accountPhrase('Sections du compte'), ENT_QUOTES, 'UTF-8') ?>">
            <?php foreach ($navGroups as $group): ?>
            <div class="account-hub__nav-group">
                <p class="account-hub__nav-group-title"><?= htmlspecialchars((string) $group['title'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php foreach ($group['items'] as $item): ?>
                    <?php
                    $isActive = $accountNavKey === ($item['key'] ?? '');
                    ?>
                <a href="<?= htmlspecialchars((string) $item['href'], ENT_QUOTES, 'UTF-8') ?>"
                   class="account-hub__nav-link<?= $isActive ? ' is-active' : '' ?>"
                   <?= $isActive ? 'aria-current="page"' : '' ?>>
                    <span class="account-hub__nav-icon"><?= $accountIcon((string) ($item['icon'] ?? '')) ?></span>
                    <span>
                        <?= htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8') ?>
                        <?php if (!empty($item['hint'])): ?>
                        <small><?= htmlspecialchars((string) $item['hint'], ENT_QUOTES, 'UTF-8') ?></small>
                        <?php endif; ?>
                    </span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </nav>

        <div class="account-hub__main">
            <?php if ($accountSuccess): ?>
            <div class="account-hub__flash account-hub__flash--ok" role="status"><?= htmlspecialchars((string) $accountSuccess, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if ($accountError): ?>
            <div class="account-hub__flash account-hub__flash--err" role="alert"><?= htmlspecialchars((string) $accountError, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php
            /* Page ouverte depuis l'accueil du profil : un lien ramène à l'étape en cours. */
            $accountWelcomeReturn = (string) (\App\Core\Session::get(\App\Services\Account\ProfileWelcomeService::SESSION_RETURN) ?? '');
            if ($accountNavKey !== 'welcome' && \App\Services\Account\ProfileWelcomeService::isStep($accountWelcomeReturn)):
            ?>
            <div class="ah-wel-return">
                <span><?= htmlspecialchars($accountPhrase('Vous complétez votre profil.'), ENT_QUOTES, 'UTF-8') ?></span>
                <a href="<?= htmlspecialchars(\App\Services\Account\ProfileWelcomeJourney::stepUrl($accountWelcomeReturn), ENT_QUOTES, 'UTF-8') ?>" class="account-hub__btn account-hub__btn--soft"><?= htmlspecialchars($accountPhrase('Revenir à l’accueil du profil'), ENT_QUOTES, 'UTF-8') ?></a>
            </div>
            <?php endif; ?>
