<?php
declare(strict_types=1);

$m = $interteamMission ?? [];
$canManage = !empty($interteamCanManage);
$canPilot = !empty($interteamCanPilot);
$csrf = $csrfToken ?? \App\Core\Csrf::token();
$sid = (int) ($m['id'] ?? 0);
$status = (string) ($m['status'] ?? '');
?>
<div class="max-w-4xl mx-auto px-6 py-10 space-y-8">
    <div>
        <a href="<?= htmlspecialchars(cooperation_mission_show_url($sid), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-medium text-slate-600 hover:text-slate-900 underline">← Synthèse</a>
        <?php require base_path('views/back_office/cooperation/missions/_nav.php'); ?>
        <h1 class="mt-4 text-2xl font-black text-slate-900">Clôture & archivage</h1>
        <p class="mt-2 text-sm text-slate-600">Met fin aux autorisations d’accès au brief partenaire et verrouille l’espace commun. Une phase de retour d’expérience structurée viendra compléter cet écran.</p>
    </div>

    <section class="rounded-xl border border-indigo-200 bg-indigo-50/40 p-6 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-wider text-indigo-900">Retour d’expérience</h2>
        <p class="mt-2 text-xs text-indigo-900/90">Prochaine étape : formulaire par unité, synthèse consolidée et capitalisation (voir feuille de route produit).</p>
    </section>

    <?php if ($canPilot && $canManage && in_array($status, ['draft', 'active'], true)): ?>
    <section class="rounded-xl border border-rose-200 bg-rose-50/50 p-6 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-wider text-rose-900">Clôturer la coopération</h2>
        <p class="mt-2 text-xs text-rose-900/90">Les accès partagés au brief sont retirés et le fil commun est clos. Cette action est définitive côté partages.</p>
        <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/close'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 space-y-4 max-w-xl" onsubmit="return confirm('Clôturer cette coopération ?');">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <div>
                <label class="block text-xs font-bold text-rose-900/80 mb-1">Motif de clôture</label>
                <input type="text" name="closure_motive" maxlength="500" class="w-full rounded-lg border border-rose-200 px-3 py-2 text-sm bg-white" placeholder="Ex. : fin d’exercice, report, annulation…">
            </div>
            <div>
                <label class="block text-xs font-bold text-rose-900/80 mb-1">Synthèse finale (facultatif)</label>
                <textarea name="closure_summary" rows="4" maxlength="20000" class="w-full rounded-lg border border-rose-200 px-3 py-2 text-sm bg-white" placeholder="Points saillants, suites éventuelles…"></textarea>
            </div>
            <div>
                <label class="block text-xs font-bold text-rose-900/80 mb-1">Niveau de conservation</label>
                <select name="archive_retention" class="w-full rounded-lg border border-rose-200 px-3 py-2 text-sm bg-white">
                    <option value="court_terme">Court terme</option>
                    <option value="standard" selected>Standard</option>
                    <option value="renforce">Renforcé</option>
                </select>
            </div>
            <button type="submit" class="rounded-xl bg-rose-800 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-900">Clôturer</button>
        </form>
    </section>
    <?php elseif ($status === 'archived'): ?>
    <p class="text-sm text-slate-600">Cette coopération est clôturée. Les interactions sont figées ; le retour d’expérience pourra encore être complété ultérieurement.</p>
    <?php else: ?>
    <p class="text-sm text-slate-600">La clôture est réservée aux unités habilitées à piloter cette coopération.</p>
    <?php endif; ?>
</div>
