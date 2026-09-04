<?php
declare(strict_types=1);

$tenant = is_array($operatorTenant ?? null) ? $operatorTenant : [];
$user = is_array($operatorUser ?? null) ? $operatorUser : [];
$profile = is_array($operatorProfile ?? null) ? $operatorProfile : [];
$units = is_array($operatorUnits ?? null) ? $operatorUnits : [];
$terminals = is_array($operatorTerminals ?? null) ? $operatorTerminals : [];
$mission = is_array($operatorMission ?? null) ? $operatorMission : null;
$h = static fn (mixed $value): string => htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8');
$value = static fn (mixed $raw): string => trim((string) $raw) !== '' ? $h($raw) : 'Non renseigné';
$date = static function (mixed $raw) use ($h): string {
    $timestamp = strtotime((string) $raw);
    return $timestamp ? date('d/m/Y à H:i', $timestamp) : $h($raw ?: 'Non renseigné');
};
?>

<div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-700">Consultation personnelle</p>
        <h1 class="mt-2 text-2xl font-black text-slate-950">Bonjour <?= $value($user['display_name'] ?? $user['callsign'] ?? '') ?></h1>
        <p class="mt-2 text-sm text-slate-600">Cet espace affiche uniquement vos données et les informations opérationnelles partagées par votre communauté. Il ne donne aucun droit d’administration.</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-black text-slate-950">Communauté & équipe</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div><dt class="text-xs font-bold uppercase tracking-wider text-slate-500">Tenant</dt><dd class="mt-1 font-semibold text-slate-900"><?= $value($tenant['name'] ?? '') ?></dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wider text-slate-500">Indicatif</dt><dd class="mt-1 font-semibold text-slate-900"><?= $value($user['callsign'] ?? $profile['callsign'] ?? '') ?></dd></div>
                <div class="sm:col-span-2"><dt class="text-xs font-bold uppercase tracking-wider text-slate-500">Équipe(s)</dt><dd class="mt-1 font-semibold text-slate-900"><?php if ($units === []): ?>Non affecté<?php else: ?><?= implode(' · ', array_map(static fn (array $unit): string => $h($unit['name'] ?? $unit['code'] ?? ''), $units)) ?><?php endif; ?></dd></div>
            </dl>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-black text-slate-950">Mission en cours</h2>
            <?php if ($mission === null): ?>
                <p class="mt-4 text-sm text-slate-500">Aucune mission n’est actuellement déclarée en direct.</p>
            <?php else: ?>
                <p class="mt-4 text-xl font-black text-slate-950"><?= $value($mission['operation_name'] ?? $mission['title'] ?? '') ?></p>
                <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div><dt class="text-xs font-bold uppercase text-slate-500">Code mission</dt><dd class="mt-1 font-semibold"><?= $value($mission['mission_code'] ?? '') ?></dd></div>
                    <div><dt class="text-xs font-bold uppercase text-slate-500">Classification</dt><dd class="mt-1 font-semibold"><?= $value($mission['classification'] ?? '') ?></dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs font-bold uppercase text-slate-500">Task force</dt><dd class="mt-1 font-semibold"><?= $value($mission['task_force_name'] ?? '') ?></dd></div>
                </dl>
            <?php endif; ?>
        </section>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-lg font-black text-slate-950">Mes données ATAK</h2><a class="text-sm font-bold text-emerald-700 hover:underline" href="<?= $h(url('atak/premiere-liaison')) ?>">Configurer ATAK</a></div>
        <?php if ($terminals === []): ?>
            <p class="mt-4 text-sm text-slate-500">Aucun terminal terrain ATAK n’est associé à votre compte.</p>
        <?php else: ?>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <?php foreach ($terminals as $terminal): ?>
                    <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex items-start justify-between gap-3"><h3 class="font-black text-slate-950"><?= $value($terminal['terminal_label'] ?? 'Terminal ATAK') ?></h3><span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-bold text-emerald-800"><?= $value($terminal['status'] ?? '') ?></span></div>
                        <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                            <div><dt class="text-xs font-bold uppercase text-slate-500">Terminal</dt><dd class="mt-1 break-all font-mono text-sm"><?= $value($terminal['terminal_uid'] ?? '') ?></dd></div>
                            <div><dt class="text-xs font-bold uppercase text-slate-500">Modèle ATAK</dt><dd class="mt-1 text-sm font-semibold"><?= $value($terminal['platform_label'] ?? $terminal['terminal_type'] ?? '') ?></dd></div>
                            <div><dt class="text-xs font-bold uppercase text-slate-500">Versions ATAK</dt><dd class="mt-1 text-sm font-semibold"><?= $value($terminal['mod_version'] ?? '') ?> · ext. <?= $value($terminal['extension_version'] ?? '') ?></dd></div>
                            <div><dt class="text-xs font-bold uppercase text-slate-500">Dernière activité</dt><dd class="mt-1 text-sm font-semibold"><?= $date($terminal['last_seen_at'] ?? $terminal['updated_at'] ?? '') ?></dd></div>
                        </dl>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-lg font-black text-slate-950">Mes données RP</h2><a class="text-sm font-bold text-emerald-700 hover:underline" href="<?= $h(url('personnel/me')) ?>">Voir mon dossier</a></div>
        <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div><dt class="text-xs font-bold uppercase text-slate-500">Personnage</dt><dd class="mt-1 font-semibold"><?= $value($profile['character_name'] ?? '') ?></dd></div>
            <div><dt class="text-xs font-bold uppercase text-slate-500">Fonction</dt><dd class="mt-1 font-semibold"><?= $value($profile['rp_operational_function'] ?? $profile['primary_role'] ?? '') ?></dd></div>
            <div><dt class="text-xs font-bold uppercase text-slate-500">Statut</dt><dd class="mt-1 font-semibold"><?= $value($profile['rp_followup_status'] ?? $profile['operator_status'] ?? '') ?></dd></div>
            <div><dt class="text-xs font-bold uppercase text-slate-500">Progression</dt><dd class="mt-1 font-semibold"><?= trim((string) ($profile['rp_followup_progress'] ?? '')) !== '' ? (int) $profile['rp_followup_progress'] . ' %' : 'Non renseignée' ?></dd></div>
        </dl>
    </section>
</div>
