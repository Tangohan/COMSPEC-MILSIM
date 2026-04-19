<?php
declare(strict_types=1);

$slaHours = max(1, (int) ($enlistmentSlaHours ?? 72));
$submittedCount = max(0, (int) ($submittedCount ?? 0));
$submittedOlderThanSla = max(0, (int) ($submittedOlderThanSla ?? 0));
?>

<div class="recruitment-bureau max-w-3xl mx-auto w-full space-y-8">
        <nav class="overflow-hidden rounded-2xl border border-stone-300/80 bg-white px-4 py-3 shadow-sm sm:px-5" aria-label="Fil d’Ariane">
            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold text-stone-600">
                <a href="<?= htmlspecialchars(url('back-office/recruitments'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg border border-transparent px-2 py-1.5 text-stone-700 transition hover:border-stone-200 hover:bg-stone-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-stone-400 focus-visible:ring-offset-2">Dossiers de candidature</a>
                <span class="text-stone-300" aria-hidden="true">/</span>
                <span class="rounded-lg border border-stone-200 bg-stone-50 px-2.5 py-1.5 font-bold text-stone-900">Délais d’alerte</span>
            </div>
        </nav>

        <div class="overflow-hidden rounded-2xl border border-stone-300/80 bg-white shadow-sm">
            <div class="border-b border-stone-200 bg-stone-50 px-6 py-7 sm:px-8 sm:py-8">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-stone-500">Bureau recrutement</p>
                        <h1 class="mt-1 text-2xl font-black tracking-tight text-stone-900 sm:text-3xl">Délais d’alerte</h1>
                        <p class="mt-3 max-w-xl text-sm leading-relaxed text-stone-600">
                            Définissez après combien d’heures sans traitement un dossier <strong class="font-semibold text-stone-900">à traiter</strong> est considéré en retard dans la file et dans les compteurs.
                        </p>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-2">
                        <a href="<?= htmlspecialchars(url('back-office/recruitments/messages-prefaits'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex min-h-[2.5rem] items-center rounded-xl border border-stone-300 bg-white px-3 py-2 text-[10px] font-bold uppercase tracking-[0.18em] text-stone-800 shadow-sm transition hover:bg-stone-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-stone-400 focus-visible:ring-offset-2">
                            Messages préfaits
                        </a>
                        <a href="<?= htmlspecialchars(url('back-office/recruitments'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex min-h-[2.5rem] items-center rounded-xl border border-stone-900 bg-stone-900 px-3 py-2 text-[10px] font-bold uppercase tracking-[0.18em] text-white shadow-sm transition hover:bg-stone-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40 focus-visible:ring-offset-2">
                            ← File des dossiers
                        </a>
                    </div>
                </div>
            </div>

            <div class="border-b border-stone-200 bg-white px-6 py-8 sm:px-8">
                <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-stone-500">Synthèse</p>
                <div class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div class="rounded-xl border border-stone-200 bg-stone-50/50 p-5 shadow-sm sm:p-6">
                        <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-stone-500">Dossiers à traiter</p>
                        <p class="mt-2 text-3xl font-black tabular-nums tracking-tight text-stone-900"><?= $submittedCount ?></p>
                        <p class="mt-3 text-xs leading-relaxed text-stone-600">En attente d’une décision de l’équipe.</p>
                    </div>
                    <div class="rounded-xl border <?= $submittedOlderThanSla > 0 ? 'border-rose-200 bg-rose-50/80' : 'border-sky-200 bg-sky-50/70' ?> p-5 shadow-sm sm:p-6">
                        <p class="text-[10px] font-bold uppercase tracking-[0.22em] <?= $submittedOlderThanSla > 0 ? 'text-rose-900/80' : 'text-sky-900/80' ?>">Sans action depuis le délai</p>
                        <p class="mt-2 text-3xl font-black tabular-nums tracking-tight <?= $submittedOlderThanSla > 0 ? 'text-rose-950' : 'text-sky-950' ?>"><?= $submittedOlderThanSla ?></p>
                        <p class="mt-3 text-xs leading-relaxed <?= $submittedOlderThanSla > 0 ? 'text-rose-900/90' : 'text-sky-900/85' ?>">Comptabilisés parmi les dossiers à traiter, au-delà du seuil que vous fixez ci-dessous.</p>
                    </div>
                </div>
            </div>

            <div class="px-6 py-8 sm:px-8 sm:py-10">
                <form method="post" action="<?= htmlspecialchars(url('back-office/recruitments/settings'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-8">
                    <?= \App\Core\Csrf::field() ?>
                    <div class="rounded-2xl border border-stone-200 bg-[#faf8f3]/50 p-5 sm:p-8">
                        <label for="enlistment_sla_hours" class="block text-sm font-bold text-stone-900">Heures sans traitement avant signalement</label>
                        <p class="mt-2 max-w-xl text-sm leading-relaxed text-stone-600">Valeur entre 1 et 720. Elle s’applique à toute la communauté pour la file des candidatures.</p>
                        <div class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-end">
                            <div>
                                <span class="sr-only">Nombre d’heures</span>
                                <input
                                    type="number"
                                    id="enlistment_sla_hours"
                                    name="enlistment_sla_hours"
                                    min="1"
                                    max="720"
                                    value="<?= $slaHours ?>"
                                    class="w-full max-w-[11rem] rounded-xl border border-stone-300 bg-white px-4 py-3 text-base font-semibold text-stone-900 shadow-inner focus:border-stone-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/25"
                                    aria-describedby="sla-help sla-days-preview-wrap"
                                >
                            </div>
                            <p id="sla-days-preview-wrap" class="text-sm text-stone-700">
                                Soit environ <strong id="sla-days-preview" class="font-bold text-stone-900 tabular-nums"><?= number_format($slaHours / 24, 1, ',', ' ') ?></strong> jour(s) calendaire(s).
                            </p>
                        </div>
                        <p id="sla-help" class="mt-6 text-xs leading-relaxed text-stone-500">Les dossiers concernés restent visibles : ils sont simplement mis en évidence pour l’équipe recrutement.</p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <button type="submit" class="inline-flex min-h-[2.75rem] items-center justify-center rounded-xl border border-stone-900 bg-stone-900 px-8 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-stone-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40 focus-visible:ring-offset-2">
                            Enregistrer
                        </button>
                        <a href="<?= htmlspecialchars(url('back-office/recruitments'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex min-h-[2.75rem] items-center justify-center rounded-xl border border-stone-300 bg-white px-8 py-2.5 text-sm font-semibold text-stone-800 transition hover:bg-stone-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-stone-400 focus-visible:ring-offset-2">
                            Retour aux dossiers
                        </a>
                    </div>
                </form>
            </div>
        </div>
</div>
<script>
(function () {
    var input = document.getElementById('enlistment_sla_hours');
    var target = document.getElementById('sla-days-preview');
    if (!input || !target) return;
    function render() {
        var hours = parseInt(input.value || '0', 10);
        if (Number.isNaN(hours) || hours < 0) hours = 0;
        target.textContent = (hours / 24).toFixed(1).replace('.', ',');
    }
    input.addEventListener('input', render);
})();
</script>
