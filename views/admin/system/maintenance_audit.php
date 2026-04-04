<?php

declare(strict_types=1);

$rule = $maintenanceRule ?? [];
$rows = $auditRows ?? [];
$id = (int) ($rule['id'] ?? 0);
?>
<div class="max-w-5xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-black text-slate-900">Historique — règle #<?= $id ?></h1>
        <a href="<?= url('admin/system/maintenance') ?>" class="text-sm text-slate-600 hover:underline">Retour</a>
    </div>
    <p class="text-sm text-slate-600 mb-4">Scope : <code class="bg-slate-100 px-1 rounded"><?= htmlspecialchars((string) ($rule['scope'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></p>

    <?php if ($rows === []): ?>
        <p class="text-slate-500">Aucune entrée d’audit.</p>
    <?php else: ?>
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Action</th>
                        <th class="px-4 py-3">Acteur</th>
                        <th class="px-4 py-3">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($rows as $a): ?>
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap text-xs"><?= htmlspecialchars((string) ($a['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-3 font-mono text-xs"><?= htmlspecialchars((string) ($a['action_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-3"><?= $a['actor_user_id'] !== null ? (int) $a['actor_user_id'] : '—' ?></td>
                            <td class="px-4 py-3 font-mono text-xs"><?= htmlspecialchars((string) ($a['actor_ip'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <tr class="bg-slate-50/50">
                            <td colspan="4" class="px-4 py-3 text-xs text-slate-600">
                                <details>
                                    <summary class="cursor-pointer font-medium text-slate-800">JSON</summary>
                                    <pre class="mt-2 whitespace-pre-wrap break-all"><?= htmlspecialchars((string) ($a['old_values'] ?? ''), ENT_QUOTES, 'UTF-8') ?></pre>
                                    <pre class="mt-2 whitespace-pre-wrap break-all"><?= htmlspecialchars((string) ($a['new_values'] ?? ''), ENT_QUOTES, 'UTF-8') ?></pre>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
