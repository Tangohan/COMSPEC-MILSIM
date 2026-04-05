<?php
$course = $course ?? [];
$tenant = $tenant ?? null;
$id = (int) ($course['id'] ?? 0);
$cycle = $course['showcase_cycle_date'] ?? '';
if (is_string($cycle) && strlen($cycle) >= 10) {
    $cycle = substr($cycle, 0, 10);
}
?>
<div class="max-w-3xl mx-auto px-6 py-12">
    <p class="text-sm text-slate-500 mb-2">
        <a href="<?= url('admin/training/courses') ?>" class="underline hover:text-slate-800">← Formations</a>
    </p>
    <h1 class="text-2xl font-black text-slate-900 mb-2">Vitrine dashboard</h1>
    <p class="text-slate-600 text-sm mb-8">
        Ajustez les cartes « Nos formations » sur le dashboard public
        <?php if ($tenant): ?>
        (communauté <strong><?= htmlspecialchars(community_display_name($tenant)) ?></strong>).
        <?php else: ?>
        de votre communauté.
        <?php endif; ?>
        Les formations doivent être en visibilité <strong>published</strong> pour apparaître.
    </p>

    <form method="post" action="<?= url('admin/training/courses/' . $id . '/showcase') ?>" class="space-y-8">
        <?= \App\Core\Csrf::field() ?>

        <section class="rounded-xl border border-slate-200 bg-white p-6 space-y-4">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-500">Médias</h2>
            <p class="text-xs text-slate-500">Chemins relatifs au dossier public (ex. <code class="bg-slate-100 px-1 rounded">uploads/training/visuel.jpg</code>) ou URL HTTPS.</p>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Miniature (carte)</label>
                <input type="text" name="thumbnail_path" value="<?= htmlspecialchars((string) ($course['thumbnail_path'] ?? '')) ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="uploads/...">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Bannière (modale)</label>
                <input type="text" name="banner_path" value="<?= htmlspecialchars((string) ($course['banner_path'] ?? '')) ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="uploads/...">
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-6 space-y-4">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-500">Textes & détail</h2>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Accroche courte</label>
                <input type="text" name="short_description" maxlength="500" value="<?= htmlspecialchars((string) ($course['short_description'] ?? '')) ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Description (modale)</label>
                <textarea name="description" rows="6" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono"><?= htmlspecialchars((string) ($course['description'] ?? '')) ?></textarea>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-6 space-y-4">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-500">Bandeau carte</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Date du cycle</label>
                    <input type="date" name="showcase_cycle_date" value="<?= htmlspecialchars($cycle) ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Lieu / modalité</label>
                    <input type="text" name="showcase_location" value="<?= htmlspecialchars((string) ($course['showcase_location'] ?? '')) ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="Paris / Visio">
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Badge</label>
                    <select name="showcase_badge" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        <?php
                        $b = (string) ($course['showcase_badge'] ?? 'open');
                        foreach (['open' => 'Ouvert', 'full' => 'Complet', 'coming_soon' => 'Bientôt', 'closed' => 'Fermé'] as $val => $lab):
                        ?>
                        <option value="<?= htmlspecialchars($val) ?>" <?= $b === $val ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Style visuel carte</label>
                    <select name="showcase_card_style" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        <?php
                        $s = (string) ($course['showcase_card_style'] ?? 'default');
                        foreach (['default' => 'Couleur', 'grayscale' => 'Noir & blanc (hover couleur)'] as $val => $lab):
                        ?>
                        <option value="<?= htmlspecialchars($val) ?>" <?= $s === $val ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Ordre d’affichage (optionnel)</label>
                <?php
                $sortVal = $course['showcase_sort_order'] ?? null;
                $sortOut = ($sortVal === null || $sortVal === '') ? '' : (string) (int) $sortVal;
                ?>
                <input type="number" name="showcase_sort_order" min="0" step="1" value="<?= htmlspecialchars($sortOut) ?>" class="w-full max-w-xs border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="Plus petit = en premier">
            </div>
        </section>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="px-6 py-3 bg-slate-900 text-white text-sm font-bold rounded-lg hover:bg-slate-800">Enregistrer</button>
            <a href="<?= url('formations/' . htmlspecialchars($course['slug'] ?? '')) ?>" class="px-6 py-3 border border-slate-300 text-slate-700 text-sm font-semibold rounded-lg hover:bg-slate-50">Voir la fiche formation</a>
        </div>
    </form>
</div>
