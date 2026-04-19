<?php require base_path('views/admin/training/partials/command_shell_open.php'); ?>
<?php
$trainerValidationTail = $trainerValidationTail ?? [];
$pedagogyPathwayCatalog = $pedagogyPathwayCatalog ?? [];
$pedagogyPathwayRows = $pedagogyPathwayRows ?? [];
?>
<header class="tc-panel p-6 md:p-8">
    <p class="tc-kicker">Vue instructeur</p>
    <h1 class="tc-hero-title mb-4">Validation et suivi</h1>
    <p class="text-slate-600 text-sm max-w-3xl leading-relaxed">
        Dernières décisions enregistrées sur le terrain et étapes de montée en puissance associées à votre compte.
    </p>
</header>

<section class="tc-panel p-6 overflow-x-auto">
    <h2 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900 mb-3">Journal récent</h2>
    <table class="min-w-full text-sm">
        <thead><tr class="text-left text-xs uppercase text-slate-500 border-b border-slate-200"><th class="py-2 pr-4">Date</th><th class="py-2 pr-4">Action</th><th class="py-2 pr-4">Personnel</th></tr></thead>
        <tbody>
        <?php foreach ($trainerValidationTail as $row): ?>
        <tr class="border-b border-slate-100">
            <td class="py-2 pr-4 whitespace-nowrap"><?= htmlspecialchars((string) ($row['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td class="py-2 pr-4"><?= htmlspecialchars((string) ($row['action_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td class="py-2 pr-4"><?= htmlspecialchars((string) ($row['target_display_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($trainerValidationTail === []): ?><p class="text-sm text-slate-500 mt-2">Aucune entrée pour le moment.</p><?php endif; ?>
</section>

<section class="grid gap-4 lg:grid-cols-2">
    <article class="tc-panel p-5">
        <h2 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900">Référentiel de progression</h2>
        <ul class="mt-3 space-y-1 text-sm text-slate-700">
            <?php foreach ($pedagogyPathwayCatalog as $st): ?>
            <li>• <?= htmlspecialchars((string) ($st['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    </article>
    <article class="tc-panel p-5">
        <h2 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900">Vos étapes enregistrées</h2>
        <ul class="mt-3 space-y-1 text-sm text-slate-600">
            <?php foreach ($pedagogyPathwayRows as $r): ?>
            <li><?= htmlspecialchars((string) ($r['stage_slug'] ?? ''), ENT_QUOTES, 'UTF-8') ?> — <span class="font-semibold"><?= htmlspecialchars((string) ($r['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></li>
            <?php endforeach; ?>
        </ul>
        <?php if ($pedagogyPathwayRows === []): ?><p class="text-xs text-slate-500 mt-2">Aucune étape en base pour l’instant.</p><?php endif; ?>
    </article>
</section>

<section class="tc-panel p-6">
    <div class="flex flex-wrap gap-3">
        <a href="<?= htmlspecialchars(training_lms_admin_url('competences/commandement'), ENT_QUOTES, 'UTF-8') ?>" class="tc-btn-primary tc-btn-emerald">Vue commandement</a>
        <a href="<?= htmlspecialchars(training_lms_admin_url('competences/formateur'), ENT_QUOTES, 'UTF-8') ?>" class="tc-btn-primary tc-btn-ghost">Espace formateur</a>
        <a href="<?= htmlspecialchars(url('formations/competences'), ENT_QUOTES, 'UTF-8') ?>" class="tc-btn-primary tc-btn-ghost">Vue personnelle</a>
    </div>
</section>
<?php require base_path('views/admin/training/partials/command_shell_close.php'); ?>
