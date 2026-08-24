<?php
declare(strict_types=1);
/** @var string $command_palette_api URL absolue ou relative vers l’API de recherche portail */
$command_palette_api = $command_palette_api ?? url('api/portal/search');
?>
<dialog id="portal-command-palette" class="max-h-[min(90vh,36rem)] w-[min(100vw-1.5rem,40rem)] overflow-hidden rounded-2xl border border-slate-200 bg-white p-0 text-slate-900 shadow-2xl backdrop:bg-slate-900/50" data-api-url="<?= htmlspecialchars((string) $command_palette_api, ENT_QUOTES, 'UTF-8') ?>">
    <div class="border-b border-slate-100 px-4 py-3 sm:px-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Accès rapide</p>
            <button type="button" class="rounded-lg px-2 py-1 text-xs font-semibold text-slate-500 transition hover:bg-slate-100 hover:text-slate-800" data-portal-command-palette-close>Échap</button>
        </div>
        <div class="mt-3 flex flex-wrap gap-2">
            <a href="<?= htmlspecialchars(url('hub'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-800 transition hover:bg-white">Hub</a>
            <a href="<?= htmlspecialchars(url('activite'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-800 transition hover:bg-white">Mon activité</a>
            <a href="<?= htmlspecialchars(url('search'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-800 transition hover:bg-white">Recherche plein écran</a>
            <a href="<?= htmlspecialchars(url('aujourdhui'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-800 transition hover:bg-white">Aujourd’hui</a>
        </div>
        <label for="portal-command-palette-q" class="mt-4 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Rechercher</label>
        <input id="portal-command-palette-q" type="search" autocomplete="off" placeholder="Document, sujet, membre…" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-sky-500 focus:bg-white focus:ring-2 focus:ring-sky-100" />
        <p class="mt-2 text-[11px] text-slate-500">Au moins 2 caractères. Les résultats respectent vos droits d’accès.</p>
    </div>
    <div id="portal-command-palette-results" class="max-h-[min(50vh,22rem)] overflow-y-auto px-3 py-3 sm:px-4" aria-live="polite"></div>
</dialog>
