<?php
$users = $users ?? [];
$roles = $roles ?? [];
$completenessByUser = $completenessByUser ?? [];
$filters = $filters ?? [];
$usersTotal = $usersTotal ?? null;
$usersPage = $usersPage ?? 1;
$usersTotalPages = $usersTotalPages ?? 1;
$usersQuery = static function (int $page) use ($filters): string {
    $q = [
        'search' => $filters['search'] ?? null,
        'status' => !empty($filters['status']) ? $filters['status'] : null,
        'role_id' => !empty($filters['role_id']) ? (int) $filters['role_id'] : null,
        'filter_incomplete' => !empty($filters['filter_incomplete']) ? '1' : null,
        'page' => $page > 1 ? $page : null,
    ];
    $q = array_filter($q, static fn ($v) => $v !== null && $v !== '');

    return url('admin/organization/users') . ($q ? '?' . http_build_query($q) : '');
};
?>
<div class="max-w-6xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-black text-slate-900">Utilisateurs</h1>
        <a href="<?= url('admin/organization/users/create') ?>" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Nouvel utilisateur</a>
    </div>

    <form method="get" action="<?= url('admin/organization/users') ?>" class="flex flex-wrap gap-3 mb-6 p-4 bg-slate-50 rounded-lg">
        <input type="text" name="search" value="<?= htmlspecialchars($filters['search'] ?? '') ?>" placeholder="Recherche (email, nom, indicatif)" class="px-3 py-2 border border-slate-200 rounded text-sm w-48">
        <select name="status" class="px-3 py-2 border border-slate-200 rounded text-sm">
            <option value="">Tous les statuts</option>
            <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Actif</option>
            <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>En attente</option>
            <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactif</option>
        </select>
        <select name="role_id" class="px-3 py-2 border border-slate-200 rounded text-sm">
            <option value="">Tous les rôles</option>
            <?php foreach ($roles as $r): ?>
            <option value="<?= (int) $r['id'] ?>" <?= (int) ($filters['role_id'] ?? 0) === (int) $r['id'] ? 'selected' : '' ?>><?= htmlspecialchars($r['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="flex items-center gap-2 cursor-pointer px-3 py-2 text-sm">
            <input type="checkbox" name="filter_incomplete" value="1" <?= !empty($filters['filter_incomplete']) ? 'checked' : '' ?>>
            À corriger (profils incomplets)
        </label>
        <button type="submit" class="px-4 py-2 bg-slate-700 text-white text-sm rounded hover:bg-slate-600">Filtrer</button>
        <a href="<?= url('admin/organization/users') ?>" class="px-4 py-2 text-slate-600 text-sm hover:underline">Réinitialiser</a>
    </form>

    <?php if ($usersTotal !== null): ?>
    <p class="text-sm text-slate-600 mb-3"><?= (int) $usersTotal ?> utilisateur(s) — page <?= (int) $usersPage ?> / <?= (int) $usersTotalPages ?></p>
    <?php endif; ?>

    <?php if (empty($users)): ?>
    <p class="text-slate-500">Aucun utilisateur.</p>
    <?php else: ?>
    <table class="w-full border border-slate-200 rounded-lg overflow-hidden">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Email</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Nom</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Rôle</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Statut</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Complétude</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u):
                $uid = (int) $u['id'];
                $comp = $completenessByUser[$uid] ?? ['score' => 0, 'sections_critiques' => []];
            ?>
            <tr class="border-b border-slate-100 hover:bg-slate-50">
                <td class="p-3"><?= htmlspecialchars($u['email']) ?></td>
                <td class="p-3"><?= htmlspecialchars($u['display_name'] ?? '—') ?></td>
                <td class="p-3"><?= htmlspecialchars($u['role_name'] ?? '—') ?></td>
                <td class="p-3">
                    <span class="px-2 py-0.5 text-xs rounded <?= ($u['status'] ?? '') === 'active' ? 'bg-emerald-100 text-emerald-800' : (($u['status'] ?? '') === 'inactive' ? 'bg-slate-200 text-slate-600' : 'bg-amber-100 text-amber-800') ?>"><?= htmlspecialchars($u['status'] ?? '—') ?></span>
                </td>
                <td class="p-3">
                    <?php if ($comp['score'] >= 100): ?>
                    <span class="text-emerald-600 text-sm font-medium">Complet</span>
                    <?php elseif (!empty($comp['sections_critiques'])): ?>
                    <span class="text-rose-600 text-sm font-medium" title="<?= htmlspecialchars(implode(', ', $comp['sections_critiques'])) ?>"><?= (int) $comp['score'] ?>% — incomplet</span>
                    <?php else: ?>
                    <span class="text-slate-600 text-sm"><?= (int) $comp['score'] ?>%</span>
                    <?php endif; ?>
                </td>
                <td class="p-3">
                    <a href="<?= url('admin/organization/users/' . $uid) ?>" class="text-slate-700 hover:underline text-sm">Voir</a>
                    <span class="mx-1">|</span>
                    <a href="<?= url('admin/organization/users/' . $uid . '/edit') ?>" class="text-slate-700 hover:underline text-sm">Modifier</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($usersTotal !== null && (int) $usersTotalPages > 1): ?>
    <div class="flex items-center justify-between mt-4 text-sm">
        <?php if ($usersPage > 1): ?>
        <a class="text-slate-700 hover:underline" href="<?= htmlspecialchars($usersQuery($usersPage - 1), ENT_QUOTES, 'UTF-8') ?>">← Précédent</a>
        <?php else: ?><span></span><?php endif; ?>
        <?php if ($usersPage < $usersTotalPages): ?>
        <a class="text-slate-700 hover:underline" href="<?= htmlspecialchars($usersQuery($usersPage + 1), ENT_QUOTES, 'UTF-8') ?>">Suivant →</a>
        <?php else: ?><span></span><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
    <p class="mt-6 text-sm text-slate-500"><a href="<?= url('admin/organization') ?>" class="underline">Retour administration organisationnelle</a></p>
</div>
