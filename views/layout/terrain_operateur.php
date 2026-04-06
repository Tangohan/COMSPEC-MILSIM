<?php
$title = $title ?? 'Terrain';
$base = url('');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= htmlspecialchars($title) ?></title>
    <?php $tailwindBaseUrl = $base; require base_path('views/partials/tailwind_cdn_or_build.php'); ?>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
    <header class="sticky top-0 z-10 border-b border-white/10 bg-slate-950/90 backdrop-blur px-4 py-3">
        <div class="mx-auto flex max-w-lg items-center justify-between gap-3">
            <a href="<?= htmlspecialchars(url('dashboard')) ?>" class="text-[10px] font-black uppercase tracking-widest text-emerald-400">Retour</a>
            <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Opérateur</span>
            <span class="w-12" aria-hidden="true"></span>
        </div>
    </header>
    <main class="mx-auto max-w-lg px-4 py-6">
        <?php
        $contentView = $content ?? null;
        if (is_string($contentView) && $contentView !== '') {
            require base_path('views/' . $contentView . '.php');
        }
        ?>
    </main>
</body>
</html>
