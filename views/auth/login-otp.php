<?php
$base = url('');
$error = \App\Core\Session::getFlash('error');
$info = \App\Core\Session::getFlash('info');
$emailMasked = $emailMasked ?? '—';
$expiresAt = (int) ($expiresAt ?? 0);
$title = $title ?? 'Double vérification';
$brand = function_exists('email_brand_name') ? email_brand_name() : 'Athena';
?>
<!DOCTYPE html>
<html lang="fr" class="h-full bg-black">
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
    <link href="<?= htmlspecialchars(asset_url('assets/css/home-impact.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <style>
        html, body {
            background: #050505;
            min-height: 100%;
        }
        .otp-cell {
            width: 2.75rem;
            height: 3.25rem;
            background: rgba(244, 244, 240, 0.04);
            border: 1px solid rgba(244, 244, 240, 0.22);
            color: #fff;
            font-family: Inter, "Segoe UI", system-ui, sans-serif;
            font-size: 1.5rem;
            font-weight: 800;
            text-align: center;
            caret-color: #34d399;
        }
        @media (min-width: 640px) {
            .otp-cell {
                width: 3.25rem;
                height: 3.75rem;
                font-size: 1.75rem;
            }
        }
        .otp-cell:focus {
            outline: none;
            border-color: rgba(52, 211, 153, 0.75);
            box-shadow: 0 0 0 3px rgba(52, 211, 153, 0.18);
            background: rgba(52, 211, 153, 0.06);
        }
        .otp-cell::placeholder { color: rgba(244, 244, 240, 0.2); }
        .otp-auth-section {
            padding-block: clamp(2.5rem, 6vh, 4.5rem);
            padding-inline: clamp(1.25rem, 4vw, 4rem);
        }
    </style>
</head>
<body class="home-impact min-h-[100svh] bg-[var(--hi-void,#050505)] text-[var(--hi-ink,#f4f4f0)] antialiased selection:bg-emerald-500 selection:text-slate-950">

