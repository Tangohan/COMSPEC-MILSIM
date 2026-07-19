<?php
declare(strict_types=1);

/**
 * Shell commun de l’espace compte (hero + navigation latérale).
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
$accountAvatarSrc = function_exists('user_media_public_url')
    ? user_media_public_url($accountUserForShell['avatar_url'] ?? null)
    : null;
$accountInitials = $displayName !== '' && function_exists('user_display_initials')
    ? user_display_initials($displayName)
    : ($displayName !== ''
        ? htmlspecialchars(mb_strtoupper(mb_substr($displayName, 0, 1)), ENT_QUOTES, 'UTF-8')
        : '·');

$navGroups = [
    [
        'title' => 'Compte',
        'items' => [
            [
                'key' => 'overview',
                'href' => url('account'),
                'label' => 'Vue d’ensemble',
                'hint' => 'Résumé et accès rapides',
            ],
            [
                'key' => 'preferences',
                'href' => url('account/preferences'),
                'label' => 'Profil & préférences',
                'hint' => 'Identité, affichage, fuseau',
            ],
            [
                'key' => 'notifications',
                'href' => url('account/preferences') . '#notifications-email',
                'label' => 'Notifications e-mail',
                'hint' => 'Messages automatiques du portail',
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
                'hint' => 'Connexion et double vérification',
            ],
            [
                'key' => 'password',
                'href' => url('account/password'),
                'label' => 'Mot de passe',
                'hint' => 'Changer le secret d’accès',
            ],
        ],
    ],
    [
        'title' => 'Apparence',
        'items' => [
            [
                'key' => 'image',
                'href' => url('account/image'),
                'label' => 'Photo de compte',
                'hint' => 'Navigation, forum, listes',
            ],
            [
                'key' => 'banner',
                'href' => url('account/banner'),
                'label' => 'Couverture du menu',
                'hint' => 'Bandeau du menu session',
            ],
            [
                'key' => 'portrait',
                'href' => url('account/portrait'),
                'label' => 'Portrait opérateur',
                'hint' => 'Fiche et organigramme',
            ],
        ],
    ],
    [
        'title' => 'Unité',
        'items' => [
            [
                'key' => 'personnel',
                'href' => url('personnel/me/edit'),
                'label' => 'Fiche personnelle',
                'hint' => 'Personnage, unité, qualifications',
            ],
            [
                'key' => 'access',
                'href' => url('account/acces'),
                'label' => 'Mes accès & rôle',
                'hint' => 'Rôle, droits, demandes en cours',
            ],
            [
                'key' => 'leave',
                'href' => url('account/acces') . '#quitter',
                'label' => 'Quitter',
                'hint' => 'Quitter la communauté actuelle',
            ],
            [
                'key' => 'recruitment',
                'href' => url('account/recruitment-presets'),
                'label' => 'Profils de candidature',
                'hint' => 'Préréglages d’enrôlement',
            ],
            [
                'key' => 'charter',
                'href' => url('account/charte-formations'),
                'label' => 'Charte des formations',
                'hint' => 'Prise de connaissance du catalogue',
            ],
        ],
    ],
];

?>
<div class="account-hub">
    <header class="account-hub__hero">
        <div class="account-hub__hero-inner">
            <div>
                <p class="account-hub__eyebrow">Espace personnel</p>
                <h1 class="account-hub__title"><?= htmlspecialchars($accountTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                <?php if ($accountLead !== ''): ?>
                <p class="account-hub__lead"><?= htmlspecialchars($accountLead, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <div class="account-hub__hero-meta">
                    <?php if ($displayName !== ''): ?>
                    <span class="account-hub__pill">
                        <span class="account-hub__avatar" aria-hidden="true">
                            <?php if ($accountAvatarSrc): ?>
                            <img src="<?= htmlspecialchars($accountAvatarSrc, ENT_QUOTES, 'UTF-8') ?>" alt="">
                            <?php else: ?>
                            <?= $accountInitials ?>
                            <?php endif; ?>
                        </span>
                        <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($tenantLabel !== ''): ?>
                    <span class="account-hub__pill account-hub__pill--tenant"><?= htmlspecialchars($tenantLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                    <?php if ($roleLabel !== ''): ?>
                    <span class="account-hub__pill" style="opacity:.75;font-weight:500;font-size:.75rem"><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!$accountHideDefaultActions): ?>
            <div class="account-hub__hero-actions">
                <a href="<?= htmlspecialchars(url('dashboard'), ENT_QUOTES, 'UTF-8') ?>" class="account-hub__btn account-hub__btn--ghost">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Tableau de bord
                </a>
                <?php if ($accountNavKey !== 'overview'): ?>
                <a href="<?= htmlspecialchars(url('account'), ENT_QUOTES, 'UTF-8') ?>" class="account-hub__btn account-hub__btn--ghost">Vue d’ensemble</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </header>

    <div class="account-hub__body">
        <nav class="account-hub__nav" aria-label="Sections du compte">
            <p class="account-hub__nav-label">Navigation</p>
            <?php foreach ($navGroups as $group): ?>
            <div class="account-hub__nav-group">
                <p class="account-hub__nav-group-title"><?= htmlspecialchars((string) $group['title'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php foreach ($group['items'] as $item): ?>
                    <?php
                    $isActive = $accountNavKey === ($item['key'] ?? '');
                    // notifications partage la page préférences
                    if ($accountNavKey === 'preferences' && ($item['key'] ?? '') === 'notifications') {
                        $isActive = false;
                    }
                    ?>
                <a href="<?= htmlspecialchars((string) $item['href'], ENT_QUOTES, 'UTF-8') ?>"
                   class="account-hub__nav-link<?= $isActive ? ' is-active' : '' ?>"
                   <?= $isActive ? 'aria-current="page"' : '' ?>>
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
