<?php
declare(strict_types=1);

/** @var string $query */
/** @var bool $canSearchDocuments */
/** @var bool $canSearchPersonnel */

$baseUrl = url('');
$searchApiUrl = url('api/portal/search');
$q = $query ?? '';
$canSearchDocuments = $canSearchDocuments ?? true;
$canSearchPersonnel = $canSearchPersonnel ?? true;
?>
<div
    id="portal-search-root"
    class="relative"
    data-api-url="<?= htmlspecialchars($searchApiUrl) ?>"
    data-initial-q="<?= htmlspecialchars($q) ?>"
    data-min-length="2"
>
    <div class="absolute inset-0 -z-10 overflow-hidden pointer-events-none" aria-hidden="true">
        <div class="absolute left-0 top-0 h-72 w-72 rounded-full bg-sky-100 blur-3xl opacity-60"></div>
        <div class="absolute right-0 top-24 h-80 w-80 rounded-full bg-slate-200 blur-3xl opacity-60"></div>
    </div>

    <section class="mx-auto max-w-6xl px-6 py-12 lg:py-20">
        <div class="grid gap-10 lg:grid-cols-[1.25fr_0.75fr] lg:items-start">
            <div class="rounded-[2rem] border border-slate-200 bg-white/90 backdrop-blur shadow-[0_20px_60px_rgba(15,23,42,0.08)] overflow-hidden">
                <div class="border-b border-slate-200 bg-gradient-to-r from-slate-950 via-slate-900 to-sky-900 px-8 py-10 text-white">
                    <p class="text-[11px] font-black uppercase tracking-[0.35em] text-sky-200">
                        Athena
                    </p>
                    <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                        Recherche portail
                    </h1>
                    <p class="mt-4 max-w-2xl text-sm leading-6 text-slate-200">
                        Accédez rapidement à l’ensemble des contenus du portail depuis un point d’entrée unique.
                        Les résultats se mettent à jour pendant la saisie (après 2 caractères).
                    </p>
                </div>

                <div class="px-8 py-8">
                    <form id="portal-search-form" class="space-y-6" autocomplete="off">
                        <div>
                            <label for="global-search" class="mb-3 block text-xs font-bold uppercase tracking-[0.25em] text-slate-500">
                                Recherche unifiée
                            </label>
                            <div class="relative">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-5 text-slate-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m1.85-5.15a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z" />
                                    </svg>
                                </div>
                                <input
                                    id="global-search"
                                    name="q"
                                    type="search"
                                    placeholder="Document, membre, sujet de forum…"
                                    value="<?= htmlspecialchars($q) ?>"
                                    class="w-full rounded-2xl border border-slate-200 bg-slate-50 pl-14 pr-36 py-4 text-sm font-medium text-slate-900 shadow-sm outline-none transition focus:border-sky-500 focus:bg-white focus:ring-4 focus:ring-sky-100"
                                    autocomplete="off"
                                    spellcheck="false"
                                    enterkeyhint="search"
                                    aria-describedby="portal-search-empty-hint"
                                >
                                <button
                                    type="submit"
                                    class="absolute right-2 top-2 inline-flex h-12 items-center justify-center rounded-xl bg-slate-950 px-5 text-sm font-semibold text-white transition hover:bg-sky-900"
                                >
                                    Rechercher
                                </button>
                            </div>
                            <p id="portal-search-empty-hint" class="mt-3 text-sm text-slate-500<?= $q !== '' ? ' hidden' : '' ?>">
                                Astuce : <kbd class="rounded border border-slate-200 bg-white px-1.5 py-0.5 font-mono text-xs text-slate-700">Ctrl</kbd>
                                + <kbd class="rounded border border-slate-200 bg-white px-1.5 py-0.5 font-mono text-xs text-slate-700">K</kbd>
                                ouvre cette page depuis n’importe où.
                            </p>
                            <div id="portal-search-status" class="mt-3 min-h-[1.25rem] text-sm" role="status"></div>
                        </div>

                        <fieldset>
                            <legend class="sr-only">Sources de recherche</legend>
                            <p class="mb-3 text-xs font-bold uppercase tracking-[0.25em] text-slate-500">Périmètre</p>
                            <div class="grid gap-3 sm:grid-cols-3">
                                <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-4 text-sm font-medium text-slate-700 transition hover:border-sky-300 hover:bg-sky-50 has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-50">
                                    <input
                                        id="scope-documents"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-slate-300 text-sky-700 focus:ring-sky-500"
                                        <?= $canSearchDocuments ? 'checked' : '' ?>
                                        <?= $canSearchDocuments ? '' : 'disabled' ?>
                                    >
                                    <span>Documents</span>
                                </label>
                                <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-4 text-sm font-medium text-slate-700 transition hover:border-sky-300 hover:bg-sky-50">
                                    <input
                                        id="scope-forum"
                                        type="checkbox"
                                        checked
                                        class="h-4 w-4 rounded border-slate-300 text-sky-700 focus:ring-sky-500"
                                    >
                                    <span>Forum</span>
                                </label>
                                <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-4 text-sm font-medium text-slate-700 transition hover:border-sky-300 hover:bg-sky-50 has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-50">
                                    <input
                                        id="scope-personnel"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-slate-300 text-sky-700 focus:ring-sky-500"
                                        <?= $canSearchPersonnel ? 'checked' : '' ?>
                                        <?= $canSearchPersonnel ? '' : 'disabled' ?>
                                    >
                                    <span>Personnel</span>
                                </label>
                            </div>
                            <?php if (!$canSearchDocuments || !$canSearchPersonnel): ?>
                            <p class="mt-3 text-xs text-slate-500">
                                <?php if (!$canSearchDocuments): ?>
                                    <span class="mr-2 inline-block">Les documents ne sont pas disponibles pour votre rôle.</span>
                                <?php endif; ?>
                                <?php if (!$canSearchPersonnel): ?>
                                    <span class="inline-block">L’annuaire personnel est restreint pour votre rôle.</span>
                                <?php endif; ?>
                            </p>
                            <?php endif; ?>
                        </fieldset>
                    </form>
                </div>
            </div>

            <aside class="space-y-6 lg:sticky lg:top-28">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xs font-black uppercase tracking-[0.28em] text-slate-500">Résultats</h2>
                    <p id="portal-search-live" class="mt-2 text-2xl font-black tabular-nums text-slate-900" aria-live="polite"></p>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">
                        Les liens ouvrent directement la fiche document, le sujet forum ou la fiche personnel.
                    </p>
                </div>
                <div class="rounded-3xl border border-dashed border-slate-200 bg-slate-50/80 p-6">
                    <p class="text-xs font-black uppercase tracking-[0.28em] text-slate-500">Raccourcis</p>
                    <ul class="mt-4 space-y-3 text-sm text-slate-700">
                        <li class="flex gap-2">
                            <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-white text-sky-700 shadow-sm ring-1 ring-slate-200" aria-hidden="true">1</span>
                            <span>Cochez les modules à interroger avant ou après la saisie.</span>
                        </li>
                        <li class="flex gap-2">
                            <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-white text-sky-700 shadow-sm ring-1 ring-slate-200" aria-hidden="true">2</span>
                            <span>Échap dans le champ vide la requête.</span>
                        </li>
                    </ul>
                </div>
                <a href="<?= htmlspecialchars($baseUrl) ?>/" class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-800 shadow-sm transition hover:border-sky-300 hover:bg-sky-50">
                    ← Retour à l’accueil
                </a>
            </aside>
        </div>

        <div id="portal-search-results" class="mt-10 min-h-[12rem] max-h-[min(70vh,720px)] overflow-y-auto pr-1"></div>
    </section>
</div>
<script defer src="<?= htmlspecialchars($baseUrl) ?>/assets/js/portal_search.js"></script>
