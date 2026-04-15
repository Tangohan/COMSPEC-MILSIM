<?php
declare(strict_types=1);
$list = is_array($deploymentCommunities ?? null) ? $deploymentCommunities : [];
$csrf = htmlspecialchars((string) ($deploymentCsrf ?? ''), ENT_QUOTES, 'UTF-8');
?>
<div class="min-h-0 flex-1 bg-slate-50">
    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
        <p class="text-xs font-semibold uppercase tracking-wider text-amber-700"><a href="<?= htmlspecialchars(url('admin/system/deployment'), ENT_QUOTES, 'UTF-8') ?>" class="hover:underline">Déploiement</a></p>
        <h1 class="mt-2 text-2xl font-black text-slate-900">Communautés de préqualification</h1>
        <p class="mt-2 text-sm text-slate-600">Groupes de membres habilités à tester des évolutions avant généralisation.</p>

        <div class="mt-8 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Nom</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Active</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Priorité</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($list as $row): ?>
                        <?php $id = (int) ($row['id'] ?? 0); ?>
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-900"><?= htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-3"><?= !empty($row['is_active']) ? 'Oui' : 'Non' ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= (int) ($row['priority'] ?? 0) ?></td>
                            <td class="px-4 py-3">
                                <a href="<?= htmlspecialchars(url('admin/system/deployment/communities/' . $id . '/edit'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-amber-700 hover:text-amber-900">Modifier</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($list === []): ?>
            <p class="mt-4 text-sm text-slate-500">Aucune communauté en base.</p>
        <?php endif; ?>
    </div>
</div>
