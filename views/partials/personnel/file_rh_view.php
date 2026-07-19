<?php
/**
 * Vue RH du dossier personnel : hero d’identification + tableau
 * administratif en plein largeur (shell sans max-w-7xl).
 *
 * Variables attendues depuis file.php (déjà résolues).
 * Optionnel : $personnelFileShell (classes conteneur plein largeur).
 */
$rhGateUrl = $personnelFileBaseUrl;
$rhPublicUrl = $personnelFileBaseUrl . '?view=public';
$rhRawAccountStatus = (string) ($targetUser['status'] ?? '');

/** @var list<array{level: string, title: string, body: string}> $rhProfileAlerts */
$rhProfileAlerts = [];
$sectionsCritiquesRh = is_array($completeness['sections_critiques'] ?? null) ? $completeness['sections_critiques'] : [];
$gradeIssuesRh = is_array($gradeValidationIssues ?? null) ? $gradeValidationIssues : [];

foreach ($sectionsCritiquesRh as $crit) {
    $crit = trim((string) $crit);
    if ($crit === '') {
        continue;
    }
    $rhProfileAlerts[] = [
        'level' => 'error',
        'title' => 'Point à corriger',
        'body' => $crit,
    ];
}

foreach ($gradeIssuesRh as $gi) {
    $msg = trim((string) ($gi['message'] ?? ''));
    if ($msg === '') {
        continue;
    }
    $lvl = (($gi['type'] ?? '') === 'error') ? 'error' : 'warning';
    $rhProfileAlerts[] = [
        'level' => $lvl,
        'title' => $lvl === 'error' ? 'Incohérence de grade' : 'Vérification de grade',
        'body' => $msg,
    ];
}

if (!empty($rpDossierNeedsAttention)) {
    $rhProfileAlerts[] = [
        'level' => 'warning',
        'title' => 'Identité personnage incomplète',
        'body' => 'Le nom affiché du dossier personnage ou la nationalité personnage manque : à compléter pour l’organigramme et les listes opérationnelles.',
    ];
}

if (!$isDeployableFile) {
    $rhProfileAlerts[] = [
        'level' => 'warning',
        'title' => 'Profil non déployable',
        'body' => 'Ce dossier est marqué comme non déployable : la personne n’apparaît pas comme disponible pour un déploiement.',
    ];
}

if ($rhRawAccountStatus !== '' && $rhRawAccountStatus !== 'active') {
    $rhProfileAlerts[] = [
        'level' => 'error',
        'title' => 'Compte non actif',
        'body' => 'Statut du compte : ' . $accountStatusFr($rhRawAccountStatus) . '.',
    ];
}

$rhCriticalKeys = ['identity_matricule', 'identity_unit', 'security_clearance', 'assignment_role'];
$rhDetails = is_array($completeness['details'] ?? null) ? $completeness['details'] : [];
$rhFieldLabels = is_array($completenessCheckLabels ?? null) ? $completenessCheckLabels : [];
foreach ($rhDetails as $fieldKey => $fieldOk) {
    if (!empty($fieldOk)) {
        continue;
    }
    if (in_array((string) $fieldKey, $rhCriticalKeys, true)) {
        continue;
    }
    $lbl = trim((string) ($rhFieldLabels[$fieldKey] ?? ''));
    if ($lbl === '') {
        continue;
    }
    $rhProfileAlerts[] = [
        'level' => 'info',
        'title' => 'Élément manquant',
        'body' => $lbl . ' n’est pas encore renseigné.',
    ];
}

$rhAlertErrorCount = 0;
$rhAlertWarnCount = 0;
foreach ($rhProfileAlerts as $a) {
    if (($a['level'] ?? '') === 'error') {
        $rhAlertErrorCount++;
    } elseif (($a['level'] ?? '') === 'warning') {
        $rhAlertWarnCount++;
    }
}
?>
<?php
$rhShell = isset($personnelFileShell) && is_string($personnelFileShell) && $personnelFileShell !== ''
    ? $personnelFileShell
    : 'w-full max-w-none px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12';
