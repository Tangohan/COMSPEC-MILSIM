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
$filterParcours = $filterParcours ?? '';
$filterLevel = $filterLevel ?? '';
$filterDuration = $filterDuration ?? '';
$filterModality = $filterModality ?? '';
$filterAvailability = $filterAvailability ?? '';
$filterLevelOptions = $filterLevelOptions ?? [];
$filterModalityOptions = $filterModalityOptions ?? [];
$catalogueSidebarEnrollments = $catalogueSidebarEnrollments ?? [];

$totalModules = count($courses) + ($training_legacy_enabled ? count($legacyModules) : 0);
$formationsUrl = rtrim($base, '/') . '/formations';
$buildFormationsUrl = static function (
    ?string $cat,
    ?string $q,
    string $parcours = '',
    string $niveau = '',
    string $duree = '',
    string $modalite = '',
    string $disponibilite = ''
) use ($formationsUrl): string {
    $p = [];
    if ($cat !== null && $cat !== '') {
        $p['category'] = $cat;
    }
    if ($q !== null && $q !== '') {
        $p['search'] = $q;
    }
    if ($parcours !== '' && in_array($parcours, ['communaute', 'plateforme'], true)) {
        $p['parcours'] = $parcours;
    }
    if ($niveau !== '') {
        $p['niveau'] = $niveau;
    }
    if ($duree !== '' && in_array($duree, ['court', 'moyen', 'long'], true)) {
        $p['duree'] = $duree;
    }
    if ($modalite !== '') {
        $p['modalite'] = $modalite;
    }
    if ($disponibilite !== '' && in_array($disponibilite, ['ouvert', 'non_commence', 'en_cours', 'termine'], true)) {
        $p['disponibilite'] = $disponibilite;
    }

    return $formationsUrl . ($p !== [] ? '?' . http_build_query($p) : '');
};

