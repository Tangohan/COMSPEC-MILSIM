<?php
/** @var list<array{id: string, label: string, description: string, href: string}> $c2_modes */
$c2_modes = $c2_modes ?? [];
?>
<div class="bg-slate-50 pb-16 sm:pb-24">
    <div class="border-b border-slate-200 bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 text-white">
        <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 sm:py-14 lg:px-8">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-300/90">Coordination</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">Poste de commandement</h1>
            <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-300 sm:text-base">
                Choisissez le mode de travail adapté à la situation : carte, supervision, terrain ou dossier opérateur.
            </p>
        </div>
    </div>

    <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
        <nav class="mb-8 flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-white p-2 shadow-sm" aria-label="Modes de coordination">
            <?php foreach ($c2_modes as $mode): ?>
                <a href="<?= htmlspecialchars((string) ($mode['href'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                   class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-emerald-50 hover:text-emerald-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
                    <?= htmlspecialchars((string) ($mode['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="grid gap-5 sm:grid-cols-2">
            <?php foreach ($c2_modes as $mode): ?>
                <a href="<?= htmlspecialchars((string) ($mode['href'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                   class="block rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-emerald-300 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
                    <h2 class="text-lg font-bold text-slate-900"><?= htmlspecialchars((string) ($mode['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
                    <p class="mt-2 text-sm text-slate-600"><?= htmlspecialchars((string) ($mode['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
