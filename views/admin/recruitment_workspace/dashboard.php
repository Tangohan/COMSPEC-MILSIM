<?php
declare(strict_types=1);
$counts = is_array($enlistmentCounts ?? null) ? $enlistmentCounts : [];
$nSubmitted = (int) ($counts['submitted'] ?? 0);
$nTotal = array_sum($counts);
$enlistmentSlaHours = max(1, (int) ($enlistmentSlaHours ?? 72));
$submittedOlderThanSla = max(0, (int) ($submittedOlderThanSla ?? 0));
$via = is_array($submittedViaCounts ?? null) ? $submittedViaCounts : [];
$weekly = is_array($weeklyCreated ?? null) ? $weeklyCreated : [];
$topOpenings = is_array($topOpenings ?? null) ? $topOpenings : [];
$viaLabel = static function (string $k): string {
    return match ($k) {
        'guest' => 'Sans compte (invité)',
        'account', 'user' => 'Compte membre',
        default => $k !== '' ? $k : 'Autre',
    };
};
require base_path('views/admin/recruitment_workspace/partials/command_shell_open.php');
?>
                <header class="recruitment-cmd-panel">
                    <p class="recruitment-cmd-kicker">Pilotage recrutement</p>
                    <h1 class="recruitment-cmd-title">Candidatures &amp; dossiers</h1>
                    <p class="text-sm text-stone-600 max-w-2xl leading-relaxed">
                        Point d’entrée unique pour suivre la file, les délais internes (SLA) et les volumes. Les actions de traitement se font depuis la file ou la fiche dossier.
                    </p>
                    <div class="grid grid-cols-2 xl:grid-cols-4 gap-3 mt-8">
                        <div class="recruitment-cmd-stat">
                            <p class="recruitment-cmd-stat__k">Total dossiers</p>
                            <p class="recruitment-cmd-stat__v"><?= $nTotal ?></p>
                        </div>
                        <div class="recruitment-cmd-stat border-amber-200/80 bg-amber-50/50">
                            <p class="recruitment-cmd-stat__k">À traiter</p>
                            <p class="recruitment-cmd-stat__v text-amber-950"><?= $nSubmitted ?></p>
                        </div>
                        <div class="recruitment-cmd-stat <?= $submittedOlderThanSla > 0 ? 'border-rose-200 bg-rose-50/70' : 'border-sky-200 bg-sky-50/40' ?>">
                            <p class="recruitment-cmd-stat__k">Dépassement SLA</p>
                            <p class="recruitment-cmd-stat__v <?= $submittedOlderThanSla > 0 ? 'text-rose-900' : 'text-sky-950' ?>"><?= $submittedOlderThanSla ?></p>
                        </div>
                        <div class="recruitment-cmd-stat border-emerald-200/80 bg-emerald-50/40">
                            <p class="recruitment-cmd-stat__k">SLA (heures)</p>
                            <p class="recruitment-cmd-stat__v text-emerald-900"><?= $enlistmentSlaHours ?></p>
                        </div>
                    </div>
                </header>

                <section class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
                    <a href="<?= htmlspecialchars(url('back-office/recruitments'), ENT_QUOTES, 'UTF-8') ?>" class="recruitment-cmd-card">
                        <p class="recruitment-cmd-card__k">File</p>
                        <h2 class="recruitment-cmd-card__t">Ouvrir la file des dossiers</h2>
                        <p class="recruitment-cmd-card__d">Filtrer par statut, consulter chaque dossier et enregistrer une décision.</p>
                    </a>
                    <a href="<?= htmlspecialchars(recruitment_workspace_url('analyses'), ENT_QUOTES, 'UTF-8') ?>" class="recruitment-cmd-card">
                        <p class="recruitment-cmd-card__k">Indicateurs</p>
                        <h2 class="recruitment-cmd-card__t">Analyses détaillées</h2>
                        <p class="recruitment-cmd-card__d">Volumes par semaine, canaux de dépôt et offres les plus sollicitées.</p>
                    </a>
                    <a href="<?= htmlspecialchars(url('back-office/recruitments/settings'), ENT_QUOTES, 'UTF-8') ?>" class="recruitment-cmd-card sm:col-span-2 xl:col-span-1">
                        <p class="recruitment-cmd-card__k">Paramètres</p>
                        <h2 class="recruitment-cmd-card__t">SLA &amp; messages</h2>
                        <p class="recruitment-cmd-card__d">Ajuster le délai d’alerte et préparer les modèles de texte pour le traitement.</p>
                    </a>
                </section>

                <?php if ($via !== []): ?>
                <section class="recruitment-cmd-panel">
                    <h2 class="text-xs font-black uppercase tracking-[0.2em] text-[#1c2d41] mb-3">Canal de transmission</h2>
                    <p class="text-xs text-stone-600 mb-4">Répartition des dossiers selon le mode de dépôt enregistré.</p>
                    <ul class="space-y-2">
                        <?php foreach ($via as $k => $n): ?>
                            <li class="flex justify-between gap-3 text-sm border-b border-stone-100 pb-2 last:border-0">
                                <span class="text-stone-700"><?= htmlspecialchars($viaLabel((string) $k), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="font-mono font-bold text-[#1c2d41]"><?= (int) $n ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
                <?php endif; ?>

                <?php if ($weekly !== []): ?>
                <section class="recruitment-cmd-panel">
                    <h2 class="text-xs font-black uppercase tracking-[0.2em] text-[#1c2d41] mb-3">Arrivées par semaine (12 dernières)</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm text-left">
                            <thead>
                                <tr class="text-[10px] font-black uppercase tracking-wider text-stone-500 border-b border-stone-200">
                                    <th class="py-2 pr-4">Semaine (lundi)</th>
                                    <th class="py-2 text-right">Candidatures</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($weekly as $row): ?>
                                    <tr class="border-b border-stone-100">
                                        <td class="py-2 pr-4 text-stone-800"><?= htmlspecialchars((string) ($row['week_start'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="py-2 text-right font-semibold tabular-nums"><?= (int) ($row['c'] ?? 0) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
                <?php endif; ?>

                <?php if ($topOpenings !== []): ?>
                <section class="recruitment-cmd-panel">
                    <h2 class="text-xs font-black uppercase tracking-[0.2em] text-[#1c2d41] mb-3">Offres les plus citées</h2>
                    <ul class="space-y-2">
                        <?php foreach ($topOpenings as $ro): ?>
                            <li class="flex flex-wrap justify-between gap-2 text-sm border-b border-stone-100 pb-2 last:border-0">
                                <span class="text-stone-800 font-medium"><?= htmlspecialchars((string) ($ro['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="font-mono text-stone-600"><?= (int) ($ro['c'] ?? 0) ?> dossier(s)</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
                <?php endif; ?>

<?php require base_path('views/admin/recruitment_workspace/partials/command_shell_close.php'); ?>
