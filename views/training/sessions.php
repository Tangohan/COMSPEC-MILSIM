<?php
declare(strict_types=1);
$base = url('');
$title = $title ?? 'Sessions & suivi';
$totalModules = (int) ($totalModules ?? 0);

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
    $lmsBootMessage = 'Chargement du suivi…';
    require base_path('views/training/partials/lms_page_boot_overlay.php');
    ?>
    <div class="lms-grain"></div>

    <div class="min-h-screen relative z-10">
        <div class="grid lg:grid-cols-[290px_1fr] min-h-screen">
            <?php
            $activeNav = 'sessions';
            $lmsSidebarShowPilotageLinks = true;
            require base_path('views/training/partials/lms_command_sidebar.php');
            ?>

            <main class="p-5 md:p-8 lg:p-10 space-y-8">
                <div class="lms-infobanner" role="note">
                    <span class="lms-infobanner__icon" aria-hidden="true">
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                    <p><strong>Repère.</strong> Créneaux annoncés, état de préparation et qualifications regroupés ici. Pour parcourir les parcours, ouvrez le <a href="<?= htmlspecialchars($base) ?>/formations" class="text-emerald-700 font-semibold hover:underline">catalogue</a>. Pour reprendre un parcours commencé ou télécharger une attestation, ouvrez <a href="<?= htmlspecialchars($base) ?>/formations/mes-formations" class="text-emerald-700 font-semibold hover:underline">Mes formations</a>.</p>
                </div>

                <header class="lms-panel rounded-[2rem] p-6 md:p-8 overflow-hidden relative">
                    <div class="absolute top-0 left-0 w-full h-[2px] bg-gradient-to-r from-emerald-600/80 via-emerald-500/25 to-transparent"></div>
                    <div class="max-w-3xl">
                        <p class="lms-catalogue-kicker lms-catalogue-kicker--accent mb-3">Suivi &amp; sessions</p>
                        <h2 class="lms-catalogue-title text-3xl md:text-4xl mb-4">
                            Sessions, préparation &amp; qualifications
                        </h2>
                        <div class="h-[1px] w-20 bg-slate-900/10 mb-4"></div>
                        <p class="text-slate-600 text-sm font-medium leading-relaxed max-w-2xl">
                            Retrouvez les créneaux planifiés par le commandement, votre état de préparation et la progression des qualifications.
                        </p>
                    </div>
                </header>

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
                    <div id="preparation" class="lms-panel rounded-[2rem] p-6 md:p-8 flex flex-col">
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
    <?php
    $lmsModuleEntryAuto = 'formation';
    require base_path('views/partials/lms_module_entry_modal.php');
    ?>
</body>
</html>
