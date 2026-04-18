<?php
declare(strict_types=1);
/** @var string $trainingAdminNav */
$active = $trainingAdminNav ?? 'dashboard';
$is = fn (string $k): string => $active === $k ? ' is-active' : '';
$coursesNavActive = ($active === 'courses' || $active === 'showcase') ? ' is-active' : '';
$certNavActive = ($active === 'certificates' || $active === 'certificates_gabarit') ? ' is-active' : '';
$gateNav = \App\Core\Gate::getInstance();
$trainingCmdCanEditContent = $gateNav->allows('admin.access') || $gateNav->allows('training.manage')
    || $gateNav->allows('training.create') || $gateNav->allows('training.update')
    || $gateNav->allows('training.delete') || $gateNav->allows('training.publish');
?>
<link rel="stylesheet" href="<?= htmlspecialchars(url('assets/css/training_admin_command.css')) ?>">
<div class="training-cmd relative overflow-hidden rounded-2xl border border-slate-200/90 shadow-md shadow-slate-900/[0.04] mb-10">
    <div class="training-cmd__grain" aria-hidden="true"></div>
    <div class="training-cmd-shell relative z-[1]">
        <div class="training-cmd-toolbar-wrap">
            <p class="training-cmd-toolbar-eyebrow">Pilotage des formations</p>
            <nav class="training-cmd-toolbar" aria-label="Sections pilotage des formations">
                <a href="<?= htmlspecialchars(training_lms_admin_url()) ?>" class="<?= trim($is('dashboard')) ?>">Vue d’ensemble</a>
                <a href="<?= htmlspecialchars(training_lms_admin_url('charte-rh')) ?>" class="<?= trim($is('charter')) ?>">Charte RH</a>
                <a href="<?= htmlspecialchars(training_lms_admin_url('courses')) ?>" class="<?= trim($coursesNavActive) ?>">Catalogue</a>
                <a href="<?= htmlspecialchars(training_studio_url()) ?>" class="<?= trim($is('studio')) ?>">Studio LMS</a>
                <a href="<?= htmlspecialchars(training_lms_admin_url('enrollments')) ?>" class="<?= trim($is('enrollments')) ?>">Assignations</a>
                <a href="<?= htmlspecialchars(training_lms_admin_url('reports')) ?>" class="<?= trim($is('reports')) ?>">Rapports</a>
                <a href="<?= htmlspecialchars(training_lms_admin_url('feedback')) ?>" class="<?= trim($is('lesson_feedback')) ?>">Feedback leçons</a>
                <a href="<?= htmlspecialchars(training_lms_admin_url('certificates')) ?>" class="<?= trim($certNavActive) ?>">Certificats</a>
                <?php if ($trainingCmdCanEditContent): ?>
                <a href="<?= htmlspecialchars(training_lms_admin_url('certificates/gabarit')) ?>" class="<?= trim($is('certificates_gabarit')) ?>">Gabarit PDF</a>
                <?php endif; ?>
                <a href="<?= htmlspecialchars(training_lms_admin_url('audit')) ?>" class="<?= trim($is('audit')) ?>">Audit</a>
                <a href="<?= url('formations') ?>" target="_blank" rel="noopener" class="training-cmd-toolbar__ext">Catalogue public</a>
            </nav>
        </div>
        <div class="training-cmd-main min-w-0">
            <div class="training-cmd-main-inner space-y-8">
