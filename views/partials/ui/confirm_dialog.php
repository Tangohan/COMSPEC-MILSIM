<?php
declare(strict_types=1);
?>
<dialog id="portal-ui-confirm" class="w-[min(100vw-2rem,26rem)] rounded-2xl border border-slate-200 bg-white p-0 text-slate-900 shadow-2xl backdrop:bg-slate-900/40">
    <form method="dialog" class="flex flex-col">
        <div class="border-b border-slate-100 px-5 py-4">
            <p id="portal-ui-confirm-title" class="text-sm font-bold text-slate-900">Confirmer</p>
            <p id="portal-ui-confirm-body" class="mt-2 text-sm leading-relaxed text-slate-600"></p>
        </div>
        <div class="flex justify-end gap-2 px-4 py-3">
            <button type="submit" value="cancel" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
                Annuler
            </button>
            <button type="submit" value="confirm" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-500">
                Confirmer
            </button>
        </div>
    </form>
</dialog>
