<?php
declare(strict_types=1);
$base = url('');
$courses = $courses ?? [];
$legacyModules = $legacyModules ?? [];
$training_legacy_enabled = $training_legacy_enabled ?? true;
$title = $title ?? 'Formations';
$filterCategory = $filterCategory ?? null;
$filterSearch = $filterSearch ?? null;
$filterCategories = $filterCategories ?? [];

$totalModules = count($courses) + ($training_legacy_enabled ? count($legacyModules) : 0);
$formationsUrl = rtrim($base, '/') . '/formations';
$buildFormationsUrl = static function (?string $cat, ?string $q) use ($formationsUrl): string {
    $p = [];
    if ($cat !== null && $cat !== '') {
        $p['category'] = $cat;
    }
    if ($q !== null && $q !== '') {
        $p['search'] = $q;
    }

    return $formationsUrl . ($p !== [] ? '?' . http_build_query($p) : '');
};

$lmsTitle = $title;
$lmsBase = $base;
$lmsThemeVars = '';
$lmsExtraHead = '';
ob_start();
require base_path('views/training/partials/lms_head.php');
$headHtml = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
<?= $headHtml ?>
</head>
<body class="bg-slate-100 text-slate-900 overflow-x-hidden">
    <?php
    $lmsBootMessage = 'Chargement des formations…';
    require base_path('views/training/partials/lms_page_boot_overlay.php');
    ?>
    <div class="lms-grain"></div>

    <div class="min-h-screen relative z-10">
        <div class="grid lg:grid-cols-[290px_1fr] min-h-screen">
            <?php
            $activeNav = 'overview';
            require base_path('views/training/partials/lms_command_sidebar.php');
            ?>

            <main class="p-5 md:p-8 lg:p-10 space-y-8">
                <div class="lms-infobanner" role="note">
                    <span class="lms-infobanner__icon" aria-hidden="true">
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                    <p><strong>Repère.</strong> Parcours publiés dans votre espace — recherche et filtres ci-dessous. Pour reprendre un parcours déjà commencé ou vos attestations, ouvrez <a href="<?= htmlspecialchars($base) ?>/formations/mes-formations" class="text-emerald-700 font-semibold hover:underline">Mes formations</a>.</p>
                </div>

                <header id="overview" class="lms-panel rounded-[2rem] p-6 md:p-8 overflow-hidden relative">
                    <div class="absolute top-0 left-0 w-full h-[2px] bg-gradient-to-r from-emerald-500/80 via-emerald-500/20 to-transparent"></div>
                    <div class="flex flex-col xl:flex-row xl:items-end xl:justify-between gap-8">
                        <div class="max-w-3xl">
                            <p class="text-[9px] font-black tracking-[0.45em] text-emerald-600 uppercase mb-4">Vue d’ensemble</p>
                            <h2 class="text-3xl md:text-5xl font-black tracking-tight uppercase leading-none mb-5">
                                Formation &amp; continuité<br>opérationnelle
                            </h2>
                            <div class="h-[1px] w-20 bg-slate-900/10 mb-5"></div>
                            <p class="text-slate-500 text-[11px] font-bold tracking-[0.18em] uppercase leading-relaxed max-w-2xl">
                                Catalogue centralisé, parcours publiés par votre communauté, suivi des inscriptions et état de qualification.
                            </p>
                        </div>
                        <div class="grid grid-cols-2 xl:grid-cols-4 gap-3 min-w-full xl:min-w-[560px]">
                            <div class="bg-slate-50 rounded-2xl border border-slate-200 p-4">
                                <p class="text-[8px] font-black tracking-[0.28em] uppercase text-slate-400 mb-2">Références</p>
                                <p class="text-2xl font-black tracking-tight"><?= $totalModules ?></p>
                            </div>
                            <div class="bg-slate-50 rounded-2xl border border-slate-200 p-4">
                                <p class="text-[8px] font-black tracking-[0.28em] uppercase text-slate-400 mb-2">Parcours LMS</p>
                                <p class="text-2xl font-black tracking-tight"><?= count($courses) ?></p>
                            </div>
                            <?php if ($training_legacy_enabled): ?>
                            <div class="bg-slate-50 rounded-2xl border border-slate-200 p-4">
                                <p class="text-[8px] font-black tracking-[0.28em] uppercase text-slate-400 mb-2">Ancien format</p>
                                <p class="text-2xl font-black tracking-tight text-emerald-600"><?= count($legacyModules) ?></p>
                            </div>
                            <?php endif; ?>
                            <div class="bg-slate-50 rounded-2xl border border-slate-200 p-4">
                                <p class="text-[8px] font-black tracking-[0.28em] uppercase text-slate-400 mb-2">Accès</p>
                                <p class="text-2xl font-black tracking-tight text-amber-500">Ouvert</p>
                            </div>
                        </div>
                    </div>
                </header>

                <section class="grid xl:grid-cols-[1.2fr_0.8fr] gap-8">
                    <div id="catalogue" class="lms-panel rounded-[2rem] p-6 md:p-8">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
                            <div>
                                <p class="text-[9px] font-black tracking-[0.35em] uppercase text-slate-400 mb-2">Catalogue</p>
                                <h3 class="text-2xl font-black tracking-tight uppercase">Parcours disponibles</h3>
                            </div>
                        </div>

                        <form method="get" action="<?= htmlspecialchars($formationsUrl) ?>" class="flex flex-col sm:flex-row gap-3 mb-6">
                            <?php if ($filterCategory): ?>
                            <input type="hidden" name="category" value="<?= htmlspecialchars($filterCategory) ?>">
                            <?php endif; ?>
                            <label class="sr-only" for="catalogue-search">Recherche</label>
                            <input type="search" id="catalogue-search" name="search" value="<?= htmlspecialchars((string) ($filterSearch ?? '')) ?>"
                                   placeholder="Rechercher un titre, un code…"
                                   class="flex-1 min-w-0 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-800 placeholder:text-slate-400">
                            <button type="submit" class="px-6 py-3 rounded-2xl bg-slate-900 text-white text-[10px] font-black uppercase tracking-[0.2em] hover:bg-emerald-600 transition-colors">
                                Rechercher
                            </button>
                        </form>
                        <form method="get" action="<?= htmlspecialchars($formationsUrl) ?>" class="flex flex-wrap gap-2 mb-8 items-center">
                            <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 mr-1">Filtrer :</span>
                            <a href="<?= htmlspecialchars($buildFormationsUrl(null, $filterSearch)) ?>"
                               class="px-4 py-2 rounded-full border text-[10px] font-black tracking-[0.18em] uppercase transition-colors <?= $filterCategory === null ? 'border-emerald-500 bg-emerald-500/10 text-emerald-800' : 'border-slate-200 bg-white text-slate-700 hover:border-emerald-200' ?>">
                                Tout
                            </a>
                            <?php foreach ($filterCategories as $cat): ?>
                            <a href="<?= htmlspecialchars($buildFormationsUrl($cat, $filterSearch)) ?>"
                               class="px-4 py-2 rounded-full border text-[10px] font-black tracking-[0.18em] uppercase transition-colors <?= ($filterCategory === $cat) ? 'border-emerald-500 bg-emerald-500/10 text-emerald-800' : 'border-slate-200 bg-white text-slate-700 hover:border-emerald-200' ?>">
                                <?= htmlspecialchars($cat) ?>
                            </a>
                            <?php endforeach; ?>
                        </form>

                        <?php if (empty($courses) && (!$training_legacy_enabled || empty($legacyModules))): ?>
                        <div class="py-16 text-center">
                            <p class="text-sm font-bold uppercase tracking-[0.24em] text-slate-400">Aucun parcours pour l’instant</p>
                            <p class="mt-3 text-slate-500">Aucune formation publiée ne correspond à votre recherche ou le catalogue est vide.</p>
                            <p class="mt-6"><a href="<?= htmlspecialchars($base) ?>/dashboard" class="text-emerald-600 hover:underline font-semibold">Retour au tableau de bord</a></p>
                        </div>
                        <?php else: ?>
                        <div class="grid md:grid-cols-2 gap-5">
                            <?php
                            $cardColorClasses = [
                                ['bg' => 'bg-emerald-500/10', 'border' => 'border-emerald-500/20', 'text' => 'text-emerald-700'],
                                ['bg' => 'bg-sky-500/10', 'border' => 'border-sky-500/20', 'text' => 'text-sky-700'],
                                ['bg' => 'bg-red-500/10', 'border' => 'border-red-500/20', 'text' => 'text-red-700'],
                                ['bg' => 'bg-amber-500/10', 'border' => 'border-amber-500/20', 'text' => 'text-amber-700'],
                                ['bg' => 'bg-violet-500/10', 'border' => 'border-violet-500/20', 'text' => 'text-violet-700'],
                            ];
                            $ci = 0;
                            foreach ($courses as $c):
                                $cat = $c['category'] ?? 'Général';
                                $code = !empty($c['course_code']) ? (string) $c['course_code'] : ($c['code'] ?? ('F-' . (int) ($c['id'] ?? 0)));
                                $mins = (int) ($c['estimated_minutes'] ?? 0);
                                $duration = $mins ? $mins . ' min' : '—';
                                $cc = $cardColorClasses[$ci % count($cardColorClasses)];
                                $ci++;
                            ?>
                            <a href="<?= htmlspecialchars($base) ?>/formations/<?= htmlspecialchars($c['slug']) ?>" class="lms-course-card block bg-white rounded-3xl border border-slate-200 p-5">
                                <div class="flex items-start justify-between gap-4 mb-5">
                                    <div class="w-12 h-12 rounded-2xl <?= $cc['bg'] ?> <?= $cc['border'] ?> flex items-center justify-center">
                                        <span class="text-[11px] font-black tracking-widest <?= $cc['text'] ?>"><?= htmlspecialchars(mb_substr($code, 0, 4)) ?></span>
                                    </div>
                                    <span class="text-[8px] font-black tracking-[0.25em] uppercase text-slate-400"><?= htmlspecialchars($cat) ?></span>
                                </div>
                                <h4 class="text-lg font-black tracking-tight uppercase mb-2"><?= htmlspecialchars($c['title']) ?></h4>
                                <p class="text-[11px] text-slate-600 font-medium leading-relaxed mb-5"><?= !empty($c['short_description']) ? htmlspecialchars($c['short_description']) : 'Parcours publié dans le catalogue.' ?></p>
                                <div class="flex items-center justify-between text-[10px] font-black uppercase tracking-[0.14em]">
                                    <span class="text-slate-400">Durée estimée</span>
                                    <span><?= htmlspecialchars($duration) ?></span>
                                </div>
                                <?php if (!empty($c['enrollment'])): ?>
                                <div class="mt-3">
                                    <span class="text-[10px] font-bold <?= ($c['enrollment']['status'] ?? '') === 'completed' ? 'text-emerald-600' : 'text-amber-600' ?>">
                                        <?= ($c['enrollment']['status'] ?? '') === 'completed' ? 'Terminé' : ((int) ($c['progress_percent'] ?? 0) . ' %') ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                            </a>
                            <?php endforeach; ?>

                            <?php if ($training_legacy_enabled): ?>
                            <?php foreach ($legacyModules as $m):
                                $code = $m['code'] ?? ('MOD-' . (int) $m['id']);
                            ?>
                            <a href="<?= htmlspecialchars($base) ?>/formations/<?= htmlspecialchars($m['slug']) ?>" class="lms-course-card block bg-white rounded-3xl border border-slate-200 p-5">
                                <div class="flex items-start justify-between gap-4 mb-5">
                                    <div class="w-12 h-12 rounded-2xl bg-sky-500/10 border border-sky-500/20 flex items-center justify-center">
                                        <span class="text-[11px] font-black tracking-widest text-sky-700"><?= htmlspecialchars(mb_substr($code, 0, 4)) ?></span>
                                    </div>
                                    <span class="text-[8px] font-black tracking-[0.25em] uppercase text-slate-400">Ancien format</span>
                                </div>
                                <h4 class="text-lg font-black tracking-tight uppercase mb-2"><?= htmlspecialchars($m['title']) ?></h4>
                                <p class="text-[11px] text-slate-600 font-medium leading-relaxed mb-5"><?= htmlspecialchars($m['code'] ?? 'Module') ?></p>
                                <div class="flex items-center justify-between text-[10px] font-black uppercase tracking-[0.14em]">
                                    <span class="text-slate-400">Type</span>
                                    <span>Module</span>
                                </div>
                            </a>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="lms-panel rounded-[2rem] p-6 md:p-8">
                        <div class="flex items-center justify-between gap-4 mb-6">
                            <div>
                                <p class="text-[9px] font-black tracking-[0.35em] uppercase text-slate-400 mb-2">Suivi</p>
                                <h3 class="text-2xl font-black tracking-tight uppercase">Mes formations</h3>
                            </div>
                            <span class="px-3 py-2 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-[10px] font-black tracking-[0.18em] uppercase text-emerald-700">Actif</span>
                        </div>
                        <p class="text-[12px] text-slate-700 leading-relaxed font-medium mb-5">
                            Progression, modules en cours et attestations éventuelles.
                        </p>
                        <a href="<?= htmlspecialchars($base) ?>/formations/mes-formations" class="inline-flex items-center gap-2 px-6 py-3 bg-slate-900 text-white text-[10px] font-black uppercase tracking-[0.2em] rounded-2xl hover:bg-emerald-600 transition-all">
                            Ouvrir Mes formations
                        </a>
                    </div>
                </section>

                <section id="sessions" class="lms-panel rounded-[2rem] p-6 md:p-8">
                    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-8">
                        <div>
                            <p class="text-[9px] font-black tracking-[0.35em] uppercase text-slate-400 mb-2">Sessions</p>
                            <h3 class="text-2xl font-black tracking-tight uppercase">Créneaux &amp; fenêtres</h3>
                        </div>
                        <div class="text-[10px] font-black tracking-[0.18em] uppercase text-slate-400">Annonces du commandement</div>
                    </div>
                    <div class="py-8 text-center text-slate-500 text-sm rounded-2xl border border-slate-200 bg-slate-50/50">
                        Aucune session planifiée ici pour le moment. Surveillez le forum et le tableau de bord.
                    </div>
                </section>

                <section id="qualifications" class="grid xl:grid-cols-[0.9fr_1.1fr] gap-8">
                    <div class="lms-panel rounded-[2rem] p-6 md:p-8">
                        <div class="mb-8">
                            <p class="text-[9px] font-black tracking-[0.35em] uppercase text-slate-400 mb-2">Préparation</p>
                            <h3 class="text-2xl font-black tracking-tight uppercase">État de préparation</h3>
                        </div>
                        <p class="text-slate-600 text-sm mb-6">Synthèse de votre avancement sur les parcours.</p>
                        <a href="<?= htmlspecialchars($base) ?>/formations/mes-formations" class="inline-flex items-center gap-2 px-6 py-3 bg-slate-900 text-white text-[10px] font-black uppercase tracking-[0.2em] rounded-2xl hover:bg-emerald-600 transition-all">
                            Mes formations
                        </a>
                    </div>
                    <div class="lms-panel rounded-[2rem] p-6 md:p-8">
                        <div class="mb-8">
                            <p class="text-[9px] font-black tracking-[0.35em] uppercase text-slate-400 mb-2">Qualifications</p>
                            <h3 class="text-2xl font-black tracking-tight uppercase">Progression &amp; attestations</h3>
                        </div>
                        <p class="text-slate-500 text-sm">Consultez <a href="<?= htmlspecialchars($base) ?>/formations/mes-formations" class="text-emerald-600 hover:underline font-semibold">Mes formations</a> pour le détail et les certificats.</p>
                    </div>
                </section>

            </main>
        </div>
    </div>
    <?php require base_path('views/partials/cookie_banner.php'); ?>
</body>
</html>
