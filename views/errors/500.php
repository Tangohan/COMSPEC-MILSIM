<!DOCTYPE html>
<html lang="<?= htmlspecialchars(function_exists('html_lang') ? html_lang() : 'fr', ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(__('errors.500_title'), ENT_QUOTES, 'UTF-8') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-900 font-sans flex items-center justify-center min-h-screen">
    <div class="text-center px-6">
        <h1 class="text-4xl font-black text-slate-900 mb-2">500</h1>
        <p class="text-slate-600 mb-6"><?= htmlspecialchars(__('errors.500_body'), ENT_QUOTES, 'UTF-8') ?></p>
        <div class="mb-6 flex justify-center"><?php $localeSwitcherVariant = 'light'; require base_path('views/partials/language_switcher.php'); ?></div>
        <a href="<?= htmlspecialchars(function_exists('url') ? url('') : '/', ENT_QUOTES, 'UTF-8') ?>" class="inline-flex px-5 py-2.5 bg-slate-900 text-white font-semibold rounded hover:bg-slate-800"><?= htmlspecialchars(__('common.home'), ENT_QUOTES, 'UTF-8') ?></a>
    </div>
</body>
</html>
