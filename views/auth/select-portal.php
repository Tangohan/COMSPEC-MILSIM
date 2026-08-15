<?php
/** @var bool $canTba */
/** @var string $tenantName */
/** @var string $displayName */
/** @var string $callsign */
$error = \App\Core\Session::getFlash('error');
$title = $title ?? 'Choisir un espace';
$who = trim($displayName !== '' ? $displayName : $callsign);
$community = trim((string) $tenantName);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> — Athena</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'IBM Plex Sans', system-ui, sans-serif; }
        .portal-mono { font-family: 'IBM Plex Mono', ui-monospace, monospace; }
    </style>
</head>
<body class="min-h-screen bg-[#070b10] text-slate-100 antialiased flex items-center justify-center p-6">
    <div class="w-full max-w-3xl">
        <div class="mb-10 text-center">
            <p class="portal-mono text-[11px] tracking-[0.35em] uppercase text-cyan-400/80 mb-3">Accès session</p>
            <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-white">Où voulez-vous entrer&nbsp;?</h1>
            <?php if ($who !== '' || $community !== ''): ?>
                <p class="mt-3 text-sm text-slate-400">
                    <?php if ($who !== ''): ?><span class="text-slate-200"><?= htmlspecialchars($who) ?></span><?php endif; ?>
                    <?php if ($who !== '' && $community !== ''): ?> · <?php endif; ?>
                    <?php if ($community !== ''): ?><?= htmlspecialchars($community) ?><?php endif; ?>
                </p>
            <?php endif; ?>
        </div>

        <?php if ($error): ?>
            <?php $flash_variant = 'error'; $flash_message = $error; require base_path('views/partials/flash_message.php'); ?>
        <?php endif; ?>

        <form method="post" action="<?= url('login/choisir-espace') ?>" class="space-y-6">
            <?= \App\Core\Csrf::field() ?>
            <div class="grid gap-4 sm:grid-cols-2">
                <?php if (!empty($canTba)): ?>
                <label class="group relative cursor-pointer rounded-xl border border-slate-700/80 bg-slate-900/70 p-6 transition hover:border-amber-400/60 hover:bg-slate-900 has-[:checked]:border-amber-400 has-[:checked]:ring-1 has-[:checked]:ring-amber-400/40">
                    <input type="radio" name="portal" value="tba" class="sr-only" required>
                    <span class="portal-mono text-[10px] tracking-[0.3em] uppercase text-amber-400">Administration</span>
                    <span class="mt-3 block text-lg font-semibold text-white">Tableau de bord administratif</span>
                    <span class="mt-2 block text-sm leading-relaxed text-slate-400">Paramétrage de la communauté, effectifs, recrutement, modération et outils de commandement.</span>
                </label>
                <?php endif; ?>

                <label class="group relative cursor-pointer rounded-xl border border-slate-700/80 bg-slate-900/70 p-6 transition hover:border-cyan-400/60 hover:bg-slate-900 has-[:checked]:border-cyan-400 has-[:checked]:ring-1 has-[:checked]:ring-cyan-400/40<?= empty($canTba) ? ' sm:col-span-2' : '' ?>">
                    <input type="radio" name="portal" value="jnet" class="sr-only" <?= empty($canTba) ? 'checked' : '' ?> required>
                    <span class="portal-mono text-[10px] tracking-[0.3em] uppercase text-cyan-400">Extranet opérationnel</span>
                    <span class="mt-3 block text-lg font-semibold text-white">JNET Extranet</span>
                    <span class="mt-2 block text-sm leading-relaxed text-slate-400">Portail de situation : briefing, théâtre, courrier interne, intentions et applications de mission.</span>
                </label>
            </div>

            <label class="flex items-center gap-3 text-sm text-slate-400 cursor-pointer select-none">
                <input type="checkbox" name="remember" value="1" class="rounded border-slate-600 bg-slate-900 text-cyan-500 focus:ring-cyan-500/40">
                Se souvenir de ce choix sur cet appareil
            </label>

            <button type="submit" class="w-full rounded-xl bg-cyan-500 px-6 py-3.5 text-sm font-semibold uppercase tracking-[0.2em] text-slate-950 transition hover:bg-cyan-400">
                Entrer
            </button>
        </form>

        <div class="mt-8 text-center text-xs text-slate-500">
            <form method="post" action="<?= url('logout') ?>" class="inline">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="text-slate-400 hover:text-cyan-300 underline-offset-2 hover:underline">Se déconnecter</button>
            </form>
        </div>
    </div>
<?php require base_path('views/partials/cookie_banner.php'); ?>
</body>
</html>
