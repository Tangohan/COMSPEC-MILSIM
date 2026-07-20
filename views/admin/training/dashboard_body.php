<?php
declare(strict_types=1);
$stats = $stats ?? ['courses' => 0, 'enrollments' => 0, 'completed' => 0, 'expiringCount' => 0];
$expiring = $expiring ?? [];
$trainingCanExportFull = !empty($trainingCanExportFull);
$expiringCount = (int) ($stats['expiringCount'] ?? 0);
$successRateTenant = is_array($successRateTenant ?? null) ? $successRateTenant : [];
$successRatePlatform = is_array($successRatePlatform ?? null) ? $successRatePlatform : [];
$tenantRateDisplay = array_key_exists('rate_percent', $successRateTenant) && $successRateTenant['rate_percent'] !== null
    ? rtrim(rtrim(number_format((float) $successRateTenant['rate_percent'], 1, ',', ''), '0'), ',') . ' %'
    : '—';
?>
<header class="lms-panel rounded-[2rem] p-6 md:p-8 overflow-hidden relative">
    <div class="absolute top-0 left-0 w-full h-[2px] bg-gradient-to-r from-emerald-600/80 via-emerald-500/25 to-transparent" aria-hidden="true"></div>
    <div class="flex flex-col xl:flex-row xl:items-end xl:justify-between gap-8">
        <div class="max-w-2xl">
            <p class="lms-catalogue-kicker lms-catalogue-kicker--accent mb-3">Pilotage</p>
            <h1 class="lms-catalogue-title text-3xl md:text-4xl mb-4">Vue d’ensemble des formations</h1>
            <div class="h-[1px] w-20 bg-slate-900/10 mb-4" aria-hidden="true"></div>
            <p class="text-slate-600 text-sm font-medium leading-relaxed">
                Suivez l’activité pédagogique de la communauté : contenus à publier, inscriptions à valider, attestations et compétences.
                Les raccourcis ci-dessous et le menu sombre à gauche mènent aux mêmes espaces.
            </p>
        </div>
        <div class="grid grid-cols-2 xl:grid-cols-4 gap-3 min-w-full xl:min-w-[560px]" aria-label="Indicateurs">
            <a href="<?= htmlspecialchars(training_lms_admin_url('courses')) ?>" class="bg-slate-50/90 rounded-2xl border border-slate-200/90 p-4 no-underline text-inherit transition hover:border-emerald-300 hover:bg-emerald-50/50">
                <p class="text-xs font-semibold text-slate-500 mb-1.5">Parcours</p>
                <p class="text-2xl font-bold tracking-tight tabular-nums text-slate-900"><?= (int) $stats['courses'] ?></p>
            </a>
            <a href="<?= htmlspecialchars(training_lms_admin_url('enrollments')) ?>" class="bg-slate-50/90 rounded-2xl border border-slate-200/90 p-4 no-underline text-inherit transition hover:border-emerald-300 hover:bg-emerald-50/50">
                <p class="text-xs font-semibold text-slate-500 mb-1.5">Inscriptions</p>
                <p class="text-2xl font-bold tracking-tight tabular-nums text-slate-900"><?= (int) $stats['enrollments'] ?></p>
            </a>
            <a href="<?= htmlspecialchars(training_lms_admin_url('reports')) ?>" class="bg-slate-50/90 rounded-2xl border border-slate-200/90 p-4 no-underline text-inherit transition hover:border-emerald-300 hover:bg-emerald-50/50">
                <p class="text-xs font-semibold text-slate-500 mb-1.5">Taux de réussite</p>
                <p class="text-2xl font-bold tracking-tight tabular-nums text-emerald-700"><?= htmlspecialchars($tenantRateDisplay) ?></p>
            </a>
            <a href="<?= htmlspecialchars(training_lms_admin_url('enrollments') . '?expiring=1') ?>" class="bg-slate-50/90 rounded-2xl border border-slate-200/90 p-4 no-underline text-inherit transition hover:border-emerald-300 hover:bg-emerald-50/50">
                <p class="text-xs font-semibold text-slate-500 mb-1.5">À surveiller</p>
                <p class="text-2xl font-bold tracking-tight tabular-nums <?= $expiringCount > 0 ? 'text-amber-600' : 'text-slate-900' ?>"><?= $expiringCount ?></p>
            </a>
        </div>
    </div>
</header>

<?php require base_path('views/admin/training/partials/success_rate_panel.php'); ?>

