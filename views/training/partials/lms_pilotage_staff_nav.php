<?php
declare(strict_types=1);
/**
 * Liens vers le pilotage LMS communauté (/formation/…).
 *
 * @var string $mode 'sidebar' | 'overview'
 */
$mode = $mode ?? 'overview';
if (!\App\Support\TrainingLmsStaffAccess::allows(\App\Core\Gate::getInstance())) {
    return;
}
if ($mode === 'sidebar' && !($lmsSidebarShowPilotageLinks ?? false)) {
    return;
}

$links = [
    'Vue d’ensemble staff' => training_lms_admin_url(),
    'Studio LMS' => training_studio_url(),
    'Catalogue (édition)' => training_lms_admin_url('courses'),
    'Publications documentaires' => training_lms_admin_url('publications'),
    'Documentations HTML' => training_lms_admin_url('pages-html'),
    'Inscriptions & validations' => training_lms_admin_url('enrollments'),
    'Rapports & synthèse' => training_lms_admin_url('reports'),
    'Feedback post-leçon' => training_lms_admin_url('feedback'),
    'Journal pédagogique (audit)' => training_lms_admin_url('audit'),
    'Certificats' => training_lms_admin_url('certificates'),
    'Gabarit PDF attestations' => training_lms_admin_url('certificates/gabarit'),
    'Charte RH formations' => training_lms_admin_url('charte-rh'),
    'Compétences — commandement' => training_lms_admin_url('competences/commandement'),
    'Compétences — instructeur' => training_lms_admin_url('competences/instructeur'),
    'Compétences — formateur' => training_lms_admin_url('competences/formateur'),
    'Compétences — bureau personnel' => training_lms_admin_url('competences/bureau-personnel'),
    'Compétences — pôle formation' => training_lms_admin_url('competences/pole-formation'),
    'Compétences — validation' => training_lms_admin_url('competences/validation'),
    'Compétences — sections' => training_lms_admin_url('competences/sections'),
];

if ($mode === 'sidebar'): ?>
    <div class="pt-6 mt-2 border-t border-white/10 space-y-2 max-h-[min(52vh,20rem)] overflow-y-auto overscroll-contain pr-0.5" aria-label="Pilotage communauté">
        <p class="text-[8px] font-black tracking-[0.3em] uppercase text-white/30 px-1 mb-2">Pilotage communauté</p>
        <?php foreach ($links as $label => $href): ?>
        <a href="<?= htmlspecialchars($href) ?>" class="block rounded-xl px-3 py-2 text-[11px] font-semibold text-white/75 hover:text-white hover:bg-white/5 border border-transparent hover:border-white/10 transition-colors">
            <?= htmlspecialchars($label) ?>
        </a>
        <?php endforeach; ?>
    </div>
<?php else:
    $group = static function (string $title, array $keys) use ($links): array {
        $out = ['title' => $title, 'items' => []];
        foreach ($keys as $k) {
            if (isset($links[$k])) {
                $out['items'][$k] = $links[$k];
            }
        }

        return $out;
    };
    $groups = [
        $group('Contenu & diffusion', [
            'Vue d’ensemble staff',
            'Studio LMS',
            'Catalogue (édition)',
            'Publications documentaires',
            'Documentations HTML',
        ]),
        $group('Inscriptions & suivi', [
            'Inscriptions & validations',
            'Rapports & synthèse',
            'Feedback post-leçon',
            'Journal pédagogique (audit)',
        ]),
        $group('Attestations & cadre RH', [
            'Certificats',
            'Gabarit PDF attestations',
            'Charte RH formations',
        ]),
        $group('Compétences & qualifications', [
            'Compétences — commandement',
            'Compétences — instructeur',
            'Compétences — formateur',
            'Compétences — bureau personnel',
            'Compétences — pôle formation',
            'Compétences — validation',
            'Compétences — sections',
        ]),
    ];
    ?>
<section id="pilotage" class="lms-panel rounded-[2rem] p-6 md:p-8 scroll-mt-24" aria-labelledby="pilotage-heading">
    <p class="text-[9px] font-black tracking-[0.35em] uppercase text-emerald-600 mb-2">Encadrement</p>
    <h2 id="pilotage-heading" class="text-2xl md:text-3xl font-black tracking-tight uppercase text-slate-900 mb-3">Pilotage de la communauté</h2>
    <p class="text-sm text-slate-600 max-w-3xl leading-relaxed mb-6">
        Raccourcis vers l’espace staff (<code class="text-xs bg-slate-100 px-1 rounded">/formation</code>) : édition des parcours, inscriptions, rapports, attestations et compétences.
        Pour le tableau détaillé avec statistiques, ouvrez la <a href="<?= htmlspecialchars(training_lms_admin_url()) ?>" class="text-emerald-700 font-semibold hover:underline">vue d’ensemble du pilotage</a>.
    </p>
    <div class="space-y-8">
        <?php foreach ($groups as $g): ?>
            <?php if (($g['items'] ?? []) === []) {
                continue;
            } ?>
        <div>
            <h3 class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-500 mb-3"><?= htmlspecialchars($g['title']) ?></h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
                <?php foreach ($g['items'] as $label => $href): ?>
                <a href="<?= htmlspecialchars($href) ?>" class="group block rounded-2xl border border-slate-200 bg-slate-50/90 px-4 py-3 no-underline text-inherit shadow-sm hover:border-emerald-300 hover:bg-white transition-colors">
                    <p class="text-sm font-black text-slate-900 group-hover:text-emerald-800"><?= htmlspecialchars($label) ?></p>
                    <p class="text-[10px] text-slate-500 mt-1 font-medium">Ouvrir dans l’espace formation</p>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
