<?php
declare(strict_types=1);

$slaHours = max(1, (int) ($enlistmentSlaHours ?? 72));
$submittedCount = max(0, (int) ($submittedCount ?? 0));
$submittedOlderThanSla = max(0, (int) ($submittedOlderThanSla ?? 0));
?>

<div class="max-w-3xl mx-auto w-full space-y-8">
        <nav class="flex flex-wrap items-center gap-2 text-xs font-semibold text-stone-600" aria-label="Fil d’Ariane">
            <a href="<?= htmlspecialchars(url('back-office/recruitments'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg px-2 py-1 transition hover:bg-white/60 hover:text-[#1c2d41] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1c4d6e] focus-visible:ring-offset-2">Dossiers de candidature</a>
            <span class="text-stone-400" aria-hidden="true">/</span>
            <span class="rounded-lg bg-white/80 px-2 py-1 text-[#1c2d41] ring-1 ring-stone-200/80">Délais d’alerte</span>
        </nav>

        <div class="lms-panel overflow-hidden rounded-[2rem] border border-slate-200/90 shadow-xl">
            <div class="relative bg-slate-900 px-6 py-8 sm:px-8 sm:pb-10 sm:pt-10">
                <div class="absolute top-0 left-0 w-full h-[2px] bg-gradient-to-r from-sky-500/90 via-sky-500/30 to-transparent" aria-hidden="true"></div>
                <div class="absolute inset-0 bg-[linear-gradient(105deg,rgba(14,165,233,0.12)_0%,transparent_45%)] pointer-events-none" aria-hidden="true"></div>
                <div class="relative flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-[9px] font-black uppercase tracking-[0.4em] text-sky-400/95">Bureau recrutement</p>
                        <h1 class="mt-2 text-3xl font-black tracking-tight uppercase text-white sm:text-4xl">Délais d’alerte</h1>
                        <p class="mt-4 max-w-xl text-sm leading-relaxed text-slate-300/95">
                            Définissez après combien d’heures sans traitement un dossier <strong class="font-semibold text-white">à traiter</strong> est considéré en retard dans la file et dans les compteurs.
                        </p>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-3">
                        <a href="<?= htmlspecialchars(url('back-office/recruitments/messages-prefaits'), ENT_QUOTES, 'UTF-8') ?>" class="lms-btn lms-btn--emerald">
                            Messages préfaits
                        </a>
                        <a href="<?= htmlspecialchars(url('back-office/recruitments'), ENT_QUOTES, 'UTF-8') ?>" class="lms-btn lms-btn--secondary">
                            ← File des dossiers
                        </a>
                    </div>
                </div>
            </div>

            <div class="border-b border-stone-200 bg-[#faf8f3] px-6 py-8 sm:px-8">
                <h2 class="text-[10px] font-bold uppercase tracking-[0.25em] text-stone-500">Synthèse</h2>
                <div class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div class="rounded-xl border border-stone-200 bg-white p-5 shadow-sm sm:p-6">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-stone-500">Dossiers à traiter</p>
                        <p class="mt-2 text-3xl font-extrabold text-[#1c2d41] tabular-nums"><?= $submittedCount ?></p>
                        <p class="mt-3 text-xs leading-relaxed text-stone-600">En attente d’une décision de l’équipe.</p>
                    </div>
                    <div class="rounded-xl border <?= $submittedOlderThanSla > 0 ? 'border-rose-300 bg-rose-50/60' : 'border-sky-200 bg-sky-50/50' ?> p-5 shadow-sm sm:p-6">
                        <p class="text-[10px] font-bold uppercase tracking-wider <?= $submittedOlderThanSla > 0 ? 'text-rose-900/80' : 'text-sky-900/80' ?>">Sans action depuis le délai</p>
                        <p class="mt-2 text-3xl font-extrabold <?= $submittedOlderThanSla > 0 ? 'text-rose-950' : 'text-sky-950' ?> tabular-nums"><?= $submittedOlderThanSla ?></p>
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
                                    class="w-full max-w-[11rem] rounded-xl border border-stone-300 bg-white px-4 py-3 text-base font-semibold text-stone-900 shadow-inner focus:border-[#1c4d6e] focus:outline-none focus:ring-2 focus:ring-[#1c4d6e]/20"
                                    aria-describedby="sla-help sla-days-preview-wrap"
                                >
                            </div>
                            <p id="sla-days-preview-wrap" class="text-sm text-stone-700">
                                Soit environ <strong id="sla-days-preview" class="font-semibold text-[#1c2d41] tabular-nums"><?= number_format($slaHours / 24, 1, ',', ' ') ?></strong> jour(s) calendaire(s).
                            </p>
                        </div>
                        <p id="sla-help" class="mt-6 text-xs leading-relaxed text-stone-500">Les dossiers concernés restent visibles : ils sont simplement mis en évidence pour l’équipe recrutement.</p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <button type="submit" class="lms-btn lms-btn--dark">
                            Enregistrer
                        </button>
                        <a href="<?= htmlspecialchars(url('back-office/recruitments'), ENT_QUOTES, 'UTF-8') ?>" class="lms-btn lms-btn--secondary">
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
