<?php require base_path('views/admin/training/partials/command_shell_open.php'); ?>
<?php
$trainerRoles = $trainerRoles ?? [];
$deliveryInstructorRoles = $deliveryInstructorRoles ?? [];
$instructorCertifierRoles = $instructorCertifierRoles ?? [];
$trainerCertifierRoles = $trainerCertifierRoles ?? [];
$tenantUsers = $tenantUsers ?? [];
$competencyTrainerSchemaReady = !empty($competencyTrainerSchemaReady);
$pedagogySteps = is_array($pedagogySteps ?? null) ? $pedagogySteps : [];
$pedagogyDesignations = is_array($pedagogyDesignations ?? null) ? $pedagogyDesignations : [];
$pedagogyChainAssess = $pedagogyChainAssess ?? ['ok' => true, 'gaps' => []];

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

$rolePanels = [
    [
        'kind' => 'design_trainer',
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
        'kind' => 'delivery_instructor',
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
        'kind' => 'instructor_certifier',
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
        'kind' => 'trainer_certifier',
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
$panelsByKind = [];
foreach ($rolePanels as $panel) {
    $panelsByKind[(string) $panel['kind']] = $panel;
}
$namedTotal = 0;
foreach ($pedagogyDesignations as $rows) {
    $namedTotal += is_array($rows) ? count($rows) : 0;
}
?>
<header class="tc-panel p-6 md:p-8">
    <p class="tc-kicker">Espace formateur</p>
    <h1 class="tc-hero-title mb-4">Qui fait quoi dans la chaîne pédagogique</h1>
    <p class="text-slate-600 text-sm max-w-3xl leading-relaxed">
        Une formation passe par quatre étapes : on la <strong>conçoit</strong>, on l’<strong>anime</strong>,
        on <strong>valide les encadrants</strong> et un référent <strong>supervise les concepteurs</strong>.
        À chaque étape, nommez les personnes concernées par leur nom. Elles sont prévenues par e-mail
        et la plateforme les reconnaît aussitôt (créneaux, publication, validations).
    </p>
</header>

<section class="grid gap-4 lg:grid-cols-2">
    <article class="tc-panel border border-emerald-200/80 bg-emerald-50/40 p-5 md:p-6">
        <p class="text-[11px] font-black uppercase tracking-[0.2em] text-emerald-800">Comment procéder</p>
        <ol class="mt-3 space-y-2 text-sm text-emerald-950/90 list-decimal pl-5">
            <li>Lisez à quoi sert l’étape et ce que la personne pourra faire.</li>
            <li>Cherchez la personne par son nom, son indicatif ou son e-mail, puis cliquez sur « Nommer ».</li>
            <li>Laissez « Prévenir par e-mail » coché pour qu’elle reçoive un message explicatif.</li>
        </ol>
    </article>
    <article class="tc-panel border border-slate-200 p-5 md:p-6">
        <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-600">Bon à savoir</p>
        <ul class="mt-3 space-y-2 text-sm text-slate-700">
            <li>Une même personne peut être nommée à plusieurs étapes.</li>
            <li>Nommer quelqu’un ne change ni son rôle ni ses accès au site : « Retirer » annule la nomination.</li>
            <li>Vous pouvez aussi relier une étape à un rôle entier, dans « Par rôle (avancé) ».</li>
        </ul>
        <?php if (empty($pedagogyChainAssess['ok'])): ?>
        <p class="mt-3 text-xs font-semibold text-amber-800">Il manque encore quelqu’un à au moins une étape.</p>
        <?php else: ?>
        <p class="mt-3 text-xs font-semibold text-emerald-800">Chaque étape a au moins une personne. <?= (int) $namedTotal ?> nomination<?= $namedTotal > 1 ? 's' : '' ?> au total.</p>
        <?php endif; ?>
    </article>
</section>

<?php foreach ($pedagogySteps as $kind => $step):
    $named = is_array($pedagogyDesignations[$kind] ?? null) ? $pedagogyDesignations[$kind] : [];
    $namedIds = array_map(static fn (array $d): int => (int) ($d['user_id'] ?? 0), $named);
    $panel = $panelsByKind[$kind] ?? null;
    ?>
<section id="etape-<?= htmlspecialchars((string) $kind, ENT_QUOTES, 'UTF-8') ?>" class="tc-panel p-5 md:p-7 space-y-4 scroll-mt-24">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0 max-w-3xl">
            <p class="text-[11px] font-black uppercase tracking-[0.2em] text-emerald-700">Étape <?= htmlspecialchars($step['step'], ENT_QUOTES, 'UTF-8') ?></p>
            <h2 class="mt-1 text-lg font-bold text-slate-900"><?= htmlspecialchars($step['title'], ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="mt-2 text-sm text-slate-600 leading-relaxed"><?= htmlspecialchars($step['lead'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <span class="inline-flex items-center rounded-full border <?= $named !== [] ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800' ?> px-2.5 py-1 text-[11px] font-bold">
            <?= $named !== [] ? count($named) . ' personne' . (count($named) > 1 ? 's' : '') . ' nommée' . (count($named) > 1 ? 's' : '') : 'Personne pour l’instant' ?>
        </span>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
            <p class="text-xs font-bold text-slate-700">Ce que la personne peut faire ensuite</p>
            <ul class="mt-2 space-y-1.5 text-sm text-slate-600 list-disc pl-5">
                <?php foreach ($step['can'] as $line): ?>
                <li><?= htmlspecialchars($line, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="rounded-xl border border-slate-200 p-4">
            <p class="text-xs font-bold text-slate-700">Personnes nommées</p>
            <?php if ($named === []): ?>
            <p class="mt-2 text-sm text-slate-500">Personne n’est encore nommé à cette étape.</p>
            <?php else: ?>
            <ul class="mt-2 divide-y divide-slate-100">
                <?php foreach ($named as $d):
                    $dn = trim((string) ($d['display_name'] ?? '')) ?: trim((string) ($d['email'] ?? ''));
                    $by = trim((string) ($d['designated_by_name'] ?? ''));
                    $inactive = (string) ($d['status'] ?? 'active') !== 'active';
                    ?>
                <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-slate-900"><?= htmlspecialchars($dn, ENT_QUOTES, 'UTF-8') ?><?= $inactive ? ' <span class="text-xs font-normal text-amber-700">(compte inactif)</span>' : '' ?></span>
                        <span class="block text-[11px] text-slate-500">Nommé<?= $by !== '' ? ' par ' . htmlspecialchars($by, ENT_QUOTES, 'UTF-8') : '' ?><?= !empty($d['created_at']) ? ' le ' . htmlspecialchars(date('d/m/Y', (int) strtotime((string) $d['created_at'])), ENT_QUOTES, 'UTF-8') : '' ?></span>
                    </span>
                    <form method="post" onsubmit="return confirm('Retirer cette nomination ?');">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="action" value="remove_designation">
                        <input type="hidden" name="kind" value="<?= htmlspecialchars((string) $kind, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="user_id" value="<?= (int) ($d['user_id'] ?? 0) ?>">
                        <button type="submit" class="text-xs font-bold text-rose-600 hover:underline">Retirer</button>
                    </form>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>

    <form method="post" class="flex flex-wrap gap-3 items-end rounded-xl border border-emerald-200/70 bg-emerald-50/30 p-4">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="action" value="designate_person">
        <input type="hidden" name="kind" value="<?= htmlspecialchars((string) $kind, ENT_QUOTES, 'UTF-8') ?>">
        <?php
        $pickerUsers = $tenantUsers;
        $pickerId = 'pick-' . $kind;
        $pickerExcludeIds = $namedIds;
        require base_path('views/admin/training/partials/pedagogy_member_picker.php');
        ?>
        <label class="inline-flex items-center gap-2 text-xs text-slate-700 pb-2">
            <input type="checkbox" name="notify_email" value="1" checked>
            Prévenir par e-mail
        </label>
        <button type="submit" class="tc-btn-primary tc-btn-emerald text-sm" <?= $tenantUsers === [] ? 'disabled' : '' ?>>Nommer</button>
    </form>

    <?php if ($panel !== null): ?>
    <details class="rounded-xl border border-slate-200 bg-white">
        <summary class="cursor-pointer select-none px-4 py-3 text-xs font-bold text-slate-600">
            Par rôle (avancé)<?= (int) $panel['picked'] > 0 ? ' · ' . (int) $panel['picked'] . ' rôle' . ((int) $panel['picked'] > 1 ? 's' : '') . ' relié' . ((int) $panel['picked'] > 1 ? 's' : '') : '' ?>
        </summary>
        <div class="border-t border-slate-100 p-4">
            <p class="text-xs text-slate-500 leading-relaxed"><?= htmlspecialchars((string) $panel['hint'], ENT_QUOTES, 'UTF-8') ?> Tous les membres qui ont ce rôle comptent alors pour cette étape, sans recevoir d’e-mail.</p>
            <?php if (!$competencyTrainerSchemaReady): ?>
            <p class="mt-2 text-xs text-amber-800">La liaison par rôle n’est pas encore activée sur cette installation. La nomination par personne ci-dessus fonctionne.</p>
            <?php endif; ?>
            <form method="post" class="mt-3 flex flex-col gap-3">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="action" value="<?= htmlspecialchars((string) $panel['action'], ENT_QUOTES, 'UTF-8') ?>">
                <div class="max-h-56 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50/70 p-3 space-y-2">
                    <?php if ($panel['roles'] === []): ?>
                    <p class="text-xs text-slate-500"><?= htmlspecialchars((string) $panel['empty'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php else: ?>
                        <?php foreach ($panel['roles'] as $role): ?>
                        <label class="flex items-start gap-2.5 text-sm text-slate-700 cursor-pointer rounded-lg px-2 py-1.5 hover:bg-white">
                            <input type="checkbox" class="mt-0.5" name="<?= htmlspecialchars((string) $panel['field'], ENT_QUOTES, 'UTF-8') ?>" value="<?= (int) ($role['id'] ?? 0) ?>" <?= (int) ($role['is_trainer_role'] ?? 0) === 1 ? 'checked' : '' ?> <?= $competencyTrainerSchemaReady ? '' : 'disabled' ?>>
                            <span><?= htmlspecialchars((string) ($role['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                        </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <button class="tc-btn-primary tc-btn-ghost self-start text-sm" type="submit" <?= ($competencyTrainerSchemaReady && $panel['roles'] !== []) ? '' : 'disabled' ?>><?= htmlspecialchars((string) $panel['save'], ENT_QUOTES, 'UTF-8') ?></button>
            </form>
        </div>
    </details>
    <?php endif; ?>
</section>
<?php endforeach; ?>

<section class="tc-panel p-6">
    <p class="text-xs font-bold uppercase tracking-[0.15em] text-slate-500 mb-3">Autres vues compétences</p>
    <div class="flex flex-wrap gap-3">
        <a href="<?= htmlspecialchars(training_lms_admin_url('competences/commandement'), ENT_QUOTES, 'UTF-8') ?>" class="tc-btn-primary tc-btn-emerald">Pilotage commandement</a>
        <a href="<?= htmlspecialchars(training_lms_admin_url('competences/instructeur'), ENT_QUOTES, 'UTF-8') ?>" class="tc-btn-primary tc-btn-ghost">Validation instructeur</a>
        <a href="<?= htmlspecialchars(training_lms_admin_url('competences/pole-formation'), ENT_QUOTES, 'UTF-8') ?>" class="tc-btn-primary tc-btn-ghost">Pôle formation</a>
    </div>
</section>
<?php require base_path('views/admin/training/partials/command_shell_close.php'); ?>
