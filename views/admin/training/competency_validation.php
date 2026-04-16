<?php require base_path('views/admin/training/partials/command_shell_open.php'); ?>
<?php
$trainerValidationTail = $trainerValidationTail ?? [];
$pedagogyChainAssess = $pedagogyChainAssess ?? ['ok' => true, 'gaps' => []];
?>
<header class="tc-panel p-6 md:p-8">
    <p class="tc-kicker">Validation / certification</p>
    <h1 class="tc-hero-title mb-4">Traçabilité des décisions</h1>
</header>

<section class="tc-panel p-6">
    <h2 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900 mb-3">Chaîne minimale</h2>
    <?php if (!empty($pedagogyChainAssess['ok'])): ?>
    <p class="text-sm text-emerald-800">Profils clés présents.</p>
    <?php else: ?>
    <ul class="list-disc pl-5 text-sm text-amber-900 space-y-1">
        <?php foreach ($pedagogyChainAssess['gaps'] as $gap): ?>
        <li><?= htmlspecialchars((string) $gap, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>

<section class="tc-panel p-6 overflow-x-auto">
    <h2 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900 mb-3">Dernières validations terrain</h2>
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
    <?php if ($trainerValidationTail === []): ?><p class="text-sm text-slate-500 mt-2">Aucune entrée.</p><?php endif; ?>
</section>

<section class="tc-panel p-6">
    <a href="<?= htmlspecialchars(url('back-office/ressources/training/competences/commandement'), ENT_QUOTES, 'UTF-8') ?>" class="tc-btn-primary tc-btn-ghost">Retour commandement</a>
</section>
<?php require base_path('views/admin/training/partials/command_shell_close.php'); ?>
