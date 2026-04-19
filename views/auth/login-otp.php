<?php
$base = url('');
$error = \App\Core\Session::getFlash('error');
$info = \App\Core\Session::getFlash('info');
$emailMasked = $emailMasked ?? '—';
$expiresAt = (int) ($expiresAt ?? 0);
$title = $title ?? 'Double vérification';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> — Athena</title>
    <?php $tailwindBaseUrl = $base; require base_path('views/partials/tailwind_cdn_or_build.php'); ?>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
<main class="mx-auto flex min-h-screen max-w-lg items-center px-4 py-10 sm:px-6 sm:py-14">
    <div class="w-full overflow-hidden rounded-3xl border border-slate-200/90 bg-white shadow-md ring-1 ring-slate-900/[0.04]">
        <div class="border-l-[5px] border-l-emerald-600 bg-gradient-to-br from-emerald-50/70 via-white to-white px-6 pb-10 pt-10 sm:px-10 sm:pb-12 sm:pt-12">
            <header class="space-y-3">
                <p class="text-[11px] font-black uppercase tracking-[0.28em] text-emerald-800/90">Connexion</p>
                <h1 class="text-2xl font-black tracking-tight text-slate-900 sm:text-[1.7rem] sm:leading-tight">Code reçu par e-mail</h1>
                <p class="max-w-md text-sm leading-relaxed text-slate-600">Saisissez le <strong class="text-slate-800">code à six chiffres</strong> envoyé à <span class="break-all font-mono text-sm font-semibold text-slate-800"><?= htmlspecialchars($emailMasked, ENT_QUOTES, 'UTF-8') ?></span>.</p>
            </header>

            <div class="mt-8 space-y-4">
                <?php if ($error): ?>
                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3.5 text-sm font-medium leading-snug text-red-900" role="alert"><?= htmlspecialchars((string) $error) ?></div>
                <?php endif; ?>
                <?php if ($info): ?>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50/95 px-4 py-3.5 text-sm font-medium leading-snug text-emerald-950" role="status"><?= htmlspecialchars((string) $info) ?></div>
                <?php endif; ?>
            </div>

            <form method="post" action="<?= url('login/otp') ?>" id="login-otp-form" class="mt-10 space-y-8" novalidate>
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="otp_code" id="otp_code" value="">

                <fieldset class="min-w-0 border-0 p-0">
                    <legend class="mb-4 block text-sm font-semibold text-slate-800">Code à six chiffres</legend>
                    <div
                        id="otp-boxes"
                        class="flex flex-wrap justify-center gap-2 sm:justify-start sm:gap-2.5"
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
                            class="otp-cell h-12 w-11 shrink-0 rounded-xl border border-slate-300 bg-white text-center font-mono text-xl font-bold text-slate-900 shadow-sm transition placeholder:text-slate-300 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 sm:h-14 sm:w-12 sm:text-2xl"
                            aria-label="Chiffre <?= (int) $i + 1 ?> sur 6"
                            <?= $i === 0 ? 'autofocus autocomplete="one-time-code"' : '' ?>
                        >
                        <?php endfor; ?>
                    </div>
                    <p id="otp-hint" class="mt-4 text-center text-xs leading-relaxed text-slate-500 sm:text-left">Vous pouvez coller les six chiffres d’un coup. Touche Retour efface la case précédente.</p>
                </fieldset>

                <div class="space-y-3 pt-1">
                    <button type="submit" class="w-full rounded-xl bg-emerald-700 px-4 py-3.5 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2">Continuer</button>
                </div>
            </form>

            <form method="post" action="<?= url('login/otp/resend') ?>" class="mt-6">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-emerald-300 hover:bg-emerald-50/60 hover:text-emerald-900">Renvoyer un code</button>
            </form>

            <?php if ($expiresAt > 0): ?>
            <footer class="mt-10 border-t border-slate-200/90 pt-6">
                <p class="text-center text-xs leading-relaxed text-slate-500 sm:text-left">
                    Ce code n’est plus valable après le <time class="font-semibold text-slate-700" datetime="<?= htmlspecialchars(date('c', $expiresAt), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(date('d/m/Y \à H:i', $expiresAt), ENT_QUOTES, 'UTF-8') ?></time>.
                </p>
            </footer>
            <?php endif; ?>
        </div>
    </div>
</main>
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
        if (i < 0) {
            i = 0;
        }
        if (i > 5) {
            i = 5;
        }
        cells[i].focus();
        try {
            cells[i].select();
        } catch (e) {}
    }

    cells.forEach(function (cell, idx) {
        cell.addEventListener('input', function () {
            var v = (cell.value || '').replace(/\D/g, '');
            if (v.length > 1) {
                v = v.slice(0, 1);
            }
            cell.value = v;
            syncHidden();
            if (v && idx < 5) {
                focusIndex(idx + 1);
            }
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