<section class="lms-panel rounded-[2rem] p-5 md:p-6" aria-label="Priorités du jour">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <h2 class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-600 m-0">Priorités du jour</h2>
        <span class="text-xs text-slate-500">À traiter en premier</span>
    </div>
    <div class="grid gap-3 md:grid-cols-3">
        <a href="<?= htmlspecialchars(training_lms_admin_url('enrollments') . '?status=pending_approval') ?>" class="rounded-xl border border-emerald-200/80 bg-emerald-50/70 px-4 py-3 no-underline text-inherit transition hover:border-emerald-400 hover:bg-emerald-50">
            <p class="text-xs font-black uppercase tracking-wide text-emerald-900 m-0">Inscriptions en attente</p>
            <p class="text-sm text-emerald-900/75 mt-1 mb-0 leading-snug">Approuver ou refuser les demandes d’accès aux parcours.</p>
        </a>
        <a href="<?= htmlspecialchars(training_lms_admin_url('enrollments') . '?expiring=1') ?>" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 no-underline text-inherit transition hover:border-amber-400">
            <p class="text-xs font-black uppercase tracking-wide text-amber-900 m-0">Validités à surveiller</p>
            <p class="text-sm text-amber-900/80 mt-1 mb-0 leading-snug">
                <?= $expiringCount > 0
                    ? $expiringCount . ' inscription' . ($expiringCount > 1 ? 's' : '') . ' expire' . ($expiringCount > 1 ? 'nt' : '') . ' sous 30 jours ou déjà expirée' . ($expiringCount > 1 ? 's' : '') . '.'
                    : 'Aucune échéance urgente pour le moment.' ?>
            </p>
        </a>
        <a href="<?= htmlspecialchars(training_lms_admin_url('feedback')) ?>" class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 no-underline text-inherit transition hover:border-emerald-300 hover:bg-emerald-50/40">
            <p class="text-xs font-black uppercase tracking-wide text-slate-800 m-0">Retours des apprenants</p>
            <p class="text-sm text-slate-600 mt-1 mb-0 leading-snug">Lire les commentaires laissés après les leçons.</p>
        </a>
    </div>
</section>

<?php
$mode = 'overview';
$pilotageOnHubPage = true;
require base_path('views/training/partials/lms_pilotage_staff_nav.php');
?>

<?php if ($trainingCanExportFull): ?>
<section class="lms-panel rounded-[2rem] p-5 md:p-6">
    <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900 mb-2">Sauvegarde &amp; transfert</h2>
    <p class="text-sm text-slate-600 mb-4 max-w-2xl leading-relaxed">
        Pour récupérer tout le contenu d’un parcours (dossier téléchargeable, réimportable dans le studio), ouvrez le catalogue d’édition puis utilisez « Télécharger le dossier » sur la ligne concernée.
    </p>
    <div class="flex flex-wrap gap-3">
        <a href="<?= htmlspecialchars(url(training_studio_path() . '/echange/importer')) ?>" class="tc-btn-primary tc-btn-ghost">Importer une formation</a>
        <a href="<?= htmlspecialchars(training_lms_admin_url('courses')) ?>" class="tc-btn-primary tc-btn-emerald">Ouvrir le catalogue d’édition</a>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($expiring)): ?>
<section class="rounded-[2rem] border border-amber-200/90 bg-amber-50/90 p-6 shadow-inner">
    <h2 class="text-sm font-black uppercase tracking-[0.2em] text-amber-900/90 mb-2">Inscriptions à surveiller</h2>
    <p class="text-xs text-amber-950/80 mb-4">Expirent bientôt ou déjà expirées (aperçu limité à 10).</p>
    <ul class="space-y-3 m-0 p-0 list-none">
        <?php foreach (array_slice($expiring, 0, 10) as $e): ?>
        <li class="flex flex-wrap gap-x-2 gap-y-1 text-sm text-slate-800 border-b border-amber-200/50 pb-2 last:border-0">
            <span class="font-semibold"><?= htmlspecialchars((string) ($e['course_title'] ?? '')) ?></span>
            <span class="text-slate-500">—</span>
            <span><?= htmlspecialchars((string) ($e['display_name'] ?? $e['email'] ?? '')) ?></span>
            <span class="text-amber-800 text-xs font-semibold tabular-nums"><?= date('d/m/Y', strtotime((string) ($e['expires_at'] ?? ''))) ?></span>
        </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<?php if (\App\Core\Gate::getInstance()->allows('admin.system')): ?>
<p class="text-sm text-slate-500 pt-2 mb-0">
    <a href="<?= url('admin') ?>" class="font-semibold text-slate-700 underline decoration-slate-300 hover:text-emerald-800">← Tableau de bord plateforme</a>
</p>
<?php endif; ?>
