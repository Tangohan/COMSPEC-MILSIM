<?php require base_path('views/admin/training/partials/command_shell_open.php'); ?>
<?php
$trainerRoles = $trainerRoles ?? [];
$deliveryInstructorRoles = $deliveryInstructorRoles ?? [];
$instructorCertifierRoles = $instructorCertifierRoles ?? [];
$trainerCertifierRoles = $trainerCertifierRoles ?? [];
$tenantUsers = $tenantUsers ?? [];
$competencyTrainerSchemaReady = !empty($competencyTrainerSchemaReady);

$countPicked = static function (array $roles): int {
    $n = 0;
    foreach ($roles as $role) {
        if ((int) ($role['is_trainer_role'] ?? 0) === 1) {
            $n++;
        }
    }

    return $n;
};

$designCount = $countPicked($trainerRoles);
$deliveryCount = $countPicked($deliveryInstructorRoles);
$instructorCertCount = $countPicked($instructorCertifierRoles);
$trainerCertCount = $countPicked($trainerCertifierRoles);
$totalPicked = $designCount + $deliveryCount + $instructorCertCount + $trainerCertCount;

$rolePanels = [
    [
        'step' => '1a',
        'title' => 'Conception de parcours',
        'lead' => 'Qui peut créer et faire évoluer les formations (modules, contenus, parcours).',
        'hint' => 'Cochez les rôles organisationnels considérés comme concepteurs.',
        'action' => 'pick_trainer_roles',
        'field' => 'trainer_role_ids[]',
        'roles' => $trainerRoles,
        'picked' => $designCount,
        'empty' => 'Aucun rôle organisationnel disponible pour le moment.',
        'save' => 'Enregistrer les concepteurs',
    ],
    [
        'step' => '1b',
        'title' => 'Animation sur le terrain',
        'lead' => 'Qui anime les séances, encadre les stagiaires et suit la progression en session.',
        'hint' => 'Cochez les rôles considérés comme animateurs / instructeurs de terrain.',
        'action' => 'pick_delivery_instructor_roles',
        'field' => 'delivery_role_ids[]',
        'roles' => $deliveryInstructorRoles,
        'picked' => $deliveryCount,
        'empty' => 'Aucun rôle organisationnel disponible pour le moment.',
        'save' => 'Enregistrer les animateurs',
    ],
    [
        'step' => '1c',
        'title' => 'Validation des encadrants',
        'lead' => 'Qui peut confirmer qu’un encadrant est habilité à valider sur le terrain.',
        'hint' => 'Cochez les rôles qui portent cette responsabilité de validation.',
        'action' => 'pick_instructor_certifier_roles',
        'field' => 'instructor_certifier_role_ids[]',
        'roles' => $instructorCertifierRoles,
        'picked' => $instructorCertCount,
        'empty' => 'Aucun rôle organisationnel disponible pour le moment.',
        'save' => 'Enregistrer les validateurs d’encadrants',
    ],
    [
        'step' => '1d',
        'title' => 'Gouvernance des concepteurs',
        'lead' => 'Qui supervise les concepteurs et peut valider leur habilitation pédagogique.',
        'hint' => 'Cochez les rôles de gouvernance pédagogique (référents, responsables formation…).',
        'action' => 'pick_trainer_certifier_roles',
        'field' => 'trainer_certifier_role_ids[]',
        'roles' => $trainerCertifierRoles,
        'picked' => $trainerCertCount,
        'empty' => 'Aucun rôle organisationnel disponible pour le moment.',
        'save' => 'Enregistrer la gouvernance',
    ],
];
?>
<header class="tc-panel p-6 md:p-8">
    <p class="tc-kicker">Espace formateur</p>
    <h1 class="tc-hero-title mb-4">Qui fait quoi dans la chaîne pédagogique</h1>
    <p class="text-slate-600 text-sm max-w-3xl leading-relaxed">
        Cette page ne crée pas de nouveaux droits : elle <strong>relie vos rôles organisationnels</strong>
        (ceux déjà définis dans la communauté) aux responsabilités pédagogiques.
        Un même rôle peut servir à plusieurs responsabilités.
    </p>
</header>

