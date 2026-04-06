<?php
$courses = $courses ?? [];
$visibilityFilter = $visibilityFilter ?? '';
$canPublish = $canPublish ?? false;
$studioCanSetPlatformScope = $studioCanSetPlatformScope ?? false;
$lmsPlatformVersion = (string) ($lmsPlatformVersion ?? '');
$lmsChangelogUrl = (string) ($lmsChangelogUrl ?? training_studio_url('versions'));

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
                <p class="text-slate-500 text-sm mt-2 max-w-2xl leading-relaxed">Utilisez le bouton bleu <strong class="font-semibold text-slate-700">Structure &amp; ressources</strong> sur une formation, ou l’onglet <strong class="font-semibold text-slate-700">Modules, leçons &amp; ressources</strong> une fois dans l’édition : le panneau <strong class="font-semibold text-slate-700">Ressources</strong> (à droite de chaque leçon) permet d’ajouter liens web, fichiers et documents du centre documentaire.</p>
                <p class="text-sm text-slate-500 mt-2">
                    <a href="<?= htmlspecialchars(training_lms_admin_url()) ?>" class="font-semibold text-slate-700 underline decoration-slate-300 hover:decoration-emerald-600 hover:text-emerald-800">← Tableau de bord formations</a>
                    <span class="text-slate-300 mx-2">·</span>
                    <a href="<?= htmlspecialchars($lmsChangelogUrl) ?>" class="text-slate-600 underline decoration-slate-200 hover:text-emerald-800">Journal du Studio</a>
                    <span class="text-slate-300 mx-2">·</span>
                    <a href="<?= htmlspecialchars(url(training_studio_path() . '/echange/importer')) ?>" class="text-slate-600 underline decoration-slate-200 hover:text-emerald-800">Importer une formation</a>
                    <?php if ($lmsPlatformVersion !== ''): ?>
                    <span class="text-slate-400 ml-1">(v<?= htmlspecialchars($lmsPlatformVersion) ?>)</span>
                    <?php endif; ?>
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

    <div class="space-y-8">
            <section class="training-studio-panel overflow-hidden w-full">
                <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-slate-50/80">
                    <div>
                        <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-500">Vos formations</h2>
                        <p class="text-sm text-slate-600 mt-0.5"><strong>Fiche</strong> pour les métadonnées ; <strong class="text-sky-900">Structure &amp; ressources</strong> pour les modules, leçons et pièces jointes par leçon.</p>
                    </div>
                    <form method="get" action="<?= training_studio_url() ?>" class="flex flex-wrap items-center gap-2">
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
                    <p class="text-sm text-slate-500 mt-2">Utilisez le formulaire <strong class="font-semibold text-slate-700">Nouvelle formation</strong> sous cette liste.</p>
                </div>
                <?php else: ?>
                <div class="divide-y divide-slate-100">
                    <?php foreach ($courses as $c):
                        $t = (string) ($c['title'] ?? '');
                        $initial = $t !== '' ? mb_strtoupper(mb_substr($t, 0, 1)) : '?';
                        $isPub = ($c['visibility'] ?? '') === 'published';
                        ?>
                    <div class="training-studio-course-row">
                        <div class="flex items-center gap-4 min-w-0 flex-1 ts-course-row__main">
                            <div class="training-studio-thumb" aria-hidden="true"><?= htmlspecialchars($initial) ?></div>
                            <div class="min-w-0 flex-1">
                                <p class="font-bold text-slate-900 text-sm sm:text-base leading-snug sm:line-clamp-2 break-words"><?= htmlspecialchars($t) ?></p>
                                <p class="text-xs font-mono text-slate-500 truncate mt-0.5"><?= htmlspecialchars((string) ($c['slug'] ?? '')) ?></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 sm:gap-2.5 w-full lg:w-auto justify-start lg:justify-end flex-wrap ts-course-row__meta">
                            <?php
                            $lmsBehind = function_exists('lms_course_studio_created_before_current') && lms_course_studio_created_before_current($c);
                            ?>
                            <?php if ($lmsBehind): ?>
                            <span class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide rounded-md bg-amber-100 text-amber-950 border border-amber-200/90 shrink-0" title="Créée avec une version antérieure du Studio — ouvrez la formation et enregistrez pour aligner la trace sur la version actuelle.">Anc. version</span>
                            <?php endif; ?>
                            <?php
                            $rowScope = (string) ($c['lms_scope'] ?? 'tenant');
                            if ($rowScope === 'platform'):
                            ?>
                            <span class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide rounded-md bg-violet-100 text-violet-900 border border-violet-200/90 shrink-0" title="Proposé dans le catalogue de toutes les organisations concernées par ce type de parcours.">Plateforme</span>
                            <?php endif; ?>
                            <?php
                            $visKey = (string) ($c['visibility'] ?? '');
                            $visShort = match ($visKey) {
                                'published' => 'Publié',
                                'draft' => 'Brouillon',
                                'private' => 'Privé',
                                'archived' => 'Archivé',
                                default => $visLabels[$visKey] ?? $visKey,
                            };
                            ?>
                            <span class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide rounded-md <?= $isPub ? 'bg-emerald-100 text-emerald-900 border border-emerald-200/90' : 'bg-slate-200 text-slate-800 border border-slate-300/80' ?> shrink-0" title="<?= htmlspecialchars($visLabels[$visKey] ?? $visKey) ?>"><?= htmlspecialchars($visShort) ?></span>
                            <a href="<?= htmlspecialchars(training_studio_url((string) (int) $c['id'] . '/echange/export')) ?>"
                               class="inline-flex items-center justify-center px-3 py-2 border border-slate-200 bg-white text-slate-800 text-xs font-bold rounded-lg hover:bg-slate-50 shadow-sm transition-colors"
                               title="Télécharger une sauvegarde complète du parcours (fichier structuré réimportable dans le Studio)">Exporter</a>
                            <a href="<?= htmlspecialchars(training_studio_url((string) (int) $c['id'] . '/structure#studio-ressources-aide')) ?>" class="inline-flex items-center justify-center px-3 py-2 border border-sky-200 bg-sky-50 text-sky-950 text-xs font-bold rounded-lg hover:bg-sky-100 shadow-sm transition-colors" title="Modules, leçons et panneau Ressources par leçon">Structure &amp; ressources</a>
                            <a href="<?= training_studio_url((string) (int) $c['id']) ?>" class="inline-flex items-center justify-center px-4 py-2 bg-slate-900 text-white text-xs font-bold rounded-lg hover:bg-slate-800 shadow-sm transition-colors">Fiche</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>

            <section class="training-studio-panel p-6 md:p-8 border-t-4 border-t-violet-500 shadow-lg shadow-slate-900/5 w-full">
                <h2 class="text-xs font-black uppercase tracking-[0.22em] text-violet-900/80 mb-1">Nouvelle formation</h2>
                <p class="text-sm text-slate-600 mb-6 max-w-3xl">Créée en brouillon par défaut ; vous pourrez compléter la fiche ensuite.</p>
                <form method="post" action="<?= training_studio_url() ?>" class="flex flex-col gap-4 xl:flex-row xl:flex-wrap xl:items-end xl:gap-5">
                    <?= \App\Core\Csrf::field() ?>
                    <div class="w-full min-w-0 xl:flex-1 xl:min-w-[12rem]">
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">Titre</label>
                        <input type="text" name="title" required class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm shadow-inner focus:ring-2 focus:ring-violet-400/40 focus:border-violet-400" placeholder="Ex. Introduction tactique">
                    </div>
                    <div class="w-full min-w-0 xl:w-[11rem] shrink-0">
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">Adresse courte <span class="font-normal text-slate-400">(optionnel)</span></label>
                        <input type="text" name="slug" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-mono shadow-inner focus:ring-2 focus:ring-violet-400/40" placeholder="généré si vide">
                    </div>
                    <?php if ($studioCanSetPlatformScope): ?>
                    <div class="w-full min-w-0 xl:w-[12rem] shrink-0">
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">Portée du catalogue</label>
                        <select name="lms_scope" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm shadow-inner focus:ring-2 focus:ring-violet-400/40 bg-white">
                            <option value="tenant" selected>Communauté</option>
                            <option value="platform">Toute la plateforme</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="w-full min-w-0 xl:w-[11rem] shrink-0">
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">Visibilité initiale</label>
                        <select name="visibility" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm shadow-inner focus:ring-2 focus:ring-violet-400/40">
                            <?php foreach ($visLabels as $k => $lab): ?>
                            <option value="<?= htmlspecialchars($k) ?>" <?= $k === 'draft' ? 'selected' : '' ?> <?= ($k === 'published' && !$canPublish) ? 'disabled' : '' ?>><?= htmlspecialchars($lab) ?><?= ($k === 'published' && !$canPublish) ? ' (permission requise)' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="w-full xl:w-auto xl:shrink-0 pt-1 xl:pt-0">
                        <button type="submit" class="w-full xl:w-auto min-w-[10rem] px-5 py-3 bg-gradient-to-br from-violet-600 to-violet-800 text-white text-sm font-black rounded-xl hover:from-violet-500 hover:to-violet-700 shadow-md shadow-violet-900/20 transition-all">Créer la formation</button>
                    </div>
                </form>
                <?php if ($studioCanSetPlatformScope): ?>
                <p class="text-xs text-slate-500 mt-3 max-w-3xl">Les parcours « toute la plateforme » doivent avoir une adresse courte unique sur l’ensemble du site.</p>
                <?php endif; ?>
            </section>
    </div>
</div>
