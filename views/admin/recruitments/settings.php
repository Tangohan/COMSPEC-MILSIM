<?php
declare(strict_types=1);

$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');
$slaHours = max(1, (int) ($enlistmentSlaHours ?? 72));
$submittedCount = max(0, (int) ($submittedCount ?? 0));
$submittedOlderThanSla = max(0, (int) ($submittedOlderThanSla ?? 0));
?>

<div class="min-h-[calc(100vh-3.5rem)] bg-gradient-to-b from-[#ebe6dc] via-[#f5f2eb] to-[#e8e4db]">
    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8 lg:py-12">
        <nav class="mb-6 flex flex-wrap items-center gap-2 text-xs font-semibold text-stone-600" aria-label="Fil d’Ariane">
            <a href="<?= htmlspecialchars(url('back-office/recruitments')) ?>" class="rounded-lg px-2 py-1 transition hover:bg-white/60 hover:text-[#1c2d41]">Dossiers de candidature</a>
            <span class="text-stone-400" aria-hidden="true">/</span>
            <span class="rounded-lg bg-white/80 px-2 py-1 text-[#1c2d41] ring-1 ring-stone-200/80">Paramètres</span>
        </nav>

        <div class="overflow-hidden rounded-2xl border border-stone-300/80 bg-white shadow-[0_25px_60px_-20px_rgba(28,45,65,0.35)] ring-1 ring-black/[0.03]">
            <div class="relative bg-[#1c2d41] px-6 py-7 sm:px-8">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.35em] text-[#c9a227]/90">Service recrutement</p>
                        <h1 class="mt-2 font-serif text-3xl font-bold tracking-tight text-white">Paramètres SLA</h1>
                        <p class="mt-3 text-sm leading-relaxed text-slate-300/95">Définissez le délai maximal sans action sur un dossier soumis avant alerte de blocage.</p>
                    </div>
                    <a href="<?= htmlspecialchars(url('back-office/recruitments/messages-prefaits'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-xl border border-sky-300/40 bg-sky-300/10 px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-sky-100 transition hover:bg-sky-300/20">
                        Messages préfaits
                    </a>
                </div>
            </div>

            <div class="space-y-4 border-b border-stone-200 bg-[#faf8f3] px-6 py-5 sm:px-8">
                <?php if ($flashOk): ?>
                    <p class="rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-950 shadow-sm"><?= htmlspecialchars((string) $flashOk, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if ($flashErr): ?>
                    <p class="rounded-xl border border-rose-200/80 bg-rose-50/90 px-4 py-3 text-sm text-rose-950 shadow-sm"><?= htmlspecialchars((string) $flashErr, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-stone-500">Dossiers à traiter</p>
                        <p class="mt-1 font-serif text-2xl font-bold text-[#1c2d41] tabular-nums"><?= $submittedCount ?></p>
                    </div>
                    <div class="rounded-xl border <?= $submittedOlderThanSla > 0 ? 'border-rose-300 bg-rose-50/50' : 'border-sky-200 bg-sky-50/40' ?> p-4 shadow-sm">
                        <p class="text-[10px] font-bold uppercase tracking-wider <?= $submittedOlderThanSla > 0 ? 'text-rose-900/80' : 'text-sky-900/80' ?>">Bloqués &gt; SLA</p>
                        <p class="mt-1 font-serif text-2xl font-bold <?= $submittedOlderThanSla > 0 ? 'text-rose-950' : 'text-sky-950' ?> tabular-nums"><?= $submittedOlderThanSla ?></p>
                    </div>
                </div>
            </div>

            <div class="px-6 py-7 sm:px-8">
                <form method="post" action="<?= htmlspecialchars(url('back-office/recruitments/settings'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-5">
                    <?= \App\Core\Csrf::field() ?>
                    <div>
                        <label for="enlistment_sla_hours" class="block text-xs font-bold uppercase tracking-wide text-stone-600">SLA candidature (heures)</label>
                        <input
                            type="number"
                            id="enlistment_sla_hours"
                            name="enlistment_sla_hours"
                            min="1"
                            max="720"
                            value="<?= $slaHours ?>"
                            class="mt-2 w-32 rounded-xl border border-stone-300 bg-white px-3 py-2.5 text-sm font-semibold text-stone-900 shadow-inner focus:border-[#1c4d6e] focus:outline-none focus:ring-2 focus:ring-[#1c4d6e]/20"
                        >
                        <p class="mt-2 text-xs text-stone-500">Plage autorisée : 1 à 720 heures. Dépassement = dossier signalé “Bloqué &gt; SLA”.</p>
                        <p class="mt-1 text-xs font-semibold text-slate-600">Équivalent: <span id="sla-days-preview"><?= number_format($slaHours / 24, 1, ',', ' ') ?></span> jour(s)</p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#1c2d41] px-6 py-2.5 text-sm font-bold text-white shadow-md shadow-[#1c2d41]/25 transition hover:bg-[#152333]">
                            Enregistrer
                        </button>
                        <a href="<?= htmlspecialchars(url('back-office/recruitments'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-xl border border-stone-300 bg-white px-6 py-2.5 text-sm font-semibold text-stone-700 hover:bg-stone-50">
                            Retour aux dossiers
                        </a>
                    </div>
                </form>
            </div>
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
