<?php require base_path('views/admin/training/partials/command_shell_open.php'); ?>
<?php
$competencyMatrices = $competencyMatrices ?? [];
$competencyUsers = $competencyUsers ?? [];
$competencyRoles = $competencyRoles ?? [];
$competencyRoleNameById = is_array($competencyRoleNameById ?? null) ? $competencyRoleNameById : [];
$competencyAssignmentsByMatrix = is_array($competencyAssignmentsByMatrix ?? null) ? $competencyAssignmentsByMatrix : [];
$competencyCandidateCountByMatrix = is_array($competencyCandidateCountByMatrix ?? null) ? $competencyCandidateCountByMatrix : [];
$competencySchemaAvailable = !empty($competencySchemaAvailable);
$competencyTrainerSchemaReady = !empty($competencyTrainerSchemaReady);
$competencyMatricesSchemaReady = !empty($competencyMatricesSchemaReady);
$pedagogyChainAssess = $pedagogyChainAssess ?? ['ok' => true, 'gaps' => []];
$matrixCount = count($competencyMatrices);
$userCount = count($competencyUsers);
$assignedTotal = 0;
foreach ($competencyMatrices as $mRow) {
    $assignedTotal += (int) ($mRow['assignment_count'] ?? 0);
}

$parseMatrixRules = static function (array $m) use ($competencyRoleNameById): array {
    $rules = json_decode((string) ($m['auto_detect_rules_json'] ?? '{}'), true);
    if (!is_array($rules)) {
        $rules = [];
    }
    $rawRoleIds = $rules['role_ids_any'] ?? $rules['role_ids'] ?? [];
    $roleIds = array_values(array_unique(array_filter(array_map('intval', is_array($rawRoleIds) ? $rawRoleIds : []), static fn (int $v): bool => $v > 0)));
    $roleNames = [];
    foreach ($roleIds as $rid) {
        $roleNames[] = (string) ($competencyRoleNameById[$rid] ?? ('Rôle ' . $rid));
    }
    $minCompleted = max(0, (int) ($rules['min_completed_courses'] ?? 0));

    return [
        'role_ids' => $roleIds,
        'role_names' => $roleNames,
        'min_completed' => $minCompleted,
    ];
};

$ruleSummary = static function (array $parsed): string {
    $parts = [];
    if ($parsed['role_names'] !== []) {
        $parts[] = 'possède l’un des rôles : ' . implode(', ', $parsed['role_names']);
    }
    if ($parsed['min_completed'] > 0) {
        $n = $parsed['min_completed'];
        $parts[] = 'a terminé au moins ' . $n . ' formation' . ($n > 1 ? 's' : '');
    }
    if ($parts === []) {
        return 'Tous les membres de la communauté (aucun critère particulier).';
    }

    return 'Membres qui ' . implode(' et qui ', $parts) . '.';
};
?>
<header class="tc-panel p-6 md:p-8">
    <p class="tc-kicker">Vue commandement</p>
    <h1 class="tc-hero-title mb-4">Pilotage des compétences</h1>
    <p class="text-slate-600 text-sm max-w-3xl leading-relaxed">
        Préparez la chaîne pédagogique, puis créez des <strong>groupes de suivi</strong> pour classer vos membres
        (cadres, animateurs, parcours confirmés…). Ces groupes aident le commandement à voir qui est où — ils ne remplacent pas les droits ni les prérequis de formation.
    </p>
</header>

<section class="grid gap-4 lg:grid-cols-2">
    <article class="tc-panel border border-emerald-200/80 bg-emerald-50/40 p-5 md:p-6">
        <p class="text-[11px] font-black uppercase tracking-[0.2em] text-emerald-800">Ce que font les groupes</p>
        <ul class="mt-3 space-y-2 text-sm text-emerald-950/90">
            <li>Regrouper des membres selon un profil (rôle, formations terminées…).</li>
            <li>Remplir automatiquement une liste à partir de critères, ou ajouter quelqu’un à la main.</li>
            <li>Donner au commandement une vue claire des cohortes à suivre.</li>
        </ul>
    </article>
    <article class="tc-panel border border-amber-200/80 bg-amber-50/50 p-5 md:p-6">
        <p class="text-[11px] font-black uppercase tracking-[0.2em] text-amber-900">Ce qu’ils ne font pas</p>
        <ul class="mt-3 space-y-2 text-sm text-amber-950/90">
            <li><strong>Ne bloquent pas</strong> l’accès aux formations ni aux modules.</li>
            <li><strong>Ne débloquent pas</strong> un parcours : les prérequis entre modules restent la règle.</li>
            <li><strong>N’accordent aucun droit</strong> (rôles, administration, validation) à eux seuls.</li>
        </ul>
        <p class="mt-3 text-xs text-amber-900/80 leading-relaxed">
            Pour ouvrir ou fermer un parcours, utilisez les prérequis pédagogiques et les inscriptions.
            Pour les droits, passez par l’administration des rôles.
        </p>
    </article>
