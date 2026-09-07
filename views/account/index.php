<?php
declare(strict_types=1);

$accountUser = is_array($accountUser ?? null) ? $accountUser : [];
$accountProfile = is_array($accountProfile ?? null) ? $accountProfile : [];
$accountSnapshot = $accountSnapshot ?? ['email_masked' => '—', 'email_verified' => false, 'last_login_label' => null];
$onboardingSnapshot = is_array($onboardingSnapshot ?? null) ? $onboardingSnapshot : [];
$accountHasPortrait = !empty($accountHasPortrait);

$prefUrl = url('account/preferences');
$callsign = trim((string) ($accountUser['callsign'] ?? ''));
$displayNameVal = trim((string) ($accountUser['display_name'] ?? ''));
$onboardingPercent = (int) ($onboardingSnapshot['percent'] ?? 0);
$onboardingNudge = trim((string) ($onboardingSnapshot['nudge'] ?? ''));
$onboardingTotal = (int) ($onboardingSnapshot['total_count'] ?? 0);
$showOnboarding = $onboardingTotal > 0 && $onboardingPercent < 100;

$accountNavKey = 'overview';
$accountTitle = 'Mon compte';
$accountLead = 'Connexion, photo et préférences. L’unité et le grade restent sur votre fiche.';
require base_path('views/partials/account/shell_open.php');

$chevron = '<svg class="account-hub__action-chevron" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>';
?>