<section class="grid gap-4 lg:grid-cols-2">
    <article class="tc-panel border border-emerald-200/80 bg-emerald-50/40 p-5 md:p-6">
        <p class="text-[11px] font-black uppercase tracking-[0.2em] text-emerald-800">À quoi ça sert</p>
        <ul class="mt-3 space-y-2 text-sm text-emerald-950/90">
            <li>Dire clairement qui conçoit, qui anime, qui valide.</li>
            <li>Préparer la chaîne pédagogique utilisée par le pilotage des compétences.</li>
            <li>Attribuer rapidement les rôles de conception à un membre, si besoin.</li>
        </ul>
    </article>
    <article class="tc-panel border border-amber-200/80 bg-amber-50/50 p-5 md:p-6">
        <p class="text-[11px] font-black uppercase tracking-[0.2em] text-amber-900">Comment procéder</p>
        <ol class="mt-3 space-y-2 text-sm text-amber-950/90 list-decimal pl-5">
            <li><strong>Étape 1</strong> — Cochez, pour chaque responsabilité, les rôles concernés, puis enregistrez.</li>
            <li><strong>Étape 2</strong> — (Facultatif) Accordez les rôles de conception à un membre précis.</li>
        </ol>
        <p class="mt-3 text-xs text-amber-900/80 leading-relaxed">
            Les cases à cocher portent sur les rôles de votre organisation, pas sur des personnes individuelles
            (sauf à l’étape 2).
        </p>
    </article>
</section>

<section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <article class="tc-panel p-5">
        <p class="text-xs uppercase tracking-[0.2em] text-slate-500 font-bold">Conception</p>
        <p class="mt-2 text-3xl font-black text-emerald-600"><?= (int) $designCount ?></p>
        <p class="mt-1 text-xs text-slate-500">rôle<?= $designCount > 1 ? 's' : '' ?> coché<?= $designCount > 1 ? 's' : '' ?></p>
    </article>
    <article class="tc-panel p-5">
        <p class="text-xs uppercase tracking-[0.2em] text-slate-500 font-bold">Animation</p>
        <p class="mt-2 text-3xl font-black text-slate-900"><?= (int) $deliveryCount ?></p>
        <p class="mt-1 text-xs text-slate-500">rôle<?= $deliveryCount > 1 ? 's' : '' ?> coché<?= $deliveryCount > 1 ? 's' : '' ?></p>
    </article>
    <article class="tc-panel p-5">
        <p class="text-xs uppercase tracking-[0.2em] text-slate-500 font-bold">Validation encadrants</p>
        <p class="mt-2 text-3xl font-black text-slate-900"><?= (int) $instructorCertCount ?></p>
        <p class="mt-1 text-xs text-slate-500">rôle<?= $instructorCertCount > 1 ? 's' : '' ?> coché<?= $instructorCertCount > 1 ? 's' : '' ?></p>
    </article>
    <article class="tc-panel p-5">
        <p class="text-xs uppercase tracking-[0.2em] text-slate-500 font-bold">Gouvernance</p>
        <p class="mt-2 text-3xl font-black text-slate-900"><?= (int) $trainerCertCount ?></p>
        <p class="mt-1 text-xs text-slate-500">rôle<?= $trainerCertCount > 1 ? 's' : '' ?> coché<?= $trainerCertCount > 1 ? 's' : '' ?></p>
    </article>
</section>

<?php if (!$competencyTrainerSchemaReady): ?>
<section class="tc-panel border border-amber-200 bg-amber-50/60 p-5 md:p-6">
    <h2 class="text-sm font-black uppercase tracking-[0.2em] text-amber-950">Fonction pas encore activée</h2>
    <p class="mt-3 text-sm text-amber-950/90 leading-relaxed max-w-3xl">
        Le paramétrage des responsabilités pédagogiques n’est pas encore disponible sur cette installation.
        Contactez l’administrateur technique pour finaliser la mise à jour, puis revenez ici.
    </p>
</section>
<?php endif; ?>

<section class="tc-panel p-6 md:p-8 space-y-3">
    <h2 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900">1 · Relier les rôles aux responsabilités</h2>
    <p class="text-sm text-slate-600 max-w-3xl leading-relaxed">
        Quatre responsabilités distinctes. Pour chacune, cochez les rôles organisationnels concernés,
        puis enregistrez. <?= $totalPicked > 0
            ? 'Actuellement, <strong>' . (int) $totalPicked . ' liaison' . ($totalPicked > 1 ? 's' : '') . '</strong> au total.'
            : 'Aucune liaison enregistrée pour l’instant — commencez par la conception ou l’animation.' ?>
    </p>
</section>

