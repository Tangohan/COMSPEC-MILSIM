<?php
$c = $courrier ?? [];
$baseUrl = url('');
$docs = $c['recent_documents'] ?? [];
$statusLabels = [
    'draft' => 'Brouillon',
    'pending_validation' => 'À valider',
    'validated' => 'Validé',
    'signed' => 'Signé',
    'rejected' => 'Refusé',
    'sent' => 'Envoyé',
    'archived' => 'Archivé',
];
?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-10">
    <header class="mb-8 pb-6 border-b border-slate-200/80">
        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-sky-700 mb-1">Bureau Courrier</p>
        <h1 class="text-3xl sm:text-4xl font-black text-slate-900 tracking-tight">Traçabilité décisionnelle</h1>
        <p class="mt-2 text-sm text-slate-600 max-w-3xl">
            Vue dédiée au suivi des validations et au contrôle documentaire. Ouvrez un document pour consulter la checklist conformité,
            l’historique unifié versions+décisions et les liens vers les dossiers personnels associés.
        </p>
        <div class="mt-4 flex flex-wrap gap-2">
            <a href="<?= $baseUrl ?>/courrier" class="px-4 py-2 rounded-lg text-sm font-semibold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50">Tableau courrier</a>
            <a href="<?= $baseUrl ?>/courrier/history" class="px-4 py-2 rounded-lg text-sm font-semibold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50">Historique</a>
            <a href="<?= $baseUrl ?>/courrier/archives" class="px-4 py-2 rounded-lg text-sm font-semibold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50">Archives</a>
        </div>
    </header>

    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-8">
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-xs uppercase tracking-wide text-amber-900/80 font-bold">À valider</p>
            <p class="text-2xl font-black text-amber-900"><?= (int) ($c['pending_count'] ?? 0) ?></p>
        </div>
        <div class="rounded-xl border border-sky-200 bg-sky-50 p-4">
            <p class="text-xs uppercase tracking-wide text-sky-900/80 font-bold">Validés</p>
            <p class="text-2xl font-black text-sky-900"><?= (int) ($c['validated_count'] ?? 0) ?></p>
        </div>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-xs uppercase tracking-wide text-emerald-900/80 font-bold">Signés</p>
            <p class="text-2xl font-black text-emerald-900"><?= (int) ($c['signed_count'] ?? 0) ?></p>
        </div>
        <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
            <p class="text-xs uppercase tracking-wide text-blue-900/80 font-bold">Envoyés</p>
            <p class="text-2xl font-black text-blue-900"><?= (int) ($c['sent_count'] ?? 0) ?></p>
        </div>
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4">
            <p class="text-xs uppercase tracking-wide text-rose-900/80 font-bold">Refusés</p>
            <p class="text-2xl font-black text-rose-900"><?= (int) ($c['rejected_count'] ?? 0) ?></p>
        </div>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
            <h2 class="text-sm font-black uppercase tracking-[0.14em] text-slate-900">Derniers documents (accès traçabilité)</h2>
        </div>
        <?php if (empty($docs)): ?>
        <p class="p-6 text-sm text-slate-500">Aucun document trouvé pour cette communauté.</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-left font-bold">Référence</th>
                        <th class="px-4 py-3 text-left font-bold">Titre</th>
                        <th class="px-4 py-3 text-left font-bold">Statut</th>
                        <th class="px-4 py-3 text-left font-bold">Mis à jour</th>
                        <th class="px-4 py-3 text-right font-bold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($docs as $d): ?>
                    <?php $status = (string) ($d['status'] ?? 'draft'); ?>
                    <tr>
                        <td class="px-4 py-3 font-mono text-xs text-slate-700"><?= htmlspecialchars((string) ($d['reference_number'] ?? ('#' . (int) ($d['id'] ?? 0)))) ?></td>
                        <td class="px-4 py-3 font-semibold text-slate-900"><?= htmlspecialchars((string) ($d['title'] ?? 'Sans titre')) ?></td>
                        <td class="px-4 py-3 text-slate-700"><?= htmlspecialchars($statusLabels[$status] ?? $status) ?></td>
                        <td class="px-4 py-3 text-slate-500"><?= !empty($d['updated_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $d['updated_at']))) : '—' ?></td>
                        <td class="px-4 py-3 text-right">
                            <a href="<?= $baseUrl ?>/courrier/read/<?= (int) ($d['id'] ?? 0) ?>" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50">
                                Ouvrir traçabilité
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
</div>
