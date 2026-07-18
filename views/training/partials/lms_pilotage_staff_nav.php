<?php
declare(strict_types=1);
/**
 * Liens vers le pilotage LMS communauté.
 *
 * @var string $mode 'sidebar' | 'overview'
 * @var bool $pilotageOnHubPage si true (page vue d’ensemble staff), adapte titres et masque le lien hub
 */
$mode = $mode ?? 'overview';
$pilotageOnHubPage = !empty($pilotageOnHubPage);
if (!\App\Support\TrainingLmsStaffAccess::allows(\App\Core\Gate::getInstance())) {
    return;
}
if ($mode === 'sidebar' && !($lmsSidebarShowPilotageLinks ?? false)) {
    return;
}

$pilotageItems = [
    [
        'label' => 'Vue d’ensemble',
        'href' => training_lms_admin_url(),
        'subtitle' => 'Indicateurs et priorités du jour',
        'icon' => 'overview',
        'group' => 'contenu',
        'hide_on_hub' => true,
    ],
    [
        'label' => 'Studio',
        'href' => training_studio_url(),
        'subtitle' => 'Créer et structurer les parcours',
        'icon' => 'studio',
        'group' => 'contenu',
    ],
    [
        'label' => 'Catalogue d’édition',
        'href' => training_lms_admin_url('courses'),
        'subtitle' => 'Publier et classer les formations',
        'icon' => 'catalogue',
        'group' => 'contenu',
    ],
    [
        'label' => 'Publications documentaires',
        'href' => training_lms_admin_url('publications'),
        'subtitle' => 'Documents de référence de la communauté',
        'icon' => 'docs',
        'group' => 'contenu',
    ],
    [
        'label' => 'Pages pédagogiques',
        'href' => training_lms_admin_url('pages-html'),
        'subtitle' => 'Manuels et pages riches pour les membres',
        'icon' => 'html',
        'group' => 'contenu',
    ],
    [
        'label' => 'Inscriptions & validations',
        'href' => training_lms_admin_url('enrollments'),
        'subtitle' => 'Approuver et suivre les inscriptions',
        'icon' => 'enroll',
        'group' => 'suivi',
    ],
    [
        'label' => 'Rapports & synthèse',
        'href' => training_lms_admin_url('reports'),
        'subtitle' => 'Avancement et résultats des parcours',
        'icon' => 'reports',
        'group' => 'suivi',
    ],
    [
        'label' => 'Retours post-leçon',
        'href' => training_lms_admin_url('feedback'),
        'subtitle' => 'Commentaires des apprenants',
        'icon' => 'feedback',
        'group' => 'suivi',
    ],
    [
        'label' => 'Journal pédagogique',
        'href' => training_lms_admin_url('audit'),
        'subtitle' => 'Historique des actions d’encadrement',
        'icon' => 'audit',
        'group' => 'suivi',
    ],
    [
        'label' => 'Attestations',
        'href' => training_lms_admin_url('certificates'),
        'subtitle' => 'Attestations délivrées et à émettre',
        'icon' => 'cert',
        'group' => 'attestations',
    ],
    [
        'label' => 'Mise en page des attestations',
        'href' => training_lms_admin_url('certificates/gabarit'),
        'subtitle' => 'Apparence des documents PDF',
        'icon' => 'gabarit',
        'group' => 'attestations',
    ],
    [
        'label' => 'Charte RH formations',
        'href' => training_lms_admin_url('charte-rh'),
        'subtitle' => 'Cadre RH applicable aux parcours',
        'icon' => 'charte',
        'group' => 'attestations',
    ],
];

/** Hub + accès secondaires (pas 7 cartes clones). */
$competencesHub = [
    'label' => 'Pilotage des compétences',
    'href' => training_lms_admin_url('competences/commandement'),
    'subtitle' => 'Vue d’ensemble commandement : progression, jalons et suivi transversal',
    'icon' => 'competency',
];
$competencesLinks = [
    [
        'label' => 'Instructeur',
        'href' => training_lms_admin_url('competences/instructeur'),
    ],
    [
        'label' => 'Formateur',
        'href' => training_lms_admin_url('competences/formateur'),
    ],
    [
        'label' => 'Bureau personnel',
        'href' => training_lms_admin_url('competences/bureau-personnel'),
    ],
    [
        'label' => 'Pôle formation',
        'href' => training_lms_admin_url('competences/pole-formation'),
    ],
    [
        'label' => 'Validation',
        'href' => training_lms_admin_url('competences/validation'),
    ],
    [
        'label' => 'Sections',
        'href' => training_lms_admin_url('competences/sections'),
    ],
];

