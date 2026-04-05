<?php
/** @var list<array<string, mixed>> $siteRolesData */
$siteRolesData = $siteRolesData ?? [];
$f = \App\Core\Session::getFlash('error');
$s = \App\Core\Session::getFlash('success');
?>
<div class="max-w-5xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-black text-slate-900">Affectations rôles site</h1>
        <a href="<?= url('admin') ?>" class="text-sm font-medium text-slate-600 hover:underline">Retour</a>
    </div>
    <p class="text-slate-600 text-sm mb-6">Attribuez un rôle plateforme à un compte par email. Les rôles site ne sont pas gérables depuis l’administration d’une communauté.</p>
    <?php if ($f): ?><p class="text-red-600 text-sm mb-4"><?= htmlspecialchars($f) ?></p><?php endif; ?>
    <?php if ($s): ?><p class="text-emerald-700 text-sm mb-4"><?= htmlspecialchars($s) ?></p><?php endif; ?>

    <?php
    $firstRoleId = 0;
    foreach ($siteRolesData as $block) {
        if (!empty($block['id'])) {
            $firstRoleId = (int) $block['id'];
            break;
        }
    }
    ?>
    <?php if ($firstRoleId > 0): ?>
    <form method="post" action="<?= url('admin/site-roles/assign') ?>" class="flex flex-wrap gap-3 items-end mb-10 border border-slate-200 rounded-lg p-4">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-xs text-slate-500 mb-1">Email</label>
            <input type="email" name="email" required class="w-full border border-slate-300 rounded px-3 py-2 text-sm" placeholder="staff@example.com">
        </div>
        <div>
            <label class="block text-xs text-slate-500 mb-1">Rôle site</label>
            <select name="role_id" class="border border-slate-300 rounded px-3 py-2 text-sm">
                <?php foreach ($siteRolesData as $block): ?>
                    <option value="<?= (int) ($block['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($block['name'] ?? '')) ?> (<?= htmlspecialchars((string) ($block['slug'] ?? '')) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded">Attribuer</button>
    </form>
    <?php endif; ?>

    <?php foreach ($siteRolesData as $block): ?>
        <div class="mb-10">
            <h2 class="text-lg font-bold text-slate-900 mb-2"><?= htmlspecialchars((string) ($block['name'] ?? '')) ?></h2>
            <p class="text-xs text-slate-500 mb-2">Slug : <?= htmlspecialchars((string) ($block['slug'] ?? '')) ?></p>
            <?php $assignments = $block['assignments'] ?? []; ?>
            <?php if (empty($assignments)): ?>
                <p class="text-slate-500 text-sm">Aucune affectation active.</p>
            <?php else: ?>
                <ul class="divide-y divide-slate-200 border border-slate-200 rounded-lg">
                    <?php foreach ($assignments as $a): ?>
                        <li class="px-4 py-2 flex justify-between items-center text-sm">
                            <span><?= htmlspecialchars((string) ($a['email_normalized'] ?? '')) ?></span>
                            <form method="post" action="<?= url('admin/site-roles/revoke') ?>" onsubmit="return confirm('Révoquer cette affectation ?');">
                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                                <input type="hidden" name="id" value="<?= (int) ($a['id'] ?? 0) ?>">
                                <button type="submit" class="text-red-600 text-xs underline">Révoquer</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
