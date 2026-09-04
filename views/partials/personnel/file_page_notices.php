<?php
/**
 * Alertes et actions propres au dossier (absences, restrictions, onglets, choix de vue).
 * À afficher sous le bandeau « Dossier personnel », pas au-dessus.
 * L’ancienneté de plateforme (bandeau sous le menu) reste dans le layout.
 *
 * @var bool $personnelFileNoticesIncludeRhSwitcher
 * @var bool $personnelFileNoticesIncludeOperatorTabs
 * @var bool $personnelFileNoticesBare  Sans conteneur largeur (déjà dans un shell).
 */
$personnelFileNoticesIncludeRhSwitcher = !empty($personnelFileNoticesIncludeRhSwitcher);
$personnelFileNoticesIncludeOperatorTabs = !empty($personnelFileNoticesIncludeOperatorTabs);
$personnelFileNoticesBare = !empty($personnelFileNoticesBare);
$noticeShell = isset($personnelFileShell) && is_string($personnelFileShell) && $personnelFileShell !== ''
    ? $personnelFileShell
    : 'max-w-7xl mx-auto px-6 md:px-8';

$openNotice = static function () use ($personnelFileNoticesBare, $noticeShell): void {
    if ($personnelFileNoticesBare) {
        return;
    }
    echo '<div class="' . htmlspecialchars($noticeShell, ENT_QUOTES, 'UTF-8') . ' pt-6">';
};
$closeNotice = static function () use ($personnelFileNoticesBare): void {
    if ($personnelFileNoticesBare) {
        return;
    }
    echo '</div>';
};
?>
<?php if ($personnelFileNoticesIncludeRhSwitcher && !empty($canAccessRhView)): ?>
<?php $openNotice(); ?>
    <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-violet-200 bg-violet-50/70 px-4 py-2.5">
        <p class="text-xs font-semibold text-violet-900">Vous consultez la vue publique de ce dossier.</p>
        <a href="<?= htmlspecialchars($personnelFileBaseUrl, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-1.5 text-[10px] font-black uppercase tracking-wider text-violet-700 hover:text-violet-900">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/></svg>
            Choisir la vue RH
        </a>
    </div>
<?php $closeNotice(); ?>
<?php endif; ?>
<?php if ($personnelModerationStaffLines !== []): ?>
<?php $openNotice(); ?>
    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 shadow-sm" role="region" aria-label="Restrictions d’accès">
        <p class="text-xs font-bold uppercase tracking-wide text-slate-600">Restrictions actuelles (vue encadrement)</p>
        <ul class="mt-2 list-disc pl-5 text-sm text-slate-800 space-y-1">
            <?php foreach ($personnelModerationStaffLines as $line): ?>
                <li><?= htmlspecialchars($line, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php $closeNotice(); ?>
<?php elseif ($personnelModerationMemberBrief !== null): ?>
<?php $openNotice(); ?>
    <div class="rounded-xl border border-amber-200 bg-amber-50/90 px-4 py-3 text-sm text-amber-950 shadow-sm" role="status">
        <?= htmlspecialchars($personnelModerationMemberBrief, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php $closeNotice(); ?>
<?php endif; ?>
<?php if ($canViewAbsences && $personnelActiveAbsences !== []): ?>
<?php $openNotice(); ?>
    <div class="rounded-xl border border-amber-200 bg-amber-50/90 px-4 py-3 text-sm text-amber-950 shadow-sm" role="status">
        <p class="font-semibold">Absence en cours</p>
        <ul class="mt-2 space-y-1.5">
            <?php foreach ($personnelActiveAbsences as $absRow): ?>
                <?php
                $absStart = (string) ($absRow['starts_on'] ?? '');
                $absEnd = $absRow['ends_on'] ?? null;
                $absStartTs = $absStart !== '' ? strtotime($absStart) : false;
                $absStartFr = $absStartTs !== false ? date('d/m/Y', $absStartTs) : $absStart;
                if ($absEnd === null || $absEnd === '') {
                    $absPeriod = $absStartFr !== '' ? ('À partir du ' . $absStartFr . ' — durée non précisée') : 'Durée non précisée';
                } else {
                    $absEndTs = strtotime((string) $absEnd);
                    $absEndFr = $absEndTs !== false ? date('d/m/Y', $absEndTs) : (string) $absEnd;
                    $absPeriod = $absStartFr . ' → ' . $absEndFr;
                }
                $absReasonKey = (string) ($absRow['reason'] ?? 'autre');
                $absReasonLab = (string) ($personnelAbsenceReasonLabels[$absReasonKey] ?? 'Autre');
                $absNote = trim((string) ($absRow['note'] ?? ''));
                ?>
                <li>
                    <?= htmlspecialchars($absPeriod, ENT_QUOTES, 'UTF-8') ?>
                    <span class="text-amber-900/80"> — <?= htmlspecialchars($absReasonLab, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if ($absNote !== ''): ?>
                        <span class="block text-xs text-amber-900/75 mt-0.5"><?= htmlspecialchars($absNote, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if (!empty($personnelIsSelf)): ?>
            <p class="mt-3 text-xs">
                <a href="<?= htmlspecialchars(url('personnel/mon-espace-rh'), ENT_QUOTES, 'UTF-8') ?>#absences" class="font-semibold text-amber-950 underline decoration-amber-300 underline-offset-2 hover:decoration-amber-600">Gérer mes absences dans l’espace RH</a>
            </p>
        <?php endif; ?>
    </div>
<?php $closeNotice(); ?>
<?php endif; ?>
<?php if ($personnelFileNoticesIncludeOperatorTabs && !empty($viewerIsPersonnelSubject)): ?>
<?php $openNotice(); ?>
    <?php
    $active_tab = 'identity';
    $base_path = 'personnel/me';
    require base_path('views/partials/personnel/operator_tabs.php');
    ?>
<?php $closeNotice(); ?>
<?php endif; ?>
<?php
$personnelFileNoticesIncludeRhSwitcher = false;
$personnelFileNoticesIncludeOperatorTabs = false;
$personnelFileNoticesBare = false;
?>
