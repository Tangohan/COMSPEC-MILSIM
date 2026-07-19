<?php
declare(strict_types=1);

use App\Services\Admin\TenantRolePermissionPresetService;

$role = is_array($role ?? null) ? $role : null;
$rolePermissions = is_array($rolePermissions ?? null) ? $rolePermissions : [];
if (!is_array($role)) {
    echo '<div class="mx-auto max-w-lg px-6 py-16 text-center">';
    echo '<p class="text-lg font-semibold text-slate-800">Ce rôle est introuvable ou n’appartient pas à votre communauté.</p>';
    echo '<a href="' . htmlspecialchars(url('back-office/roles'), ENT_QUOTES, 'UTF-8') . '" class="mt-6 inline-flex rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-slate-800">Retour à la liste des rôles</a>';
    echo '</div>';

    return;
}
$rid = (int) ($role['id'] ?? 0);
$__g = \App\Core\Gate::getInstance();
$roleLocked = (int) ($role['is_locked'] ?? 0) !== 0;
$canEditPermissions = ($__g->allows('admin.organization') || $__g->allows('admin.roles.manage') || $__g->allows('admin.permissions.manage')) && !$roleLocked;

$tier = (string) ($role['semantic_tier'] ?? 'function');
$tierHuman = match ($tier) {
    'authority' => 'Commandement',
    'function' => 'Emploi',
    'liaison' => 'Liaison',
    'support' => 'Soutien',
    'specialty' => 'Spécialité',
    'status' => 'Statut affiché',
    default => 'Emploi',
};
$tierChip = match ($tier) {
    'authority' => 'bg-rose-100 text-rose-900 ring-1 ring-rose-200/80',
    'function' => 'bg-sky-100 text-sky-900 ring-1 ring-sky-200/80',
    'liaison' => 'bg-amber-100 text-amber-950 ring-1 ring-amber-200/80',
    'support' => 'bg-teal-100 text-teal-900 ring-1 ring-teal-200/80',
    'specialty' => 'bg-violet-100 text-violet-900 ring-1 ring-violet-200/80',
    'status' => 'bg-slate-200 text-slate-800 ring-1 ring-slate-300/80',
    default => 'bg-sky-100 text-sky-900 ring-1 ring-sky-200/80',
};

