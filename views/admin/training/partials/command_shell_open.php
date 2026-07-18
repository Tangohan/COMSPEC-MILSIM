<?php
declare(strict_types=1);
/** @var string $trainingAdminNav */
$active = $trainingAdminNav ?? 'dashboard';
$is = fn (string $k): string => $active === $k ? ' is-active' : '';
$coursesNavActive = ($active === 'courses' || $active === 'showcase') ? ' is-active' : '';
$certNavActive = ($active === 'certificates') ? ' is-active' : '';
$gateNav = \App\Core\Gate::getInstance();
$trainingCmdCanEditContent = $gateNav->allows('admin.access') || $gateNav->allows('training.manage')
    || $gateNav->allows('training.create') || $gateNav->allows('training.update')
    || $gateNav->allows('training.delete') || $gateNav->allows('training.publish');

$navGroups = [
    [
        'label' => 'Contenu',
        'items' => [
            ['href' => training_lms_admin_url(), 'label' => 'Vue d’ensemble', 'class' => trim($is('dashboard'))],
            ['href' => training_lms_admin_url('courses'), 'label' => 'Catalogue', 'class' => trim($coursesNavActive)],
            ['href' => training_studio_url(), 'label' => 'Studio', 'class' => trim($is('studio'))],
            ['href' => training_lms_admin_url('pages-html'), 'label' => 'Pages pédagogiques', 'class' => trim($is('custom_pages'))],
        ],
    ],
    [
        'label' => 'Suivi',
        'items' => [
            ['href' => training_lms_admin_url('enrollments'), 'label' => 'Inscriptions', 'class' => trim($is('enrollments'))],
            ['href' => training_lms_admin_url('reports'), 'label' => 'Rapports', 'class' => trim($is('reports'))],
            ['href' => training_lms_admin_url('feedback'), 'label' => 'Retours', 'class' => trim($is('lesson_feedback'))],
            ['href' => training_lms_admin_url('audit'), 'label' => 'Journal', 'class' => trim($is('audit'))],
        ],
    ],
    [
        'label' => 'Documents',
        'items' => array_values(array_filter([
            ['href' => training_lms_admin_url('charte-rh'), 'label' => 'Charte RH', 'class' => trim($is('charter'))],
            ['href' => training_lms_admin_url('certificates'), 'label' => 'Attestations', 'class' => trim($certNavActive)],
            $trainingCmdCanEditContent
                ? ['href' => training_lms_admin_url('certificates/gabarit'), 'label' => 'Gabarit', 'class' => trim($is('certificates_gabarit'))]
                : null,
        ])),
    ],
];
?>
<link rel="stylesheet" href="<?= htmlspecialchars(url('assets/css/training_admin_command.css')) ?>">
<div class="training-cmd relative overflow-hidden rounded-2xl border border-slate-200/90 shadow-md shadow-slate-900/[0.04] mb-10">
    <div class="training-cmd__grain" aria-hidden="true"></div>
    <div class="training-cmd-shell relative z-[1]">
        <div class="training-cmd-toolbar-wrap">
            <div class="training-cmd-toolbar-top">
                <p class="training-cmd-toolbar-eyebrow">Pilotage des formations</p>
                <a href="<?= url('formations') ?>" target="_blank" rel="noopener" class="training-cmd-toolbar__ext">Catalogue public</a>
            </div>
            <nav class="training-cmd-toolbar" aria-label="Sections pilotage des formations">
                <?php foreach ($navGroups as $group): ?>
                <div class="training-cmd-toolbar__group">
                    <span class="training-cmd-toolbar__group-label"><?= htmlspecialchars((string) $group['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    <div class="training-cmd-toolbar__links">
                        <?php foreach ($group['items'] as $item): ?>
                        <a href="<?= htmlspecialchars((string) $item['href'], ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars((string) ($item['class'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8') ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </nav>
        </div>
        <div class="training-cmd-main min-w-0">
            <div class="training-cmd-main-inner space-y-8">
