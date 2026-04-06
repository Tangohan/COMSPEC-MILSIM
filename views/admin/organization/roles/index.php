<?php
$roles = $roles ?? [];
if (!is_array($roles)) {
    $roles = [];
}
$roleViewSections = $roleViewSections ?? [];
if (!is_array($roleViewSections)) {
    $roleViewSections = [];
}
$permissionCounts = $permissionCounts ?? [];
$roleLayerFilter = $roleLayerFilter ?? '';
$roleTierFilter = $roleTierFilter ?? '';
$base = url('back-office/roles');
$__g = \App\Core\Gate::getInstance();
$canPresets = $__g->allows('admin.organization') || $__g->allows('admin.roles.manage') || $__g->allows('admin.permissions.manage');

$tierChip = static function (string $t): array {
    return match ($t) {
        'authority' => ['Commandement', 'bg-rose-100 text-rose-900 ring-rose-200'],
        'function' => ['Emploi', 'bg-sky-100 text-sky-900 ring-sky-200'],
        'liaison' => ['Liaison', 'bg-amber-100 text-amber-950 ring-amber-200'],
        'support' => ['Soutien', 'bg-teal-100 text-teal-900 ring-teal-200'],
        'specialty' => ['Spécialité', 'bg-violet-100 text-violet-900 ring-violet-200'],
        'status' => ['Statut affiché', 'bg-slate-200 text-slate-800 ring-slate-300'],
        default => ['Emploi', 'bg-sky-100 text-sky-900 ring-sky-200'],
    };
};

