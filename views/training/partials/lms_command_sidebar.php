<?php
declare(strict_types=1);
/**
 * Aside du module Formations (catalogue membre et pilotage encadrement).
 * Rendu commun : views/partials/lms_nav_aside.php.
 *
 * @var string $lmsBase
 * @var int $totalModules
 * @var string $activeNav one of overview|catalogue|mine|sessions|qualifications|competences|publications|docs_html|staff_hub
 * @var bool $lmsSidebarShowPilotageLinks affiche la section « Encadrement » aux membres qui y ont accès
 * @var string $lmsSidebarContext 'catalogue' | 'staff'
 */
$lmsBase = $lmsBase ?? url('');
$totalModules = (int) ($totalModules ?? 0);
$activeNav = (string) ($activeNav ?? 'overview');
$lmsSidebarContext = (string) ($lmsSidebarContext ?? 'catalogue');
$isStaffSidebar = $lmsSidebarContext === 'staff';

$staffAccess = false;
try {
    $staffAccess = \App\Support\TrainingLmsStaffAccess::allows(\App\Core\Gate::getInstance());
} catch (\Throwable) {
    $staffAccess = false;
}

$modulesLabel = $totalModules . ' module' . ($totalModules > 1 ? 's' : '');

if ($isStaffSidebar) {
    // Pilotage : la rubrique active se déduit de l’adresse (liens réels, sans ancre).
    $lmsNav = [
        'id' => 'formation-staff',
        'tone' => 'emerald',
        'kicker' => 'Athena · Formations',
        'title' => 'Pilotage formation',
        'lead' => 'Contenus, inscriptions, attestations et compétences de la communauté.',
        'aria' => 'Navigation du pilotage des formations',
        'sections' => [
            ['label' => 'Pilotage', 'items' => [
                ['href' => training_lms_admin_url(), 'label' => 'Vue d’ensemble', 'hint' => 'Indicateurs et priorités', 'icon' => 'overview'],
                ['href' => training_lms_admin_url('recherche'), 'label' => 'Recherche', 'hint' => 'Formations, documents, attestations', 'icon' => 'search'],
            ]],
            ['label' => 'Contenus', 'items' => [
                ['href' => training_studio_url(), 'label' => 'Studio', 'hint' => 'Créer et structurer les parcours', 'icon' => 'studio'],
                ['href' => training_lms_admin_url('courses'), 'label' => 'Catalogue d’édition', 'hint' => 'Publier et classer', 'icon' => 'catalogue'],
                ['href' => training_lms_admin_url('publications'), 'label' => 'Publications', 'hint' => 'Documents de référence', 'icon' => 'docs'],
                ['href' => training_lms_admin_url('pages-html'), 'label' => 'Pages pédagogiques', 'hint' => 'Manuels et pages riches', 'icon' => 'html'],
            ]],
            ['label' => 'Suivi des apprenants', 'items' => [
                ['href' => training_lms_admin_url('enrollments'), 'label' => 'Inscriptions', 'hint' => 'Approuver et suivre', 'icon' => 'enroll'],
                ['href' => training_lms_admin_url('groupes'), 'label' => 'Groupes', 'hint' => 'Cohortes sur un même parcours', 'icon' => 'groups'],
                ['href' => training_lms_admin_url('reports'), 'label' => 'Rapports', 'hint' => 'Avancement et résultats', 'icon' => 'reports'],
                ['href' => training_lms_admin_url('feedback'), 'label' => 'Retours post-leçon', 'hint' => 'Avis des apprenants', 'icon' => 'feedback'],
                ['href' => training_lms_admin_url('audit'), 'label' => 'Journal pédagogique', 'hint' => 'Actions d’encadrement', 'icon' => 'audit'],
            ]],
            ['label' => 'Attestations', 'items' => [
                ['href' => training_lms_admin_url('certificates'), 'label' => 'Attestations', 'hint' => 'Délivrées et à émettre', 'icon' => 'cert'],
                ['href' => training_lms_admin_url('certificates/gabarit'), 'label' => 'Mise en page', 'hint' => 'Apparence des PDF', 'icon' => 'gabarit'],
                ['href' => training_lms_admin_url('charte-rh'), 'label' => 'Charte RH', 'hint' => 'Cadre applicable aux parcours', 'icon' => 'charte'],
            ]],
            ['label' => 'Compétences', 'items' => [
                ['href' => training_lms_admin_url('competences/commandement'), 'label' => 'Pilotage des compétences', 'hint' => 'Progression et jalons', 'icon' => 'star'],
                ['href' => training_lms_admin_url('competences/modules'), 'label' => 'Modules ALPHA → DELTA', 'hint' => 'Référentiel des modules', 'icon' => 'mine'],
                ['href' => training_lms_admin_url('competences/validation'), 'label' => 'Validations', 'hint' => 'À valider par l’encadrement', 'icon' => 'cert'],
                ['href' => training_lms_admin_url('competences/sections'), 'label' => 'Sections', 'hint' => 'Organisation par section', 'icon' => 'groups'],
            ]],
        ],
        'cards' => [
            ['kicker' => 'Catalogue public', 'label' => $modulesLabel, 'href' => $lmsBase . '/formations', 'text' => 'Ce que voient les membres.'],
        ],
        'foot' => ['href' => url('dashboard'), 'label' => 'Tableau de bord', 'sub' => 'Retour Athena'],
    ];
} else {
    $is = static fn (string ...$ids): bool => in_array($activeNav, $ids, true);
    $sections = [
        ['label' => 'Apprendre', 'items' => [
            ['href' => $lmsBase . '/formations#overview', 'label' => 'Vue d’ensemble', 'hint' => 'Repères et continuité', 'icon' => 'overview', 'active' => $is('overview')],
            ['href' => $lmsBase . '/formations#catalogue', 'label' => 'Catalogue', 'hint' => 'Parcours ouverts à l’inscription', 'icon' => 'catalogue', 'badge' => $totalModules > 0 ? $totalModules : null, 'active' => $is('catalogue')],
            ['href' => $lmsBase . '/formations/mes-formations', 'label' => 'Mes formations', 'hint' => 'Inscriptions et progression', 'icon' => 'mine', 'active' => $is('mine')],
        ]],
        ['label' => 'Planning et acquis', 'items' => [
            ['href' => $lmsBase . '/formations/sessions', 'label' => 'Sessions', 'hint' => 'Rendez-vous planifiés', 'icon' => 'calendar', 'active' => $is('sessions')],
            ['href' => $lmsBase . '/formations/sessions#qualifications', 'label' => 'Qualifications', 'hint' => 'Préparation et attestations', 'icon' => 'cert', 'active' => $is('qualifications')],
            ['href' => $lmsBase . '/formations/competences', 'label' => 'Mes compétences', 'hint' => 'Parcours ALPHA → DELTA', 'icon' => 'star', 'active' => $is('competences')],
        ]],
    ];
    if ($staffAccess && (!empty($lmsSidebarShowPilotageLinks) || $is('publications', 'docs_html'))) {
        $sections[] = ['label' => 'Encadrement', 'items' => [
            ['href' => training_lms_admin_url(), 'label' => 'Pilotage formation', 'hint' => 'Indicateurs et priorités', 'icon' => 'overview', 'active' => $is('staff_hub')],
            ['href' => training_studio_url(), 'label' => 'Studio', 'hint' => 'Créer et éditer les parcours', 'icon' => 'studio', 'active' => false],
            ['href' => training_lms_admin_url('enrollments'), 'label' => 'Inscriptions', 'hint' => 'Approuver et suivre', 'icon' => 'enroll', 'active' => false],
            ['href' => training_lms_admin_url('publications'), 'label' => 'Publications', 'hint' => 'Documents de référence', 'icon' => 'docs', 'active' => $is('publications')],
            ['href' => training_lms_admin_url('pages-html'), 'label' => 'Pages pédagogiques', 'hint' => 'Supports de parcours', 'icon' => 'html', 'active' => $is('docs_html')],
        ]];
    }
    $lmsNav = [
        'id' => 'formation',
        'tone' => 'emerald',
        'kicker' => 'Athena · Formations',
        'title' => 'Formations',
        'lead' => 'Catalogue, parcours de qualification, sessions planifiées et suivi de vos acquis.',
        'aria' => 'Navigation des formations',
        'sections' => $sections,
        'cards' => [
            ['kicker' => 'Catalogue', 'label' => $modulesLabel, 'href' => $lmsBase . '/formations#catalogue', 'text' => 'Formations et parcours disponibles.'],
        ],
        'foot' => ['href' => url('dashboard'), 'label' => 'Tableau de bord', 'sub' => 'Retour Athena'],
    ];
}

require base_path('views/partials/lms_nav_aside.php');
