<?php
declare(strict_types=1);

/**
 * Shell commun de l’espace compte (bandeau + navigation latérale).
 *
 * Variables attendues (optionnelles sauf $accountNavKey / $accountTitle) :
 * - string $accountNavKey
 * - string $accountTitle
 * - string $accountLead
 * - string|null $success
 * - string|null $error
 * - bool $accountHideDefaultActions
 */
$accountNavKey = (string) ($accountNavKey ?? 'overview');
$accountTitle = (string) ($accountTitle ?? 'Mon compte');
$accountLead = (string) ($accountLead ?? '');
$accountSuccess = $success ?? null;
$accountError = $error ?? null;
$accountHideDefaultActions = !empty($accountHideDefaultActions);

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

$navGroups = [
    [
        'title' => 'Compte',
        'items' => [
            [
                'key' => 'overview',
                'href' => url('account'),
                'label' => 'Vue d’ensemble',
                'hint' => 'État du compte, activité',
                'icon' => '<path d="M3 12l9-8 9 8"/><path d="M5 10v10h14V10"/>',
            ],
            [
                'key' => 'preferences',
                'href' => url('account/preferences'),
                'label' => 'Profil et préférences',
                'hint' => 'Nom, langue, thème, e-mails',
                'icon' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>',
            ],
        ],
    ],
    [
        'title' => 'Sécurité',
        'items' => [
            [
                'key' => 'mail',
                'href' => url('account/mail'),
                'label' => 'Adresse e-mail',
                'hint' => 'Adresse de connexion',
                'icon' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
            ],
            [
                'key' => 'password',
                'href' => url('account/password'),
                'label' => 'Mot de passe',
                'hint' => 'Changer le secret d’accès',
                'icon' => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 018 0v3"/>',
            ],
            [
                'key' => 'security',
                'href' => url('account/security'),
                'label' => 'Double vérification',
                'hint' => 'Code par e-mail ou application',
                'icon' => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
            ],
            [
                'key' => 'devices',
                'href' => url('account/security/devices'),
                'label' => 'Appareils ATAK',
                'hint' => 'Téléphones COMSPEC liés',
                'icon' => '<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/>',
            ],
        ],
    ],
    [
        'title' => 'Apparence',
        'items' => [
            [
                'key' => 'portrait',
                'href' => url('account/portrait'),
                'label' => 'Portrait',
                'hint' => 'Votre photo d’opérateur',
                'icon' => '<rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="12" cy="10" r="3"/><path d="M6 19c1.2-2.4 3.4-3.5 6-3.5s4.8 1.1 6 3.5"/>',
            ],
            [
                'key' => 'banner',
                'href' => url('account/banner'),
                'label' => 'Couverture du menu',
                'hint' => 'Bandeau du menu session',
                'icon' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 15l5-4 4 3 3-2 6 4"/>',
            ],
        ],
    ],
    [
        'title' => 'Communauté',
        'items' => [
            [
                'key' => 'access',
                'href' => url('account/acces'),
                'label' => 'Mes accès',
                'hint' => 'Rôle et départ de la communauté',
                'icon' => '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M17 6l3 3"/>',
            ],
            [
                'key' => 'recruitment',
                'href' => url('account/recruitment-presets'),
                'label' => 'Profils de candidature',
                'hint' => 'Préréglages d’enrôlement',
                'icon' => '<path d="M9 4h6l1 2h3v14H5V6h3z"/><path d="M9 12h6M9 16h4"/>',
            ],
        ],
    ],
    [
        'title' => 'Confidentialité',
        'items' => [
            [
                'key' => 'donnees',
                'href' => url('account/donnees'),
                'label' => 'Mes données',
                'hint' => 'Export et suppression du compte',
                'icon' => '<path d="M4 16v3a2 2 0 002 2h12a2 2 0 002-2v-3"/><path d="M12 3v12M7 10l5 5 5-5"/>',
            ],
        ],
    ],
];

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
                <a href="<?= htmlspecialchars(url('personnel/me'), ENT_QUOTES, 'UTF-8') ?>" class="account-hub__btn account-hub__btn--ghost"><?= htmlspecialchars($accountPhrase('Ma fiche'), ENT_QUOTES, 'UTF-8') ?></a>
                <a href="<?= htmlspecialchars(url('dashboard'), ENT_QUOTES, 'UTF-8') ?>" class="account-hub__btn account-hub__btn--ghost">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <?= htmlspecialchars($accountPhrase('Tableau de bord'), ENT_QUOTES, 'UTF-8') ?>
                </a>
            </div>
            <?php endif; ?>
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
