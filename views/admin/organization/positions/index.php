<?php
declare(strict_types=1);

use App\Repositories\PositionRepository;

$positions = is_array($positions ?? null) ? $positions : [];
$roleSets = is_array($roleSets ?? null) ? $roleSets : [];
$positionCategoryLabels = is_array($positionCategoryLabels ?? null) ? $positionCategoryLabels : PositionRepository::CATEGORY_LABELS;
?>
<div class="max-w-3xl mx-auto px-6 py-12">
    <div class="mb-8 flex items-center justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-black text-slate-900">Postes organisationnels</h1>
            <p class="mt-2 text-sm text-slate-600 max-w-xl">
                Titres et responsabilités dans l’organisation (opérationnel, état-major ou administratif).
                Distincts des habilitations d’accès : un poste peut éventuellement appliquer un pack d’habilitations à l’affectation.
            </p>
        </div>
        <a href="<?= url('back-office/roles') ?>" class="text-sm font-medium text-slate-600 hover:underline">← Habilitations et rôles</a>
    </div>

    <?php if (\App\Core\Session::get('success')): ?>
    <p class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"><?= htmlspecialchars((string) \App\Core\Session::get('success')) ?></p>
    <?php \App\Core\Session::forget('success'); endif; ?>
    <?php if (\App\Core\Session::get('error')): ?>
    <p class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900"><?= htmlspecialchars((string) \App\Core\Session::get('error')) ?></p>
    <?php \App\Core\Session::forget('error'); endif; ?>

    <section class="mb-10 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-bold text-slate-800 mb-4">Nouveau poste</h2>
        <form method="post" action="<?= url('back-office/positions') ?>" class="space-y-4">
            <?= \App\Core\Csrf::field() ?>
            <div>
                <label for="pos_name" class="block text-sm font-medium text-slate-700">Intitulé</label>
                <input type="text" id="pos_name" name="name" required maxlength="160" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm" placeholder="Ex. Responsable administratif">
            </div>
            <div>
                <label for="pos_category" class="block text-sm font-medium text-slate-700">Catégorie</label>
                <select id="pos_category" name="category" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm">
                    <?php foreach ($positionCategoryLabels as $catValue => $catLabel): ?>
                        <option value="<?= htmlspecialchars((string) $catValue, ENT_QUOTES, 'UTF-8') ?>"<?= (string) $catValue === PositionRepository::CATEGORY_ADMINISTRATIVE ? ' selected' : '' ?>><?= htmlspecialchars((string) $catLabel, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="mt-1 text-xs text-slate-500">La catégorie administrative sert aux positions de gestion, distinctes des postes de terrain.</p>
            </div>
            <div>
                <label for="pos_desc" class="block text-sm font-medium text-slate-700">Description (optionnel)</label>
                <textarea id="pos_desc" name="description" rows="2" maxlength="500" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm"></textarea>
            </div>
            <div>
                <label for="pos_role_set" class="block text-sm font-medium text-slate-700">Pack d’habilitations associé (optionnel)</label>
                <select id="pos_role_set" name="default_role_set_id" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm">
                    <option value="">Aucun — le poste n’accorde pas d’accès automatiquement</option>
                    <?php foreach ($roleSets as $rs): ?>
                        <option value="<?= (int) ($rs['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($rs['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="mt-1 text-xs text-slate-500">Si un pack est choisi, il sera ajouté aux habilitations du membre lors de l’affectation du poste.</p>
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="is_temporary" value="1" class="rounded border-slate-300">
                Poste temporaire ou rotatif
            </label>
            <button type="submit" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Créer</button>
        </form>
    </section>

    <section>
        <h2 class="text-sm font-bold text-slate-800 mb-3">Liste</h2>
        <?php if ($positions === []): ?>
        <p class="text-sm text-slate-500">Aucun poste pour l’instant. Les affectations se font depuis la fiche membre (back-office).</p>
        <?php else: ?>
        <ul class="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
            <?php foreach ($positions as $p): ?>
            <?php
            $pid = (int) ($p['id'] ?? 0);
            $cat = (string) ($p['category'] ?? PositionRepository::CATEGORY_OPERATIONAL);
            $catLabel = (string) ($positionCategoryLabels[$cat] ?? PositionRepository::categoryLabel($cat));
            $packName = trim((string) ($p['default_role_set_name'] ?? ''));
            ?>
            <li class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-4 py-3">
                <div>
                    <p class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($p['name'] ?? '')) ?></p>
                    <?php if (!empty($p['description'])): ?>
                    <p class="text-xs text-slate-600 mt-0.5"><?= htmlspecialchars((string) $p['description']) ?></p>
                    <?php endif; ?>
                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                        <span class="inline-block text-[10px] font-semibold uppercase tracking-wide text-slate-700 bg-slate-100 px-2 py-0.5 rounded"><?= htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if (!empty($p['is_temporary'])): ?>
                        <span class="inline-block text-[10px] font-semibold uppercase tracking-wide text-amber-800 bg-amber-50 px-2 py-0.5 rounded">Temporaire</span>
                        <?php endif; ?>
                        <?php if ($packName !== ''): ?>
                        <span class="inline-block text-[10px] font-semibold uppercase tracking-wide text-violet-800 bg-violet-50 px-2 py-0.5 rounded">Pack : <?= htmlspecialchars($packName, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <form method="post" action="<?= url('back-office/positions/' . $pid . '/delete') ?>" onsubmit="return confirm('Supprimer ce poste ?');">
                    <?= \App\Core\Csrf::field() ?>
                    <button type="submit" class="text-sm text-red-700 hover:underline">Supprimer</button>
                </form>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>
</div>
