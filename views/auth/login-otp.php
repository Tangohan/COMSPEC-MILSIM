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
<main class="mx-auto flex min-h-screen max-w-lg items-center px-4 py-12 sm:py-16">
    <div class="w-full overflow-hidden rounded-3xl border border-slate-200/90 bg-white shadow-md ring-1 ring-slate-900/[0.04]">
        <div class="border-l-[5px] border-l-emerald-600 bg-gradient-to-br from-emerald-50/60 via-white to-white px-6 pb-8 pt-7 sm:px-8 sm:pb-10 sm:pt-8">
            <p class="text-[11px] font-black uppercase tracking-[0.28em] text-emerald-800/80">Connexion</p>
            <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-900 sm:text-[1.65rem]">Code reçu par e-mail</h1>
            <p class="mt-3 text-sm leading-relaxed text-slate-600">Saisissez le <strong>code à six chiffres</strong> envoyé à <span class="break-all font-mono text-slate-800"><?= htmlspecialchars($emailMasked, ENT_QUOTES, 'UTF-8') ?></span>.</p>

            <?php if ($error): ?><div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-900"><?= htmlspecialchars((string) $error) ?></div><?php endif; ?>
            <?php if ($info): ?><div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900"><?= htmlspecialchars((string) $info) ?></div><?php endif; ?>

            <form method="post" action="<?= url('login/otp') ?>" class="mt-8 space-y-5">
                <?= \App\Core\Csrf::field() ?>
                <div>
                    <label for="otp_code" class="mb-2 block text-sm font-semibold text-slate-800">Code à six chiffres</label>
                    <input type="text" id="otp_code" name="otp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3.5 text-center font-mono text-xl tracking-[0.4em] text-slate-900 shadow-inner focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/25" placeholder="000000" aria-describedby="otp-hint">
                    <p id="otp-hint" class="mt-2 text-xs text-slate-500">Les chiffres uniquement, sans espace.</p>
                </div>
                <button type="submit" class="w-full rounded-xl bg-emerald-700 px-4 py-3.5 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2">Continuer</button>
            </form>

            <form method="post" action="<?= url('login/otp/resend') ?>" class="mt-4">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-emerald-200 hover:bg-emerald-50/50 hover:text-emerald-900">Renvoyer un code</button>
            </form>

            <?php if ($expiresAt > 0): ?>
            <p class="mt-6 border-t border-slate-200/80 pt-5 text-xs leading-relaxed text-slate-500">Ce code n’est plus valable après le <span class="font-medium text-slate-700"><?= htmlspecialchars(date('d/m/Y \à H:i', $expiresAt), ENT_QUOTES, 'UTF-8') ?></span>.</p>
            <?php endif; ?>
        </div>
    </div>
</main>
</body>
</html>
