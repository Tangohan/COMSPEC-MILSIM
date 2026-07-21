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
$draftCount = count(array_filter($courses, static fn (array $c) => ($c['visibility'] ?? '') === 'draft'));
?>
<div class="ts-index">
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

    <header class="training-studio-hero mb-6">
        <div class="flex flex-col xl:flex-row xl:items-end xl:justify-between gap-6 xl:gap-10">
            <div class="min-w-0 flex-1">
                <p class="text-[0.65rem] font-black tracking-[0.35em] uppercase text-emerald-600 mb-3">Studio formation</p>
                <h1 class="text-2xl md:text-4xl font-black text-slate-900 tracking-tight leading-tight">Vos parcours</h1>
                <p class="text-slate-600 text-sm mt-3 max-w-3xl leading-relaxed">Créez, structurez et publiez les formations de la communauté. Ouvrez la fiche pour les données, ou Modules pour le contenu pédagogique.</p>
                <p class="text-sm text-slate-500 mt-3 flex flex-wrap items-center gap-x-2 gap-y-1">
                    <a href="<?= htmlspecialchars(training_lms_admin_url()) ?>" class="font-semibold text-slate-700 underline decoration-slate-300 hover:decoration-emerald-600 hover:text-emerald-800">← Pilotage des formations</a>
                    <span class="text-slate-300">·</span>
                    <a href="<?= htmlspecialchars($lmsChangelogUrl) ?>" class="text-slate-600 underline decoration-slate-200 hover:text-emerald-800">Journal du Studio</a>
                    <span class="text-slate-300">·</span>
                    <a href="<?= htmlspecialchars(url(training_studio_path() . '/echange/importer')) ?>" class="text-slate-600 underline decoration-slate-200 hover:text-emerald-800">Importer une formation</a>
                    <?php if ($lmsPlatformVersion !== ''): ?>
                    <span class="text-slate-400">(v<?= htmlspecialchars($lmsPlatformVersion) ?>)</span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="ts-index-hero-stats shrink-0">
                <div class="training-studio-stat">
                    <p class="training-studio-stat__k">Total</p>
                    <p class="training-studio-stat__v"><?= (int) count($courses) ?></p>
                </div>
                <div class="training-studio-stat">
                    <p class="training-studio-stat__k">Publiées</p>
                    <p class="training-studio-stat__v text-emerald-600"><?= (int) $publishedCount ?></p>
                </div>
                <div class="training-studio-stat">
                    <p class="training-studio-stat__k">Brouillons</p>
                    <p class="training-studio-stat__v"><?= (int) $draftCount ?></p>
                </div>
            </div>
        </div>
    </header>

    <section class="training-studio-panel ts-index-create p-5 md:p-6 mb-6">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-3 mb-4">
            <div>
                <h2 class="text-xs font-black uppercase tracking-[0.2em] text-emerald-800 mb-1">Nouvelle formation</h2>
                <p class="text-sm text-slate-600">Créée en brouillon par défaut. Ensuite : fiche → premier module → présentation → publication.</p>
            </div>
            <?php
            $tcap = $trainingCourseCapacity ?? null;
            $tcapBlocked = is_array($tcap) && empty($tcap['unlimited']) && empty($tcap['can_create']);
            ?>
            <?php if (is_array($tcap) && empty($tcap['unlimited']) && (int) ($tcap['limit'] ?? 0) > 0): ?>
            <p class="text-xs text-slate-600 shrink-0 lg:pt-1">Offre : <strong class="text-slate-900"><?= (int) ($tcap['used'] ?? 0) ?></strong> / <?= (int) ($tcap['limit'] ?? 0) ?> parcours</p>
            <?php endif; ?>
        </div>
        <?php if ($tcapBlocked): ?>
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
            Nombre maximal de parcours atteint pour votre formule. Passez à une offre supérieure ou retirez un parcours existant.
        </div>
        <?php endif; ?>
        <form method="post" action="<?= training_studio_url() ?>" class="ts-index-create__grid"<?= $tcapBlocked ? ' aria-disabled="true"' : '' ?>>
            <?= \App\Core\Csrf::field() ?>
            <div class="ts-field min-w-0">
                <label for="studio-new-course-title">Titre</label>
                <input id="studio-new-course-title" type="text" name="title" required placeholder="Ex. Introduction tactique"<?= $tcapBlocked ? ' disabled' : '' ?>>
            </div>
            <div class="ts-field min-w-0">
                <label for="studio-new-course-slug">Adresse courte <span class="font-normal normal-case tracking-normal text-slate-400">(optionnel)</span></label>
                <input id="studio-new-course-slug" type="text" name="slug" class="font-mono text-xs" placeholder="Générée si vide"<?= $tcapBlocked ? ' disabled' : '' ?>>
            </div>
            <?php if ($studioCanSetPlatformScope): ?>
            <div class="ts-field min-w-0">
                <label for="studio-new-course-scope">Portée</label>
                <select id="studio-new-course-scope" name="lms_scope"<?= $tcapBlocked ? ' disabled' : '' ?>>
                    <option value="tenant" selected>Communauté</option>
                    <option value="platform">Toute la plateforme</option>
                </select>
            </div>
            <?php endif; ?>
            <div class="ts-field min-w-0">
                <label for="studio-new-course-visibility">Visibilité</label>
                <select id="studio-new-course-visibility" name="visibility"<?= $tcapBlocked ? ' disabled' : '' ?>>
                    <?php foreach ($visLabels as $k => $lab): ?>
                    <option value="<?= htmlspecialchars($k) ?>" <?= $k === 'draft' ? 'selected' : '' ?> <?= ($k === 'published' && !$canPublish) ? 'disabled' : '' ?>><?= htmlspecialchars($lab) ?><?= ($k === 'published' && !$canPublish) ? ' (permission requise)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (!empty($pedagogyColumnsReady)): ?>
            <div class="ts-field min-w-0">
                <label for="studio-new-owner">Responsable pédagogique</label>
                <select id="studio-new-owner" name="pedagogical_owner_user_id"<?= $tcapBlocked ? ' disabled' : '' ?>>
                    <option value="">— Plus tard —</option>
                    <?php foreach ($studioStaffPickUsers ?? [] as $su):
                        $sid = (int) ($su['id'] ?? 0);
                        $slab = trim((string) ($su['display_name'] ?? '')) !== '' ? (string) $su['display_name'] : ('#' . $sid);
                    ?>
                    <option value="<?= $sid ?>"><?= htmlspecialchars($slab) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="min-w-0 flex items-end">
                <button type="submit" class="ts-index-create__submit w-full xl:w-auto"<?= $tcapBlocked ? ' disabled' : '' ?>>Créer</button>
            </div>
        </form>
        <?php if ($studioCanSetPlatformScope): ?>
        <p class="text-xs text-slate-500 mt-3">Les parcours « toute la plateforme » doivent avoir une adresse courte unique sur l’ensemble du site.</p>
        <?php endif; ?>
    </section>

    <section class="training-studio-panel overflow-hidden">
        <div class="px-5 py-4 md:px-6 border-b border-slate-100">
            <div class="ts-index-toolbar !mb-0">
                <div class="ts-index-toolbar__title">
                    <h2>Liste des formations</h2>
                    <p><strong>Fiche</strong> pour les données et l’inscription · <strong>Modules</strong> pour le contenu et les ressources par leçon.</p>
                </div>
                <div class="ts-index-toolbar__controls">
                    <?php if (!empty($courses)): ?>
                    <div class="ts-index-search">
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m17 17-3.5-3.5M15.5 9a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <input type="search" id="studio-index-search" placeholder="Rechercher un parcours…" aria-label="Rechercher un parcours" autocomplete="off">
                    </div>
                    <?php endif; ?>
                    <form method="get" action="<?= training_studio_url() ?>" class="ts-index-filter">
                        <label for="studio-vis-filter">Visibilité</label>
                        <select id="studio-vis-filter" name="visibility" onchange="this.form.submit()">
                            <option value="">Toutes</option>
                            <?php foreach ($visLabels as $k => $lab): ?>
                            <option value="<?= htmlspecialchars($k) ?>" <?= $visibilityFilter === $k ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>
        </div>

        <?php if (empty($courses)): ?>
        <div class="ts-index-empty">
            <div class="ts-index-empty__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none"><path d="M4 6.5A2.5 2.5 0 0 1 6.5 4H14l6 6v9.5A2.5 2.5 0 0 1 17.5 22h-11A2.5 2.5 0 0 1 4 19.5v-13Z" stroke="currentColor" stroke-width="1.5"/><path d="M14 4v5a1 1 0 0 0 1 1h5" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
            </div>
            <p class="text-slate-700 font-semibold text-base"><?= $visibilityFilter !== '' ? 'Aucune formation pour ce filtre.' : 'Aucune formation pour le moment.' ?></p>
            <p class="text-sm text-slate-500 mt-2">Utilisez le formulaire <strong class="text-slate-700">Nouvelle formation</strong> ci-dessus pour créer le premier parcours.</p>
        </div>
        <?php else: ?>
        <div class="ts-index-table-wrap">
            <table class="ts-index-table" id="studio-index-table">
                <thead>
                    <tr>
                        <th scope="col">Parcours</th>
                        <th scope="col">Statut</th>
                        <th scope="col" class="hidden lg:table-cell">Portée</th>
                        <th scope="col" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses as $c):
                        $t = (string) ($c['title'] ?? '');
                        $slugRow = (string) ($c['slug'] ?? '');
                        $initial = $t !== '' ? mb_strtoupper(mb_substr($t, 0, 1)) : '?';
                        $isPub = ($c['visibility'] ?? '') === 'published';
                        $visKey = (string) ($c['visibility'] ?? '');
                        $visShort = $visLabels[$visKey] ?? $visKey;
                        $lmsBehind = function_exists('lms_course_studio_created_before_current') && lms_course_studio_created_before_current($c);
                        $rowScope = (string) ($c['lms_scope'] ?? 'tenant');
                        $cidRow = (int) ($c['id'] ?? 0);
                        $thumbClass = 'training-studio-thumb' . ($isPub ? ' training-studio-thumb--published' : '');
                    ?>
                    <tr data-studio-search="<?= htmlspecialchars(mb_strtolower($t . ' ' . $slugRow), ENT_QUOTES, 'UTF-8') ?>">
                        <td data-label="Parcours">
                            <div class="ts-index-course">
                                <div class="<?= $thumbClass ?>" aria-hidden="true"><?= htmlspecialchars($initial) ?></div>
                                <div class="ts-index-course__text">
                                    <strong><?= htmlspecialchars($t) ?></strong>
                                    <span title="<?= htmlspecialchars($slugRow) ?>"><?= htmlspecialchars($slugRow) ?></span>
                                </div>
                            </div>
                        </td>
                        <td data-label="Statut">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide rounded-md <?= $isPub ? 'bg-emerald-100 text-emerald-900 border border-emerald-200/90' : 'bg-slate-200 text-slate-800 border border-slate-300/80' ?>"><?= htmlspecialchars($visShort) ?></span>
                                <?php if ($lmsBehind): ?>
                                <span class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide rounded-md bg-amber-100 text-amber-950 border border-amber-200/90" title="Créée avec une version antérieure du Studio — ouvrez et enregistrez pour aligner la trace.">Anc. version</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="hidden lg:table-cell" data-label="Portée">
                            <?php if ($rowScope === 'platform'): ?>
                            <span class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide rounded-md bg-sky-100 text-sky-900 border border-sky-200/90">Plateforme</span>
                            <?php else: ?>
                            <span class="text-xs text-slate-500 font-medium">Communauté</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Actions">
                            <div class="ts-index-actions">
                                <a href="<?= htmlspecialchars(training_studio_url((string) $cidRow . '/echange/export')) ?>" class="ts-index-btn ts-index-btn--ghost" title="Télécharger une sauvegarde réimportable">Exporter</a>
                                <a href="<?= htmlspecialchars(training_studio_url((string) $cidRow . '/structure#studio-ressources-aide')) ?>" class="ts-index-btn ts-index-btn--sky" title="Modules, leçons et ressources">Modules</a>
                                <a href="<?= training_studio_url((string) $cidRow) ?>" class="ts-index-btn ts-index-btn--primary">Fiche</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="ts-index-no-match hidden" id="studio-index-no-match">Aucun parcours ne correspond à cette recherche.</p>
        </div>
        <?php endif; ?>
    </section>
</div>
<?php if (!empty($courses)): ?>
<script>
(function () {
    var input = document.getElementById('studio-index-search');
    var rows = Array.prototype.slice.call(document.querySelectorAll('#studio-index-table [data-studio-search]'));
    var noMatch = document.getElementById('studio-index-no-match');
    if (!input || rows.length === 0) {
        return;
    }
    input.addEventListener('input', function () {
        var q = input.value.trim().toLowerCase();
        var visible = 0;
        rows.forEach(function (row) {
            var match = !q || (row.getAttribute('data-studio-search') || '').indexOf(q) !== -1;
            row.classList.toggle('hidden', !match);
            if (match) {
                visible++;
            }
        });
        if (noMatch) {
            noMatch.classList.toggle('hidden', visible > 0);
        }
    });
})();
</script>
<?php endif; ?>
