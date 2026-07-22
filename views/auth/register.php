<?php
$base = url('');
$title = $title ?? 'Créer un compte';
$brand = function_exists('email_brand_name') ? email_brand_name() : 'Athena';
$old = is_array($register_old ?? null) ? $register_old : [];
$prefillCc = (string) ($prefill_community_code ?? ($old['community_code'] ?? ''));
$prefillSlug = (string) ($prefill_tenant_slug ?? '');
$startStep = (int) ($register_step ?? 1);
if ($startStep < 1 || $startStep > 3) {
    $startStep = 1;
}
$val = static function (array $old, string $key, string $default = '') : string {
    $v = $old[$key] ?? $default;

    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
};
?>
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?></title>
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
        .login-field {
            width: 100%;
            border-radius: 0.75rem;
            border: 1px solid rgba(244, 244, 240, 0.16);
            background: rgba(244, 244, 240, 0.04);
            color: #f4f4f0;
            padding: 0.85rem 1rem;
            font-size: 0.9375rem;
            font-weight: 500;
            letter-spacing: 0.01em;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }
        .login-field::placeholder { color: rgba(244, 244, 240, 0.28); }
        .login-field:focus {
            outline: none;
            border-color: rgba(52, 211, 153, 0.65);
            box-shadow: 0 0 0 3px rgba(52, 211, 153, 0.16);
            background: rgba(52, 211, 153, 0.05);
        }
        .login-label {
            display: block;
            margin-bottom: 0.45rem;
            font-size: 0.6875rem;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: rgba(244, 244, 240, 0.45);
        }
        .login-panel {
            border: 1px solid rgba(244, 244, 240, 0.1);
            background: linear-gradient(165deg, rgba(244, 244, 240, 0.06) 0%, rgba(244, 244, 240, 0.02) 100%);
            backdrop-filter: blur(12px);
            border-radius: 1.25rem;
            box-shadow: 0 24px 64px -28px rgba(0, 0, 0, 0.65);
        }
        .login-visual {
            background:
                linear-gradient(180deg, rgba(5, 5, 5, 0.35) 0%, rgba(5, 5, 5, 0.82) 100%),
                url('<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/images/fog-team.jpg') center / cover no-repeat;
        }
        .reg-step-dot {
            width: 1.75rem;
            height: 1.75rem;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.6875rem;
            font-weight: 800;
            border: 1px solid rgba(244, 244, 240, 0.18);
            color: rgba(244, 244, 240, 0.4);
            background: transparent;
        }
        .reg-step-dot.is-active {
            border-color: rgba(52, 211, 153, 0.7);
            background: rgba(52, 211, 153, 0.18);
            color: #34d399;
        }
        .reg-step-dot.is-done {
            border-color: rgba(52, 211, 153, 0.45);
            background: #059669;
            color: #fff;
        }
        .reg-step-line {
            flex: 1 1 auto;
            height: 1px;
            background: rgba(244, 244, 240, 0.12);
            min-width: 1.25rem;
        }
        .reg-step-line.is-done { background: rgba(52, 211, 153, 0.45); }
        @media (prefers-reduced-motion: reduce) {
            .login-field { transition: none; }
        }
    </style>
