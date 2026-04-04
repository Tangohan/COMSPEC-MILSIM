<?php
/** @var array $orgWorkQueue */
/** @var list<array<string, mixed>> $orgModerationRecent */
/** @var string|null $orgModerationError */
$wq = $orgWorkQueue ?? ['expired_invitations' => [], 'training_expiring' => [], 'error_invitations' => null, 'error_training' => null];
$mod = $orgModerationRecent ?? [];
$modErr = $orgModerationError ?? null;
?>
<div class="grid md:grid-cols-3 gap-6 mb-8">
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="text-sm font-bold text-slate-900 mb-2">Invitations expirées</h3>
        <?php if (!empty($wq['error_invitations'])): ?>
            <p class="text-sm text-rose-600"><?= htmlspecialchars($wq['error_invitations'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php elseif (empty($wq['expired_invitations'])): ?>
            <p class="text-sm text-slate-500">Aucune invitation expirée en attente.</p>
        <?php else: ?>
            <ul class="text-sm space-y-1">
                <?php foreach ($wq['expired_invitations'] as $inv): ?>
                    <li class="flex justify-between gap-2 text-slate-700">
                        <span class="truncate"><?= htmlspecialchars((string) ($inv['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="text-slate-500 whitespace-nowrap"><?= htmlspecialchars((string) ($inv['expires_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <a href="<?= url('admin/organization/invitations') ?>" class="inline-block mt-3 text-sm font-medium text-amber-700 hover:underline">Gérer les invitations</a>
        <?php endif; ?>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="text-sm font-bold text-slate-900 mb-2">Formations (échéance proche)</h3>
        <?php if (!empty($wq['error_training'])): ?>
            <p class="text-sm text-rose-600"><?= htmlspecialchars($wq['error_training'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php elseif (empty($wq['training_expiring'])): ?>
            <p class="text-sm text-slate-500">Rien à relancer sur 30 jours.</p>
        <?php else: ?>
            <ul class="text-sm space-y-1">
                <?php foreach ($wq['training_expiring'] as $row): ?>
                    <li class="text-slate-700">
                        <span class="font-medium"><?= htmlspecialchars((string) ($row['course_title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="text-slate-500"> — <?= htmlspecialchars((string) ($row['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <a href="<?= url('admin/training') ?>" class="inline-block mt-3 text-sm font-medium text-amber-700 hover:underline">Formations admin</a>
        <?php endif; ?>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="text-sm font-bold text-slate-900 mb-2">Modération récente</h3>
        <?php if ($modErr): ?>
            <p class="text-sm text-rose-600"><?= htmlspecialchars($modErr, ENT_QUOTES, 'UTF-8') ?></p>
        <?php elseif (empty($mod)): ?>
            <p class="text-sm text-slate-500">Aucune action récente.</p>
        <?php else: ?>
            <ul class="text-sm space-y-1">
                <?php foreach ($mod as $a): ?>
                    <li class="text-slate-700">
                        <span class="font-mono text-xs"><?= htmlspecialchars((string) ($a['action_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="text-slate-500"> — <?= htmlspecialchars((string) ($a['target_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <a href="<?= url('admin/organization/moderation') ?>" class="inline-block mt-3 text-sm font-medium text-amber-700 hover:underline">Ouvrir la modération</a>
        <?php endif; ?>
    </div>
</div>
