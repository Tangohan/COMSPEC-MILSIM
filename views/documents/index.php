<?php
$documents = $documents ?? [];
$categories = $categories ?? [];
$currentCategoryId = $currentCategoryId ?? null;
$search = $search ?? '';
$documentType = $documentType ?? '';
$sort = $sort ?? 'title_asc';
$entity_type = $entity_type ?? null;
$entity_id = $entity_id ?? null;
/** @var array<int, list<array{label: string, href: string}>> $documentTrainingRefs */
$documentTrainingRefs = $documentTrainingRefs ?? [];
$totalDocs = count($documents);
$totalCategories = count($categories);
$documentTypes = [
    'manuel' => 'Manuel',
    'procedure' => 'Procédure',
    'note' => 'Note',
    'annexe' => 'Annexe',
    'support_formation' => 'Support formation',
    'fiche_equipement' => 'Fiche équipement',
    'document_operationnel' => 'Document opérationnel',
    'piece_jointe' => 'Pièce jointe',
];
$sortLabels = [
    'title_asc' => 'Titre (A → Z)',
    'title_desc' => 'Titre (Z → A)',
    'updated_desc' => 'Plus récents',
    'updated_asc' => 'Plus anciens',
];
$hasActiveFilters = ($search !== '' || $currentCategoryId !== null || $documentType !== '' || $sort !== 'title_asc');
$baseUrlList = url('documents');
?>
<div class="min-h-screen bg-slate-100 text-slate-900">
    <div class="mx-auto max-w-[1800px] px-4 py-8 sm:px-6 lg:px-8">

        <!-- En-tête -->
        <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-[0_20px_70px_-30px_rgba(15,23,42,0.14)]">
            <div class="border-b border-slate-100 bg-gradient-to-br from-slate-50 via-white to-emerald-50/40 px-6 py-8 md:px-10 md:py-10">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex rounded-full bg-slate-900 px-3 py-1 text-[10px] font-black uppercase tracking-[0.2em] text-white">Portail</span>
                    <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.2em] text-emerald-800">Lecture</span>
                </div>
                <h1 class="mt-5 text-3xl font-black tracking-tight text-slate-950 md:text-4xl">
                    Documents publiés
                </h1>
                <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-600">
                    Doctrine, SOP, manuels et ressources opérationnelles accessibles selon vos droits. Recherchez par mot-clé, filtrez par catégorie ou type, triez par date ou par titre.
                </p>
                <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-2xl border border-slate-200 bg-white/80 px-4 py-4 shadow-sm">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Résultats</p>
                        <p class="mt-2 text-3xl font-black tabular-nums text-slate-950"><?= (int) $totalDocs ?></p>
                        <p class="mt-1 text-xs text-slate-500">Après filtres d’accès</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white/80 px-4 py-4 shadow-sm">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Catégories</p>
                        <p class="mt-2 text-3xl font-black tabular-nums text-slate-950"><?= (int) $totalCategories ?></p>
                        <p class="mt-1 text-xs text-slate-500">Dans la communauté</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white/80 px-4 py-4 shadow-sm sm:col-span-2">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Raccourci</p>
                        <p class="mt-2 text-sm font-semibold text-slate-800">Besoin d’une référence précise ?</p>
                        <p class="mt-1 text-xs leading-relaxed text-slate-500">Combinez recherche plein texte + catégorie + type pour affiner le catalogue.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Filtres & tri -->
        <section class="mt-8 overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-[0_20px_70px_-30px_rgba(15,23,42,0.12)]">
            <div class="border-b border-slate-200 px-6 py-4 md:px-8">
                <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Exploration</p>
                        <h2 class="mt-1 text-xl font-black tracking-tight text-slate-950">Recherche &amp; tri</h2>
                    </div>
                    <p class="max-w-xl text-sm text-slate-500">
                        Les champs s’appliquent ensemble. La liste reflète uniquement les documents publiés auxquels vous avez accès.
                    </p>
                </div>
            </div>

            <form method="get" action="<?= htmlspecialchars($baseUrlList) ?>" class="px-6 py-6 md:px-8 md:py-8">
                <?php if ($entity_type !== null && $entity_type !== '' && $entity_id !== null): ?>
                <input type="hidden" name="entity_type" value="<?= htmlspecialchars((string) $entity_type) ?>">
                <input type="hidden" name="entity_id" value="<?= (int) $entity_id ?>">
                <?php endif; ?>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                    <div class="xl:col-span-2">
                        <label for="doc-q" class="mb-2 block text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Recherche</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </span>
                            <input
                                id="doc-q"
                                type="search"
                                name="q"
                                value="<?= htmlspecialchars((string) $search, ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="Titre, description, résumé…"
                                autocomplete="off"
                                class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 pl-11 pr-4 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:bg-white focus:ring-4 focus:ring-emerald-100"
                            >
                        </div>
                    </div>
                    <div>
                        <label for="doc-cat" class="mb-2 block text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Catégorie</label>
                        <select id="doc-cat" name="category" class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:bg-white focus:ring-4 focus:ring-emerald-100">
                            <option value="">Toutes</option>
                            <?php foreach ($categories as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= $currentCategoryId !== null && (int) $currentCategoryId === (int) $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) ($c['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="doc-type" class="mb-2 block text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Type</label>
                        <select id="doc-type" name="document_type" class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:bg-white focus:ring-4 focus:ring-emerald-100">
                            <option value="">Tous</option>
                            <?php foreach ($documentTypes as $k => $label): ?>
                            <option value="<?= htmlspecialchars($k) ?>" <?= $documentType === $k ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="doc-sort" class="mb-2 block text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Tri</label>
                        <select id="doc-sort" name="sort" class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:bg-white focus:ring-4 focus:ring-emerald-100">
                            <?php foreach ($sortLabels as $k => $label): ?>
                            <option value="<?= htmlspecialchars($k) ?>" <?= $sort === $k ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex flex-col justify-end gap-2 md:col-span-2 xl:col-span-1">
                        <span class="mb-2 hidden text-[11px] font-black uppercase tracking-[0.16em] text-transparent xl:block">Actions</span>
                        <div class="flex flex-wrap gap-2">
                            <button type="submit" class="inline-flex flex-1 min-w-[7rem] items-center justify-center rounded-2xl bg-slate-950 px-4 py-3 text-[11px] font-black uppercase tracking-[0.14em] text-white transition hover:bg-slate-800">
                                Appliquer
                            </button>
                            <a href="<?= htmlspecialchars($baseUrlList) ?><?= ($entity_type !== null && $entity_type !== '' && $entity_id !== null) ? '?' . http_build_query(['entity_type' => $entity_type, 'entity_id' => $entity_id]) : '' ?>" class="inline-flex flex-1 min-w-[7rem] items-center justify-center rounded-2xl border border-slate-300 bg-white px-4 py-3 text-[11px] font-black uppercase tracking-[0.14em] text-slate-700 transition hover:bg-slate-50">
                                Réinitialiser
                            </a>
                        </div>
                    </div>
                </div>

                <?php if ($hasActiveFilters): ?>
                <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-5">
                    <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Filtres actifs</span>
                    <?php if ($search !== ''): ?>
                    <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-900">
                        « <?= htmlspecialchars(mb_substr($search, 0, 40)) ?><?= mb_strlen($search) > 40 ? '…' : '' ?> »
                    </span>
                    <?php endif; ?>
                    <?php if ($currentCategoryId !== null): ?>
                        <?php foreach ($categories as $c): ?>
                            <?php if ((int) $c['id'] === (int) $currentCategoryId): ?>
                            <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700"><?= htmlspecialchars((string) ($c['name'] ?? '')) ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if ($documentType !== '' && isset($documentTypes[$documentType])): ?>
                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700"><?= htmlspecialchars($documentTypes[$documentType]) ?></span>
                    <?php endif; ?>
                    <?php if ($sort !== 'title_asc'): ?>
                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700"><?= htmlspecialchars($sortLabels[$sort] ?? $sort) ?></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </form>
        </section>

        <!-- Liste -->
        <section class="mt-8 overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-[0_20px_70px_-30px_rgba(15,23,42,0.1)]">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-6 py-4 md:flex-row md:items-center md:justify-between md:px-8">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Catalogue</p>
                    <h2 class="mt-1 text-xl font-black tracking-tight text-slate-950">Documents disponibles</h2>
                </div>
                <div class="flex flex-wrap gap-2">
                    <span class="inline-flex items-center rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-600">
                        <?= (int) $totalDocs ?> document<?= $totalDocs > 1 ? 's' : '' ?>
                    </span>
                </div>
            </div>

            <?php if (empty($documents)): ?>
            <div class="px-6 py-16 text-center md:px-8">
                <p class="text-base font-bold text-slate-800">Aucun document à afficher</p>
                <p class="mt-2 max-w-md mx-auto text-sm text-slate-500">Élargissez la recherche, changez de catégorie ou réinitialisez les filtres. Seuls les documents publiés et autorisés pour votre compte apparaissent ici.</p>
                <p class="mt-8">
                    <a href="<?= htmlspecialchars($baseUrlList) ?>" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-800 transition hover:bg-slate-50">Voir tout le catalogue</a>
                </p>
            </div>
            <?php else: ?>
            <div class="grid gap-5 p-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 md:p-8">
                <?php foreach ($documents as $doc):
                    $catName = (string) ($doc['category_name'] ?? '');
                    $initial = $catName !== '' ? mb_strtoupper(mb_substr($catName, 0, 1)) : 'D';
                    $snippet = trim((string) ($doc['short_description'] ?? ''));
                    if ($snippet === '' && !empty($doc['description'])) {
                        $snippet = trim(strip_tags((string) $doc['description']));
                        if (function_exists('mb_strlen') && mb_strlen($snippet) > 140) {
                            $snippet = mb_substr($snippet, 0, 137) . '…';
                        } elseif (strlen($snippet) > 140) {
                            $snippet = substr($snippet, 0, 137) . '…';
                        }
                    }
                    $slug = (string) ($doc['slug'] ?? '');
                    $docUrl = $slug !== '' ? url('documents/' . $slug) : '#';
                    $updated = !empty($doc['updated_at']) ? date('d/m/Y', strtotime($doc['updated_at'])) : (!empty($doc['created_at']) ? date('d/m/Y', strtotime($doc['created_at'])) : '');
                    ?>
                <article class="group flex flex-col rounded-3xl border border-slate-200 bg-gradient-to-b from-white to-slate-50/80 p-5 shadow-sm transition hover:border-emerald-300/60 hover:shadow-md">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-emerald-500/25 bg-emerald-500/10 text-sm font-black text-emerald-800">
                            <?= htmlspecialchars($initial) ?>
                        </div>
                        <?php if ($catName !== ''): ?>
                        <span class="max-w-[55%] text-right text-[10px] font-black uppercase tracking-[0.12em] text-slate-400"><?= htmlspecialchars($catName) ?></span>
                        <?php endif; ?>
                    </div>
                    <h3 class="mt-4 text-base font-black leading-snug tracking-tight text-slate-950">
                        <a href="<?= htmlspecialchars($docUrl) ?>" class="transition hover:text-emerald-700"><?= htmlspecialchars((string) ($doc['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></a>
                    </h3>
                    <?php if ($snippet !== ''): ?>
                    <p class="mt-2 flex-1 text-sm leading-relaxed text-slate-600 line-clamp-3"><?= htmlspecialchars($snippet, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php else: ?>
                    <p class="mt-2 flex-1 text-sm text-slate-500">Document consultable en ligne ou téléchargeable.</p>
                    <?php endif; ?>
                    <?php
                    $trainingRefs = $documentTrainingRefs[(int) ($doc['id'] ?? 0)] ?? [];
                    if ($trainingRefs !== []):
                    ?>
                    <div class="mt-3 rounded-xl border border-violet-200/90 bg-violet-50/70 px-3 py-2.5">
                        <p class="text-[10px] font-black uppercase tracking-[0.14em] text-violet-900/85">Référencé dans des formations</p>
                        <ul class="mt-1.5 space-y-1">
                            <?php foreach ($trainingRefs as $tr): ?>
                            <li>
                                <a href="<?= htmlspecialchars($tr['href'], ENT_QUOTES, 'UTF-8') ?>" class="text-xs font-semibold text-violet-950 underline decoration-violet-300 underline-offset-2 hover:text-violet-800"><?= htmlspecialchars($tr['label'], ENT_QUOTES, 'UTF-8') ?></a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    <?php if ($updated !== ''): ?>
                    <p class="mt-3 text-[11px] font-medium text-slate-400">Mise à jour <?= htmlspecialchars($updated) ?></p>
                    <?php endif; ?>
                    <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
                        <a href="<?= htmlspecialchars($docUrl) ?>" class="inline-flex flex-1 min-w-[6rem] items-center justify-center rounded-xl bg-slate-900 px-3 py-2 text-center text-[11px] font-black uppercase tracking-[0.12em] text-white transition hover:bg-slate-800">Ouvrir</a>
                        <a href="<?= url('documents/' . (int) ($doc['id'] ?? 0) . '/download') ?>" class="inline-flex flex-1 min-w-[6rem] items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-center text-[11px] font-black uppercase tracking-[0.12em] text-slate-700 transition hover:bg-slate-50">Télécharger</a>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>

        <p class="mt-8 text-center text-sm text-slate-500">
            <a href="<?= url('dashboard') ?>" class="font-semibold text-emerald-700 underline decoration-emerald-200 underline-offset-2 hover:text-emerald-800">Retour au dashboard</a>
        </p>
    </div>
</div>
