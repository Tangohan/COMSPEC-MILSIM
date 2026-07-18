<?php
$base = url('');
$title = $title ?? 'Inscription';
$active = 'register';
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
    <link href="<?= htmlspecialchars($base) ?>/assets/css/design-system.css" rel="stylesheet">
    <link href="<?= htmlspecialchars($base) ?>/assets/css/public-portal-day.css" rel="stylesheet">
    <style>
        body.public-portal-day { font-family: Inter, system-ui, sans-serif; }
    </style>
</head>
<body class="public-portal-day min-h-screen bg-white text-slate-900 antialiased flex flex-col">

<div class="absolute top-0 left-0 w-full h-1 bg-emerald-600 z-30" aria-hidden="true"></div>
<div class="pointer-events-none fixed inset-0 -z-10 bg-[radial-gradient(ellipse_100%_55%_at_50%_-15%,rgba(16,185,129,0.07),transparent_52%)]" aria-hidden="true"></div>

<?php require base_path('views/partials/public_portal_auth_frame.php'); ?>

<main class="relative z-10 flex-1 w-full px-4 py-10 sm:py-14 flex justify-center">
    <div class="w-full max-w-2xl">

        <div class="text-center mb-10">
            <p class="text-[11px] font-black tracking-[0.45em] text-emerald-700/80 uppercase mb-3">Nouveau compte</p>
            <div class="flex items-center justify-center gap-4 mb-4">
                <span class="h-px w-10 bg-slate-200"></span>
                <span class="text-2xl font-black italic tracking-tight uppercase text-slate-900">Forward</span>
                <span class="h-px w-10 bg-slate-200"></span>
            </div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Créer un compte opérateur</h1>
            <p class="mt-3 text-sm text-slate-700 leading-relaxed max-w-lg mx-auto">
                Parcours en 2 blocs : <strong>identité légale</strong> (administrative, isolée) puis <strong>identité plateforme</strong> (pseudo affiché). L’identité <strong>personnage</strong> se complète dans votre dossier de candidature ou sur la fiche personnelle.
            </p>
            <p class="mt-2 text-xs text-slate-500 leading-relaxed max-w-lg mx-auto">
                Après inscription, vous recevez aussi un e-mail de checklist (sécurité OTP, profil Steam et démarrage).
            </p>
        </div>

        <?php $err = \App\Core\Session::getFlash('error'); $ok = \App\Core\Session::getFlash('success'); ?>
        <?php if ($err): ?>
            <?php $flash_variant = 'error'; $flash_message = $err; $flash_margin_class = 'mb-8'; require base_path('views/partials/flash_message.php'); ?>
        <?php endif; ?>
        <?php if ($ok): ?>
            <?php $flash_variant = 'success'; $flash_message = $ok; $flash_margin_class = 'mb-8'; require base_path('views/partials/flash_message.php'); ?>
        <?php endif; ?>

        <?php
        $prefillCc = $prefill_community_code ?? '';
        $prefillSlug = $prefill_tenant_slug ?? '';
        ?>

        <div class="bg-white border border-slate-200/90 rounded-[1.75rem] shadow-[0_20px_50px_-12px_rgba(15,23,42,0.12)] overflow-hidden ring-1 ring-slate-100">
            <div class="px-6 sm:px-8 py-6 border-b border-slate-100 bg-emerald-50/35">
                <p class="text-[10px] font-black uppercase tracking-[0.28em] text-slate-500">Invitation (facultatif)</p>
                <p class="mt-2 text-sm text-slate-600 leading-relaxed">
                    <?php if ($prefillSlug !== ''): ?>
                        Espace ciblé : <span class="font-semibold text-emerald-700"><?= htmlspecialchars($prefillSlug) ?></span>
                        — le champ est prérempli si un code vous a été transmis.
                    <?php else: ?>
                        <strong class="text-slate-800">Sans code</strong>, laissez vide : le compte se crée normalement. Vous pourrez rejoindre une unité plus tard (invitation, lien, etc.).
                    <?php endif; ?>
                </p>
            </div>

            <form method="post" action="<?= htmlspecialchars(url('register'), ENT_QUOTES, 'UTF-8') ?>" class="px-6 sm:px-8 py-8 space-y-6">
                <?= \App\Core\Csrf::field() ?>
                <div class="rounded-2xl border border-amber-200/90 bg-gradient-to-br from-amber-50/90 to-white p-5 sm:p-6">
                    <p class="text-[10px] font-black uppercase tracking-[0.28em] text-amber-900/90 mb-1">1 · Identité légale</p>
                    <p class="text-xs text-slate-600 mb-4 leading-relaxed">
                        Données administratives, isolées du pseudo public. Date de naissance, pays et Discord sont utiles à l’équipe ; laissez vide si vous préférez ne pas les renseigner tout de suite.
                    </p>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-500 ml-1" for="legal_first_name">Prénom légal</label>
                            <input
                                id="legal_first_name"
                                type="text"
                                name="legal_first_name"
                                required
                                minlength="2"
                                maxlength="100"
                                autocomplete="given-name"
                                placeholder="Prénom administratif"
                                class="ds-input w-full text-sm shadow-inner shadow-slate-100/60"
                            >
                        </div>
                        <div class="space-y-2">
                            <label class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-500 ml-1" for="legal_last_name">Nom légal</label>
                            <input
                                id="legal_last_name"
                                type="text"
                                name="legal_last_name"
                                required
                                minlength="2"
                                maxlength="100"
                                autocomplete="family-name"
                                placeholder="Nom administratif"
                                class="ds-input w-full text-sm shadow-inner shadow-slate-100/60"
                            >
                        </div>
                        <div class="space-y-2">
                            <label class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-500 ml-1" for="legal_birth_date">Date de naissance</label>
                            <input
                                id="legal_birth_date"
                                type="date"
                                name="legal_birth_date"
                                autocomplete="bday"
                                class="ds-input w-full text-sm shadow-inner shadow-slate-100/60"
                            >
                        </div>
                        <div class="space-y-2">
                            <label class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-500 ml-1" for="legal_country">Pays (résidence)</label>
                            <input
                                id="legal_country"
                                type="text"
                                name="legal_country"
                                maxlength="100"
                                autocomplete="country-name"
                                placeholder="ex. France"
                                class="ds-input w-full text-sm shadow-inner shadow-slate-100/60"
                            >
                        </div>
                        <div class="space-y-2 sm:col-span-2">
                            <label class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-500 ml-1" for="discord_handle">Pseudo ou identifiant Discord</label>
                            <input
                                id="discord_handle"
                                type="text"
                                name="discord_handle"
                                maxlength="120"
                                autocomplete="off"
                                placeholder="ex. pseudo#1234 ou @pseudo"
                                class="ds-input w-full text-sm shadow-inner shadow-slate-100/60"
                            >
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-emerald-200/90 bg-gradient-to-br from-emerald-50/60 to-white p-5 sm:p-6 space-y-6">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.28em] text-emerald-800/90 mb-1">2 · Accès plateforme</p>
                        <p class="text-xs text-slate-600 leading-relaxed">
                            Connexion, pseudo affiché publiquement et sécurité du compte.
                        </p>
                    </div>

                    <div class="space-y-2">
                        <label class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-500 ml-1" for="community_code">Code reçu (si applicable)</label>
                        <input
                            id="community_code"
                            type="text"
                            name="community_code"
                            value="<?= htmlspecialchars((string) $prefillCc, ENT_QUOTES, 'UTF-8') ?>"
                            maxlength="64"
                            placeholder="Ex. UNIT-ALPHA"
                            autocomplete="off"
                            class="ds-input w-full text-sm font-semibold tracking-wide uppercase placeholder:normal-case placeholder:tracking-normal shadow-inner shadow-slate-100/60"
                        >
                    </div>

                    <div class="space-y-2">
                        <label class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-500 ml-1" for="email">E-mail</label>
                        <input
                            id="email"
                            type="email"
                            name="email"
                            data-lowercase="email"
                            required
                            autocomplete="email"
                            placeholder="operateur@exemple.fr"
                            class="ds-input w-full text-sm shadow-inner shadow-slate-100/60"
                        >
                    </div>
                    <div class="space-y-2">
                        <label class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-500 ml-1" for="steam_profile">Profil Steam (optionnel)</label>
                        <p class="text-xs text-slate-500 ml-1">SteamID 64, lien <code>/profiles/…</code> ou <code>/id/…</code> — synchronisé dès l’inscription.</p>
                        <input
                            id="steam_profile"
                            type="text"
                            name="steam_profile"
                            maxlength="512"
                            autocomplete="off"
                            placeholder="https://steamcommunity.com/id/votre-profil"
                            class="ds-input w-full text-sm shadow-inner shadow-slate-100/60"
                        >
                    </div>

                    <div class="space-y-2">
                        <label class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-500 ml-1" for="display_name">Pseudo / nom affiché</label>
                        <p class="text-xs text-slate-500 ml-1">Visible sur la plateforme (2 à 100 caractères). Différent du prénom et nom légaux.</p>
                        <input
                            id="display_name"
                            type="text"
                            name="display_name"
                            required
                            minlength="2"
                            maxlength="100"
                            autocomplete="nickname"
                            placeholder="Votre pseudo public"
                            class="ds-input w-full text-sm shadow-inner shadow-slate-100/60"
                        >
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-500 ml-1" for="password">Mot de passe</label>
                            <input
                                id="password"
                                type="password"
                                name="password"
                                required
                                minlength="8"
                                autocomplete="new-password"
                                placeholder="••••••••"
                                class="ds-input w-full text-sm shadow-inner shadow-slate-100/60"
                            >
                        </div>
                        <div class="space-y-2">
                            <label class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-500 ml-1" for="password_confirmation">Confirmation</label>
                            <input
                                id="password_confirmation"
                                type="password"
                                name="password_confirmation"
                                required
                                minlength="8"
                                autocomplete="new-password"
                                placeholder="••••••••"
                                class="ds-input w-full text-sm shadow-inner shadow-slate-100/60"
                            >
                        </div>
                    </div>
                    <p class="text-xs text-slate-500">Au moins 8 caractères.</p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-5 sm:p-6 space-y-4">
                    <label class="flex items-start gap-3 text-sm text-slate-700 leading-relaxed">
                        <input type="checkbox" name="accept_identity_split" value="1" required class="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                        <span>Je comprends que l’identité légale (administrative) est séparée du pseudo affiché sur la plateforme et que l’identité personnage se complète dans le dossier de recrutement ou la fiche personnelle.</span>
                    </label>
                    <label class="flex items-start gap-3 text-sm text-slate-700 leading-relaxed">
                        <input type="checkbox" name="accept_terms" value="1" required class="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                        <span>J’accepte les <a href="<?= htmlspecialchars(url('legal/site'), ENT_QUOTES, 'UTF-8') ?>#cgu" class="font-semibold text-emerald-700 hover:underline">conditions d’utilisation</a> et la <a href="<?= htmlspecialchars(url('legal/site'), ENT_QUOTES, 'UTF-8') ?>#rgpd" class="font-semibold text-emerald-700 hover:underline">politique de données personnelles</a>.</span>
                    </label>
                </div>

                <button
                    type="submit"
                    class="ds-btn ds-btn--primary shadow-lg shadow-slate-200/80"
                >
                    Valider l’inscription
                </button>
            </form>

            <div class="px-6 sm:px-8 py-5 border-t border-slate-100 bg-slate-50/70 text-center">
                <a href="<?= htmlspecialchars(url('login'), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-semibold text-slate-600 hover:text-emerald-700 transition-colors">
                    Déjà un compte ? Connexion
                </a>
                <p class="mt-3 text-xs text-slate-500">
                    Pas encore de communauté ?
                    <a href="<?= htmlspecialchars(url('join'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-700 hover:underline">Utiliser un code d’accès</a>
                </p>
            </div>
        </div>
    </div>
</main>

<?php require base_path('views/partials/public_portal_auth_footer.php'); ?>
<script defer src="<?= htmlspecialchars($base) ?>/assets/js/auth_forms.js"></script>

</body>
</html>
