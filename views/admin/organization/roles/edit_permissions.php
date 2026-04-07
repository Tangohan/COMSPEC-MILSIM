<?php
declare(strict_types=1);

$role = $role ?? null;
$permissionIds = $permissionIds ?? [];
$allPermissions = is_array($allPermissions ?? null) ? $allPermissions : [];
$moduleLabels = is_array($moduleLabels ?? null) ? $moduleLabels : [];
if (!$role) {
    echo '<p>Rôle introuvable.</p>';
    return;
}
$rid = (int) $role['id'];
$permSet = array_flip(array_map('intval', $permissionIds));
$byModule = [];
foreach ($allPermissions as $p) {
    $m = trim((string) ($p['module'] ?? ''));
    if ($m === '') {
        $m = 'autre';
    }
    $byModule[$m][] = $p;
}
foreach ($byModule as $mk => $list) {
    usort($byModule[$mk], static function (array $a, array $b): int {
        return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });
}
$moduleOrder = array_keys($moduleLabels);
$seen = [];
$sortedKeys = [];
foreach ($moduleOrder as $k) {
    if (isset($byModule[$k]) && !isset($seen[$k])) {
        $sortedKeys[] = $k;
        $seen[$k] = true;
    }
}
foreach (array_keys($byModule) as $k) {
    if (!isset($seen[$k])) {
        $sortedKeys[] = $k;
    }
}
?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <div class="mb-6 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
        Cochez les habilitations actives pour le rôle <strong class="font-semibold"><?= htmlspecialchars((string) ($role['name'] ?? '')) ?></strong> dans votre communauté. Les changements s’appliquent aux membres qui portent déjà ce rôle après leur prochaine action sur le portail.
    </div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-8">
        <h1 class="text-2xl font-black text-slate-900">Habilitations du rôle</h1>
        <div class="flex flex-wrap gap-3">
            <a href="<?= url('back-office/roles/' . $rid) ?>" class="text-sm font-semibold text-slate-600 hover:underline">Fiche du rôle</a>
            <a href="<?= url('back-office/roles') ?>" class="text-sm font-semibold text-slate-600 hover:underline">Liste des rôles</a>
        </div>
    </div>

    <form method="post" action="<?= url('back-office/roles/' . $rid . '/permissions') ?>" class="space-y-8">
        <?= \App\Core\Csrf::field() ?>
        <?php foreach ($sortedKeys as $moduleKey):
            $perms = $byModule[$moduleKey] ?? [];
            if ($perms === []) {
                continue;
            }
            $sectionTitle = $moduleLabels[$moduleKey] ?? ($moduleKey === 'autre' ? 'Autres' : ucfirst(str_replace('_', ' ', $moduleKey)));
            ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-500 mb-4"><?= htmlspecialchars($sectionTitle) ?></h2>
            <ul class="grid gap-3 sm:grid-cols-1 md:grid-cols-2">
                <?php foreach ($perms as $p):
                    $pid = (int) $p['id'];
                    $checked = isset($permSet[$pid]);
                    ?>
                <li class="flex items-start gap-3 rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-2.5">
                    <input type="checkbox" name="permission_ids[]" value="<?= $pid ?>" id="perm_<?= $pid ?>" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" <?= $checked ? 'checked' : '' ?>>
                    <label for="perm_<?= $pid ?>" class="cursor-pointer text-sm leading-snug text-slate-800">
                        <span class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($p['name'] ?? '')) ?></span>
                        <?php if (!empty($p['action'])): ?>
                        <span class="block text-xs text-slate-500 mt-0.5"><?= htmlspecialchars((string) $p['action']) ?></span>
                        <?php endif; ?>
                    </label>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endforeach; ?>

        <?php if ($allPermissions === []): ?>
        <p class="text-slate-500 text-sm">Aucune habilitation n’est définie pour cette communauté. Vérifiez la configuration ou contactez l’administrateur plateforme.</p>
        <?php endif; ?>

        <div class="flex flex-wrap gap-3 pt-2">
            <button type="submit" class="inline-flex items-center rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white hover:bg-slate-800">Enregistrer</button>
            <a href="<?= url('back-office/roles/' . $rid) ?>" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Annuler</a>
        </div>
    </form>
</div>
