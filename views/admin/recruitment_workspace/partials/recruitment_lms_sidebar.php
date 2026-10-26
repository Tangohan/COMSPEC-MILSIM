<?php
declare(strict_types=1);
$lmsBase = $lmsBase ?? url('');
$active = (string) ($recruitmentAdminNav ?? '');
if ($active === '') {
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    if (str_contains($requestUri, '/back-office/recruitments/messages-prefaits')) {
        $active = 'messages';
    } elseif (str_contains($requestUri, '/back-office/recruitments/codes-invitation')) {
        $active = 'invite_codes';
    } elseif (str_contains($requestUri, '/back-office/recruitments/discord-questions')) {
        $active = 'discord';
    } elseif (str_contains($requestUri, '/back-office/recruitments/settings')) {
        $active = 'sla';
    } elseif (str_contains($requestUri, '/back-office/recruitment/reference-format')) {
        $active = 'reference';
    } elseif (str_contains($requestUri, '/back-office/recruitment/offers')) {
        $active = 'offers';
    } elseif (str_contains($requestUri, '/back-office/ressources/recrutement/analyses')) {
        $active = 'analytics';
    } elseif (str_contains($requestUri, '/back-office/ressources/recrutement')) {
        $active = 'dashboard';
    } elseif (str_contains($requestUri, '/back-office/recruitments/equipe')) {
        $active = 'teamwall';
    } elseif (str_contains($requestUri, '/back-office/recruitments')) {
        $active = 'queue';
    }
}
$gateNav = \App\Core\Gate::getInstance();
$canRecOffers = $gateNav->allows('organization.recruitment.openings.manage') || $gateNav->allows('organization.recruitment.manage');
$canStructureHub = $gateNav->allows('organization.orbat.view')
    || $gateNav->allows('organization.orbat.manage')
    || $gateNav->allows('admin.organization')
    || $gateNav->allows('admin.access')
    || $gateNav->allows('site.support');
$rwBase = function_exists('recruitment_workspace_url') ? recruitment_workspace_url() : url('back-office/ressources/recrutement');
$rwAnalyses = function_exists('recruitment_workspace_url') ? recruitment_workspace_url('analyses') : url('back-office/ressources/recrutement/analyses');
$counts = is_array($recruitmentSidebarCounts ?? null) ? $recruitmentSidebarCounts : [];
$nSubmitted = (int) ($counts['submitted'] ?? 0);
$nTotal = $counts !== [] ? array_sum($counts) : 0;
$nTreated = max(0, $nTotal - $nSubmitted);
$is = static fn (string $id): bool => $id === $active;

$sections = [
    ['label' => 'Dossiers', 'items' => [
        ['href' => $rwBase, 'label' => 'Vue d’ensemble', 'hint' => 'Pilotage du bureau', 'icon' => 'overview', 'active' => $is('dashboard')],
        ['href' => url('back-office/recruitments'), 'label' => 'File des dossiers', 'hint' => 'Candidatures à instruire', 'icon' => 'inbox', 'badge' => $nSubmitted > 0 ? $nSubmitted : null, 'active' => $is('queue')],
        ['href' => url('back-office/recruitments/equipe'), 'label' => 'Fil recruteurs', 'hint' => 'Coordination de l’équipe', 'icon' => 'wall', 'active' => $is('teamwall')],
        ['href' => $rwAnalyses, 'label' => 'Analyses', 'hint' => 'Délais, conversions, KPI', 'icon' => 'chart', 'active' => $is('analytics')],
    ]],
];
if ($canRecOffers) {
    $sections[] = ['label' => 'Offres', 'items' => [
        ['href' => url('back-office/recruitment/offers'), 'label' => 'Offres', 'hint' => 'Avis d’ouverture publiés', 'icon' => 'offer', 'active' => $is('offers')],
        ['href' => url('back-office/recruitment/reference-format'), 'label' => 'Références des offres', 'hint' => 'Format de numérotation', 'icon' => 'tag', 'active' => $is('reference')],
    ]];
}
$sections[] = ['label' => 'Réglages', 'items' => [
    ['href' => url('back-office/recruitments/settings'), 'label' => 'Délais (SLA)', 'hint' => 'Échéances d’instruction', 'icon' => 'clock', 'active' => $is('sla')],
    ['href' => url('back-office/recruitments/messages-prefaits'), 'label' => 'Messages préfaits', 'hint' => 'Modèles de réponse', 'icon' => 'template', 'active' => $is('messages')],
    ['href' => url('back-office/recruitments/discord-questions'), 'label' => 'Questions Discord', 'hint' => 'Questionnaire d’entretien', 'icon' => 'discord', 'active' => $is('discord')],
    ['href' => url('back-office/recruitments/codes-invitation'), 'label' => 'Codes prioritaires', 'hint' => 'Accès prioritaire à la candidature', 'icon' => 'key', 'active' => $is('invite_codes')],
]];
if ($canStructureHub) {
    $sections[] = ['label' => 'Organisation', 'items' => [
        ['href' => url('back-office/organisation/structure'), 'label' => 'Structure', 'hint' => 'ORBAT et postes à pourvoir', 'icon' => 'sitemap', 'active' => false],
    ]];
}

$lmsNav = [
    'id' => 'recrutement',
    'tone' => 'sky',
    'kicker' => 'Athena · Recrutement',
    'title' => 'Bureau recrutement',
    'lead' => 'Candidatures, décisions, délais et offres publiées.',
    'aria' => 'Navigation du bureau recrutement',
    'sections' => $sections,
    'progress' => $nTotal > 0 ? [
        'label' => 'Dossiers instruits',
        'value' => $nTreated,
        'max' => $nTotal,
        'caption' => $nTreated . ' / ' . $nTotal,
        'text' => $nSubmitted > 0
            ? $nSubmitted . ' dossier' . ($nSubmitted > 1 ? 's' : '') . ' à traiter.'
            : 'Aucun dossier en attente.',
    ] : [
        'label' => 'Dossiers instruits',
        'value' => 0,
        'max' => 0,
        'caption' => '—',
        'text' => 'Aucun dossier enregistré pour l’instant.',
    ],
    'cards' => [
        ['kicker' => 'Formation', 'label' => 'Parcours bureau recrutement', 'href' => url('formations/parcours-bureau-recrutement'), 'text' => 'Instruire une candidature de bout en bout.'],
    ],
    'foot' => ['href' => url('back-office'), 'label' => 'Retour au back-office', 'sub' => 'Menu de la communauté'],
];

require base_path('views/partials/lms_nav_aside.php');
