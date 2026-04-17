<?php
$documents = $documents ?? [];
$users = $users ?? [];
$categories = $categories ?? [];
$documentStats = $documentStats ?? ['published_count' => 0, 'categories_count' => 0, 'latest_activity_at' => null, 'stale_count' => 0];
$filters = $filters ?? ['category' => null, 'status' => null, 'q' => '', 'document_type' => '', 'classification_level' => ''];
$statuses = ['draft' => 'Brouillon', 'review' => 'En relecture', 'approval' => 'À valider', 'published' => 'Publié', 'suspended' => 'Suspendu', 'archived' => 'Archivé', 'obsolete' => 'Obsolète'];
$documentTypes = ['manuel' => 'Manuel', 'procedure' => 'Procédure', 'note' => 'Note', 'annexe' => 'Annexe', 'support_formation' => 'Support formation', 'fiche_equipement' => 'Fiche équipement', 'document_operationnel' => 'Document opérationnel', 'piece_jointe' => 'Pièce jointe'];
$classificationLevels = ['public' => 'Public', 'interne' => 'Interne', 'restreint' => 'Restreint', 'sensible' => 'Sensible', 'confidentiel' => 'Confidentiel', 'operationnel' => 'Opérationnel'];
$statusBadgeClass = [
    'draft' => 'bg-slate-100 text-slate-700',
    'review' => 'bg-blue-100 text-blue-800',
    'approval' => 'bg-amber-100 text-amber-800',
    'published' => 'bg-emerald-100 text-emerald-800',
    'suspended' => 'bg-orange-100 text-orange-800',
    'archived' => 'bg-slate-200 text-slate-600',
    'obsolete' => 'bg-red-100 text-red-800',
];
$resultCount = count($documents);
$latestAt = $documentStats['latest_activity_at'] ?? null;
$latestLabel = $latestAt ? date('d.m.Y', strtotime($latestAt)) : '—';
?>
<style>
  .doc-row-actions a,
  .doc-row-actions button { min-height: 2.25rem; }
