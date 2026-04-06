<?php
$base = url('');
$title = $title ?? 'Rejoindre';
$active = 'join';
?>
<!DOCTYPE html>
<html lang="fr" class="public-portal-day">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title) ?> — Athena</title>
    <?php $tailwindBaseUrl = $base; require base_path('views/partials/tailwind_cdn_or_build.php'); ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <?php if (is_file(base_path('public/assets/css/styles.css'))): ?>
    <link href="<?= htmlspecialchars($base) ?>/assets/css/styles.css" rel="stylesheet">
    <?php endif; ?>
    <link href="<?= htmlspecialchars($base) ?>/assets/css/public-portal-day.css" rel="stylesheet">
    <style>
        body.public-portal-day { font-family: Inter, system-ui, sans-serif; }
    </style>
</head>
<body class="public-portal-day min-h-screen bg-white text-slate-900 antialiased flex flex-col">

<div class="absolute top-0 left-0 w-full h-1 bg-emerald-600 z-30" aria-hidden="true"></div>
<div class="pointer-events-none fixed inset-0 -z-10 bg-[radial-gradient(ellipse_100%_60%_at_50%_-10%,rgba(16,185,129,0.08),transparent_50%)]" aria-hidden="true"></div>

<?php require base_path('views/partials/public_portal_auth_frame.php'); ?>

<main class="relative z-10 flex-1 w-full px-4 py-12 sm:py-16 flex flex-col items-center justify-center">
    <div class="w-full max-w-lg">

        <div class="text-center mb-10">
            <p class="text-[11px] font-black tracking-[0.45em] text-emerald-700/80 uppercase mb-3">Accès communauté</p>
            <div class="flex items-center justify-center gap-4 mb-4">
                <span class="h-px w-10 bg-slate-200"></span>
                <span class="text-2xl font-black italic tracking-tight uppercase text-slate-900">Forward</span>
                <span class="h-px w-10 bg-slate-200"></span>
            </div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Rejoindre avec un code</h1>
            <p class="mt-3 text-sm text-slate-700 leading-relaxed">
                Saisissez le code communauté fourni par votre staff (invitation, brief, message). Vous serez redirigé vers la bonne étape (inscription ou connexion).
            </p>
        </div>

        <?php $err = \App\Core\Session::getFlash('error'); ?>
        <?php if ($err): ?>
            <?php $flash_variant = 'error'; $flash_message = $err; $flash_margin_class = 'mb-8'; require base_path('views/partials/flash_message.php'); ?>
        <?php endif; ?>

        <div class="bg-white border border-slate-200/90 rounded-[1.75rem] shadow-[0_20px_50px_-12px_rgba(15,23,42,0.12)] overflow-hidden ring-1 ring-slate-100">
            <div class="px-6 sm:px-8 py-6 border-b border-slate-100 bg-emerald-50/40">
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-600 text-white text-lg font-black shadow-sm" aria-hidden="true">1</div>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.28em] text-slate-500">Étape unique</p>
                        <p class="mt-1 text-sm text-slate-600 leading-relaxed">
                            Le code ressemble souvent à un identifiant d’unité, par exemple
                            <span class="inline-flex items-center rounded-lg bg-emerald-50 px-2 py-0.5 font-mono text-sm font-semibold text-emerald-800 ring-1 ring-emerald-200/80">UNIT-ALPHA</span>.
                        </p>
                    </div>
                </div>
            </div>

            <form method="post" action="<?= htmlspecialchars(url('community/resolve-code'), ENT_QUOTES, 'UTF-8') ?>" class="px-6 sm:px-8 py-8 space-y-6">
                <?= \App\Core\Csrf::field() ?>

                <div class="space-y-2">
                    <label class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 ml-1" for="community_code">Code communauté</label>
                    <input
                        id="community_code"
                        type="text"
                        name="community_code"
                        value="<?= htmlspecialchars($prefill_code ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        required
                        minlength="3"
                        maxlength="64"
                        placeholder="Saisissez votre code"
                        autocomplete="off"
                        class="w-full bg-white border-2 border-slate-200 px-4 py-4 rounded-2xl text-center text-lg font-bold tracking-[0.2em] uppercase text-slate-900 placeholder:text-slate-400 placeholder:tracking-normal placeholder:text-sm placeholder:font-semibold focus:outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/15 transition-all shadow-inner shadow-slate-100/80"
                    >
                </div>

                <button
                    type="submit"
                    class="w-full rounded-2xl bg-slate-900 py-4 text-[11px] font-black uppercase tracking-[0.28em] text-white shadow-lg shadow-slate-200/80 transition-all hover:bg-emerald-600 hover:-translate-y-0.5 focus:outline-none focus:ring-4 focus:ring-emerald-500/15"
                >
                    Continuer
                </button>
            </form>

            <div class="px-6 sm:px-8 py-5 border-t border-slate-100 bg-slate-50/60">
                <p class="text-center text-xs text-slate-500 mb-3">Pas encore de code ?</p>
                <div class="flex flex-col sm:flex-row items-stretch justify-center gap-3 text-center text-sm">
                    <a href="<?= htmlspecialchars(url(''), ENT_QUOTES, 'UTF-8') ?>" class="rounded-xl border border-slate-200 bg-white px-4 py-3 font-semibold text-slate-700 hover:border-emerald-300 hover:text-emerald-800 transition-colors">
                        Retour à l’accueil
                    </a>
                    <a href="<?= htmlspecialchars(url('register'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-xl border border-transparent bg-emerald-600 px-4 py-3 font-semibold text-white hover:bg-emerald-700 transition-colors">
                        Créer un compte sans code
                    </a>
                </div>
            </div>
        </div>

        <p class="mt-8 text-center text-xs text-slate-500 leading-relaxed max-w-md mx-auto">
            Après connexion, vous pourrez aussi
            <a href="<?= htmlspecialchars(url('communities/create'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-slate-700 hover:text-emerald-700 underline decoration-slate-300 underline-offset-2">créer une communauté</a>
            si votre projet le prévoit.
        </p>
    </div>
</main>

<?php require base_path('views/partials/public_portal_auth_footer.php'); ?>

</body>
</html>