$moduleLabels = TenantRolePermissionPresetService::permissionModuleLabelsFr();
$byModule = [];
foreach ($rolePermissions as $p) {
    if (!is_array($p)) {
        continue;
    }
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
$sortedModuleKeys = [];
foreach ($moduleOrder as $k) {
    if (isset($byModule[$k]) && !isset($seen[$k])) {
        $sortedModuleKeys[] = $k;
        $seen[$k] = true;
    }
}
foreach (array_keys($byModule) as $k) {
    if (!isset($seen[$k])) {
        $sortedModuleKeys[] = $k;
    }
}

$permCount = count($rolePermissions);
$desc = trim((string) ($role['description'] ?? ''));
?>
<div class="min-h-[calc(100vh-3.5rem)] bg-gradient-to-b from-slate-100/90 via-slate-50 to-white">
    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 sm:py-10 lg:py-12">
        <nav class="mb-6 text-sm text-slate-500" aria-label="Fil d’Ariane">
            <a href="<?= htmlspecialchars(url('back-office'), ENT_QUOTES, 'UTF-8') ?>" class="font-medium text-slate-600 underline decoration-slate-300 underline-offset-2 hover:text-slate-900">Centre de pilotage</a>
            <span class="mx-2 text-slate-300" aria-hidden="true">/</span>
            <a href="<?= htmlspecialchars(url('back-office/roles'), ENT_QUOTES, 'UTF-8') ?>" class="font-medium text-slate-600 underline decoration-slate-300 underline-offset-2 hover:text-slate-900">Rôles communautaires</a>
            <span class="mx-2 text-slate-300" aria-hidden="true">/</span>
            <span class="font-semibold text-slate-800"><?= htmlspecialchars((string) ($role['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
        </nav>

        <div class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm shadow-slate-900/[0.04]">
            <div class="h-1.5 bg-gradient-to-r from-emerald-500 via-sky-500 to-violet-500" aria-hidden="true"></div>
            <div class="border-b border-slate-100 px-5 py-6 sm:px-8 sm:py-8">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 space-y-3">
                        <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-500">Rôle communautaire</p>
                        <h1 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl"><?= htmlspecialchars((string) ($role['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold <?= htmlspecialchars($tierChip, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($tierHuman, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if ($roleLocked): ?>
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-950 ring-1 ring-amber-200/80" title="Ce rôle ne peut pas être modifié depuis votre espace">
                                    <svg class="h-3.5 w-3.5 shrink-0 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 00-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                                    Verrouillé
                                </span>
                            <?php endif; ?>
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold tabular-nums text-slate-700 ring-1 ring-slate-200/80">
                                <?= (int) $permCount ?> habilitation<?= $permCount > 1 ? 's' : '' ?>
                            </span>
                        </div>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center lg:shrink-0">
                        <?php if ($canEditPermissions): ?>
                            <a href="<?= htmlspecialchars(url('back-office/roles/' . $rid . '/permissions'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                                Modifier les habilitations
                            </a>
                        <?php elseif ($roleLocked): ?>
                            <p class="max-w-xs text-xs leading-relaxed text-amber-800 sm:text-right">Les habilitations de ce rôle sont gérées par la plateforme et ne sont pas modifiables ici.</p>
                        <?php endif; ?>
                        <a href="<?= htmlspecialchars(url('back-office/roles/' . $rid . '/edit-presentation'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-bold text-slate-800 shadow-sm transition hover:border-slate-400 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2">
                            Présentation du rôle
                        </a>
                        <a href="<?= htmlspecialchars(url('back-office/roles'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                            ← Liste des rôles
                        </a>
                    </div>
                </div>
            </div>

            <div class="px-5 py-5 sm:px-8 sm:py-6">
                <div class="rounded-xl border border-slate-200/90 bg-slate-50/80 px-4 py-3 text-sm leading-relaxed text-slate-700">
                    Habilitations de ce rôle au sein de <strong class="font-semibold text-slate-900">votre communauté</strong>. Les rôles réservés à l’ensemble du site ne sont pas modifiables depuis cet espace.
                </div>
            </div>

            <div class="grid gap-6 border-t border-slate-100 px-5 py-6 sm:px-8 sm:py-8 lg:grid-cols-12">
                <div class="space-y-5 lg:col-span-5">
                    <h2 class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Informations</h2>
                    <dl class="space-y-4 rounded-2xl border border-slate-200/90 bg-slate-50/50 p-5">
                        <div>
                            <dt class="text-xs font-bold uppercase tracking-wider text-slate-500">Description</dt>
                            <dd class="mt-1 text-sm leading-relaxed text-slate-800">
                                <?php if ($desc !== ''): ?>
                                    <?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?>
                                <?php else: ?>
                                    <span class="text-slate-500 italic">Aucune description renseignée.</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <?php if (!empty($role['category'])): ?>
                            <div class="border-t border-slate-200/80 pt-4">
                                <dt class="text-xs font-bold uppercase tracking-wider text-slate-500">Famille</dt>
                                <dd class="mt-1 text-sm font-medium text-slate-800"><?= htmlspecialchars((string) $role['category'], ENT_QUOTES, 'UTF-8') ?></dd>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($role['subcategory'])): ?>
                            <div class="border-t border-slate-200/80 pt-4">
                                <dt class="text-xs font-bold uppercase tracking-wider text-slate-500">Sous-ensemble</dt>
                                <dd class="mt-1 text-sm font-medium text-slate-800"><?= htmlspecialchars((string) $role['subcategory'], ENT_QUOTES, 'UTF-8') ?></dd>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($role['label_en'])): ?>
                            <div class="border-t border-slate-200/80 pt-4">
                                <dt class="text-xs font-bold uppercase tracking-wider text-slate-500">Libellé en anglais</dt>
                                <dd class="mt-1 text-sm text-slate-800"><?= htmlspecialchars((string) $role['label_en'], ENT_QUOTES, 'UTF-8') ?></dd>
                            </div>
                        <?php endif; ?>
                        <div class="border-t border-slate-200/80 pt-4">
                            <details class="group text-sm">
                                <summary class="cursor-pointer list-none font-semibold text-slate-600 marker:content-none [&::-webkit-details-marker]:hidden">
                                    <span class="inline-flex items-center gap-1 underline decoration-slate-300 underline-offset-2 group-open:text-slate-900">
                                        Référence technique (support)
                                        <svg class="h-4 w-4 shrink-0 text-slate-400 transition group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                    </span>
                                </summary>
                                <p class="mt-2 rounded-lg bg-white px-3 py-2 font-mono text-xs text-slate-700 ring-1 ring-slate-200"><?= htmlspecialchars((string) ($role['slug'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                            </details>
                        </div>
                    </dl>
                </div>

                <div class="lg:col-span-7">
                    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                        <h2 class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Habilitations actives</h2>
                        <?php if ($canEditPermissions && $permCount > 0): ?>
                            <a href="<?= htmlspecialchars(url('back-office/roles/' . $rid . '/permissions'), ENT_QUOTES, 'UTF-8') ?>" class="text-xs font-bold text-emerald-700 underline decoration-emerald-300 underline-offset-2 hover:text-emerald-900">Ajuster la liste</a>
                        <?php endif; ?>
                    </div>

                    <?php if ($permCount === 0): ?>
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/80 px-6 py-12 text-center">
                            <p class="text-sm font-medium text-slate-600">Aucune habilitation n’est encore associée à ce rôle.</p>
                            <?php if ($canEditPermissions): ?>
                                <a href="<?= htmlspecialchars(url('back-office/roles/' . $rid . '/permissions'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 inline-flex rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-slate-800">Configurer les habilitations</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="max-h-[min(32rem,70vh)] space-y-5 overflow-y-auto rounded-2xl border border-slate-200/90 bg-white p-4 pr-2 shadow-inner sm:p-5">
                            <?php foreach ($sortedModuleKeys as $modKey):
                                $perms = $byModule[$modKey] ?? [];
                                if ($perms === []) {
                                    continue;
                                }
                                $modTitle = $moduleLabels[$modKey] ?? ($modKey === 'autre' ? 'Autres' : $modKey);
                                $modId = 'role-mod-' . preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string) $modKey);
                                ?>
                                <section aria-labelledby="<?= htmlspecialchars($modId, ENT_QUOTES, 'UTF-8') ?>">
                                    <h3 id="<?= htmlspecialchars($modId, ENT_QUOTES, 'UTF-8') ?>" class="sticky top-0 z-[1] -mx-1 mb-2 bg-white/95 px-1 py-1 text-[11px] font-black uppercase tracking-[0.16em] text-slate-500 backdrop-blur-sm">
                                        <?= htmlspecialchars((string) $modTitle, ENT_QUOTES, 'UTF-8') ?>
                                    </h3>
                                    <ul class="space-y-1.5">
                                        <?php foreach ($perms as $p): ?>
                                            <?php if (!is_array($p)) {
                                                continue;
                                            } ?>
                                            <li>
                                                <span class="flex items-start gap-2 rounded-xl border border-slate-100 bg-slate-50/60 px-3 py-2.5 text-sm text-slate-800 transition hover:border-slate-200 hover:bg-white">
                                                    <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-700" aria-hidden="true">
                                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                    </span>
                                                    <span class="min-w-0 leading-snug"><?= htmlspecialchars((string) ($p['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </section>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
