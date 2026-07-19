<?php
declare(strict_types=1);
/** @var array<string,mixed>|null $enlistmentMemberOpeningInsight */
$insight = is_array($enlistmentMemberOpeningInsight ?? null) ? $enlistmentMemberOpeningInsight : null;
if ($insight === null || empty($insight['rows']) || !is_array($insight['rows'])) {
    return;
}
$lead = trim((string) ($insight['lead'] ?? ''));
$footnote = trim((string) ($insight['footnote'] ?? ''));
?>
<div class="ce-insight mb-8 rounded-xl border border-indigo-200 bg-gradient-to-br from-indigo-50 to-white p-5 sm:p-6 shadow-sm">
    <p class="ce-insight__kicker text-[10px] font-black uppercase tracking-widest text-indigo-900">Candidature interne — évolution</p>
    <?php if ($lead !== ''): ?>
        <p class="ce-insight__lead mt-3 text-sm text-slate-800 leading-relaxed"><?= htmlspecialchars($lead, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <div class="ce-insight__table-wrap mt-5 overflow-x-auto rounded-lg border border-indigo-100 bg-white/90">
        <table class="min-w-full text-left text-[11px]">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50/90 text-[9px] font-black uppercase tracking-wider text-slate-500">
                    <th class="px-3 py-2.5 sm:px-4">Thème</th>
                    <th class="px-3 py-2.5 sm:px-4">Votre situation aujourd’hui</th>
                    <th class="px-3 py-2.5 sm:px-4">Visé par cet avis</th>
                </tr>
            </thead>
            <tbody class="text-slate-800">
                <?php foreach ($insight['rows'] as $row): ?>
                    <?php
                    if (!is_array($row)) {
                        continue;
                    }
                    $th = trim((string) ($row['theme'] ?? ''));
                    $cur = trim((string) ($row['current'] ?? ''));
                    $tgt = trim((string) ($row['target'] ?? ''));
                    $em = !empty($row['emphasize']);
                    ?>
                    <tr class="border-b border-slate-100 last:border-0 <?= $em ? 'is-emphasis bg-amber-50/50' : '' ?>">
                        <td class="px-3 py-3 sm:px-4 font-bold text-slate-700 align-top"><?= htmlspecialchars($th, ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-3 py-3 sm:px-4 align-top text-slate-600"><?= htmlspecialchars($cur, ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-3 py-3 sm:px-4 align-top font-semibold text-slate-900"><?= htmlspecialchars($tgt, ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($footnote !== ''): ?>
        <p class="ce-insight__foot mt-4 text-[10px] text-slate-500 leading-relaxed"><?= htmlspecialchars($footnote, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
</div>
