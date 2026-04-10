<?php require base_path('views/admin/training/partials/command_shell_open.php'); ?>
<?php
$competencyMatrices = $competencyMatrices ?? [];
$competencyUsers = $competencyUsers ?? [];
$competencyRoles = $competencyRoles ?? [];
$competencySchemaAvailable = !empty($competencySchemaAvailable);
?>
<header class="tc-panel p-6 md:p-8">
    <p class="tc-kicker">Vue commandement</p>
    <h1 class="tc-hero-title mb-4">Carte de compétences &amp; readiness</h1>
    <p class="text-slate-600 text-sm max-w-3xl leading-relaxed">
        Pilotage des matrices de compétence, règles de détection automatique et assignations opérationnelles.
    </p>
</header>

<section class="grid gap-4 md:grid-cols-3">
    <article class="tc-panel p-5"><p class="text-xs uppercase tracking-[0.2em] text-slate-500 font-bold">Matrices actives</p><p class="mt-2 text-3xl font-black text-emerald-600"><?= count($competencyMatrices) ?></p></article>
    <article class="tc-panel p-5"><p class="text-xs uppercase tracking-[0.2em] text-slate-500 font-bold">Personnel assignable</p><p class="mt-2 text-3xl font-black text-slate-900"><?= count($competencyUsers) ?></p></article>
    <article class="tc-panel p-5"><p class="text-xs uppercase tracking-[0.2em] text-slate-500 font-bold">Schéma compétence</p><p class="mt-2 text-sm font-black <?= $competencySchemaAvailable ? 'text-emerald-700' : 'text-amber-700' ?>"><?= $competencySchemaAvailable ? 'Actif' : 'Migration à exécuter' ?></p></article>
</section>

<section class="tc-panel p-6 space-y-4">
    <h2 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900">Créer une matrice</h2>
    <form method="post" class="grid gap-3 lg:grid-cols-2">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="action" value="create_matrix">
        <div><label class="block text-xs font-bold text-slate-600 mb-1">Nom</label><input required name="matrix_name" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
        <div><label class="block text-xs font-bold text-slate-600 mb-1">Min. formations complétées (auto)</label><input type="number" min="0" name="auto_min_completed" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" value="0"></div>
        <div class="lg:col-span-2"><label class="block text-xs font-bold text-slate-600 mb-1">Description</label><textarea name="matrix_description" rows="2" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></textarea></div>
        <div class="lg:col-span-2"><label class="block text-xs font-bold text-slate-600 mb-1">Rôles cibles auto-détection</label><select name="auto_role_ids[]" multiple size="5" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"><?php foreach ($competencyRoles as $r): ?><option value="<?= (int) $r['id'] ?>"><?= htmlspecialchars((string) ($r['name'] ?? '')) ?></option><?php endforeach; ?></select></div>
        <div class="lg:col-span-2"><button class="tc-btn-primary tc-btn-emerald" type="submit">Créer la matrice</button></div>
    </form>
</section>

<section class="tc-panel p-6">
    <h2 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900">Matrices existantes</h2>
    <div class="mt-4 space-y-3">
        <?php foreach ($competencyMatrices as $m): ?>
        <article class="rounded-xl border border-slate-200 p-4 space-y-2">
            <p class="font-bold text-slate-900"><?= htmlspecialchars((string) ($m['name'] ?? 'Matrice')) ?> <span class="text-xs text-slate-500">#<?= (int) ($m['id'] ?? 0) ?></span></p>
            <p class="text-xs text-slate-600"><?= htmlspecialchars((string) ($m['description'] ?? '')) ?></p>
            <p class="text-xs text-slate-500">Assignations: <?= (int) ($m['assignment_count'] ?? 0) ?></p>
            <div class="flex flex-wrap gap-2">
                <form method="post" class="flex items-center gap-2"><?= \App\Core\Csrf::field() ?><input type="hidden" name="action" value="auto_detect"><input type="hidden" name="matrix_id" value="<?= (int) $m['id'] ?>"><button class="tc-btn-primary tc-btn-ghost" type="submit">Détection auto</button></form>
                <form method="post" class="flex items-center gap-2"><?= \App\Core\Csrf::field() ?><input type="hidden" name="action" value="assign_matrix"><input type="hidden" name="matrix_id" value="<?= (int) $m['id'] ?>"><select name="user_ids[]" multiple size="2" class="border border-slate-200 rounded px-2 py-1 text-xs"><?php foreach ($competencyUsers as $u): ?><option value="<?= (int) $u['id'] ?>"><?= htmlspecialchars((string) ($u['display_name'] ?? $u['email'] ?? '')) ?></option><?php endforeach; ?></select><button class="tc-btn-primary tc-btn-emerald" type="submit">Assigner</button></form>
            </div>
        </article>
        <?php endforeach; ?>
        <?php if ($competencyMatrices === []): ?><p class="text-sm text-slate-500">Aucune matrice pour le moment.</p><?php endif; ?>
    </div>
</section>

<section class="tc-panel p-6">
    <div class="mt-1 flex flex-wrap gap-3">
        <a href="<?= htmlspecialchars(url('back-office/ressources/training/competences/formateur'), ENT_QUOTES, 'UTF-8') ?>" class="tc-btn-primary tc-btn-emerald">Espace formateur</a>
        <a href="<?= htmlspecialchars(url('back-office/ressources/training/competences/instructeur'), ENT_QUOTES, 'UTF-8') ?>" class="tc-btn-primary tc-btn-ghost">Ouvrir la vue instructeur</a>
    </div>
</section>
<?php require base_path('views/admin/training/partials/command_shell_close.php'); ?>
