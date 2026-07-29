<?php
declare(strict_types=1);
/** @var list<array<string, mixed>> $militaryUnits */
/** @var list<array<string, mixed>> $militaryCountries */
/** @var string $searchQuery */
/** @var string $filterCountry */
$units = $militaryUnits ?? [];
$countries = $militaryCountries ?? [];
$q = $searchQuery ?? '';
$fc = $filterCountry ?? '';
?>
<div class="max-w-6xl mx-auto px-6 py-12">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-black text-slate-900">Référentiel militaire</h1>
            <p class="text-sm text-slate-600 mt-1 max-w-2xl">Organisations, commandements et unités de forces spéciales. Les données sont en base et servent à l’affiliation des communautés.</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="<?= url('admin/system/military-referential/create') ?>" class="inline-flex items-center px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-bold hover:bg-emerald-600 transition-colors">Nouvelle entité</a>
            <a href="<?= url('admin') ?>" class="text-sm font-medium text-slate-600 hover:text-slate-900 underline">Retour</a>
        </div>
    </div>

    <?php $s = \App\Core\Session::getFlash('success'); $e = \App\Core\Session::getFlash('error'); ?>
    <?php if ($s): ?><p class="text-emerald-700 text-sm mb-4"><?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <?php if ($e): ?><p class="text-red-600 text-sm mb-4"><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

    <form method="get" action="<?= url('admin/system/military-referential') ?>" class="flex flex-wrap gap-3 mb-6 items-end">
        <div>
            <label class="block text-xs font-semibold text-slate-500 mb-1" for="mil-q">Rechercher</label>
            <input type="search" name="q" id="mil-q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Hubert, USASOC, plongée…" class="rounded-lg border border-slate-300 px-3 py-2 text-sm min-w-[16rem]">
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-500 mb-1" for="mil-country">Pays</label>
            <select name="country" id="mil-country" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Tous</option>
                <?php foreach ($countries as $c): ?>
                    <option value="<?= htmlspecialchars((string) $c['iso2'], ENT_QUOTES, 'UTF-8') ?>" <?= $fc === (string) $c['iso2'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $c['name_fr'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="rounded-lg bg-slate-800 text-white text-sm font-semibold px-4 py-2">Filtrer</button>
    </form>

    <?php if ($units === []): ?>
        <p class="text-slate-600 text-sm">Aucune entité trouvée.</p>
    <?php else: ?>
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Entité</th>
                        <th class="px-4 py-3">Pays / service</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Rattachement</th>
                        <th class="px-4 py-3">Statut</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($units as $u): ?>
                        <?php $id = (int) ($u['id'] ?? 0); ?>
                        <tr class="hover:bg-slate-50/80">
                            <td class="px-4 py-3">
                                <p class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($u['display_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="text-xs text-slate-500 mt-0.5"><?= htmlspecialchars((string) ($u['official_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                <?= htmlspecialchars((string) ($u['country_name_fr'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                <?php if (!empty($u['service_name'])): ?>
                                    <br><span class="text-xs"><?= htmlspecialchars((string) $u['service_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars((string) ($u['entity_type_label_fr'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-3 text-slate-600 text-xs"><?= htmlspecialchars((string) ($u['parent_display_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-3">
                                <?php if (!empty($u['active'])): ?>
                                    <span class="text-emerald-700 font-medium">Actif</span>
                                <?php else: ?>
                                    <span class="text-slate-400">Inactif</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="<?= url('admin/system/military-referential/' . $id . '/edit') ?>" class="text-sm font-semibold text-blue-700 hover:underline">Modifier</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
