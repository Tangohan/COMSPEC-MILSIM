<?php
/** @var string $email */
/** @var list<array{tenant_id: int, user_id: int, tenant_name: string, tenant_slug: string}> $candidates */
$error = \App\Core\Session::getFlash('error');
$title = $title ?? 'Communauté';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> — Athena</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-900 antialiased min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <h2 class="text-[11px] font-black tracking-[0.6em] text-slate-400 uppercase mb-2">Multi-tenant</h2>
            <h1 class="text-xl font-black uppercase italic text-slate-900">Choisir une communauté</h1>
            <p class="text-xs text-slate-500 mt-2"><?= htmlspecialchars($email) ?></p>
        </div>
        <?php if ($error): ?>
            <?php $flash_variant = 'error'; $flash_message = $error; require base_path('views/partials/flash_message.php'); ?>
        <?php endif; ?>
        <form method="post" action="<?= url('login/select-community') ?>" class="bg-white border border-slate-200 rounded-3xl p-8 shadow-sm space-y-4">
            <?= \App\Core\Csrf::field() ?>
            <p class="text-sm text-slate-600 mb-4">Vous avez plusieurs accès avec cet e-mail. Sélectionnez la communauté à ouvrir pour cette session.</p>
            <div class="space-y-2 max-h-72 overflow-y-auto">
                <?php foreach ($candidates as $c): ?>
                    <label class="flex items-center gap-3 p-4 rounded-2xl border border-slate-100 hover:border-emerald-400 cursor-pointer has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50/50">
                        <input type="radio" name="tenant_id" value="<?= (int) $c['tenant_id'] ?>" required class="text-emerald-600">
                        <span class="flex-1">
                            <span class="block font-bold text-slate-900"><?= htmlspecialchars($c['tenant_name']) ?></span>
                            <span class="text-xs text-slate-500 font-mono"><?= htmlspecialchars($c['tenant_slug']) ?></span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="w-full py-4 bg-slate-900 text-white text-[11px] font-black uppercase tracking-[0.25em] rounded-2xl hover:bg-emerald-600 transition-colors">
                Continuer
            </button>
        </form>
        <p class="mt-6 text-center text-xs text-slate-500">
            <a href="<?= url('login') ?>" class="text-emerald-700 font-semibold hover:underline">Autre compte</a>
        </p>
        <div class="mt-6 flex flex-wrap justify-center gap-x-3 gap-y-1 text-center text-[10px] text-slate-400 max-w-lg mx-auto px-2">
            <?php
            $legal_link_class = 'font-semibold hover:text-emerald-700';
            require base_path('views/partials/legal_site_links.php');
            ?>
        </div>
    </div>
<?php require base_path('views/partials/cookie_banner.php'); ?>
</body>
</html>
