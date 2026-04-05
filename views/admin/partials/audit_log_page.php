<?php
/** @var string $auditScope */
/** @var list<array<string, mixed>> $auditRows */
/** @var int $auditTotal */
/** @var int $auditPage */
/** @var int $auditTotalPages */
/** @var array<string, mixed> $auditFilters */
$auditScope = $auditScope ?? 'system';
$basePath = $auditScope === 'organization' ? 'back-office/audit' : 'admin/audit';
$showTenantCol = $auditScope === 'system';
$tableColspan = $showTenantCol ? 5 : 4;

$buildLink = static function (int $page) use ($auditFilters, $basePath): string {
    $q = array_merge($auditFilters, ['page' => $page > 1 ? $page : null]);
    $q = array_filter($q, static fn ($v) => $v !== null && $v !== '');

    return url($basePath) . ($q ? '?' . http_build_query($q) : '');
};
?>
<div class="max-w-6xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-black text-slate-900"><?= $auditScope === 'organization' ? 'Journal d\'activité' : 'Journaux d\'audit' ?></h1>
        <a href="<?= url($auditScope === 'organization' ? 'back-office' : 'admin') ?>" class="text-sm font-medium text-slate-600 hover:text-slate-900 underline">Retour</a>
    </div>

    <form method="get" action="<?= url($basePath) ?>" class="flex flex-wrap items-end gap-3 mb-6 p-4 rounded-xl border border-slate-200 bg-slate-50/80">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Du</label>
            <input type="date" name="date_from" value="<?= htmlspecialchars((string) ($auditFilters['date_from'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="w-full min-w-[10rem] rounded border border-slate-300 text-sm px-2 py-1.5" />
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Au</label>
            <input type="date" name="date_to" value="<?= htmlspecialchars((string) ($auditFilters['date_to'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="w-full min-w-[10rem] rounded border border-slate-300 text-sm px-2 py-1.5" />
        </div>
        <div class="flex-1 min-w-[12rem]">
            <label class="block text-xs font-medium text-slate-600 mb-1">Action (contient)</label>
            <input type="text" name="action" value="<?= htmlspecialchars((string) ($auditFilters['action'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded border border-slate-300 text-sm px-2 py-1.5" placeholder="ex. user_updated" />
        </div>
        <div class="w-28">
            <label class="block text-xs font-medium text-slate-600 mb-1">User ID</label>
            <input type="number" name="user_id" value="<?= $auditFilters['user_id'] !== null && $auditFilters['user_id'] !== '' ? (int) $auditFilters['user_id'] : '' ?>" class="w-full rounded border border-slate-300 text-sm px-2 py-1.5" min="1" />
        </div>
        <?php if ($showTenantCol): ?>
        <div class="w-28">
            <label class="block text-xs font-medium text-slate-600 mb-1">Tenant ID</label>
            <input type="number" name="tenant_id" value="<?= isset($auditFilters['tenant_id']) && $auditFilters['tenant_id'] ? (int) $auditFilters['tenant_id'] : '' ?>" class="w-full rounded border border-slate-300 text-sm px-2 py-1.5" min="1" />
        </div>
        <?php endif; ?>
        <div class="flex items-center gap-2 pb-0.5">
            <button type="submit" class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-800">Filtrer</button>
            <a href="<?= url($basePath) ?>" class="px-3 py-2 text-sm text-slate-600 hover:underline">Réinitialiser</a>
        </div>
    </form>

    <p class="text-sm text-slate-600 mb-3"><?= (int) $auditTotal ?> événement(s)</p>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-2">Date</th>
                    <?php if ($showTenantCol): ?><th class="px-3 py-2">Tenant</th><?php endif; ?>
                    <th class="px-3 py-2">Acteur</th>
                    <th class="px-3 py-2">Action</th>
                    <th class="px-3 py-2">Cible</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($auditRows)): ?>
                    <tr><td colspan="<?= (int) $tableColspan ?>" class="px-3 py-8 text-center text-slate-500">Aucun enregistrement.</td></tr>
                <?php else: ?>
                    <?php foreach ($auditRows as $row): ?>
                        <tr class="hover:bg-slate-50/80">
                            <td class="px-3 py-2 whitespace-nowrap text-slate-700"><?= htmlspecialchars((string) ($row['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <?php if ($showTenantCol): ?>
                                <td class="px-3 py-2 text-slate-700"><?= htmlspecialchars((string) ($row['tenant_name'] ?? ($row['tenant_id'] ?? '—')), ENT_QUOTES, 'UTF-8') ?></td>
                            <?php endif; ?>
                            <td class="px-3 py-2 text-slate-700"><?= htmlspecialchars((string) ($row['actor_email'] ?? ($row['user_id'] ?? '—')), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-3 py-2 font-mono text-xs text-slate-800"><?= htmlspecialchars((string) ($row['action'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-3 py-2 text-xs text-slate-600"><?= htmlspecialchars(trim(($row['entity_type'] ?? '') . ' #' . (string) ($row['entity_id'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($auditTotalPages > 1): ?>
        <div class="flex items-center justify-between mt-6 text-sm">
            <span class="text-slate-600">Page <?= (int) $auditPage ?> / <?= (int) $auditTotalPages ?></span>
            <div class="flex gap-2">
                <?php if ($auditPage > 1): ?>
                    <a class="px-3 py-1.5 rounded border border-slate-300 hover:bg-slate-50" href="<?= htmlspecialchars($buildLink($auditPage - 1), ENT_QUOTES, 'UTF-8') ?>">Précédent</a>
                <?php endif; ?>
                <?php if ($auditPage < $auditTotalPages): ?>
                    <a class="px-3 py-1.5 rounded border border-slate-300 hover:bg-slate-50" href="<?= htmlspecialchars($buildLink($auditPage + 1), ENT_QUOTES, 'UTF-8') ?>">Suivant</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
