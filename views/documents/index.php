<?php
$documents = $documents ?? [];
$categories = $categories ?? [];
$currentCategoryId = $currentCategoryId ?? null;
$search = $search ?? '';
$totalDocs = count($documents);
$totalCategories = count($categories);
?>
<style>
.doc-page .doc-panel {
    background: rgba(255,255,255,0.78);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(15,23,42,0.06);
    box-shadow: 0 18px 40px rgba(15,23,42,0.05);
}
.doc-page .doc-card:hover {
    transform: translateY(-4px);
    border-color: rgba(16,185,129,0.25);
    box-shadow: 0 20px 45px rgba(15,23,42,0.07);
}
</style>

<div class="doc-page min-h-screen bg-slate-100 text-slate-900 overflow-x-hidden">
    <div class="max-w-[1800px] mx-auto px-6 py-8 space-y-8">

        <header id="overview" class="doc-panel rounded-[2rem] p-6 md:p-8 overflow-hidden relative">
            <div class="absolute top-0 left-0 w-full h-[2px] bg-gradient-to-r from-emerald-500/80 via-emerald-500/20 to-transparent"></div>
            <div class="flex flex-col xl:flex-row xl:items-end xl:justify-between gap-8">
                <div class="max-w-3xl">
                    <p class="text-[9px] font-black tracking-[0.45em] text-emerald-600 uppercase mb-4">Documentation</p>
                    <h2 class="text-3xl md:text-5xl font-black tracking-tight uppercase leading-none mb-5">
                        Doctrine, SOP &amp;<br>Manuels
                    </h2>
                    <div class="h-[1px] w-20 bg-slate-900/10 mb-5"></div>
                    <p class="text-slate-500 text-[11px] font-bold tracking-[0.18em] uppercase leading-relaxed max-w-2xl">
                        Consultation des documents publiés, fiches et ressources opérationnelles.
                    </p>
                </div>
                <div class="grid grid-cols-2 xl:grid-cols-4 gap-3 min-w-full xl:min-w-[400px]">
                    <div class="bg-slate-50 rounded-2xl border border-slate-200 p-4">
                        <p class="text-[8px] font-black tracking-[0.28em] uppercase text-slate-400 mb-2">Documents</p>
                        <p class="text-2xl font-black tracking-tight"><?= $totalDocs ?></p>
                    </div>
                    <div class="bg-slate-50 rounded-2xl border border-slate-200 p-4">
                        <p class="text-[8px] font-black tracking-[0.28em] uppercase text-slate-400 mb-2">Catégories</p>
                        <p class="text-2xl font-black tracking-tight"><?= $totalCategories ?></p>
                    </div>
                </div>
            </div>
        </header>

        <section class="doc-panel rounded-[2rem] p-6 md:p-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
                <div>
                    <p class="text-[9px] font-black tracking-[0.35em] uppercase text-slate-400 mb-2">Catalogue</p>
                    <h3 class="text-2xl font-black tracking-tight uppercase">Documents disponibles</h3>
                </div>

                <form method="get" action="<?= url('documents') ?>" class="flex flex-wrap items-center gap-3">
                    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Rechercher…" class="rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium outline-none focus:border-slate-400 w-48 md:w-56" />
                    <select name="category" class="rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium outline-none focus:border-slate-400">
                        <option value="">Toutes catégories</option>
                        <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $currentCategoryId === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="rounded-2xl bg-slate-900 text-white px-5 py-2.5 text-[11px] font-black tracking-[0.16em] uppercase hover:bg-slate-800 transition-colors">Filtrer</button>
                </form>
            </div>

            <?php if (empty($documents)): ?>
            <div class="py-16 text-center">
                <p class="text-sm font-bold uppercase tracking-[0.24em] text-slate-400">Aucun document publié</p>
                <p class="mt-3 text-slate-500">Aucun document ne correspond aux critères ou n’est publié pour le moment.</p>
                <p class="mt-6"><a href="<?= url('dashboard') ?>" class="text-emerald-600 hover:underline font-semibold">Retour au dashboard</a></p>
            </div>
            <?php else: ?>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-5">
                <?php foreach ($documents as $doc):
                    $catName = $doc['category_name'] ?? '';
                    $initial = $catName ? mb_substr($catName, 0, 1) : 'D';
                ?>
                <article class="doc-card bg-white rounded-3xl border border-slate-200 p-5 transition-all">
                    <div class="flex items-start justify-between gap-4 mb-4">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center flex-shrink-0">
                            <span class="text-[11px] font-black tracking-widest text-emerald-700"><?= htmlspecialchars($initial) ?></span>
                        </div>
                        <?php if ($catName): ?>
                        <span class="text-[8px] font-black tracking-[0.25em] uppercase text-slate-400"><?= htmlspecialchars($catName) ?></span>
                        <?php endif; ?>
                    </div>
                    <h4 class="text-lg font-black tracking-tight uppercase mb-2">
                        <a href="<?= url('documents/' . htmlspecialchars($doc['slug'])) ?>" class="hover:text-emerald-600 transition-colors"><?= htmlspecialchars($doc['title']) ?></a>
                    </h4>
                    <?php if (!empty($doc['description'])): ?>
                    <p class="text-[11px] text-slate-600 font-medium leading-relaxed mb-5 line-clamp-2"><?= htmlspecialchars($doc['description']) ?></p>
                    <?php else: ?>
                    <p class="text-[11px] text-slate-500 font-medium mb-5">Document consultable en ligne ou téléchargeable.</p>
                    <?php endif; ?>
                    <div class="flex items-center justify-between gap-3 flex-wrap">
                        <a href="<?= url('documents/' . htmlspecialchars($doc['slug'])) ?>" class="text-[10px] font-black uppercase tracking-[0.14em] text-emerald-600 hover:text-emerald-700">Ouvrir</a>
                        <a href="<?= url('documents/' . (int)$doc['id'] . '/download') ?>" class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500 hover:text-slate-900">Télécharger</a>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <p class="mt-8 text-sm text-slate-500"><a href="<?= url('dashboard') ?>" class="hover:text-emerald-600 underline">Retour au dashboard</a></p>
        </section>
    </div>
</div>
