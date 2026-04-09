<?php
declare(strict_types=1);
$matrix = $cooperationAnnouncementMatrix ?? [];
$evtLabels = $cooperationAnnouncementEventLabels ?? [];
$chLabels = $cooperationAnnouncementChannelLabels ?? [];
?>
<div class="max-w-6xl mx-auto px-6 py-10">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <a href="<?= htmlspecialchars(cooperation_mission_index_url(), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-medium text-slate-600 hover:text-slate-900 underline">← Coopérations inter-unités</a>
            <h1 class="mt-3 text-2xl font-black text-slate-900">Messages types — annonces coopération</h1>
        </div>
    </div>
    <p class="text-sm text-slate-600 mb-6 max-w-3xl">Adaptez les textes envoyés par courriel, affichés dans le centre de notifications ou publiés sur le forum lors des principales étapes d’un dossier. Si vous ne personnalisez pas un couple « événement / canal », ce sont les réglages du site qui s’appliquent.</p>
    <?php $s = \App\Core\Session::getFlash('success'); $e = \App\Core\Session::getFlash('error'); ?>
    <?php if ($s): ?><p class="text-emerald-700 text-sm mb-4"><?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <?php if ($e): ?><p class="text-red-600 text-sm mb-4"><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3">Événement</th>
                    <th class="px-4 py-3">Canal</th>
                    <th class="px-4 py-3">Personnalisation locale</th>
                    <th class="px-4 py-3">Utilisé</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($matrix as $cell): ?>
                    <?php
                    $ek = (string) ($cell['event_key'] ?? '');
                    $ch = (string) ($cell['channel'] ?? '');
                    $hasLocal = !empty($cell['has_local']);
                    $isLive = !empty($cell['is_live']);
                    ?>
                    <tr class="hover:bg-slate-50/80">
                        <td class="px-4 py-3 font-medium text-slate-900"><?= htmlspecialchars($evtLabels[$ek] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($chLabels[$ch] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-4 py-3"><?= $hasLocal ? '<span class="text-emerald-700 font-medium">Oui</span>' : '<span class="text-slate-400">Non</span>' ?></td>
                        <td class="px-4 py-3"><?= $isLive ? '<span class="text-emerald-700 font-medium">Oui</span>' : '<span class="text-slate-400">Non</span>' ?></td>
                        <td class="px-4 py-3 text-right">
                            <a href="<?= url('back-office/cooperation/announcements/edit?event=' . rawurlencode($ek) . '&channel=' . rawurlencode($ch)) ?>" class="text-sm font-semibold text-blue-700 hover:underline">Modifier</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
