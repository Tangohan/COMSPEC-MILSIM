<?php require base_path('views/admin/training/partials/command_shell_open.php'); ?>
<?php $pedagogyChainAssess = $pedagogyChainAssess ?? ['ok' => true, 'gaps' => []]; ?>
<header class="tc-panel p-6 md:p-8">
    <p class="tc-kicker">Structure organisationnelle</p>
    <h1 class="tc-hero-title mb-4">Sections obligatoires</h1>
    <p class="text-slate-600 text-sm max-w-3xl">Votre organisation doit comporter une section « Pilotage et expertise » et une sous-section « Bureau du personnel et des compétences ». Utilisez le bouton ci-dessous depuis la vue commandement pour les créer automatiquement si elles manquent.</p>
</header>

<section class="tc-panel p-6 space-y-3">
    <h2 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900">Chaîne pédagogique</h2>
    <?php if (!empty($pedagogyChainAssess['ok'])): ?>
    <p class="text-sm text-emerald-800">Profils clés présents.</p>
    <?php else: ?>
    <ul class="list-disc pl-5 text-sm text-amber-900 space-y-1">
        <?php foreach ($pedagogyChainAssess['gaps'] as $gap): ?>
        <li><?= htmlspecialchars((string) $gap, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <p class="text-sm text-slate-700">Depuis la <a class="font-bold text-emerald-800 underline" href="<?= htmlspecialchars(training_lms_admin_url('competences/commandement'), ENT_QUOTES, 'UTF-8') ?>">vue commandement</a>, lancez la vérification des sections réservées.</p>
</section>
<?php require base_path('views/admin/training/partials/command_shell_close.php'); ?>