</style>
<div class="min-h-screen bg-slate-100">
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">

        <?php if (\App\Core\Session::get('success')): ?>
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <?= htmlspecialchars((string)\App\Core\Session::get('success')) ?>
        </div>
        <?php \App\Core\Session::forget('success'); endif; ?>
        <?php if (\App\Core\Session::get('error')): ?>
        <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <?= htmlspecialchars((string)\App\Core\Session::get('error')) ?>
        </div>
        <?php \App\Core\Session::forget('error'); endif; ?>

        <!-- Header -->
        <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-[0_20px_70px_-30px_rgba(15,23,42,0.18)]">
            <div class="grid gap-6 px-6 py-6 lg:grid-cols-[1.2fr_0.8fr] lg:px-8 lg:py-8">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="inline-flex items-center rounded-full bg-slate-900 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-white">
                            Documents
                        </span>
                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-emerald-700">
                            Gestion
                        </span>
                    </div>

                    <h1 class="mt-4 text-3xl font-black tracking-tight text-slate-950 md:text-4xl">
                        Centre documentaire
                    </h1>

                    <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600">
                        Supervision, filtrage, édition et pilotage du registre documentaire. Accédez aux contenus, suivez leur statut, contrôlez leur cycle de vie et centralisez les opérations de publication.
                    </p>

                    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Documents visibles</p>
                            <p class="mt-2 text-3xl font-black tracking-tight text-slate-950"><?= (int) $resultCount ?></p>
                            <p class="mt-1 text-xs text-slate-500">Résultat actuel</p>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Publié</p>
                            <p class="mt-2 text-3xl font-black tracking-tight text-slate-950"><?= (int) ($documentStats['published_count'] ?? 0) ?></p>
                            <p class="mt-1 text-xs text-slate-500">Documents actifs</p>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Catégories</p>
                            <p class="mt-2 text-3xl font-black tracking-tight text-slate-950"><?= (int) ($documentStats['categories_count'] ?? 0) ?></p>
                            <p class="mt-1 text-xs text-slate-500">Référencées</p>
                        </div>

                        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4">
                            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-amber-700">Obsolètes détectés</p>
                            <p class="mt-2 text-3xl font-black tracking-tight text-amber-900"><?= (int) ($documentStats['stale_count'] ?? 0) ?></p>
                            <p class="mt-1 text-xs text-amber-700/80">Revue/correction/suppression requise</p>
                        </div>
                    </div>
                </div>

                <aside class="flex flex-col justify-between rounded-[1.5rem] border border-slate-200 bg-gradient-to-br from-slate-50 via-white to-slate-100 p-5">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Actions rapides</p>

                        <div class="mt-5 grid gap-3">
                            <a href="<?= url('documents/gestion/ajout') ?>"
                               class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-4 py-3 text-[11px] font-black uppercase tracking-[0.18em] text-white transition hover:bg-slate-800">
                                Upload document
                            </a>

                            <a href="<?= url('documents/gestion/arborescence') ?>"
                               class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-4 py-3 text-[11px] font-black uppercase tracking-[0.18em] text-slate-700 transition hover:bg-slate-50">
                                Ouvrir l'arborescence
                            </a>
                        </div>
                    </div>

                    <div class="mt-6 rounded-2xl border border-slate-200 bg-white px-4 py-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Périmètre</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900">
                            Gestion complète du cycle documentaire
                        </p>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            Recherche, validation, publication, suspension, archivage et consultation publique.
                        </p>
                    </div>
                </aside>
            </div>
        </section>

        <!-- Filters -->
        <section class="mt-8 overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-[0_20px_70px_-30px_rgba(15,23,42,0.16)]">
            <div class="border-b border-slate-200 px-6 py-4 lg:px-8">
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Filtres</p>
                        <h2 class="mt-1 text-xl font-black tracking-tight text-slate-950">Recherche documentaire</h2>
                    </div>
                    <p class="text-sm text-slate-500">
                        Affinez le registre par contenu, catégorie, statut, type et niveau de classification.
                    </p>
                </div>
            </div>

            <form method="get" action="<?= url('documents/gestion') ?>" class="px-6 py-6 lg:px-8">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                    <div class="xl:col-span-2">
                        <label class="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Recherche</label>
                        <input
                            type="text"
                            name="q"
                            value="<?= htmlspecialchars((string)($filters['q'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="Titre, description"
                            class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:bg-white focus:ring-4 focus:ring-emerald-100"
                        >
                    </div>

                    <div>
                        <label class="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Catégorie</label>
                        <select name="category" class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:bg-white focus:ring-4 focus:ring-emerald-100">
                            <option value="">Toutes</option>
                            <?php foreach ($categories as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= (int)($filters['category'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)($c['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Statut</label>
                        <select name="status" class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:bg-white focus:ring-4 focus:ring-emerald-100">
                            <option value="">Tous</option>
                            <?php foreach ($statuses as $k => $v): ?>
                            <option value="<?= htmlspecialchars($k) ?>" <?= ($filters['status'] ?? '') === $k ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Type</label>
                        <select name="document_type" class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:bg-white focus:ring-4 focus:ring-emerald-100">
                            <option value="">Tous</option>
                            <?php foreach ($documentTypes as $k => $v): ?>
                            <option value="<?= htmlspecialchars($k) ?>" <?= ($filters['document_type'] ?? '') === $k ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Classification</label>
                        <select name="classification_level" class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:bg-white focus:ring-4 focus:ring-emerald-100">
                            <option value="">Toutes</option>
                            <?php foreach ($classificationLevels as $k => $v): ?>
                            <option value="<?= htmlspecialchars($k) ?>" <?= ($filters['classification_level'] ?? '') === $k ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-5 py-3 text-[11px] font-black uppercase tracking-[0.18em] text-white transition hover:bg-slate-800"
                    >
                        Filtrer
                    </button>

                    <a
                        href="<?= url('documents/gestion') ?>"
                        class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-5 py-3 text-[11px] font-black uppercase tracking-[0.18em] text-slate-700 transition hover:bg-slate-50"
                    >
                        Réinitialiser
                    </a>
                </div>
            </form>
        </section>

        <!-- Table -->
        <section class="mt-8 overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-[0_20px_70px_-30px_rgba(15,23,42,0.16)]">
            <div class="border-b border-slate-200 px-6 py-4 lg:px-8">
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Registre</p>
                        <h2 class="mt-1 text-xl font-black tracking-tight text-slate-950">Documents indexés</h2>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span class="inline-flex items-center rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-600">
                            <?= (int) $resultCount ?> résultat<?= $resultCount > 1 ? 's' : '' ?>
                        </span>
                        <span class="inline-flex items-center rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-600">
                            Dernière mise à jour : <?= htmlspecialchars($latestLabel) ?>
                        </span>
                    </div>
                </div>
            </div>

            <?php if (empty($documents)): ?>
            <div class="px-6 py-12 text-center lg:px-8">
                <p class="text-slate-600">Aucun document ne correspond aux critères.</p>
                <p class="mt-2 text-sm text-slate-500">
                    <a href="<?= url('documents/gestion/ajout') ?>" class="font-semibold text-slate-800 underline decoration-slate-300 underline-offset-2 hover:text-slate-950">Uploader un document</a>
                </p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[72rem] table-fixed border-collapse">
                    <colgroup>
                        <col class="w-[28%]" />
                        <col class="w-[14%]" />
                        <col class="w-[8%]" />
                        <col class="w-[11%]" />
                        <col class="w-[13%]" />
                        <col class="w-[10%]" />
                        <col class="w-[16%]" />
                    </colgroup>
                    <thead class="sticky top-0 z-[1] border-b border-slate-200/90 bg-slate-50/95 shadow-[0_1px_0_rgba(15,23,42,0.06)] backdrop-blur-sm">
                        <tr>
                            <th scope="col" class="px-5 py-3.5 text-left align-middle text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Document</th>
                            <th scope="col" class="px-5 py-3.5 text-left align-middle text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Catégorie</th>
                            <th scope="col" class="px-5 py-3.5 text-left align-middle text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Ver.</th>
                            <th scope="col" class="px-5 py-3.5 text-left align-middle text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Statut</th>
                            <th scope="col" class="px-5 py-3.5 text-left align-middle text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Auteur</th>
                            <th scope="col" class="px-5 py-3.5 text-left align-middle text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Maj</th>
                            <th scope="col" class="px-5 py-3.5 text-right align-middle text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Actions</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100 bg-white">
                        <?php foreach ($documents as $d):
                            $st = (string)($d['status'] ?? '');
                            $statusLabel = $statuses[$st] ?? $st;
                            $badgeClass = $statusBadgeClass[$st] ?? 'bg-slate-100 text-slate-700';
                            $rawDesc = trim((string)($d['short_description'] ?? ''));
                            if ($rawDesc === '' && !empty($d['description'])) {
                                $rawDesc = trim(strip_tags((string) $d['description']));
                                if (function_exists('mb_substr')) {
                                    if (mb_strlen($rawDesc) > 160) {
                                        $rawDesc = mb_substr($rawDesc, 0, 157) . '…';
                                    }
                                } elseif (strlen($rawDesc) > 160) {
                                    $rawDesc = substr($rawDesc, 0, 157) . '…';
                                }
                            }
                            $dateStr = !empty($d['updated_at']) ? date('d.m.Y', strtotime($d['updated_at'])) : (!empty($d['created_at']) ? date('d.m.Y', strtotime($d['created_at'])) : '—');
                            $dateIso = !empty($d['updated_at']) ? date('c', strtotime($d['updated_at'])) : (!empty($d['created_at']) ? date('c', strtotime($d['created_at'])) : '');
                            $isStale = !empty($d['lifecycle_stale']);
                            ?>
                        <tr class="group align-middle transition-colors duration-150 hover:bg-gradient-to-r hover:from-emerald-50/[0.45] hover:to-slate-50/90">
                            <td class="border-l-[3px] border-l-transparent px-5 py-4 align-top transition-[border-color] group-hover:border-l-emerald-500">
                                <div class="flex min-w-0 gap-3.5">
                                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500/15 via-white to-slate-100 shadow-[inset_0_1px_0_rgba(255,255,255,0.8)] ring-1 ring-slate-200/80" aria-hidden="true">
                                        <svg class="h-6 w-6 text-emerald-700/90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 8.25H4.875c-.621 0-1.125.504-1.125 1.125v9.75c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V12M10.5 8.25L19.5 3" /></svg>
                                    </div>
                                    <div class="min-w-0 flex-1 pt-0.5">
                                        <p class="line-clamp-2 text-[15px] font-semibold leading-snug tracking-tight text-slate-900"><?= htmlspecialchars((string)($d['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php if ($rawDesc !== ''): ?>
                                        <p class="mt-1 line-clamp-2 text-sm leading-relaxed text-slate-500">
                                            <?= htmlspecialchars($rawDesc, ENT_QUOTES, 'UTF-8') ?>
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                            <td class="px-5 py-4 align-middle">
                                <span class="inline-flex max-w-full truncate rounded-lg bg-slate-100/90 px-2.5 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-200/60" title="<?= htmlspecialchars((string)($d['category_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars((string)($d['category_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>

                            <td class="px-5 py-4 align-middle">
                                <span class="inline-flex rounded-lg border border-slate-200/90 bg-white px-2.5 py-1 font-mono text-xs font-bold tabular-nums text-slate-800 shadow-sm">
                                    <?= isset($d['version_number']) ? 'v' . (int) $d['version_number'] : '—' ?>
                                </span>
                            </td>

                            <td class="px-5 py-4 align-middle">
                                <div class="flex flex-col gap-1">
                                    <span class="inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-[11px] font-bold leading-none <?= $badgeClass ?>">
                                        <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <?php if ($isStale): ?>
                                    <span class="inline-flex whitespace-nowrap rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-bold text-amber-800">Obsolète — action obligatoire</span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td class="px-5 py-4 align-middle">
                                <span class="block truncate text-sm font-medium text-slate-800" title="<?= htmlspecialchars((string)($users[$d['owner_user_id'] ?? $d['created_by'] ?? 0] ?? '#' . ($d['owner_user_id'] ?? $d['created_by'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars((string)($users[$d['owner_user_id'] ?? $d['created_by'] ?? 0] ?? '#' . ($d['owner_user_id'] ?? $d['created_by'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>

                            <td class="px-5 py-4 align-middle">
                                <time class="text-sm tabular-nums text-slate-600"<?= $dateIso !== '' ? ' datetime="' . htmlspecialchars($dateIso, ENT_QUOTES, 'UTF-8') . '"' : '' ?>><?= htmlspecialchars($dateStr) ?></time>
                            </td>

                            <td class="px-5 py-4 align-middle">
                                <div class="doc-row-actions flex flex-col items-stretch justify-end gap-1.5 sm:flex-row sm:flex-wrap sm:justify-end">
                                    <a href="<?= url('documents/gestion/' . $d['id'] . '/modifier') ?>"
                                       class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-slate-900 px-3 py-2 text-center text-xs font-semibold text-white shadow-sm transition hover:bg-slate-800">
                                        <svg class="h-3.5 w-3.5 opacity-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" /></svg>
                                        Modifier
                                    </a>
                                    <a href="<?= url('documents/gestion/' . $d['id']) ?>"
                                       class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-center text-xs font-semibold text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50">
                                        Détail
                                    </a>
                                    <?php if ($d['status'] === 'published' && !empty($d['slug'])): ?>
                                    <a href="<?= url('documents/' . (string) $d['slug']) ?>"
                                       class="inline-flex items-center justify-center rounded-xl border border-emerald-200/80 bg-emerald-50/80 px-3 py-2 text-center text-xs font-semibold text-emerald-900 shadow-sm transition hover:bg-emerald-100">
                                        Public
                                    </a>
                                    <?php endif; ?>
                                    <?php if ($isStale): ?>
                                    <form action="<?= url('documents/gestion/' . $d['id'] . '/cycle') ?>" method="post" class="m-0 inline w-full sm:w-auto">
                                        <?= \App\Core\Csrf::field() ?>
                                        <input type="hidden" name="action" value="review">
                                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-800 transition hover:bg-blue-100 sm:w-auto">Forcer revue</button>
                                    </form>
                                    <form action="<?= url('documents/gestion/' . $d['id'] . '/cycle') ?>" method="post" class="m-0 inline w-full sm:w-auto">
                                        <?= \App\Core\Csrf::field() ?>
                                        <input type="hidden" name="action" value="correct">
                                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl border border-violet-200 bg-violet-50 px-3 py-2 text-xs font-semibold text-violet-800 transition hover:bg-violet-100 sm:w-auto">Correction</button>
                                    </form>
                                    <form action="<?= url('documents/gestion/' . $d['id'] . '/cycle') ?>" method="post" class="m-0 inline w-full sm:w-auto" onsubmit="return confirm('Archiver ce document obsolète ?');">
                                        <?= \App\Core\Csrf::field() ?>
                                        <input type="hidden" name="action" value="remove">
                                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-800 transition hover:bg-rose-100 sm:w-auto">Suppression logique</button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if ($d['status'] !== 'archived' && \App\Core\Gate::getInstance()->allows('documents.archive')): ?>
                                    <form action="<?= url('documents/gestion/' . $d['id'] . '/archiver') ?>"
                                          method="post"
                                          class="m-0 inline w-full max-w-full sm:w-auto"
                                          onsubmit="return confirm('Archiver ce document ?');">
                                        <?= \App\Core\Csrf::field() ?>
                                        <button type="submit"
                                                class="inline-flex w-full items-center justify-center rounded-xl border border-amber-200/90 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800 transition hover:bg-amber-100 sm:w-auto">
                                            Archiver
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>

        <!-- Footer -->
        <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-slate-500">
                Registre documentaire opérationnel et administratif.
            </p>
            <a href="<?= url('admin') ?>"
               class="text-sm font-semibold text-slate-700 transition hover:text-slate-950">
                Retour administration
            </a>
        </div>
    </div>
</div>
