<?php
declare(strict_types=1);

$accountUser = is_array($accountUser ?? null) ? $accountUser : [];
$accountProfile = is_array($accountProfile ?? null) ? $accountProfile : [];
$accountSnapshot = $accountSnapshot ?? ['email_masked' => '—', 'email_verified' => false, 'last_login_label' => null];
$onboardingSnapshot = is_array($onboardingSnapshot ?? null) ? $onboardingSnapshot : [];
$health = $systemHealth ?? [];
$db = $health['database'] ?? [];
$api = $health['api'] ?? [];

$prefUrl = url('account/preferences');
$fn = trim((string) ($accountProfile['first_name'] ?? ''));
$ln = trim((string) ($accountProfile['last_name'] ?? ''));
$civilLine = trim($fn . ' ' . $ln);
$callsign = trim((string) ($accountUser['callsign'] ?? ''));
$displayNameVal = trim((string) ($accountUser['display_name'] ?? ''));
$tz = trim((string) ($accountProfile['timezone'] ?? ''));
$lang = trim((string) ($accountProfile['language'] ?? ''));
$langLabel = match ($lang) {
    'en' => 'English',
    'fr', '' => 'Français',
    default => $lang,
};
$steam = trim((string) ($accountUser['steam_id'] ?? ''));
$slug = trim((string) ($accountUser['profile_slug'] ?? ''));
$onboardingSteps = is_array($onboardingSnapshot['steps'] ?? null) ? $onboardingSnapshot['steps'] : [];
$onboardingCompleted = (int) ($onboardingSnapshot['completed_count'] ?? 0);
$onboardingTotal = (int) ($onboardingSnapshot['total_count'] ?? 0);
$onboardingPercent = (int) ($onboardingSnapshot['percent'] ?? 0);
$onboardingStatus = trim((string) ($onboardingSnapshot['status'] ?? 'à démarrer'));
$onboardingPlan = trim((string) ($onboardingSnapshot['plan'] ?? 'membre'));
$onboardingNudge = trim((string) ($onboardingSnapshot['nudge'] ?? ''));
$canAtakAdmin = function_exists('can') && (can('admin.access') || can('admin.system') || can('admin.organization'));

$accountNavKey = 'overview';
$accountTitle = 'Mon compte';
$accountLead = 'Identité civile, sécurité, apparence et préférences du portail — le dossier opérationnel (personnage, unité) reste sur votre fiche personnelle.';
require base_path('views/partials/account/shell_open.php');

$chevron = '<svg class="account-hub__action-chevron" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>';
?>

