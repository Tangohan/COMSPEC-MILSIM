<?php
declare(strict_types=1);
/** @var string $trainingAdminNav */
$active = $trainingAdminNav ?? 'dashboard';
$is = fn (string $k): string => $active === $k ? ' is-active' : '';
$coursesNavActive = ($active === 'courses' || $active === 'showcase') ? ' is-active' : '';
?>
<link rel="stylesheet" href="<?= htmlspecialchars(url('assets/css/training_admin_command.css')) ?>">
<div class="training-cmd relative overflow-hidden rounded-2xl border border-slate-200/90 shadow-xl shadow-slate-900/[0.06] mb-10">
    <div class="training-cmd__grain" aria-hidden="true"></div>
    <div class="training-cmd-layout relative z-[1]">
        <aside class="training-cmd-aside" aria-label="Navigation formations">
            <div class="training-cmd-aside__brand">
                <p class="training-cmd-aside__kicker">Athena · Admin</p>
                <p class="training-cmd-aside__title">Training Command</p>
                <p class="training-cmd-aside__sub">Catalogue LMS, assignations, conformité et suivi des parcours pour votre communauté.</p>
            </div>
            <nav class="training-cmd-nav">
                <a href="<?= htmlspecialchars(url('admin/training')) ?>" class="<?= trim($is('dashboard')) ?>">
                    <span>Vue d’ensemble</span>
                    <span class="tc-nav-meta">01</span>
                </a>
                <a href="<?= htmlspecialchars(url('admin/training/courses')) ?>" class="<?= trim($coursesNavActive) ?>">
                    <span>Catalogue</span>
                    <span class="tc-nav-meta">02</span>
                </a>
                <a href="<?= htmlspecialchars(url('admin/training/studio')) ?>" class="<?= trim($is('studio')) ?>">
                    <span>Studio LMS</span>
                    <span class="tc-nav-meta">03</span>
                </a>
                <a href="<?= htmlspecialchars(url('admin/training/enrollments')) ?>" class="<?= trim($is('enrollments')) ?>">
                    <span>Assignations</span>
                    <span class="tc-nav-meta">04</span>
                </a>
                <a href="<?= htmlspecialchars(url('admin/training/reports')) ?>" class="<?= trim($is('reports')) ?>">
                    <span>Rapports</span>
                    <span class="tc-nav-meta">05</span>
                </a>
                <a href="<?= htmlspecialchars(url('admin/training/certificates')) ?>" class="<?= trim($is('certificates')) ?>">
                    <span>Certificats</span>
                    <span class="tc-nav-meta">06</span>
                </a>
                <a href="<?= htmlspecialchars(url('admin/training/audit')) ?>" class="<?= trim($is('audit')) ?>">
                    <span>Audit</span>
                    <span class="tc-nav-meta">07</span>
                </a>
            </nav>
            <div class="training-cmd-aside__foot">
                LMS — interface administration
            </div>
        </aside>
        <div class="training-cmd-main min-w-0">
            <div class="training-cmd-main-inner space-y-8">
