<?php
/** @var list<array<string, mixed>> $platformAlerts */
$rows = $platformAlerts ?? [];
$canManagePlatform = \App\Core\Gate::getInstance()->allows('admin.system');
?>
<div class="max-w-5xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-black text-slate-900">Alertes plateforme</h1>
        <div class="flex gap-3">
            <?php if ($canManagePlatform): ?>
            <a href="<?= url('admin/system/alerts/create') ?>" class="inline-flex items-center px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-bold hover:bg-emerald-600 transition-colors">Nouvelle alerte</a>
            <?php endif; ?>
            <a href="<?= url('admin') ?>" class="text-sm font-medium text-slate-600 hover:text-slate-900 underline">Retour</a>
        </div>
    </div>
    <?php $s = \App\Core\Session::getFlash('success'); $e = \App\Core\Session::getFlash('error'); ?>
    <?php if ($s): ?><p class="text-emerald-700 text-sm mb-4"><?= htmlspecialchars($s) ?></p><?php endif; ?>
    <?php if ($e): ?><p class="text-red-600 text-sm mb-4"><?= htmlspecialchars($e) ?></p><?php endif; ?>

    <?php if ($rows === []): ?>
        <p class="text-slate-600 text-sm"><?= $canManagePlatform
            ? 'Aucune alerte. Créez-en une pour afficher bandeaux promo, nouveautés ou messages urgents sur l’interface.'
            : 'Aucune alerte configurée pour le moment.' ?></p>
    <?php else: ?>
        <ul class="space-y-3">
            <?php foreach ($rows as $r): ?>
                <?php
                $id = (int) ($r['id'] ?? 0);
                $active = ! empty($r['is_active']);
                ?>
                <li class="rounded-xl border border-slate-200 bg-white p-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-black uppercase tracking-widest text-slate-500"><?= htmlspecialchars((string) ($r['kind'] ?? '')) ?></p>
                        <p class="font-black text-slate-900"><?= htmlspecialchars((string) ($r['title'] ?? '')) ?></p>
                        <p class="text-xs text-slate-500 mt-1"><?= $active ? 'Active' : 'Inactive' ?> · ordre <?= (int) ($r['sort_order'] ?? 0) ?></p>
                    </div>
                    <?php if ($canManagePlatform): ?>
                    <div class="flex gap-2">
                        <a href="<?= url('admin/system/alerts/' . $id . '/edit') ?>" class="text-sm font-semibold text-blue-700 hover:underline">Modifier</a>
                        <form method="post" action="<?= url('admin/system/alerts/' . $id . '/delete') ?>" class="inline" onsubmit="return confirm('Supprimer cette alerte ?');">
                            <?= \App\Core\Csrf::field() ?>
                            <button type="submit" class="text-sm font-semibold text-rose-600 hover:underline">Supprimer</button>
                        </form>
                    </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
