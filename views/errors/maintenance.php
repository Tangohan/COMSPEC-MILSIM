<?php

declare(strict_types=1);

$appLabel = $appName ?? (function_exists('config') ? (string) config('app.name', 'Athena') : 'Athena');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Maintenance en cours', ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($appLabel, ENT_QUOTES, 'UTF-8') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <div class="min-h-screen flex items-center justify-center px-6 py-12">
        <div class="w-full max-w-3xl overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl shadow-slate-200/50">
            <div class="border-b border-slate-200 bg-slate-950 px-8 py-6 text-white">
                <p class="text-[11px] font-black uppercase tracking-[0.28em] text-emerald-400"><?= htmlspecialchars($appLabel, ENT_QUOTES, 'UTF-8') ?></p>
                <h1 class="mt-3 text-3xl font-black tracking-tight">
                    <?= htmlspecialchars($title ?? 'Maintenance en cours', ENT_QUOTES, 'UTF-8') ?>
                </h1>
            </div>

            <div class="px-8 py-8">
                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-amber-900">
                    <p class="text-sm font-semibold leading-6">
                        <?= nl2br(htmlspecialchars($message ?? 'Le service est momentanément indisponible.', ENT_QUOTES, 'UTF-8')) ?>
                    </p>
                </div>

                <?php if (!empty($endsAt)): ?>
                    <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4">
                        <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-500">
                            Fin prévisionnelle
                        </p>
                        <p class="mt-2 text-base font-semibold text-slate-900">
                            <?= htmlspecialchars((string) $endsAt, ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($code)): ?>
                    <div class="mt-4">
                        <p class="text-xs text-slate-500">
                            Code maintenance : <span class="font-mono font-semibold text-slate-800"><?= htmlspecialchars((string) $code, ENT_QUOTES, 'UTF-8') ?></span>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
