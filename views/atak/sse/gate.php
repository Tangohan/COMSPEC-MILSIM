<?php
declare(strict_types=1);
$title = (string) ($title ?? 'Accès renseignement interpersonnel');
$error = $error ?? \App\Core\Session::getFlash('error');
$success = $success ?? \App\Core\Session::getFlash('success');
$loggedIn = (bool) ($loggedIn ?? false);
$sseTheme = sse_ui_theme_normalize((string) ($sseTheme ?? sse_ui_theme()));
$sseThemeOptions = is_array($sseThemeOptions ?? null) ? $sseThemeOptions : sse_ui_theme_options();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= $h($title) ?></title>
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/sse_portal.css')) ?>?v=202607290118">
</head>
<body class="sse-theme-<?= $h($sseTheme) ?> sse-gate">
<div class="gate-shell">
    <div class="classification-bar gate-class-bar">
        Accès contrôlé // Diffusion restreinte // Personnel habilité uniquement
    </div>

    <div class="gate-card">
        <div class="page-heading-overline" style="margin-bottom:.75rem">Athena // SSE</div>
        <h1>Sas d’accès</h1>
        <p class="lead">
            Choisissez l’apparence, puis saisissez le code temporaire délivré par votre commandement.
            Toute consultation est tracée.
        </p>

        <?php if ($error): ?><div class="flash flash--error"><?= $h($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="flash flash--ok"><?= $h($success) ?></div><?php endif; ?>

        <form method="post" action="<?= $h(url('atak/sse/entrer')) ?>" id="sse-gate-form">
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

            <label for="access_code">Code d’accès</label>
            <input id="access_code" name="access_code" type="text" required autocomplete="off"
                   maxlength="16" placeholder="········" autofocus
                   class="gate-code-input">
            <?php if (!$loggedIn): ?>
                <label for="guest_name">Votre indicatif (invité)</label>
                <input id="guest_name" name="guest_name" type="text" maxlength="80" placeholder="Ex. Opérateur terrain">
            <?php endif; ?>
            <button class="btn" type="submit">Entrer</button>
        </form>

        <p class="gate-hint">
            <?php if ($loggedIn): ?>
                Compte connecté · le code membre confirme votre habilitation.
            <?php else: ?>
                Sans compte Athena, un code invité suffit.
                <a href="<?= $h(url('login')) ?>">Se connecter</a>
            <?php endif; ?>
        </p>
    </div>

    <div class="classification-bar classification-bar--bottom gate-class-bar">
        Accès contrôlé // Diffusion restreinte // Personnel habilité uniquement
    </div>
</div>
<script>
(function () {
  var form = document.getElementById('sse-gate-form');
  var hidden = document.getElementById('sse-ui-theme');
  if (!form || !hidden) return;
  form.querySelectorAll('[data-sse-theme]').forEach(function (input) {
    input.addEventListener('change', function () {
      var theme = input.getAttribute('data-sse-theme') || 'archive';
      hidden.value = theme;
      document.body.className = 'sse-theme-' + theme + ' sse-gate';
      form.querySelectorAll('.theme-card').forEach(function (card) {
        card.classList.toggle('is-selected', card.contains(input) && input.checked);
      });
      try {
        document.cookie = 'sse_ui_theme=' + encodeURIComponent(theme)
          + ';path=/;max-age=31536000;SameSite=Lax';
      } catch (e) {}
    });
  });
})();
</script>
</body>
</html>
