<?php
declare(strict_types=1);
/** @var list<array<string, mixed>> $cooperationAnnouncementRows */
$rows = $cooperationAnnouncementRows ?? [];
$evtLabels = $cooperationAnnouncementEventLabels ?? [];
$chLabels = $cooperationAnnouncementChannelLabels ?? [];
?>
<div class="max-w-6xl mx-auto px-6 py-12">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h1 class="text-2xl font-black text-slate-900">Messages types — coopération</h1>
        <a href="<?= url('admin') ?>" class="text-sm font-medium text-slate-600 hover:text-slate-900 underline">Retour</a>
    </div>
    <p class="text-sm text-slate-600 mb-6 max-w-3xl">Définissez les textes par défaut pour les courriels, les notifications du portail et, le cas échéant, les publications forum. Les communautés peuvent ensuite ajuster leurs propres réglages.</p>
    <?php $s = \App\Core\Session::getFlash('success'); $e = \App\Core\Session::getFlash('error'); ?>
    <?php if ($s): ?><p class="text-emerald-700 text-sm mb-4"><?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <?php if ($e): ?><p class="text-red-600 text-sm mb-4"><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

    <?php if ($rows === []): ?>
        <p class="text-slate-600 text-sm">Aucun gabarit. Exécutez les migrations si besoin.</p>
    <?php else: ?>
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Événement</th>
                        <th class="px-4 py-3">Canal</th>
                        <th class="px-4 py-3">Actif</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($rows as $r): ?>
                        <?php
                        $ek = (string) ($r['event_key'] ?? '');
                        $ch = (string) ($r['channel'] ?? '');
                        $active = !empty($r['is_active']);
                        $evtLab = $evtLabels[$ek] ?? $ek;
                        $chLab = $chLabels[$ch] ?? $ch;
                        ?>
                        <tr class="hover:bg-slate-50/80">
                            <td class="px-4 py-3 font-medium text-slate-900"><?= htmlspecialchars($evtLab, ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($chLab, ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-3"><?= $active ? '<span class="text-emerald-700 font-medium">Oui</span>' : '<span class="text-slate-400">Non</span>' ?></td>
                            <td class="px-4 py-3 text-right">
                                <a href="<?= url('admin/system/cooperation/announcements/edit?event=' . rawurlencode($ek) . '&channel=' . rawurlencode($ch)) ?>" class="text-sm font-semibold text-blue-700 hover:underline">Modifier</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
