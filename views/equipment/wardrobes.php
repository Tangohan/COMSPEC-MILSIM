<?php
$wardrobes = $wardrobes ?? [];
$collections = $collections ?? [];
$migrationMissing = !empty($migrationMissing);
$csrf = \App\Core\Csrf::token();
?>
<div class="max-w-5xl mx-auto px-6 py-10">
    <div class="mb-8">
        <p class="text-sm text-slate-500 mb-1">
            <a href="<?= url('equipment') ?>" class="underline hover:text-slate-700">Équipement</a>
            <span class="mx-1">/</span>
            Wardrobes
        </p>
        <h1 class="text-2xl font-black text-slate-900 tracking-tight">Wardrobes ACE Arsenal</h1>
        <p class="mt-2 text-slate-600 max-w-2xl">
            Sauvegardez vos loadouts ACE Arsenal depuis le jeu, synchronisez-les en ligne et organisez-les en collections d’équipement.
            Un panneau Athena s’affiche aussi dans l’arsenal in-game.
        </p>
    </div>

    <?php if ($migrationMissing): ?>
    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900 text-sm mb-8">
        Tables non encore créées sur cette instance. Lancez les migrations (<code class="text-xs">arsenal_wardrobe</code>) puis rechargez cette page.
    </div>
    <?php endif; ?>

    <section class="mb-10">
        <h2 class="text-lg font-bold text-slate-800 mb-3">Collections d’équipement</h2>
        <?php if (!$migrationMissing): ?>
        <form method="post" action="<?= url('equipment/wardrobes/collections') ?>" class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 items-end">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <label class="block text-sm">
                <span class="text-slate-600">Nom</span>
                <input name="name" required maxlength="120" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Ex. Assaut nocturne">
            </label>
            <label class="block text-sm">
                <span class="text-slate-600">Visibilité</span>
                <select name="visibility" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="personal">Perso</option>
                    <option value="unit">Unité</option>
                    <option value="tenant">Communauté</option>
                </select>
            </label>
            <label class="block text-sm sm:col-span-2 lg:col-span-1">
                <span class="text-slate-600">Tags (virgules)</span>
                <input name="tags" maxlength="200" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="assaut, nuit">
            </label>
            <button type="submit" class="rounded-lg bg-slate-900 text-white text-sm font-semibold px-4 py-2 hover:bg-slate-800">Créer</button>
        </form>
        <?php endif; ?>

        <?php if (empty($collections)): ?>
        <p class="text-sm text-slate-500">Aucune collection pour le moment.</p>
        <?php else: ?>
        <ul class="space-y-2">
            <?php foreach ($collections as $c): ?>
            <li class="flex flex-wrap items-center justify-between gap-3 border border-slate-200 rounded-xl bg-white px-4 py-3">
                <div>
                    <span class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($c['name'] ?? '')) ?></span>
                    <span class="ml-2 text-xs uppercase tracking-wide text-slate-500"><?= htmlspecialchars((string) ($c['visibility'] ?? 'personal')) ?></span>
                    <p class="text-sm text-slate-500 mt-0.5"><?= (int) ($c['wardrobe_count'] ?? 0) ?> wardrobe(s)</p>
                </div>
                <?php if (!$migrationMissing): ?>
                <form method="post" action="<?= url('equipment/wardrobes/collections/' . (int) ($c['id'] ?? 0) . '/delete') ?>" onsubmit="return confirm('Supprimer cette collection ?');">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <button type="submit" class="text-sm text-rose-600 hover:underline">Supprimer</button>
                </form>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>

    <section>
        <h2 class="text-lg font-bold text-slate-800 mb-3">Wardrobes synchronisées</h2>
        <?php if (empty($wardrobes)): ?>
        <p class="text-sm text-slate-500">
            Aucune wardrobe en ligne. Ouvrez ACE Arsenal in-game → panneau <strong>Athena</strong> → « Sauvegarder tout » après liaison ATAK.
        </p>
        <?php else: ?>
        <ul class="space-y-2">
            <?php foreach ($wardrobes as $w): ?>
            <li class="flex flex-wrap items-center justify-between gap-3 border border-slate-200 rounded-xl bg-white px-4 py-3">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="font-semibold text-slate-900 truncate"><?= htmlspecialchars((string) ($w['name'] ?? '')) ?></span>
                        <?php if (!empty($w['is_favorite'])): ?>
                        <span class="text-xs text-amber-600">★</span>
                        <?php endif; ?>
                    </div>
                    <p class="text-sm text-slate-500 mt-0.5">
                        <?= htmlspecialchars((string) ($w['collection_name'] ?? 'Sans collection')) ?>
                        · <?= (int) ($w['payload_bytes'] ?? 0) ?> o
                        <?php if (!empty($w['last_synced_at'])): ?>
                        · sync <?= htmlspecialchars((string) $w['last_synced_at']) ?>
                        <?php endif; ?>
                    </p>
                </div>
                <?php if (!$migrationMissing && (int) ($w['user_id'] ?? 0) === (int) Session::get('user_id')): ?>
                <form method="post" action="<?= url('equipment/wardrobes/' . (int) ($w['id'] ?? 0) . '/delete') ?>" onsubmit="return confirm('Supprimer cette wardrobe ?');">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <button type="submit" class="text-sm text-rose-600 hover:underline">Supprimer</button>
                </form>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>
</div>
