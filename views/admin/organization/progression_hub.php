<?php
declare(strict_types=1);

$stats = is_array($progressionStats ?? null) ? $progressionStats : [];
$canConfigure = !empty($canConfigure);
$canValidate = !empty($canValidate);
$canCallsign = !empty($canCallsign);
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$schemaReady = !empty($stats['schema_ready']);
?>
<div class="max-w-5xl mx-auto space-y-6 px-4 py-6">
    <header class="space-y-2">
        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Back-office · Personnel</p>
        <h1 class="text-2xl font-black text-slate-900">Progression &amp; carrière</h1>
        <p class="text-sm text-slate-600 max-w-3xl">
            Moteur multi-tenant : indicatifs séquentiels, parcours, conditions, validations humaines, qualifications et audit.
            Lot 1 livré : fondations schéma + générateur d’indicatifs + hub. Les éditeurs de parcours et l’évaluation CRON complète suivent.
        </p>
        <a href="<?= $h(url('back-office/organisation-effectifs')) ?>" class="text-sm font-semibold text-emerald-800 hover:underline">← Centre effectifs</a>
    </header>

    <?php
    $notice_tone = 'info';
    $notice_title = 'Réutilisation de l’existant';
    $notice_body = 'Le moteur s’appuie sur le dossier personnel, les grades référentiel, le LMS (<code>REQUIRED_TRAINING</code>), '
        . '<code>personnel_qualifications</code>, l’ancienneté configurable, le playtime Arma, les RSVP check-in, '
        . 'et le modèle matricule (curseur atomique) pour les indicatifs. '
        . 'Voir le rapport d’audit technique.';
    include base_path('views/partials/bo_dsfr_notice.php');
    ?>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Parcours</p>
            <p class="mt-1 text-2xl font-black text-slate-900"><?= (int) ($stats['tracks'] ?? 0) ?></p>
            <p class="text-xs text-slate-500"><?= (int) ($stats['published_tracks'] ?? 0) ?> publié(s)</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Demandes en attente</p>
            <p class="mt-1 text-2xl font-black text-slate-900"><?= (int) ($stats['pending_requests'] ?? 0) ?></p>
            <p class="text-xs text-slate-500">Éligibles / validation</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Séquences indicatifs</p>
            <p class="mt-1 text-2xl font-black text-slate-900"><?= (int) ($stats['sequences'] ?? 0) ?></p>
            <p class="text-xs text-slate-500"><?= (int) ($stats['holds'] ?? 0) ?> gel(s) actif(s)</p>
        </div>
    </div>

    <section class="grid gap-3 md:grid-cols-2">
        <?php if ($canCallsign): ?>
        <a href="<?= $h(url('back-office/organisation/indicatifs')) ?>" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:border-emerald-300 transition">
            <p class="text-sm font-black text-slate-900">Règles d’indicatifs</p>
            <p class="mt-1 text-xs text-slate-600">Séquences NUMERIC / PREFIX / PATTERN, plages réservées, historique.</p>
        </a>
        <?php endif; ?>
        <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 p-5">
            <p class="text-sm font-black text-slate-700">Parcours &amp; étapes</p>
            <p class="mt-1 text-xs text-slate-500">Éditeur visuel + conditions ALL/ANY — prochain lot<?= $schemaReady ? ' (tables prêtes)' : '' ?>.</p>
        </div>
        <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 p-5">
            <p class="text-sm font-black text-slate-700">Demandes &amp; validations</p>
            <p class="mt-1 text-xs text-slate-500">Workflow multi-niveaux formateur → commandement.</p>
        </div>
        <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 p-5">
            <p class="text-sm font-black text-slate-700">Simulation</p>
            <p class="mt-1 text-xs text-slate-500">Expliquer précisément pourquoi un membre est éligible ou non.</p>
        </div>
    </section>
</div>
