<?php
$base = url('');
$error = \App\Core\Session::getFlash('error');
$success = \App\Core\Session::getFlash('success');
$warning = \App\Core\Session::getFlash('warning');
$info = \App\Core\Session::getFlash('info');
$pendingVerificationEmail = \App\Core\Session::get('pending_verification_email');
$title = $title ?? 'Connexion';
$brand = function_exists('email_brand_name') ? email_brand_name() : 'Athena';
$brandText = htmlspecialchars($brand, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="fr" class="h-full bg-black">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> — <?= $brandText ?></title>
    <meta name="theme-color" content="#050505">
    <meta name="robots" content="noindex,nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <?php $tailwindBaseUrl = $base; require base_path('views/partials/tailwind_cdn_or_build.php'); ?>
    <script defer src="https://unpkg.com/alpinejs@3/dist/cdn.min.js"></script>
    <link href="<?= htmlspecialchars(asset_url('assets/css/home-impact.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <style>
        [x-cloak] { display: none !important; }
        html, body { background: #050505; min-height: 100%; }
        .otp-auth-section {
            padding-block: clamp(2.5rem, 6vh, 4.5rem);
            padding-inline: clamp(1.25rem, 4vw, 4rem);
        }
        .login-field {
            width: 100%;
            border-radius: 0.65rem;
            border: 1px solid rgba(244, 244, 240, 0.22);
            background: rgba(244, 244, 240, 0.04);
            color: #f4f4f0;
            padding: 0.9rem 1rem;
            font-size: 0.9375rem;
            font-weight: 500;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }
        .login-field::placeholder { color: rgba(244, 244, 240, 0.28); }
        .login-field:focus {
            outline: none;
            border-color: rgba(52, 211, 153, 0.75);
            box-shadow: 0 0 0 3px rgba(52, 211, 153, 0.18);
            background: rgba(52, 211, 153, 0.06);
        }
        .login-label {
            display: block;
            margin-bottom: 0.4rem;
            font-size: 0.625rem;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: rgba(244, 244, 240, 0.42);
        }
        @media (prefers-reduced-motion: reduce) {
            .login-field { transition: none; }
        }
    </style>
</head>
<body
    class="home-impact min-h-[100svh] bg-[var(--hi-void,#050505)] text-[var(--hi-ink,#f4f4f0)] antialiased selection:bg-emerald-500 selection:text-slate-950"
    x-data="{ view: 'login', showPassword: false }"
>

<section class="relative flex min-h-[100svh] flex-col bg-[var(--hi-void,#050505)]">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,rgba(52,211,153,0.06),transparent_55%)]" aria-hidden="true"></div>

    <header class="relative z-10 shrink-0 border-b border-white/5 bg-black">
        <div class="mx-auto flex h-12 max-w-[100rem] items-center justify-between px-5 md:px-8">
            <a href="<?= htmlspecialchars(url(''), ENT_QUOTES, 'UTF-8') ?>" class="hi-kicker text-white/45 transition hover:text-white">Accueil</a>
            <span class="text-[11px] font-black uppercase tracking-[0.32em] text-white"><?= $brandText ?></span>
            <a href="<?= htmlspecialchars(url('register'), ENT_QUOTES, 'UTF-8') ?>" class="hi-kicker text-emerald-400/85 transition hover:text-emerald-300">Inscription</a>
        </div>
    </header>

    <div class="relative z-10 flex flex-1 flex-col justify-center otp-auth-section">
        <p class="hi-kicker hi-kicker-glitch hi-reveal text-emerald-400/90">Portail MILSIM · Accès sécurisé</p>
        <h1 class="hi-display hi-hero-brand hi-glitch hi-reveal hi-reveal-delay mt-6 text-white" data-text="<?= $brandText ?>" aria-label="<?= $brandText ?>">
            <span class="hi-glitch__main" aria-hidden="true"><?= $brandText ?><span class="hi-glitch__dot">.</span></span>
        </h1>
        <p class="hi-body hi-reveal hi-reveal-delay mt-8 max-w-xl text-white/70">
            Entrez dans votre espace communauté — organisation, formations et commandement.
        </p>

        <div class="hi-reveal hi-reveal-delay mt-8 max-w-xl space-y-3">
            <?php if ($error): ?>
                <?php $flash_variant = 'error'; $flash_message = $error; $flash_surface = 'dark'; require base_path('views/partials/flash_message.php'); ?>
            <?php endif; ?>
            <?php if ($success): ?>
                <?php $flash_variant = 'success'; $flash_message = $success; $flash_surface = 'dark'; require base_path('views/partials/flash_message.php'); ?>
            <?php endif; ?>
            <?php if ($warning): ?>
                <?php $flash_variant = 'warning'; $flash_message = $warning; $flash_surface = 'dark'; require base_path('views/partials/flash_message.php'); ?>
            <?php endif; ?>
            <?php if ($info): ?>
                <?php $flash_variant = 'info'; $flash_message = $info; $flash_surface = 'dark'; require base_path('views/partials/flash_message.php'); ?>
            <?php endif; ?>
        </div>

        <?php if ($pendingVerificationEmail !== null && $pendingVerificationEmail !== ''): ?>
        <form method="post" action="<?= url('resend-verification') ?>" class="hi-reveal hi-reveal-delay mt-6 max-w-xl rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-4">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="email" value="<?= htmlspecialchars((string) $pendingVerificationEmail, ENT_QUOTES, 'UTF-8') ?>">
            <p class="mb-3 text-sm font-medium text-emerald-100/90">Votre adresse e-mail n’est pas encore confirmée.</p>
            <button type="submit" class="hi-cta hi-cta-solid w-full justify-center">
                Renvoyer le lien de confirmation
            </button>
        </form>
        <?php endif; ?>

        <div class="hi-reveal hi-reveal-delay mt-10 max-w-xl" x-show="view === 'login'" x-transition.opacity.duration.250ms>
            <p class="hi-kicker text-white/45">Connexion</p>
            <form method="post" action="<?= url('login') ?>" class="mt-5 space-y-5">
                <?= \App\Core\Csrf::field() ?>
                <div>
                    <label for="email" class="login-label">Adresse e-mail</label>
                    <input type="email" name="email" id="email" required autocomplete="email" autocapitalize="none" spellcheck="false"
                           placeholder="vous@exemple.fr"
                           class="login-field"
                           data-lowercase="email">
                </div>
                <div>
                    <div class="mb-0.5 flex items-center justify-between gap-3">
                        <label for="password" class="login-label mb-0">Mot de passe</label>
                        <button type="button" @click.prevent="view = 'forgot'" class="text-[11px] font-bold uppercase tracking-wider text-emerald-400/90 transition hover:text-emerald-300">
                            Oublié ?
                        </button>
                    </div>
                    <div class="relative mt-2">
                        <input :type="showPassword ? 'text' : 'password'" name="password" id="password" required autocomplete="current-password"
                               placeholder="••••••••••••"
                               class="login-field pr-12">
                        <button type="button" @click="showPassword = !showPassword"
                                class="absolute inset-y-0 right-0 flex items-center px-3 text-white/35 transition hover:text-white/80"
                                :aria-label="showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'">
                            <svg x-show="!showPassword" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="showPassword" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                        </button>
                    </div>
                </div>
                <button type="submit" class="hi-cta hi-cta-solid mt-1 w-full justify-center">Se connecter</button>
            </form>
        </div>

        <div class="hi-reveal hi-reveal-delay mt-10 max-w-xl" x-show="view === 'forgot'" x-transition.opacity.duration.250ms x-cloak>
            <p class="hi-kicker text-white/45">Récupération</p>
            <p class="mt-3 text-sm leading-relaxed text-white/55">
                Indiquez l’adresse du compte : un lien pour en choisir un nouveau vous sera envoyé.
            </p>
            <form method="post" action="<?= url('forgot-password') ?>" class="mt-6 space-y-5">
                <?= \App\Core\Csrf::field() ?>
                <div>
                    <label for="forgot-email" class="login-label">Adresse e-mail</label>
                    <input type="email" name="email" id="forgot-email" required autocomplete="email" autocapitalize="none" spellcheck="false"
                           placeholder="vous@exemple.fr"
                           class="login-field"
                           data-lowercase="email">
                </div>
                <button type="submit" class="hi-cta hi-cta-solid w-full justify-center">Envoyer le lien</button>
                <button type="button" @click.prevent="view = 'login'" class="hi-cta hi-cta-ghost w-full justify-center">Retour à la connexion</button>
            </form>
        </div>

        <div class="hi-reveal hi-reveal-delay mt-10 max-w-xl space-y-3 text-sm text-white/45">
            <p>
                Pas encore de compte ?
                <a href="<?= url('register') ?>" class="font-semibold text-emerald-400 transition hover:text-emerald-300">Créer un compte</a>
            </p>
            <p class="text-xs leading-relaxed text-white/35">
                Code d’invitation ?
                <a href="<?= url('join') ?>" class="font-semibold text-white/65 underline decoration-white/20 underline-offset-4 hover:text-white">Rejoindre une communauté</a>
            </p>
            <div class="flex flex-wrap gap-x-3 gap-y-1 pt-2 text-[10px] text-white/25">
                <?php
                $legal_link_class = 'font-semibold text-white/35 hover:text-emerald-400';
                require base_path('views/partials/legal_site_links.php');
                ?>
            </div>
        </div>
    </div>
</section>

<?php require base_path('views/partials/cookie_banner.php'); ?>
<script defer src="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/js/auth_forms.js"></script>
</body>
</html>