<section class="relative flex min-h-[100svh] flex-col bg-[var(--hi-void,#050505)]">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,rgba(52,211,153,0.06),transparent_55%)]" aria-hidden="true"></div>

    <header class="relative z-10 shrink-0 border-b border-white/5 bg-black">
        <div class="mx-auto flex h-12 max-w-[100rem] items-center justify-between px-5 md:px-8">
            <a href="<?= htmlspecialchars(url(''), ENT_QUOTES, 'UTF-8') ?>" class="hi-kicker text-white/45 transition hover:text-white">Retour</a>
            <span class="text-[11px] font-black uppercase tracking-[0.32em] text-white"><?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="hi-kicker text-emerald-400/85">Connexion</span>
        </div>
    </header>

    <div class="relative z-10 flex flex-1 flex-col justify-center otp-auth-section">
        <p class="hi-kicker hi-kicker-glitch hi-reveal text-emerald-400/90">Double vérification</p>
        <h1 class="hi-display hi-hero-brand hi-glitch hi-reveal hi-reveal-delay mt-6 text-white" data-text="<?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?>">
            <span class="hi-glitch__main" aria-hidden="true"><?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?><span class="hi-glitch__dot">.</span></span>
        </h1>
        <p class="hi-body hi-reveal hi-reveal-delay mt-8 max-w-xl text-white/70">
            Un code à six chiffres a été envoyé à
            <span class="font-semibold text-white"><?= htmlspecialchars($emailMasked, ENT_QUOTES, 'UTF-8') ?></span>.
            Saisissez-le pour finaliser votre entrée.
        </p>

        <div class="hi-reveal hi-reveal-delay mt-8 max-w-xl space-y-3">
            <?php if ($error): ?>
                <p class="hi-body-sm text-red-300" role="alert"><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <?php if ($info): ?>
                <p class="hi-body-sm text-emerald-300/90" role="status"><?= htmlspecialchars((string) $info, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
        </div>

        <form method="post" action="<?= url('login/otp') ?>" id="login-otp-form" class="hi-reveal hi-reveal-delay mt-10 max-w-xl" novalidate>
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="otp_code" id="otp_code" value="">

            <fieldset class="min-w-0 border-0 p-0">
                <legend class="hi-kicker text-white/45">Code à six chiffres</legend>
                <div
                    id="otp-boxes"
                    class="mt-5 flex flex-wrap gap-2 sm:gap-3"
                    role="group"
                    aria-describedby="otp-hint"
                >
                    <?php for ($i = 0; $i < 6; $i++): ?>
                    <input
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="1"
                        data-otp-index="<?= (int) $i ?>"
                        class="otp-cell"
                        aria-label="Chiffre <?= (int) $i + 1 ?> sur 6"
                        <?= $i === 0 ? 'autofocus autocomplete="one-time-code"' : '' ?>
                    >
                    <?php endfor; ?>
                </div>
                <p id="otp-hint" class="mt-4 hi-body-sm text-white/40">
                    Collez les six chiffres d’un coup. Retour efface la case précédente.
                </p>
            </fieldset>

            <div class="mt-10 flex flex-wrap gap-3">
                <button type="submit" class="hi-cta hi-cta-solid">Continuer</button>
            </div>
        </form>

        <form method="post" action="<?= url('login/otp/resend') ?>" class="hi-reveal hi-reveal-delay mt-4">
            <?= \App\Core\Csrf::field() ?>
            <button type="submit" class="hi-cta hi-cta-ghost">Renvoyer un code</button>
        </form>

        <?php if ($expiresAt > 0): ?>
            <p class="hi-reveal hi-reveal-delay mt-8 hi-body-sm text-white/40">
                Valable jusqu’au
                <time class="text-white/70" datetime="<?= htmlspecialchars(date('c', $expiresAt), ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars(date('d/m/Y \à H:i', $expiresAt), ENT_QUOTES, 'UTF-8') ?>
                </time>
            </p>
        <?php endif; ?>
    </div>

    <div class="relative z-10 shrink-0 border-t border-white/10 bg-black">
        <div class="mx-auto flex max-w-[100rem] items-center justify-between gap-4 px-5 py-2 md:px-8">
            <p class="hi-body-sm text-[10px] uppercase tracking-[0.14em] text-white/45">Sécurité du compte</p>
            <a href="<?= htmlspecialchars(url('login'), ENT_QUOTES, 'UTF-8') ?>" class="hi-body-sm text-[10px] uppercase tracking-[0.14em] text-emerald-400/80 transition hover:text-emerald-300">
                Autre compte
            </a>
        </div>
    </div>
</section>

<script>
(function () {
    var form = document.getElementById('login-otp-form');
    var hidden = document.getElementById('otp_code');
    var cells = Array.prototype.slice.call(document.querySelectorAll('.otp-cell'));
    if (!form || !hidden || cells.length !== 6) {
        return;
    }

    function syncHidden() {
        hidden.value = cells.map(function (c) { return (c.value || '').replace(/\D/g, '').slice(0, 1); }).join('');
    }

    function focusIndex(i) {
        if (i < 0) i = 0;
        if (i > 5) i = 5;
        cells[i].focus();
        try { cells[i].select(); } catch (e) {}
    }

    cells.forEach(function (cell, idx) {
        cell.addEventListener('input', function () {
            var v = (cell.value || '').replace(/\D/g, '');
            if (v.length > 1) v = v.slice(0, 1);
            cell.value = v;
            syncHidden();
            if (v && idx < 5) focusIndex(idx + 1);
        });

        cell.addEventListener('keydown', function (e) {
            if (e.key === 'Backspace' && !cell.value && idx > 0) {
                e.preventDefault();
                cells[idx - 1].value = '';
                syncHidden();
                focusIndex(idx - 1);
            }
            if (e.key === 'ArrowLeft' && idx > 0) {
                e.preventDefault();
                focusIndex(idx - 1);
            }
            if (e.key === 'ArrowRight' && idx < 5) {
                e.preventDefault();
                focusIndex(idx + 1);
            }
        });

        cell.addEventListener('paste', function (e) {
            e.preventDefault();
            var raw = (e.clipboardData || window.clipboardData).getData('text') || '';
            var digits = raw.replace(/\D/g, '').slice(0, 6);
            for (var j = 0; j < 6; j++) {
                cells[j].value = digits.charAt(j) || '';
            }
            syncHidden();
            focusIndex(digits.length >= 6 ? 5 : Math.max(0, digits.length - 1));
        });
    });

    form.addEventListener('submit', function (e) {
        syncHidden();
        if (!/^\d{6}$/.test(hidden.value)) {
            e.preventDefault();
            focusIndex(0);
        }
    });

    syncHidden();
})();
</script>
</body>
</html>
