<?php
declare(strict_types=1);
$course = $course ?? [];
$cid = (int) ($course['id'] ?? 0);
$modules = $course['modules'] ?? [];
$labels = function_exists('training_lesson_type_labels_fr') ? training_lesson_type_labels_fr() : [];
$levelLabels = function_exists('training_course_level_labels_fr') ? training_course_level_labels_fr() : [];
?>
<div class="training-studio-panel p-6 md:p-8 mb-8 border-2 border-amber-300/80 bg-amber-50/90 rounded-2xl shadow-sm">
    <p class="text-[0.65rem] font-black uppercase tracking-[0.28em] text-amber-900 mb-2">Aperçu auteur — mode caviardé</p>
    <h1 class="text-xl md:text-2xl font-black text-slate-900 tracking-tight mb-3">Structure visible, contenu masqué</h1>
    <p class="text-sm text-slate-700 leading-relaxed max-w-3xl">
        Cette page reproduit l’<strong>arborescence</strong> de la formation (modules, leçons, types, durées) pour valider le parcours avant publication.
        Les <strong>textes</strong>, <strong>médias</strong>, <strong>liens</strong> et <strong>contenus interactifs</strong> sont <strong>caviardés</strong> : aucune URL réelle ni contenu pédagogique n’est affiché, afin de limiter les fuites lors de démonstrations ou captures d’écran.
    </p>
    <p class="text-xs text-amber-900/80 mt-4">
        Pour voir le rendu réel côté apprenant (après publication), utilisez <strong>Aperçu public</strong> dans le menu ou le catalogue.
    </p>
</div>

