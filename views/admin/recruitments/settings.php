<?php
declare(strict_types=1);

$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');
$slaHours = max(1, (int) ($enlistmentSlaHours ?? 72));
$submittedCount = max(0, (int) ($submittedCount ?? 0));
$submittedOlderThanSla = max(0, (int) ($submittedOlderThanSla ?? 0));
require base_path('views/admin/recruitment_workspace/partials/command_shell_open.php');
?>

<div class="min-h-[calc(100vh-3.5rem)] bg-gradient-to-b from-[#ebe6dc] via-[#f5f2eb] to-[#e8e4db]">
    <div class="mx-auto max-w-3xl space-y-8 px-4 py-8 sm:px-6 lg:px-8 lg:py-12">
        <nav class="flex flex-wrap items-center gap-2 text-xs font-semibold text-stone-600" aria-label="Fil d’Ariane">
            <a href="<?= htmlspecialchars(url('back-office/recruitments'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg px-2 py-1 transition hover:bg-white/60 hover:text-[#1c2d41] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1c4d6e] focus-visible:ring-offset-2">Dossiers de candidature</a>
            <span class="text-stone-400" aria-hidden="true">/</span>
            <span class="rounded-lg bg-white/80 px-2 py-1 text-[#1c2d41] ring-1 ring-stone-200/80">Délais d’alerte</span>
        </nav>

        <?php if ($flashOk): ?>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50/95 px-4 py-3 text-sm font-medium text-emerald-950 shadow-sm sm:px-5" role="status"><?= htmlspecialchars((string) $flashOk, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($flashErr): ?>
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-950 shadow-sm sm:px-5" role="alert"><?= htmlspecialchars((string) $flashErr, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="overflow-hidden rounded-2xl border border-stone-300/80 bg-white shadow-[0_25px_60px_-20px_rgba(28,45,65,0.35)] ring-1 ring-black/[0.03]">
            <div class="relative bg-[#1c2d41] px-6 py-8 sm:px-8 sm:pb-10 sm:pt-10">
                <div class="absolute inset-0 bg-[linear-gradient(105deg,rgba(201,162,39,0.12)_0%,transparent_45%)] pointer-events-none" aria-hidden="true"></div>
                <div class="relative flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.35em] text-[#c9a227]/90">Service recrutement</p>
                        <h1 class="mt-2 font-serif text-3xl font-bold tracking-tight text-white sm:text-4xl">Délais d’alerte</h1>
                        <p class="mt-4 max-w-xl text-sm leading-relaxed text-slate-300/95">
                            Définissez après combien d’heures sans traitement un dossier <strong class="font-semibold text-white">à traiter</strong> est considéré en retard dans la file et dans les compteurs.
                        </p>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-3">
                        <a href="<?= htmlspecialchars(url('back-office/recruitments/messages-prefaits'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex min-h-[2.75rem] items-center rounded-xl border border-sky-300/40 bg-sky-300/10 px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-sky-100 transition hover:bg-sky-300/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-200 focus-visible:ring-offset-2 focus-visible:ring-offset-[#1c2d41]">
                            Messages préfaits
                        </a>
                        <a href="<?= htmlspecialchars(url('back-office/recruitments'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex min-h-[2.75rem] items-center rounded-xl border border-white/20 bg-white/10 px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-white backdrop-blur-sm transition hover:bg-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60 focus-visible:ring-offset-2 focus-visible:ring-offset-[#1c2d41]">
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
                        <p class="mt-2 font-serif text-3xl font-bold text-[#1c2d41] tabular-nums"><?= $submittedCount ?></p>
                        <p class="mt-3 text-xs leading-relaxed text-stone-600">En attente d’une décision de l’équipe.</p>
                    </div>
                    <div class="rounded-xl border <?= $submittedOlderThanSla > 0 ? 'border-rose-300 bg-rose-50/60' : 'border-sky-200 bg-sky-50/50' ?> p-5 shadow-sm sm:p-6">
                        <p class="text-[10px] font-bold uppercase tracking-wider <?= $submittedOlderThanSla > 0 ? 'text-rose-900/80' : 'text-sky-900/80' ?>">Sans action depuis le délai</p>
                        <p class="mt-2 font-serif text-3xl font-bold <?= $submittedOlderThanSla > 0 ? 'text-rose-950' : 'text-sky-950' ?> tabular-nums"><?= $submittedOlderThanSla ?></p>
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
                        <button type="submit" class="inline-flex min-h-[2.75rem] items-center justify-center rounded-xl bg-[#1c2d41] px-8 py-2.5 text-sm font-bold text-white shadow-md shadow-[#1c2d41]/25 transition hover:bg-[#152333] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c9a227] focus-visible:ring-offset-2">
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
</div>
<?php require base_path('views/admin/recruitment_workspace/partials/command_shell_close.php'); ?>
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