<?php if ($showOnboarding): ?>
<section class="account-hub__panel" aria-labelledby="onboarding-heading">
    <div class="account-hub__panel-head">
        <p class="account-hub__panel-kicker"><?= htmlspecialchars(function_exists('__') ? __('common.integration_eyebrow') : 'Arrivée', ENT_QUOTES, 'UTF-8') ?></p>
        <h2 id="onboarding-heading" class="account-hub__panel-title"><?= htmlspecialchars(function_exists('__') ? __('common.integration_account_heading') : 'Votre arrivée n’est pas terminée', ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="account-hub__panel-desc">
            <?= $onboardingNudge !== '' && $onboardingNudge !== 'RAS'
                ? htmlspecialchars($onboardingNudge, ENT_QUOTES, 'UTF-8')
                : htmlspecialchars(function_exists('__') ? __('common.integration_account_nudge') : 'Les étapes restantes se trouvent dans Mon intégration, pas ici.', ENT_QUOTES, 'UTF-8') ?>
        </p>
    </div>
    <div class="account-hub__panel-body">
        <a href="<?= htmlspecialchars(url('mon-integration'), ENT_QUOTES, 'UTF-8') ?>" class="account-hub__btn account-hub__btn--ink"><?= htmlspecialchars(function_exists('__') ? __('common.integration_open') : 'Ouvrir Mon intégration', ENT_QUOTES, 'UTF-8') ?></a>
    </div>
</section>
<?php endif; ?>

<section class="account-hub__panel" aria-labelledby="account-overview-heading"<?= $showOnboarding ? ' style="margin-top:1.25rem"' : '' ?>>
    <div class="account-hub__panel-head">
        <p class="account-hub__panel-kicker">Aperçu</p>
        <h2 id="account-overview-heading" class="account-hub__panel-title">Ce compte</h2>
        <p class="account-hub__panel-desc">Trois informations utiles, puis les réglages correspondants.</p>
    </div>
    <div class="account-hub__panel-body">
        <div class="account-hub__stat-grid">
            <div class="account-hub__stat">
                <p class="account-hub__stat-label">Connexion</p>
                <p class="account-hub__stat-value" style="font-family:ui-monospace,monospace;font-size:.85rem"><?= htmlspecialchars((string) ($accountSnapshot['email_masked'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></p>
                <p class="account-hub__stat-meta">
                    <?php if (!empty($accountSnapshot['email_verified'])): ?>
                    <span class="account-hub__badge account-hub__badge--ok">Adresse confirmée</span>
                    <?php else: ?>
                    <span class="account-hub__badge account-hub__badge--warn">Confirmation en attente</span>
                    <?php endif; ?>
                </p>
                <p class="account-hub__stat-meta" style="margin-top:.55rem">
                    <a href="<?= htmlspecialchars(url('account/mail'), ENT_QUOTES, 'UTF-8') ?>" style="font-weight:700;color:#047857;text-decoration:underline;text-underline-offset:2px">Changer l’adresse</a>
                </p>
            </div>
            <div class="account-hub__stat">
                <p class="account-hub__stat-label">Nom affiché</p>
                <p class="account-hub__stat-value"><?= htmlspecialchars($displayNameVal !== '' ? $displayNameVal : 'Non renseigné', ENT_QUOTES, 'UTF-8') ?></p>
                <p class="account-hub__stat-meta">
                    <?php if ($callsign !== ''): ?>Indicatif : <?= htmlspecialchars($callsign, ENT_QUOTES, 'UTF-8') ?><?php else: ?>Indicatif non renseigné<?php endif; ?>
                </p>
                <p class="account-hub__stat-meta" style="margin-top:.55rem">
                    <a href="<?= htmlspecialchars($prefUrl, ENT_QUOTES, 'UTF-8') ?>" style="font-weight:700;color:#047857;text-decoration:underline;text-underline-offset:2px">Modifier le profil</a>
                </p>
            </div>
            <div class="account-hub__stat">
                <p class="account-hub__stat-label">Portrait</p>
                <p class="account-hub__stat-value"><?= $accountHasPortrait ? 'Enregistré' : 'À ajouter' ?></p>
                <p class="account-hub__stat-meta">Une seule photo, visible sur le portail et la fiche.</p>
                <p class="account-hub__stat-meta" style="margin-top:.55rem">
                    <a href="<?= htmlspecialchars(url('account/portrait'), ENT_QUOTES, 'UTF-8') ?>" style="font-weight:700;color:#047857;text-decoration:underline;text-underline-offset:2px"><?= $accountHasPortrait ? 'Changer la photo' : 'Ajouter une photo' ?></a>
                </p>
            </div>
        </div>
    </div>
</section>

<?php
$hubActions = [
    [
        'href' => $prefUrl,
        'title' => 'Profil et préférences',
        'desc' => 'Nom, indicatif, langue, thème et notifications par e-mail.',
        'icon' => '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
    ],
    [
        'href' => url('account/security'),
        'title' => 'Sécurité',
        'desc' => 'Mot de passe, double vérification et appareils ATAK.',
        'icon' => '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>',
    ],
    [
        'href' => url('account/donnees'),
        'title' => 'Mes données',
        'desc' => 'Télécharger une copie des informations enregistrées sur ce compte.',
        'icon' => '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>',
    ],
    [
        'href' => url('personnel/me'),
        'title' => 'Ma fiche personnelle',
        'desc' => 'Unité, grade et affectations : ce n’est pas ici.',
        'icon' => '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>',
    ],
];
?>

<section class="account-hub__panel" style="margin-top:1.25rem" aria-labelledby="account-go-heading">
    <div class="account-hub__panel-head">
        <p class="account-hub__panel-kicker">Réglages</p>
        <h2 id="account-go-heading" class="account-hub__panel-title">Où aller</h2>
        <p class="account-hub__panel-desc">Le menu de gauche reprend les mêmes destinations, sans les répéter deux fois.</p>
    </div>
    <div class="account-hub__panel-body" style="padding-top:.35rem;padding-bottom:.5rem">
        <ul class="account-hub__action-list">
            <?php foreach ($hubActions as $action): ?>
            <li>
                <a href="<?= htmlspecialchars($action['href'], ENT_QUOTES, 'UTF-8') ?>" class="account-hub__action">
                    <span class="account-hub__action-icon" aria-hidden="true"><?= $action['icon'] ?></span>
                    <span>
                        <p class="account-hub__action-title"><?= htmlspecialchars($action['title'], ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="account-hub__action-desc"><?= htmlspecialchars($action['desc'], ENT_QUOTES, 'UTF-8') ?></p>
                    </span>
                    <?= $chevron ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<p class="account-hub__footer-note">
    Une question sur votre unité ? Ouvrez <a href="<?= htmlspecialchars(url('personnel/me'), ENT_QUOTES, 'UTF-8') ?>">votre fiche</a> ou le <a href="<?= htmlspecialchars(url('dashboard'), ENT_QUOTES, 'UTF-8') ?>">tableau de bord</a>.
</p>

<?php require base_path('views/partials/account/shell_close.php'); ?>
