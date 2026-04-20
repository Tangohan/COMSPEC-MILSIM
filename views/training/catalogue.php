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
                    <p><strong>Repère.</strong> Catalogue mêlant les parcours de votre communauté et, le cas échéant, des parcours proposés sur l’ensemble du site — recherche et filtres ci-dessous. Pour reprendre un parcours déjà commencé ou vos attestations, ouvrez <a href="<?= htmlspecialchars($base) ?>/formations/mes-formations" class="text-emerald-700 font-semibold hover:underline">Mes formations</a>. Pour signaler un problème sur un parcours, ouvrez sa fiche puis utilisez le lien en bas de page.</p>
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
                    <div id="catalogue" class="lms-panel min-w-0 rounded-[2rem] p-6 md:p-8">
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
                                   placeholder="Rechercher un titre, un code…"
                                   class="flex-1 min-w-0 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-800 placeholder:text-slate-400">
                            <button type="submit" class="px-6 py-3 rounded-2xl bg-slate-900 text-white text-[10px] font-black uppercase tracking-[0.2em] hover:bg-emerald-600 transition-colors">
                                Rechercher
                            </button>
                        </form>
                        <form method="get" action="<?= htmlspecialchars($formationsUrl) ?>" class="flex flex-wrap gap-2 mb-6 items-center">
                            <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 mr-1">Origine :</span>
                            <a href="<?= htmlspecialchars($buildFormationsUrl($filterCategory, $filterSearch, '', $filterLevel, $filterDuration, $filterModality, $filterAvailability)) ?>"
                               class="px-4 py-2 rounded-full border text-[10px] font-black tracking-[0.18em] uppercase transition-colors <?= $filterParcours === '' ? 'border-emerald-500 bg-emerald-500/10 text-emerald-800' : 'border-slate-200 bg-white text-slate-700 hover:border-emerald-200' ?>">
                                Tous
                            </a>
                            <a href="<?= htmlspecialchars($buildFormationsUrl($filterCategory, $filterSearch, 'communaute', $filterLevel, $filterDuration, $filterModality, $filterAvailability)) ?>"
                               class="px-4 py-2 rounded-full border text-[10px] font-black tracking-[0.18em] uppercase transition-colors <?= $filterParcours === 'communaute' ? 'border-emerald-500 bg-emerald-500/10 text-emerald-800' : 'border-slate-200 bg-white text-slate-700 hover:border-emerald-200' ?>">
                                Communauté
                            </a>
                            <a href="<?= htmlspecialchars($buildFormationsUrl($filterCategory, $filterSearch, 'plateforme', $filterLevel, $filterDuration, $filterModality, $filterAvailability)) ?>"
                               class="px-4 py-2 rounded-full border text-[10px] font-black tracking-[0.18em] uppercase transition-colors <?= $filterParcours === 'plateforme' ? 'border-violet-500 bg-violet-500/10 text-violet-900' : 'border-slate-200 bg-white text-slate-700 hover:border-violet-200' ?>">
                                Toute la plateforme
                            </a>
                        </form>
                        <form method="get" action="<?= htmlspecialchars($formationsUrl) ?>" class="flex flex-wrap gap-2 mb-4 items-center">
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
                            <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 mr-1">Thème :</span>
                            <a href="<?= htmlspecialchars($buildFormationsUrl(null, $filterSearch, $filterParcours, $filterLevel, $filterDuration, $filterModality, $filterAvailability)) ?>"
                               class="px-4 py-2 rounded-full border text-[10px] font-black tracking-[0.18em] uppercase transition-colors <?= $filterCategory === null ? 'border-emerald-500 bg-emerald-500/10 text-emerald-800' : 'border-slate-200 bg-white text-slate-700 hover:border-emerald-200' ?>">
                                Tous
                            </a>
                            <?php foreach ($filterCategories as $cat): ?>
                            <a href="<?= htmlspecialchars($buildFormationsUrl($cat, $filterSearch, $filterParcours, $filterLevel, $filterDuration, $filterModality, $filterAvailability)) ?>"
                               class="px-4 py-2 rounded-full border text-[10px] font-black tracking-[0.18em] uppercase transition-colors <?= ($filterCategory === $cat) ? 'border-emerald-500 bg-emerald-500/10 text-emerald-800' : 'border-slate-200 bg-white text-slate-700 hover:border-emerald-200' ?>">
                                <?= htmlspecialchars($cat) ?>
                            </a>
                            <?php endforeach; ?>
                        </form>
                        <form method="get" action="<?= htmlspecialchars($formationsUrl) ?>" class="mb-8 rounded-2xl border border-slate-200 bg-slate-50/70 p-3.5 md:p-4">
                            <?php if ($filterCategory): ?><input type="hidden" name="category" value="<?= htmlspecialchars($filterCategory) ?>"><?php endif; ?>
                            <?php if ($filterSearch): ?><input type="hidden" name="search" value="<?= htmlspecialchars((string) $filterSearch) ?>"><?php endif; ?>
                            <?php if ($filterParcours !== ''): ?><input type="hidden" name="parcours" value="<?= htmlspecialchars($filterParcours) ?>"><?php endif; ?>
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Filtres utilitaires</p>
                                <a href="<?= htmlspecialchars($buildFormationsUrl($filterCategory, $filterSearch, $filterParcours)) ?>" class="text-[11px] font-bold text-emerald-700 underline decoration-emerald-200 hover:text-emerald-900">Réinitialiser</a>
                            </div>
                            <div class="grid gap-2.5 sm:grid-cols-2">
                                <select name="niveau" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800">
                                    <option value="">Niveau : tous</option>
                                    <?php foreach ($filterLevelOptions as $levelOpt): ?>
                                    <option value="<?= htmlspecialchars($levelOpt) ?>" <?= $filterLevel === $levelOpt ? 'selected' : '' ?>>Niveau : <?= htmlspecialchars(ucfirst($levelOpt)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="duree" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800">
                                    <option value="">Durée : toutes</option>
                                    <option value="court" <?= $filterDuration === 'court' ? 'selected' : '' ?>>Durée : courte (≤ 30 min)</option>
                                    <option value="moyen" <?= $filterDuration === 'moyen' ? 'selected' : '' ?>>Durée : moyenne (31 à 90 min)</option>
                                    <option value="long" <?= $filterDuration === 'long' ? 'selected' : '' ?>>Durée : longue (&gt; 90 min)</option>
                                </select>
                                <select name="modalite" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800">
                                    <option value="">Modalité : toutes</option>
                                    <?php foreach ($filterModalityOptions as $modalityOpt): ?>
                                    <option value="<?= htmlspecialchars($modalityOpt) ?>" <?= $filterModality === $modalityOpt ? 'selected' : '' ?>>Modalité : <?= htmlspecialchars(ucfirst($modalityOpt)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="disponibilite" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800">
                                    <option value="">Disponibilité : toutes</option>
                                    <option value="ouvert" <?= $filterAvailability === 'ouvert' ? 'selected' : '' ?>>Ouvertes à l’inscription</option>
                                    <option value="non_commence" <?= $filterAvailability === 'non_commence' ? 'selected' : '' ?>>Non commencées</option>
                                    <option value="en_cours" <?= $filterAvailability === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                                    <option value="termine" <?= $filterAvailability === 'termine' ? 'selected' : '' ?>>Terminées</option>
                                </select>
                            </div>
                            <div class="mt-3">
                                <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-[11px] font-black uppercase tracking-[0.17em] text-white hover:bg-emerald-600 transition-colors">Appliquer les filtres</button>
                            </div>
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
                            ?>
                            <a href="<?= htmlspecialchars($base) ?>/formations/<?= htmlspecialchars($c['slug']) ?>" class="lms-course-card group block bg-white rounded-3xl border border-slate-200 p-5 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/80 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-100">
                                <?php if ($cardStateSr !== ''): ?>
                                <span class="sr-only"><?= htmlspecialchars($cardStateSr, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                                <div class="flex items-start justify-between gap-4 mb-5">
                                    <div class="w-12 h-12 rounded-2xl <?= $cc['bg'] ?> <?= $cc['border'] ?> flex items-center justify-center">
                                        <span class="text-[11px] font-black tracking-widest <?= $cc['text'] ?>"><?= htmlspecialchars(mb_substr($code, 0, 4)) ?></span>
                                    </div>
                                    <div class="text-right space-y-1.5 max-w-[58%]">
                                        <?php if ($scopeBadge !== ''): ?>
                                        <span class="inline-block px-2.5 py-1 rounded-full text-[10px] font-black tracking-[0.12em] uppercase leading-tight <?= $lmsScopeRow === 'platform'
                                            ? 'bg-violet-500/12 text-violet-900 border border-violet-400/30'
                                            : 'bg-emerald-500/10 text-emerald-900 border border-emerald-500/25' ?>"><?= htmlspecialchars($scopeBadge) ?></span>
                                        <?php endif; ?>
                                        <span class="block text-[10px] font-black tracking-[0.18em] uppercase text-slate-500"><?= htmlspecialchars($cat) ?></span>
                                    </div>
                                </div>
                                <h4 class="text-lg font-black tracking-tight uppercase mb-2 text-slate-900 transition-colors group-hover:text-emerald-800"><?= htmlspecialchars($c['title']) ?></h4>
                                <p class="text-[11px] text-slate-600 font-medium leading-relaxed mb-4"><?= !empty($c['short_description']) ? htmlspecialchars($c['short_description']) : 'Parcours publié dans le catalogue.' ?></p>
                                <?php
                                $metaLevel = trim((string) ($c['level'] ?? ''));
                                $metaWeekly = (int) ($c['estimated_weekly_minutes'] ?? 0);
                                $metaFormat = trim((string) ($c['learning_format'] ?? $c['modality'] ?? 'mixte'));
                                ?>
                                <div class="mb-3 grid grid-cols-3 gap-2 text-[11px] font-semibold">
                                    <span class="rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-slate-700">Niveau: <?= htmlspecialchars($metaLevel !== '' ? ucfirst($metaLevel) : 'À définir') ?></span>
                                    <span class="rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-slate-700">Charge: <?= $metaWeekly > 0 ? $metaWeekly . ' min/sem.' : 'À définir' ?></span>
                                    <span class="rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-slate-700">Format: <?= htmlspecialchars($metaFormat !== '' ? ucfirst($metaFormat) : 'Mixte') ?></span>
                                </div>
                                <?php require base_path('views/training/partials/catalogue_card_status_overlay.php'); ?>
                                <?php
                                $progressBadgeLabel = 'Non commencé';
                                $progressBadgeClass = 'bg-slate-100 text-slate-700 border-slate-200';
                                if ($cardState === 'en_cours') {
                                    $progressBadgeLabel = 'En cours';
                                    $progressBadgeClass = 'bg-sky-100 text-sky-900 border-sky-200';
                                } elseif ($cardState === 'valide') {
                                    $progressBadgeLabel = 'Terminé';
                                    $progressBadgeClass = 'bg-emerald-100 text-emerald-900 border-emerald-200';
                                }
                                ?>
                                <div class="mb-3">
                                    <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-black uppercase tracking-[0.14em] <?= $progressBadgeClass ?>"><?= $progressBadgeLabel ?></span>
                                </div>
                                <?php if ($mins > 0): ?>
                                <div class="flex items-center justify-between text-[11px] font-black uppercase tracking-[0.1em] pt-1 border-t border-slate-100">
                                    <span class="text-slate-400">Durée estimée</span>
                                    <span class="text-slate-800 tabular-nums"><?= $mins ?> min</span>
                                </div>
                                <?php endif; ?>
                                <p class="mt-3 text-[11px] font-black uppercase tracking-[0.13em] text-slate-500 group-hover:text-emerald-700 transition-colors">Consulter le parcours</p>
                                <?php if (!empty($c['enrollment']) && $cardState === null): ?>
                                <div class="mt-2 rounded-xl bg-slate-50 border border-slate-100 px-3 py-2">
                                    <span class="text-[10px] font-bold <?= ($c['enrollment']['status'] ?? '') === 'completed' ? 'text-emerald-700' : 'text-amber-700' ?>">
                                        <?= ($c['enrollment']['status'] ?? '') === 'completed' ? 'Inscription : terminé' : 'Progression : ' . (int) ($c['progress_percent'] ?? 0) . ' %' ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                            </a>
                            <?php endforeach; ?>

                            <?php if ($training_legacy_enabled): ?>
                            <?php foreach ($legacyModules as $m):
                                $code = $m['code'] ?? ('MOD-' . (int) $m['id']);
                            ?>
                            <a href="<?= htmlspecialchars($base) ?>/formations/<?= htmlspecialchars($m['slug']) ?>" class="lms-course-card group block bg-white rounded-3xl border border-slate-200 p-5 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/80 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-100">
                                <div class="flex items-start justify-between gap-4 mb-5">
                                    <div class="w-12 h-12 rounded-2xl bg-sky-500/10 border border-sky-500/20 flex items-center justify-center">
                                        <span class="text-[11px] font-black tracking-widest text-sky-700"><?= htmlspecialchars(mb_substr($code, 0, 4)) ?></span>
                                    </div>
                                    <span class="inline-block px-2.5 py-1 rounded-full text-[8px] font-black tracking-[0.18em] uppercase bg-slate-100 text-slate-600 border border-slate-200/80">Ancien format</span>
                                </div>
                                <h4 class="text-lg font-black tracking-tight uppercase mb-2 text-slate-900 transition-colors group-hover:text-emerald-800"><?= htmlspecialchars($m['title']) ?></h4>
                                <p class="text-[11px] text-slate-600 font-medium leading-relaxed mb-5"><?= htmlspecialchars($m['code'] ?? 'Module') ?></p>
                                <div class="flex items-center justify-between text-[10px] font-black uppercase tracking-[0.14em] pt-1 border-t border-slate-100">
                                    <span class="text-slate-400">Type</span>
                                    <span class="text-slate-800">Module</span>
                                </div>
                                <p class="mt-3 text-[10px] font-black uppercase tracking-wider text-slate-400 group-hover:text-emerald-600 transition-colors">Consulter le parcours</p>
                            </a>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="lms-panel relative min-w-0 overflow-hidden rounded-[2rem] p-6 md:p-8">
                        <div class="pointer-events-none absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-emerald-500/55 via-emerald-500/15 to-transparent" aria-hidden="true"></div>
                        <header class="mb-5">
                            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                <p class="text-[9px] font-black uppercase tracking-[0.35em] text-slate-400">Suivi</p>
                                <span class="shrink-0 rounded-full border border-emerald-500/25 bg-emerald-500/10 px-2.5 py-1 text-[9px] font-black uppercase tracking-[0.16em] text-emerald-800">Actif</span>
                            </div>
                            <h3 class="text-xl font-black uppercase tracking-tight text-slate-900 sm:text-2xl">Mes formations</h3>
                        </header>
                        <p class="mb-5 min-w-0 max-w-prose text-pretty text-sm font-medium leading-relaxed text-slate-600">
                            Aperçu de vos parcours sur cette communauté. Sous chaque titre, l’état et l’avancement. Pour un parcours terminé avec attestation, un lien de téléchargement est proposé.
                        </p>
                        <?php if ($catalogueSidebarEnrollments !== []): ?>
                        <div class="mb-5 max-h-[min(22rem,52vh)] overflow-hidden rounded-2xl border border-slate-200/80 bg-slate-50/60 p-1.5 shadow-inner">
                        <ul class="max-h-[min(20.5rem,48vh)] space-y-2 overflow-y-auto overscroll-contain pr-1 [scrollbar-gutter:stable]" aria-label="Aperçu de vos formations">
                            <?php foreach ($catalogueSidebarEnrollments as $se):
                                $seStatus = (string) ($se['status'] ?? '');
                                $seSlug = trim((string) ($se['course_slug'] ?? ''));
                                $seHref = $seSlug !== '' ? $base . '/formations/' . rawurlencode($seSlug) : $base . '/formations/mes-formations';
                                $sePct = max(0, min(100, (int) ($se['progress_percent'] ?? 0)));
                                $seCertId = (int) ($se['certificate_id'] ?? 0);
                                $seCertifying = (int) ($se['is_certifying'] ?? 0) === 1;
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
                                    'in_progress', 'assigned' => 'bg-sky-500/10 text-sky-900 border-sky-500/20',
                                    'pending_approval' => 'bg-violet-500/10 text-violet-900 border-violet-500/25',
                                    'failed' => 'bg-amber-500/12 text-amber-950 border-amber-500/25',
                                    default => 'bg-slate-100 text-slate-700 border-slate-200',
                                };
                                ?>
                            <li class="min-w-0 rounded-xl border border-slate-200/90 bg-white/95 px-3.5 py-3 shadow-sm transition-colors hover:border-emerald-300/40 hover:shadow-md">
                                <a href="<?= htmlspecialchars($seHref) ?>" class="block rounded-md text-left text-[13px] font-black uppercase leading-snug tracking-tight text-slate-900 break-words hyphens-auto hover:text-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/60 focus-visible:ring-offset-2">
                                    <?= htmlspecialchars((string) ($se['course_title'] ?? 'Parcours')) ?>
                                </a>
                                <div class="mt-3 flex flex-col gap-2.5">
                                    <?php if ($seStatus !== 'completed' && $seStatus !== 'pending_approval'): ?>
                                    <div class="inline-flex w-fit max-w-full flex-wrap items-center gap-2 rounded-full border border-slate-200/90 bg-slate-50/90 px-2.5 py-1.5">
                                        <span class="inline-flex shrink-0 items-center rounded-full border px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.12em] <?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusLabel) ?></span>
                                        <span class="h-3.5 w-px shrink-0 bg-slate-200" aria-hidden="true"></span>
                                        <span class="text-[10px] font-bold tabular-nums leading-none text-slate-600"><?= $sePct ?> %</span>
                                    </div>
                                    <?php else: ?>
                                    <span class="inline-flex w-fit max-w-full items-center rounded-full border px-2.5 py-1 text-[9px] font-black uppercase tracking-[0.12em] <?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusLabel) ?></span>
                                    <?php endif; ?>
                                    <?php if ($seCertifying && $seCertId > 0 && $seStatus === 'completed'): ?>
                                    <a href="<?= htmlspecialchars($base) ?>/formations/certificate/<?= $seCertId ?>" class="inline-flex min-h-[2.75rem] w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-3 py-2.5 text-[10px] font-black uppercase tracking-[0.14em] text-white shadow-sm shadow-emerald-900/15 transition-colors hover:bg-emerald-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 sm:w-fit sm:justify-start">
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
                            <a href="<?= htmlspecialchars($base) ?>/formations/mes-formations" class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-slate-900 px-6 py-3.5 text-[10px] font-black uppercase tracking-[0.2em] text-white transition-colors hover:bg-emerald-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 sm:w-auto">
                                Ouvrir Mes formations
                            </a>
                        </div>
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
    <?php require base_path('views/partials/community_report_modal.php'); ?>
    <?php require base_path('views/partials/cookie_banner.php'); ?>
</body>
</html>