$pilotageIconSvg = static function (string $icon): string {
    $common = 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';
    return match ($icon) {
        'overview' => '<svg viewBox="0 0 24 24" ' . $common . ' aria-hidden="true"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>',
        'studio' => '<svg viewBox="0 0 24 24" ' . $common . ' aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>',
        'catalogue' => '<svg viewBox="0 0 24 24" ' . $common . ' aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>',
        'docs' => '<svg viewBox="0 0 24 24" ' . $common . ' aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
        'html' => '<svg viewBox="0 0 24 24" ' . $common . ' aria-hidden="true"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>',
        'enroll' => '<svg viewBox="0 0 24 24" ' . $common . ' aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>',
        'reports' => '<svg viewBox="0 0 24 24" ' . $common . ' aria-hidden="true"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
        'feedback' => '<svg viewBox="0 0 24 24" ' . $common . ' aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
        'audit' => '<svg viewBox="0 0 24 24" ' . $common . ' aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
        'cert' => '<svg viewBox="0 0 24 24" ' . $common . ' aria-hidden="true"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/></svg>',
        'gabarit' => '<svg viewBox="0 0 24 24" ' . $common . ' aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>',
        'charte' => '<svg viewBox="0 0 24 24" ' . $common . ' aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
        'competency' => '<svg viewBox="0 0 24 24" ' . $common . ' aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>',
        'contenu' => '<svg viewBox="0 0 24 24" ' . $common . ' aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>',
        'suivi' => '<svg viewBox="0 0 24 24" ' . $common . ' aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/></svg>',
        'attestations' => '<svg viewBox="0 0 24 24" ' . $common . ' aria-hidden="true"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/></svg>',
        'competences' => '<svg viewBox="0 0 24 24" ' . $common . ' aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>',
        'arrow' => '<svg viewBox="0 0 24 24" ' . $common . ' aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>',
        default => '<svg viewBox="0 0 24 24" ' . $common . ' aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>',
    };
};

$arrowSvg = $pilotageIconSvg('arrow');

$groupMeta = [
    'contenu' => ['title' => 'Contenu', 'icon' => 'contenu'],
    'suivi' => ['title' => 'Inscriptions', 'icon' => 'suivi'],
    'attestations' => ['title' => 'Attestations', 'icon' => 'attestations'],
    'competences' => ['title' => 'Compétences', 'icon' => 'competences'],
];

$visiblePilotageItems = array_values(array_filter(
    $pilotageItems,
    static function (array $item) use ($pilotageOnHubPage, $mode): bool {
        if ($mode === 'overview' && $pilotageOnHubPage && !empty($item['hide_on_hub'])) {
            return false;
        }
        return true;
    }
));

if ($mode === 'sidebar'):
    $sidebarGrouped = [];
    foreach ($groupMeta as $key => $meta) {
        $sidebarGrouped[$key] = ['title' => $meta['title'], 'items' => []];
    }
    foreach ($visiblePilotageItems as $item) {
        if (!empty($item['hide_on_hub']) && ($lmsSidebarContext ?? '') === 'staff') {
            // Sur le hub staff, la vue d’ensemble est déjà en tête de nav.
            continue;
        }
        $g = (string) ($item['group'] ?? '');
        if (isset($sidebarGrouped[$g])) {
            $sidebarGrouped[$g]['items'][] = $item;
        }
    }
    $sidebarGrouped['competences']['items'][] = array_merge($competencesHub, ['icon' => 'competency']);
    foreach ($competencesLinks as $link) {
        $sidebarGrouped['competences']['items'][] = [
            'label' => (string) $link['label'],
            'href' => $link['href'],
            'icon' => 'competency',
        ];
    }
    ?>
    <div class="pt-6 mt-2 border-t border-white/10 space-y-4 max-h-[min(52vh,22rem)] overflow-y-auto overscroll-contain pr-0.5 lms-sidebar-scroll" aria-label="Pilotage communauté">
        <p class="text-[8px] font-black tracking-[0.3em] uppercase text-white/30 px-1 mb-1">Pilotage communauté</p>
        <?php foreach ($sidebarGrouped as $g): ?>
            <?php if (($g['items'] ?? []) === []) {
                continue;
            } ?>
        <div class="lms-pilotage-sidebar-group">
            <p class="lms-pilotage-sidebar-group__title"><?= htmlspecialchars((string) $g['title']) ?></p>
            <div class="space-y-1">
                <?php foreach ($g['items'] as $item): ?>
                <a href="<?= htmlspecialchars((string) $item['href']) ?>" class="lms-pilotage-sidebar-link">
                    <span class="lms-pilotage-sidebar-link__icon" aria-hidden="true"><?= $pilotageIconSvg((string) ($item['icon'] ?? 'default')) ?></span>
                    <span><?= htmlspecialchars((string) $item['label']) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php else:
    $grouped = [];
    foreach (['contenu', 'suivi', 'attestations'] as $key) {
        $meta = $groupMeta[$key];
        $grouped[$key] = ['title' => $meta['title'], 'icon' => $meta['icon'], 'items' => []];
    }
    foreach ($visiblePilotageItems as $item) {
        $g = (string) ($item['group'] ?? '');
        if (isset($grouped[$g])) {
            $grouped[$g]['items'][] = $item;
        }
    }
    $overviewTitle = $pilotageOnHubPage ? 'Accès par domaine' : 'Pilotage de la communauté';
    $overviewLead = $pilotageOnHubPage
        ? 'Quatre familles d’outils : contenus à publier, suivi des inscriptions, attestations, puis compétences & qualifications.'
        : 'Raccourcis vers l’espace d’encadrement : édition des parcours, inscriptions, rapports, attestations et compétences.';
    ?>
