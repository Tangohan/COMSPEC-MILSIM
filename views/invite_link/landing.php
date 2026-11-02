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
?>
<!DOCTYPE html>
<html lang="fr" class="public-portal-day">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $h($title) ?> — Athena</title>
    <meta name="robots" content="noindex,nofollow">
    <?php $tailwindBaseUrl = $base; require base_path('views/partials/tailwind_cdn_or_build.php'); ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link href="<?= $h($base) ?>/assets/css/public-portal-day.css" rel="stylesheet">
    <style>
        :root { --il-ink: #0f172a; --il-muted: #475569; --il-line: #e2e8f0; --il-card: #ffffff; --il-accent: #059669; --il-accent-ink: #ffffff; --il-soft: #ecfdf5; --il-warn: #b45309; --il-warn-soft: #fffbeb; --il-bg: #f8fafc; }
        body.public-portal-day { font-family: Inter, system-ui, sans-serif; background: var(--il-bg); color: var(--il-ink); }
        .il-wrap, .il-wrap * { box-sizing: border-box; }
        .il-wrap { width: 100%; max-width: 34rem; margin: 0 auto; padding: 2.5rem 16px 3rem; }
        .il-card { background: var(--il-card); border: 1px solid var(--il-line); border-radius: 1.5rem; overflow: hidden; box-shadow: 0 20px 50px -12px rgba(15, 23, 42, .12); }
        .il-banner { height: 9rem; background: linear-gradient(135deg, #0f172a, #065f46); background-size: cover; background-position: center; }
        .il-head { padding: 0 1.5rem 1.25rem; margin-top: -2.5rem; text-align: center; }
        .il-logo { width: 5rem; height: 5rem; margin: 0 auto; border-radius: 1.25rem; border: 4px solid var(--il-card); background: var(--il-ink); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 900; overflow: hidden; }
        .il-logo img { width: 100%; height: 100%; object-fit: cover; }
        .il-kicker { margin-top: .9rem; font-size: 11px; font-weight: 900; letter-spacing: .3em; text-transform: uppercase; color: var(--il-accent); }
        .il-name { margin-top: .35rem; font-size: 1.5rem; font-weight: 900; line-height: 1.2; overflow-wrap: anywhere; }
        .il-tagline { margin-top: .5rem; font-size: .9rem; color: var(--il-muted); line-height: 1.5; }
        .il-facts { display: flex; flex-wrap: wrap; justify-content: center; gap: .5rem; margin-top: .9rem; }
        .il-fact { font-size: .75rem; font-weight: 700; color: var(--il-muted); background: var(--il-bg); border: 1px solid var(--il-line); border-radius: 999px; padding: .3rem .75rem; }
        .il-body { border-top: 1px solid var(--il-line); padding: 1.5rem; display: flex; flex-direction: column; gap: .75rem; }
        .il-lead { font-size: .9rem; color: var(--il-muted); line-height: 1.55; text-align: center; }
        .il-btn { display: block; width: 100%; text-align: center; border-radius: 1rem; padding: .95rem 1rem; font-size: .8rem; font-weight: 900; letter-spacing: .18em; text-transform: uppercase; text-decoration: none; border: 2px solid var(--il-ink); background: var(--il-card); color: var(--il-ink); cursor: pointer; font-family: inherit; }
        .il-btn--solid { background: var(--il-accent); border-color: var(--il-accent); color: var(--il-accent-ink); }
        .il-btn:hover { filter: brightness(.95); }
        .il-btn:focus-visible { outline: 3px solid rgba(5, 150, 105, .35); outline-offset: 2px; }
        .il-link { text-align: center; font-size: .82rem; color: var(--il-muted); }
        .il-link a { color: var(--il-ink); font-weight: 700; }
        .il-note { border-radius: 1rem; padding: .85rem 1rem; font-size: .88rem; line-height: 1.5; background: var(--il-soft); color: #064e3b; }
        .il-note--warn { background: var(--il-warn-soft); color: #78350f; }
        .il-note--error { background: #fef2f2; color: #7f1d1d; }
        .il-fine { font-size: .75rem; color: var(--il-muted); text-align: center; line-height: 1.5; }
    </style>
</head>
<body class="public-portal-day min-h-screen antialiased flex flex-col">
<?php require base_path('views/partials/public_portal_auth_frame.php'); ?>

<main class="il-wrap">
    <div class="il-card">
        <div class="il-banner"<?php if ($community !== null && $community['banner'] !== ''): ?> style="background-image:url('<?= $h((string) $community['banner']) ?>')"<?php endif; ?>></div>
        <div class="il-head">
            <div class="il-logo" aria-hidden="true">
                <?php if ($community !== null && $community['logo'] !== ''): ?>
                    <img src="<?= $h((string) $community['logo']) ?>" alt="">
                <?php else: ?>
                    <span translate="no"><?= $h($initial) ?></span>
                <?php endif; ?>
            </div>
            <p class="il-kicker"><?= $unusableMessage === null ? 'Vous êtes invité' : 'Lien d’invitation' ?></p>
            <?php if ($community !== null): ?>
                <h1 class="il-name" translate="no"><?= $h($name) ?></h1>
                <?php if ($community['tagline'] !== ''): ?>
                    <p class="il-tagline" translate="no"><?= $h((string) $community['tagline']) ?></p>
                <?php endif; ?>
                <div class="il-facts">
                    <?php if ($community['game'] !== ''): ?>
                        <span class="il-fact" translate="no"><?= $h((string) $community['game']) ?></span>
                    <?php endif; ?>
                    <?php if ((int) $community['members'] > 0): ?>
                        <span class="il-fact"><?= (int) $community['members'] ?> <?= (int) $community['members'] > 1 ? 'membres' : 'membre' ?></span>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <h1 class="il-name">Invitation introuvable</h1>
            <?php endif; ?>
        </div>

        <div class="il-body">
            <?php if ($flashError): ?>
                <p class="il-note il-note--error" role="alert"><?= $h((string) $flashError) ?></p>
            <?php endif; ?>
            <?php if ($flashSuccess): ?>
                <p class="il-note" role="status"><?= $h((string) $flashSuccess) ?></p>
            <?php endif; ?>

            <?php if ($unusableMessage !== null): ?>
                <p class="il-note il-note--warn" role="alert"><?= $h($unusableMessage) ?></p>
                <?php if ($community !== null && $community['public_url'] !== ''): ?>
                    <a class="il-btn" href="<?= $h((string) $community['public_url']) ?>">Voir la page de la communauté</a>
                <?php endif; ?>
                <a class="il-btn" href="<?= $h(url('')) ?>">Retour à l’accueil</a>
            <?php elseif ($memberState === 'member'): ?>
                <p class="il-note" role="status">Vous faites déjà partie de cette communauté.</p>
                <a class="il-btn il-btn--solid" href="<?= $h(url('dashboard')) ?>">Ouvrir mon espace</a>
            <?php elseif ($memberState === 'pending'): ?>
                <p class="il-note" role="status">Votre demande est déjà entre les mains de l’équipe de la communauté. Vous serez prévenu de sa réponse.</p>
                <a class="il-btn" href="<?= $h(url('dashboard')) ?>">Ouvrir mon espace</a>
            <?php elseif ($loggedIn): ?>
                <?php if ($isApply): ?>
                    <p class="il-lead">La communauté vous invite à déposer votre candidature. Le formulaire est prêt, rattaché à cette invitation.</p>
                    <a class="il-btn il-btn--solid" href="<?= $h((string) $formUrl) ?>">Candidater</a>
                <?php else: ?>
                    <p class="il-lead"><?= $requiresApproval
                        ? 'Votre demande sera examinée par l’équipe de la communauté avant votre arrivée.'
                        : 'Vous rejoindrez la communauté tout de suite, avec votre compte actuel.' ?></p>
                    <form method="post" action="<?= $h((string) $actionUrl) ?>">
                        <?= \App\Core\Csrf::field() ?>
                        <button type="submit" class="il-btn il-btn--solid"><?= $requiresApproval ? 'Demander à rejoindre' : 'Rejoindre la communauté' ?></button>
                    </form>
                    <p class="il-fine">Un compte ne fait partie que d’une seule communauté à la fois.</p>
                <?php endif; ?>
            <?php else: ?>
                <p class="il-lead"><?= $isApply
                    ? 'Créez votre compte Athena pour candidater : votre invitation est gardée pendant l’inscription et vous ramène ici.'
                    : 'Créez votre compte Athena pour rejoindre la communauté : votre invitation est gardée pendant l’inscription et vous ramène ici.' ?></p>
                <a class="il-btn il-btn--solid" href="<?= $h((string) $registerUrl) ?>">Créer mon compte</a>
                <a class="il-btn" href="<?= $h((string) $loginUrl) ?>">J’ai déjà un compte</a>
                <?php if ($isApply): ?>
                    <p class="il-link"><a href="<?= $h((string) $formUrl) ?>">Candidater sans créer de compte</a></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($community !== null && $community['public_url'] !== '' && $unusableMessage === null): ?>
        <p class="il-link" style="margin-top:1.25rem"><a href="<?= $h((string) $community['public_url']) ?>">Découvrir la communauté</a></p>
    <?php endif; ?>
</main>

<?php require base_path('views/partials/public_portal_auth_footer.php'); ?>
</body>
</html>
