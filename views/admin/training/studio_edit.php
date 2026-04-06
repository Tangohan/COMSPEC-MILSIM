<?php
$course = $course ?? [];
$tenant = $tenant ?? null;
$lessonTypes = $lessonTypes ?? [];
$visibilityOptions = $visibilityOptions ?? [];
$levelOptions = $levelOptions ?? [];
$canPublish = $canPublish ?? false;
$cid = (int) ($course['id'] ?? 0);
$slug = (string) ($course['slug'] ?? '');
$modules = $course['modules'] ?? [];

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

    <header class="training-studio-hero mb-8">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6">
            <div class="min-w-0">
                <p class="text-[0.65rem] font-black tracking-[0.35em] uppercase text-emerald-600 mb-2">Édition — formation</p>
                <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight"><?= htmlspecialchars((string) ($course['title'] ?? '')) ?></h1>
                <?php if ($tenant): ?>
                <p class="text-slate-600 text-sm mt-2">Communauté <strong><?= htmlspecialchars(community_display_name($tenant)) ?></strong></p>
                <?php endif; ?>
                <p class="text-sm text-slate-500 mt-3">
                    <a href="<?= training_studio_url() ?>" class="font-semibold text-slate-700 underline decoration-slate-300 hover:text-emerald-800">← Toutes les formations</a>
                    <span class="text-slate-300 mx-2">·</span>
                    <a href="<?= htmlspecialchars(training_lms_admin_url()) ?>" class="text-slate-600 underline decoration-slate-200 hover:text-slate-900">Admin formations</a>
                </p>
            </div>
            <div class="flex flex-wrap gap-2 shrink-0">
                <?php if ($studioEditCanVitrine): ?>
                <a href="<?= htmlspecialchars(training_lms_admin_url('courses/' . $cid . '/showcase')) ?>" class="px-3 py-2 border border-slate-200 bg-white text-slate-800 text-xs font-bold rounded-xl hover:bg-slate-50 shadow-sm">Vitrine</a>
                <?php endif; ?>
                <a href="<?= training_studio_url($cid . '/preview') ?>" class="px-3 py-2 border border-amber-200 bg-amber-50 text-amber-950 text-xs font-bold rounded-xl hover:bg-amber-100 shadow-sm">Aperçu caviardé</a>
                <a href="<?= url('formations/' . rawurlencode($slug)) ?>" class="px-3 py-2 border border-slate-200 bg-white text-slate-800 text-xs font-bold rounded-xl hover:bg-slate-50 shadow-sm" target="_blank" rel="noopener">Aperçu public</a>
                <a href="<?= htmlspecialchars(training_lms_admin_url('enrollments') . '?course_id=' . (int) $cid) ?>" class="px-3 py-2 border border-slate-200 bg-white text-slate-800 text-xs font-bold rounded-xl hover:bg-slate-50 shadow-sm">Assignations</a>
            </div>
        </div>
    </header>

    <?php
    $trainingStudioSection = $trainingStudioSection ?? 'fiche';
    if (!in_array($trainingStudioSection, ['fiche', 'presentation', 'structure'], true)) {
        $trainingStudioSection = 'fiche';
    }
    $studioU = static fn (string $s): string => training_studio_url($cid . '/' . $s);
    ?>
    <nav class="mb-8 flex flex-wrap gap-2 rounded-2xl border border-slate-200/80 bg-slate-100/60 p-1.5 shadow-inner" aria-label="Sections du studio">
        <a href="<?= htmlspecialchars($studioU('fiche')) ?>" class="inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-xs font-black uppercase tracking-wider transition <?= $trainingStudioSection === 'fiche' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-white hover:text-slate-900' ?>">Données &amp; inscription</a>
        <a href="<?= htmlspecialchars($studioU('presentation')) ?>" class="inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-xs font-black uppercase tracking-wider transition <?= $trainingStudioSection === 'presentation' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-white hover:text-slate-900' ?>">Présentation apprenant</a>
        <a href="<?= htmlspecialchars($studioU('structure')) ?>" class="inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-xs font-black uppercase tracking-wider transition <?= $trainingStudioSection === 'structure' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-white hover:text-slate-900' ?>">Modules &amp; leçons</a>
    </nav>

    <?php if ($trainingStudioSection === 'fiche'): ?>
    <form method="post" action="<?= training_studio_url($cid) ?>" class="space-y-10 mb-12" id="studio-fiche-form">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="_action" value="save_course">
        <input type="hidden" name="_studio_section" value="fiche">

        <section id="studio-fiche" class="training-studio-panel scroll-mt-28 p-6 md:p-8 space-y-4 shadow-sm">
            <h2 class="text-sm font-black uppercase tracking-[0.2em] text-slate-500">Fiche formation</h2>
            <p class="text-xs text-slate-500">Les formations <strong>publiées</strong> apparaissent dans le catalogue apprenant. Les brouillons restent réservés au Studio.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-slate-600 mb-1">Titre</label>
                    <input type="text" name="title" required value="<?= htmlspecialchars((string) ($course['title'] ?? '')) ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Slug</label>
                    <input type="text" name="slug" required value="<?= htmlspecialchars($slug) ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono text-xs">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Code affichage (ex. A-03)</label>
                    <input type="text" name="course_code" maxlength="32" value="<?= htmlspecialchars((string) ($course['course_code'] ?? '')) ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono text-xs" placeholder="Optionnel">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Visibilité</label>
                    <?php $curVis = (string) ($course['visibility'] ?? 'draft'); ?>
                    <select name="visibility" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        <?php foreach ($visibilityOptions as $v):
                            $pubLocked = ($v === 'published' && !$canPublish && $curVis !== 'published');
                        ?>
                        <option value="<?= htmlspecialchars($v) ?>" <?= ($curVis === $v) ? 'selected' : '' ?> <?= $pubLocked ? 'disabled' : '' ?>><?= htmlspecialchars($visLabels[$v] ?? $v) ?><?= $pubLocked ? ' (permission requise)' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Catégorie</label>
                    <input type="text" name="category" value="<?= htmlspecialchars((string) ($course['category'] ?? '')) ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="Optionnel">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Niveau</label>
                    <select name="level" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        <?php foreach ($levelOptions as $lv): ?>
                        <option value="<?= htmlspecialchars($lv) ?>" <?= (($course['level'] ?? 'initiation') === $lv) ? 'selected' : '' ?>><?= htmlspecialchars($levelLabels[$lv] ?? $lv) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Langue (code)</label>
                    <input type="text" name="language_code" maxlength="10" value="<?= htmlspecialchars((string) ($course['language_code'] ?? 'fr')) ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Durée estimée (min)</label>
                    <input type="number" name="estimated_minutes" min="0" step="1" value="<?= (int) ($course['estimated_minutes'] ?? 0) ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Score de réussite (%)</label>
                    <input type="number" name="passing_score" min="0" max="100" step="0.01" value="<?= htmlspecialchars((string) ($course['passing_score'] ?? '80')) ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
                <div class="md:col-span-2 flex flex-wrap gap-6">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="is_mandatory" value="1" <?= !empty($course['is_mandatory']) ? 'checked' : '' ?>>
                        Formation obligatoire
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="is_certifying" value="1" <?= !empty($course['is_certifying']) ? 'checked' : '' ?>>
                        Certifiante
                    </label>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Validité (jours)</label>
                    <?php $validityDaysField = $course['validity_days'] ?? null; ?>
                    <input type="number" name="validity_days" min="0" step="1" value="<?= $validityDaysField !== null && $validityDaysField !== '' ? (int) $validityDaysField : '' ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="Vide = illimité">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-slate-600 mb-1">Accroche courte</label>
                    <input type="text" name="short_description" maxlength="500" value="<?= htmlspecialchars((string) ($course['short_description'] ?? '')) ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-slate-600 mb-1">Description</label>
                    <textarea name="description" rows="5" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"><?= htmlspecialchars((string) ($course['description'] ?? '')) ?></textarea>
                </div>
                <div class="md:col-span-2 rounded-xl border border-sky-100 bg-sky-50/50 p-4 text-sm text-sky-950">
                    <p class="font-bold text-sky-950 mb-1">Apparence &amp; médias de couverture</p>
                    <p class="text-xs text-sky-900/90">Thème couleur, typographie, miniature, bannière et consignes audio se règlent dans l’onglet <a href="<?= htmlspecialchars($studioU('presentation')) ?>" class="font-black underline decoration-sky-400 hover:text-sky-950">Présentation apprenant</a>.</p>
                </div>
                <div class="md:col-span-2" data-lms-objectives-scope>
                    <label class="block text-xs font-bold text-slate-600 mb-2">Objectifs pédagogiques</label>
                    <div class="space-y-2" data-lms-objectives-list>
                        <?php foreach ($courseObjectiveLines as $objLine): ?>
                        <div class="flex gap-2 items-center" data-lms-objective-row>
                            <input type="text" name="course_learning_objectives[]" value="<?= htmlspecialchars($objLine) ?>" class="flex-1 min-w-0 border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="Ex. Savoir appliquer la consigne de sécurité">
                            <button type="button" class="shrink-0 px-2 py-1.5 text-xs font-bold text-rose-600 hover:bg-rose-50 rounded-lg border border-transparent hover:border-rose-100" data-lms-objective-remove title="Retirer cette ligne">Retirer</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="mt-2 px-3 py-1.5 text-xs font-black uppercase tracking-wide text-emerald-800 border border-dashed border-emerald-300 rounded-lg hover:bg-emerald-50" data-lms-objective-add>+ Ajouter un objectif</button>
                </div>
            </div>

            <div id="studio-engagement" class="md:col-span-2 border-t border-slate-200 pt-8 mt-6 scroll-mt-28">
                <h3 class="text-sm font-black uppercase tracking-[0.18em] text-slate-600 mb-3">Politique d’inscription &amp; consignes</h3>
                <p class="text-xs text-slate-500 mb-4 max-w-3xl">Conditions pour l’<strong>auto-inscription</strong> (les assignations manuelles par le staff restent possibles). Prérequis = formation précédente <strong>validée</strong> ; certificats = autres formations dont l’attestation est exigée.</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-800">
                        <input type="checkbox" name="policy_enrollments_blocked" value="1" <?= !empty($policy['enrollments_blocked']) ? 'checked' : '' ?>>
                        Bloquer toutes les nouvelles inscriptions
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-800">
                        <input type="checkbox" name="policy_self_enroll_disabled" value="1" <?= isset($policy['self_enroll_allowed']) && $policy['self_enroll_allowed'] === false ? 'checked' : '' ?>>
                        Désactiver l’auto-inscription (inscription libre)
                    </label>
                    <input type="hidden" name="policy_self_enroll_requires_approval" value="0">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-800 md:col-span-2">
                        <input type="checkbox" name="policy_self_enroll_requires_approval" value="1" <?= !empty($policy['self_enroll_requires_approval']) ? 'checked' : '' ?>>
                        Exiger une validation par un formateur après chaque auto-inscription
                    </label>
                    <p class="text-[11px] text-slate-500 md:col-span-2 -mt-2">Sans effet si l’inscription libre est désactivée. Les personnes choisies ci-dessous (et l’auteur de la fiche) reçoivent une alerte par e-mail.</p>
                    <input type="hidden" name="policy_comments_enabled" value="0">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-800 md:col-span-2">
                        <input type="checkbox" name="policy_comments_enabled" value="1" <?= function_exists('training_lms_policy_comments_enabled') ? (training_lms_policy_comments_enabled($policy) ? 'checked' : '') : 'checked' ?>>
                        Autoriser les commentaires sur la page « Avis &amp; échanges »
                    </label>
                </div>
                <div class="mb-4">
                    <label class="block text-xs font-bold text-slate-600 mb-1">Formateurs notifiés pour valider les inscriptions</label>
                    <select name="policy_enrollment_approver_user_ids[]" multiple size="6" class="w-full max-w-xl border border-slate-200 rounded-lg px-2 py-2 text-sm">
                        <?php foreach ($studioStaffPickUsers as $su):
                            $suid = (int) ($su['id'] ?? 0);
                            if ($suid < 1) {
                                continue;
                            }
                            $slab = trim((string) ($su['display_name'] ?? ''));
                            if ($slab === '') {
                                $slab = (string) ($su['email'] ?? ('#' . $suid));
                            }
                        ?>
                        <option value="<?= $suid ?>" <?= in_array($suid, $policyApproverIds, true) ? 'selected' : '' ?>><?= htmlspecialchars($slab) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-[11px] text-slate-500 mt-1">Vide = seul l’auteur de la fiche formation est prévenu (en plus des gestionnaires disposant déjà des droits d’assignation).</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Prérequis (formations validées avant)</label>
                        <select name="policy_prerequisite_course_ids[]" multiple size="6" class="w-full border border-slate-200 rounded-lg px-2 py-2 text-sm">
                            <?php foreach ($studioOtherCourses as $oc):
                                $oid = (int) ($oc['id'] ?? 0);
                            ?>
                            <option value="<?= $oid ?>" <?= in_array($oid, $policyPrereq, true) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($oc['title'] ?? '')) ?> (<?= htmlspecialchars((string) ($oc['visibility'] ?? '')) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-[11px] text-slate-500 mt-1">Ctrl+clic pour plusieurs.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Attestations requises (autres formations)</label>
                        <select name="policy_certificate_course_ids[]" multiple size="6" class="w-full border border-slate-200 rounded-lg px-2 py-2 text-sm">
                            <?php foreach ($studioOtherCourses as $oc):
                                $oid = (int) ($oc['id'] ?? 0);
                            ?>
                            <option value="<?= $oid ?>" <?= in_array($oid, $policyCerts, true) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($oc['title'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Rôles autorisés (au moins un)</label>
                        <select name="policy_required_role_ids[]" multiple size="6" class="w-full border border-slate-200 rounded-lg px-2 py-2 text-sm">
                            <?php foreach ($studioRoles as $r):
                                $rid = (int) ($r['id'] ?? 0);
                            ?>
                            <option value="<?= $rid ?>" <?= in_array($rid, $policyRoles, true) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($r['name'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-[11px] text-slate-500 mt-1">Vide = aucune contrainte de rôle.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Grades autorisés</label>
                        <select name="policy_required_grade_ids[]" multiple size="6" class="w-full border border-slate-200 rounded-lg px-2 py-2 text-sm">
                            <?php foreach ($studioGrades as $g):
                                $gid = (int) ($g['id'] ?? 0);
                            ?>
                            <option value="<?= $gid ?>" <?= in_array($gid, $policyGrades, true) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($g['label_short'] ?? $g['code'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <p class="block text-xs font-bold text-slate-600 mb-2">Statuts de compte autorisés pour l’auto-inscription</p>
                        <p class="text-[11px] text-slate-500 mb-2">Laissez tout décoché pour n’imposer aucune contrainte sur le statut. Sinon, l’apprenant doit correspondre à <strong>au moins une</strong> des cases cochées.</p>
                        <div class="flex flex-wrap gap-4">
                            <?php foreach ($policyUserStatusLabels as $stVal => $stLabel): ?>
                            <label class="inline-flex items-center gap-2 text-sm text-slate-800">
                                <input type="checkbox" name="policy_user_status[]" value="<?= htmlspecialchars($stVal) ?>" <?= in_array($stVal, $policyStatusesSelected, true) ? 'checked' : '' ?>>
                                <?= htmlspecialchars($stLabel) ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="border-t border-slate-100 pt-6 mt-2" id="studio-engagement-share">
                    <h4 class="text-xs font-black uppercase text-slate-500 mb-2">Repérer la formation ailleurs</h4>
                    <p class="text-[11px] text-slate-500 mb-3 max-w-3xl">Code court unique : les membres connectés à <strong>cette</strong> communauté peuvent le saisir sur la page dédiée pour ouvrir directement la fiche. Si la formation appartient à une autre communauté, le portail l’indique clairement sans mélanger les espaces.</p>
                    <div class="flex flex-wrap items-end gap-3">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">Code actuel</label>
                            <input type="text" readonly class="border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono tracking-widest bg-slate-50 w-48" value="<?= $shareCodeDisplay !== '' ? htmlspecialchars($shareCodeDisplay) : '— (enregistrez la fiche pour en générer un)' ?>">
                        </div>
                        <p class="text-xs text-slate-600 pb-2">Page apprenant : <a href="<?= url('formations/code-acces') ?>" class="font-semibold text-emerald-800 underline decoration-emerald-200 hover:text-emerald-950" target="_blank" rel="noopener">Ouvrir la saisie du code</a></p>
                    </div>
                </div>
            </div>

            <button type="submit" class="px-6 py-3 bg-slate-900 text-white text-sm font-black rounded-xl hover:bg-slate-800 shadow-md">Enregistrer la fiche</button>
        </section>
    </form>

    <div class="training-studio-panel scroll-mt-28 p-6 md:p-8 shadow-sm mb-10 border border-dashed border-slate-200">
        <p class="text-xs font-bold text-slate-700 mb-2">Code de partage</p>
        <p class="text-sm text-slate-600 mb-4">Générez un nouveau code si l’ancien a été partagé trop largement. Les liens déjà envoyés avec l’ancien code ne fonctionneront plus.</p>
        <form method="post" action="<?= training_studio_url($cid) ?>" class="inline-flex">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="_action" value="regenerate_enrollment_share_code">
            <button type="submit" class="px-4 py-2.5 bg-amber-600 text-white text-xs font-black uppercase tracking-wide rounded-xl hover:bg-amber-700 shadow-sm">Régénérer le code</button>
        </form>
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
                <div><label class="block text-[11px] font-bold text-slate-600 mb-0.5">ID instructeur (user)</label><input type="number" name="session_instructor_user_id" min="0" class="w-full border border-slate-200 rounded px-2 py-1.5 text-sm" placeholder="Optionnel"></div>
                <div class="sm:col-span-2"><label class="block text-[11px] font-bold text-slate-600 mb-0.5">Audio briefing (URL)</label><input type="url" name="session_audio_url" class="w-full border border-slate-200 rounded px-2 py-1.5 text-sm"></div>
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
    <form method="post" action="<?= training_studio_url($cid) ?>" class="space-y-8 mb-12" id="studio-presentation-form">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="_action" value="save_course">
        <input type="hidden" name="_studio_section" value="presentation">

        <section id="studio-presentation" class="training-studio-panel scroll-mt-28 p-6 md:p-8 space-y-6 shadow-sm">
            <h2 class="text-sm font-black uppercase tracking-[0.2em] text-slate-500">Présentation côté apprenant</h2>
            <p class="text-xs text-slate-500 max-w-3xl">Apparence du parcours, visuels de couverture et consignes audio. Les textes, visibilité et règles d’inscription se gèrent dans l’onglet <a href="<?= htmlspecialchars($studioU('fiche')) ?>" class="font-semibold text-emerald-800 underline decoration-emerald-200 hover:text-emerald-950">Données &amp; inscription</a>.</p>

            <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4 space-y-3">
                <label class="inline-flex items-center gap-2 text-sm font-bold text-slate-800">
                    <input type="checkbox" name="lms_theme_enable" value="1" <?= $themeEnable ? 'checked' : '' ?>>
                    Personnaliser l’apparence du parcours pour cette formation
                </label>
                <p class="text-[11px] text-slate-600">Couleurs, typographie et forme des blocs visibles par les apprenants sur cette formation.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-1">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Couleur d’accent</label>
                        <input type="color" name="lms_theme_accent" value="<?= htmlspecialchars($themeAccent) ?>" class="h-10 w-full max-w-[8rem] cursor-pointer rounded-lg border border-slate-200 bg-white p-1">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Typographie</label>
                        <select name="lms_theme_font" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                            <?php foreach ($themeFontPresets as $fk => $_css): ?>
                            <option value="<?= htmlspecialchars($fk) ?>" <?= $themeFontKey === $fk ? 'selected' : '' ?>><?= htmlspecialchars($themeFontLabels[$fk] ?? $fk) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Arrondi des blocs</label>
                        <select name="lms_theme_radius" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                            <?php foreach ($themeRadiusPresets as $rk => $_rv): ?>
                            <option value="<?= htmlspecialchars($rk) ?>" <?= $themeRadiusKey === $rk ? 'selected' : '' ?>><?= htmlspecialchars($themeRadiusLabels[$rk] ?? $rk) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Ambiance</label>
                        <select name="lms_theme_variant" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                            <?php foreach ($themeVariantLabels as $vk => $vlab): ?>
                            <option value="<?= htmlspecialchars($vk) ?>" <?= $themeVariant === $vk ? 'selected' : '' ?>><?= htmlspecialchars($vlab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Miniature (chemin ou emplacement du fichier)</label>
                    <input type="text" name="thumbnail_path" value="<?= htmlspecialchars((string) ($course['thumbnail_path'] ?? '')) ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono text-xs" placeholder="uploads/…">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Bannière (chemin ou emplacement du fichier)</label>
                    <input type="text" name="banner_path" value="<?= htmlspecialchars((string) ($course['banner_path'] ?? '')) ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono text-xs">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 border-t border-slate-100 pt-6">
                <div class="md:col-span-2">
                    <h3 class="text-xs font-black uppercase text-slate-500 mb-2">Consignes audio (optionnel)</h3>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-slate-600 mb-1">Adresse du fichier ou du flux audio</label>
                    <input type="url" name="instruction_audio_url" value="<?= htmlspecialchars((string) ($course['instruction_audio_url'] ?? '')) ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="https://…">
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="instruction_audio_instructor_optional" value="1" <?= (($course['instruction_audio_instructor_optional'] ?? 1) == 1) ? 'checked' : '' ?>>
                    Écoute possible sans instructeur présent
                </label>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-slate-600 mb-1">Notes</label>
                    <input type="text" name="instruction_audio_notes" maxlength="500" value="<?= htmlspecialchars((string) ($course['instruction_audio_notes'] ?? '')) ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="Contexte, consignes de sécurité…">
                </div>
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <button type="submit" class="px-6 py-3 bg-slate-900 text-white text-sm font-black rounded-xl hover:bg-slate-800 shadow-md">Enregistrer la présentation</button>
                <a href="<?= htmlspecialchars($studioU('structure')) ?>" class="inline-flex items-center px-4 py-3 border border-slate-200 bg-white text-slate-800 text-sm font-bold rounded-xl hover:bg-slate-50">Aller aux modules &amp; leçons</a>
            </div>
        </section>
    </form>

    <?php else: ?>

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
            foreach ($modules as $tMod):
                $tIdx++;
                $tMid = (int) ($tMod['id'] ?? 0);
                $tLessons = $tMod['lessons'] ?? [];
                $tLc = count($tLessons);
                $tTitle = (string) ($tMod['title'] ?? '');
                $tTitleShort = function_exists('mb_strimwidth') ? mb_strimwidth($tTitle, 0, 40, '…', 'UTF-8') : (strlen($tTitle) > 40 ? substr($tTitle, 0, 37) . '…' : $tTitle);
                ?>
            <div class="studio-timeline-node" data-module-id="<?= $tMid ?>" data-timeline-node>
                <span class="studio-timeline-node__grip" title="Déplacer ce module dans le parcours" aria-hidden="true">⠿</span>
                <a href="#studio-mod-<?= $tMid ?>" class="studio-timeline-node__body">
                    <span class="studio-timeline-node__n"><?= (int) $tIdx ?></span>
                    <span class="studio-timeline-node__title"><?= htmlspecialchars($tTitleShort !== '' ? $tTitleShort : 'Module') ?></span>
                    <span class="studio-timeline-node__meta"><?= $tLc === 0 ? 'Sans leçon' : ((int) $tLc . ' leçon' . ($tLc > 1 ? 's' : '')) ?></span>
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
            <div class="studio-sort-lesson-card border border-slate-100 rounded-lg p-3 bg-slate-50/50 space-y-3" data-lesson-id="<?= (int) $lid ?>">
                <div class="flex flex-wrap justify-between gap-2 items-center">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="studio-lesson-drag-handle cursor-grab text-slate-400 hover:text-slate-600 select-none text-base leading-none" title="Glisser pour déplacer la leçon" aria-hidden="true">⠿</span>
                        <span class="text-xs font-semibold text-slate-700 truncate">Leçon <?= (int) $li ?></span>
                    </div>
                </div>
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
                    <?php
                    $studioRes = $les['studio_resources'] ?? [];
                    $lessonResAnchor = 'lesson-res-' . (int) $lid;
                    ?>
                    <div class="rounded-lg border border-sky-100 bg-sky-50/40 p-3 space-y-2" id="<?= htmlspecialchars($lessonResAnchor) ?>">
                        <p class="text-[11px] font-black uppercase tracking-wide text-sky-800">Ressources pour l’apprenant</p>
                        <p class="text-[10px] text-sky-900/80">Fichiers ou liens listés sur la page de la leçon (en complément du contenu principal).</p>
                        <?php if ($studioRes !== []): ?>
                        <ul class="text-sm space-y-2">
                            <?php foreach ($studioRes as $sr):
                                $srid = (int) ($sr['id'] ?? 0);
                                $srt = (string) ($sr['resource_type'] ?? 'link');
                                $srtl = $resourceTypeLabels[$srt] ?? $srt;
                            ?>
                            <li class="flex flex-wrap justify-between gap-2 items-start border-b border-sky-100/80 pb-2">
                                <span class="text-slate-800 min-w-0"><span class="font-semibold"><?= htmlspecialchars((string) ($sr['title'] ?? '')) ?></span> <span class="text-slate-500">(<?= htmlspecialchars($srtl) ?>)</span></span>
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
                        <p class="text-xs text-slate-600">Aucune ressource pour l’instant.</p>
                        <?php endif; ?>
                        <form method="post" action="<?= training_studio_url($cid) ?>" class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-2 border-t border-sky-100">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="hidden" name="_action" value="add_lesson_resource">
                            <input type="hidden" name="lesson_id" value="<?= (int) $lid ?>">
                            <div class="sm:col-span-2">
                                <label class="block text-[10px] font-bold text-slate-600 mb-0.5">Titre affiché</label>
                                <input type="text" name="resource_title" required maxlength="255" class="w-full border border-slate-200 rounded px-2 py-1.5 text-sm" placeholder="Ex. Grille d’évaluation, Vidéo complémentaire">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 mb-0.5">Type</label>
                                <select name="resource_type" class="w-full border border-slate-200 rounded px-2 py-1.5 text-sm">
                                    <?php foreach ($resourceTypeLabels as $rk => $rlab): ?>
                                    <option value="<?= htmlspecialchars($rk) ?>"><?= htmlspecialchars($rlab) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 mb-0.5">Adresse web (optionnel)</label>
                                <input type="url" name="resource_external_url" class="w-full border border-slate-200 rounded px-2 py-1.5 text-sm" placeholder="https://…">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-[10px] font-bold text-slate-600 mb-0.5">Fichier sur le serveur (optionnel)</label>
                                <input type="text" name="resource_file_path" maxlength="255" class="w-full border border-slate-200 rounded px-2 py-1.5 text-sm font-mono text-xs" placeholder="ex. uploads/…">
                                <p class="text-[10px] text-slate-500 mt-0.5">Chemin relatif à l’application ou absolu. Les apprenants inscrits téléchargent via un lien sécurisé.</p>
                            </div>
                            <div class="sm:col-span-2">
                                <button type="submit" class="px-3 py-1.5 bg-sky-700 text-white text-xs font-bold rounded-lg hover:bg-sky-800">Ajouter la ressource</button>
                            </div>
                        </form>
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

    <?php if (in_array($trainingStudioSection, ['fiche', 'structure'], true)): ?>
    <script src="<?= url('assets/js/training_studio_objectives.js') ?>" defer></script>
    <?php endif; ?>
    <?php if ($trainingStudioSection === 'structure'): ?>
    <link rel="stylesheet" href="<?= url('assets/css/training_canvas.css') ?>">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js" defer></script>
    <script src="<?= url('assets/js/training_studio_sortable.js') ?>" defer></script>
    <script src="<?= url('assets/js/training_canvas_editor.js') ?>" defer></script>
    <script src="<?= url('assets/js/training_studio_interactive_editors.js') ?>" defer></script>
    <?php endif; ?>
</div>
