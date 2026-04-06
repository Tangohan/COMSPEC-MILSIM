<?php
declare(strict_types=1);
$modpacks = $modpacks ?? [];
$gradients = [
    'from-emerald-800 via-emerald-900 to-slate-950',
    'from-sky-800 via-indigo-900 to-slate-950',
    'from-teal-800 via-emerald-950 to-slate-950',
    'from-slate-700 via-slate-800 to-slate-950',
    'from-cyan-800 via-teal-900 to-slate-950',
    'from-stone-700 via-stone-800 to-stone-950',
];
?>
<div class="relative overflow-hidden bg-gradient-to-b from-slate-50 via-white to-slate-100">
    <div class="pointer-events-none absolute inset-x-0 top-0 h-72 bg-gradient-to-b from-emerald-500/10 to-transparent" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-6xl px-4 pb-16 pt-10 sm:px-6 lg:px-8 lg:pb-20 lg:pt-14">
        <nav class="mb-8 flex flex-wrap items-center gap-2 text-sm font-medium text-slate-600" aria-label="Fil d’Ariane">
            <a href="<?= htmlspecialchars(url('dashboard'), ENT_QUOTES, 'UTF-8') ?>" class="text-sky-700 hover:text-sky-900 hover:underline">Tableau de bord</a>
            <span class="text-slate-300" aria-hidden="true">/</span>
            <span class="font-semibold text-slate-900">Modpacks</span>
        </nav>

        <header class="mb-10 max-w-2xl">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-800">Ressources mission</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">Packs de mods de l’unité</h1>
            <p class="mt-4 text-base leading-relaxed text-slate-600">
                Retrouvez ici les environnements de jeu validés par votre communauté : versions, tailles et documents associés.
                Ouvrez une fiche pour télécharger le pack ou consulter les visuels.
            </p>
        </header>

        <?php if (empty($modpacks)): ?>
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white/80 px-6 py-16 text-center shadow-sm">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-slate-500" aria-hidden="true">
                    <svg class="h-8 w-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 .41-.21.815-.688 1.194C17.064 15.876 14.718 16.5 12 16.5c-2.717 0-5.064-.624-7.062-1.931-.478-.38-.688-.784-.688-1.194z"/></svg>
                </div>
                <h2 class="mt-6 text-lg font-semibold text-slate-900">Aucun modpack publié pour l’instant</h2>
                <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-600">
                    Lorsque votre communauté aura publié un pack, il apparaîtra ici. En attendant, vérifiez le tableau de bord ou les consignes de mission.
                </p>
                <a href="<?= htmlspecialchars(url('dashboard'), ENT_QUOTES, 'UTF-8') ?>" class="mt-8 inline-flex items-center justify-center rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800">
                    Retour au tableau de bord
                </a>
            </div>
        <?php else: ?>
            <ul class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                <?php foreach ($modpacks as $idx => $mp): ?>
                    <?php
                    $slug = (string) ($mp['slug'] ?? '');
                    $href = $slug !== '' ? url('modpacks/' . rawurlencode($slug)) : url('modpacks');
                    $cover = $mp['cover_url'] ?? null;
                    $g = $gradients[$idx % count($gradients)];
                    $ver = trim((string) ($mp['version'] ?? ''));
                    $sizeFmt = (string) ($mp['size_formatted'] ?? '—');
                    $hasFile = !empty($mp['file_path']);
                    ?>
                    <li>
                        <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"
                           class="group flex h-full flex-col overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-black/[0.03] transition hover:-translate-y-0.5 hover:border-emerald-200/90 hover:shadow-lg hover:shadow-emerald-900/5">
                            <div class="relative aspect-[16/10] overflow-hidden bg-slate-900">
                                <?php if ($cover): ?>
                                    <img src="<?= htmlspecialchars($cover, ENT_QUOTES, 'UTF-8') ?>" alt="" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]" loading="lazy" />
                                <?php else: ?>
                                    <div class="absolute inset-0 bg-gradient-to-br <?= htmlspecialchars($g, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></div>
                                    <div class="absolute inset-0 flex items-center justify-center opacity-40" aria-hidden="true">
                                        <svg class="h-20 w-20 text-white/90" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 .41-.21.815-.688 1.194C17.064 15.876 14.718 16.5 12 16.5c-2.717 0-5.064-.624-7.062-1.931-.478-.38-.688-.784-.688-1.194z"/></svg>
                                    </div>
                                <?php endif; ?>
                                <div class="absolute inset-0 bg-gradient-to-t from-black/55 via-transparent to-transparent" aria-hidden="true"></div>
                                <?php if ($ver !== ''): ?>
                                    <span class="absolute right-3 top-3 rounded-full bg-black/45 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-white backdrop-blur-sm">
                                        v<?= htmlspecialchars($ver, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php endif; ?>
                                <div class="absolute bottom-3 left-3 right-3">
                                    <p class="text-lg font-bold leading-tight text-white drop-shadow-md"><?= htmlspecialchars((string) ($mp['name'] ?? 'Modpack'), ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                            </div>
                            <div class="flex flex-1 flex-col p-5">
                                <?php if (!empty($mp['excerpt'])): ?>
                                    <p class="text-sm leading-relaxed text-slate-600 line-clamp-2"><?= htmlspecialchars((string) $mp['excerpt'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php else: ?>
                                    <p class="text-sm italic text-slate-400">Pas de description courte — ouvrez la fiche pour plus de détails.</p>
                                <?php endif; ?>
                                <div class="mt-auto flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                                    <span class="text-xs font-medium text-slate-500">
                                        <?php if ($hasFile): ?>
                                            <span class="inline-flex items-center gap-1.5 text-slate-700">
                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>
                                                Téléchargement <?= $sizeFmt !== '' && $sizeFmt !== '0 o' ? ' · ' . htmlspecialchars($sizeFmt, ENT_QUOTES, 'UTF-8') : '' ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-amber-800">Fiche informative</span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="text-sm font-semibold text-emerald-700 transition group-hover:text-emerald-800">
                                        Ouvrir la fiche
                                        <span aria-hidden="true" class="inline-block transition group-hover:translate-x-0.5">→</span>
                                    </span>
                                </div>
                            </div>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
