<?php require base_path('views/admin/training/partials/command_shell_open.php'); ?>
<?php
$pedagogyAuditTail = $pedagogyAuditTail ?? [];
$pedagogyPathwayCatalog = $pedagogyPathwayCatalog ?? [];
$pedagogyPathwayRows = $pedagogyPathwayRows ?? [];
?>
<header class="tc-panel p-6 md:p-8">
    <p class="tc-kicker">Bureau du personnel et des compétences</p>
    <h1 class="tc-hero-title mb-4">Suivi des habilitations</h1>
    <p class="text-slate-600 text-sm max-w-3xl">Journal des actions sensibles et vision de votre progression pédagogique déclarée.</p>
</header>

<section class="tc-panel p-6 overflow-x-auto">
    <h2 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900 mb-3">Journal récent</h2>
    <table class="min-w-full text-sm">
        <thead><tr class="text-left text-xs uppercase text-slate-500 border-b border-slate-200"><th class="py-2 pr-4">Date</th><th class="py-2 pr-4">Action</th><th class="py-2 pr-4">Cible</th></tr></thead>
        <tbody>
        <?php foreach ($pedagogyAuditTail as $row): ?>
        <tr class="border-b border-slate-100">
            <td class="py-2 pr-4 whitespace-nowrap"><?= htmlspecialchars((string) ($row['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td class="py-2 pr-4"><?= htmlspecialchars((string) ($row['action_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td class="py-2 pr-4"><?= htmlspecialchars((string) ($row['entity_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($pedagogyAuditTail === []): ?><p class="text-sm text-slate-500 mt-2">Aucun événement enregistré.</p><?php endif; ?>
</section>

<section class="tc-panel p-5">
    <h2 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900">Montée en puissance (référentiel)</h2>
    <ul class="mt-2 text-sm text-slate-700 space-y-1">
        <?php foreach ($pedagogyPathwayCatalog as $st): ?>
        <li>• <?= htmlspecialchars((string) ($st['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
    </ul>
    <h3 class="text-xs font-bold text-slate-500 mt-4 uppercase tracking-wide">Votre suivi</h3>
    <ul class="mt-2 text-sm text-slate-600">
        <?php foreach ($pedagogyPathwayRows as $r): ?>
        <li><?= htmlspecialchars((string) ($r['stage_slug'] ?? ''), ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars((string) ($r['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
    </ul>
</section>

<section class="tc-panel p-6">
    <a href="<?= htmlspecialchars(url('back-office/ressources/training/competences/commandement'), ENT_QUOTES, 'UTF-8') ?>" class="tc-btn-primary tc-btn-ghost">Retour commandement</a>
</section>
<?php require base_path('views/admin/training/partials/command_shell_close.php'); ?>
