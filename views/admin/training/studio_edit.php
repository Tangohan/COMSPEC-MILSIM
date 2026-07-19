<?php
$course = $course ?? [];
$tenant = $tenant ?? null;
$lessonTypes = $lessonTypes ?? [];
$visibilityOptions = $visibilityOptions ?? [];
$levelOptions = $levelOptions ?? [];
$canPublish = $canPublish ?? false;
$publishElevationRecipients = $publishElevationRecipients ?? [];
$publishElevationCooldownSec = $publishElevationCooldownSec ?? null;
$studioCanSetPlatformScope = $studioCanSetPlatformScope ?? false;
$curLmsScope = (string) ($course['lms_scope'] ?? 'tenant');
if ($curLmsScope !== 'platform' && $curLmsScope !== 'tenant') {
    $curLmsScope = 'tenant';
}
$cid = (int) ($course['id'] ?? 0);
$slug = (string) ($course['slug'] ?? '');
$modules = is_array($course['modules'] ?? null) ? $course['modules'] : [];
$trainingStudioCourseMeta = $trainingStudioCourse ?? [];
$studioMetaModuleCount = (int) ($trainingStudioCourseMeta['module_count'] ?? 0);
$studioMetaLessonCount = (int) ($trainingStudioCourseMeta['lesson_count'] ?? 0);
$studioLessonTotal = 0;
foreach ($modules as $sm) {
    $studioLessonTotal += count($sm['lessons'] ?? []);
}
$studioStructureEmptyButMetaShowsModules = $modules === [] && $studioMetaModuleCount > 0;
$libraryDocumentsForPicker = $libraryDocumentsForPicker ?? [];

$gateEdit = \App\Core\Gate::getInstance();
$studioEditCanVitrine = $gateEdit->allows('admin.access') || $gateEdit->allows('training.manage')
    || $gateEdit->allows('training.create') || $gateEdit->allows('training.update')
    || $gateEdit->allows('training.delete') || $gateEdit->allows('training.publish');

$visLabels = [
    'draft' => 'Brouillon',
    'private' => 'Privé',
    'published' => 'Publié',
    'archived' => 'Archivé',
];
$levelLabels = [
    'initiation' => 'Initiation',
    'intermediaire' => 'Intermédiaire',
    'avance' => 'Avancé',
    'expert' => 'Expert',
];
$lessonTypeLabels = function_exists('training_lesson_type_labels_fr') ? training_lesson_type_labels_fr() : [
    'richtext' => 'Texte enrichi',
    'video' => 'Vidéo',
    'video_integrated' => 'Vidéo intégrée',
    'video_embed' => 'Vidéo YouTube / Vimeo',
    'pdf' => 'PDF',
    'audio' => 'Audio',
    'scorm_like' => 'SCORM',
    'checklist' => 'Liste de contrôle',
    'external_link' => 'Lien externe',
    'canvas' => 'Parcours visuel',
    'quiz' => 'Quiz',
    'modals' => 'Modales',
    'slideshow' => 'Diaporama',
];
$lessonTypeOptgroups = function_exists('training_lesson_type_optgroups') ? training_lesson_type_optgroups() : [
    'Types' => array_keys($lessonTypeLabels),
];
$resourceTypeLabels = function_exists('training_lms_resource_type_labels_fr') ? training_lms_resource_type_labels_fr() : [];
if ($resourceTypeLabels === []) {
    $resourceTypeLabels = ['link' => 'Lien web'];
}
$fileResourceTypeLabels = array_intersect_key(
    $resourceTypeLabels,
    array_flip(['image', 'pdf', 'video', 'audio', 'zip', 'attachment'])
);
if ($fileResourceTypeLabels === []) {
    $fileResourceTypeLabels = ['attachment' => 'Fichier joint'];
}
$studioDocPickerCount = count($libraryDocumentsForPicker);
$studioCanDocsAdmin = $gateEdit->allows('documents.upload') || $gateEdit->allows('documents.view') || $gateEdit->allows('admin.access');
$studioOtherCourses = $studioOtherCourses ?? [];
$studioRoles = $studioRoles ?? [];
$studioGrades = $studioGrades ?? [];
$studioSessions = $studioSessions ?? [];
$studioQuestions = $studioQuestions ?? [];
$policy = [];
if (!empty($course['enrollment_policy_json'])) {
    $pj = json_decode((string) $course['enrollment_policy_json'], true);
    $policy = is_array($pj) ? $pj : [];
}
$policyPrereq = array_map('intval', $policy['prerequisite_course_ids'] ?? []);
$policyCerts = array_map('intval', $policy['require_certificate_from_course_ids'] ?? []);
$policyRoles = array_map('intval', $policy['required_role_ids'] ?? []);
$policyGrades = array_map('intval', $policy['required_grade_ids'] ?? []);
$policyStatusesSelected = [];
if (isset($policy['required_user_statuses']) && is_array($policy['required_user_statuses'])) {
    foreach ($policy['required_user_statuses'] as $ps) {
        $policyStatusesSelected[] = (string) $ps;
    }
}
$policyUserStatusLabels = function_exists('training_lms_enrollment_user_status_labels_fr') ? training_lms_enrollment_user_status_labels_fr() : [];
$studioStaffPickUsers = $studioStaffPickUsers ?? [];
$policyApproverIds = [];
if (isset($policy['enrollment_approver_user_ids']) && is_array($policy['enrollment_approver_user_ids'])) {
    foreach ($policy['enrollment_approver_user_ids'] as $aid) {
        $policyApproverIds[] = (int) $aid;
    }
}
$policyApproverIds = array_values(array_unique(array_filter($policyApproverIds, static fn (int $x): bool => $x > 0)));
$shareCodeDisplay = trim((string) ($course['enrollment_share_code'] ?? ''));
$studioReadinessChecks = [
    'slug' => trim((string) ($course['slug'] ?? '')) !== '',
    'objectifs' => trim((string) ($course['learning_objectives'] ?? '')) !== '',
    'visibilite' => in_array((string) ($course['visibility'] ?? ''), ['private', 'published'], true),
    'ressources' => $studioLessonTotal > 0,
    'quiz' => !empty($studioQuestions),
];
$studioReadinessDone = count(array_filter($studioReadinessChecks, static fn (bool $ok): bool => $ok));
$studioReadinessTotal = count($studioReadinessChecks);
$studioReadinessScore = $studioReadinessTotal > 0 ? (int) round(($studioReadinessDone * 100) / $studioReadinessTotal) : 0;

$courseObjectiveLines = function_exists('training_lms_objectives_list_from_storage')
    ? training_lms_objectives_list_from_storage((string) ($course['learning_objectives'] ?? ''))
    : [];
if ($courseObjectiveLines === []) {
    $courseObjectiveLines = [''];
}

$themeParsed = function_exists('training_lms_parse_theme') ? training_lms_parse_theme((string) ($course['theme_json'] ?? '')) : [];
$tjRaw = trim((string) ($course['theme_json'] ?? ''));
$themeEnable = $tjRaw !== '' && $tjRaw !== '{}';
$themeAccent = isset($themeParsed['accent']) && is_string($themeParsed['accent']) && preg_match('/^#[0-9A-Fa-f]{6}$/', $themeParsed['accent'])
    ? $themeParsed['accent']
    : '#10b981';
$themeFontKey = function_exists('training_lms_theme_font_key_from_css') ? training_lms_theme_font_key_from_css($themeParsed['font'] ?? null) : 'inter';
$themeRadiusKey = function_exists('training_lms_theme_radius_key_from_value') ? training_lms_theme_radius_key_from_value($themeParsed['radius'] ?? null) : 'generous';
$themeVariant = isset($themeParsed['variant']) && is_string($themeParsed['variant']) ? $themeParsed['variant'] : 'default';
$themeVariantLabels = function_exists('training_lms_theme_variant_labels_fr') ? training_lms_theme_variant_labels_fr() : ['default' => 'Standard'];
if (!array_key_exists($themeVariant, $themeVariantLabels)) {
    $themeVariant = 'default';
}
$themeOpeningLoaderImage = isset($themeParsed['openingLoaderImage']) && is_string($themeParsed['openingLoaderImage']) ? trim($themeParsed['openingLoaderImage']) : '';
$themeOpeningLoaderTitle = isset($themeParsed['openingLoaderTitle']) && is_string($themeParsed['openingLoaderTitle']) ? trim($themeParsed['openingLoaderTitle']) : '';
$themeOpeningLoaderBody = isset($themeParsed['openingLoaderBody']) && is_string($themeParsed['openingLoaderBody']) ? trim($themeParsed['openingLoaderBody']) : '';
$themeFontPresets = function_exists('training_lms_theme_font_presets') ? training_lms_theme_font_presets() : [];
$themeFontLabels = function_exists('training_lms_theme_font_labels_fr') ? training_lms_theme_font_labels_fr() : [];
$themeRadiusPresets = function_exists('training_lms_theme_radius_presets') ? training_lms_theme_radius_presets() : [];
$themeRadiusLabels = function_exists('training_lms_theme_radius_labels_fr') ? training_lms_theme_radius_labels_fr() : [];

$lmsPlatformVersion = (string) ($lmsPlatformVersion ?? '');
$lmsChangelogUrl = (string) ($lmsChangelogUrl ?? training_studio_url('versions'));
$lmsCourseCreatedBeforeCurrent = (bool) ($lmsCourseCreatedBeforeCurrent ?? false);
$lmsCourseLastSaveBehind = (bool) ($lmsCourseLastSaveBehind ?? false);
$lmsCreatedLabel = function_exists('lms_course_studio_created_version_label') ? lms_course_studio_created_version_label($course) : '';
$lmsLastSavedLabel = function_exists('lms_course_studio_last_saved_version_label') ? lms_course_studio_last_saved_version_label($course) : null;