<div class="training-studio-panel p-6 md:p-8 space-y-8">
    <header class="border-b border-slate-100 pb-6">
        <p class="text-[0.65rem] font-black uppercase tracking-[0.3em] text-slate-400 mb-2">Formation</p>
        <h2 class="text-2xl font-black text-slate-900"><?= htmlspecialchars((string) ($course['title'] ?? '')) ?></h2>
        <?php if (!empty($course['short_description'])): ?>
        <p class="text-slate-600 mt-2"><?= htmlspecialchars((string) $course['short_description']) ?></p>
        <?php endif; ?>
        <p class="text-sm text-slate-500 mt-2"><?= (int) ($course['estimated_minutes'] ?? 0) ?> min estimées · <?= htmlspecialchars((string) ($course['level'] ?? '')) ?></p>
    </header>

    <?php
    $mi = 0;
    foreach ($modules as $mod):
        $mi++;
        $lessons = $mod['lessons'] ?? [];
    ?>
    <section class="rounded-xl border border-slate-200 bg-white overflow-hidden">
        <div class="bg-slate-50 px-4 py-3 border-b border-slate-100">
            <p class="text-[0.6rem] font-black uppercase tracking-[0.25em] text-slate-500">Module <?= (int) $mi ?></p>
            <h3 class="text-lg font-black text-slate-900"><?= htmlspecialchars((string) ($mod['title'] ?? '')) ?></h3>
            <?php if (!empty($mod['subtitle'])): ?>
            <p class="text-sm font-semibold text-slate-700 mt-1"><?= htmlspecialchars((string) $mod['subtitle']) ?></p>
            <?php endif; ?>
            <?php if (!empty($mod['description'])): ?>
            <p class="text-sm text-slate-600 mt-1"><?= htmlspecialchars((string) $mod['description']) ?></p>
            <?php endif; ?>
            <?php
            $modPreviewObjs = function_exists('training_lms_learning_objectives')
                ? training_lms_learning_objectives(['learning_objectives' => $mod['learning_objectives'] ?? ''])
                : [];
            ?>
            <?php if ($modPreviewObjs !== []): ?>
            <ul class="mt-2 text-xs text-slate-600 list-disc list-inside space-y-0.5">
                <?php foreach (array_slice($modPreviewObjs, 0, 5) as $mo): ?>
                <li><?= htmlspecialchars($mo) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <?php if ((int) ($mod['estimated_minutes'] ?? 0) > 0): ?>
            <p class="text-[11px] text-slate-500 mt-2">Durée indicative module : <?= (int) $mod['estimated_minutes'] ?> min</p>
            <?php endif; ?>
        </div>
        <ul class="divide-y divide-slate-100">
            <?php
            $li = 0;
            foreach ($lessons as $les):
                $li++;
                $lt = (string) ($les['lesson_type'] ?? 'richtext');
                $typeLabel = $labels[$lt] ?? $lt;
                $diffK = trim((string) ($les['difficulty'] ?? ''));
                $diffLab = $diffK !== '' && isset($levelLabels[$diffK]) ? $levelLabels[$diffK] : '';
            ?>
            <li class="px-4 py-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-[0.55rem] font-black uppercase tracking-[0.2em] text-emerald-700">Leçon <?= (int) $li ?></p>
                        <p class="font-bold text-slate-900"><?= htmlspecialchars((string) ($les['title'] ?? '')) ?></p>
                        <?php if (!empty($les['summary'])): ?>
                        <p class="text-sm text-slate-600 mt-1"><?= htmlspecialchars((string) $les['summary']) ?></p>
                        <?php endif; ?>
                        <?php
                        $lesObjs = function_exists('training_lms_learning_objectives')
                            ? training_lms_learning_objectives(['learning_objectives' => $les['learning_objectives'] ?? ''])
                            : [];
                        ?>
                        <?php if ($lesObjs !== []): ?>
                        <ul class="mt-2 text-[11px] text-slate-600 list-disc list-inside">
                            <?php foreach (array_slice($lesObjs, 0, 3) as $lo): ?>
                            <li><?= htmlspecialchars($lo) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                        <p class="text-xs text-slate-500 mt-1">
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 font-semibold text-slate-700"><?= htmlspecialchars($typeLabel) ?></span>
                            · <?= (int) ($les['duration_minutes'] ?? 0) ?> min
                            <?php if ($diffLab !== ''): ?>
                            · <?= htmlspecialchars($diffLab) ?>
                            <?php endif; ?>
                            <?php if (!empty($les['is_required'])): ?>
                            · <span class="text-rose-600 font-semibold">Obligatoire</span>
                            <?php endif; ?>
                        </p>
                        <?php if (!empty($les['instructor_notes'])): ?>
                        <p class="text-[11px] text-amber-800/90 mt-2 font-mono bg-amber-50 border border-amber-200/80 rounded-lg px-2 py-1.5">Notes internes : <span class="select-none">[masquées en mode caviardé]</span></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="training-preview-redact-block mt-3 rounded-lg border border-dashed border-slate-300 bg-slate-100/80 px-3 py-4 relative overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-b from-slate-200/40 to-slate-300/50 pointer-events-none" aria-hidden="true"></div>
                    <p class="relative text-xs font-mono text-slate-500 select-none">
                        [Contenu pédagogique masqué — <?= htmlspecialchars($typeLabel) ?>]
                    </p>
                    <p class="relative text-[11px] text-slate-500 mt-2 select-none">
                        Lien externe affiché en mode caviardé : <strong><?= htmlspecialchars(training_preview_redact_url(isset($les['external_url']) ? (string) $les['external_url'] : null)) ?></strong>
                    </p>
                </div>
            </li>
            <?php endforeach; ?>
            <?php if ($lessons === []): ?>
            <li class="px-4 py-6 text-sm text-slate-500">Aucune leçon dans ce module.</li>
            <?php endif; ?>
        </ul>
    </section>
    <?php endforeach; ?>

    <?php if ($modules === []): ?>
    <p class="text-slate-500 text-sm">Aucun module — ajoutez-en depuis l’édition de la formation.</p>
    <?php endif; ?>
</div>

<div class="flex flex-wrap gap-3 mt-8">
    <a href="<?= training_studio_url($cid) ?>" class="inline-flex items-center px-5 py-2.5 bg-slate-900 text-white text-xs font-black uppercase tracking-wider rounded-xl hover:bg-slate-800">← Retour au Studio</a>
    <a href="<?= url('formations/' . rawurlencode((string) ($course['slug'] ?? ''))) ?>" target="_blank" rel="noopener" class="inline-flex items-center px-5 py-2.5 border border-slate-300 text-slate-800 text-xs font-black uppercase tracking-wider rounded-xl hover:bg-slate-50">Aperçu public (nouvel onglet)</a>
</div>
