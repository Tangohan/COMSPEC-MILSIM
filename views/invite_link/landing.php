<?php
declare(strict_types=1);

/**
 * Page publique d'un lien d'invitation (/i/<code>) : vitrine de la communauté puis l'action proposée au visiteur.
 *
 * @var array<string, mixed>|null $community name, slug, logo, banner, game, tagline, members, public_url
 * @var string|null $unusableMessage lien inconnu, expiré, épuisé ou révoqué
 * @var array<string, mixed> $link
 * @var bool $isApply
 * @var bool $loggedIn
 * @var string|null $memberState member | pending | null
 */
$base = url('');
$title = $title ?? 'Invitation';
$active = '';
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$community = is_array($community ?? null) ? $community : null;
$unusableMessage = isset($unusableMessage) ? (string) $unusableMessage : null;
$link = is_array($link ?? null) ? $link : [];
$isApply = (bool) ($isApply ?? true);
$loggedIn = (bool) ($loggedIn ?? false);
$memberState = $memberState ?? null;
$requiresApproval = !empty($link['requires_approval']);
$name = $community !== null ? (string) $community['name'] : '';
$initial = $name !== '' ? mb_strtoupper(mb_substr($name, 0, 1)) : 'A';
$flashError = \App\Core\Session::getFlash('error');
$flashSuccess = \App\Core\Session::getFlash('success');
$publicUrl = \App\Services\Recruitment\InviteLinkService::publicUrl((string) ($link['public_code'] ?? ''));
$expiresTs = !empty($link['expires_at']) ? strtotime((string) $link['expires_at']) : false;
$usesLeft = isset($link['max_uses']) ? max(0, (int) $link['max_uses'] - (int) ($link['uses_count'] ?? 0)) : null;
$bannerStyle = $community !== null && $community['banner'] !== '' ? ' style="background-image:url(\'' . $h((string) $community['banner']) . '\')"' : '';
// Étape en cours du parcours : 1 = compte à créer, 2 = candidature ou adhésion.
$step = $loggedIn ? 2 : 1;
?>
<!DOCTYPE html>
<html lang="<?= $h(html_lang()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= $h($title) ?> — Athena</title>
    <meta name="robots" content="noindex,nofollow">
    <?php $tailwindBaseUrl = $base; require base_path('views/partials/tailwind_cdn_or_build.php'); ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,600;0,700;0,800;0,900;1,900&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link href="<?= $h(asset_url('assets/css/styles.css')) ?>" rel="stylesheet">
    <link href="<?= $h(asset_url('assets/css/home-impact.css')) ?>" rel="stylesheet">
    <link href="<?= $h(asset_url('assets/css/public-site-footer.css')) ?>" rel="stylesheet">
    <link href="<?= $h(asset_url('assets/css/invite-links-ath.css')) ?>" rel="stylesheet">
</head>
<body class="home-impact layout-light ilx-page ilx-body antialiased overflow-x-hidden">
<?php
// Même barre que la page d'accueil ; connexion et inscription gardent l'invitation.
$homeTopbarLoginUrl = isset($loginUrl) && $loginUrl !== '' ? (string) $loginUrl : null;
$homeTopbarRegisterUrl = isset($registerUrl) && $registerUrl !== '' ? (string) $registerUrl : null;
require base_path('views/partials/home_topbar.php');
?>

