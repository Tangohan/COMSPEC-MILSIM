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
$rhEditUrl = url('personnel/' . (int) ($targetUser['id'] ?? 0) . '/edit?return_view=rh');
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
/** @var list<string> $rhOptionalMissingLabels */
$rhOptionalMissingLabels = [];
foreach ($rhDetails as $fieldKey => $fieldOk) {
    if (!empty($fieldOk)) {
        continue;
    }
    if (in_array((string) $fieldKey, $rhCriticalKeys, true)) {
        continue;
    }
    $lbl = trim((string) ($rhFieldLabels[$fieldKey] ?? ''));
    if ($lbl !== '') {
        $rhOptionalMissingLabels[] = $lbl;
    }
}

$rhPriorityAlerts = array_values(array_filter(
    $rhProfileAlerts,
    static fn (array $a): bool => in_array($a['level'] ?? '', ['error', 'warning'], true)
));
$rhAlertErrorCount = count(array_filter($rhPriorityAlerts, static fn (array $a): bool => ($a['level'] ?? '') === 'error'));
$rhAlertWarnCount = count(array_filter($rhPriorityAlerts, static fn (array $a): bool => ($a['level'] ?? '') === 'warning'));
?>
<?php
$rhShell = isset($personnelFileShell) && is_string($personnelFileShell) && $personnelFileShell !== ''
    ? $personnelFileShell
    : 'w-full max-w-none px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12';
