<!DOCTYPE html>
<html lang="<?= htmlspecialchars(function_exists('html_lang') ? html_lang() : 'fr', ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= htmlspecialchars($title ?? __('errors.403_title'), ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        body { font-family: system-ui, sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0; padding: 1rem; }
        .box { max-width: 28rem; text-align: center; }
        h1 { font-size: 1.25rem; margin-bottom: 0.5rem; }
        p { color: #94a3b8; font-size: 0.95rem; }
    </style>
</head>
<body>
    <div class="box">
        <h1><?= htmlspecialchars(__('errors.403_title'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p><?= htmlspecialchars(__('errors.403_body'), ENT_QUOTES, 'UTF-8') ?></p>
        <p style="margin-top:1.5rem"><?php $localeSwitcherVariant = 'dark'; require base_path('views/partials/language_switcher.php'); ?></p>
    </div>
</body>
</html>
