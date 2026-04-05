<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'Rejoindre') ?> — Athena</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="min-h-screen bg-slate-950 text-white">
    <div class="relative isolate flex min-h-screen items-center justify-center px-4 py-10">

        <!-- Fonds décoratifs -->
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(16,185,129,0.18),transparent_30%),radial-gradient(circle_at_80%_20%,rgba(59,130,246,0.12),transparent_28%)]"></div>
        <div class="absolute inset-0 bg-[linear-gradient(to_bottom,rgba(2,6,23,0.6),rgba(2,6,23,0.95))]"></div>

        <div class="relative z-10 w-full max-w-lg">
            <!-- En-tête -->
            <div class="mb-8 text-center">
                <div class="inline-flex items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1.5 text-[11px] font-black uppercase tracking-[0.22em] text-emerald-300">
                    Accès communauté
                </div>

                <h1 class="mt-6 text-3xl font-black tracking-tight text-white">
                    Rejoindre une communauté
                </h1>

                <p class="mt-3 text-sm text-slate-400 leading-6">
                    Saisissez un code valide pour accéder à un espace existant ou initier votre inscription.
                </p>
            </div>

            <?php $err = \App\Core\Session::getFlash('error'); ?>
            <?php if ($err): ?>
                <?php $flash_variant = 'error'; $flash_message = $err; $flash_surface = 'dark'; $flash_margin_class = 'mb-6'; require base_path('views/partials/flash_message.php'); ?>
            <?php endif; ?>

            <!-- Carte formulaire -->
            <div class="overflow-hidden rounded-[1.75rem] border border-white/10 bg-white/[0.06] shadow-[0_30px_80px_-20px_rgba(0,0,0,0.6)] backdrop-blur-xl">

                <div class="border-b border-white/10 px-6 py-5">
                    <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">
                        Code d’accès
                    </p>
                    <p class="mt-2 text-sm text-slate-400">
                        Exemple :
                        <span class="ml-1 inline-flex items-center rounded-md bg-emerald-400/10 px-2 py-0.5 font-mono text-emerald-300 ring-1 ring-emerald-400/20">
                            UNIT-ALPHA
                        </span>
                    </p>
                </div>

                <form method="post" action="<?= htmlspecialchars(url('community/resolve-code'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-6 px-6 py-6">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">

                    <div class="space-y-2">
                        <label class="block text-[11px] font-black uppercase tracking-[0.22em] text-slate-400" for="community_code">
                            Code communauté
                        </label>

                        <div class="rounded-2xl border border-white/10 bg-slate-950/70 p-2 ring-1 ring-white/5 focus-within:border-emerald-400/40 focus-within:ring-emerald-400/20 transition">
                            <input
                                id="community_code"
                                type="text"
                                name="community_code"
                                value="<?= htmlspecialchars($prefill_code ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                required
                                minlength="3"
                                maxlength="64"
                                placeholder="UNIT-2026"
                                autocomplete="off"
                                class="w-full bg-transparent px-3 py-3 text-sm font-semibold tracking-widest uppercase text-white outline-none placeholder:text-slate-500"
                            >
                        </div>
                    </div>

                    <button
                        type="submit"
                        class="w-full rounded-2xl bg-emerald-600 px-6 py-3 text-sm font-black uppercase tracking-[0.18em] text-white transition hover:bg-emerald-500 focus:outline-none focus:ring-4 focus:ring-emerald-400/20"
                    >
                        Continuer
                    </button>
                </form>

                <div class="border-t border-white/10 px-6 py-4 text-center text-sm text-slate-500">
                    <a href="<?= htmlspecialchars(url(''), ENT_QUOTES, 'UTF-8') ?>" class="hover:text-white transition">
                        Accueil
                    </a>
                    <span class="mx-2 text-slate-700">·</span>
                    <a href="<?= htmlspecialchars(url('register'), ENT_QUOTES, 'UTF-8') ?>" class="hover:text-white transition">
                        Créer un compte
                    </a>
                </div>

            </div>
        </div>
    </div>
</body>
</html>