$lmsTitle = $title;
$lmsBase = $base;
$lmsThemeVars = '--lms-accent: #059669; --lms-accent-rgb: 5, 150, 105;';
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
            $lmsSidebarShowPilotageLinks = true;
            require base_path('views/training/partials/lms_command_sidebar.php');
            ?>

            <main class="p-5 md:p-8 lg:p-10 space-y-8">
                <div class="lms-infobanner" role="note">
                    <span class="lms-infobanner__icon" aria-hidden="true">
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                    <p><strong>Repère.</strong> Catalogue mêlant les parcours de votre communauté et, le cas échéant, des parcours proposés sur l’ensemble du site — recherche et filtres ci-dessous. Pour reprendre un parcours déjà commencé ou vos attestations, ouvrez <a href="<?= htmlspecialchars($base) ?>/formations/mes-formations" class="text-emerald-700 font-semibold hover:underline">Mes formations</a>. Pour signaler un problème sur un parcours, ouvrez sa fiche puis utilisez le lien en bas de page.<?php if (\App\Support\TrainingLmsStaffAccess::allows(\App\Core\Gate::getInstance())): ?> <strong>Encadrement :</strong> accès directs au pilotage (studio, inscriptions, rapports, compétences…) dans la section <a href="#pilotage" class="text-emerald-700 font-semibold hover:underline">Pilotage de la communauté</a> ou via le menu sombre à gauche.<?php endif; ?></p>
                </div>

                <header id="overview" class="lms-panel rounded-[2rem] p-6 md:p-8 overflow-hidden relative">
                    <div class="absolute top-0 left-0 w-full h-[2px] bg-gradient-to-r from-emerald-600/80 via-emerald-500/25 to-transparent"></div>
                    <div class="flex flex-col xl:flex-row xl:items-end xl:justify-between gap-8">
                        <div class="max-w-3xl">
                            <p class="lms-catalogue-kicker lms-catalogue-kicker--accent mb-3">Vue d’ensemble</p>
                            <h2 class="lms-catalogue-title text-3xl md:text-4xl mb-4">
                                Formation &amp; continuité opérationnelle
                            </h2>
                            <div class="h-[1px] w-20 bg-slate-900/10 mb-4"></div>
                            <p class="text-slate-600 text-sm font-medium leading-relaxed max-w-2xl">
                                Catalogue centralisé, parcours publiés par votre communauté, suivi des inscriptions et état de qualification.
                            </p>
                        </div>
                        <div class="grid grid-cols-2 xl:grid-cols-4 gap-3 min-w-full xl:min-w-[560px]">
                            <div class="bg-slate-50/90 rounded-2xl border border-slate-200/90 p-4">
                                <p class="text-xs font-semibold text-slate-500 mb-1.5">Références</p>
                                <p class="text-2xl font-bold tracking-tight tabular-nums text-slate-900"><?= $totalModules ?></p>
                            </div>
                            <div class="bg-slate-50/90 rounded-2xl border border-slate-200/90 p-4">
                                <p class="text-xs font-semibold text-slate-500 mb-1.5">Parcours</p>
                                <p class="text-2xl font-bold tracking-tight tabular-nums text-slate-900"><?= count($courses) ?></p>
                            </div>
                            <?php if ($training_legacy_enabled): ?>
                            <div class="bg-slate-50/90 rounded-2xl border border-slate-200/90 p-4">
                                <p class="text-xs font-semibold text-slate-500 mb-1.5">Ancien format</p>
                                <p class="text-2xl font-bold tracking-tight tabular-nums text-emerald-700"><?= count($legacyModules) ?></p>
                            </div>
                            <?php endif; ?>
                            <div class="bg-slate-50/90 rounded-2xl border border-slate-200/90 p-4">
                                <p class="text-xs font-semibold text-slate-500 mb-1.5">Accès</p>
                                <p class="text-2xl font-bold tracking-tight text-emerald-700">Ouvert</p>
                            </div>
                        </div>
                    </div>
                </header>

                <?php
                $mode = 'overview';
                require base_path('views/training/partials/lms_pilotage_staff_nav.php');
                ?>

                <section class="grid xl:grid-cols-[1.2fr_0.8fr] gap-8">
                    <div id="catalogue" class="lms-panel min-w-0 rounded-[2rem] p-6 md:p-8">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
                            <div>
                                <p class="lms-catalogue-kicker mb-1.5">Catalogue</p>
                                <h3 class="lms-catalogue-title text-2xl">Parcours disponibles</h3>
                            </div>
                        </div>

                        <form method="get" action="<?= htmlspecialchars($formationsUrl) ?>" class="flex flex-col sm:flex-row gap-3 mb-5">
                            <?php if ($filterCategory): ?>
                            <input type="hidden" name="category" value="<?= htmlspecialchars($filterCategory) ?>">
                            <?php endif; ?>
                            <?php if ($filterParcours !== '' && in_array($filterParcours, ['communaute', 'plateforme'], true)): ?>
                            <input type="hidden" name="parcours" value="<?= htmlspecialchars($filterParcours) ?>">
                            <?php endif; ?>
                            <?php if ($filterLevel !== ''): ?>
                            <input type="hidden" name="niveau" value="<?= htmlspecialchars($filterLevel) ?>">
                            <?php endif; ?>
                            <?php if ($filterDuration !== ''): ?>
                            <input type="hidden" name="duree" value="<?= htmlspecialchars($filterDuration) ?>">
                            <?php endif; ?>
                            <?php if ($filterModality !== ''): ?>
                            <input type="hidden" name="modalite" value="<?= htmlspecialchars($filterModality) ?>">
                            <?php endif; ?>
                            <?php if ($filterAvailability !== ''): ?>
                            <input type="hidden" name="disponibilite" value="<?= htmlspecialchars($filterAvailability) ?>">
                            <?php endif; ?>
                            <label class="sr-only" for="catalogue-search">Recherche</label>
                            <input type="search" id="catalogue-search" name="search" value="<?= htmlspecialchars((string) ($filterSearch ?? '')) ?>"
                                   placeholder="Rechercher un titre ou une référence…"
                                   class="flex-1 min-w-0 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500/50">
                            <button type="submit" class="lms-catalogue-btn lms-catalogue-btn--void">
                                Rechercher
                            </button>
                        </form>
                        <div class="mb-4 space-y-3">
                            <div class="flex flex-wrap gap-2 items-center">
                                <span class="lms-catalogue-filters__label mr-1">Origine</span>
                                <a href="<?= htmlspecialchars($buildFormationsUrl($filterCategory, $filterSearch, '', $filterLevel, $filterDuration, $filterModality, $filterAvailability)) ?>"
                                   class="lms-catalogue-chip <?= $filterParcours === '' ? 'lms-catalogue-chip--active' : '' ?>">
                                    Tous
                                </a>
                                <a href="<?= htmlspecialchars($buildFormationsUrl($filterCategory, $filterSearch, 'communaute', $filterLevel, $filterDuration, $filterModality, $filterAvailability)) ?>"
                                   class="lms-catalogue-chip <?= $filterParcours === 'communaute' ? 'lms-catalogue-chip--active' : '' ?>">
                                    Communauté
                                </a>
                                <a href="<?= htmlspecialchars($buildFormationsUrl($filterCategory, $filterSearch, 'plateforme', $filterLevel, $filterDuration, $filterModality, $filterAvailability)) ?>"
                                   class="lms-catalogue-chip lms-catalogue-chip--platform <?= $filterParcours === 'plateforme' ? 'lms-catalogue-chip--active' : '' ?>">
                                    Toute la plateforme
                                </a>
                            </div>
                            <div class="flex flex-wrap gap-2 items-center">
                                <span class="lms-catalogue-filters__label mr-1">Thème</span>
                                <a href="<?= htmlspecialchars($buildFormationsUrl(null, $filterSearch, $filterParcours, $filterLevel, $filterDuration, $filterModality, $filterAvailability)) ?>"
                                   class="lms-catalogue-chip <?= $filterCategory === null ? 'lms-catalogue-chip--active' : '' ?>">
                                    Tous
                                </a>
                                <?php foreach ($filterCategories as $cat): ?>
                                <a href="<?= htmlspecialchars($buildFormationsUrl($cat, $filterSearch, $filterParcours, $filterLevel, $filterDuration, $filterModality, $filterAvailability)) ?>"
                                   class="lms-catalogue-chip <?= ($filterCategory === $cat) ? 'lms-catalogue-chip--active' : '' ?>">
                                    <?= htmlspecialchars($cat) ?>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <form method="get" action="<?= htmlspecialchars($formationsUrl) ?>" class="lms-catalogue-filters mb-8">
                            <?php if ($filterCategory): ?><input type="hidden" name="category" value="<?= htmlspecialchars($filterCategory) ?>"><?php endif; ?>
                            <?php if ($filterSearch): ?><input type="hidden" name="search" value="<?= htmlspecialchars((string) $filterSearch) ?>"><?php endif; ?>
                            <?php if ($filterParcours !== ''): ?><input type="hidden" name="parcours" value="<?= htmlspecialchars($filterParcours) ?>"><?php endif; ?>
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <p class="lms-catalogue-filters__label">Affiner la recherche</p>
                                <a href="<?= htmlspecialchars($buildFormationsUrl($filterCategory, $filterSearch, $filterParcours)) ?>" class="text-xs font-semibold text-emerald-700 hover:text-emerald-900 hover:underline">Réinitialiser</a>
                            </div>
                            <div class="grid gap-2.5 sm:grid-cols-2">
                                <label class="sr-only" for="catalogue-filter-niveau">Niveau</label>
                                <select id="catalogue-filter-niveau" name="niveau" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/35">
                                    <option value="">Tous les niveaux</option>
                                    <?php foreach ($filterLevelOptions as $levelOpt): ?>
                                    <option value="<?= htmlspecialchars($levelOpt) ?>" <?= $filterLevel === $levelOpt ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($levelOpt)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label class="sr-only" for="catalogue-filter-duree">Durée</label>
                                <select id="catalogue-filter-duree" name="duree" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/35">
                                    <option value="">Toutes les durées</option>
                                    <option value="court" <?= $filterDuration === 'court' ? 'selected' : '' ?>>Courte (≤ 30 min)</option>
                                    <option value="moyen" <?= $filterDuration === 'moyen' ? 'selected' : '' ?>>Moyenne (31 à 90 min)</option>
                                    <option value="long" <?= $filterDuration === 'long' ? 'selected' : '' ?>>Longue (&gt; 90 min)</option>
                                </select>
                                <label class="sr-only" for="catalogue-filter-modalite">Modalité</label>
                                <select id="catalogue-filter-modalite" name="modalite" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/35">
                                    <option value="">Toutes les modalités</option>
                                    <?php foreach ($filterModalityOptions as $modalityOpt): ?>
                                    <option value="<?= htmlspecialchars($modalityOpt) ?>" <?= $filterModality === $modalityOpt ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($modalityOpt)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label class="sr-only" for="catalogue-filter-disponibilite">Disponibilité</label>
                                <select id="catalogue-filter-disponibilite" name="disponibilite" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/35">
                                    <option value="">Toutes les disponibilités</option>
                                    <option value="ouvert" <?= $filterAvailability === 'ouvert' ? 'selected' : '' ?>>Ouvertes à l’inscription</option>
                                    <option value="non_commence" <?= $filterAvailability === 'non_commence' ? 'selected' : '' ?>>Non commencées</option>
                                    <option value="en_cours" <?= $filterAvailability === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                                    <option value="termine" <?= $filterAvailability === 'termine' ? 'selected' : '' ?>>Terminées</option>
                                </select>
                            </div>
                            <div class="mt-3.5">
                                <button type="submit" class="lms-catalogue-btn lms-catalogue-btn--primary">Appliquer les filtres</button>
                            </div>
                        </form>

                        <?php if (empty($courses) && (!$training_legacy_enabled || empty($legacyModules))): ?>
                        <div class="lms-catalogue-empty py-12">
                            <span class="lms-catalogue-empty__icon" aria-hidden="true">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </span>
                            <p class="text-sm font-semibold text-slate-700">Aucun parcours pour l’instant</p>
                            <p class="text-sm text-slate-500 max-w-sm">Aucune formation publiée ne correspond à votre recherche, ou le catalogue est vide.</p>
                            <p class="mt-2"><a href="<?= htmlspecialchars($base) ?>/dashboard" class="text-sm font-semibold text-emerald-700 hover:underline">Retour au tableau de bord</a></p>
                        </div>
                        <?php else: ?>
                        <div class="grid md:grid-cols-2 gap-5">
                            <?php
                            $catalogueCardVisualState = static function (array $c): ?string {
                                $pct = max(0, min(100, (int) ($c['progress_percent'] ?? 0)));
                                $enr = $c['enrollment'] ?? null;
                                $st = is_array($enr) ? (string) ($enr['status'] ?? '') : '';
                                if (in_array($st, ['withdrawn', 'revoked', 'expired'], true)) {
                                    $enr = null;
                                    $st = '';
                                    $pct = 0;
                                }
                                $now = time();
                                $nouveauteJours = 21;
                                if (!is_array($enr) || empty($enr['id'])) {
                                    $ts = strtotime((string) ($c['created_at'] ?? '')) ?: 0;
                                    if ($ts > 0 && ($now - $ts) <= $nouveauteJours * 86400) {
                                        return 'nouvelle';
                                    }

                                    return null;
                                }

                                return match (true) {
                                    $st === 'completed' => 'valide',
                                    in_array($st, ['failed', 'expired'], true) => 'non_termine',
                                    $st === 'in_progress' || ($pct > 0 && $pct < 100) => 'en_cours',
                                    in_array($st, ['assigned', 'pending_approval'], true) && $pct === 0 => 'inscrit',
                                    default => null,
                                };
                            };
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
                                $cc = $cardColorClasses[$ci % count($cardColorClasses)];
                                $ci++;
                                $lmsScopeRow = (string) ($c['lms_scope'] ?? 'tenant');
                                $scopeBadge = function_exists('training_lms_course_scope_label_fr') ? training_lms_course_scope_label_fr($lmsScopeRow) : '';
                                $cardState = $catalogueCardVisualState($c);
                                $cardProgressPercent = ($cardState === 'en_cours')
                                    ? max(0, min(100, (int) ($c['progress_percent'] ?? 0)))
                                    : null;
                                $cardStateSr = match ($cardState) {
                                    'nouvelle' => 'Parcours récent sur le catalogue.',
                                    'inscrit' => 'Inscrit : le parcours n’est pas encore commencé.',
                                    'valide' => 'Parcours validé.',
                                    'en_cours' => 'Parcours en cours'
                                        . ($cardProgressPercent !== null ? ' — environ ' . $cardProgressPercent . ' % réalisés.' : '.'),
                                    'non_termine' => 'Parcours à reprendre ou non validé.',
                                    default => '',
                                };
                                $metaLevel = trim((string) ($c['level'] ?? ''));
                                $metaWeekly = (int) ($c['estimated_weekly_minutes'] ?? 0);
                                $metaFormat = trim((string) ($c['learning_format'] ?? $c['modality'] ?? 'mixte'));
                            ?>
                            <a href="<?= htmlspecialchars($base) ?>/formations/<?= htmlspecialchars($c['slug']) ?>" class="lms-course-card group block bg-white rounded-3xl border border-slate-200 p-5 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/80 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-100">
                                <?php if ($cardStateSr !== ''): ?>
                                <span class="sr-only"><?= htmlspecialchars($cardStateSr, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                                <div class="flex items-start justify-between gap-3 mb-4">
                                    <div class="w-11 h-11 rounded-xl <?= $cc['bg'] ?> <?= $cc['border'] ?> border flex items-center justify-center shrink-0">
                                        <span class="text-[10px] font-bold tracking-wide <?= $cc['text'] ?>"><?= htmlspecialchars(mb_substr($code, 0, 4)) ?></span>
                                    </div>
                                    <div class="flex flex-wrap items-center justify-end gap-1.5 max-w-[65%]">
                                        <?php if ($scopeBadge !== ''): ?>
                                        <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-semibold leading-tight <?= $lmsScopeRow === 'platform'
                                            ? 'bg-violet-500/10 text-violet-900 border border-violet-400/25'
                                            : 'bg-emerald-500/10 text-emerald-900 border border-emerald-500/20' ?>"><?= htmlspecialchars($scopeBadge) ?></span>
                                        <?php endif; ?>
                                        <span class="text-[11px] font-medium text-slate-500"><?= htmlspecialchars($cat) ?></span>
                                    </div>
                                </div>
                                <h4 class="lms-catalogue-card-title"><?= htmlspecialchars($c['title']) ?></h4>
                                <p class="text-sm text-slate-600 font-medium leading-relaxed mb-3 line-clamp-2"><?= !empty($c['short_description']) ? htmlspecialchars($c['short_description']) : 'Parcours publié dans le catalogue.' ?></p>
                                <div class="lms-catalogue-meta">
                                    <span>Niveau <?= htmlspecialchars($metaLevel !== '' ? ucfirst($metaLevel) : 'à définir') ?></span>
                                    <span><?= $metaWeekly > 0 ? $metaWeekly . ' min/sem.' : 'Charge à définir' ?></span>
                                    <span><?= htmlspecialchars($metaFormat !== '' ? ucfirst($metaFormat) : 'Mixte') ?></span>
                                    <?php if ($mins > 0): ?>
                                    <span><?= $mins ?> min</span>
                                    <?php endif; ?>
                                </div>
                                <?php require base_path('views/training/partials/catalogue_card_status_overlay.php'); ?>
                                <?php if ($cardState === null): ?>
                                <div class="mb-3">
                                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-semibold text-slate-600">Non commencé</span>
                                </div>
                                <?php endif; ?>
                                <p class="mt-1 text-sm font-semibold text-emerald-700 group-hover:text-emerald-800 transition-colors">Consulter le parcours →</p>
                            </a>
                            <?php endforeach; ?>

                            <?php if ($training_legacy_enabled): ?>
                            <?php foreach ($legacyModules as $m):
                                $code = $m['code'] ?? ('MOD-' . (int) $m['id']);
                            ?>
                            <a href="<?= htmlspecialchars($base) ?>/formations/<?= htmlspecialchars($m['slug']) ?>" class="lms-course-card group block bg-white rounded-3xl border border-slate-200 p-5 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/80 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-100">
                                <div class="flex items-start justify-between gap-3 mb-4">
                                    <div class="w-11 h-11 rounded-xl bg-sky-500/10 border border-sky-500/20 flex items-center justify-center shrink-0">
                                        <span class="text-[10px] font-bold tracking-wide text-sky-700"><?= htmlspecialchars(mb_substr($code, 0, 4)) ?></span>
                                    </div>
                                    <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-600 border border-slate-200/80">Ancien format</span>
                                </div>
                                <h4 class="lms-catalogue-card-title"><?= htmlspecialchars($m['title']) ?></h4>
                                <p class="text-sm text-slate-600 font-medium leading-relaxed mb-3"><?= htmlspecialchars($m['code'] ?? 'Module') ?></p>
                                <div class="lms-catalogue-meta">
                                    <span>Module</span>
                                </div>
                                <p class="mt-1 text-sm font-semibold text-emerald-700 group-hover:text-emerald-800 transition-colors">Consulter le parcours →</p>
                            </a>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="lms-panel relative min-w-0 overflow-hidden rounded-[2rem] p-6 md:p-8">
                        <div class="pointer-events-none absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-emerald-600/60 via-emerald-400/30 to-transparent" aria-hidden="true"></div>
                        <header class="mb-4">
                            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                <p class="lms-catalogue-kicker mb-0">Suivi</p>
                                <span class="lms-catalogue-status--active shrink-0 rounded-full border px-2.5 py-1 text-[11px] font-semibold">Actif</span>
                            </div>
                            <h3 class="lms-catalogue-title text-xl sm:text-2xl">Mes formations</h3>
                        </header>
                        <p class="mb-5 min-w-0 max-w-prose text-pretty text-sm font-medium leading-relaxed text-slate-600">
                            Aperçu de vos parcours sur cette communauté. Sous chaque titre : l’état et l’avancement. Pour un parcours terminé avec attestation, un téléchargement est proposé.
                        </p>
                        <?php if ($catalogueSidebarEnrollments !== []): ?>
                        <div class="mb-5 max-h-[min(22rem,52vh)] overflow-hidden rounded-2xl border border-slate-200/80 bg-slate-50/50 p-1.5">
                        <ul class="max-h-[min(20.5rem,48vh)] space-y-2 overflow-y-auto overscroll-contain pr-1 [scrollbar-gutter:stable]" aria-label="Aperçu de vos formations">
                            <?php foreach ($catalogueSidebarEnrollments as $se):
                                $seStatus = (string) ($se['status'] ?? '');
                                $seSlug = trim((string) ($se['course_slug'] ?? ''));
                                $seHref = $seSlug !== '' ? $base . '/formations/' . rawurlencode($seSlug) : $base . '/formations/mes-formations';
                                $sePct = max(0, min(100, (int) ($se['progress_percent'] ?? 0)));
                                $seCertId = (int) ($se['certificate_id'] ?? 0);
                                $seCertifying = (int) ($se['is_certifying'] ?? 0) === 1;
                                $showProgress = in_array($seStatus, ['in_progress', 'assigned', 'failed'], true) || ($sePct > 0 && $seStatus !== 'completed');
                                $statusLabel = match ($seStatus) {
                                    'completed' => 'Validé',
                                    'in_progress' => 'En cours',
                                    'assigned' => $sePct > 0 ? 'En cours' : 'Inscrit',
                                    'pending_approval' => 'Inscription en attente',
                                    'failed' => 'À reprendre',
                                    default => 'Suivi',
                                };
                                $statusClass = match ($seStatus) {
                                    'completed' => 'bg-emerald-500/12 text-emerald-900 border-emerald-500/25',
                                    'in_progress', 'assigned' => 'bg-emerald-500/10 text-emerald-900 border-emerald-500/20',
                                    'pending_approval' => 'bg-violet-500/10 text-violet-900 border-violet-500/25',
                                    'failed' => 'bg-amber-500/12 text-amber-950 border-amber-500/25',
                                    default => 'bg-slate-100 text-slate-700 border-slate-200',
                                };
                                ?>
                            <li class="lms-catalogue-sidebar-item">
                                <a href="<?= htmlspecialchars($seHref) ?>" class="lms-catalogue-sidebar-title break-words hyphens-auto focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/60 focus-visible:ring-offset-2 rounded-md">
                                    <?= htmlspecialchars((string) ($se['course_title'] ?? 'Parcours')) ?>
                                </a>
                                <div class="mt-2.5 flex flex-col gap-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="inline-flex shrink-0 items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold <?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusLabel) ?></span>
                                        <?php if ($showProgress && $seStatus !== 'pending_approval'): ?>
                                        <span class="text-[11px] font-semibold tabular-nums text-slate-600"><?= $sePct ?> %</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($showProgress && $seStatus !== 'pending_approval'): ?>
                                    <div class="lms-catalogue-progress" role="progressbar" aria-valuenow="<?= $sePct ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Progression">
                                        <span style="width: <?= $sePct ?>%"></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($seCertifying && $seCertId > 0 && $seStatus === 'completed'): ?>
                                    <a href="<?= htmlspecialchars($base) ?>/formations/certificate/<?= $seCertId ?>" class="lms-catalogue-btn lms-catalogue-btn--primary w-full sm:w-fit text-[11px] py-2.5">
                                        <svg class="h-3.5 w-3.5 shrink-0 opacity-95" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M12 18v-6"/><path d="m9 15 3 3 3-3"/></svg>
                                        Télécharger l’attestation
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        </div>
                        <?php else: ?>
                        <p class="mb-5 text-sm leading-relaxed text-slate-500">Vous n’avez pas encore de parcours en suivi sur cette communauté. Parcourez le catalogue ou ouvrez l’espace dédié.</p>
                        <?php endif; ?>
                        <div class="mt-2 border-t border-slate-200/80 pt-5">
                            <a href="<?= htmlspecialchars($base) ?>/formations/mes-formations" class="lms-catalogue-btn lms-catalogue-btn--void w-full sm:w-auto">
                                Ouvrir Mes formations
                            </a>
                        </div>
                    </div>
                </section>

                <section id="sessions" class="lms-panel rounded-[2rem] p-6 md:p-8">
                    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-5">
                        <div>
                            <p class="lms-catalogue-kicker mb-1.5">Sessions</p>
                            <h3 class="lms-catalogue-title text-2xl">Créneaux &amp; fenêtres</h3>
                        </div>
                        <p class="text-xs font-medium text-slate-500">Annonces du commandement</p>
                    </div>
                    <div class="lms-catalogue-empty">
                        <span class="lms-catalogue-empty__icon" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        </span>
                        <p class="text-sm font-semibold text-slate-700">Aucune session planifiée</p>
                        <p class="text-sm text-slate-500 max-w-md">Les créneaux apparaîtront ici lorsqu’ils seront annoncés. En attendant, surveillez le forum et le tableau de bord.</p>
                    </div>
                </section>

                <section id="qualifications" class="grid xl:grid-cols-2 gap-6">
                    <div class="lms-panel rounded-[2rem] p-6 md:p-8 flex flex-col">
                        <p class="lms-catalogue-kicker mb-1.5">Préparation</p>
                        <h3 class="lms-catalogue-title text-xl mb-3">État de préparation</h3>
                        <p class="text-slate-600 text-sm mb-5 flex-1">Synthèse de votre avancement sur les parcours inscrits. Retrouvez le détail, la reprise et les attestations dans Mes formations.</p>
                        <a href="<?= htmlspecialchars($base) ?>/formations/mes-formations" class="lms-catalogue-btn lms-catalogue-btn--void w-fit">
                            Voir Mes formations
                        </a>
                    </div>
                    <div class="lms-panel rounded-[2rem] p-6 md:p-8 flex flex-col">
                        <p class="lms-catalogue-kicker mb-1.5">Qualifications</p>
                        <h3 class="lms-catalogue-title text-xl mb-3">Progression &amp; attestations</h3>
                        <p class="text-slate-600 text-sm mb-5 flex-1">Les parcours validés et les attestations disponibles sont regroupés dans votre suivi personnel.</p>
                        <a href="<?= htmlspecialchars($base) ?>/formations/mes-formations" class="text-sm font-semibold text-emerald-700 hover:text-emerald-900 hover:underline w-fit">
                            Consulter le détail →
                        </a>
                    </div>
                </section>

            </main>
        </div>
    </div>
    <?php require base_path('views/partials/community_report_modal.php'); ?>
    <?php require base_path('views/partials/cookie_banner.php'); ?>
</body>
</html>