<section id="pilotage" class="lms-panel rounded-[2rem] p-6 md:p-8 scroll-mt-24" aria-labelledby="pilotage-heading">
    <p class="lms-catalogue-kicker lms-catalogue-kicker--accent mb-2">Encadrement</p>
    <h2 id="pilotage-heading" class="lms-catalogue-title text-2xl md:text-3xl mb-3"><?= htmlspecialchars($overviewTitle) ?></h2>
    <p class="text-sm text-slate-600 max-w-3xl leading-relaxed mb-6">
        <?= htmlspecialchars($overviewLead) ?>
        <?php if (!$pilotageOnHubPage): ?>
        Pour le tableau avec indicateurs, ouvrez la <a href="<?= htmlspecialchars(training_lms_admin_url()) ?>" class="text-emerald-700 font-semibold hover:underline">vue d’ensemble du pilotage</a>.
        <?php endif; ?>
    </p>
    <div class="lms-pilotage-groups">
        <?php foreach ($grouped as $g): ?>
            <?php if (($g['items'] ?? []) === []) {
                continue;
            } ?>
        <div class="lms-pilotage-group">
            <h3 class="lms-pilotage-group__title">
                <span class="lms-pilotage-group__icon" aria-hidden="true"><?= $pilotageIconSvg((string) $g['icon']) ?></span>
                <?= htmlspecialchars($g['title']) ?>
            </h3>
            <div class="lms-pilotage-grid">
                <?php foreach ($g['items'] as $item): ?>
                <a href="<?= htmlspecialchars((string) $item['href']) ?>" class="lms-pilotage-card group">
                    <span class="lms-pilotage-card__icon" aria-hidden="true"><?= $pilotageIconSvg((string) ($item['icon'] ?? 'default')) ?></span>
                    <span class="lms-pilotage-card__body">
                        <p class="lms-pilotage-card__title"><?= htmlspecialchars((string) $item['label']) ?></p>
                        <p class="lms-pilotage-card__sub"><?= htmlspecialchars((string) ($item['subtitle'] ?? '')) ?></p>
                    </span>
                    <span class="lms-pilotage-card__arrow" aria-hidden="true"><?= $arrowSvg ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="lms-pilotage-group">
            <h3 class="lms-pilotage-group__title">
                <span class="lms-pilotage-group__icon" aria-hidden="true"><?= $pilotageIconSvg('competences') ?></span>
                Compétences
            </h3>
            <div class="lms-pilotage-competences">
                <a href="<?= htmlspecialchars((string) $competencesHub['href']) ?>" class="lms-pilotage-card lms-pilotage-card--hub group">
                    <span class="lms-pilotage-card__icon" aria-hidden="true"><?= $pilotageIconSvg('competency') ?></span>
                    <span class="lms-pilotage-card__body">
                        <p class="lms-pilotage-card__title"><?= htmlspecialchars((string) $competencesHub['label']) ?></p>
                        <p class="lms-pilotage-card__sub"><?= htmlspecialchars((string) $competencesHub['subtitle']) ?></p>
                    </span>
                    <span class="lms-pilotage-card__arrow" aria-hidden="true"><?= $arrowSvg ?></span>
                </a>
                <ul class="lms-pilotage-competences__links" aria-label="Autres accès compétences">
                    <?php foreach ($competencesLinks as $link): ?>
                    <li>
                        <a href="<?= htmlspecialchars((string) $link['href']) ?>" class="lms-pilotage-chip">
                            <?= htmlspecialchars((string) $link['label']) ?>
                            <span class="lms-pilotage-chip__arrow" aria-hidden="true"><?= $arrowSvg ?></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>
