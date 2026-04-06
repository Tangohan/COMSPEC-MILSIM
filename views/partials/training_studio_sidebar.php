<?php

declare(strict_types=1);



/** @var string $trainingStudioMode index|edit */

/** @var int $trainingStudioCourseCount */

/** @var array|null $trainingStudioCourse */



$mode = $trainingStudioMode ?? 'index';

$count = (int) ($trainingStudioCourseCount ?? 0);

/** Résumé formation (id, titre, compteurs…) — ne pas utiliser le nom $course : il est réservé au tableau complet dans la vue principale. */
$studioSidebarCourse = $trainingStudioCourse ?? null;

$cid = $studioSidebarCourse ? (int) ($studioSidebarCourse['id'] ?? 0) : 0;



$visLabels = [

    'draft' => 'Brouillon',

    'private' => 'Privé',

    'published' => 'Publié',

    'archived' => 'Archivé',

];

$vis = $studioSidebarCourse ? ($visLabels[$studioSidebarCourse['visibility'] ?? ''] ?? (string) ($studioSidebarCourse['visibility'] ?? '')) : '';

$gateStudio = \App\Core\Gate::getInstance();
$studioCanEditVitrine = $gateStudio->allows('admin.access') || $gateStudio->allows('training.manage')
    || $gateStudio->allows('training.create') || $gateStudio->allows('training.update')
    || $gateStudio->allows('training.delete') || $gateStudio->allows('training.publish');

?>

<aside class="training-studio-sidebar"

       id="training-studio-sidebar"

       aria-label="Navigation Studio formation">



    <div class="training-studio-sidebar__brand">

        <p class="training-studio-sidebar__kicker">Studio formation</p>

        <p class="training-studio-sidebar__title">Espace auteur</p>

        <p class="training-studio-sidebar__sub">Concevoir les parcours, structurer les modules et publier dans le catalogue des formations.</p>

    </div>



    <nav class="training-studio-nav" aria-label="Sections studio">

        <p class="training-studio-nav__label">Espace créateur</p>

        <a href="<?= training_studio_url() ?>"

           class="<?= $mode === 'index' ? 'is-active' : '' ?>">

            <span>Tableau des formations</span>

            <span class="ts-meta"><?= $count ?></span>

        </a>

        <a href="<?= htmlspecialchars(training_lms_admin_url()) ?>">

            <span>Tableau de bord admin</span>

            <span class="ts-meta">·</span>

        </a>

        <a href="<?= training_studio_url('versions') ?>">

            <span>Journal &amp; versions</span>

            <span class="ts-meta">v</span>

        </a>

        <a href="<?= htmlspecialchars(url(training_studio_path() . '/echange/importer')) ?>">

            <span>Importer une formation</span>

            <span class="ts-meta">↓</span>

        </a>

        <?php if ($mode === 'edit' && $studioSidebarCourse && $cid > 0): ?>

        <p class="training-studio-nav__label mt-4">Formation en cours</p>

        <a href="<?= training_studio_url() ?>">

            <span>← Toutes les formations</span>

        </a>

        <a href="<?= htmlspecialchars(training_studio_url($cid . '/fiche#studio-fiche')) ?>"

           class="pl-3 border-l-2 border-emerald-500/40">

            <span>Fiche &amp; métadonnées</span>

        </a>

        <a href="<?= htmlspecialchars(training_studio_url($cid . '/presentation#studio-presentation')) ?>"

           class="pl-3 border-l-2 border-emerald-500/40">

            <span>Présentation apprenant</span>

        </a>

        <a href="<?= htmlspecialchars(training_studio_url($cid . '/fiche#studio-engagement')) ?>"

           class="pl-3 border-l-2 border-emerald-500/40">

            <span>Politique &amp; engagement</span>

        </a>

        <a href="<?= htmlspecialchars(training_studio_url($cid . '/fiche#studio-sessions-qa')) ?>"

           class="pl-3 border-l-2 border-emerald-500/40">

            <span>Créneaux &amp; questions</span>

        </a>

        <a href="<?= htmlspecialchars(training_studio_url($cid . '/structure#studio-ressources-aide')) ?>"

           class="pl-3 border-l-2 border-emerald-500/40">

            <span>Modules, leçons &amp; ressources</span>

        </a>

        <a href="<?= htmlspecialchars(training_studio_url($cid . '/structure#studio-parcours-timeline')) ?>"

           class="pl-3 border-l-2 border-violet-500/40">

            <span>Frise du parcours</span>

        </a>

        <a href="<?= training_studio_url($cid . '/preview') ?>">

            <span>Aperçu caviardé</span>

            <span class="ts-meta">◆</span>

        </a>

        <a href="<?= htmlspecialchars(training_studio_url($cid . '/echange')) ?>">

            <span>Export &amp; import</span>

            <span class="ts-meta">⇄</span>

        </a>

        <a href="<?= url('formations/' . rawurlencode((string) ($studioSidebarCourse['slug'] ?? ''))) ?>"

           target="_blank" rel="noopener">

            <span>Aperçu public</span>

            <span class="ts-meta">↗</span>

        </a>

        <?php if ($studioCanEditVitrine): ?>
        <a href="<?= htmlspecialchars(training_lms_admin_url('courses/' . $cid . '/showcase')) ?>">

            <span>Vitrine</span>

        </a>
        <?php endif; ?>

        <a href="<?= htmlspecialchars(training_lms_admin_url('enrollments') . '?course_id=' . (int) $cid) ?>">

            <span>Assignations</span>

        </a>

        <?php endif; ?>

    </nav>



    <?php if ($mode === 'index'): ?>

    <div class="training-studio-sidebar__card">

        <dl>

            <dt>Formations</dt>

            <dd><?= (int) $count ?> dans cet espace</dd>

        </dl>

    </div>

    <?php elseif ($studioSidebarCourse && $cid > 0): ?>

    <div class="training-studio-sidebar__card">

        <dl>

            <dt><?= htmlspecialchars(mb_strimwidth((string) ($studioSidebarCourse['title'] ?? ''), 0, 42, '…')) ?></dt>

            <dd class="text-xs font-semibold normal-case tracking-normal text-emerald-300/95"><?= htmlspecialchars($vis) ?>

                · <?= (int) ($studioSidebarCourse['module_count'] ?? 0) ?> module(s)

                · <?= (int) ($studioSidebarCourse['lesson_count'] ?? 0) ?> leçon(s)</dd>

        </dl>

    </div>

    <?php endif; ?>



    <div class="training-studio-sidebar__footer mt-auto text-[11px] text-slate-500">

        Athena · Studio LMS

        <?php if (function_exists('lms_platform_version')): ?>

        <span class="block mt-1 font-semibold text-slate-600">v<?= htmlspecialchars(lms_platform_version()) ?></span>

        <?php endif; ?>

    </div>

</aside>

