<?php
declare(strict_types=1);
$b = url('');
?>
<div id="portal-cookie-banner" class="hidden fixed bottom-0 inset-x-0 z-[300] p-4 md:p-6" role="dialog" aria-labelledby="cookie-banner-title" aria-live="polite" aria-modal="false" hidden>
    <div class="max-w-5xl mx-auto rounded-2xl border border-slate-200 bg-white shadow-2xl px-5 py-4 sm:px-6 sm:py-5 md:px-8 md:py-6 flex flex-col gap-4">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:gap-5 lg:gap-6">
            <div class="min-w-0 flex-1 space-y-2.5">
                <h2 id="cookie-banner-title" class="text-sm font-black text-slate-900 uppercase tracking-tight"><?= htmlspecialchars(__('cookies.title'), ENT_QUOTES, 'UTF-8') ?></h2>
                <p class="text-xs text-slate-600 leading-relaxed max-w-xl">
                    <?= htmlspecialchars(__('cookies.body'), ENT_QUOTES, 'UTF-8') ?>
                </p>
                <nav class="text-[11px] text-slate-500 flex flex-wrap gap-x-3 gap-y-1.5 items-center" aria-label="<?= htmlspecialchars(__('cookies.links_aria'), ENT_QUOTES, 'UTF-8') ?>">
                    <a href="<?= htmlspecialchars(url('legal/site'), ENT_QUOTES, 'UTF-8') ?>#rgpd" class="font-semibold text-emerald-700 hover:underline"><?= htmlspecialchars(__('common.personal_data'), ENT_QUOTES, 'UTF-8') ?></a>
                    <span class="text-slate-300 select-none" aria-hidden="true">·</span>
                    <a href="<?= htmlspecialchars(url('legal/site'), ENT_QUOTES, 'UTF-8') ?>#cookies" class="font-semibold text-emerald-700 hover:underline"><?= htmlspecialchars(__('legal.cookies'), ENT_QUOTES, 'UTF-8') ?></a>
                    <span class="text-slate-300 select-none" aria-hidden="true">·</span>
                    <a href="<?= htmlspecialchars(url('legal/site'), ENT_QUOTES, 'UTF-8') ?>#mentions" class="font-semibold text-emerald-700 hover:underline"><?= htmlspecialchars(__('legal.mentions'), ENT_QUOTES, 'UTF-8') ?></a>
                    <span class="text-slate-300 select-none" aria-hidden="true">·</span>
                    <a href="<?= htmlspecialchars(url('legal/site'), ENT_QUOTES, 'UTF-8') ?>#cgu" class="font-semibold text-emerald-700 hover:underline"><?= htmlspecialchars(__('legal.cgu'), ENT_QUOTES, 'UTF-8') ?></a>
                </nav>
            </div>
            <div class="grid grid-cols-2 gap-2 w-full shrink-0 md:w-[22rem] lg:w-[24rem]" role="group" aria-label="<?= htmlspecialchars(__('cookies.choices_aria'), ENT_QUOTES, 'UTF-8') ?>">
                <button type="button" id="portal-cookie-essential-only" class="w-full min-h-[2.75rem] px-3 py-2.5 rounded-xl border border-slate-200 text-[10px] font-black uppercase tracking-widest text-slate-700 hover:bg-slate-50 transition-colors text-center leading-tight">
                    <?= htmlspecialchars(__('cookies.essential_only'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button type="button" id="portal-cookie-customize" class="w-full min-h-[2.75rem] px-3 py-2.5 rounded-xl border border-slate-200 text-[10px] font-black uppercase tracking-widest text-slate-700 hover:bg-slate-50 transition-colors text-center leading-tight" aria-expanded="false" aria-controls="portal-cookie-panel">
                    <?= htmlspecialchars(__('cookies.customize'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button type="button" id="portal-cookie-reject-all" class="w-full min-h-[2.75rem] px-3 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-800 hover:bg-slate-100 transition-colors text-center leading-tight">
                    <?= htmlspecialchars(__('cookies.reject_all'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button type="button" id="portal-cookie-accept-all" class="w-full min-h-[2.75rem] px-3 py-2.5 rounded-xl bg-slate-900 text-white text-[10px] font-black uppercase tracking-widest hover:bg-emerald-700 transition-colors shadow-sm text-center leading-tight">
                    <?= htmlspecialchars(__('cookies.accept_all'), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>
        </div>

        <div id="portal-cookie-panel" class="hidden border-t border-slate-100 pt-4 space-y-4" hidden aria-hidden="true">
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400"><?= htmlspecialchars(__('cookies.refine'), ENT_QUOTES, 'UTF-8') ?></p>
            <p class="text-xs text-slate-500"><?= htmlspecialchars(__('cookies.refine_hint'), ENT_QUOTES, 'UTF-8') ?></p>
            <p id="portal-cookie-last-choice" class="text-[11px] text-slate-500"><?= htmlspecialchars(__('cookies.no_choice'), ENT_QUOTES, 'UTF-8') ?></p>
            <ul class="space-y-3">
                <li class="flex gap-3 items-start rounded-xl border border-slate-100 bg-slate-50/80 px-4 py-3">
                    <span class="mt-0.5 text-emerald-600" aria-hidden="true">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </span>
                    <div>
                        <p class="text-xs font-bold text-slate-900"><?= htmlspecialchars(__('cookies.essential_title'), ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="text-[11px] text-slate-500 mt-0.5"><?= htmlspecialchars(__('cookies.essential_desc'), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </li>
                <li class="flex gap-3 items-start rounded-xl border border-slate-200 bg-white px-4 py-3">
                    <input type="checkbox" id="portal-cookie-audience" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                    <label for="portal-cookie-audience" class="cursor-pointer">
                        <span class="text-xs font-bold text-slate-900"><?= htmlspecialchars(__('cookies.audience_title'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="block text-[11px] text-slate-500 mt-0.5"><?= htmlspecialchars(__('cookies.audience_desc'), ENT_QUOTES, 'UTF-8') ?></span>
                    </label>
                </li>
                <li class="flex gap-3 items-start rounded-xl border border-slate-200 bg-white px-4 py-3">
                    <input type="checkbox" id="portal-cookie-personalization" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                    <label for="portal-cookie-personalization" class="cursor-pointer">
                        <span class="text-xs font-bold text-slate-900"><?= htmlspecialchars(__('cookies.personalization_title'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="block text-[11px] text-slate-500 mt-0.5"><?= htmlspecialchars(__('cookies.personalization_desc'), ENT_QUOTES, 'UTF-8') ?></span>
                    </label>
                </li>
                <li class="flex gap-3 items-start rounded-xl border border-slate-200 bg-white px-4 py-3">
                    <input type="checkbox" id="portal-cookie-ads" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                    <label for="portal-cookie-ads" class="cursor-pointer">
                        <span class="text-xs font-bold text-slate-900"><?= htmlspecialchars(__('cookies.ads_title'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="block text-[11px] text-slate-500 mt-0.5"><?= htmlspecialchars(__('cookies.ads_desc'), ENT_QUOTES, 'UTF-8') ?></span>
                    </label>
                </li>
            </ul>
            <p class="text-[11px] text-slate-500"><?= htmlspecialchars(__('cookies.ttl_hint'), ENT_QUOTES, 'UTF-8') ?></p>
            <div class="flex flex-wrap gap-2 justify-end">
                <button type="button" id="portal-cookie-reset" class="px-4 py-2.5 rounded-xl border border-slate-200 text-[10px] font-black uppercase tracking-widest text-slate-700 hover:bg-slate-50 transition-colors">
                    <?= htmlspecialchars(__('cookies.reset'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button type="button" id="portal-cookie-save-custom" class="px-4 py-2.5 rounded-xl bg-slate-900 text-white text-[10px] font-black uppercase tracking-widest hover:bg-emerald-700 transition-colors">
                    <?= htmlspecialchars(__('cookies.save'), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>
        </div>
    </div>
</div>
<script src="<?= htmlspecialchars($b) ?>/assets/js/cookie_consent.js" defer></script>
