<?php
/** @var list<array<string, mixed>> $adminRecentActivity */
/** @var string|null $adminRecentActivityError */
/** @var string $adminRecentActivityMoreUrl */
$rows = $adminRecentActivity ?? [];
$error = $adminRecentActivityError ?? null;
$moreUrl = $adminRecentActivityMoreUrl ?? url('admin/audit');
?>
<div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5 mb-8">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold text-slate-900">Activité récente</h2>
        <a href="<?= htmlspecialchars($moreUrl, ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-medium text-amber-700 hover:underline">Voir tout</a>
    </div>
    <?php if ($error): ?>
        <p class="text-sm text-rose-600"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php elseif (empty($rows)): ?>
        <p class="text-sm text-slate-500">Aucun événement récent.</p>
    <?php else: ?>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($rows as $row): ?>
                <li class="py-2 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                    <span class="text-slate-500 whitespace-nowrap"><?= htmlspecialchars((string) ($row['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="font-medium text-slate-800"><?= htmlspecialchars(audit_action_label_fr((string) ($row['action'] ?? '')), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="text-slate-600"><?= htmlspecialchars((string) ($row['actor_email'] ?? ('#' . (string) ($row['user_id'] ?? ''))), ENT_QUOTES, 'UTF-8') ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
