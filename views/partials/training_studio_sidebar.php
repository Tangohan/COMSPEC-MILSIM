<?php
declare(strict_types=1);

/** @var string $trainingStudioMode index|edit */
/** @var int $trainingStudioCourseCount */
/** @var array|null $trainingStudioCourse */

$mode = $trainingStudioMode ?? 'index';
$count = (int) ($trainingStudioCourseCount ?? 0);
$course = $trainingStudioCourse ?? null;
$cid = $course ? (int) ($course['id'] ?? 0) : 0;

$visLabels = [
    'draft' => 'Brouillon',
    'private' => 'Privé',
    'published' => 'Publié',
    'archived' => 'Archivé',
];
$vis = $course ? ($visLabels[$course['visibility'] ?? ''] ?? (string) ($course['visibility'] ?? '')) : '';
?>
<aside class="training-studio-sidebar training-studio-sidebar--drawer"
       :class="{ 'is-open': navOpen }"
       id="training-studio-sidebar"
       aria-label="Navigation Studio formation">
    <button type="button"
            class="training-studio-close-drawer"
            @click="navOpen = false"
            aria-label="Fermer le menu">×</button>

    <div class="training-studio-sidebar__brand">
        <p class="training-studio-sidebar__kicker">Studio formation</p>
        <p class="training-studio-sidebar__title">Espace auteur</p>
        <p class="training-studio-sidebar__sub">Concevoir les parcours, structurer les modules et publier dans le catalogue des formations.</p>
    </div>

    <nav class="training-studio-nav" aria-label="Sections studio">
        <p class="training-studio-nav__label">Espace créateur</p>
        <a href="<?= url('admin/training/studio') ?>"
           class="<?= $mode === 'index' ? 'is-active' : '' ?>"
           @click="navOpen = false">
            <span>Tableau des formations</span>
            <span class="ts-meta"><?= $count ?></span>
        </a>
        <a href="<?= url('admin/training') ?>"
           @click="navOpen = false">
            <span>Tableau de bord admin</span>
            <span class="ts-meta">·</span>
        </a>
        <a href="<?= url('admin/training/studio/versions') ?>"
           @click="navOpen = false">
            <span>Journal &amp; versions</span>
            <span class="ts-meta">v</span>
        </a>
        <?php if ($mode === 'edit' && $course && $cid > 0): ?>
        <p class="training-studio-nav__label mt-4">Formation en cours</p>
        <a href="<?= url('admin/training/studio') ?>"
           @click="navOpen = false">
            <span>← Toutes les formations</span>
        </a>
        <a href="#studio-fiche"
           class="pl-3 border-l-2 border-emerald-500/40"
           @click="navOpen = false">
            <span>Fiche &amp; métadonnées</span>
        </a>
        <a href="#studio-engagement"
           class="pl-3 border-l-2 border-emerald-500/40"
           @click="navOpen = false">
            <span>Politique &amp; engagement</span>
        </a>
        <a href="#studio-sessions-qa"
           class="pl-3 border-l-2 border-emerald-500/40"
           @click="navOpen = false">
            <span>Créneaux &amp; questions</span>
        </a>
        <a href="#studio-structure"
           class="pl-3 border-l-2 border-emerald-500/40"
           @click="navOpen = false">
            <span>Modules &amp; leçons</span>
        </a>
        <a href="#studio-parcours-timeline"
           class="pl-3 border-l-2 border-violet-500/40"
           @click="navOpen = false">
            <span>Frise du parcours</span>
        </a>
        <a href="<?= url('admin/training/studio/' . $cid . '/preview') ?>"
           @click="navOpen = false">
            <span>Aperçu caviardé</span>
            <span class="ts-meta">◆</span>
        </a>
        <a href="<?= url('formations/' . rawurlencode((string) ($course['slug'] ?? ''))) ?>"
           target="_blank" rel="noopener"
           @click="navOpen = false">
            <span>Aperçu public</span>
            <span class="ts-meta">↗</span>
        </a>
        <a href="<?= url('admin/training/courses/' . $cid . '/showcase') ?>"
           @click="navOpen = false">
            <span>Vitrine</span>
        </a>
        <a href="<?= url('admin/training/enrollments?course_id=' . $cid) ?>"
           @click="navOpen = false">
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
    <?php elseif ($course && $cid > 0): ?>
    <div class="training-studio-sidebar__card">
        <dl>
            <dt><?= htmlspecialchars(mb_strimwidth((string) ($course['title'] ?? ''), 0, 42, '…')) ?></dt>
            <dd class="text-xs font-semibold normal-case tracking-normal text-emerald-300/95"><?= htmlspecialchars($vis) ?>
                · <?= (int) ($course['module_count'] ?? 0) ?> module(s)
                · <?= (int) ($course['lesson_count'] ?? 0) ?> leçon(s)</dd>
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