<section class="grid gap-6 lg:grid-cols-2">
    <?php foreach ($rolePanels as $panel): ?>
    <article class="tc-panel p-5 md:p-6 flex flex-col">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-[11px] font-black uppercase tracking-[0.2em] text-emerald-700"><?= htmlspecialchars((string) $panel['step'], ENT_QUOTES, 'UTF-8') ?></p>
                <h3 class="mt-1 text-base font-bold text-slate-900"><?= htmlspecialchars((string) $panel['title'], ENT_QUOTES, 'UTF-8') ?></h3>
            </div>
            <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-bold text-slate-600">
                <?= (int) $panel['picked'] ?> coché<?= (int) $panel['picked'] > 1 ? 's' : '' ?>
            </span>
        </div>
        <p class="mt-3 text-sm text-slate-600 leading-relaxed"><?= htmlspecialchars((string) $panel['lead'], ENT_QUOTES, 'UTF-8') ?></p>
        <p class="mt-2 text-xs text-slate-500 leading-relaxed"><?= htmlspecialchars((string) $panel['hint'], ENT_QUOTES, 'UTF-8') ?></p>

        <form method="post" class="mt-5 flex-1 flex flex-col gap-3">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="action" value="<?= htmlspecialchars((string) $panel['action'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="max-h-56 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50/70 p-3 space-y-2">
                <?php if ($panel['roles'] === []): ?>
                <p class="text-xs text-slate-500"><?= htmlspecialchars((string) $panel['empty'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php else: ?>
                    <?php foreach ($panel['roles'] as $role): ?>
                    <label class="flex items-start gap-2.5 text-sm text-slate-700 cursor-pointer rounded-lg px-2 py-1.5 hover:bg-white">
                        <input
                            type="checkbox"
                            class="mt-0.5"
                            name="<?= htmlspecialchars((string) $panel['field'], ENT_QUOTES, 'UTF-8') ?>"
                            value="<?= (int) ($role['id'] ?? 0) ?>"
                            <?= (int) ($role['is_trainer_role'] ?? 0) === 1 ? 'checked' : '' ?>
                            <?= $competencyTrainerSchemaReady ? '' : 'disabled' ?>
                        >
                        <span><?= htmlspecialchars((string) ($role['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button
                class="tc-btn-primary tc-btn-emerald mt-auto self-start"
                type="submit"
                <?= ($competencyTrainerSchemaReady && $panel['roles'] !== []) ? '' : 'disabled' ?>
            ><?= htmlspecialchars((string) $panel['save'], ENT_QUOTES, 'UTF-8') ?></button>
        </form>
    </article>
    <?php endforeach; ?>
</section>

<section class="tc-panel p-6 md:p-8 space-y-4">
    <div class="max-w-2xl">
        <h2 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900">2 · Accorder la conception à un membre</h2>
        <p class="mt-2 text-sm text-slate-600 leading-relaxed">
            Choisissez un membre : les rôles cochés en « Conception de parcours » lui seront
            <strong>ajoutés</strong> sans retirer ceux qu’il a déjà.
            Utile pour habiliter rapidement un concepteur sans passer par l’administration des rôles.
        </p>
    </div>
    <?php if ($designCount < 1): ?>
    <p class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 max-w-2xl">
        Aucun rôle de conception n’est encore coché à l’étape 1a. Enregistrez d’abord au moins un rôle « Conception de parcours », puis revenez ici.
    </p>
    <?php endif; ?>
    <form method="post" class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 md:p-5 space-y-4 max-w-xl">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="action" value="assign_trainer_roles">
        <div>
            <label class="block text-xs font-bold text-slate-600 mb-1" for="target_user_id">Membre concerné</label>
            <select
                id="target_user_id"
                name="target_user_id"
                required
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white"
                <?= ($competencyTrainerSchemaReady && $tenantUsers !== [] && $designCount > 0) ? '' : 'disabled' ?>
            >
                <option value="">— Choisir un membre —</option>
                <?php foreach ($tenantUsers as $u): ?>
                <option value="<?= (int) $u['id'] ?>"><?= htmlspecialchars((string) ($u['display_name'] ?? $u['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($tenantUsers === []): ?>
            <p class="mt-1 text-xs text-slate-500">Aucun membre trouvé dans la communauté.</p>
            <?php endif; ?>
        </div>
        <button
            class="tc-btn-primary tc-btn-emerald"
            type="submit"
            <?= ($competencyTrainerSchemaReady && $tenantUsers !== [] && $designCount > 0) ? '' : 'disabled' ?>
        >Ajouter les rôles de conception à ce membre</button>
    </form>
</section>

<section class="tc-panel p-6">
    <p class="text-xs font-bold uppercase tracking-[0.15em] text-slate-500 mb-3">Autres vues compétences</p>
    <div class="flex flex-wrap gap-3">
        <a href="<?= htmlspecialchars(training_lms_admin_url('competences/commandement'), ENT_QUOTES, 'UTF-8') ?>" class="tc-btn-primary tc-btn-emerald">Pilotage commandement</a>
        <a href="<?= htmlspecialchars(training_lms_admin_url('competences/instructeur'), ENT_QUOTES, 'UTF-8') ?>" class="tc-btn-primary tc-btn-ghost">Validation instructeur</a>
        <a href="<?= htmlspecialchars(training_lms_admin_url('competences/pole-formation'), ENT_QUOTES, 'UTF-8') ?>" class="tc-btn-primary tc-btn-ghost">Pôle formation</a>
    </div>
</section>
<?php require base_path('views/admin/training/partials/command_shell_close.php'); ?>