</head>
<body
    class="home-impact min-h-[100svh] bg-[var(--hi-void,#050505)] text-[var(--hi-ink,#f4f4f0)] antialiased selection:bg-emerald-500 selection:text-slate-950"
    x-data="{
        step: <?= $startStep ?>,
        showPassword: false,
        showPassword2: false,
        validateStep(n) {
            const root = this.$refs['step' + n];
            if (!root) return true;
            const fields = root.querySelectorAll('input, select, textarea');
            for (const el of fields) {
                if (!el.checkValidity()) {
                    el.reportValidity();
                    return false;
                }
            }
            return true;
        },
        next() {
            if (!this.validateStep(this.step)) return;
            this.step = Math.min(3, this.step + 1);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
        prev() {
            this.step = Math.max(1, this.step - 1);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
        onSubmit(e) {
            if (this.step !== 3) {
                e.preventDefault();
                this.next();
                return;
            }
            for (let n = 1; n <= 3; n++) {
                if (!this.validateStep(n)) {
                    e.preventDefault();
                    this.step = n;
                    return;
                }
            }
        }
    }"
>

<div class="pointer-events-none fixed inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(52,211,153,0.07),transparent_50%)]" aria-hidden="true"></div>

<header class="relative z-20 border-b border-white/5 bg-black/80 backdrop-blur-md">
    <div class="mx-auto flex h-14 max-w-[100rem] items-center justify-between px-5 md:px-8">
        <a href="<?= htmlspecialchars(url(''), ENT_QUOTES, 'UTF-8') ?>" class="hi-kicker text-white/45 transition hover:text-white">Retour</a>
        <a href="<?= htmlspecialchars(url(''), ENT_QUOTES, 'UTF-8') ?>" class="text-[11px] font-black uppercase tracking-[0.32em] text-white">
            <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?>
        </a>
        <a href="<?= htmlspecialchars(url('login'), ENT_QUOTES, 'UTF-8') ?>" class="hi-kicker text-emerald-400/90 transition hover:text-emerald-300">Connexion</a>
    </div>
</header>

<main class="relative z-10 mx-auto grid min-h-[calc(100svh-3.5rem)] w-full max-w-[100rem] lg:grid-cols-2">
    <aside class="login-visual relative hidden overflow-hidden lg:flex lg:flex-col lg:justify-end" aria-hidden="true">
        <div class="absolute inset-0 bg-gradient-to-t from-black via-black/40 to-transparent"></div>
        <div class="relative z-10 p-10 xl:p-14">
            <p class="hi-kicker text-emerald-400/90">Nouveau compte</p>
            <p class="hi-display mt-4 text-[clamp(3rem,6vw,5.5rem)] text-white leading-none">
                <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?><span class="text-emerald-400">.</span>
            </p>
            <p class="hi-body mt-6 max-w-md text-white/65">
                Trois étapes courtes : votre identité administrative, votre accès, puis la validation.
                Le personnage se complète plus tard dans le recrutement.
            </p>
            <ol class="mt-10 space-y-3 text-sm text-white/55">
                <li class="flex gap-3"><span class="font-black text-emerald-400">01</span> Identité administrative (privée)</li>
                <li class="flex gap-3"><span class="font-black text-emerald-400">02</span> E-mail, pseudo et mot de passe</li>
                <li class="flex gap-3"><span class="font-black text-emerald-400">03</span> Confirmation et création</li>
            </ol>
        </div>
    </aside>

    <section class="relative flex flex-col justify-center px-5 py-10 sm:px-8 md:px-12 lg:px-16 xl:px-20">
        <div class="mx-auto w-full max-w-lg">
            <div class="mb-8">
                <p class="hi-kicker text-emerald-400/90">Inscription</p>
                <h1 class="mt-3 text-3xl font-black tracking-tight text-white sm:text-4xl">Créer mon compte</h1>
                <p class="mt-3 text-sm leading-relaxed text-white/55">
                    Environ deux minutes. Un e-mail de confirmation vous sera envoyé ensuite.
                </p>
            </div>

            <nav class="mb-8" aria-label="Étapes d’inscription">
                <div class="flex items-center gap-2">
                    <span class="reg-step-dot" :class="step > 1 ? 'is-done' : (step === 1 ? 'is-active' : '')" aria-current="<?= $startStep === 1 ? 'step' : 'false' ?>">1</span>
                    <span class="reg-step-line" :class="step > 1 ? 'is-done' : ''" aria-hidden="true"></span>
                    <span class="reg-step-dot" :class="step > 2 ? 'is-done' : (step === 2 ? 'is-active' : '')">2</span>
                    <span class="reg-step-line" :class="step > 2 ? 'is-done' : ''" aria-hidden="true"></span>
                    <span class="reg-step-dot" :class="step === 3 ? 'is-active' : ''">3</span>
                </div>
                <p class="mt-3 text-xs font-semibold uppercase tracking-[0.14em] text-white/40">
                    <span x-show="step === 1">Identité</span>
                    <span x-show="step === 2" x-cloak>Compte</span>
                    <span x-show="step === 3" x-cloak>Validation</span>
                </p>
            </nav>

            <?php $err = \App\Core\Session::getFlash('error'); $ok = \App\Core\Session::getFlash('success'); ?>
            <?php if ($err): ?>
                <?php $flash_variant = 'error'; $flash_message = $err; $flash_surface = 'dark'; require base_path('views/partials/flash_message.php'); ?>
            <?php endif; ?>
            <?php if ($ok): ?>
                <?php $flash_variant = 'success'; $flash_message = $ok; $flash_surface = 'dark'; require base_path('views/partials/flash_message.php'); ?>
            <?php endif; ?>

            <div class="login-panel p-6 sm:p-8">
                <form method="post" action="<?= htmlspecialchars(url('register'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-5" novalidate @submit="onSubmit($event)">
                    <?= \App\Core\Csrf::field() ?>

                    <div x-show="step === 1" x-ref="step1" x-transition.opacity.duration.200ms>
                        <p class="mb-1 text-sm font-bold text-white">Qui êtes-vous ?</p>
                        <p class="mb-5 text-xs leading-relaxed text-white/45">
                            Ces informations restent administratives. Elles ne remplacent pas votre pseudo public.
                        </p>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="login-label" for="legal_first_name">Prénom</label>
                                <input id="legal_first_name" type="text" name="legal_first_name" required minlength="2" maxlength="100"
                                       autocomplete="given-name" placeholder="Prénom"
                                       value="<?= $val($old, 'legal_first_name') ?>"
                                       class="login-field">
                            </div>
                            <div>
                                <label class="login-label" for="legal_last_name">Nom</label>
                                <input id="legal_last_name" type="text" name="legal_last_name" required minlength="2" maxlength="100"
                                       autocomplete="family-name" placeholder="Nom"
                                       value="<?= $val($old, 'legal_last_name') ?>"
                                       class="login-field">
                            </div>
                            <div>
                                <label class="login-label" for="legal_birth_date">Date de naissance <span class="normal-case tracking-normal text-white/30">(facultatif)</span></label>
                                <input id="legal_birth_date" type="date" name="legal_birth_date" autocomplete="bday"
                                       value="<?= $val($old, 'legal_birth_date') ?>"
                                       class="login-field">
                            </div>
                            <div>
                                <label class="login-label" for="legal_country">Pays <span class="normal-case tracking-normal text-white/30">(facultatif)</span></label>
                                <input id="legal_country" type="text" name="legal_country" maxlength="100"
                                       autocomplete="country-name" placeholder="ex. France"
                                       value="<?= $val($old, 'legal_country') ?>"
                                       class="login-field">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="login-label" for="discord_handle">Discord <span class="normal-case tracking-normal text-white/30">(facultatif)</span></label>
                                <input id="discord_handle" type="text" name="discord_handle" maxlength="120" autocomplete="off"
                                       placeholder="Votre pseudo Discord"
                                       value="<?= $val($old, 'discord_handle') ?>"
                                       class="login-field">
                            </div>
                        </div>
                        <button type="button" @click="next()" class="hi-cta hi-cta-solid mt-6 w-full justify-center">Continuer</button>
                    </div>

                    <div x-show="step === 2" x-ref="step2" x-cloak x-transition.opacity.duration.200ms>
                        <p class="mb-1 text-sm font-bold text-white">Accès à la plateforme</p>
                        <p class="mb-5 text-xs leading-relaxed text-white/45">
                            E-mail de connexion, pseudo visible et mot de passe.
                            <?php if ($prefillSlug !== ''): ?>
                                Espace ciblé : <span class="font-semibold text-emerald-400"><?= htmlspecialchars($prefillSlug, ENT_QUOTES, 'UTF-8') ?></span>.
                            <?php else: ?>
                                Un code d’invitation n’est pas obligatoire.
                            <?php endif; ?>
                        </p>

                        <div class="space-y-4">
                            <div>
                                <label class="login-label" for="community_code">Code d’invitation <span class="normal-case tracking-normal text-white/30">(facultatif)</span></label>
                                <input id="community_code" type="text" name="community_code" maxlength="64" autocomplete="off"
                                       placeholder="Si vous en avez reçu un"
                                       value="<?= htmlspecialchars($prefillCc, ENT_QUOTES, 'UTF-8') ?>"
                                       class="login-field uppercase tracking-wide placeholder:normal-case placeholder:tracking-normal">
                            </div>
                            <div>
                                <label class="login-label" for="email">Adresse e-mail</label>
                                <input id="email" type="email" name="email" data-lowercase="email" required autocomplete="email"
                                       placeholder="vous@exemple.fr"
                                       value="<?= $val($old, 'email') ?>"
                                       class="login-field">
                            </div>
                            <div>
                                <label class="login-label" for="display_name">Pseudo affiché</label>
                                <input id="display_name" type="text" name="display_name" required minlength="2" maxlength="100"
                                       autocomplete="nickname" placeholder="Visible par les autres membres"
                                       value="<?= $val($old, 'display_name') ?>"
                                       class="login-field">
                            </div>
                            <div>
                                <label class="login-label" for="steam_profile">Profil Steam <span class="normal-case tracking-normal text-white/30">(facultatif)</span></label>
                                <input id="steam_profile" type="text" name="steam_profile" maxlength="512" autocomplete="off"
                                       placeholder="Lien de votre profil Steam"
                                       value="<?= $val($old, 'steam_profile') ?>"
                                       class="login-field">
                                <p class="mt-1.5 text-xs text-white/35">Lien de profil Steam, numéro en jeu, ou identifiant Steam classique.</p>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="login-label" for="password">Mot de passe</label>
                                    <div class="relative">
                                        <input :type="showPassword ? 'text' : 'password'" id="password" name="password" required minlength="8"
                                               autocomplete="new-password" placeholder="8 caractères minimum"
                                               class="login-field pr-12">
                                        <button type="button" @click="showPassword = !showPassword"
                                                class="absolute inset-y-0 right-0 flex items-center px-3 text-white/40 hover:text-white/80"
                                                :aria-label="showPassword ? 'Masquer' : 'Afficher'">
                                            <svg x-show="!showPassword" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            <svg x-show="showPassword" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <label class="login-label" for="password_confirmation">Confirmation</label>
                                    <div class="relative">
                                        <input :type="showPassword2 ? 'text' : 'password'" id="password_confirmation" name="password_confirmation" required minlength="8"
                                               autocomplete="new-password" placeholder="Retapez le mot de passe"
                                               class="login-field pr-12"
                                               @input="if ($el.value && document.getElementById('password').value !== $el.value) { $el.setCustomValidity('Les deux mots de passe doivent être identiques.'); } else { $el.setCustomValidity(''); }">
                                        <button type="button" @click="showPassword2 = !showPassword2"
                                                class="absolute inset-y-0 right-0 flex items-center px-3 text-white/40 hover:text-white/80"
                                                :aria-label="showPassword2 ? 'Masquer' : 'Afficher'">
                                            <svg x-show="!showPassword2" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            <svg x-show="showPassword2" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row">
                            <button type="button" @click="prev()" class="hi-cta hi-cta-ghost w-full justify-center sm:w-auto sm:min-w-[8rem]">Retour</button>
                            <button type="button" @click="next()" class="hi-cta hi-cta-solid w-full flex-1 justify-center">Continuer</button>
                        </div>
                    </div>

                    <div x-show="step === 3" x-ref="step3" x-cloak x-transition.opacity.duration.200ms>
                        <p class="mb-1 text-sm font-bold text-white">Dernière étape</p>
                        <p class="mb-5 text-xs leading-relaxed text-white/45">
                            Confirmez les règles du compte, puis validez. Vous pourrez ensuite confirmer votre e-mail.
                        </p>

                        <div class="space-y-4 rounded-xl border border-white/10 bg-white/[0.03] p-4">
                            <label class="flex items-start gap-3 text-sm leading-relaxed text-white/70">
                                <input type="checkbox" name="accept_identity_split" value="1" required
                                       class="mt-1 h-4 w-4 rounded border-white/20 bg-transparent text-emerald-500 focus:ring-emerald-500/40"
                                       <?= !empty($old['accept_identity_split']) ? 'checked' : '' ?>>
                                <span>Je comprends que mon identité administrative est séparée du pseudo affiché, et que mon personnage se complète plus tard (candidature ou fiche personnelle).</span>
                            </label>
                            <label class="flex items-start gap-3 text-sm leading-relaxed text-white/70">
                                <input type="checkbox" name="accept_terms" value="1" required
                                       class="mt-1 h-4 w-4 rounded border-white/20 bg-transparent text-emerald-500 focus:ring-emerald-500/40"
                                       <?= !empty($old['accept_terms']) ? 'checked' : '' ?>>
                                <span>
                                    J’accepte les
                                    <a href="<?= htmlspecialchars(url('legal/site'), ENT_QUOTES, 'UTF-8') ?>#cgu" class="font-semibold text-emerald-400 hover:underline" target="_blank" rel="noopener">conditions d’utilisation</a>
                                    et la
                                    <a href="<?= htmlspecialchars(url('legal/site'), ENT_QUOTES, 'UTF-8') ?>#rgpd" class="font-semibold text-emerald-400 hover:underline" target="_blank" rel="noopener">politique de données personnelles</a>.
                                </span>
                            </label>
                        </div>

                        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row">
                            <button type="button" @click="prev()" class="hi-cta hi-cta-ghost w-full justify-center sm:w-auto sm:min-w-[8rem]">Retour</button>
                            <button type="submit" class="hi-cta hi-cta-solid w-full flex-1 justify-center">Créer mon compte</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="mt-8 space-y-3 text-center text-sm text-white/50">
                <p>
                    Déjà un compte ?
                    <a href="<?= htmlspecialchars(url('login'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-400 transition hover:text-emerald-300">Connexion</a>
                </p>
                <p class="text-xs leading-relaxed text-white/40">
                    Vous avez seulement un code d’accès ?
                    <a href="<?= htmlspecialchars(url('join'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-white/70 underline decoration-white/20 underline-offset-4 hover:text-white">Rejoindre une communauté</a>
                </p>
            </div>

            <div class="mt-10 flex flex-wrap justify-center gap-x-3 gap-y-1 text-center text-[10px] text-white/30">
                <?php
                $legal_link_class = 'font-semibold text-white/40 hover:text-emerald-400';
                require base_path('views/partials/legal_site_links.php');
                ?>
            </div>
        </div>
    </section>
</main>

<?php require base_path('views/partials/cookie_banner.php'); ?>
<script defer src="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/js/auth_forms.js"></script>
</body>
</html>
