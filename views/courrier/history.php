<?php
$c = $courrier ?? [];
$documents = $c['documents'] ?? [];
$statusFilter = $c['status_filter'] ?? null;
$baseUrl = url('');
$statusLabels = [
    'draft' => 'Brouillon',
    'pending_validation' => 'À valider',
    'validated' => 'Validé',
    'signed' => 'Signé',
    'sent' => 'Envoyé',
    'rejected' => 'Refusé',
    'archived' => 'Archivé',
];
$filters = [
    '' => 'Tous',
    'draft' => 'Brouillons',
    'pending_validation' => 'À valider',
    'validated' => 'Validés',
    'signed' => 'Signés',
    'sent' => 'Envoyés',
    'rejected' => 'Refusés',
];
?>
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-10">
    <header class="mb-8">
        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-sky-700 mb-1"><a href="<?= $baseUrl ?>/courrier" class="hover:underline">Bureau Courrier</a></p>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Historique</h1>
        <p class="mt-2 text-sm text-slate-600 max-w-2xl">Courriers où vous êtes rédacteur, validateur ou signataire.</p>
    </header>

    <div class="flex flex-wrap gap-2 mb-8">
        <?php foreach ($filters as $code => $label): ?>
            <?php
            $active = ($statusFilter === null && $code === '') || ($statusFilter !== null && $statusFilter === $code);
            $href = $code === '' ? $baseUrl . '/courrier/history' : $baseUrl . '/courrier/history?status=' . rawurlencode($code);
            ?>
            <a href="<?= htmlspecialchars($href) ?>"
               class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-bold uppercase tracking-wide border transition-colors <?= $active ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-600 border-slate-200 hover:border-slate-400' ?>">
                <?= htmlspecialchars($label) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($documents)): ?>
        <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 px-6 py-16 text-center">
            <p class="text-slate-600 font-medium">Aucun document pour ce filtre.</p>
            <p class="text-sm text-slate-500 mt-2">Les courriers <strong>signés</strong> apparaissent ici après signature, avant l’envoi.</p>
        </div>
    <?php else: ?>
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="p-3 sm:p-4 font-bold text-slate-600 text-xs uppercase tracking-wider">Document</th>
                        <th class="p-3 sm:p-4 font-bold text-slate-600 text-xs uppercase tracking-wider hidden sm:table-cell">Statut</th>
                        <th class="p-3 sm:p-4 font-bold text-slate-600 text-xs uppercase tracking-wider hidden md:table-cell">Modifié</th>
                        <th class="p-3 sm:p-4 w-24"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($documents as $d): ?>
                        <?php $st = (string)($d['status'] ?? ''); ?>
                        <tr class="hover:bg-slate-50/80">
                            <td class="p-3 sm:p-4">
                                <span class="font-semibold text-slate-900"><?= htmlspecialchars($d['title'] ?: $d['reference_number'] ?: 'Sans titre') ?></span>
                                <?php if (!empty($d['reference_number']) && ($d['title'] ?? '') !== ''): ?>
                                    <span class="block text-xs font-mono text-slate-500 mt-0.5"><?= htmlspecialchars($d['reference_number']) ?></span>
                                <?php endif; ?>
                                <span class="sm:hidden inline-block mt-1 text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-slate-100 text-slate-700"><?= htmlspecialchars($statusLabels[$st] ?? $st) ?></span>
                            </td>
                            <td class="p-3 sm:p-4 hidden sm:table-cell">
                                <span class="inline-flex text-xs font-bold uppercase px-2.5 py-1 rounded-lg <?= $st === 'signed' ? 'bg-emerald-100 text-emerald-900' : ($st === 'sent' ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-700') ?>">
                                    <?= htmlspecialchars($statusLabels[$st] ?? $st) ?>
                                </span>
                            </td>
                            <td class="p-3 sm:p-4 text-slate-500 text-xs hidden md:table-cell whitespace-nowrap"><?= !empty($d['updated_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime($d['updated_at']))) : '—' ?></td>
                            <td class="p-3 sm:p-4">
                                <a href="<?= $baseUrl ?>/courrier/read/<?= (int)$d['id'] ?>" class="text-sm font-bold text-sky-700 hover:text-sky-900 hover:underline">Ouvrir</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
