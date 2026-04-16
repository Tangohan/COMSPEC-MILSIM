<?php require base_path('views/admin/training/partials/command_shell_open.php'); ?>
<?php $pedagogyChainAssess = $pedagogyChainAssess ?? ['ok' => true, 'gaps' => []]; ?>
<header class="tc-panel p-6 md:p-8">
    <p class="tc-kicker">Pôle formation</p>
    <h1 class="tc-hero-title mb-4">Pilotage des parcours</h1>
    <p class="text-slate-600 text-sm max-w-3xl">Vue d’ensemble de la chaîne pédagogique requise pour proposer et publier des formations en bonne ordonnance.</p>
</header>

<section class="tc-panel p-6">
    <h2 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900 mb-3">Cohérence de la chaîne</h2>
    <?php if (!empty($pedagogyChainAssess['ok'])): ?>
    <p class="text-sm text-emerald-800 font-medium">Les profils clés attendus sont présents.</p>
    <?php else: ?>
    <ul class="list-disc pl-5 text-sm text-amber-900 space-y-1">
        <?php foreach ($pedagogyChainAssess['gaps'] as $gap): ?>
        <li><?= htmlspecialchars((string) $gap, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>

<section class="tc-panel p-6 flex flex-wrap gap-3">
    <a href="<?= htmlspecialchars(url('back-office/ressources/training/studio'), ENT_QUOTES, 'UTF-8') ?>" class="tc-btn-primary tc-btn-emerald">Ouvrir le studio</a>
    <a href="<?= htmlspecialchars(url('back-office/ressources/training/competences/formateur'), ENT_QUOTES, 'UTF-8') ?>" class="tc-btn-primary tc-btn-ghost">Paramétrer les rôles pédagogiques</a>
</section>
<?php require base_path('views/admin/training/partials/command_shell_close.php'); ?>
