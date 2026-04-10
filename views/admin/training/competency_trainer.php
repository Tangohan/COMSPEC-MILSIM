<?php require base_path('views/admin/training/partials/command_shell_open.php'); ?>
<?php
$trainerRoles = $trainerRoles ?? [];
$tenantUsers = $tenantUsers ?? [];
?>
<header class="tc-panel p-6 md:p-8">
    <p class="tc-kicker">Espace formateur</p>
    <h1 class="tc-hero-title mb-4">Picking &amp; assignation des rôles formateur</h1>
</header>

<section class="grid gap-4 lg:grid-cols-2">
    <article class="tc-panel p-5">
        <h2 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900">1) Picking des rôles</h2>
        <form method="post" class="mt-3 space-y-2">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="action" value="pick_trainer_roles">
            <?php foreach ($trainerRoles as $role): ?>
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="trainer_role_ids[]" value="<?= (int) $role['id'] ?>" <?= (int) ($role['is_trainer_role'] ?? 0) === 1 ? 'checked' : '' ?>>
                <span><?= htmlspecialchars((string) ($role['name'] ?? '')) ?></span>
            </label>
            <?php endforeach; ?>
            <button class="tc-btn-primary tc-btn-emerald" type="submit">Enregistrer</button>
        </form>
    </article>

    <article class="tc-panel p-5">
        <h2 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900">2) Assigner au personnel</h2>
        <form method="post" class="mt-3 space-y-3">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="action" value="assign_trainer_roles">
            <select name="target_user_id" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                <?php foreach ($tenantUsers as $u): ?><option value="<?= (int) $u['id'] ?>"><?= htmlspecialchars((string) ($u['display_name'] ?? $u['email'] ?? '')) ?></option><?php endforeach; ?>
            </select>
            <button class="tc-btn-primary tc-btn-emerald" type="submit">Assigner les rôles formateur sélectionnés</button>
        </form>
    </article>
</section>

<section class="tc-panel p-6">
    <div class="flex flex-wrap gap-3">
        <a href="<?= htmlspecialchars(url('back-office/ressources/training/competences/commandement'), ENT_QUOTES, 'UTF-8') ?>" class="tc-btn-primary tc-btn-ghost">Retour commandement</a>
    </div>
</section>
<?php require base_path('views/admin/training/partials/command_shell_close.php'); ?>
