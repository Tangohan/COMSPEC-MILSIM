<?php
declare(strict_types=1);
$title = (string) ($title ?? 'Accès renseignement interpersonnel');
$error = $error ?? \App\Core\Session::getFlash('error');
$success = $success ?? \App\Core\Session::getFlash('success');
$loggedIn = (bool) ($loggedIn ?? false);
$sseTheme = sse_ui_theme_normalize((string) ($sseTheme ?? sse_ui_theme()));
$sseThemeOptions = is_array($sseThemeOptions ?? null) ? $sseThemeOptions : sse_ui_theme_options();
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
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@75,400;75,600;75,700;75,800;75,900&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/sse_portal.css')) ?>?v=202608061000">
</head>
<body class="sse-theme-<?= $h($sseTheme) ?> sse-gate">
<div class="sse-gate-hub" id="sse-gate-hub">
    <div class="sse-gate-hub__stage">
        <aside class="sse-gate-hub__visual" aria-hidden="true">
            <div class="sse-gate-hub__visual-glow"></div>
            <div class="sse-gate-hub__eagle">
                <img
                    class="sse-gate-hub__eagle-img"
                    src="<?= $h($eagleSrc) ?>"
                    alt=""
                    width="512"
                    height="512"
                    decoding="async"
                >
                <span class="sse-gate-hub__eagle-sweep"></span>
            </div>
            <div class="sse-gate-hub__brand">
                <span class="sse-gate-hub__brand-main">ATHENA</span>
                <span class="sse-gate-hub__brand-sub">SSE</span>
            </div>
            <p class="sse-gate-hub__tagline">
                Renseignement interpersonnel · compartiment classifié
            </p>
        </aside>

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
                    ? 'Votre profil est reconnu. Saisissez le code temporaire délivré par le commandement pour ouvrir le compartiment, ou changez l’apparence ci-dessous.'
                    : 'Entrez le code temporaire délivré par votre commandement pour ouvrir le compartiment. Sans compte Athena, indiquez votre indicatif.' ?>
            </p>

            <?php if ($error): ?><div class="flash flash--error"><?= $h($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="flash flash--ok"><?= $h($success) ?></div><?php endif; ?>

            <form method="post" action="<?= $h(url('atak/sse/entrer')) ?>" id="sse-gate-form" class="sse-gate-hub__form">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="ui_theme" id="sse-ui-theme" value="<?= $h($sseTheme) ?>">

                <fieldset class="theme-picker">
                    <legend>Apparence de l’espace</legend>
                    <div class="theme-picker-grid">
                        <?php foreach ($sseThemeOptions as $key => $opt): ?>
                            <label class="theme-card <?= $sseTheme === $key ? 'is-selected' : '' ?>">
                                <input type="radio" name="theme_preview" value="<?= $h($key) ?>"
                                       <?= $sseTheme === $key ? 'checked' : '' ?>
                                       data-sse-theme="<?= $h($key) ?>">
                                <span class="theme-card-preview theme-card-preview--<?= $h($key) ?>" aria-hidden="true"></span>
                                <span class="theme-card-copy">
                                    <strong><?= $h($opt['label']) ?></strong>
                                    <small><?= $h($opt['hint']) ?></small>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <label class="sse-gate-hub__label" for="access_code">Code d’accès</label>
                <input id="access_code" name="access_code" type="text" required autocomplete="off"
                       maxlength="16" placeholder="········" autofocus
                       class="gate-code-input sse-gate-hub__code">

                <?php if (!$loggedIn): ?>
                    <label class="sse-gate-hub__label" for="guest_name">Votre indicatif</label>
                    <input id="guest_name" name="guest_name" type="text" maxlength="80"
                           placeholder="Ex. Opérateur terrain" class="sse-gate-hub__input">
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
    </div>
</div>
<script>
(function () {
  var form = document.getElementById('sse-gate-form');
  var hidden = document.getElementById('sse-ui-theme');
  var hub = document.getElementById('sse-gate-hub');
  if (!form || !hidden) return;
  form.querySelectorAll('[data-sse-theme]').forEach(function (input) {
    input.addEventListener('change', function () {
      var theme = input.getAttribute('data-sse-theme') || 'console';
      hidden.value = theme;
      document.body.className = 'sse-theme-' + theme + ' sse-gate';
      if (hub) {
        hub.classList.toggle('sse-gate-hub--archive', theme === 'archive');
        hub.classList.toggle('sse-gate-hub--console', theme === 'console');
      }
      form.querySelectorAll('.theme-card').forEach(function (card) {
        card.classList.toggle('is-selected', card.contains(input) && input.checked);
      });
      try {
        document.cookie = 'sse_ui_theme=' + encodeURIComponent(theme)
          + ';path=/;max-age=31536000;SameSite=Lax';
      } catch (e) {}
    });
  });
  if (hub) {
    hub.classList.add(sseThemeClass());
  }
  function sseThemeClass() {
    return hidden.value === 'archive' ? 'sse-gate-hub--archive' : 'sse-gate-hub--console';
  }
})();
</script>
</body>
</html>
