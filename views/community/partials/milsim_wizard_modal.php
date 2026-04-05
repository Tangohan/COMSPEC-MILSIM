<?php
declare(strict_types=1);
/** @var string $baseUrl */
$previewUrl = url('communities/create/preview');
?>
<div id="milsim-wizard-modal" class="fixed inset-0 z-[300] hidden" aria-modal="true" role="dialog" aria-labelledby="milsim-wizard-title">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" data-milsim-modal-backdrop></div>
    <div class="absolute inset-2 sm:inset-6 flex flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl lg:flex-row">
        <div class="flex min-h-0 flex-1 flex-col border-b border-slate-200 lg:max-w-[52%] lg:border-b-0 lg:border-r">
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                <h2 id="milsim-wizard-title" class="text-sm font-black uppercase tracking-widest text-slate-900">Atelier MilSim</h2>
                <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900" data-milsim-modal-close aria-label="Fermer">&times;</button>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto p-4 sm:p-6 space-y-5">
                <p class="text-xs leading-relaxed text-slate-600">Préremplissez le ton du portail et les champs du dossier. Les changements sont inclus dans le même formulaire que l’assistant (pas besoin de JSON).</p>
                <div class="space-y-2">
                    <label class="block text-[11px] font-bold text-slate-700">Titre portail</label>
                    <input type="text" name="wizard_milsim[portal_title]" form="community-create-form" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Portail de recrutement">
                </div>
                <div class="space-y-2">
                    <label class="block text-[11px] font-bold text-slate-700">Sous-titre portail</label>
                    <input type="text" name="wizard_milsim[portal_subtitle]" form="community-create-form" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Infrastructure sécurisée">
                </div>
                <div class="space-y-2">
                    <label class="block text-[11px] font-bold text-slate-700">Titre préambule</label>
                    <input type="text" name="wizard_milsim[preamble_title]" form="community-create-form" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                </div>
                <div class="space-y-2">
                    <label class="block text-[11px] font-bold text-slate-700">Bloc statut (une ligne par ligne)</label>
                    <textarea name="wizard_milsim[preamble_status]" form="community-create-form" rows="4" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-mono"></textarea>
                </div>
                <div class="space-y-2">
                    <label class="block text-[11px] font-bold text-slate-700">ROE — une règle par ligne</label>
                    <textarea name="wizard_milsim[roe_lines]" form="community-create-form" rows="5" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></textarea>
                </div>
                <div>
                    <p class="mb-2 text-[11px] font-black uppercase tracking-wider text-slate-500">Champs du dossier</p>
                    <?php
                    $fieldsData = [];
                    $inputPrefix = 'wizard_milsim[fields]';
                    include base_path('views/partials/milsim_pack_fields_editor.php');
                    ?>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 bg-slate-50 px-4 py-3">
                <button type="submit" form="community-create-form" formaction="<?= htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8') ?>" formmethod="post" formtarget="milsimPreviewFrame" class="inline-flex flex-1 items-center justify-center rounded-xl bg-emerald-600 px-4 py-2.5 text-[11px] font-black uppercase tracking-widest text-white shadow hover:bg-emerald-700 sm:flex-none">
                    Actualiser l’aperçu
                </button>
                <button type="button" data-milsim-modal-close class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-[11px] font-bold text-slate-800 hover:bg-slate-50">Fermer</button>
            </div>
        </div>
        <div class="flex min-h-[280px] flex-1 flex-col bg-slate-100 lg:min-h-0">
            <p class="border-b border-slate-200 px-4 py-2 text-[10px] font-bold uppercase tracking-wider text-slate-500">Aperçu (session assistant — nom de communauté requis)</p>
            <iframe name="milsimPreviewFrame" title="Aperçu MilSim" class="h-full min-h-[320px] w-full flex-1 bg-white"></iframe>
        </div>
    </div>
</div>
