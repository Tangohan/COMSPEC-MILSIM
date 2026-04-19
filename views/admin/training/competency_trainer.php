<?php require base_path('views/admin/training/partials/command_shell_open.php'); ?>
<?php
$trainerRoles = $trainerRoles ?? [];
$deliveryInstructorRoles = $deliveryInstructorRoles ?? [];
$instructorCertifierRoles = $instructorCertifierRoles ?? [];
$trainerCertifierRoles = $trainerCertifierRoles ?? [];
$tenantUsers = $tenantUsers ?? [];
?>
<header class="tc-panel p-6 md:p-8">
    <p class="tc-kicker">Espace formateur</p>
    <h1 class="tc-hero-title mb-4">Rôles pédagogiques par communauté</h1>
    <p class="text-slate-600 text-sm max-w-3xl">Cochez les rôles organisationnels qui correspondent à chaque niveau de responsabilité. Les membres peuvent cumuler plusieurs rôles.</p>
</header>

<section class="grid gap-4 lg:grid-cols-2">
    <article class="tc-panel p-5">
        <h2 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900">Conception de parcours</h2>
        <form method="post" class="mt-3 space-y-2">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="action" value="pick_trainer_roles">
            <?php foreach ($trainerRoles as $role): ?>
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="trainer_role_ids[]" value="<?= (int) $role['id'] ?>" <?= (int) ($role['is_trainer_role'] ?? 0) === 1 ? 'checked' : '' ?>>
                <span><?= htmlspecialchars((string) ($role['name'] ?? '')) ?></span>
            </label>
            <?php endforeach; ?>
            <?php if ($trainerRoles === []): ?><p class="text-xs text-slate-500">Aucun rôle disponible ou migrations à exécuter.</p><?php endif; ?>
            <button class="tc-btn-primary tc-btn-emerald" type="submit">Enregistrer</button>
        </form>
    </article>

    <article class="tc-panel p-5">
        <h2 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900">Animation sur le terrain</h2>
        <form method="post" class="mt-3 space-y-2">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="action" value="pick_delivery_instructor_roles">
            <?php foreach ($deliveryInstructorRoles as $role): ?>
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="delivery_role_ids[]" value="<?= (int) $role['id'] ?>" <?= (int) ($role['is_trainer_role'] ?? 0) === 1 ? 'checked' : '' ?>>
                <span><?= htmlspecialchars((string) ($role['name'] ?? '')) ?></span>
            </label>
            <?php endforeach; ?>
            <?php if ($deliveryInstructorRoles === []): ?><p class="text-xs text-slate-500">—</p><?php endif; ?>
            <button class="tc-btn-primary tc-btn-emerald" type="submit">Enregistrer</button>
        </form>
    </article>

    <article class="tc-panel p-5">
        <h2 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900">Validation des encadrants</h2>
        <form method="post" class="mt-3 space-y-2">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="action" value="pick_instructor_certifier_roles">
            <?php foreach ($instructorCertifierRoles as $role): ?>
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="instructor_certifier_role_ids[]" value="<?= (int) $role['id'] ?>" <?= (int) ($role['is_trainer_role'] ?? 0) === 1 ? 'checked' : '' ?>>
                <span><?= htmlspecialchars((string) ($role['name'] ?? '')) ?></span>
            </label>
            <?php endforeach; ?>
            <?php if ($instructorCertifierRoles === []): ?><p class="text-xs text-slate-500">—</p><?php endif; ?>
            <button class="tc-btn-primary tc-btn-emerald" type="submit">Enregistrer</button>
        </form>
    </article>

    <article class="tc-panel p-5">
        <h2 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900">Gouvernance des concepteurs</h2>
        <form method="post" class="mt-3 space-y-2">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="action" value="pick_trainer_certifier_roles">
            <?php foreach ($trainerCertifierRoles as $role): ?>
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="trainer_certifier_role_ids[]" value="<?= (int) $role['id'] ?>" <?= (int) ($role['is_trainer_role'] ?? 0) === 1 ? 'checked' : '' ?>>
                <span><?= htmlspecialchars((string) ($role['name'] ?? '')) ?></span>
            </label>
            <?php endforeach; ?>
            <?php if ($trainerCertifierRoles === []): ?><p class="text-xs text-slate-500">—</p><?php endif; ?>
            <button class="tc-btn-primary tc-btn-emerald" type="submit">Enregistrer</button>
        </form>
    </article>
</section>

<section class="tc-panel p-5">
    <h2 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900">Attribuer les rôles de conception à un membre</h2>
    <form method="post" class="mt-3 space-y-3 max-w-xl">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="action" value="assign_trainer_roles">
        <select name="target_user_id" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
            <?php foreach ($tenantUsers as $u): ?><option value="<?= (int) $u['id'] ?>"><?= htmlspecialchars((string) ($u['display_name'] ?? $u['email'] ?? '')) ?></option><?php endforeach; ?>
        </select>
        <button class="tc-btn-primary tc-btn-emerald" type="submit">Fusionner avec les rôles déjà ouverts pour ce membre</button>
    </form>
</section>

<section class="tc-panel p-6">
    <div class="flex flex-wrap gap-3">
        <a href="<?= htmlspecialchars(training_lms_admin_url('competences/commandement'), ENT_QUOTES, 'UTF-8') ?>" class="tc-btn-primary tc-btn-ghost">Retour commandement</a>
    </div>
</section>
<?php require base_path('views/admin/training/partials/command_shell_close.php'); ?>