?>
<style>
/* Vue RH : plein largeur dans le main, tableau qui exploite la hauteur viewport */
.personnel-file--rh-full .personnel-sheets {
    max-height: min(calc(100dvh - 18rem), 72rem);
}
.personnel-file--rh-full .personnel-sheets__table th:nth-child(3),
.personnel-file--rh-full .personnel-sheets__table td:nth-child(3) {
    min-width: 16rem;
}
.personnel-file--rh-full .personnel-sheets__table th:nth-child(4),
.personnel-file--rh-full .personnel-sheets__table td:nth-child(4) {
    min-width: 12rem;
}
</style>
<div class="<?= htmlspecialchars($rhShell, ENT_QUOTES, 'UTF-8') ?> space-y-6 pt-2 pb-8">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="<?= htmlspecialchars($rhGateUrl, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-1.5 text-[10px] font-black uppercase tracking-wider text-slate-500 hover:text-slate-800">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            Changer de vue
        </a>
        <a href="<?= htmlspecialchars($rhPublicUrl, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-[10px] font-black uppercase tracking-wider text-slate-700 hover:bg-slate-50">
            Vue publique du dossier
        </a>
    </div>

    <!-- Hero RH (bandeau identification) -->
    <section class="w-full rounded-2xl bg-slate-900 bg-gradient-to-br from-slate-900 via-slate-800 to-emerald-950/40 border border-slate-700/50 shadow-sm overflow-hidden">
        <div class="px-5 py-5 md:px-8 md:py-7 lg:px-10">
            <div class="flex flex-wrap items-center gap-5 lg:gap-8">
                <div class="flex shrink-0 items-center gap-3">
                    <div class="relative h-16 w-16 md:h-[4.5rem] md:w-[4.5rem] shrink-0 overflow-hidden rounded-2xl border-2 border-slate-600/50 bg-slate-800" title="Photo de compte">
                        <?php if (!empty($avatarUrl)): ?>
                        <img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Photo de compte" loading="eager" decoding="async" class="h-full w-full object-cover" data-img-fallback="avatar" data-img-initials="<?= htmlspecialchars(function_exists('user_display_initials') ? user_display_initials((string) $displayName, 2) : '?', ENT_QUOTES, 'UTF-8') ?>" data-img-label="Photo de compte indisponible" />
                        <?php else: ?>
                        <div class="flex h-full w-full items-center justify-center text-slate-500">
                            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($portraitUrl)): ?>
                    <div class="relative hidden sm:block h-16 w-12 md:h-[4.5rem] md:w-[3.4rem] shrink-0 overflow-hidden rounded-xl border-2 border-slate-600/50 bg-slate-950" title="Portrait opérateur">
                        <img src="<?= htmlspecialchars($portraitUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Portrait opérateur" loading="eager" decoding="async" class="h-full w-full object-cover object-top" data-img-fallback="portrait" data-img-initials="<?= htmlspecialchars(function_exists('user_display_initials') ? user_display_initials((string) $displayName, 2) : '?', ENT_QUOTES, 'UTF-8') ?>" data-img-label="Portrait opérateur indisponible" />
                    </div>
                    <?php endif; ?>
                </div>
                <div class="min-w-0 grow">
                    <p class="text-[9px] font-black uppercase tracking-[0.35em] text-emerald-400/90 italic mb-1">Vue RH — dossier personnel</p>
                    <h1 class="text-xl md:text-2xl lg:text-3xl font-black uppercase tracking-tight text-white italic truncate">
                        <?= htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8') ?>
                    </h1>
                    <div class="mt-1 flex flex-wrap items-baseline gap-x-4 gap-y-1">
                        <?php if (!empty($callsign)): ?>
                        <span class="text-sm font-bold text-slate-300 italic"><?= htmlspecialchars((string) $callsign, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                        <?php if (!empty($unitName)): ?>
                        <span class="text-xs text-slate-400"><?= htmlspecialchars((string) $unitName, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded text-[10px] font-black uppercase <?= $rhRawAccountStatus === 'active' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-slate-600/30 text-slate-400' ?>">
                        <span class="w-1.5 h-1.5 rounded-full <?= $rhRawAccountStatus === 'active' ? 'bg-emerald-400 animate-pulse' : 'bg-slate-500' ?>"></span>
                        <?= htmlspecialchars($accountStatusFr($rhRawAccountStatus), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <?php if ($isDeployableFile): ?>
                    <span class="inline-flex px-2.5 py-1 rounded text-[10px] font-black uppercase bg-emerald-500/20 text-emerald-400">Déployable</span>
                    <?php else: ?>
                    <span class="inline-flex px-2.5 py-1 rounded text-[10px] font-black uppercase bg-amber-500/15 text-amber-200">Non déployable</span>
                    <?php endif; ?>
                    <?php if ($rhProfileAlerts !== []): ?>
                    <span class="inline-flex px-2.5 py-1 rounded text-[10px] font-black uppercase <?= $rhAlertErrorCount > 0 ? 'bg-rose-500/20 text-rose-300' : 'bg-amber-500/20 text-amber-200' ?>">
                        <?= (int) count($rhProfileAlerts) ?> alerte<?= count($rhProfileAlerts) > 1 ? 's' : '' ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="mt-5 grid grid-cols-2 gap-4 border-t border-white/10 pt-4 sm:grid-cols-3 md:grid-cols-6 lg:gap-6">
                <?php if (!empty($matricule) && !empty($showMatriculePublic)): ?>
                <div>
                    <p class="text-[7px] font-black text-slate-500 uppercase tracking-widest mb-0.5">Matricule</p>
                    <p class="text-sm font-black text-white italic"><?= htmlspecialchars((string) $matricule, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <?php endif; ?>
                <div>
                    <p class="text-[7px] font-black text-slate-500 uppercase tracking-widest mb-0.5">Grade / rang</p>
                    <p class="text-sm font-black text-white italic"><?= $effectiveRankDisplay !== '' ? htmlspecialchars($effectiveRankDisplay, ENT_QUOTES, 'UTF-8') : '—' ?></p>
                </div>
                <div>
                    <p class="text-[7px] font-black text-slate-500 uppercase tracking-widest mb-0.5">Habilitation</p>
                    <p class="text-sm font-black text-emerald-400 italic"><?= $clearanceLevel !== '' ? htmlspecialchars($clearanceLevel, ENT_QUOTES, 'UTF-8') : '—' ?></p>
                </div>
                <div>
                    <p class="text-[7px] font-black text-slate-500 uppercase tracking-widest mb-0.5">Préparation</p>
                    <p class="text-sm font-black text-white"><?= $readiness !== null ? $readiness . ' %' : '—' ?></p>
                </div>
                <?php if (!empty($enlistmentFormatted)): ?>
                <div>
                    <p class="text-[7px] font-black text-slate-500 uppercase tracking-widest mb-0.5">Enrôlement</p>
                    <p class="text-sm font-black text-white"><?= htmlspecialchars((string) $enlistmentFormatted, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <?php endif; ?>
                <div>
                    <p class="text-[7px] font-black text-slate-500 uppercase tracking-widest mb-0.5">Dossier complété</p>
                    <p class="text-sm font-black text-white"><?= (int) $completenessScore ?> %</p>
                </div>
            </div>
        </div>
    </section>

    <?php if ($rhProfileAlerts !== []): ?>
    <section class="rounded-2xl border <?= $rhAlertErrorCount > 0 ? 'border-rose-200 bg-rose-50/80' : 'border-amber-200 bg-amber-50/80' ?> p-5 md:p-6 shadow-sm" aria-labelledby="rh-profile-alerts-title" role="region">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.22em] <?= $rhAlertErrorCount > 0 ? 'text-rose-800' : 'text-amber-900' ?>">Suivi dossier</p>
                <h2 id="rh-profile-alerts-title" class="mt-1 text-lg font-black tracking-tight text-slate-900">
                    Alertes liées à ce profil
                </h2>
                <p class="mt-1 text-sm text-slate-600">
                    <?php if ($rhAlertErrorCount > 0): ?>
                    <?= (int) $rhAlertErrorCount ?> point<?= $rhAlertErrorCount > 1 ? 's' : '' ?> à corriger
                    <?php if ($rhAlertWarnCount > 0): ?> · <?= (int) $rhAlertWarnCount ?> vigilance<?= $rhAlertWarnCount > 1 ? 's' : '' ?><?php endif; ?>
                    <?php else: ?>
                    Éléments à surveiller ou à compléter sur ce dossier.
                    <?php endif; ?>
                </p>
            </div>
            <?php if (!empty($canEditProfile)): ?>
            <a href="<?= htmlspecialchars(url('personnel/' . (int) ($targetUser['id'] ?? 0) . '/edit'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-xl bg-slate-900 px-3.5 py-2 text-[10px] font-black uppercase tracking-wider text-white hover:bg-slate-800">
                Corriger le dossier
            </a>
            <?php endif; ?>
        </div>
        <ul class="mt-4 grid gap-2.5 lg:grid-cols-2">
            <?php foreach ($rhProfileAlerts as $alert):
                $lvl = (string) ($alert['level'] ?? 'info');
                $rowCls = match ($lvl) {
                    'error' => 'border-rose-200 bg-white text-rose-950',
                    'warning' => 'border-amber-200 bg-white text-amber-950',
                    default => 'border-slate-200 bg-white text-slate-800',
                };
                $badgeCls = match ($lvl) {
                    'error' => 'bg-rose-100 text-rose-800',
                    'warning' => 'bg-amber-100 text-amber-900',
                    default => 'bg-slate-100 text-slate-700',
                };
                $badgeLab = match ($lvl) {
                    'error' => 'À corriger',
                    'warning' => 'Vigilance',
                    default => 'À compléter',
                };
            ?>
            <li class="flex flex-wrap items-start gap-3 rounded-xl border px-3.5 py-3 <?= $rowCls ?>">
                <span class="inline-flex shrink-0 rounded-md px-2 py-0.5 text-[10px] font-black uppercase tracking-wider <?= $badgeCls ?>"><?= htmlspecialchars($badgeLab, ENT_QUOTES, 'UTF-8') ?></span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold"><?= htmlspecialchars((string) ($alert['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mt-0.5 text-sm leading-relaxed opacity-90"><?= htmlspecialchars((string) ($alert['body'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php else: ?>
    <div class="rounded-2xl border border-emerald-200 bg-emerald-50/70 px-4 py-3 text-sm text-emerald-950 shadow-sm" role="status">
        <span class="font-semibold">Aucune alerte dossier</span>
        <span class="text-emerald-900/80"> — les points de contrôle principaux sont en ordre sur ce profil.</span>
    </div>
    <?php endif; ?>

    <!-- Tableau administratif pleine page -->
    <div>
        <?php $tableauAdminStandalone = true; ?>
        <?php require base_path('views/partials/personnel/file_tableau_admin_tab.php'); ?>
    </div>
</div>
