<?php
$base = url('');
$error = \App\Core\Session::getFlash('error');
$success = \App\Core\Session::getFlash('success');
$title = $title ?? 'Connexion';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> — Athena</title>
    <?php $tailwindBaseUrl = $base; require base_path('views/partials/tailwind_cdn_or_build.php'); ?>
    <script defer src="https://unpkg.com/alpinejs@3/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <?php if (is_file(base_path('public/assets/css/styles.css'))): ?>
    <link href="<?= $base ?>/assets/css/styles.css" rel="stylesheet">
    <?php endif; ?>
    <style>
        body { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
        input::placeholder { color: #cbd5e1; letter-spacing: 0.1em; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 antialiased">

<main class="min-h-screen bg-slate-50 flex items-center justify-center p-6 relative overflow-hidden">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(16,185,129,0.03),transparent_70%)]"></div>
    <div class="absolute top-0 left-0 w-full h-1 bg-slate-900"></div>

    <div class="w-full max-w-md relative z-10" x-data="{ view: 'login' }">

        <div class="text-center mb-12">
            <h2 class="text-[11px] font-black tracking-[0.6em] text-slate-400 uppercase mb-2">Access Control</h2>
            <div class="flex items-center justify-center gap-4">
                <span class="h-[1px] w-8 bg-slate-200"></span>
                <span class="text-2xl font-black italic tracking-tighter uppercase text-slate-900">Forward</span>
                <span class="h-[1px] w-8 bg-slate-200"></span>
            </div>
        </div>

        <?php if ($error): ?>
        <?php $flash_variant = 'error'; $flash_message = $error; require base_path('views/partials/flash_message.php'); ?>
        <?php endif; ?>
        <?php if ($success): ?>
        <?php $flash_variant = 'success'; $flash_message = $success; require base_path('views/partials/flash_message.php'); ?>
        <?php endif; ?>

        <div x-show="view === 'login'" x-transition.opacity.duration.400ms
             class="bg-white border border-slate-200 p-8 md:p-10 shadow-sm rounded-3xl">

            <div class="mb-8">
                <h3 class="text-xl font-black uppercase italic tracking-tight text-slate-900">Connexion</h3>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Opérateur authentifié uniquement</p>
            </div>

            <form method="post" action="<?= url('login') ?>" class="space-y-6">
                <?= \App\Core\Csrf::field() ?>
                <div class="space-y-2">
                    <label for="email" class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 ml-1">Identifiant_ID</label>
                    <input type="email" name="email" id="email" required autocomplete="email" placeholder="NOM.P_00"
                           class="w-full bg-slate-50 border border-slate-100 p-4 rounded-2xl text-xs font-bold tracking-widest focus:outline-none focus:border-emerald-500 transition-colors uppercase">
                </div>

                <div class="space-y-2">
                    <div class="flex justify-between items-center">
                        <label for="password" class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 ml-1">Mot de passe</label>
                        <button type="button" @click.prevent="view = 'forgot'" class="text-[9px] font-black uppercase tracking-[0.2em] text-emerald-600 hover:text-slate-900 transition-colors">Perdu ?</button>
                    </div>
                    <input type="password" name="password" id="password" required autocomplete="current-password" placeholder="••••••••••••"
                           class="w-full bg-slate-50 border border-slate-100 p-4 rounded-2xl text-xs focus:outline-none focus:border-emerald-500 transition-colors">
                </div>

                <button type="submit" class="w-full py-5 bg-slate-900 text-white text-[11px] font-black uppercase tracking-[0.3em] rounded-2xl hover:bg-emerald-600 transition-all hover:translate-y-[-2px] shadow-lg shadow-slate-200">
                    Initialiser Session
                </button>
            </form>
        </div>

        <div x-show="view === 'forgot'" x-transition.opacity.duration.400ms
             class="bg-white border border-slate-200 p-8 md:p-10 shadow-sm rounded-3xl" x-cloak>

            <div class="mb-8">
                <h3 class="text-xl font-black uppercase italic tracking-tight text-slate-900">Récupération</h3>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Procédure de réinitialisation</p>
            </div>

            <p class="text-xs text-slate-500 leading-relaxed mb-8">
                Saisissez votre adresse e-mail opérationnelle. Un lien de réinitialisation à usage unique sera généré.
            </p>

            <form method="post" action="<?= url('forgot-password') ?>" class="space-y-6">
                <?= \App\Core\Csrf::field() ?>
                <div class="space-y-2">
                    <label for="forgot-email" class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 ml-1">Email de secours</label>
                    <input type="email" name="email" id="forgot-email" required autocomplete="email" placeholder="OPERATOR@FORWARD.OBS"
                           class="w-full bg-slate-50 border border-slate-100 p-4 rounded-2xl text-xs font-bold tracking-widest focus:outline-none focus:border-emerald-500 transition-colors">
                </div>

                <button type="submit" class="w-full py-5 bg-emerald-600 text-white text-[11px] font-black uppercase tracking-[0.3em] rounded-2xl hover:bg-slate-900 transition-all hover:translate-y-[-2px]">
                    Envoyer le lien
                </button>

                <button type="button" @click.prevent="view = 'login'" class="w-full text-[9px] font-black uppercase tracking-[0.3em] text-slate-400 hover:text-slate-900 transition-colors">
                    Retour au terminal
                </button>
            </form>
        </div>

        <p class="mt-8 text-center text-sm text-slate-600">
            Pas encore de compte ? <a href="<?= url('register') ?>" class="font-semibold text-emerald-700 hover:underline">Créer un compte</a>
        </p>
        <p class="mt-3 text-center text-[11px] text-slate-500 leading-relaxed max-w-sm mx-auto">
            Pas encore dans une communauté ?
            <a href="<?= url('join') ?>" class="font-semibold text-emerald-700 hover:underline">Rejoindre avec un code</a>
            · après connexion, <a href="<?= url('communities/create') ?>" class="font-semibold text-slate-700 hover:underline">créer une communauté</a>
        </p>

        <div class="mt-12 flex justify-between items-center opacity-30 px-4">
            <span class="text-[8px] font-black tracking-widest uppercase">Encryption: AES-256</span>
            <span class="text-[8px] font-black tracking-widest uppercase">Node: Paris_FR</span>
        </div>
    </div>
</main>

</body>
</html>