<?php if ($onboardingSteps !== [] && $onboardingPercent < 100): ?>
<section class="account-hub__panel" aria-labelledby="onboarding-heading">
    <div class="account-hub__panel-head">
        <div class="account-hub__progress">
            <div>
                <p class="account-hub__panel-kicker">Intégration</p>
                <h2 id="onboarding-heading" class="account-hub__panel-title">Progression dans la communauté</h2>
                <p class="account-hub__panel-desc">
                    Parcours <?= htmlspecialchars($onboardingPlan, ENT_QUOTES, 'UTF-8') ?> —
                    <span class="account-hub__badge account-hub__badge--off"><?= htmlspecialchars($onboardingStatus, ENT_QUOTES, 'UTF-8') ?></span>
                </p>
                <?php if ($onboardingNudge !== '' && $onboardingNudge !== 'RAS'): ?>
                <p class="account-hub__panel-desc" style="margin-top:.65rem;color:#92400e;font-weight:600"><?= htmlspecialchars($onboardingNudge, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
            </div>
            <div style="text-align:right">
                <p style="margin:0;font-size:1.5rem;font-weight:900;color:#047857"><?= $onboardingCompleted ?>/<?= $onboardingTotal > 0 ? $onboardingTotal : 5 ?></p>
                <p style="margin:.15rem 0 0;font-size:.75rem;font-weight:700;color:#64748b"><?= $onboardingPercent ?> % complété</p>
            </div>
        </div>
        <div class="account-hub__progress-bar" role="progressbar" aria-valuenow="<?= $onboardingPercent ?>" aria-valuemin="0" aria-valuemax="100">
            <div class="account-hub__progress-fill" style="width:<?= max(0, min(100, $onboardingPercent)) ?>%"></div>
        </div>
    </div>
    <div class="account-hub__panel-body">
        <div class="account-hub__step-grid">
            <?php foreach ($onboardingSteps as $step): ?>
                <?php
                $isDone = !empty($step['done']);
                $href = (string) ($step['href'] ?? '#');
                $critical = !empty($step['critical']);
                ?>
            <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" class="account-hub__step<?= $isDone ? ' is-done' : '' ?>">
                <span>
                    <span class="account-hub__step-label"><?= htmlspecialchars((string) ($step['label'] ?? 'Étape'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="account-hub__step-mod"><?= htmlspecialchars((string) ($step['module'] ?? ''), ENT_QUOTES, 'UTF-8') ?><?= $critical ? ' · prioritaire' : '' ?></span>
                </span>
                <span class="account-hub__badge <?= $isDone ? 'account-hub__badge--ok' : 'account-hub__badge--warn' ?>">
                    <?= $isDone ? 'Fait' : 'À faire' ?>
                </span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="account-hub__panel" aria-labelledby="account-overview-heading" style="margin-top:1.25rem">
    <div class="account-hub__panel-head">
        <p class="account-hub__panel-kicker">Aperçu</p>
        <h2 id="account-overview-heading" class="account-hub__panel-title">État de votre dossier</h2>
        <p class="account-hub__panel-desc">Résumé des informations enregistrées. Les modifications se font via les liens de chaque section.</p>
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
                <p class="account-hub__stat-label">Identité portail</p>
                <p class="account-hub__stat-value"><?= htmlspecialchars($displayNameVal !== '' ? $displayNameVal : 'Nom non renseigné', ENT_QUOTES, 'UTF-8') ?></p>
                <p class="account-hub__stat-meta">
                    <?= htmlspecialchars($civilLine !== '' ? $civilLine : 'Prénom et nom à compléter', ENT_QUOTES, 'UTF-8') ?>
                    <?php if ($callsign !== ''): ?><br>Indicatif : <?= htmlspecialchars($callsign, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                </p>
                <p class="account-hub__stat-meta" style="margin-top:.55rem">
                    <a href="<?= htmlspecialchars($prefUrl, ENT_QUOTES, 'UTF-8') ?>" style="font-weight:700;color:#047857;text-decoration:underline;text-underline-offset:2px">Modifier le profil</a>
                </p>
            </div>
            <div class="account-hub__stat">
                <p class="account-hub__stat-label">Locale</p>
                <p class="account-hub__stat-value"><?= htmlspecialchars($tz !== '' ? $tz : 'Europe/Paris', ENT_QUOTES, 'UTF-8') ?></p>
                <p class="account-hub__stat-meta">
                    Langue : <?= htmlspecialchars($langLabel, ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>
            <div class="account-hub__stat">
                <p class="account-hub__stat-label">Liaisons</p>
                <p class="account-hub__stat-value"><?= $steam !== '' ? 'Steam renseigné' : 'Steam non renseigné' ?></p>
                <p class="account-hub__stat-meta">
                    Adresse courte de fiche : <?= $slug !== '' ? 'définie' : 'non définie' ?><br>
                    Dernière connexion : <?= $accountSnapshot['last_login_label'] !== null ? htmlspecialchars((string) $accountSnapshot['last_login_label'], ENT_QUOTES, 'UTF-8') : '—' ?>
                </p>
            </div>
        </div>
    </div>
</section>

<?php
$hubSections = [
    [
        'kicker' => 'Profil',
        'title' => 'Identité et préférences',
        'desc' => 'Ce que le portail affiche de vous, et comment l’interface se comporte.',
        'actions' => [
            [
                'href' => $prefUrl,
                'title' => 'Profil & préférences',
                'desc' => 'Nom affiché, indicatif, identité civile, fuseau, thème et barre latérale.',
                'icon' => '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
            ],
            [
                'href' => $prefUrl . '#notifications-email',
                'title' => 'Notifications par e-mail',
                'desc' => 'Choisir les messages automatiques (sécurité, événements, formations…).',
                'icon' => '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>',
            ],
        ],
    ],
    [
        'kicker' => 'Sécurité',
        'title' => 'Accès au compte',
        'desc' => 'Protégez votre connexion et gardez une adresse de contact à jour.',
        'actions' => [
            [
                'href' => url('account/mail'),
                'title' => 'Adresse e-mail',
                'desc' => 'Adresse de connexion et double vérification par code.',
                'icon' => '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>',
            ],
            [
                'href' => url('account/password'),
                'title' => 'Mot de passe',
                'desc' => 'Changer le secret utilisé pour vous connecter.',
                'icon' => '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>',
            ],
        ],
    ],
    [
        'kicker' => 'Apparence',
        'title' => 'Images du compte',
        'desc' => 'Trois visuels distincts : photo de compte, couverture du menu, portrait opérationnel.',
        'actions' => [
            [
                'href' => url('account/image'),
                'title' => 'Photo de compte',
                'desc' => 'Visible dans la navigation, le forum et les listes de membres.',
                'icon' => '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
            ],
            [
                'href' => url('account/banner'),
                'title' => 'Couverture du menu session',
                'desc' => 'Bandeau affiché en haut de votre menu profil.',
                'icon' => '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
            ],
            [
                'href' => url('account/portrait'),
                'title' => 'Portrait opérateur',
                'desc' => 'Image « in-universe » pour fiches, organigramme et briefings.',
                'icon' => '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>',
            ],
        ],
    ],
    [
        'kicker' => 'Unité',
        'title' => 'Dossier opérationnel & candidatures',
        'desc' => 'Le personnage, l’affectation et l’enrôlement se gèrent hors des réglages civils du compte.',
        'actions' => [
            [
                'href' => url('personnel/me/edit'),
                'title' => 'Modifier ma fiche personnelle',
                'desc' => 'Personnage, unité, matricule, clearance, formations liées au rôle.',
                'icon' => '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>',
            ],
            [
                'href' => url('personnel/me'),
                'title' => 'Voir ma fiche',
                'desc' => 'Aperçu du dossier tel qu’affiché aux membres habilités.',
                'icon' => '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>',
            ],
            [
                'href' => url('account/recruitment-presets'),
                'title' => 'Profils de candidature',
                'desc' => 'Préréglages pour accélérer vos dossiers d’enrôlement.',
                'icon' => '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
            ],
            [
                'href' => url('account/charte-formations'),
                'title' => 'Charte des formations',
                'desc' => 'Lire la charte publiée par votre communauté et confirmer votre prise en compte.',
                'icon' => '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75l2.25 2.25L15 9.75m6 2.25a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
            ],
        ],
    ],
];
?>

<div class="account-hub__stack" style="margin-top:1.25rem">
<?php foreach ($hubSections as $block): ?>
    <section class="account-hub__panel">
        <div class="account-hub__panel-head">
            <p class="account-hub__panel-kicker"><?= htmlspecialchars($block['kicker'], ENT_QUOTES, 'UTF-8') ?></p>
            <h2 class="account-hub__panel-title"><?= htmlspecialchars($block['title'], ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="account-hub__panel-desc"><?= htmlspecialchars($block['desc'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="account-hub__panel-body" style="padding-top:.35rem;padding-bottom:.5rem">
            <ul class="account-hub__action-list">
                <?php foreach ($block['actions'] as $action): ?>
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
<?php endforeach; ?>
</div>

<section class="account-hub__panel" style="margin-top:1.25rem" aria-labelledby="section-health">
    <div class="account-hub__panel-head" style="background:#0f172a;border-bottom:0">
        <p class="account-hub__panel-kicker" style="color:#6ee7b7">Services</p>
        <h2 id="section-health" class="account-hub__panel-title" style="color:#fff">État des services</h2>
        <p class="account-hub__panel-desc" style="color:#94a3b8">Indicateur simplifié pour votre communauté (données et carte tactique).</p>
    </div>
    <div class="account-hub__health">
        <div class="account-hub__health-cell">
            <p class="account-hub__stat-label">Données du portail</p>
            <p class="account-hub__stat-value"><?= !empty($db['ok']) ? 'Disponibles' : 'Indisponibles' ?></p>
            <p class="account-hub__stat-meta"><?= htmlspecialchars((string) ($db['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="account-hub__health-cell">
            <p class="account-hub__stat-label">Carte &amp; outils tactiques</p>
            <p class="account-hub__stat-value"><?= !empty($api['ok']) ? 'Joignable' : 'À vérifier' ?></p>
            <p class="account-hub__stat-meta"><?= htmlspecialchars((string) ($api['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
            <?php if (!$canAtakAdmin && empty($api['ok']) && !empty($db['ok'])): ?>
            <p class="account-hub__stat-meta">Si le problème continue, prévenez une personne administratrice de votre communauté.</p>
            <?php elseif ($canAtakAdmin && empty($api['ok']) && !empty($db['ok'])): ?>
            <p class="account-hub__stat-meta">
                Vous pouvez vérifier les <a href="<?= htmlspecialchars(url('admin/atak-config'), ENT_QUOTES, 'UTF-8') ?>" style="font-weight:700;color:#047857;text-decoration:underline">réglages du serveur cartographique</a>.
            </p>
            <?php endif; ?>
        </div>
    </div>
</section>

<p class="account-hub__footer-note">
    Une question sur votre unité ? Repassez par le <a href="<?= htmlspecialchars(url('dashboard'), ENT_QUOTES, 'UTF-8') ?>">tableau de bord</a> ou contactez votre référent.
</p>

<?php require base_path('views/partials/account/shell_close.php'); ?>
