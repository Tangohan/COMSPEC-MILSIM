<?php
declare(strict_types=1);

$stats = is_array($progressionStats ?? null) ? $progressionStats : [];
$canConfigure = !empty($canConfigure);
$canValidate = !empty($canValidate);
$canCallsign = !empty($canCallsign);
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$schemaReady = !empty($stats['schema_ready']);
$axesReady = !empty($stats['axes_schema_ready']);
?>
<div class="max-w-5xl mx-auto space-y-6 px-4 py-6">
    <header class="space-y-2">
        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Back-office · Personnel</p>
        <h1 class="text-2xl font-black text-slate-900">Progression &amp; carrière</h1>
        <p class="text-sm text-slate-600 max-w-3xl">
            Quatre axes séparés : <strong>grade/niveau</strong>, <strong>fonction/poste</strong>,
            <strong>qualification</strong> (validité admin ≠ currency) et <strong>capacité opérationnelle</strong>.
            Un Opérateur confirmé peut être ACTING Team Leader, Medic VALID mais NON CURRENT → readiness 82&nbsp;% et NON DEPLOYABLE.
        </p>
        <a href="<?= $h(url('back-office/organisation-effectifs')) ?>" class="text-sm font-semibold text-emerald-800 hover:underline">← Centre effectifs</a>
    </header>

    <?php
    $notice_tone = 'info';
    $notice_title = 'Axes RH (ne pas fusionner)';
    $notice_body = '<ol class="mt-2 list-decimal pl-5 space-y-1">'
        . '<li><strong>Grade / niveau</strong> — carrière (ex. Opérateur confirmé).</li>'
        . '<li><strong>Fonction / billet</strong> — poste ORBAT, y compris intérim ACTING (ne change pas le grade).</li>'
        . '<li><strong>Qualification</strong> — VALID administrativement <em>et</em> CURRENT (pratique récente).</li>'
        . '<li><strong>Capacité opérationnelle</strong> — disponibilité + currencies + effectifs → déployable ou non.</li>'
        . '</ol>';
    include base_path('views/partials/bo_dsfr_notice.php');
    ?>

    <?php if ($schemaReady || $axesReady): ?>
    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Parcours</p>
            <p class="mt-1 text-2xl font-black text-slate-900"><?= (int) ($stats['tracks'] ?? 0) ?></p>
            <p class="text-xs text-slate-500"><?= (int) ($stats['published_tracks'] ?? 0) ?> publié(s)</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Demandes</p>
            <p class="mt-1 text-2xl font-black text-slate-900"><?= (int) ($stats['pending_requests'] ?? 0) ?></p>
            <p class="text-xs text-slate-500">en attente</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">NON CURRENT</p>
            <p class="mt-1 text-2xl font-black text-amber-800"><?= (int) ($stats['non_current_quals'] ?? 0) ?></p>
            <p class="text-xs text-slate-500">quals à remédier</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Billets ORBAT</p>
            <p class="mt-1 text-2xl font-black text-slate-900"><?= (int) ($stats['billets'] ?? 0) ?></p>
            <p class="text-xs text-slate-500"><?= (int) ($stats['holds'] ?? 0) ?> hold(s) actifs</p>
        </div>
    </section>
    <?php endif; ?>

    <section class="grid gap-3 md:grid-cols-2">
        <?php if ($canCallsign): ?>
        <a href="<?= $h(url('back-office/organisation/indicatifs')) ?>" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:border-emerald-300 transition">
            <p class="text-sm font-black text-slate-900">Règles d’indicatifs</p>
            <p class="mt-1 text-xs text-slate-600">Séquences NUMERIC / PREFIX / PATTERN, plages réservées, historique.</p>
        </a>
        <?php endif; ?>
        <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 p-5">
            <p class="text-sm font-black text-slate-700">Currency &amp; billets ORBAT</p>
            <p class="mt-1 text-xs text-slate-500">
                <?= $axesReady
                    ? 'Schéma lot 2 actif : currency_days, practice log, orbat_billets (ex. 4/6 Riflemen), waivers, boards, mentors.'
                    : 'Migration axes à exécuter (currency, billets, waivers, boards).' ?>
            </p>
        </div>
        <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 p-5">
            <p class="text-sm font-black text-slate-700">Parcours &amp; étapes</p>
            <p class="mt-1 text-xs text-slate-500">Éditeur visuel + conditions ALL/ANY — prochain lot<?= $schemaReady ? ' (tables prêtes)' : '' ?>.</p>
        </div>
        <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 p-5">
            <p class="text-sm font-black text-slate-700">Demandes &amp; validations</p>
            <p class="mt-1 text-xs text-slate-500">Workflow multi-niveaux, promotion board, waivers, date d’effet différée<?= $canValidate ? '' : '' ?>.</p>
        </div>
    </section>
</div>