?>
<style>
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
<div class="<?= htmlspecialchars($rhShell, ENT_QUOTES, 'UTF-8') ?> space-y-5 pt-2 pb-8">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="<?= htmlspecialchars($rhGateUrl, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-slate-900">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            Changer de vue
        </a>
        <div class="flex flex-wrap items-center gap-2">
            <a href="<?= htmlspecialchars($rhPublicUrl, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                Vue publique
            </a>
            <?php if (!empty($canEditProfile)): ?>
            <a href="<?= htmlspecialchars($rhEditUrl, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800">
                Modifier le dossier
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($personnelModerationStaffLines !== []): ?>
    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800" role="region" aria-label="Restrictions d’accès">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">Restrictions actives</p>
        <ul class="mt-1.5 list-disc pl-5 space-y-0.5">
            <?php foreach ($personnelModerationStaffLines as $line): ?>
                <li><?= htmlspecialchars($line, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if ($canViewAbsences && $personnelActiveAbsences !== []): ?>
    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950" role="status">
        <p class="font-semibold">Absence en cours</p>
        <ul class="mt-1.5 space-y-1">
            <?php foreach ($personnelActiveAbsences as $absRow): ?>
                <?php
                $absStart = (string) ($absRow['starts_on'] ?? '');
                $absEnd = $absRow['ends_on'] ?? null;
                $absStartTs = $absStart !== '' ? strtotime($absStart) : false;
                $absStartFr = $absStartTs !== false ? date('d/m/Y', $absStartTs) : $absStart;
                if ($absEnd === null || $absEnd === '') {
                    $absPeriod = $absStartFr !== '' ? ('À partir du ' . $absStartFr) : 'Durée non précisée';
                } else {
                    $absEndTs = strtotime((string) $absEnd);
                    $absEndFr = $absEndTs !== false ? date('d/m/Y', $absEndTs) : (string) $absEnd;
                    $absPeriod = $absStartFr . ' → ' . $absEndFr;
                }
                $absReasonKey = (string) ($absRow['reason'] ?? 'autre');
                $absReasonLab = (string) ($personnelAbsenceReasonLabels[$absReasonKey] ?? 'Autre');
                ?>
                <li><?= htmlspecialchars($absPeriod, ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($absReasonLab, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Hero RH -->
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
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-emerald-400/90 mb-1">Vue RH — dossier personnel</p>
                    <h1 class="text-xl md:text-2xl lg:text-3xl font-bold tracking-tight text-white truncate">
                        <?= htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8') ?>
                    </h1>
                    <div class="mt-1 flex flex-wrap items-baseline gap-x-4 gap-y-1 text-sm">
                        <?php if (!empty($callsign)): ?>
                        <span class="font-medium text-slate-300"><?= htmlspecialchars((string) $callsign, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                        <?php if (!empty($unitName)): ?>
                        <span class="text-slate-400"><?= htmlspecialchars((string) $unitName, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold <?= $rhRawAccountStatus === 'active' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-600/30 text-slate-400' ?>">
                        <?= htmlspecialchars($accountStatusFr($rhRawAccountStatus), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <span class="inline-flex px-2.5 py-1 rounded-md text-xs font-semibold <?= $isDeployableFile ? 'bg-emerald-500/20 text-emerald-300' : 'bg-amber-500/15 text-amber-200' ?>">
                        <?= $isDeployableFile ? 'Déployable' : 'Non déployable' ?>
                    </span>
                    <?php if ($rhPriorityAlerts !== []): ?>
                    <span class="inline-flex px-2.5 py-1 rounded-md text-xs font-semibold <?= $rhAlertErrorCount > 0 ? 'bg-rose-500/20 text-rose-200' : 'bg-amber-500/20 text-amber-200' ?>">
                        <?= (int) count($rhPriorityAlerts) ?> alerte<?= count($rhPriorityAlerts) > 1 ? 's' : '' ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="mt-5 grid grid-cols-2 gap-4 border-t border-white/10 pt-4 sm:grid-cols-3 md:grid-cols-6 lg:gap-6">
                <?php if (!empty($matricule) && !empty($showMatriculePublic)): ?>
                <div>
                    <p class="text-[10px] font-medium text-slate-500 uppercase tracking-wide mb-0.5">Matricule</p>
                    <p class="text-sm font-semibold text-white"><?= htmlspecialchars((string) $matricule, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <?php endif; ?>
                <div>
                    <p class="text-[10px] font-medium text-slate-500 uppercase tracking-wide mb-0.5">Grade / rang</p>
                    <p class="text-sm font-semibold text-white"><?= $effectiveRankDisplay !== '' ? htmlspecialchars($effectiveRankDisplay, ENT_QUOTES, 'UTF-8') : '—' ?></p>
                </div>
                <div>
                    <p class="text-[10px] font-medium text-slate-500 uppercase tracking-wide mb-0.5">Habilitation</p>
                    <p class="text-sm font-semibold text-emerald-400"><?= $clearanceLevel !== '' ? htmlspecialchars($clearanceLevel, ENT_QUOTES, 'UTF-8') : '—' ?></p>
                </div>
                <div>
                    <p class="text-[10px] font-medium text-slate-500 uppercase tracking-wide mb-0.5">Préparation</p>
                    <p class="text-sm font-semibold text-white"><?= $readiness !== null ? $readiness . ' %' : '—' ?></p>
                </div>
                <?php if (!empty($enlistmentFormatted)): ?>
                <div>
                    <p class="text-[10px] font-medium text-slate-500 uppercase tracking-wide mb-0.5">Enrôlement</p>
                    <p class="text-sm font-semibold text-white"><?= htmlspecialchars((string) $enlistmentFormatted, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <?php endif; ?>
                <div>
                    <p class="text-[10px] font-medium text-slate-500 uppercase tracking-wide mb-0.5">Dossier complété</p>
                    <p class="text-sm font-semibold text-white"><?= (int) $completenessScore ?> %</p>
                </div>
            </div>
        </div>
    </section>

    <?php if ($rhPriorityAlerts !== [] || $rhOptionalMissingLabels !== []): ?>
    <details class="group rounded-xl border border-slate-200 bg-white shadow-sm" <?= $rhAlertErrorCount > 0 ? 'open' : '' ?>>
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-sm font-semibold text-slate-900 [&::-webkit-details-marker]:hidden">
            <span>
                Suivi dossier
                <?php if ($rhPriorityAlerts !== []): ?>
                <span class="ml-2 inline-flex rounded-md px-2 py-0.5 text-xs font-semibold <?= $rhAlertErrorCount > 0 ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-900' ?>">
                    <?= (int) count($rhPriorityAlerts) ?> point<?= count($rhPriorityAlerts) > 1 ? 's' : '' ?> prioritaire<?= count($rhPriorityAlerts) > 1 ? 's' : '' ?>
                </span>
                <?php endif; ?>
                <?php if ($rhOptionalMissingLabels !== []): ?>
                <span class="ml-1 text-xs font-normal text-slate-500">· <?= count($rhOptionalMissingLabels) ?> champ<?= count($rhOptionalMissingLabels) > 1 ? 's' : '' ?> optionnel<?= count($rhOptionalMissingLabels) > 1 ? 's' : '' ?></span>
                <?php endif; ?>
            </span>
            <svg class="h-4 w-4 shrink-0 text-slate-400 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
        </summary>
        <div class="border-t border-slate-100 px-4 py-3 space-y-3">
            <?php if ($rhPriorityAlerts !== []): ?>
            <ul class="space-y-2">
                <?php foreach ($rhPriorityAlerts as $alert):
                    $lvl = (string) ($alert['level'] ?? 'info');
                    $dotCls = match ($lvl) {
                        'error' => 'bg-rose-500',
                        'warning' => 'bg-amber-500',
                        default => 'bg-slate-400',
                    };
                ?>
                <li class="flex gap-3 text-sm text-slate-800">
                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full <?= $dotCls ?>" aria-hidden="true"></span>
                    <div>
                        <p class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($alert['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="mt-0.5 text-slate-600 leading-relaxed"><?= htmlspecialchars((string) ($alert['body'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <?php if ($rhOptionalMissingLabels !== []): ?>
            <div class="rounded-lg bg-slate-50 px-3 py-2.5 text-sm text-slate-700">
                <p class="font-medium text-slate-900">Champs optionnels non renseignés</p>
                <p class="mt-1 text-slate-600"><?= htmlspecialchars(implode(' · ', array_slice($rhOptionalMissingLabels, 0, 12)), ENT_QUOTES, 'UTF-8') ?><?= count($rhOptionalMissingLabels) > 12 ? '…' : '' ?></p>
            </div>
            <?php endif; ?>
        </div>
    </details>
    <?php else: ?>
    <div class="rounded-xl border border-emerald-200 bg-emerald-50/70 px-4 py-2.5 text-sm text-emerald-950" role="status">
        Aucune alerte prioritaire — les points de contrôle principaux sont en ordre.
    </div>
    <?php endif; ?>

    <div>
        <?php $tableauAdminStandalone = true; ?>
        <?php require base_path('views/partials/personnel/file_tableau_admin_tab.php'); ?>
    </div>
</div>