$defaultCanvasJson = json_encode([
    'version' => 1,
    'modals' => [],
    'slides' => [[
        'id' => 'slide-start',
        'template' => 'title_hero',
        'title' => '',
        'subtitle' => '',
        'body' => '',
        'imageUrl' => '',
        'imageCaption' => '',
        'fileUrl' => '',
        'fileLabel' => '',
        'resources' => [],
        'primaryAction' => null,
        'secondaryAction' => null,
    ]],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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

    <?php if ($lmsPlatformVersion !== ''): ?>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
        <span>Studio — version <strong class="text-slate-700">v<?= htmlspecialchars($lmsPlatformVersion) ?></strong></span>
        <a href="<?= htmlspecialchars($lmsChangelogUrl) ?>" class="font-semibold text-emerald-700 underline decoration-emerald-200 hover:text-emerald-900">Journal des évolutions</a>
    </div>
    <?php endif; ?>

    <?php if ($lmsCourseCreatedBeforeCurrent): ?>
    <div class="mb-6 p-4 rounded-xl bg-amber-50 border border-amber-200/90 text-amber-950 text-sm leading-relaxed shadow-sm">
        <p class="font-bold text-amber-950 mb-1">Version du Studio à la création</p>
        <p>Cette formation a été créée avec une <strong>version antérieure</strong> de l’outil Studio (référence enregistrée : <?= htmlspecialchars($lmsCreatedLabel) ?>).
        Le rendu apprenant ou les options d’édition peuvent avoir évolué depuis. Consultez le <a href="<?= htmlspecialchars($lmsChangelogUrl) ?>" class="font-semibold underline decoration-amber-400 hover:text-amber-900">journal des évolutions</a>, puis enregistrez à nouveau la fiche ou le contenu pour actualiser la trace de version.</p>
    </div>
    <?php elseif ($lmsCourseLastSaveBehind && $lmsLastSavedLabel !== null): ?>
    <div class="mb-6 p-4 rounded-xl bg-sky-50 border border-sky-200/90 text-sky-950 text-sm leading-relaxed shadow-sm">
        <p class="font-bold text-sky-950 mb-1">Enregistrement sous une version précédente</p>
        <p>La dernière sauvegarde dans le Studio date de <strong><?= htmlspecialchars($lmsLastSavedLabel) ?></strong> alors que l’outil est en <strong>v<?= htmlspecialchars($lmsPlatformVersion) ?></strong>.
        Enregistrez à nouveau la fiche formation ou modifiez une leçon pour aligner la trace sur la version actuelle. <a href="<?= htmlspecialchars($lmsChangelogUrl) ?>" class="font-semibold underline decoration-sky-400 hover:text-sky-900">Voir ce qui a changé</a>.</p>
    </div>
    <?php endif; ?>

    <?php
    $trainingStudioSection = $trainingStudioSection ?? 'fiche';
    if (!in_array($trainingStudioSection, ['fiche', 'presentation', 'structure'], true)) {
        $trainingStudioSection = 'fiche';
    }
    $studioU = static fn (string $s): string => training_studio_url($cid . '/' . $s);
    $heroVis = (string) ($course['visibility'] ?? 'draft');
    $heroVisLabel = $visLabels[$heroVis] ?? $heroVis;
    $heroBadgeClass = match ($heroVis) {
        'published' => 'ts-hero-badge--published',
        'private' => 'ts-hero-badge--private',
        'archived' => 'ts-hero-badge--archived',
        default => 'ts-hero-badge--draft',
    };
    $heroModuleCount = max(count($modules), $studioMetaModuleCount);
    $heroLessonCount = max($studioLessonTotal, $studioMetaLessonCount);
    ?>
    <header class="training-studio-hero mb-8">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6">
            <div class="min-w-0">
                <p class="text-[0.65rem] font-black tracking-[0.35em] uppercase text-emerald-600 mb-2">Édition — formation</p>
                <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight"><?= htmlspecialchars((string) ($course['title'] ?? '')) ?></h1>
                <?php if ($tenant): ?>
                <p class="text-slate-600 text-sm mt-2">Communauté <strong><?= htmlspecialchars(community_display_name($tenant)) ?></strong></p>
                <?php endif; ?>
                <div class="flex flex-wrap items-center gap-2 mt-1">
                    <span class="ts-hero-badge <?= htmlspecialchars($heroBadgeClass) ?>"><?= htmlspecialchars($heroVisLabel) ?></span>
                    <span class="text-xs font-semibold text-slate-500"><?= (int) $heroModuleCount ?> module<?= $heroModuleCount === 1 ? '' : 's' ?> · <?= (int) $heroLessonCount ?> leçon<?= $heroLessonCount === 1 ? '' : 's' ?></span>
                </div>
                <p class="text-sm text-slate-500 mt-3">
                    <a href="<?= training_studio_url() ?>" class="font-semibold text-slate-700 underline decoration-slate-300 hover:text-emerald-800">← Toutes les formations</a>
                    <span class="text-slate-300 mx-2">·</span>
                    <a href="<?= htmlspecialchars(training_lms_admin_url()) ?>" class="text-slate-600 underline decoration-slate-200 hover:text-slate-900">Pilotage des formations</a>
                </p>
            </div>
            <div class="flex flex-wrap gap-2 shrink-0">
                <?php if ($studioEditCanVitrine): ?>
                <a href="<?= htmlspecialchars(training_lms_admin_url('courses/' . $cid . '/showcase')) ?>" class="px-3 py-2 border border-slate-200 bg-white text-slate-700 text-xs font-bold rounded-xl hover:bg-slate-50 shadow-sm">Vitrine</a>
                <?php endif; ?>
                <a href="<?= training_studio_url($cid . '/preview') ?>" class="px-3 py-2 border border-emerald-200 bg-emerald-600 text-white text-xs font-bold rounded-xl hover:bg-emerald-500 shadow-sm">Aperçu caviardé</a>
                <a href="<?= url('formations/' . rawurlencode($slug)) ?>" class="px-3 py-2 border border-slate-200 bg-white text-slate-700 text-xs font-bold rounded-xl hover:bg-slate-50 shadow-sm" target="_blank" rel="noopener">Aperçu public</a>
                <a href="<?= htmlspecialchars(training_lms_admin_url('enrollments') . '?course_id=' . (int) $cid) ?>" class="px-3 py-2 border border-slate-200 bg-white text-slate-800 text-xs font-bold rounded-xl hover:bg-slate-50 shadow-sm">Assignations</a>
            </div>
        </div>
    </header>

    <nav class="ts-section-tabs mb-8" aria-label="Sections du studio">
        <a href="<?= htmlspecialchars($studioU('fiche')) ?>" class="<?= $trainingStudioSection === 'fiche' ? 'is-active' : '' ?>"><span class="ts-section-tabs__n">1</span> Données</a>
        <a href="<?= htmlspecialchars($studioU('presentation')) ?>" class="<?= $trainingStudioSection === 'presentation' ? 'is-active' : '' ?>"><span class="ts-section-tabs__n">2</span> Présentation</a>
        <a href="<?= htmlspecialchars($studioU('structure')) ?>#studio-ressources-aide" class="<?= $trainingStudioSection === 'structure' ? 'is-active' : '' ?>"><span class="ts-section-tabs__n">3</span> Modules</a>
    </nav>

    <?php if ($trainingStudioSection === 'fiche'): ?>
    <div class="ts-fiche-layout mb-10">
        <div class="ts-fiche-main min-w-0">
            <?php require __DIR__ . '/partials/studio_fiche_form.php'; ?>
        </div>
        <aside class="ts-fiche-aside" aria-label="Préparation à la publication">
            <div class="ts-fiche-aside-card">
                <h2>Prêt à publier</h2>
                <div class="ts-readiness-meter">
                    <strong><?= (int) $studioReadinessScore ?>%</strong>
                    <span><?= (int) $studioReadinessDone ?> / <?= (int) $studioReadinessTotal ?></span>
                </div>
                <div class="ts-readiness-bar" aria-hidden="true"><i style="width: <?= (int) $studioReadinessScore ?>%"></i></div>
                <ul class="ts-readiness-list">
                    <li class="<?= !empty($studioReadinessChecks['slug']) ? 'is-ok' : 'is-todo' ?>">Adresse courte renseignée</li>
                    <li class="<?= !empty($studioReadinessChecks['objectifs']) ? 'is-ok' : 'is-todo' ?>">Objectifs pédagogiques saisis</li>
                    <li class="<?= !empty($studioReadinessChecks['visibilite']) ? 'is-ok' : 'is-todo' ?>">Visibilité prête à publier</li>
                    <li class="<?= !empty($studioReadinessChecks['ressources']) ? 'is-ok' : 'is-todo' ?>">Modules et leçons présents</li>
                    <li class="<?= !empty($studioReadinessChecks['quiz']) ? 'is-ok' : 'is-todo' ?>">Questionnaire prévu</li>
                </ul>
            </div>
            <div class="ts-fiche-aside-card">
                <h2>Guide de mise en ligne</h2>
                <ol class="ts-guide-list">
                    <li><b>1</b><span>Complétez la fiche (titre, adresse courte, visibilité).</span></li>
                    <li><b>2</b><span>Ajoutez un premier module et une première leçon.</span></li>
                    <li><b>3</b><span>Soignez la présentation apprenant.</span></li>
                    <li><b>4</b><span>Vérifiez la publication, puis l’aperçu.</span></li>
                </ol>
            </div>
        </aside>
    </div>

    <section id="studio-sessions-qa" class="training-studio-panel scroll-mt-28 p-6 md:p-8 space-y-8 shadow-sm mb-10">
        <h2 class="text-sm font-black uppercase tracking-[0.2em] text-slate-500">Créneaux &amp; questions apprenants</h2>

        <div>
            <h3 class="text-xs font-bold text-slate-700 mb-3">Créneaux (sessions, briefing audio par créneau possible)</h3>
            <?php if ($studioSessions === []): ?>
            <p class="text-sm text-slate-500">Aucun créneau défini.</p>
            <?php else: ?>
            <ul class="space-y-2 text-sm">
                <?php foreach ($studioSessions as $s):
                    $sid = (int) ($s['id'] ?? 0);
                ?>
                <li class="flex flex-wrap items-center justify-between gap-2 border border-slate-100 rounded-lg px-3 py-2 bg-slate-50">
                    <span><?= htmlspecialchars((string) ($s['label'] ?? 'Session')) ?> — <?= htmlspecialchars((string) ($s['starts_at'] ?? '')) ?> → <?= htmlspecialchars((string) ($s['ends_at'] ?? '')) ?></span>
                    <form method="post" action="<?= training_studio_url($cid) ?>" class="inline" onsubmit="return confirm('Supprimer ce créneau ?');">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="_action" value="delete_session">
                        <input type="hidden" name="session_id" value="<?= $sid ?>">
                        <button type="submit" class="text-xs text-rose-600 underline">Supprimer</button>
                    </form>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <form method="post" action="<?= training_studio_url($cid) ?>" class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4 border-t border-slate-100 pt-4">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="_action" value="add_session">
                <div><label class="block text-[11px] font-bold text-slate-600 mb-0.5">Début</label><input type="datetime-local" name="session_starts_at" required class="w-full border border-slate-200 rounded px-2 py-1.5 text-sm"></div>
                <div><label class="block text-[11px] font-bold text-slate-600 mb-0.5">Fin</label><input type="datetime-local" name="session_ends_at" required class="w-full border border-slate-200 rounded px-2 py-1.5 text-sm"></div>
                <div><label class="block text-[11px] font-bold text-slate-600 mb-0.5">Libellé</label><input type="text" name="session_label" class="w-full border border-slate-200 rounded px-2 py-1.5 text-sm"></div>
                <div><label class="block text-[11px] font-bold text-slate-600 mb-0.5">Lieu</label><input type="text" name="session_location" class="w-full border border-slate-200 rounded px-2 py-1.5 text-sm"></div>
                <div><label class="block text-[11px] font-bold text-slate-600 mb-0.5">Places max</label><input type="number" name="session_max_seats" min="0" class="w-full border border-slate-200 rounded px-2 py-1.5 text-sm"></div>
                <div><label class="block text-[11px] font-bold text-slate-600 mb-0.5">Instructeur (numéro de membre, optionnel)</label><input type="number" name="session_instructor_user_id" min="0" class="w-full border border-slate-200 rounded px-2 py-1.5 text-sm" placeholder="Optionnel"></div>
                <div class="sm:col-span-2"><label class="block text-[11px] font-bold text-slate-600 mb-0.5">Audio de briefing (lien)</label><input type="url" name="session_audio_url" class="w-full border border-slate-200 rounded px-2 py-1.5 text-sm"></div>
                <div class="sm:col-span-2"><label class="block text-[11px] font-bold text-slate-600 mb-0.5">Notes</label><textarea name="session_notes" rows="2" class="w-full border border-slate-200 rounded px-2 py-1.5 text-sm"></textarea></div>
                <div class="sm:col-span-2"><button type="submit" class="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-lg hover:bg-emerald-700">Ajouter un créneau</button></div>
            </form>
        </div>

        <div>
            <h3 class="text-xs font-bold text-slate-700 mb-3">Questions des apprenants</h3>
            <?php if ($studioQuestions === []): ?>
            <p class="text-sm text-slate-500">Aucune question.</p>
            <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($studioQuestions as $sq):
                    $qid = (int) ($sq['id'] ?? 0);
                ?>
                <div class="border border-slate-100 rounded-lg p-3 bg-white">
                    <p class="text-sm text-slate-800"><?= nl2br(htmlspecialchars((string) ($sq['question_text'] ?? ''))) ?></p>
                    <p class="text-[11px] text-slate-500 mt-1"><?= htmlspecialchars((string) ($sq['author_name'] ?? '')) ?> — <?= htmlspecialchars((string) ($sq['created_at'] ?? '')) ?></p>
                    <?php if (!empty($sq['answer_text'])): ?>
                    <p class="text-sm text-emerald-800 mt-2 border-l-2 border-emerald-400 pl-2"><?= nl2br(htmlspecialchars((string) $sq['answer_text'])) ?></p>
                    <?php else: ?>
                    <form method="post" action="<?= training_studio_url($cid) ?>" class="mt-2 space-y-2">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="_action" value="answer_question">
                        <input type="hidden" name="question_id" value="<?= $qid ?>">
                        <textarea name="question_answer" rows="2" class="w-full border border-slate-200 rounded px-2 py-1.5 text-sm" placeholder="Réponse du staff…" required></textarea>
                        <button type="submit" class="px-3 py-1.5 bg-slate-800 text-white text-xs font-bold rounded-lg">Publier la réponse</button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <?php elseif ($trainingStudioSection === 'presentation'): ?>
    <section class="mb-8 rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4 md:p-5">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-xs font-black uppercase tracking-[0.18em] text-emerald-900">Checklist prêt-à-publier</h2>
            <span class="text-sm font-black text-emerald-900"><?= $studioReadinessScore ?>%</span>
        </div>
        <div class="mt-2 h-2 rounded-full bg-emerald-100">
            <div class="h-2 rounded-full bg-emerald-500" style="width: <?= $studioReadinessScore ?>%"></div>
        </div>
        <ul class="mt-3 grid gap-2 text-sm md:grid-cols-2">
            <li class="rounded-lg border px-3 py-2 <?= $studioReadinessChecks['slug'] ? 'border-emerald-200 bg-white text-emerald-900' : 'border-amber-200 bg-amber-50 text-amber-900' ?>">Adresse courte renseignée</li>
            <li class="rounded-lg border px-3 py-2 <?= $studioReadinessChecks['objectifs'] ? 'border-emerald-200 bg-white text-emerald-900' : 'border-amber-200 bg-amber-50 text-amber-900' ?>">Objectifs pédagogiques saisis</li>
            <li class="rounded-lg border px-3 py-2 <?= $studioReadinessChecks['visibilite'] ? 'border-emerald-200 bg-white text-emerald-900' : 'border-amber-200 bg-amber-50 text-amber-900' ?>">Visibilité prête à publier</li>
            <li class="rounded-lg border px-3 py-2 <?= $studioReadinessChecks['ressources'] ? 'border-emerald-200 bg-white text-emerald-900' : 'border-amber-200 bg-amber-50 text-amber-900' ?>">Modules et leçons présents</li>
            <li class="rounded-lg border px-3 py-2 <?= $studioReadinessChecks['quiz'] ? 'border-emerald-200 bg-white text-emerald-900' : 'border-amber-200 bg-amber-50 text-amber-900' ?>">Questionnaire prévu</li>
        </ul>
    </section>

    <form method="post" action="<?= training_studio_url($cid) ?>" enctype="multipart/form-data" class="space-y-8 mb-12" id="studio-presentation-form"
          data-pres-course-title="<?= htmlspecialchars((string) ($course['title'] ?? 'Formation')) ?>"
          data-pres-media-base="<?= htmlspecialchars(rtrim(url(''), '/')) ?>">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="_action" value="save_course">
        <input type="hidden" name="_studio_section" value="presentation">

        <section id="studio-presentation" class="training-studio-panel scroll-mt-28 p-6 md:p-8 space-y-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-sm font-black uppercase tracking-[0.2em] text-slate-500">Présentation côté apprenant</h2>
                    <p class="text-xs text-slate-500 max-w-3xl mt-2">Apparence du parcours, visuels de couverture et consignes audio. Les textes, visibilité et règles d’inscription se gèrent dans l’onglet <a href="<?= htmlspecialchars($studioU('fiche')) ?>" class="font-semibold text-emerald-800 underline decoration-emerald-200 hover:text-emerald-950">Données &amp; inscription</a>.</p>
                </div>
                <button type="button" id="studio-pres-preview-open" class="inline-flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-xs font-black uppercase tracking-wide text-emerald-900 hover:bg-emerald-100">
                    Aperçu apprenant
                </button>
            </div>

            <?php
            $studioPresentationKits = is_array($studioPresentationKits ?? null) ? $studioPresentationKits : [];
            $studioSiteImages = is_array($studioSiteImages ?? null) ? $studioSiteImages : [];
            $thumbPath = trim((string) ($course['thumbnail_path'] ?? ''));
            $bannerPath = trim((string) ($course['banner_path'] ?? ''));
            $audioPath = trim((string) ($course['instruction_audio_url'] ?? ''));
            $thumbPreview = $thumbPath !== '' ? training_media_url($thumbPath) : null;
            $bannerPreview = $bannerPath !== '' ? training_media_url($bannerPath) : null;
            $loaderPreview = $themeOpeningLoaderImage !== '' ? training_media_url($themeOpeningLoaderImage) : null;
            $audioPreview = $audioPath !== '' ? training_media_url($audioPath) : null;
            ?>

            <div id="studio-presentation-kits" class="rounded-xl border border-violet-200 bg-violet-50/50 p-4 space-y-3 scroll-mt-28">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-xs font-black uppercase tracking-wide text-violet-900">Kits de présentation</h3>
                        <p class="text-[11px] text-violet-900/80 mt-1 max-w-2xl">Enregistrez le jeu de réglages actuellement <strong>sauvegardé</strong> sur cette formation pour le réappliquer plus tard sur d’autres parcours de votre communauté.</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 items-end">
                    <div class="min-w-[12rem] flex-1">
                        <label class="block text-[11px] font-bold text-violet-900 mb-1" for="kit_name">Nom du kit</label>
                        <input form="studio-kit-save-form" type="text" name="kit_name" id="kit_name" maxlength="80" class="w-full border border-violet-200 rounded-lg px-3 py-2 text-sm bg-white" placeholder="Ex. Ambiance opérationnelle">
                    </div>
                    <button form="studio-kit-save-form" type="submit" class="rounded-xl bg-violet-800 px-4 py-2.5 text-xs font-bold text-white hover:bg-violet-700">Enregistrer comme kit</button>
                </div>
                <?php if ($studioPresentationKits !== []): ?>
                <ul class="space-y-2 pt-1">
                    <?php foreach ($studioPresentationKits as $kitRow):
                        $kitId = (string) ($kitRow['id'] ?? '');
                        $kitName = (string) ($kitRow['name'] ?? 'Sans nom');
                        if ($kitId === '') {
                            continue;
                        }
                        ?>
                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-violet-200/80 bg-white px-3 py-2">
                        <span class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($kitName) ?></span>
                        <span class="flex flex-wrap gap-2">
                            <form method="post" action="<?= training_studio_url($cid) ?>" class="inline">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="_action" value="apply_presentation_kit">
                                <input type="hidden" name="kit_id" value="<?= htmlspecialchars($kitId) ?>">
                                <button type="submit" class="text-xs font-bold text-emerald-800 hover:underline">Appliquer</button>
                            </form>
                            <form method="post" action="<?= training_studio_url($cid) ?>" class="inline" onsubmit="return confirm('Supprimer ce kit de présentation ?');">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="_action" value="delete_presentation_kit">
                                <input type="hidden" name="kit_id" value="<?= htmlspecialchars($kitId) ?>">
                                <button type="submit" class="text-xs font-bold text-rose-700 hover:underline">Supprimer</button>
                            </form>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <p class="text-[11px] text-violet-900/70">Aucun kit enregistré pour le moment.</p>
                <?php endif; ?>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4 space-y-3">
                <label class="inline-flex items-center gap-2 text-sm font-bold text-slate-800">
                    <input type="checkbox" name="lms_theme_enable" value="1" <?= $themeEnable ? 'checked' : '' ?> data-pres-theme-enable>
                    Personnaliser l’apparence du parcours pour cette formation
                </label>
                <p class="text-[11px] text-slate-600">Couleurs, typographie et forme des blocs visibles par les apprenants sur cette formation.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-1">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Couleur d’accent</label>
                        <input type="color" name="lms_theme_accent" value="<?= htmlspecialchars($themeAccent) ?>" class="h-10 w-full max-w-[8rem] cursor-pointer rounded-lg border border-slate-200 bg-white p-1" data-pres-accent>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Typographie</label>
                        <select name="lms_theme_font" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" data-pres-font>
                            <?php foreach ($themeFontPresets as $fk => $_css): ?>
                            <option value="<?= htmlspecialchars($fk) ?>" <?= $themeFontKey === $fk ? 'selected' : '' ?> data-font-css="<?= htmlspecialchars((string) $_css) ?>"><?= htmlspecialchars($themeFontLabels[$fk] ?? $fk) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Arrondi des blocs</label>
                        <select name="lms_theme_radius" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" data-pres-radius>
                            <?php foreach ($themeRadiusPresets as $rk => $_rv): ?>
                            <option value="<?= htmlspecialchars($rk) ?>" <?= $themeRadiusKey === $rk ? 'selected' : '' ?> data-radius-css="<?= htmlspecialchars((string) $_rv) ?>"><?= htmlspecialchars($themeRadiusLabels[$rk] ?? $rk) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Ambiance</label>
                        <select name="lms_theme_variant" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" data-pres-variant>
                            <?php foreach ($themeVariantLabels as $vk => $vlab): ?>
                            <option value="<?= htmlspecialchars($vk) ?>" <?= $themeVariant === $vk ? 'selected' : '' ?>><?= htmlspecialchars($vlab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="space-y-3 border-t border-slate-200/70 pt-3">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-600">Loader d’ouverture (slide)</p>
                        <p class="text-[11px] text-slate-500">Pendant « Préparation du parcours… », vous pouvez afficher une image et un texte de contexte.</p>
                    </div>
                    <?php
                    $mediaKey = 'loader';
                    $mediaKind = 'image';
                    $mediaLabel = 'Image du loader';
                    $mediaHelp = 'Image affichée pendant la préparation du parcours.';
                    $mediaPathValue = $themeOpeningLoaderImage;
                    $mediaPathName = 'lms_opening_loader_image';
                    $mediaUploadName = 'lms_opening_loader_image_upload';
                    $mediaRemoveName = 'lms_opening_loader_image_remove';
                    $mediaAccept = 'image/jpeg,image/png,image/webp,image/gif';
                    $mediaRatio = 'aspect-[16/10]';
                    $mediaPreviewUrl = $loaderPreview;
                    require base_path('views/admin/training/partials/studio_presentation_media_field.php');
                    ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">Titre du slide loader</label>
                            <input type="text" name="lms_opening_loader_title" maxlength="120" value="<?= htmlspecialchars($themeOpeningLoaderTitle) ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="Mise en place du module" data-pres-loader-title>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">Texte du slide loader</label>
                            <textarea name="lms_opening_loader_body" rows="2" maxlength="320" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="Objectifs, consignes, ambiance..." data-pres-loader-body><?= htmlspecialchars($themeOpeningLoaderBody) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <?php
                $mediaKey = 'thumbnail';
                $mediaKind = 'image';
                $mediaLabel = 'Miniature';
                $mediaHelp = 'Affichée sur la carte de la formation dans le catalogue.';
                $mediaPathValue = $thumbPath;
                $mediaPathName = 'thumbnail_path';
                $mediaUploadName = 'thumbnail_upload';
                $mediaRemoveName = 'thumbnail_remove';
                $mediaAccept = 'image/jpeg,image/png,image/webp,image/gif';
                $mediaRatio = 'aspect-[4/3]';
                $mediaPreviewUrl = $thumbPreview;
                require base_path('views/admin/training/partials/studio_presentation_media_field.php');

                $mediaKey = 'banner';
                $mediaKind = 'image';
                $mediaLabel = 'Bannière';
                $mediaHelp = 'Grande image d’ouverture du parcours côté apprenant.';
                $mediaPathValue = $bannerPath;
                $mediaPathName = 'banner_path';
                $mediaUploadName = 'banner_upload';
                $mediaRemoveName = 'banner_remove';
                $mediaAccept = 'image/jpeg,image/png,image/webp,image/gif';
                $mediaRatio = 'aspect-[16/6]';
                $mediaPreviewUrl = $bannerPreview;
                require base_path('views/admin/training/partials/studio_presentation_media_field.php');
                ?>
            </div>

            <div class="border-t border-slate-100 pt-6 space-y-4">
                <h3 class="text-xs font-black uppercase text-slate-500">Consignes audio (optionnel)</h3>
                <?php
                $mediaKey = 'audio';
                $mediaKind = 'audio';
                $mediaLabel = 'Fichier de consignes';
                $mediaHelp = 'Joignez un enregistrement depuis votre poste. Les adresses externes déjà enregistrées restent prises en charge.';
                $mediaPathValue = $audioPath;
                $mediaPathName = 'instruction_audio_url';
                $mediaUploadName = 'instruction_audio_upload';
                $mediaRemoveName = 'instruction_audio_remove';
                $mediaAccept = 'audio/mpeg,audio/mp3,audio/ogg,audio/wav,audio/mp4,.mp3,.ogg,.wav,.m4a';
                $mediaRatio = '';
                $mediaPreviewUrl = $audioPreview;
                require base_path('views/admin/training/partials/studio_presentation_media_field.php');
                ?>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="instruction_audio_instructor_optional" value="1" <?= (($course['instruction_audio_instructor_optional'] ?? 1) == 1) ? 'checked' : '' ?>>
                    Écoute possible sans instructeur présent
                </label>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Notes</label>
                    <input type="text" name="instruction_audio_notes" maxlength="500" value="<?= htmlspecialchars((string) ($course['instruction_audio_notes'] ?? '')) ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="Contexte, consignes de sécurité…">
                </div>
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <button type="submit" class="px-6 py-3 bg-slate-900 text-white text-sm font-black rounded-xl hover:bg-slate-800 shadow-md">Enregistrer la présentation</button>
                <a href="<?= htmlspecialchars($studioU('structure')) ?>" class="inline-flex items-center px-4 py-3 border border-slate-200 bg-white text-slate-800 text-sm font-bold rounded-xl hover:bg-slate-50">Aller aux modules &amp; leçons</a>
                <a href="<?= training_studio_url($cid . '/preview') ?>" class="inline-flex items-center px-4 py-3 border border-emerald-200 bg-emerald-50 text-emerald-900 text-sm font-bold rounded-xl hover:bg-emerald-100">Aperçu caviardé du parcours</a>
            </div>
        </section>
    </form>

    <form id="studio-kit-save-form" method="post" action="<?= training_studio_url($cid) ?>" class="hidden">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="_action" value="save_presentation_kit">
    </form>

    <dialog id="studio-pres-library" class="ts-pres-dialog w-full max-w-4xl rounded-2xl border border-slate-200 p-0 shadow-2xl backdrop:bg-slate-950/50">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div>
                <h3 class="text-sm font-black uppercase tracking-wide text-slate-900">Images du site</h3>
                <p class="text-[11px] text-slate-500 mt-0.5">Choisissez une image publique déjà présente sur le portail.</p>
            </div>
            <button type="button" class="text-xs font-bold text-slate-600 hover:text-slate-900" data-pres-library-close>Fermer</button>
        </div>
        <div class="px-5 py-3 border-b border-slate-100">
            <label class="sr-only" for="studio-pres-library-search">Filtrer les images</label>
            <input type="search" id="studio-pres-library-search" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm" placeholder="Rechercher par nom…" autocomplete="off">
        </div>
        <div class="max-h-[min(60vh,28rem)] overflow-y-auto p-4">
            <?php if ($studioSiteImages === []): ?>
            <p class="text-sm text-slate-600 px-1">Aucune image publique n’a été trouvée sur le site.</p>
            <?php else: ?>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3" id="studio-pres-library-grid">
                <?php foreach ($studioSiteImages as $siteImg):
                    $sip = (string) ($siteImg['path'] ?? '');
                    $sil = (string) ($siteImg['label'] ?? $sip);
                    $siu = (string) ($siteImg['url'] ?? '');
                    if ($sip === '' || $siu === '') {
                        continue;
                    }
                    ?>
                <button type="button"
                        class="ts-pres-lib-item group text-left rounded-xl border border-slate-200 bg-white overflow-hidden hover:border-emerald-400 hover:shadow-sm transition"
                        data-pres-lib-path="<?= htmlspecialchars($sip) ?>"
                        data-pres-lib-url="<?= htmlspecialchars($siu) ?>"
                        data-pres-lib-label="<?= htmlspecialchars(mb_strtolower($sil)) ?>">
                    <span class="block aspect-[4/3] bg-slate-100 overflow-hidden">
                        <img src="<?= htmlspecialchars($siu) ?>" alt="" loading="lazy" decoding="async" class="w-full h-full object-cover group-hover:scale-[1.03] transition duration-300">
                    </span>
                    <span class="block px-2 py-1.5 text-[10px] font-semibold text-slate-700 leading-snug line-clamp-2"><?= htmlspecialchars($sil) ?></span>
                </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </dialog>

    <dialog id="studio-pres-preview" class="ts-pres-dialog w-full max-w-3xl rounded-2xl border border-slate-200 p-0 shadow-2xl backdrop:bg-slate-950/55">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div>
                <h3 class="text-sm font-black uppercase tracking-wide text-slate-900">Aperçu côté apprenant</h3>
                <p class="text-[11px] text-slate-500 mt-0.5">Simulation locale à partir des réglages du formulaire (sans enregistrement).</p>
            </div>
            <button type="button" class="text-xs font-bold text-slate-600 hover:text-slate-900" data-pres-preview-close>Fermer</button>
        </div>
        <div class="p-5 space-y-4" id="studio-pres-preview-body">
            <div class="ts-pres-preview-loader rounded-2xl border border-slate-800 bg-slate-950 text-white p-6 text-center space-y-3">
                <div class="mx-auto max-w-xs overflow-hidden rounded-xl border border-white/10 bg-slate-900 aspect-[16/10] flex items-center justify-center">
                    <img src="" alt="" class="hidden w-full h-full object-cover" data-prev-loader-img>
                    <span class="text-[11px] text-slate-500 px-3" data-prev-loader-empty>Sans image de loader</span>
                </div>
                <p class="text-[10px] font-black uppercase tracking-[0.25em] text-slate-400">Préparation du parcours…</p>
                <p class="text-base font-bold" data-prev-loader-title>Mise en place</p>
                <p class="text-sm text-slate-300 leading-relaxed max-w-md mx-auto" data-prev-loader-body></p>
            </div>
            <div class="rounded-2xl border border-slate-200 overflow-hidden bg-white" data-prev-theme-card style="--lms-accent:#10b981;font-family:Inter,system-ui,sans-serif;border-radius:1.25rem">
                <div class="aspect-[16/6] bg-slate-200 relative overflow-hidden">
                    <img src="" alt="" class="hidden absolute inset-0 w-full h-full object-cover" data-prev-banner-img>
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950/70 to-transparent"></div>
                    <div class="absolute bottom-3 left-4 right-4 text-white">
                        <p class="text-[10px] font-black uppercase tracking-[0.2em] opacity-80">Préambule</p>
                        <p class="text-lg font-black leading-tight" data-prev-course-title>Formation</p>
                    </div>
                </div>
                <div class="p-4 flex gap-4 items-start">
                    <div class="w-20 aspect-[4/3] rounded-lg overflow-hidden border border-slate-200 bg-slate-100 shrink-0 flex items-center justify-center">
                        <img src="" alt="" class="hidden w-full h-full object-cover" data-prev-thumb-img>
                        <span class="text-[9px] text-slate-400 px-1 text-center" data-prev-thumb-empty>Miniature</span>
                    </div>
                    <div class="min-w-0 space-y-2">
                        <p class="text-sm text-slate-600 leading-relaxed">Exemple de bloc avec la couleur d’accent et la typographie choisies.</p>
                        <span class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-bold text-white" style="background:var(--lms-accent)" data-prev-accent-chip>Accent</span>
                    </div>
                </div>
            </div>
        </div>
    </dialog>

    <?php else: ?>
    <section class="mb-8 rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4 md:p-5">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-xs font-black uppercase tracking-[0.18em] text-emerald-900">Checklist prêt-à-publier</h2>
            <span class="text-sm font-black text-emerald-900"><?= $studioReadinessScore ?>%</span>
        </div>
        <div class="mt-2 h-2 rounded-full bg-emerald-100">
            <div class="h-2 rounded-full bg-emerald-500" style="width: <?= $studioReadinessScore ?>%"></div>
        </div>
        <ul class="mt-3 grid gap-2 text-sm md:grid-cols-2">
            <li class="rounded-lg border px-3 py-2 <?= $studioReadinessChecks['slug'] ? 'border-emerald-200 bg-white text-emerald-900' : 'border-amber-200 bg-amber-50 text-amber-900' ?>">Adresse courte renseignée</li>
            <li class="rounded-lg border px-3 py-2 <?= $studioReadinessChecks['objectifs'] ? 'border-emerald-200 bg-white text-emerald-900' : 'border-amber-200 bg-amber-50 text-amber-900' ?>">Objectifs pédagogiques saisis</li>
            <li class="rounded-lg border px-3 py-2 <?= $studioReadinessChecks['visibilite'] ? 'border-emerald-200 bg-white text-emerald-900' : 'border-amber-200 bg-amber-50 text-amber-900' ?>">Visibilité prête à publier</li>
            <li class="rounded-lg border px-3 py-2 <?= $studioReadinessChecks['ressources'] ? 'border-emerald-200 bg-white text-emerald-900' : 'border-amber-200 bg-amber-50 text-amber-900' ?>">Modules et leçons présents</li>
            <li class="rounded-lg border px-3 py-2 <?= $studioReadinessChecks['quiz'] ? 'border-emerald-200 bg-white text-emerald-900' : 'border-amber-200 bg-amber-50 text-amber-900' ?>">Questionnaire prévu</li>
        </ul>
    </section>


    <details class="rounded-2xl border border-slate-200 bg-white p-5 mb-6 text-sm text-slate-800 shadow-sm open:ring-1 open:ring-slate-100">
        <summary class="cursor-pointer font-black text-slate-900 uppercase tracking-wide text-xs select-none">Comprendre les types de leçon</summary>
        <div class="mt-4 space-y-3 text-slate-700 leading-relaxed border-t border-slate-100 pt-4">
            <p><strong>Texte &amp; pages</strong> — contenu riche HTML ou listes à cocher. Idéal pour consignes et référentiels.</p>
            <p><strong>Vidéo &amp; audio</strong> — URL du fichier (MP4/WebM) ou lecteur intégré YouTube/Vimeo selon le type choisi ; renseignez aussi <em>URL externe</em> si demandé.</p>
            <p><strong>PDF / lien externe / SCORM</strong> — pointent vers une ressource hébergée ; le champ URL externe sert souvent de cible principale.</p>
            <p><strong>Parcours visuel (Canvas)</strong> — éditeur graphique (slides, modales) ; après création d’une leçon Canvas, enregistrez puis ouvrez l’éditeur dans la carte leçon.</p>
            <p><strong>Quiz, Modales, Diaporama</strong> — éditeurs visuels sous chaque leçon (questions / fenêtres / diapositives) ; rien à coder à la main.</p>
        </div>
    </details>
    <div class="rounded-2xl border border-emerald-200/90 bg-emerald-50/80 p-5 mb-10 text-sm text-emerald-950 shadow-sm">
        <strong>Raccourci :</strong> validez la structure sans diffuser le contenu avec <a href="<?= training_studio_url($cid . '/preview') ?>" class="font-black text-emerald-900 underline decoration-emerald-400 hover:text-emerald-950">Aperçu caviardé</a>.
        Les types <strong>Quiz / Modales / Diaporama</strong> se configurent avec les formulaires sous chaque leçon.
    </div>

    <h2 id="studio-structure" class="text-xl font-black text-slate-900 mb-2 scroll-mt-28 flex items-center gap-3">
        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-500/15 text-emerald-800 text-[11px] font-black tracking-tight" title="Modules">M</span>
        Structure pédagogique
    </h2>
    <div id="studio-ressources-aide" class="ts-structure-aide scroll-mt-28 mb-8">
        <p class="ts-structure-aide__kicker">Où se trouve le panneau Ressources ?</p>
        <p class="ts-structure-aide__body">Pour chaque <strong>leçon</strong>, un panneau <strong>Ressources</strong> occupe la colonne de droite (ou s’affiche juste sous le formulaire sur mobile). Vous pouvez y ajouter une <strong>image</strong> (JPG, PNG, WebP), un lien web, un autre fichier, ou un document du centre documentaire — visibles en bas de leçon pour les apprenants inscrits.</p>
        <?php if ($studioStructureEmptyButMetaShowsModules): ?>
        <p class="ts-structure-aide__warn">Le menu indique encore des modules, mais la liste n’apparaît pas ici. <strong>Actualisez la page</strong> (rechargement complet). Si rien ne s’affiche après actualisation, contactez un <strong>administrateur du site</strong>.</p>
        <?php elseif (count($modules) > 0 && $studioLessonTotal === 0): ?>
        <p class="ts-structure-aide__hint">Ajoutez au moins une <strong>leçon</strong> dans un module ci-dessous : le panneau <strong>Ressources</strong> apparaît alors sur cette leçon.</p>
        <?php endif; ?>
    </div>
    <p class="text-sm text-slate-600 mb-3 max-w-2xl">Réordonnez les <strong>modules</strong> depuis la <strong>frise</strong> ci-dessous ou depuis chaque carte (poignée ⠿). Dans chaque module, réordonnez les <strong>leçons</strong> de la même façon. L’ordre est enregistré tout de suite.</p>
    <p class="text-xs text-slate-500 mb-6 max-w-2xl">Les diapositives d’un <strong>parcours visuel</strong> se déplacent dans l’éditeur de la leçon concernée, pas sur cette frise.</p>

    <?php if (count($modules) > 0): ?>
    <nav class="studio-parcours-timeline mb-8" id="studio-parcours-timeline" aria-label="Frise du parcours — ordre des modules">
        <div class="studio-parcours-timeline__head">
            <span class="studio-parcours-timeline__label">Frise du parcours</span>
            <span class="studio-parcours-timeline__hint">Poignée ⠿ pour réordonner · clic sur le titre pour accéder au module</span>
        </div>
        <div id="studio-timeline-track" class="studio-timeline-track">
            <?php
            $tIdx = 0;
            $timelineElapsedMinutes = 0;
            foreach ($modules as $tMod):
                $tIdx++;
                $tMid = (int) ($tMod['id'] ?? 0);
                $tLessons = $tMod['lessons'] ?? [];
                $tLc = count($tLessons);
                $tEstimated = max(0, (int) ($tMod['estimated_minutes'] ?? 0));
                $timelineStart = $timelineElapsedMinutes;
                $timelineElapsedMinutes += $tEstimated;
                $tTitle = (string) ($tMod['title'] ?? '');
                $tTitleShort = function_exists('mb_strimwidth') ? mb_strimwidth($tTitle, 0, 40, '…', 'UTF-8') : (strlen($tTitle) > 40 ? substr($tTitle, 0, 37) . '…' : $tTitle);
                ?>
            <div class="studio-timeline-node" data-module-id="<?= $tMid ?>" data-timeline-node>
                <span class="studio-timeline-node__grip" title="Déplacer ce module dans le parcours" aria-hidden="true">⠿</span>
                <a href="#studio-mod-<?= $tMid ?>" class="studio-timeline-node__body">
                    <span class="studio-timeline-node__n"><?= (int) $tIdx ?></span>
                    <span class="studio-timeline-node__title"><?= htmlspecialchars($tTitleShort !== '' ? $tTitleShort : 'Module') ?></span>
                    <span class="studio-timeline-node__meta"><?= $tLc === 0 ? 'Sans leçon' : ((int) $tLc . ' leçon' . ($tLc > 1 ? 's' : '')) ?></span>
                    <span class="studio-timeline-node__timing">
                        <span class="studio-timeline-node__badge">T+<?= (int) $timelineStart ?> min</span>
                        <?php if ($tEstimated > 0): ?>
                        <span class="studio-timeline-node__duration">≈ <?= (int) $tEstimated ?> min</span>
                        <?php endif; ?>
                    </span>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </nav>
    <?php endif; ?>

    <div id="studio-modules-list" class="space-y-4" data-studio-url="<?= htmlspecialchars(training_studio_url($cid)) ?>" data-csrf="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
    <?php
    $mi = 0;
    foreach ($modules as $mod):
        $mi++;
        $mid = (int) ($mod['id'] ?? 0);
        $lessons = $mod['lessons'] ?? [];
        $modObjectiveLines = function_exists('training_lms_objectives_list_from_storage')
            ? training_lms_objectives_list_from_storage((string) ($mod['learning_objectives'] ?? ''))
            : [];
        if ($modObjectiveLines === []) {
            $modObjectiveLines = [''];
        }
    ?>
    <div id="studio-mod-<?= (int) $mid ?>" class="studio-sort-module-card training-studio-panel rounded-xl border border-slate-200 bg-white overflow-hidden shadow-sm scroll-mt-36" data-module-id="<?= (int) $mid ?>">
        <div class="bg-slate-50 border-b border-slate-200 px-4 py-3 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2 min-w-0">
                <span class="studio-module-drag-handle cursor-grab text-slate-400 hover:text-slate-600 select-none px-0.5 text-lg leading-none" title="Glisser pour déplacer le module" aria-hidden="true">⠿</span>
                <span class="text-xs font-black uppercase text-slate-500 truncate">Module <?= (int) $mi ?></span>
            </div>
        </div>
        <div class="p-4 space-y-4">
            <form method="post" action="<?= training_studio_url($cid) ?>" class="space-y-3 border-b border-slate-100 pb-4">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="_action" value="update_module">
                <input type="hidden" name="module_id" value="<?= $mid ?>">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Titre du module</label>
                    <input type="text" name="module_title" required value="<?= htmlspecialchars((string) ($mod['title'] ?? '')) ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Description</label>
                    <textarea name="module_description" rows="2" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"><?= htmlspecialchars((string) ($mod['description'] ?? '')) ?></textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Sous-titre (apprenant)</label>
                    <input type="text" name="module_subtitle" maxlength="255" value="<?= htmlspecialchars((string) ($mod['subtitle'] ?? '')) ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="Ex. Phase tactique — semaine 2">
                </div>
                <div data-lms-objectives-scope>
                    <label class="block text-xs font-bold text-slate-600 mb-2">Objectifs du module</label>
                    <div class="space-y-2" data-lms-objectives-list>
                        <?php foreach ($modObjectiveLines as $mol): ?>
                        <div class="flex gap-2 items-center" data-lms-objective-row>
                            <input type="text" name="module_learning_objectives[]" value="<?= htmlspecialchars($mol) ?>" class="flex-1 min-w-0 border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="Objectif du module">
                            <button type="button" class="shrink-0 px-2 py-1.5 text-xs font-bold text-rose-600 hover:bg-rose-50 rounded-lg" data-lms-objective-remove>Retirer</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="mt-2 px-3 py-1.5 text-xs font-black uppercase text-emerald-800 border border-dashed border-emerald-300 rounded-lg hover:bg-emerald-50" data-lms-objective-add>+ Ajouter</button>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Durée indicative du module (min)</label>
                    <input type="number" name="module_estimated_minutes" min="0" max="99999" step="1" value="<?= (int) ($mod['estimated_minutes'] ?? 0) ?>" class="w-full max-w-xs border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="module_is_required" value="1" <?= !empty($mod['is_required']) ? 'checked' : '' ?>>
                    Module obligatoire
                </label>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="px-4 py-2 bg-slate-800 text-white text-xs font-bold rounded-lg hover:bg-slate-700">Enregistrer le module</button>
                </div>
            </form>
            <form method="post" action="<?= training_studio_url($cid) ?>" class="inline" onsubmit="return confirm('Supprimer ce module et toutes ses leçons ?');">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="_action" value="delete_module">
                <input type="hidden" name="module_id" value="<?= $mid ?>">
                <button type="submit" class="text-xs text-rose-600 underline hover:text-rose-800">Supprimer le module</button>
            </form>

            <h3 class="text-xs font-black uppercase text-slate-500 pt-2">Leçons</h3>
            <div class="studio-sort-lessons space-y-3" data-module-id="<?= (int) $mid ?>">
            <?php
            $li = 0;
            foreach ($lessons as $les):
                $li++;
                $lid = (int) ($les['id'] ?? 0);
                $lt = (string) ($les['lesson_type'] ?? 'richtext');
                $lesObjLines = function_exists('training_lms_objectives_list_from_storage')
                    ? training_lms_objectives_list_from_storage((string) ($les['learning_objectives'] ?? ''))
                    : [];
                if ($lesObjLines === []) {
                    $lesObjLines = [''];
                }
            ?>
            <?php
                $studioRes = $les['studio_resources'] ?? [];
                $lessonResAnchor = 'lesson-res-' . (int) $lid;
                $resCount = count($studioRes);
                $docStatusLabels = function_exists('training_lms_document_status_labels_fr') ? training_lms_document_status_labels_fr() : [];
            ?>
            <div class="studio-sort-lesson-card ts-lesson-card border border-slate-100 rounded-xl p-4 md:p-5 bg-slate-50/50 space-y-3" data-lesson-id="<?= (int) $lid ?>">
                <div class="flex flex-wrap justify-between gap-2 items-center">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="studio-lesson-drag-handle cursor-grab text-slate-400 hover:text-slate-600 select-none text-base leading-none" title="Glisser pour déplacer la leçon" aria-hidden="true">⠿</span>
                        <span class="text-xs font-semibold text-slate-700 truncate">Leçon <?= (int) $li ?></span>
                    </div>
                </div>
                <div class="ts-lesson-layout flex flex-col xl:grid xl:grid-cols-[minmax(0,1.15fr)_minmax(22rem,1fr)] gap-5 xl:items-start">
                    <div class="min-w-0 space-y-3 order-1">
                        <form method="post" action="<?= training_studio_url($cid) ?>" class="space-y-2">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="hidden" name="_action" value="update_lesson">
                            <input type="hidden" name="lesson_id" value="<?= $lid ?>">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-600 mb-0.5">Titre</label>
                                <input type="text" name="lesson_title" required value="<?= htmlspecialchars((string) ($les['title'] ?? '')) ?>" class="w-full border border-slate-200 rounded px-2 py-1.5 text-sm">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-600 mb-0.5">Résumé (apprenant, catalogue &amp; menu)</label>
                                <input type="text" name="lesson_summary" maxlength="500" value="<?= htmlspecialchars((string) ($les['summary'] ?? '')) ?>" class="w-full border border-slate-200 rounded px-2 py-1.5 text-sm" placeholder="Court accroche sous le titre">
                            </div>
                            <div data-lms-objectives-scope>
                                <label class="block text-[11px] font-bold text-slate-600 mb-1">Objectifs de la leçon</label>
                                <div class="space-y-2" data-lms-objectives-list>
                                    <?php foreach ($lesObjLines as $lol): ?>
                                    <div class="flex gap-2 items-center" data-lms-objective-row>
                                        <input type="text" name="lesson_learning_objectives[]" value="<?= htmlspecialchars($lol) ?>" class="flex-1 min-w-0 border border-slate-200 rounded px-2 py-1.5 text-sm" placeholder="Objectif">
                                        <button type="button" class="shrink-0 px-2 py-1 text-[11px] font-bold text-rose-600" data-lms-objective-remove>Retirer</button>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="mt-1 px-2 py-1 text-[11px] font-black uppercase text-emerald-800 border border-dashed border-emerald-300 rounded" data-lms-objective-add>+ Ajouter</button>
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-600 mb-0.5">Notes internes / formateur <span class="font-normal text-slate-400">(non affichées côté apprenant)</span></label>
                                <textarea name="lesson_instructor_notes" rows="2" class="w-full border border-slate-200 rounded px-2 py-1.5 text-sm"><?= htmlspecialchars((string) ($les['instructor_notes'] ?? '')) ?></textarea>
                            </div>
                            <?php
                            $isCanvasLesson = ($lt === 'canvas');
                            $isJsonLesson = in_array($lt, ['quiz', 'modals', 'slideshow'], true);
                            $canvasStored = trim((string) ($les['content'] ?? ''));
                            $canvasJsonOut = ($isCanvasLesson && $canvasStored !== '') ? $canvasStored : $defaultCanvasJson;
                            ?>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-600 mb-0.5">Type</label>
                                    <select name="lesson_type" class="w-full border border-slate-200 rounded px-2 py-1.5 text-sm" onchange="lmsCanvasToggleLessonEditor(this)">
                                        <?php foreach ($lessonTypeOptgroups as $groupLabel => $typeIds): ?>
                                        <optgroup label="<?= htmlspecialchars($groupLabel) ?>">
                                        <?php foreach ($typeIds as $t): ?>
                                        <option value="<?= htmlspecialchars($t) ?>" <?= $lt === $t ? 'selected' : '' ?>><?= htmlspecialchars($lessonTypeLabels[$t] ?? $t) ?></option>
                                        <?php endforeach; ?>
                                        </optgroup>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-600 mb-0.5">Durée (min)</label>
                                    <input type="number" name="lesson_duration_minutes" min="0" step="1" value="<?= (int) ($les['duration_minutes'] ?? 0) ?>" class="w-full border border-slate-200 rounded px-2 py-1.5 text-sm">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-600 mb-0.5">Difficulté</label>
                                    <select name="lesson_difficulty" class="w-full border border-slate-200 rounded px-2 py-1.5 text-sm">
                                        <option value="">—</option>
                                        <?php foreach ($levelLabels as $lv => $lab): ?>
                                        <option value="<?= htmlspecialchars($lv) ?>" <?= (($les['difficulty'] ?? '') === $lv) ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="hidden text-[11px] text-slate-600 bg-slate-50 border border-slate-100 rounded-lg p-2 mb-1" data-lms-json-help data-template-quiz="<?= htmlspecialchars(training_lesson_default_quiz_json(), ENT_QUOTES, 'UTF-8') ?>" data-template-modals="<?= htmlspecialchars(training_lesson_default_modals_json(), ENT_QUOTES, 'UTF-8') ?>" data-template-slideshow="<?= htmlspecialchars(training_lesson_default_slideshow_json(), ENT_QUOTES, 'UTF-8') ?>">
                                <span data-lms-json-help-text></span>
                            </div>
                            <div data-lms-plain-content class="<?= $isCanvasLesson ? 'hidden' : '' ?>">
                                <label class="block text-[11px] font-bold text-slate-600 mb-0.5" data-lms-plain-label><?= $isJsonLesson ? 'Contenu de la leçon' : 'Contenu (HTML ou texte)' ?></label>
                                <div data-lms-interactive-root class="<?= $isJsonLesson ? '' : 'hidden' ?> rounded-xl border border-emerald-100 bg-emerald-50/40 p-3 mb-2 min-h-[4rem]"></div>
                                <textarea name="lesson_content" rows="<?= $isJsonLesson ? '6' : '4' ?>" class="w-full border border-slate-200 rounded px-2 py-1.5 <?= $isJsonLesson ? 'hidden text-xs font-mono' : 'text-sm' ?>" <?= $isCanvasLesson ? 'disabled' : '' ?> data-lms-lesson-body><?= htmlspecialchars((string) ($les['content'] ?? '')) ?></textarea>
                            </div>
                            <div data-lms-canvas-wrap class="<?= $isCanvasLesson ? '' : 'hidden' ?>">
                                <label class="block text-[11px] font-bold text-violet-700 mb-1">Éditeur graphique (slides, templates, médias, actions)</label>
                                <div data-lms-canvas-editor class="rounded-xl border border-violet-200 bg-violet-50/40 p-4">
                                    <textarea name="lesson_content" class="hidden" data-lms-canvas-json <?= !$isCanvasLesson ? 'disabled' : '' ?>><?= htmlspecialchars($canvasJsonOut) ?></textarea>
                                </div>
                            </div>
                            <div data-lms-external-block>
                                <label class="block text-[11px] font-bold text-slate-600 mb-0.5" data-lms-external-label>URL externe (optionnel)</label>
                                <input type="text" name="lesson_external_url" value="<?= htmlspecialchars((string) ($les['external_url'] ?? '')) ?>" class="w-full border border-slate-200 rounded px-2 py-1.5 text-sm" data-lms-external-input>
                                <p class="text-[10px] text-slate-500 mt-0.5 hidden" data-lms-external-hint></p>
                            </div>
                            <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                                <input type="checkbox" name="lesson_is_required" value="1" <?= !empty($les['is_required']) ? 'checked' : '' ?>>
                                Leçon obligatoire
                            </label>
                            <div class="flex flex-wrap gap-2">
                                <button type="submit" class="px-3 py-1.5 bg-slate-800 text-white text-xs font-bold rounded hover:bg-slate-700">Enregistrer la leçon</button>
                            </div>
                        </form>
                        <form method="post" action="<?= training_studio_url($cid) ?>" onsubmit="return confirm('Supprimer cette leçon ?');">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="hidden" name="_action" value="delete_lesson">
                            <input type="hidden" name="lesson_id" value="<?= $lid ?>">
                            <button type="submit" class="text-xs text-rose-600 underline">Supprimer la leçon</button>
                        </form>
                    </div>
                    <aside class="order-2 xl:sticky xl:top-28 self-start w-full">
                        <details class="ts-res-panel rounded-xl border border-sky-200 bg-sky-50/70 text-slate-800 shadow-sm" id="<?= htmlspecialchars($lessonResAnchor) ?>" open>
                            <summary class="ts-res-panel__summary cursor-pointer select-none px-4 py-3 text-xs font-black uppercase tracking-wide text-sky-900">
                                Ressources<?= $resCount > 0 ? ' (' . (int) $resCount . ')' : '' ?>
                            </summary>
                            <div class="px-4 pb-4 pt-2 border-t border-sky-100 space-y-4">
                                <p class="text-xs text-sky-950/90 leading-relaxed">Ajoutez une <strong class="font-semibold">image</strong>, un <strong class="font-semibold">lien web</strong>, un <strong class="font-semibold">fichier</strong> ou un <strong class="font-semibold">document du centre</strong> : les apprenants les retrouvent en bas de leçon.</p>
                                <?php if ($studioRes !== []): ?>
                                <ul class="ts-res-list space-y-3">
                                    <?php foreach ($studioRes as $sr):
                                        $srid = (int) ($sr['id'] ?? 0);
                                        $kindLab = function_exists('training_lms_studio_resource_kind_label_fr')
                                            ? training_lms_studio_resource_kind_label_fr($sr)
                                            : ($resourceTypeLabels[(string) ($sr['resource_type'] ?? 'link')] ?? '');
                                        $dSt = (string) ($sr['document_status'] ?? '');
                                        $dStLab = ($dSt !== '' && isset($docStatusLabels[$dSt])) ? $docStatusLabels[$dSt] : '';
                                        $srIsImage = function_exists('training_lms_resource_is_image') && training_lms_resource_is_image($sr) && !empty($sr['file_path']);
                                        $srPreviewUrl = $srIsImage
                                            ? training_studio_url($cid . '/ressource/' . $srid) . '?inline=1'
                                            : '';
                                    ?>
                                    <li class="ts-res-item flex flex-wrap gap-3 items-start border-b border-sky-100/90 pb-3 last:border-0 last:pb-0">
                                        <?php if ($srPreviewUrl !== ''): ?>
                                        <a href="<?= htmlspecialchars($srPreviewUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="ts-res-thumb shrink-0" title="Aperçu de l’image">
                                            <img src="<?= htmlspecialchars($srPreviewUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy" width="96" height="72">
                                        </a>
                                        <?php endif; ?>
                                        <span class="text-sm text-slate-800 min-w-0 flex-1">
                                            <span class="font-semibold block leading-snug"><?= htmlspecialchars((string) ($sr['title'] ?? '')) ?></span>
                                            <span class="text-xs text-slate-500"><?= htmlspecialchars($kindLab) ?><?php if ($dStLab !== '' && !empty($sr['document_id'])): ?> · <?= htmlspecialchars($dStLab) ?><?php endif; ?></span>
                                        </span>
                                        <form method="post" action="<?= training_studio_url($cid) ?>" class="shrink-0" onsubmit="return confirm('Retirer cette ressource de la leçon ?');">
                                            <?= \App\Core\Csrf::field() ?>
                                            <input type="hidden" name="_action" value="delete_lesson_resource">
                                            <input type="hidden" name="resource_id" value="<?= $srid ?>">
                                            <button type="submit" class="text-xs font-bold text-rose-600 hover:underline">Retirer</button>
                                        </form>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php else: ?>
                                <p class="text-sm text-slate-600">Aucune ressource pour l’instant.</p>
                                <?php endif; ?>
                                <form method="post" action="<?= training_studio_url($cid) ?>" enctype="multipart/form-data" class="ts-res-form space-y-3 pt-3 border-t border-sky-100" data-lms-lesson-resource-form>
                                    <?= \App\Core\Csrf::field() ?>
                                    <input type="hidden" name="_action" value="add_lesson_resource">
                                    <input type="hidden" name="lesson_id" value="<?= (int) $lid ?>">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 mb-1">Titre affiché pour l’apprenant</label>
                                        <input type="text" name="resource_title" maxlength="255" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="Laisser vide pour reprendre le nom du fichier ou du document">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 mb-1">Que souhaitez-vous lier ?</label>
                                        <select name="resource_add_mode" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" data-lms-resource-mode>
                                            <option value="image">Une image (JPG, PNG, WebP)</option>
                                            <option value="link">Une adresse web (site, article, vidéo en ligne…)</option>
                                            <option value="file">Un autre fichier (PDF, archive, audio…)</option>
                                            <option value="library">Un document du centre documentaire de la communauté</option>
                                            <option value="library_upload">Bibliothèque d’assets (upload type YouTube Studio)</option>
                                        </select>
                                    </div>
                                    <div class="space-y-2" data-lms-res-panel="image">
                                        <input type="hidden" name="resource_type" value="image">
                                        <div class="rounded-lg border border-sky-200 bg-white/90 px-3 py-2.5 text-xs text-sky-950">
                                            L’image s’affiche dans la leçon pour les apprenants. Formats acceptés : JPG, PNG, WebP (max. 15 Mo).
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-700 mb-1">Choisir une image</label>
                                            <input type="file" name="resource_upload" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-sky-100 file:px-3 file:py-2 file:text-xs file:font-bold file:text-sky-900">
                                        </div>
                                    </div>
                                    <div class="space-y-2 hidden" data-lms-res-panel="link">
                                        <input type="hidden" name="resource_type" value="link">
                                        <div>
                                            <label class="block text-xs font-bold text-slate-700 mb-1">Adresse web</label>
                                            <input type="text" name="resource_external_url" inputmode="url" autocomplete="off" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="https://…">
                                            <p class="mt-1 text-[11px] text-slate-500">Collez l’adresse complète affichée dans le navigateur (de préférence en https).</p>
                                        </div>
                                    </div>
                                    <div class="space-y-2 hidden" data-lms-res-panel="file">
                                        <div>
                                            <label class="block text-xs font-bold text-slate-700 mb-1">Type de fichier pour l’apprenant</label>
                                            <select name="resource_type" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                                                <?php foreach ($fileResourceTypeLabels as $rk => $rlab): ?>
                                                <option value="<?= htmlspecialchars($rk) ?>"<?= $rk === 'attachment' ? ' selected' : '' ?>><?= htmlspecialchars($rlab) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-700 mb-1">Envoyer un fichier depuis votre poste</label>
                                            <input type="file" name="resource_upload" accept=".pdf,.jpg,.jpeg,.png,.webp,.zip,.mp4,.mp3,.doc,.docx,image/*,application/pdf" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-sky-100 file:px-3 file:py-2 file:text-xs file:font-bold file:text-sky-900">
                                        </div>
                                        <details class="text-xs text-slate-600">
                                            <summary class="cursor-pointer font-bold text-slate-700">Fichier déjà présent sur le serveur</summary>
                                            <p class="mt-1 mb-1">Si le fichier a été placé manuellement sur l’hébergement, indiquez son chemin relatif à la racine du projet (réservé aux équipes techniques).</p>
                                            <input type="text" name="resource_file_path" maxlength="255" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-xs" placeholder="Ex. storage/…">
                                        </details>
                                    </div>
                                    <div class="space-y-2 hidden" data-lms-res-panel="library">
                                        <?php if ($studioDocPickerCount > 5): ?>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-700 mb-1">Filtrer la liste</label>
                                            <input type="search" data-lms-doc-filter class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="Titre, statut…" autocomplete="off">
                                        </div>
                                        <?php endif; ?>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-700 mb-1">Document du centre</label>
                                            <?php
                                            $lmsDocsByStudioCat = [];
                                            foreach ($libraryDocumentsForPicker as $lmsPickDoc) {
                                                $lmsPid = (int) ($lmsPickDoc['id'] ?? 0);
                                                if ($lmsPid < 1) {
                                                    continue;
                                                }
                                                $lmsCn = trim((string) ($lmsPickDoc['category_name'] ?? ''));
                                                $lmsGrp = $lmsCn !== '' ? $lmsCn : '— Sans classement —';
                                                $lmsDocsByStudioCat[$lmsGrp][] = $lmsPickDoc;
                                            }
                                            uksort($lmsDocsByStudioCat, 'strnatcasecmp');
                                            $lmsSelectSize = $studioDocPickerCount > 12 ? min(10, max(4, $studioDocPickerCount)) : null;
                                            ?>
                                            <select name="document_id" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" data-lms-doc-select<?= $lmsSelectSize !== null ? ' size="' . (int) $lmsSelectSize . '"' : '' ?>>
                                                <option value="">— Choisir un document —</option>
                                                <?php foreach ($lmsDocsByStudioCat as $lmsGrpLabel => $lmsGrpDocs): ?>
                                                <optgroup label="<?= htmlspecialchars($lmsGrpLabel) ?>">
                                                    <?php foreach ($lmsGrpDocs as $pickDoc):
                                                        $pid = (int) ($pickDoc['id'] ?? 0);
                                                        $pTitle = (string) ($pickDoc['title'] ?? 'Sans titre');
                                                        $pSt = (string) ($pickDoc['status'] ?? '');
                                                        $pStLab = $pSt !== '' && isset($docStatusLabels[$pSt]) ? $docStatusLabels[$pSt] : $pSt;
                                                        ?>
                                                    <option value="<?= $pid ?>"><?= htmlspecialchars($pTitle) ?><?= $pStLab !== '' ? ' — ' . htmlspecialchars($pStLab) : '' ?></option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if ($studioDocPickerCount > 12): ?>
                                            <p class="mt-1 text-[11px] text-slate-500">Liste déroulante élargie : faites défiler ou utilisez le filtre ci-dessus.</p>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($studioCanDocsAdmin): ?>
                                        <p class="text-xs text-slate-600">
                                            <a href="<?= htmlspecialchars(url('documents/gestion'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-sky-800 underline decoration-sky-200 hover:decoration-sky-600">Ouvrir la gestion documentaire</a>
                                            pour publier ou mettre à jour des fichiers du centre.
                                        </p>
                                        <?php endif; ?>
                                        <?php if ($studioDocPickerCount === 0): ?>
                                        <p class="rounded-lg border border-amber-200 bg-amber-50/80 px-3 py-2 text-xs text-amber-950">Aucun document dans le centre pour cette communauté. Ajoutez-en depuis la gestion documentaire, puis revenez ici.</p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="space-y-2 hidden" data-lms-res-panel="library_upload">
                                        <div class="rounded-lg border border-violet-200 bg-violet-50/70 px-3 py-2.5 text-xs text-violet-900">
                                            <p class="font-semibold">Bibliothèque d’assets Studio</p>
                                            <p class="mt-0.5">Importez un média depuis votre ordinateur, ajoutez un descriptif et choisissez sa visibilité <strong>privé</strong> comme sur YouTube Studio.</p>
                                        </div>
                                        <button type="button" class="w-full rounded-lg border border-violet-300 bg-white px-3 py-2.5 text-xs font-black uppercase tracking-wide text-violet-800 hover:bg-violet-50" data-lms-open-upload-modal data-lms-upload-modal-target="studio-asset-modal-<?= (int) $lid ?>">
                                            Ouvrir le modal d’upload d’asset
                                        </button>
                                        <p class="text-[11px] text-slate-500">Après l’upload, l’asset est ajouté à la bibliothèque documentaire puis lié à cette leçon.</p>
                                    </div>
                                    <button type="submit" class="w-full px-4 py-2.5 bg-sky-700 text-white text-sm font-bold rounded-xl hover:bg-sky-800" data-lms-inline-submit>Ajouter la ressource</button>
                                </form>
                                <dialog id="studio-asset-modal-<?= (int) $lid ?>" class="w-full max-w-xl rounded-2xl border border-slate-200 p-0 shadow-xl backdrop:bg-slate-950/45">
                                    <form method="dialog" class="border-b border-slate-100 px-4 py-3 flex items-center justify-between gap-3">
                                        <p class="text-sm font-black text-slate-800">Upload asset — Leçon <?= (int) $li ?></p>
                                        <button type="submit" class="rounded-md border border-slate-200 px-2 py-1 text-xs font-bold text-slate-600 hover:bg-slate-50">Fermer</button>
                                    </form>
                                    <form method="post" action="<?= training_studio_url($cid) ?>" enctype="multipart/form-data" class="space-y-3 p-4">
                                        <?= \App\Core\Csrf::field() ?>
                                        <input type="hidden" name="_action" value="add_lesson_resource">
                                        <input type="hidden" name="lesson_id" value="<?= (int) $lid ?>">
                                        <input type="hidden" name="resource_add_mode" value="library_upload">
                                        <div>
                                            <label class="block text-[11px] font-bold text-slate-700 mb-0.5">Fichier (depuis votre poste)</label>
                                            <input type="file" name="resource_library_upload" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp,.pdf,video/mp4,.mp4" required class="block w-full text-xs text-slate-600 file:mr-2 file:rounded file:border-0 file:bg-violet-100 file:px-2 file:py-1">
                                            <p class="mt-1 text-[10px] text-slate-500">Formats acceptés : PDF, images JPG/PNG/WebP, vidéo MP4 (max 10 Mo).</p>
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-bold text-slate-700 mb-0.5">Titre de l’asset</label>
                                            <input type="text" name="resource_library_title" maxlength="255" class="w-full border border-slate-200 rounded px-2 py-1.5 text-sm" placeholder="Ex. Débrief Alpha — vidéo courte">
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-bold text-slate-700 mb-0.5">Description (bibliothèque)</label>
                                            <textarea name="resource_library_description" rows="2" class="w-full border border-slate-200 rounded px-2 py-1.5 text-sm" placeholder="Contexte, auteur, restrictions d’usage…"></textarea>
                                        </div>
                                        <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                                            <input type="checkbox" name="resource_library_private" value="1">
                                            Asset privé (uniquement staff autorisé)
                                        </label>
                                        <button type="submit" class="w-full rounded-lg bg-violet-700 px-3 py-2 text-xs font-black uppercase tracking-wide text-white hover:bg-violet-800">Uploader l’asset et lier à la leçon</button>
                                    </form>
                                </dialog>
                            </div>
                        </details>
                    </aside>
                </div>
            </div>
            <?php endforeach; ?>
            </div>

            <div class="border-t border-slate-100 pt-4 mt-2">
                <p class="text-xs font-bold text-slate-600 mb-2">Ajouter une leçon dans ce module</p>
                <form method="post" action="<?= training_studio_url($cid) ?>" class="space-y-2">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="_action" value="add_lesson">
                    <input type="hidden" name="module_id" value="<?= $mid ?>">
                    <input type="text" name="lesson_title" required placeholder="Titre de la leçon" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                    <input type="text" name="lesson_summary" maxlength="500" placeholder="Résumé (optionnel)" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                    <div data-lms-objectives-scope>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Objectifs de la leçon (optionnel)</label>
                        <div class="space-y-2" data-lms-objectives-list>
                            <div class="flex gap-2 items-center" data-lms-objective-row>
                                <input type="text" name="lesson_learning_objectives[]" value="" class="flex-1 min-w-0 border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="Objectif">
                                <button type="button" class="shrink-0 px-2 py-1 text-[11px] font-bold text-rose-600" data-lms-objective-remove>Retirer</button>
                            </div>
                        </div>
                        <button type="button" class="mt-1 px-2 py-1 text-[11px] font-black uppercase text-emerald-800 border border-dashed border-emerald-300 rounded" data-lms-objective-add>+ Ajouter</button>
                    </div>
                    <textarea name="lesson_instructor_notes" rows="2" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="Notes internes formateur (optionnel)"></textarea>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <select name="lesson_type" class="border border-slate-200 rounded-lg px-3 py-2 text-sm" onchange="lmsCanvasToggleLessonEditor(this)">
                            <?php foreach ($lessonTypeOptgroups as $groupLabel => $typeIds): ?>
                            <optgroup label="<?= htmlspecialchars($groupLabel) ?>">
                            <?php foreach ($typeIds as $t): ?>
                            <option value="<?= htmlspecialchars($t) ?>" <?= $t === 'richtext' ? 'selected' : '' ?>><?= htmlspecialchars($lessonTypeLabels[$t] ?? $t) ?></option>
                            <?php endforeach; ?>
                            </optgroup>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" name="lesson_duration_minutes" min="0" value="0" placeholder="Durée (min)" class="border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        <select name="lesson_difficulty" class="border border-slate-200 rounded-lg px-3 py-2 text-sm">
                            <option value="">Difficulté</option>
                            <?php foreach ($levelLabels as $lv => $lab): ?>
                            <option value="<?= htmlspecialchars($lv) ?>"><?= htmlspecialchars($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="hidden text-[11px] text-slate-600 bg-slate-50 border border-slate-100 rounded-lg p-2 mb-1" data-lms-json-help data-template-quiz="<?= htmlspecialchars(training_lesson_default_quiz_json(), ENT_QUOTES, 'UTF-8') ?>" data-template-modals="<?= htmlspecialchars(training_lesson_default_modals_json(), ENT_QUOTES, 'UTF-8') ?>" data-template-slideshow="<?= htmlspecialchars(training_lesson_default_slideshow_json(), ENT_QUOTES, 'UTF-8') ?>">
                        <span data-lms-json-help-text></span>
                    </div>
                    <div data-lms-plain-content>
                        <label class="block text-[11px] font-bold text-slate-600 mb-0.5" data-lms-plain-label>Contenu (HTML ou texte)</label>
                        <div data-lms-interactive-root class="hidden rounded-xl border border-emerald-100 bg-emerald-50/40 p-3 mb-2 min-h-[4rem]"></div>
                        <textarea name="lesson_content" rows="3" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="Contenu" data-lms-lesson-body></textarea>
                    </div>
                    <div data-lms-external-block>
                        <label class="block text-[11px] font-bold text-slate-600 mb-0.5" data-lms-external-label>URL externe (optionnel)</label>
                        <input type="text" name="lesson_external_url" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="URL externe (optionnel)" data-lms-external-input>
                        <p class="text-[10px] text-slate-500 mt-0.5 hidden" data-lms-external-hint></p>
                    </div>
                    <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                        <input type="checkbox" name="lesson_is_required" value="1" checked>
                        Obligatoire
                    </label>
                    <button type="submit" class="px-4 py-2 bg-emerald-700 text-white text-xs font-bold rounded-lg hover:bg-emerald-800">Ajouter la leçon</button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>

    <section class="rounded-xl border border-dashed border-slate-300 bg-slate-50/50 p-6 mb-8">
        <h3 class="text-sm font-black uppercase text-slate-500 mb-3">Ajouter un module</h3>
        <form method="post" action="<?= training_studio_url($cid) ?>" class="space-y-3 max-w-xl">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="_action" value="add_module">
            <input type="text" name="module_title" required placeholder="Titre du module" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
            <input type="text" name="module_subtitle" maxlength="255" placeholder="Sous-titre (optionnel)" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
            <textarea name="module_description" rows="2" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="Description (optionnel)"></textarea>
            <div data-lms-objectives-scope>
                <label class="block text-xs font-bold text-slate-600 mb-2">Objectifs du module (optionnel)</label>
                <div class="space-y-2" data-lms-objectives-list>
                    <div class="flex gap-2 items-center" data-lms-objective-row>
                        <input type="text" name="module_learning_objectives[]" value="" class="flex-1 min-w-0 border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="Objectif">
                        <button type="button" class="shrink-0 px-2 py-1.5 text-xs font-bold text-rose-600" data-lms-objective-remove>Retirer</button>
                    </div>
                </div>
                <button type="button" class="mt-2 px-3 py-1.5 text-xs font-black uppercase text-emerald-800 border border-dashed border-emerald-300 rounded-lg hover:bg-emerald-50" data-lms-objective-add>+ Ajouter</button>
            </div>
            <input type="number" name="module_estimated_minutes" min="0" max="99999" value="0" placeholder="Durée indicative module (min)" class="w-full max-w-xs border border-slate-200 rounded-lg px-3 py-2 text-sm">
            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="module_is_required" value="1" checked>
                Module obligatoire
            </label>
            <button type="submit" class="px-6 py-3 bg-slate-900 text-white text-sm font-bold rounded-lg hover:bg-slate-800">Ajouter le module</button>
        </form>
    </section>

    <?php endif; ?>

    <?php if ($trainingStudioSection === 'presentation'): ?>
    <script src="<?= url('assets/js/training_studio_presentation.js') ?>" defer></script>
    <?php endif; ?>
    <?php if (in_array($trainingStudioSection, ['fiche', 'structure'], true)): ?>
    <script src="<?= url('assets/js/training_studio_objectives.js') ?>" defer></script>
    <?php endif; ?>
    <?php if ($trainingStudioSection === 'structure'): ?>
    <link rel="stylesheet" href="<?= url('assets/css/training_canvas.css') ?>">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js" defer></script>
    <script src="<?= url('assets/js/training_studio_sortable.js') ?>" defer></script>
    <script src="<?= url('assets/js/training_studio_lesson_resources.js') ?>" defer></script>
    <script src="<?= url('assets/js/training_canvas_editor.js') ?>" defer></script>
    <script src="<?= url('assets/js/training_studio_interactive_editors.js') ?>" defer></script>
    <?php endif; ?>
</div>
