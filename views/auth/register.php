<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'Inscription') ?> — Athena</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="min-h-screen bg-slate-950 text-white">
    <div class="relative isolate flex min-h-screen items-center justify-center px-4 py-10">

        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(16,185,129,0.18),transparent_30%),radial-gradient(circle_at_80%_20%,rgba(59,130,246,0.12),transparent_28%)]"></div>
        <div class="absolute inset-0 bg-[linear-gradient(to_bottom,rgba(2,6,23,0.6),rgba(2,6,23,0.95))]"></div>

        <div class="relative z-10 w-full max-w-2xl">
            <div class="mb-8 text-center">
                <div class="inline-flex items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1.5 text-[11px] font-black uppercase tracking-[0.22em] text-emerald-300">
                    Nouveau compte
                </div>

                <h1 class="mt-6 text-3xl font-black tracking-tight text-white">
                    Créer un compte
                </h1>

                <p class="mt-3 text-sm text-slate-400 leading-6">
                    Indiquez votre identité sur la plateforme et les informations de votre personnage role play. Le code d’unité ci-dessous ne sert que si vous en avez reçu un pour rejoindre une communauté sur Athena.
                </p>
            </div>

            <?php $err = \App\Core\Session::getFlash('error'); $ok = \App\Core\Session::getFlash('success'); ?>
            <?php if ($err): ?>
                <?php $flash_variant = 'error'; $flash_message = $err; $flash_surface = 'dark'; $flash_margin_class = 'mb-6'; require base_path('views/partials/flash_message.php'); ?>
            <?php endif; ?>
            <?php if ($ok): ?>
                <?php $flash_variant = 'success'; $flash_message = $ok; $flash_surface = 'dark'; $flash_margin_class = 'mb-6'; require base_path('views/partials/flash_message.php'); ?>
            <?php endif; ?>

            <?php
            $prefillCc = $prefill_community_code ?? '';
            $prefillSlug = $prefill_tenant_slug ?? '';
            ?>

            <div class="overflow-hidden rounded-[1.75rem] border border-white/10 bg-white/[0.06] shadow-[0_30px_80px_-20px_rgba(0,0,0,0.6)] backdrop-blur-xl">

                <div class="border-b border-white/10 px-6 py-5">
                    <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">
                        Code d’unité ou d’invitation <span class="font-semibold normal-case tracking-normal text-slate-500">(facultatif)</span>
                    </p>
                    <div class="mt-3 space-y-2 text-sm text-slate-400 leading-relaxed">
                        <?php if ($prefillSlug !== ''): ?>
                            <p>
                                Inscription associée à l’espace <span class="font-mono font-semibold text-emerald-300"><?= htmlspecialchars($prefillSlug) ?></span>
                                — le champ ci-dessous est prérempli si un code vous a été transmis.
                            </p>
                        <?php else: ?>
                            <p>
                                Remplissez ce champ <strong class="font-semibold text-slate-300">uniquement</strong> si un responsable ou un lien vous a fourni un code pour rejoindre <em class="not-italic text-slate-300">leur</em> groupe sur Athena.
                            </p>
                            <p>
                                <strong class="font-semibold text-slate-300">Pas de code ?</strong> Laissez vide : le compte se crée quand même. Vous pourrez rejoindre une unité plus tard (invitation, nouveau code, etc.).
                            </p>
                            <p class="text-xs text-slate-500">
                                Exemple de format souvent utilisé :
                                <span class="inline-flex items-center rounded-md bg-emerald-400/10 px-2 py-0.5 font-mono text-emerald-300 ring-1 ring-emerald-400/20">UNIT-ALPHA</span>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <form method="post" action="<?= htmlspecialchars(url('register'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-5 px-6 py-6">
                    <?= \App\Core\Csrf::field() ?>

                    <div class="space-y-2">
                        <label class="block text-[11px] font-black uppercase tracking-[0.22em] text-slate-400" for="community_code">Code reçu (si applicable)</label>
                        <div class="rounded-2xl border border-white/10 bg-slate-950/70 p-2 ring-1 ring-white/5 focus-within:border-emerald-400/40 focus-within:ring-emerald-400/20 transition">
                            <input
                                id="community_code"
                                type="text"
                                name="community_code"
                                value="<?= htmlspecialchars((string) $prefillCc, ENT_QUOTES, 'UTF-8') ?>"
                                maxlength="64"
                                placeholder="UNIT-ALPHA"
                                autocomplete="off"
                                class="w-full bg-transparent px-3 py-3 text-sm font-mono font-semibold tracking-wide uppercase text-white outline-none placeholder:text-slate-500"
                            >
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[11px] font-black uppercase tracking-[0.22em] text-slate-400" for="email">E-mail</label>
                        <div class="rounded-2xl border border-white/10 bg-slate-950/70 p-2 ring-1 ring-white/5 focus-within:border-emerald-400/40 focus-within:ring-emerald-400/20 transition">
                            <input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autocomplete="email"
                                placeholder="operateur@exemple.fr"
                                class="w-full bg-transparent px-3 py-3 text-sm font-semibold text-white outline-none placeholder:text-slate-500"
                            >
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[11px] font-black uppercase tracking-[0.22em] text-slate-400" for="display_name">Nom affiché (compte)</label>
                        <p class="text-xs text-slate-500">Nom public visible sur la plateforme (2 à 100 caractères).</p>
                        <div class="rounded-2xl border border-white/10 bg-slate-950/70 p-2 ring-1 ring-white/5 focus-within:border-emerald-400/40 focus-within:ring-emerald-400/20 transition">
                            <input
                                id="display_name"
                                type="text"
                                name="display_name"
                                required
                                minlength="2"
                                maxlength="100"
                                autocomplete="nickname"
                                placeholder="Votre nom ou pseudo plateforme"
                                class="w-full bg-transparent px-3 py-3 text-sm font-semibold text-white outline-none placeholder:text-slate-500"
                            >
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[11px] font-black uppercase tracking-[0.22em] text-slate-400" for="password">Mot de passe</label>
                        <p class="text-xs text-slate-500">Minimum 8 caractères.</p>
                        <div class="rounded-2xl border border-white/10 bg-slate-950/70 p-2 ring-1 ring-white/5 focus-within:border-emerald-400/40 focus-within:ring-emerald-400/20 transition">
                            <input
                                id="password"
                                type="password"
                                name="password"
                                required
                                minlength="8"
                                autocomplete="new-password"
                                placeholder="••••••••"
                                class="w-full bg-transparent px-3 py-3 text-sm text-white outline-none placeholder:text-slate-500"
                            >
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[11px] font-black uppercase tracking-[0.22em] text-slate-400" for="password_confirmation">Confirmation</label>
                        <div class="rounded-2xl border border-white/10 bg-slate-950/70 p-2 ring-1 ring-white/5 focus-within:border-emerald-400/40 focus-within:ring-emerald-400/20 transition">
                            <input
                                id="password_confirmation"
                                type="password"
                                name="password_confirmation"
                                required
                                minlength="8"
                                autocomplete="new-password"
                                placeholder="••••••••"
                                class="w-full bg-transparent px-3 py-3 text-sm text-white outline-none placeholder:text-slate-500"
                            >
                        </div>
                    </div>

                    <div class="rounded-2xl border border-emerald-400/20 bg-emerald-500/5 px-4 py-4">
                        <p class="text-[11px] font-black uppercase tracking-[0.22em] text-emerald-300/90 mb-4">
                            Personnage role play
                        </p>
                        <p class="text-xs text-slate-500 mb-4 leading-relaxed">
                            Ces identifiants constituent votre fiche opérationnelle de départ ; vous pourrez la compléter plus tard dans le dossier personnel.
                        </p>
                        <div class="space-y-4">
                            <div class="space-y-2">
                                <label class="block text-[11px] font-black uppercase tracking-[0.22em] text-slate-400" for="character_name">Nom opérateur / RP</label>
                                <div class="rounded-2xl border border-white/10 bg-slate-950/70 p-2 ring-1 ring-white/5 focus-within:border-emerald-400/40 focus-within:ring-emerald-400/20 transition">
                                    <input
                                        id="character_name"
                                        type="text"
                                        name="character_name"
                                        required
                                        minlength="2"
                                        maxlength="150"
                                        autocomplete="off"
                                        placeholder="Nom de votre personnage"
                                        class="w-full bg-transparent px-3 py-3 text-sm font-semibold text-white outline-none placeholder:text-slate-500"
                                    >
                                </div>
                            </div>
                            <div class="space-y-2">
                                <label class="block text-[11px] font-black uppercase tracking-[0.22em] text-slate-400" for="callsign">Callsign</label>
                                <p class="text-xs text-slate-500">Unique dans la communauté (2 à 50 caractères).</p>
                                <div class="rounded-2xl border border-white/10 bg-slate-950/70 p-2 ring-1 ring-white/5 focus-within:border-emerald-400/40 focus-within:ring-emerald-400/20 transition">
                                    <input
                                        id="callsign"
                                        type="text"
                                        name="callsign"
                                        required
                                        minlength="2"
                                        maxlength="50"
                                        autocomplete="off"
                                        placeholder="E-10"
                                        class="w-full bg-transparent px-3 py-3 text-sm font-mono font-semibold tracking-wide text-white outline-none placeholder:text-slate-500"
                                    >
                                </div>
                            </div>
                            <div class="space-y-2">
                                <label class="block text-[11px] font-black uppercase tracking-[0.22em] text-slate-400" for="primary_role">Rôle principal</label>
                                <div class="rounded-2xl border border-white/10 bg-slate-950/70 p-2 ring-1 ring-white/5 focus-within:border-emerald-400/40 focus-within:ring-emerald-400/20 transition">
                                    <input
                                        id="primary_role"
                                        type="text"
                                        name="primary_role"
                                        required
                                        minlength="2"
                                        maxlength="100"
                                        autocomplete="off"
                                        placeholder="Fusilier, médic, JTAC…"
                                        class="w-full bg-transparent px-3 py-3 text-sm font-semibold text-white outline-none placeholder:text-slate-500"
                                    >
                                </div>
                            </div>
                            <div class="space-y-2">
                                <label class="block text-[11px] font-black uppercase tracking-[0.22em] text-slate-400" for="secondary_role">Rôle secondaire <span class="font-semibold normal-case tracking-normal text-slate-500">(facultatif)</span></label>
                                <div class="rounded-2xl border border-white/10 bg-slate-950/70 p-2 ring-1 ring-white/5 focus-within:border-emerald-400/40 focus-within:ring-emerald-400/20 transition">
                                    <input
                                        id="secondary_role"
                                        type="text"
                                        name="secondary_role"
                                        maxlength="100"
                                        autocomplete="off"
                                        placeholder="…"
                                        class="w-full bg-transparent px-3 py-3 text-sm font-semibold text-white outline-none placeholder:text-slate-500"
                                    >
                                </div>
                            </div>
                        </div>
                    </div>

                    <button
                        type="submit"
                        class="w-full rounded-2xl bg-emerald-600 px-6 py-3.5 text-sm font-black uppercase tracking-[0.18em] text-white transition hover:bg-emerald-500 focus:outline-none focus:ring-4 focus:ring-emerald-400/20"
                    >
                        S’inscrire
                    </button>
                </form>

                <div class="border-t border-white/10 px-6 py-4 text-center text-sm text-slate-500">
                    <a href="<?= htmlspecialchars(url('login'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-slate-400 hover:text-white transition">
                        Déjà un compte ? Connexion
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