</section>

<section class="grid gap-4 md:grid-cols-3">
    <article class="tc-panel p-5">
        <p class="text-xs uppercase tracking-[0.2em] text-slate-500 font-bold">Groupes de suivi</p>
        <p class="mt-2 text-3xl font-black text-emerald-600"><?= (int) $matrixCount ?></p>
    </article>
    <article class="tc-panel p-5">
        <p class="text-xs uppercase tracking-[0.2em] text-slate-500 font-bold">Placements dans les groupes</p>
        <p class="mt-2 text-3xl font-black text-slate-900"><?= (int) $assignedTotal ?></p>
    </article>
    <article class="tc-panel p-5">
        <p class="text-xs uppercase tracking-[0.2em] text-slate-500 font-bold">Membres de la communauté</p>
        <p class="mt-2 text-3xl font-black text-slate-900"><?= (int) $userCount ?></p>
        <?php if (!$competencyMatricesSchemaReady): ?>
        <p class="mt-2 text-xs font-semibold text-amber-700">Fonction de suivi pas encore activée sur cette installation.</p>
        <?php endif; ?>
    </article>
</section>

<section class="tc-panel p-6 space-y-3">
    <h2 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900">1 · Préparer la chaîne pédagogique</h2>
    <p class="text-sm text-slate-600 max-w-3xl leading-relaxed">
        Avant de classer les membres, vérifiez que l’organisation a les sections utiles et un référent capable de valider les encadrants.
        Cette étape concerne la gouvernance — pas les groupes de suivi ci-dessous.
    </p>
    <?php if (!empty($pedagogyChainAssess['ok'])): ?>
    <p class="text-sm text-emerald-800 font-medium">Les profils clés attendus sont présents dans votre organisation.</p>
    <?php else: ?>
    <ul class="list-disc pl-5 text-sm text-amber-900 space-y-1">
        <?php foreach ($pedagogyChainAssess['gaps'] as $gap): ?>
        <li><?= htmlspecialchars((string) $gap, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <div class="flex flex-wrap gap-2 items-center">
        <form method="post" class="inline">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="action" value="ensure_org_sections">
            <button type="submit" class="tc-btn-primary tc-btn-ghost text-sm">Vérifier les sections « pilotage » et « bureau des compétences »</button>
        </form>
    </div>
    <?php if ($competencyUsers !== []): ?>
    <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50/80 p-4 space-y-2">
        <h3 class="text-xs font-black uppercase tracking-[0.15em] text-slate-700">Désigner un référent rapidement</h3>
        <p class="text-xs text-slate-600 leading-relaxed max-w-2xl">
            Choisissez la personne qui valide les encadrants et assure la gouvernance des concepteurs.
            Des habilitations de validation lui seront ajoutées ; vous pourrez les ajuster ensuite dans l’espace formateur.
        </p>
        <form method="post" class="flex flex-wrap gap-2 items-end">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="action" value="quick_chain_referent">
            <div class="min-w-[220px] flex-1">
                <label class="block text-xs font-bold text-slate-600 mb-1">Membre</label>
                <select name="target_user_id" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                    <option value="">— Choisir —</option>
                    <?php foreach ($competencyUsers as $u): ?>
                    <option value="<?= (int) $u['id'] ?>"><?= htmlspecialchars((string) ($u['display_name'] ?? $u['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="tc-btn-primary tc-btn-emerald text-sm">Désigner ce référent</button>
        </form>
        <?php if (!$competencyTrainerSchemaReady): ?>
        <p class="text-xs text-slate-500">Une fois l’installation complète, l’espace formateur reflétera automatiquement cette désignation.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</section>

<?php if ($competencyMatricesSchemaReady): ?>
<section class="tc-panel p-6 space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="max-w-2xl">
            <h2 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900">2 · Créer un groupe de suivi</h2>
            <p class="mt-2 text-sm text-slate-600 leading-relaxed">
                Un groupe est une liste nominative. Les critères ci-dessous servent uniquement au
                <strong>remplissage automatique</strong> — ils n’ouvrent ni ne ferment d’accès.
            </p>
        </div>
        <form method="post" class="inline">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="action" value="seed_preset_matrices">
            <button type="submit" class="tc-btn-primary tc-btn-ghost text-sm">Ajouter les 3 groupes suggérés</button>
        </form>
    </div>

    <form method="post" class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 md:p-5 grid gap-4 lg:grid-cols-2">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="action" value="create_matrix">
        <div>
            <label class="block text-xs font-bold text-slate-600 mb-1" for="matrix_name">Nom du groupe</label>
            <input id="matrix_name" required name="matrix_name" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white" placeholder="Ex. Encadrement terrain">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-600 mb-1" for="auto_min_completed">Formations terminées (minimum pour le remplissage auto)</label>
            <input id="auto_min_completed" type="number" min="0" name="auto_min_completed" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white" value="0">
            <p class="mt-1 text-[11px] text-slate-500">0 = pas de seuil. Ex. 3 = au moins trois formations terminées.</p>
        </div>
        <div class="lg:col-span-2">
            <label class="block text-xs font-bold text-slate-600 mb-1" for="matrix_description">À quoi sert ce groupe ?</label>
            <textarea id="matrix_description" name="matrix_description" rows="2" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white" placeholder="Ex. Suivi des cadres pour le brief du week-end. Ne change pas les droits."></textarea>
        </div>
        <div class="lg:col-span-2">
            <p class="block text-xs font-bold text-slate-600 mb-2">Rôles pris en compte pour le remplissage automatique</p>
            <p class="text-[11px] text-slate-500 mb-2">Cochez les rôles concernés. Un membre est retenu s’il a <strong>au moins l’un</strong> de ces rôles. Laissez vide pour ignorer le critère rôle.</p>
            <div class="max-h-48 overflow-y-auto rounded-xl border border-slate-200 bg-white p-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                <?php if ($competencyRoles === []): ?>
                <p class="text-xs text-slate-500 sm:col-span-2 lg:col-span-3">Aucun rôle organisationnel disponible.</p>
                <?php else: ?>
                    <?php foreach ($competencyRoles as $r):
                        $rid = (int) ($r['id'] ?? 0);
                        if ($rid < 1) {
                            continue;
                        }
                        $rName = (string) ($r['name'] ?? 'Rôle');
                    ?>
                    <label class="flex items-start gap-2 text-sm text-slate-700 cursor-pointer">
                        <input type="checkbox" name="auto_role_ids[]" value="<?= $rid ?>" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                        <span><?= htmlspecialchars($rName, ENT_QUOTES, 'UTF-8') ?></span>
                    </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="lg:col-span-2">
            <button class="tc-btn-primary tc-btn-emerald" type="submit">Créer le groupe</button>
        </div>
    </form>
</section>

<section class="tc-panel p-6">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900">3 · Groupes existants</h2>
            <p class="mt-1 text-xs text-slate-500"><?= (int) $matrixCount ?> groupe(s) · <?= (int) $assignedTotal ?> placement(s)</p>
        </div>
    </div>

    <div class="mt-5 space-y-4">
        <?php foreach ($competencyMatrices as $m):
            $mid = (int) ($m['id'] ?? 0);
            if ($mid < 1) {
                continue;
            }
            $parsed = $parseMatrixRules($m);
            $members = $competencyAssignmentsByMatrix[$mid] ?? [];
            $candidateCount = (int) ($competencyCandidateCountByMatrix[$mid] ?? 0);
            $assignedIds = [];
            foreach ($members as $mem) {
                $assignedIds[(int) ($mem['user_id'] ?? 0)] = true;
            }
        ?>
        <article class="rounded-2xl border border-slate-200 bg-white p-4 md:p-5 space-y-4 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0 max-w-2xl">
                    <h3 class="font-black text-slate-900 text-lg tracking-tight"><?= htmlspecialchars((string) ($m['name'] ?? 'Groupe'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <?php if (trim((string) ($m['description'] ?? '')) !== ''): ?>
                    <p class="mt-1 text-sm text-slate-600 leading-relaxed"><?= htmlspecialchars((string) $m['description'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                </div>
                <form method="post" onsubmit="return confirm('Supprimer ce groupe et retirer tous ses membres de la liste ?');">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="action" value="delete_matrix">
                    <input type="hidden" name="matrix_id" value="<?= $mid ?>">
                    <button type="submit" class="text-xs font-bold uppercase tracking-wider text-slate-400 hover:text-red-700">Supprimer</button>
                </form>
            </div>

            <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 space-y-2">
                <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Critères de remplissage auto</p>
                <p class="text-sm text-slate-700"><?= htmlspecialchars($ruleSummary($parsed), ENT_QUOTES, 'UTF-8') ?></p>
                <p class="text-xs text-slate-500">
                    <?= (int) $candidateCount ?> membre(s) correspondent aujourd’hui ·
                    <?= count($members) ?> déjà dans le groupe
                </p>
            </div>

            <div class="flex flex-wrap gap-2 items-end">
                <form method="post">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="action" value="auto_detect">
                    <input type="hidden" name="matrix_id" value="<?= $mid ?>">
                    <button class="tc-btn-primary tc-btn-ghost text-sm" type="submit">
                        Remplir selon les critères
                        <?php if ($candidateCount > 0): ?>
                        <span class="ml-1 text-emerald-700">(<?= (int) $candidateCount ?>)</span>
                        <?php endif; ?>
                    </button>
                </form>
                <form method="post" class="flex flex-wrap items-end gap-2">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="action" value="assign_matrix">
                    <input type="hidden" name="matrix_id" value="<?= $mid ?>">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1" for="assign-user-<?= $mid ?>">Ajouter un membre</label>
                        <select id="assign-user-<?= $mid ?>" name="user_id" required class="min-w-[14rem] border border-slate-200 rounded-lg px-3 py-2 text-sm">
                            <option value="">— Choisir —</option>
                            <?php foreach ($competencyUsers as $u):
                                $uid = (int) ($u['id'] ?? 0);
                                if ($uid < 1 || isset($assignedIds[$uid])) {
                                    continue;
                                }
                            ?>
                            <option value="<?= $uid ?>"><?= htmlspecialchars((string) ($u['display_name'] ?? $u['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="tc-btn-primary tc-btn-emerald text-sm" type="submit">Ajouter</button>
                </form>
            </div>

            <div>
                <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500 mb-2">Membres dans ce groupe</p>
                <?php if ($members === []): ?>
                <p class="text-sm text-slate-500">Personne n’y est encore placé. Utilisez le remplissage automatique ou ajoutez un membre.</p>
                <?php else: ?>
                <ul class="divide-y divide-slate-100 rounded-xl border border-slate-200 overflow-hidden">
                    <?php foreach ($members as $mem):
                        $muid = (int) ($mem['user_id'] ?? 0);
                        $src = (string) ($mem['source'] ?? 'manual');
                        $srcLabel = $src === 'auto_detect' ? 'Ajouté automatiquement' : 'Ajouté à la main';
                    ?>
                    <li class="flex flex-wrap items-center justify-between gap-2 bg-white px-3 py-2.5">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900 truncate"><?= htmlspecialchars((string) ($mem['display_name'] ?? 'Membre'), ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="text-[11px] text-slate-400"><?= htmlspecialchars($srcLabel, ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <form method="post">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="hidden" name="action" value="unassign_matrix">
                            <input type="hidden" name="matrix_id" value="<?= $mid ?>">
                            <input type="hidden" name="user_id" value="<?= $muid ?>">
                            <button type="submit" class="text-xs font-bold text-slate-500 hover:text-red-700">Retirer</button>
                        </form>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>

            <p class="text-[11px] text-slate-400 leading-relaxed border-t border-slate-100 pt-3">
                Rappel : être dans ce groupe ne change ni les droits ni l’accès aux formations.
            </p>
        </article>
        <?php endforeach; ?>

        <?php if ($competencyMatrices === []): ?>
        <p class="text-sm text-slate-500 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center">
            Aucun groupe de suivi pour le moment. Créez-en un ci-dessus, ou ajoutez les trois groupes suggérés.
        </p>
        <?php endif; ?>
    </div>
</section>
<?php else: ?>
<section class="tc-panel p-6">
    <h2 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900">Groupes de suivi</h2>
    <p class="mt-2 text-sm text-amber-800">
        Cette fonction n’est pas encore activée sur votre installation. Contactez un administrateur technique pour la finaliser.
    </p>
</section>
<?php endif; ?>

<section class="tc-panel p-6">
    <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-500 mb-3">Autres espaces compétences</p>
    <div class="flex flex-wrap gap-3">
        <a href="<?= htmlspecialchars(training_lms_admin_url('competences/bureau-personnel'), ENT_QUOTES, 'UTF-8') ?>" class="tc-btn-primary tc-btn-ghost">Bureau du personnel</a>
        <a href="<?= htmlspecialchars(training_lms_admin_url('competences/pole-formation'), ENT_QUOTES, 'UTF-8') ?>" class="tc-btn-primary tc-btn-ghost">Pôle formation</a>
        <a href="<?= htmlspecialchars(training_lms_admin_url('competences/validation'), ENT_QUOTES, 'UTF-8') ?>" class="tc-btn-primary tc-btn-ghost">Validation / certification</a>
        <a href="<?= htmlspecialchars(training_lms_admin_url('competences/sections'), ENT_QUOTES, 'UTF-8') ?>" class="tc-btn-primary tc-btn-ghost">Sections organisationnelles</a>
        <a href="<?= htmlspecialchars(training_lms_admin_url('competences/formateur'), ENT_QUOTES, 'UTF-8') ?>" class="tc-btn-primary tc-btn-emerald">Espace formateur</a>
        <a href="<?= htmlspecialchars(training_lms_admin_url('competences/instructeur'), ENT_QUOTES, 'UTF-8') ?>" class="tc-btn-primary tc-btn-ghost">Vue instructeur</a>
    </div>
</section>
<?php require base_path('views/admin/training/partials/command_shell_close.php'); ?>
