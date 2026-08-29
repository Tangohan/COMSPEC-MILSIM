<?php
$base = url('');
$title = $title ?? __('auth.title_register');
$brand = function_exists('email_brand_name') ? email_brand_name() : 'Athena';
$old = is_array($register_old ?? null) ? $register_old : [];
$prefillCc = (string) ($prefill_community_code ?? ($old['community_code'] ?? ''));
$prefillSlug = (string) ($prefill_tenant_slug ?? '');
$startStep = (int) ($register_step ?? 1);
if ($startStep < 1 || $startStep > 2) {
    $startStep = 1;
}
$val = static function (array $old, string $key, string $default = '') : string {
    $v = $old[$key] ?? $default;

    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
};
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(html_lang(), ENT_QUOTES, 'UTF-8') ?>" class="h-full">
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
                linear-gradient(180deg, rgba(5, 5, 5, 0.2) 0%, rgba(5, 5, 5, 0.55) 45%, rgba(5, 5, 5, 0.92) 100%),
                url('<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/images/fog-team.jpg') center / cover no-repeat;
            background-color: #050505;
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
        }
        .reg-step-line.is-done { background: rgba(52, 211, 153, 0.45); }
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
        syncDisplayName() {
            const first = (document.getElementById('first_name')?.value || '').trim();
            const last = (document.getElementById('last_name')?.value || '').trim();
            const dn = document.getElementById('display_name');
            if (dn) dn.value = (first + ' ' + last).trim();
        },
        next() {
            this.syncDisplayName();
            if (!this.validateStep(this.step)) return;
            this.step = Math.min(2, this.step + 1);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
        prev() {
            this.step = Math.max(1, this.step - 1);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
        onSubmit(e) {
            this.syncDisplayName();
            if (this.step !== 2) {
                e.preventDefault();
                this.next();
                return;
            }
            for (let n = 1; n <= 2; n++) {
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
    <div class="flex h-14 w-full items-center justify-between gap-3 px-5 md:px-8 lg:px-10">
        <a href="<?= htmlspecialchars(url(''), ENT_QUOTES, 'UTF-8') ?>" class="hi-kicker text-white/45 transition hover:text-white"><?= htmlspecialchars(__('common.back'), ENT_QUOTES, 'UTF-8') ?></a>
        <a href="<?= htmlspecialchars(url(''), ENT_QUOTES, 'UTF-8') ?>" class="text-[11px] font-black uppercase tracking-[0.32em] text-white">
            <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?>
        </a>
        <div class="flex items-center gap-3">
            <?php $localeSwitcherVariant = 'dark'; require base_path('views/partials/language_switcher.php'); ?>
            <a href="<?= htmlspecialchars(url('login'), ENT_QUOTES, 'UTF-8') ?>" class="hi-kicker text-emerald-400/90 transition hover:text-emerald-300"><?= htmlspecialchars(__('common.login'), ENT_QUOTES, 'UTF-8') ?></a>
        </div>
    </div>
</header>

<main class="relative z-10 grid min-h-[calc(100svh-3.5rem)] w-full lg:grid-cols-2">
    <aside class="login-visual relative hidden min-h-full overflow-hidden lg:flex lg:flex-col lg:justify-end" aria-hidden="true">
        <div class="absolute inset-0 bg-gradient-to-t from-black via-black/55 to-black/10"></div>
        <div class="relative z-10 p-10 xl:p-14 2xl:p-16">
            <p class="hi-kicker text-emerald-400/90"><?= htmlspecialchars(__('auth.register_kicker'), ENT_QUOTES, 'UTF-8') ?></p>
            <p class="hi-display mt-4 text-[clamp(3rem,5.5vw,5.5rem)] text-white leading-none">
                <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?><span class="text-emerald-400">.</span>
            </p>
            <p class="hi-body mt-6 max-w-md text-white/65">
                <?= htmlspecialchars(__('auth.register_aside'), ENT_QUOTES, 'UTF-8') ?>
            </p>
            <ol class="mt-10 space-y-3 text-sm text-white/55">
                <li class="flex gap-3"><span class="font-black text-emerald-400">01</span> <?= htmlspecialchars(__('auth.register_step1'), ENT_QUOTES, 'UTF-8') ?></li>
                <li class="flex gap-3"><span class="font-black text-emerald-400">02</span> <?= htmlspecialchars(__('auth.register_step2'), ENT_QUOTES, 'UTF-8') ?></li>
            </ol>
        </div>
    </aside>

    <section class="relative flex flex-col justify-center px-5 py-10 sm:px-8 md:px-12 lg:px-14 xl:px-20 2xl:px-28">
        <div class="mx-auto w-full max-w-lg lg:mx-0 lg:ml-0 xl:max-w-xl">
            <div class="mb-8">
                <p class="hi-kicker text-emerald-400/90"><?= htmlspecialchars(__('common.register'), ENT_QUOTES, 'UTF-8') ?></p>
                <h1 class="mt-3 text-3xl font-black tracking-tight text-white sm:text-4xl"><?= htmlspecialchars(__('auth.register_heading'), ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="mt-3 text-sm leading-relaxed text-white/55">
                    <?= htmlspecialchars(__('auth.register_sub'), ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>

            <nav class="mb-8" aria-label="<?= htmlspecialchars(__('auth.steps_aria'), ENT_QUOTES, 'UTF-8') ?>">
                <div class="flex items-center gap-2">
                    <span class="reg-step-dot" :class="step > 1 ? 'is-done' : (step === 1 ? 'is-active' : '')" aria-current="<?= $startStep === 1 ? 'step' : 'false' ?>">1</span>
                    <span class="reg-step-line" :class="step > 1 ? 'is-done' : ''" aria-hidden="true"></span>
                    <span class="reg-step-dot" :class="step === 2 ? 'is-active' : ''">2</span>
                </div>
                <p class="mt-3 text-xs font-semibold uppercase tracking-[0.14em] text-white/40">
                    <span x-show="step === 1"><?= htmlspecialchars(__('auth.step_account'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span x-show="step === 2" x-cloak><?= htmlspecialchars(__('auth.step_validation'), ENT_QUOTES, 'UTF-8') ?></span>
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
                        <p class="mb-1 text-sm font-bold text-white"><?= htmlspecialchars(__('auth.register_access'), ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="mb-5 text-xs leading-relaxed text-white/45">
                            E-mail de connexion, prénom et nom du personnage, mot de passe.
                            <?php if ($prefillSlug !== ''): ?>
                                Espace ciblé : <span class="font-semibold text-emerald-400"><?= htmlspecialchars($prefillSlug, ENT_QUOTES, 'UTF-8') ?></span>.
                            <?php else: ?>
                                Un code d’invitation n’est pas obligatoire.
                            <?php endif; ?>
                        </p>

                        <div class="space-y-4">
                            <div>
                                <label class="login-label" for="community_code"><?= htmlspecialchars(__('auth.register_invite'), ENT_QUOTES, 'UTF-8') ?> <span class="normal-case tracking-normal text-white/30"><?= htmlspecialchars(__('auth.register_optional'), ENT_QUOTES, 'UTF-8') ?></span></label>
                                <input id="community_code" type="text" name="community_code" maxlength="64" autocomplete="off"
                                       placeholder="Si vous en avez reçu un"
                                       value="<?= htmlspecialchars($prefillCc, ENT_QUOTES, 'UTF-8') ?>"
                                       class="login-field uppercase tracking-wide placeholder:normal-case placeholder:tracking-normal">
                            </div>
                            <div>
                                <label class="login-label" for="email"><?= htmlspecialchars(__('auth.email'), ENT_QUOTES, 'UTF-8') ?></label>
                                <input id="email" type="email" name="email" data-lowercase="email" required autocomplete="email"
                                       placeholder="<?= htmlspecialchars(__('auth.placeholder_email'), ENT_QUOTES, 'UTF-8') ?>"
                                       value="<?= $val($old, 'email') ?>"
                                       class="login-field">
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="login-label" for="first_name">Prénom</label>
                                    <input id="first_name" type="text" name="first_name" required minlength="1" maxlength="100"
                                           autocomplete="given-name" placeholder="Prénom du personnage"
                                           value="<?= $val($old, 'first_name') ?>"
                                           class="login-field">
                                </div>
                                <div>
                                    <label class="login-label" for="last_name">Nom</label>
                                    <input id="last_name" type="text" name="last_name" required minlength="1" maxlength="100"
                                           autocomplete="family-name" placeholder="Nom du personnage"
                                           value="<?= $val($old, 'last_name') ?>"
                                           class="login-field">
                                </div>
                            </div>
                            <p class="text-xs text-white/35">Prénom et nom du personnage — une seule identité (plus de « nom affiché » séparé).</p>
                            <input type="hidden" name="display_name" id="display_name" value="<?= $val($old, 'display_name') ?>">
                            <div>
                                <label class="login-label" for="discord_handle">Discord <span class="normal-case tracking-normal text-white/30"><?= htmlspecialchars(__('auth.register_optional'), ENT_QUOTES, 'UTF-8') ?></span></label>
                                <input id="discord_handle" type="text" name="discord_handle" maxlength="120" autocomplete="off"
                                       placeholder="Votre pseudo Discord"
                                       value="<?= $val($old, 'discord_handle') ?>"
                                       class="login-field">
                            </div>
                            <div>
                                <label class="login-label" for="steam_profile"><?= htmlspecialchars(__('auth.register_steam'), ENT_QUOTES, 'UTF-8') ?> <span class="normal-case tracking-normal text-white/30"><?= htmlspecialchars(__('auth.register_optional'), ENT_QUOTES, 'UTF-8') ?></span></label>
                                <input id="steam_profile" type="text" name="steam_profile" maxlength="512" autocomplete="off"
                                       placeholder="Lien de votre profil Steam"
                                       value="<?= $val($old, 'steam_profile') ?>"
                                       class="login-field">
                                <p class="mt-1.5 text-xs text-white/35"><?= htmlspecialchars(__('auth.register_steam_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="login-label" for="password"><?= htmlspecialchars(__('auth.password'), ENT_QUOTES, 'UTF-8') ?></label>
                                    <div class="relative">
                                        <input type="password" :type="showPassword ? 'text' : 'password'" id="password" name="password" required minlength="8"
                                               autocomplete="new-password" placeholder="8 caractères minimum"
                                               class="login-field pr-12">
                                        <button type="button" @click="showPassword = !showPassword"
                                                class="absolute inset-y-0 right-0 flex items-center px-3 text-white/40 hover:text-white/80"
                                                :aria-label="showPassword ? <?= json_encode(__('auth.hide_password'), JSON_UNESCAPED_UNICODE) ?> : <?= json_encode(__('auth.show_password'), JSON_UNESCAPED_UNICODE) ?>">
                                            <svg x-show="!showPassword" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            <svg x-show="showPassword" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <label class="login-label" for="password_confirmation"><?= htmlspecialchars(__('auth.register_password_confirm'), ENT_QUOTES, 'UTF-8') ?></label>
                                    <div class="relative">
                                        <input type="password" :type="showPassword2 ? 'text' : 'password'" id="password_confirmation" name="password_confirmation" required minlength="8"
                                               autocomplete="new-password" placeholder="Retapez le mot de passe"
                                               class="login-field pr-12"
                                               @input="if ($el.value && document.getElementById('password').value !== $el.value) { $el.setCustomValidity(<?= json_encode(__('auth.flash_passwords_mismatch'), JSON_UNESCAPED_UNICODE) ?>); } else { $el.setCustomValidity(''); }">
                                        <button type="button" @click="showPassword2 = !showPassword2"
                                                class="absolute inset-y-0 right-0 flex items-center px-3 text-white/40 hover:text-white/80"
                                                :aria-label="showPassword2 ? <?= json_encode(__('auth.hide_password'), JSON_UNESCAPED_UNICODE) ?> : <?= json_encode(__('auth.show_password'), JSON_UNESCAPED_UNICODE) ?>">
                                            <svg x-show="!showPassword2" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            <svg x-show="showPassword2" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button type="button" @click="next()" class="hi-cta hi-cta-solid mt-6 w-full justify-center"><?= htmlspecialchars(__('auth.register_continue'), ENT_QUOTES, 'UTF-8') ?></button>
                    </div>

                    <div x-show="step === 2" x-ref="step2" x-cloak x-transition.opacity.duration.200ms>
                        <p class="mb-1 text-sm font-bold text-white"><?= htmlspecialchars(__('auth.register_last_step'), ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="mb-5 text-xs leading-relaxed text-white/45">
                            Confirmez les règles du compte, puis validez. Vous pourrez ensuite confirmer votre e-mail.
                        </p>

                        <div class="space-y-4 rounded-xl border border-white/10 bg-white/[0.03] p-4">
                            <label class="flex items-start gap-3 text-sm leading-relaxed text-white/70">
                                <input type="checkbox" name="accept_terms" value="1" required
                                       class="mt-1 h-4 w-4 rounded border-white/20 bg-transparent text-emerald-500 focus:ring-emerald-500/40"
                                       <?= !empty($old['accept_terms']) ? 'checked' : '' ?>>
                                <span>
                                    <?= htmlspecialchars(__('auth.register_accept_prefix'), ENT_QUOTES, 'UTF-8') ?>
                                    <a href="<?= htmlspecialchars(url('legal/site'), ENT_QUOTES, 'UTF-8') ?>#cgu" class="font-semibold text-emerald-400 hover:underline" target="_blank" rel="noopener"><?= htmlspecialchars(__('auth.register_terms'), ENT_QUOTES, 'UTF-8') ?></a>
                                    <?= htmlspecialchars(__('auth.register_accept_and'), ENT_QUOTES, 'UTF-8') ?>
                                    <a href="<?= htmlspecialchars(url('legal/site'), ENT_QUOTES, 'UTF-8') ?>#rgpd" class="font-semibold text-emerald-400 hover:underline" target="_blank" rel="noopener"><?= htmlspecialchars(__('auth.register_privacy'), ENT_QUOTES, 'UTF-8') ?></a>.
                                </span>
                            </label>
                        </div>

                        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row">
                            <button type="button" @click="prev()" class="hi-cta hi-cta-ghost w-full justify-center sm:w-auto sm:min-w-[8rem]"><?= htmlspecialchars(__('common.back'), ENT_QUOTES, 'UTF-8') ?></button>
                            <button type="submit" class="hi-cta hi-cta-solid w-full flex-1 justify-center"><?= htmlspecialchars(__('auth.register_submit'), ENT_QUOTES, 'UTF-8') ?></button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="mt-8 space-y-3 text-center text-sm text-white/50">
                <p>
                    <?= htmlspecialchars(__('auth.register_have_account'), ENT_QUOTES, 'UTF-8') ?>
                    <a href="<?= htmlspecialchars(url('login'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-400 transition hover:text-emerald-300"><?= htmlspecialchars(__('common.login'), ENT_QUOTES, 'UTF-8') ?></a>
                </p>
                <p class="text-xs leading-relaxed text-white/40">
                    <?= htmlspecialchars(__('auth.invite_code'), ENT_QUOTES, 'UTF-8') ?>
                    <a href="<?= htmlspecialchars(url('join'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-white/70 underline decoration-white/20 underline-offset-4 hover:text-white"><?= htmlspecialchars(__('auth.join_community'), ENT_QUOTES, 'UTF-8') ?></a>
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
