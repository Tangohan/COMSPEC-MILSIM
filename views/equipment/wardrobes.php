<?php
$wardrobes = $wardrobes ?? [];
$collections = $collections ?? [];
$migrationMissing = !empty($migrationMissing);
$csrf = \App\Core\Csrf::token();
$me = (int) \App\Core\Session::get('user_id');
$visibilityLabel = static function (string $v): string {
    return match ($v) {
        'unit' => 'Unité',
        'tenant' => 'Communauté',
        default => 'Personnel',
    };
};
?>
<div class="max-w-5xl mx-auto px-6 py-10">
    <div class="mb-8">
        <p class="text-sm text-slate-500 mb-1">
            <a href="<?= url('equipment') ?>" class="underline hover:text-slate-700">Équipement</a>
            <span class="mx-1">/</span>
            Tenues
        </p>
        <h1 class="text-2xl font-black text-slate-900 tracking-tight">Tenues de la communauté</h1>
        <p class="mt-2 text-slate-600 max-w-2xl">
            Chaque membre voit les tenues enregistrées depuis l’arsenal en jeu. Envoyez les vôtres depuis le bandeau Athena, en haut de l’écran d’équipement.
        </p>
    </div>

    <?php if ($migrationMissing): ?>
    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900 text-sm mb-8">
        Cette page n’est pas encore prête sur cette instance. Demandez à l’administration d’appliquer la mise à jour, puis rechargez.
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
                <span class="text-slate-600">Qui peut s’en servir</span>
                <select name="visibility" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="personal">Moi seulement</option>
                    <option value="unit">Mon unité</option>
                    <option value="tenant">Toute la communauté</option>
                </select>
            </label>
            <label class="block text-sm sm:col-span-2 lg:col-span-1">
                <span class="text-slate-600">Mots-clés (facultatif)</span>
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
                    <span class="ml-2 text-xs uppercase tracking-wide text-slate-500"><?= htmlspecialchars($visibilityLabel((string) ($c['visibility'] ?? 'personal'))) ?></span>
                    <p class="text-sm text-slate-500 mt-0.5"><?= (int) ($c['wardrobe_count'] ?? 0) ?> tenue(s)</p>
                </div>
                <?php if (!$migrationMissing): ?>
                <form method="post" action="<?= url('equipment/wardrobes/collections/' . (int) ($c['id'] ?? 0) . '/delete') ?>" onsubmit="return confirm('Retirer cette collection ?');">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <button type="submit" class="text-sm text-rose-600 hover:underline">Retirer</button>
                </form>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>

    <section>
        <h2 class="text-lg font-bold text-slate-800 mb-3">Tenues enregistrées</h2>
        <?php if (empty($wardrobes)): ?>
        <p class="text-sm text-slate-500">
            Aucune tenue n’a encore été envoyée. En jeu, ouvrez l’arsenal, puis le bandeau Athena en haut de l’écran, et choisissez « Envoyer vers Athena » une fois le compte relié.
        </p>
        <?php else: ?>
        <ul class="space-y-2">
            <?php foreach ($wardrobes as $w): ?>
            <?php
                $owner = trim((string) ($w['owner_label'] ?? ''));
                $isMine = (int) ($w['user_id'] ?? 0) === $me;
            ?>
            <li class="flex flex-wrap items-center justify-between gap-3 border border-slate-200 rounded-xl bg-white px-4 py-3">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="font-semibold text-slate-900 truncate"><?= htmlspecialchars((string) ($w['name'] ?? '')) ?></span>
                        <?php if (!empty($w['is_favorite'])): ?>
                        <span class="text-xs text-amber-600">★</span>
                        <?php endif; ?>
                    </div>
                    <p class="text-sm text-slate-500 mt-0.5">
                        <?= htmlspecialchars($owner !== '' ? $owner : 'Membre') ?>
                        · <?= htmlspecialchars((string) ($w['collection_name'] ?? 'Sans collection')) ?>
                    </p>
                </div>
                <?php if (!$migrationMissing && $isMine): ?>
                <form method="post" action="<?= url('equipment/wardrobes/' . (int) ($w['id'] ?? 0) . '/delete') ?>" onsubmit="return confirm('Retirer cette tenue ?');">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <button type="submit" class="text-sm text-rose-600 hover:underline">Retirer</button>
                </form>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>
</div>