$appendQuery = static function (string $baseUrl, array $params): string {
    $q = http_build_query(array_filter($params, static fn ($v) => $v !== null && $v !== ''));

    return $q === '' ? $baseUrl : $baseUrl . '?' . $q;
};
?>
<div class="max-w-5xl mx-auto px-6 py-12">
    <div class="mb-6 rounded-lg border border-blue-100 bg-blue-50/90 px-4 py-3 text-sm text-slate-800">
        Vous gérez ici les rôles propres à <strong class="font-semibold">votre communauté</strong> (gouvernance et rôles opérationnels).
        Les habilitations réservées à l’ensemble du site sont gérées par l’administration plateforme.
    </div>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
        <h1 class="text-2xl font-black text-slate-900">Rôles communauté</h1>
        <div class="flex flex-wrap gap-2">
            <?php if ($canPresets): ?>
            <a href="<?= url('back-office/roles/presets') ?>" class="inline-flex items-center rounded-lg bg-blue-700 px-4 py-2 text-sm font-bold text-white hover:bg-blue-800">Profils de permissions</a>
            <a href="<?= url('back-office/positions') ?>" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Postes organisationnels</a>
            <?php endif; ?>
            <a href="<?= url('back-office') ?>" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Back-office</a>
        </div>
    </div>
    <p class="text-slate-600 text-sm mb-2">Liste structurée par famille opérationnelle (catégorie et sous-ensemble), puis par type de rôle. L’ordre reflète une hiérarchie d’emplois, pas un grade automatique.</p>
    <p class="text-slate-500 text-xs mb-4">Le <strong class="font-semibold text-slate-700">nombre de droits</strong> indique combien d’habilitations sont actives pour ce rôle. Utilisez <a href="<?= url('back-office/roles/presets') ?>" class="font-semibold text-blue-700 underline">Profils de permissions</a> pour harmoniser les jeux de droits.</p>
    <?php
    $layerTousParams = [];
    if ($roleTierFilter !== '') {
        $layerTousParams['tier'] = $roleTierFilter;
    }
    ?>
    <div class="flex flex-wrap gap-2 mb-3 text-sm">
        <a href="<?= htmlspecialchars($appendQuery($base, $layerTousParams)) ?>" class="px-3 py-1 rounded border <?= $roleLayerFilter === '' ? 'bg-slate-900 text-white border-slate-900' : 'border-slate-300 text-slate-700 hover:bg-slate-50' ?>">Tous</a>
        <a href="<?= htmlspecialchars($appendQuery($base, ['layer' => 'community', 'tier' => $roleTierFilter === '' ? null : $roleTierFilter])) ?>" class="px-3 py-1 rounded border <?= $roleLayerFilter === 'community' ? 'bg-slate-900 text-white border-slate-900' : 'border-slate-300 text-slate-700 hover:bg-slate-50' ?>">Gouvernance communauté</a>
        <a href="<?= htmlspecialchars($appendQuery($base, ['layer' => 'intra', 'tier' => $roleTierFilter === '' ? null : $roleTierFilter])) ?>" class="px-3 py-1 rounded border <?= $roleLayerFilter === 'intra' ? 'bg-slate-900 text-white border-slate-900' : 'border-slate-300 text-slate-700 hover:bg-slate-50' ?>">Rôles opérationnels</a>
    </div>
    <p class="text-xs font-semibold text-slate-600 mb-1">Filtrer par type de rôle</p>
    <div class="flex flex-wrap gap-2 mb-6 text-sm">
        <?php
        $tierLinks = [
            '' => 'Tous les types',
            'authority' => 'Commandement',
            'function' => 'Emploi',
            'liaison' => 'Liaison',
            'support' => 'Soutien',
            'specialty' => 'Spécialité',
            'status' => 'Statut affiché',
        ];
        foreach ($tierLinks as $tv => $tlab):
            $active = $roleTierFilter === $tv;
            $params = ['tier' => $tv === '' ? null : $tv];
            if ($roleLayerFilter !== '') {
                $params['layer'] = $roleLayerFilter;
            }
        ?>
        <a href="<?= htmlspecialchars($appendQuery($base, $params)) ?>" class="px-3 py-1 rounded border <?= $active ? 'bg-indigo-900 text-white border-indigo-900' : 'border-slate-300 text-slate-700 hover:bg-slate-50' ?>"><?= htmlspecialchars($tlab) ?></a>
        <?php endforeach; ?>
    </div>
    <?php if (empty($roles)): ?>
    <p class="text-slate-500">Aucun rôle ne correspond à ces filtres.</p>
    <?php else: ?>
    <?php foreach ($roleViewSections as $section): ?>
    <?php
        $secRoles = $section['roles'] ?? [];
        if (!is_array($secRoles) || $secRoles === []) {
            continue;
        }
        $secTitle = (string) ($section['title'] ?? '');
    ?>
    <div class="mb-8">
        <p class="text-xs font-black uppercase tracking-widest text-slate-600 mb-3"><?= htmlspecialchars($secTitle) ?></p>
        <table class="w-full border border-slate-200 rounded-xl overflow-hidden shadow-sm">
            <thead class="bg-slate-100 border-b border-slate-200">
                <tr>
                    <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Rôle</th>
                    <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Type</th>
                    <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Référence interne</th>
                    <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Périmètre</th>
                    <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase" title="Nombre de droits accordés à ce rôle">Droits</th>
                    <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($secRoles as $r):
                    $rid = (int) $r['id'];
                    $count = $permissionCounts[$rid] ?? 0;
                    $layer = (string) ($r['role_layer'] ?? 'community');
                    $stier = (string) ($r['semantic_tier'] ?? 'function');
                    [$tLab, $tClass] = $tierChip($stier);
                    ?>
                <tr class="border-b border-slate-100 hover:bg-blue-50/40 transition-colors <?= $layer === 'community' ? 'bg-white' : 'bg-slate-50/40' ?>">
                    <td class="p-3">
                        <span class="font-semibold text-slate-900"><?= htmlspecialchars($r['name']) ?></span>
                        <?php if (!empty($r['label_en'])): ?>
                        <span class="block text-[11px] text-slate-500 mt-0.5"><?= htmlspecialchars((string) $r['label_en']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($r['description'])): ?>
                        <span class="block text-[11px] text-slate-500 mt-1 leading-snug max-w-md"><?= htmlspecialchars((string) $r['description']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3 align-top">
                        <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-bold ring-1 ring-inset <?= htmlspecialchars($tClass) ?>"><?= htmlspecialchars($tLab) ?></span>
                    </td>
                    <td class="p-3 text-slate-600 font-mono text-xs align-top"><?= htmlspecialchars($r['slug']) ?></td>
                    <td class="p-3 text-xs align-top">
                        <span class="inline-flex rounded-full px-2 py-0.5 font-semibold <?= $layer === 'community' ? 'bg-indigo-100 text-indigo-900' : 'bg-emerald-100 text-emerald-900' ?>">
                            <?= $layer === 'community' ? 'Communauté' : 'Unité' ?>
                        </span>
                    </td>
                    <td class="p-3 align-top tabular-nums text-slate-800"><?= $count ?></td>
                    <td class="p-3 align-top"><a href="<?= url('back-office/roles/' . $rid) ?>" class="text-blue-800 hover:underline text-sm font-semibold">Configurer</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
