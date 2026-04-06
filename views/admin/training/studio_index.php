<?php
$courses = $courses ?? [];
$visibilityFilter = $visibilityFilter ?? '';
$canPublish = $canPublish ?? false;

$visLabels = [
    'draft' => 'Brouillon',
    'private' => 'Privé',
    'published' => 'Publié',
    'archived' => 'Archivé',
];
$publishedCount = count(array_filter($courses, static fn (array $c) => ($c['visibility'] ?? '') === 'published'));
?>
<div>
    <?php
    $flashOk = \App\Core\Session::getFlash('success');
    $flashErr = \App\Core\Session::getFlash('error');
    ?>
    <?php if ($flashOk): ?>
    <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200/80 text-emerald-950 text-sm font-medium shadow-sm"><?= htmlspecialchars((string) $flashOk) ?></div>
    <?php endif; ?>
    <?php if ($flashErr): ?>
    <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200/80 text-rose-950 text-sm font-medium shadow-sm"><?= htmlspecialchars((string) $flashErr) ?></div>
    <?php endif; ?>

    <header class="training-studio-hero mb-8">
        <div class="flex flex-col xl:flex-row xl:items-end xl:justify-between gap-8">
            <div class="min-w-0">
                <p class="text-[0.65rem] font-black tracking-[0.35em] uppercase text-emerald-600 mb-3">Studio formation</p>
                <h1 class="text-2xl md:text-4xl font-black text-slate-900 tracking-tight uppercase leading-tight">Tableau des formations</h1>
                <p class="text-slate-600 text-sm mt-3 max-w-2xl leading-relaxed">Créez des parcours, ajoutez des modules et des leçons, puis publiez-les dans le catalogue apprenant — comme un espace créateur dédié.</p>
                <p class="text-sm text-slate-500 mt-2">
                    <a href="<?= url('admin/training') ?>" class="font-semibold text-slate-700 underline decoration-slate-300 hover:decoration-emerald-600 hover:text-emerald-800">← Tableau de bord formations</a>
                </p>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 shrink-0 w-full sm:w-auto max-w-md">
                <div class="training-studio-stat">
                    <p class="training-studio-stat__k">Total</p>
                    <p class="training-studio-stat__v"><?= (int) count($courses) ?></p>
                </div>
                <div class="training-studio-stat">
                    <p class="training-studio-stat__k">Publiées</p>
                    <p class="training-studio-stat__v text-emerald-600"><?= (int) $publishedCount ?></p>
                </div>
                <div class="training-studio-stat col-span-2 sm:col-span-1">
                    <p class="training-studio-stat__k">Filtre</p>
                    <p class="training-studio-stat__v !text-lg"><?= $visibilityFilter === '' ? 'Tous' : htmlspecialchars($visLabels[$visibilityFilter] ?? $visibilityFilter) ?></p>
                </div>
            </div>
        </div>
    </header>

    <div class="grid grid-cols-1 xl:grid-cols-[1fr_320px] gap-8 items-start">
        <div class="space-y-6 min-w-0">
            <section class="training-studio-panel overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-slate-50/80">
                    <div>
                        <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-500">Vos formations</h2>
                        <p class="text-sm text-slate-600 mt-0.5">Cliquez sur <strong>Éditer</strong> pour structurer les modules et leçons.</p>
                    </div>
                    <form method="get" action="<?= url('admin/training/studio') ?>" class="flex flex-wrap items-center gap-2">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Visibilité</label>
                        <select name="visibility" class="border border-slate-200 rounded-lg px-3 py-2 text-sm font-medium bg-white shadow-sm focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-400" onchange="this.form.submit()">
                            <option value="">Toutes</option>
                            <?php foreach ($visLabels as $k => $lab): ?>
                            <option value="<?= htmlspecialchars($k) ?>" <?= $visibilityFilter === $k ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>

                <?php if (empty($courses)): ?>
                <div class="p-10 text-center">
                    <p class="text-slate-600 font-medium">Aucune formation pour ce filtre.</p>
                    <p class="text-sm text-slate-500 mt-2">Utilisez le panneau à droite pour créer votre première formation.</p>
                </div>
                <?php else: ?>
                <div class="divide-y divide-slate-100">
                    <?php foreach ($courses as $c):
                        $t = (string) ($c['title'] ?? '');
                        $initial = $t !== '' ? mb_strtoupper(mb_substr($t, 0, 1)) : '?';
                        $isPub = ($c['visibility'] ?? '') === 'published';
                        ?>
                    <div class="training-studio-course-row">
                        <div class="flex items-center gap-4 min-w-0 flex-1">
                            <div class="training-studio-thumb" aria-hidden="true"><?= htmlspecialchars($initial) ?></div>
                            <div class="min-w-0">
                                <p class="font-bold text-slate-900 truncate"><?= htmlspecialchars($t) ?></p>
                                <p class="text-xs font-mono text-slate-500 truncate"><?= htmlspecialchars((string) ($c['slug'] ?? '')) ?></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 w-full sm:w-auto justify-between sm:justify-end">
                            <span class="px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide rounded-full <?= $isPub ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' ?>"><?= htmlspecialchars($visLabels[$c['visibility']] ?? (string) ($c['visibility'] ?? '')) ?></span>
                            <a href="<?= url('admin/training/studio/' . (int) $c['id']) ?>" class="inline-flex items-center justify-center px-4 py-2 bg-slate-900 text-white text-xs font-bold rounded-lg hover:bg-slate-800 shadow-sm transition-colors">Éditer</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>
        </div>

        <aside class="xl:sticky xl:top-24 space-y-4">
            <section class="training-studio-panel p-6 border-t-4 border-t-violet-500 shadow-lg shadow-slate-900/5">
                <h2 class="text-xs font-black uppercase tracking-[0.22em] text-violet-900/80 mb-1">Nouvelle formation</h2>
                <p class="text-sm text-slate-600 mb-5">Créée en brouillon par défaut ; vous pourrez compléter la fiche ensuite.</p>
                <form method="post" action="<?= url('admin/training/studio') ?>" class="space-y-4">
                    <?= \App\Core\Csrf::field() ?>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">Titre</label>
                        <input type="text" name="title" required class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm shadow-inner focus:ring-2 focus:ring-violet-400/40 focus:border-violet-400" placeholder="Ex. Introduction tactique">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">Identifiant d’URL <span class="font-normal text-slate-400">(optionnel)</span></label>
                        <input type="text" name="slug" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-mono shadow-inner focus:ring-2 focus:ring-violet-400/40" placeholder="généré depuis le titre si vide">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">Visibilité initiale</label>
                        <select name="visibility" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm shadow-inner focus:ring-2 focus:ring-violet-400/40">
                            <?php foreach ($visLabels as $k => $lab): ?>
                            <option value="<?= htmlspecialchars($k) ?>" <?= $k === 'draft' ? 'selected' : '' ?> <?= ($k === 'published' && !$canPublish) ? 'disabled' : '' ?>><?= htmlspecialchars($lab) ?><?= ($k === 'published' && !$canPublish) ? ' (permission requise)' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="w-full px-4 py-3.5 bg-gradient-to-br from-violet-600 to-violet-800 text-white text-sm font-black rounded-xl hover:from-violet-500 hover:to-violet-700 shadow-md shadow-violet-900/20 transition-all">Créer la formation</button>
                </form>
            </section>
        </aside>
    </div>
</div>