<main id="contenu" class="ilx-hero" tabindex="-1">
    <div class="ilx-hero__ph"<?= $bannerStyle ?>></div>
    <div class="ilx-wrap">
        <div>
            <p class="ilx-kicker"><i></i><?= $unusableMessage === null ? 'Vous êtes invité' : 'Lien d’invitation' ?></p>
            <div class="ilx-unit">
                <div class="ilx-crest" aria-hidden="true">
                    <?php if ($community !== null && $community['logo'] !== ''): ?>
                        <img src="<?= $h((string) $community['logo']) ?>" alt="">
                    <?php else: ?>
                        <span translate="no"><?= $h($initial) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($unusableMessage !== null): ?>
                    <p class="ilx-from">Ce lien ne peut plus être utilisé.</p>
                <?php elseif ($isApply): ?>
                    <p class="ilx-from">Vous êtes invité à candidater dans cette communauté sur Athena.</p>
                <?php else: ?>
                    <p class="ilx-from">Vous êtes invité à rejoindre cette communauté sur Athena.</p>
                <?php endif; ?>
            </div>
            <?php if ($community !== null): ?>
                <h1 class="ilx-title"><span translate="no" style="color:inherit"><?= $h($name) ?></span><span>.</span></h1>
                <?php if ($community['tagline'] !== ''): ?>
                    <p class="ilx-desc" translate="no"><?= $h((string) $community['tagline']) ?></p>
                <?php endif; ?>
                <div class="ilx-facts">
                    <?php if ((int) $community['members'] > 0): ?>
                        <div><p class="ilx-facts__k">Effectif</p><p class="ilx-facts__v"><?= (int) $community['members'] ?><small><?= (int) $community['members'] > 1 ? 'membres' : 'membre' ?></small></p></div>
                    <?php endif; ?>
                    <?php if ($unusableMessage === null): ?>
                        <div><p class="ilx-facts__k">Accès</p><p class="ilx-facts__v"><span class="ilx-dot"></span><?= $isApply ? 'Candidature' : 'Adhésion' ?></p></div>
                    <?php endif; ?>
                    <?php if ($community['game'] !== ''): ?>
                        <div><p class="ilx-facts__k">Jeu</p><p class="ilx-facts__v" translate="no"><?= $h((string) $community['game']) ?></p></div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <h1 class="ilx-title">Invitation introuvable</h1>
            <?php endif; ?>
        </div>

        <aside class="ilx-ticket">
            <div class="ilx-tk-h">
                <div style="min-width:0;">
                    <p class="ilx-tk-h__k">Votre accès</p>
                    <?php if ($unusableMessage !== null): ?>
                        <p class="ilx-tk-h__t">Lien indisponible</p>
                    <?php elseif ($isApply): ?>
                        <p class="ilx-tk-h__t">Candidater</p>
                    <?php else: ?>
                        <p class="ilx-tk-h__t">Rejoindre la communauté</p>
                    <?php endif; ?>
                </div>
                <?php if ($unusableMessage === null && $publicUrl !== ''): ?>
                    <button type="button" class="ilx-code" title="Copier le lien" data-ilx-copy="<?= $h($publicUrl) ?>" translate="no"><?= $h((string) ($link['public_code'] ?? '')) ?></button>
                <?php endif; ?>
            </div>
            <div class="ilx-perf"></div>

            <?php if ($unusableMessage === null && $memberState === null): ?>
            <ol class="ilx-steps">
                <?php if ($loggedIn): ?>
                    <li><span class="ilx-steps__n">01</span><div><b>Compte Athena prêt</b><p>Vous êtes connecté : l’invitation s’applique à votre compte.</p></div></li>
                <?php else: ?>
                    <li class="is-on"><span class="ilx-steps__n">01</span><div><b>Créez votre compte Athena</b><p>Deux minutes. Votre invitation reste attachée et vous ramène ici.</p></div></li>
                <?php endif; ?>
                <?php if ($isApply): ?>
                    <li class="<?= $step === 2 ? 'is-on' : '' ?>"><span class="ilx-steps__n">02</span><div><b>Déposez votre candidature</b><p>Le formulaire de la communauté est prêt, rattaché à cette invitation.</p></div></li>
                    <li><span class="ilx-steps__n">03</span><div><b>Réponse de l’équipe</b><p>L’équipe de recrutement étudie votre dossier et vous recontacte.</p></div></li>
                <?php else: ?>
                    <li class="<?= $step === 2 ? 'is-on' : '' ?>"><span class="ilx-steps__n">02</span><div><b>Rejoignez la communauté</b>
                        <?php if ($requiresApproval): ?><p>L’équipe valide votre demande avant votre arrivée.</p><?php else: ?><p>Vous devenez membre tout de suite.</p><?php endif; ?>
                    </div></li>
                    <li><span class="ilx-steps__n">03</span><div><b>Bienvenue à bord</b><p>Votre espace s’ouvre : opérations, formation et organisation au même endroit.</p></div></li>
                <?php endif; ?>
            </ol>
            <?php endif; ?>

            <div class="ilx-cta"<?= $unusableMessage !== null || $memberState !== null ? ' style="padding-top:18px;"' : '' ?>>
                <?php if ($flashError): ?>
                    <p class="ilx-note ilx-note--error" role="alert"><?= $h((string) $flashError) ?></p>
                <?php endif; ?>
                <?php if ($flashSuccess): ?>
                    <p class="ilx-note" role="status"><?= $h((string) $flashSuccess) ?></p>
                <?php endif; ?>

                <?php if ($unusableMessage !== null): ?>
                    <p class="ilx-note ilx-note--warn" role="alert"><?= $h($unusableMessage) ?></p>
                    <?php if ($community !== null && $community['public_url'] !== ''): ?>
                        <a class="ilx-btn ilx-btn--solid" href="<?= $h((string) $community['public_url']) ?>">Voir la page de la communauté</a>
                    <?php endif; ?>
                    <a class="ilx-btn" href="<?= $h(url('')) ?>">Retour à l’accueil</a>
                <?php elseif ($memberState === 'member'): ?>
                    <p class="ilx-note" role="status">Vous faites déjà partie de cette communauté.</p>
                    <a class="ilx-btn ilx-btn--solid" href="<?= $h(url('dashboard')) ?>">Ouvrir mon espace</a>
                <?php elseif ($memberState === 'pending'): ?>
                    <p class="ilx-note" role="status">Votre demande est déjà entre les mains de l’équipe de la communauté. Vous serez prévenu de sa réponse.</p>
                    <a class="ilx-btn" href="<?= $h(url('dashboard')) ?>">Ouvrir mon espace</a>
                <?php elseif ($loggedIn): ?>
                    <?php if ($isApply): ?>
                        <a class="ilx-btn ilx-btn--solid" href="<?= $h((string) $formUrl) ?>">Candidater</a>
                    <?php else: ?>
                        <form method="post" action="<?= $h((string) $actionUrl) ?>">
                            <?= \App\Core\Csrf::field() ?>
                            <button type="submit" class="ilx-btn ilx-btn--solid"><?= $requiresApproval ? 'Demander à rejoindre' : 'Rejoindre la communauté' ?></button>
                        </form>
                        <p class="ilx-fine">Un compte ne fait partie que d’une seule communauté à la fois.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <a class="ilx-btn ilx-btn--solid" href="<?= $h((string) $registerUrl) ?>">Créer mon compte</a>
                    <a class="ilx-btn" href="<?= $h((string) $loginUrl) ?>">J’ai déjà un compte</a>
                    <?php if ($isApply): ?>
                        <p class="ilx-alt"><a href="<?= $h((string) $formUrl) ?>">Candidater sans créer de compte</a></p>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($community !== null && $community['public_url'] !== '' && $unusableMessage === null): ?>
                    <p class="ilx-alt"><a href="<?= $h((string) $community['public_url']) ?>">Découvrir la communauté</a></p>
                <?php endif; ?>
            </div>

            <?php if ($unusableMessage === null): ?>
            <div class="ilx-tk-f">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg>
                <?php if ($expiresTs): ?>
                    <span>Valable jusqu’au <?= $h(date('d/m/Y', $expiresTs)) ?></span>
                <?php else: ?>
                    <span>Sans date d’expiration</span>
                <?php endif; ?>
                <?php if ($usesLeft !== null): ?>
                    <span aria-hidden="true">·</span>
                    <?php if ($usesLeft === 1): ?><span>1 utilisation restante</span><?php else: ?><span><?= $usesLeft ?> utilisations restantes</span><?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </aside>
    </div>
</main>
<p class="ilx-toast" role="status" aria-live="polite" data-ilx-toast>Lien copié</p>

<?php require base_path('views/partials/public_site_footer.php'); ?>
<?php require base_path('views/partials/cookie_banner.php'); ?>
<script>
(function () {
    var btn = document.querySelector('[data-ilx-copy]');
    var toast = document.querySelector('[data-ilx-toast]');
    if (!btn || !toast) { return; }
    btn.addEventListener('click', function () {
        var done = function () {
            toast.classList.add('is-on');
            setTimeout(function () { toast.classList.remove('is-on'); }, 1800);
        };
        if (navigator.clipboard) { navigator.clipboard.writeText(btn.getAttribute('data-ilx-copy')).then(done, done); } else { done(); }
    });
})();
</script>
</body>
</html>
