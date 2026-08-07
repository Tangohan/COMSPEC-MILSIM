<?php
declare(strict_types=1);
$title = (string) ($title ?? 'Accès renseignement interpersonnel');
$error = $error ?? \App\Core\Session::getFlash('error');
$success = $success ?? \App\Core\Session::getFlash('success');
$loggedIn = (bool) ($loggedIn ?? false);
$sseTheme = sse_ui_theme_normalize((string) ($sseTheme ?? sse_ui_theme()));
$operatorName = (string) ($operatorName ?? 'Opérateur');
$operatorMeta = (string) ($operatorMeta ?? 'Session SSE');
$operatorInitial = (string) ($operatorInitial ?? 'O');
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$eagleSrc = asset_url('assets/img/atak-eagle-logo.png');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= $h($title) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/sse_portal.css')) ?>?v=202608062340">
</head>
<body class="sse-theme-bureau sse-gate">
<div class="sse-gate-hub sse-gate-hub--bureau" id="sse-gate-hub">
    <div class="sse-gate-hub__stage">
        <section class="sse-gate-hub__panel">
            <div class="sse-gate-hub__identity">
                <span class="sse-gate-hub__avatar" aria-hidden="true"><?= $h($operatorInitial) ?></span>
                <div class="sse-gate-hub__identity-text">
                    <strong><?= $h($operatorName) ?></strong>
                    <span><?= $h($operatorMeta) ?></span>
                </div>
            </div>

            <p class="sse-gate-hub__kicker">Session</p>
            <h1 class="sse-gate-hub__title">Accès SSE</h1>
            <p class="sse-gate-hub__lead">
                <?= $loggedIn
                    ? 'Votre profil est reconnu. Saisissez le code temporaire délivré par le commandement pour ouvrir le compartiment.'
                    : 'Entrez le code temporaire délivré par votre commandement pour ouvrir le compartiment. Sans compte Athena, indiquez votre indicatif.' ?>
            </p>

            <?php if ($error): ?>
                <div class="sse-gate-hub__alert sse-gate-hub__alert--error" role="alert"><?= $h($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="sse-gate-hub__alert sse-gate-hub__alert--ok" role="status"><?= $h($success) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= $h(url('atak/sse/entrer')) ?>" id="sse-gate-form" class="sse-gate-hub__form">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="ui_theme" id="sse-ui-theme" value="<?= $h($sseTheme) ?>">

                <label class="sse-gate-hub__label" for="access_code">Code d’accès</label>
                <input id="access_code" name="access_code" type="text" required autocomplete="off"
                       maxlength="16" placeholder="Saisir le code reçu"
                       spellcheck="false" autocapitalize="characters"
                       class="gate-code-input sse-gate-hub__code" autofocus>

                <?php if (!$loggedIn): ?>
                    <label class="sse-gate-hub__label" for="guest_name">Votre indicatif</label>
                    <input id="guest_name" name="guest_name" type="text" maxlength="80"
                           placeholder="Ex. Opérateur terrain" class="sse-gate-hub__input"
                           autocomplete="nickname">
                <?php endif; ?>

                <button class="sse-gate-hub__btn" type="submit">Entrer dans la session</button>
            </form>

            <p class="sse-gate-hub__hint">
                <?php if ($loggedIn): ?>
                    Compte connecté · le code membre confirme votre habilitation.
                <?php else: ?>
                    Sans compte Athena, un code invité suffit.
                    <a href="<?= $h(url('login')) ?>">Se connecter</a>
                <?php endif; ?>
            </p>

            <p class="sse-gate-hub__legal">
                Accès contrôlé · diffusion restreinte · journalisation active
            </p>
        </section>

        <aside class="sse-gate-hub__visual" aria-hidden="true">
            <div class="sse-gate-hub__visual-glow"></div>
            <div class="sse-gate-hub__gridlines"></div>
            <div class="sse-gate-hub__eagle">
                <img
                    class="sse-gate-hub__eagle-img"
                    src="<?= $h($eagleSrc) ?>"
                    alt=""
                    width="512"
                    height="512"
                    decoding="async"
                    onerror="this.classList.add('is-missing'); this.nextElementSibling?.classList.add('is-visible');"
                >
                <span class="sse-gate-hub__mark" aria-hidden="true">SSE</span>
                <span class="sse-gate-hub__eagle-sweep"></span>
            </div>
            <div class="sse-gate-hub__brand">
                <span class="sse-gate-hub__brand-main">ATHENA</span>
                <span class="sse-gate-hub__brand-sub">SSE</span>
            </div>
            <p class="sse-gate-hub__tagline">
                Renseignement interpersonnel · bureau de travail
            </p>
        </aside>
    </div>
</div>
</body>
</html>
