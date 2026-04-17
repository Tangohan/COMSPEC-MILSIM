<?php
$base = url('');
$error = \App\Core\Session::getFlash('error');
$info = \App\Core\Session::getFlash('info');
$emailMasked = $emailMasked ?? '—';
$expiresAt = (int) ($expiresAt ?? 0);
$title = $title ?? 'Validation OTP';
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
<main class="mx-auto flex min-h-screen max-w-lg items-center px-4 py-10">
    <div class="w-full rounded-3xl border border-slate-200 bg-white p-8 shadow-sm">
        <p class="text-[11px] font-black uppercase tracking-[0.3em] text-emerald-700/90">Connexion sécurisée</p>
        <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-900">Validation OTP e-mail</h1>
        <p class="mt-2 text-sm text-slate-600">Un code à 6 chiffres a été envoyé à <span class="font-mono"><?= htmlspecialchars($emailMasked, ENT_QUOTES, 'UTF-8') ?></span>.</p>

        <?php if ($error): ?><div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-900"><?= htmlspecialchars((string) $error) ?></div><?php endif; ?>
        <?php if ($info): ?><div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900"><?= htmlspecialchars((string) $info) ?></div><?php endif; ?>

        <form method="post" action="<?= url('login/otp') ?>" class="mt-6 space-y-4">
            <?= \App\Core\Csrf::field() ?>
            <div>
                <label for="otp_code" class="mb-1 block text-xs font-black uppercase tracking-[0.2em] text-slate-500">Code OTP</label>
                <input type="text" id="otp_code" name="otp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required class="w-full rounded-xl border border-slate-300 px-4 py-3 font-mono text-lg tracking-[0.35em] focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/30" placeholder="000000">
            </div>
            <button type="submit" class="w-full rounded-xl bg-slate-900 px-4 py-3 text-sm font-black uppercase tracking-[0.2em] text-white hover:bg-emerald-700">Valider la connexion</button>
        </form>

        <form method="post" action="<?= url('login/otp/resend') ?>" class="mt-3">
            <?= \App\Core\Csrf::field() ?>
            <button type="submit" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-xs font-black uppercase tracking-[0.2em] text-slate-700 hover:bg-slate-50">Renvoyer un code</button>
        </form>

        <?php if ($expiresAt > 0): ?>
        <p class="mt-4 text-xs text-slate-500">Expiration du code : <?= htmlspecialchars(date('d/m/Y H:i:s', $expiresAt), ENT_QUOTES, 'UTF-8') ?> (heure serveur).</p>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
