<?php
$units = $units ?? [];
$unitTypes = $unitTypes ?? [];
// Sections créées d'office pour le pilotage des formations (TenantPedagogyStructureService) : recréées si supprimées.
$reservedTypes = [
    'pedagogy_pilotage' => 'Pilotage et expertise (réservé)',
    'pedagogy_bureau_competences' => 'Bureau du personnel et des compétences (réservé)',
];
$typeLabel = function ($type) use ($unitTypes, $reservedTypes) {
    $type = (string) $type;

    return $unitTypes[$type]['label'] ?? $reservedTypes[$type] ?? ($type !== '' ? ucfirst(str_replace('_', ' ', $type)) : '—');
};
?>
<div class="bo-legacy bo-legacy--4xl">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-black text-slate-900">Unités / Équipes / Groupes</h1>
        <a href="<?= url('back-office/units/create') ?>" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Nouvelle unité</a>
    </div>
    <?php if (\App\Core\Session::get('success')): ?>
    <p class="mb-4 text-sm text-emerald-600"><?= htmlspecialchars(\App\Core\Session::get('success')) ?></p>
    <?php \App\Core\Session::forget('success'); endif; ?>
    <?php if (\App\Core\Session::get('error')): ?>
    <p class="mb-4 text-sm text-red-600"><?= htmlspecialchars(\App\Core\Session::get('error')) ?></p>
    <?php \App\Core\Session::forget('error'); endif; ?>
    <?php if (empty($units)): ?>
    <p class="text-slate-500">Aucune unité. <a href="<?= url('back-office/units/create') ?>" class="underline">Créer une unité, équipe ou groupe</a>.</p>
    <?php else: ?>
    <table class="w-full border border-slate-200 rounded-lg overflow-hidden">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Nom</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Type</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Code</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Ordre</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($units as $u): ?>
            <tr class="border-b border-slate-100 hover:bg-slate-50">
                <td class="p-3 font-medium"><?= htmlspecialchars($u['name']) ?></td>
                <td class="p-3"><?= htmlspecialchars($typeLabel($u['type'] ?? '')) ?></td>
                <td class="p-3"><?= htmlspecialchars($u['code'] ?? '—') ?></td>
                <td class="p-3"><?= (int) ($u['display_order'] ?? 0) ?></td>
                <td class="p-3">
                    <a href="<?= url('back-office/units/' . $u['id'] . '/edit') ?>" class="text-slate-600 hover:text-slate-900 text-sm underline">Modifier</a>
                    <?php if (isset($reservedTypes[(string) ($u['type'] ?? '')])): ?>
                    <span class="ml-2 text-xs text-slate-400" title="Section créée automatiquement pour le pilotage des formations : elle serait recréée.">Section réservée</span>
                    <?php else: ?>
                    <form action="<?= url('back-office/units/' . $u['id'] . '/delete') ?>" method="post" class="inline ml-2" onsubmit="return confirm('Supprimer cette unité ?');">
                        <?= \App\Core\Csrf::field() ?>
                        <button type="submit" class="text-red-600 hover:text-red-800 text-sm underline">Supprimer</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <p class="mt-6 text-sm text-slate-500"><a href="<?= url('back-office') ?>" class="underline">Retour au back-office</a> · <a href="<?= url('orbat') ?>" class="underline">Voir l’ORBAT</a></p>
</div>
