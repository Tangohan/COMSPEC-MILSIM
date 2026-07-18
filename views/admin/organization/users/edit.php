<?php
declare(strict_types=1);

use App\Support\OrganizationRoleLabels;

$user = $user ?? null;
$userProfile = is_array($userProfile ?? null) ? $userProfile : [];
$roles = $roles ?? [];
$roleMatrix = $roleMatrix ?? ['roles' => [], 'permissions' => [], 'byRole' => []];
$selectedRoleIds = $selectedRoleIds ?? [];
$grades = $grades ?? [];
$gradeCategories = $gradeCategories ?? [];
$positionsList = is_array($positionsList ?? null) ? $positionsList : [];
$userActivePositions = is_array($userActivePositions ?? null) ? $userActivePositions : [];
$roleSetsList = is_array($roleSetsList ?? null) ? $roleSetsList : [];
$organizationRoleLabelMode = $organizationRoleLabelMode ?? OrganizationRoleLabels::MODE_FR;
if (!$user) {
    echo '<p>Utilisateur introuvable.</p>';
    return;
}
$uid = (int) $user['id'];
$isServiceAccount = !empty($isServiceAccount);
$personnelEditUrl = url('personnel/' . $uid . '/edit');
$showUrl = url('back-office/users/' . $uid);
$updateUrl = url('back-office/users/' . $uid . '/update');

$displayName = trim((string) ($user['display_name'] ?? ''));
$email = (string) ($user['email'] ?? '');
$callsign = trim((string) ($user['callsign'] ?? ''));
$ust = (string) ($user['status'] ?? '');
$statusLabel = match ($ust) {
    'active' => 'Compte actif',
    'inactive' => 'Compte inactif',
    'pending_verification' => 'En attente de vérification de l’e-mail',
    default => $ust !== '' ? 'Statut à clarifier' : '—',
};
$statusBadgeClass = match ($ust) {
    'active' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
    'inactive' => 'bg-slate-100 text-slate-700 ring-slate-200',
    default => 'bg-amber-50 text-amber-900 ring-amber-200',
};

$avatarSrc = function_exists('user_media_public_url')
    ? user_media_public_url($user['avatar_url'] ?? null)
    : null;
$initialsSource = $displayName !== '' ? $displayName : $email;
$initials = function_exists('user_display_initials')
    ? user_display_initials($initialsSource, 2)
    : mb_strtoupper(mb_substr($initialsSource, 0, 2, 'UTF-8'), 'UTF-8');

$inputClass = 'mt-1.5 block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 hover:border-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20';
$labelClass = 'block text-sm font-semibold text-slate-800';
$helpClass = 'mt-1 text-xs leading-relaxed text-slate-500';
$sectionClass = 'rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6';
$sectionTitleClass = 'text-base font-black tracking-tight text-slate-950';
$sectionLeadClass = 'mt-1 text-sm leading-relaxed text-slate-600';

$byLayer = ['community' => [], 'intra' => []];
foreach ($roles as $r) {
    $ly = (string) ($r['role_layer'] ?? 'community');
    if (!isset($byLayer[$ly])) {
        $byLayer[$ly] = [];
    }
    $byLayer[$ly][] = $r;
}

$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');
$flashWarn = \App\Core\Session::getFlash('warning');
$gradeValidationIssues = $gradeValidationIssues ?? [];
?>
<div class="min-h-[calc(100vh-3.5rem)] bg-slate-50">
    <div class="mx-auto max-w-5xl px-4 pb-28 pt-8 sm:px-6 lg:px-8 lg:pt-10">
        <div class="space-y-8">

            <header class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 bg-gradient-to-br from-slate-950 via-slate-900 to-emerald-950 px-5 py-6 sm:px-8 sm:py-8">
                    <p class="text-[11px] font-bold uppercase tracking-[0.28em] text-emerald-400/90">Back-office communauté</p>
                    <div class="mt-4 flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex min-w-0 items-start gap-4">
                            <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-emerald-500/20 text-lg font-black text-white ring-2 ring-white/15 sm:h-20 sm:w-20 sm:text-xl" aria-hidden="true">
                                <?php if ($avatarSrc): ?>
                                    <img src="<?= htmlspecialchars($avatarSrc, ENT_QUOTES, 'UTF-8') ?>" alt="" class="h-full w-full object-cover">
                                <?php else: ?>
                                    <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </div>
                            <div class="min-w-0">
                                <h1 class="text-2xl font-black tracking-tight text-white sm:text-3xl">
                                    <?= htmlspecialchars($displayName !== '' ? $displayName : 'Compte membre', ENT_QUOTES, 'UTF-8') ?>
                                </h1>
                                <p class="mt-1 truncate text-sm text-slate-300"><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></p>
                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset <?= htmlspecialchars($statusBadgeClass, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <?php if ($callsign !== ''): ?>
                                    <span class="inline-flex items-center rounded-full bg-white/10 px-2.5 py-0.5 text-xs font-semibold text-emerald-100 ring-1 ring-white/15">
                                        Indicatif <?= htmlspecialchars($callsign, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <?php endif; ?>
                                    <?php if ($isServiceAccount): ?>
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">Compte technique</span>
                                    <?php endif; ?>
                                    <?php if (!$avatarSrc): ?>
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-900 ring-1 ring-amber-200">Pas de photo de profil</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="flex shrink-0 flex-wrap gap-2">
                            <a href="<?= htmlspecialchars($showUrl, ENT_QUOTES, 'UTF-8') ?>"
                               class="inline-flex items-center justify-center rounded-xl border border-white/15 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white backdrop-blur-sm transition hover:bg-white/15">
                                Voir la fiche
                            </a>
                            <?php if (!$isServiceAccount): ?>
                            <a href="<?= htmlspecialchars($personnelEditUrl, ENT_QUOTES, 'UTF-8') ?>"
                               class="inline-flex items-center justify-center rounded-xl bg-emerald-500 px-4 py-2.5 text-sm font-semibold text-slate-950 shadow-sm transition hover:bg-emerald-400">
                                Fiche personnelle
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="px-5 py-4 sm:px-8">
                    <p class="text-sm leading-relaxed text-slate-600">
                        Réglez ici le <strong class="font-semibold text-slate-900">compte de connexion</strong>, les rôles et le statut.
                        Le personnage, l’affectation et la clearance se gèrent sur la <a href="<?= htmlspecialchars($personnelEditUrl, ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-800 underline decoration-emerald-200 underline-offset-2 hover:decoration-emerald-600">fiche personnelle</a>.
                    </p>
                </div>
            </header>

            <?php if ($flashOk): ?>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status"><?= htmlspecialchars((string) $flashOk, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if ($flashWarn): ?>
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950" role="status"><?= htmlspecialchars((string) $flashWarn, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if ($flashErr): ?>
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert"><?= htmlspecialchars((string) $flashErr, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php foreach ($gradeValidationIssues as $i):
                $issueClass = ($i['type'] ?? '') === 'error'
                    ? 'border-rose-200 bg-rose-50 text-rose-900'
                    : 'border-amber-200 bg-amber-50 text-amber-950';
            ?>
            <div class="rounded-xl border px-4 py-3 text-sm <?= $issueClass ?>"><?= htmlspecialchars((string) ($i['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
            <?php endforeach; ?>

            <?php /* Formulaire principal — aucun autre <form> à l’intérieur (sinon le navigateur ferme trop tôt et Enregistrer ne poste plus). */ ?>
            <form id="user-admin-edit-form" method="post" action="<?= htmlspecialchars($updateUrl, ENT_QUOTES, 'UTF-8') ?>" class="space-y-8">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="user_roles_form" value="1">

                <section class="<?= $sectionClass ?>" aria-labelledby="sec-identity">
                    <h2 id="sec-identity" class="<?= $sectionTitleClass ?>">Identité affichée</h2>
                    <p class="<?= $sectionLeadClass ?>">Nom et indicatif visibles sur le portail. L’identité civile détaillée reste sur la fiche personnelle.</p>
                    <div class="mt-6 grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="display_name" class="<?= $labelClass ?>">Nom d’affichage</label>
                            <input type="text" id="display_name" name="display_name" class="<?= $inputClass ?>" value="<?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>" autocomplete="nickname">
                            <p class="<?= $helpClass ?>">Tel qu’il apparaît dans les listes et le forum.</p>
                        </div>
                        <div>
                            <label for="callsign" class="<?= $labelClass ?>">Indicatif (compte)</label>
                            <input type="text" id="callsign" name="callsign" class="<?= $inputClass ?>" value="<?= htmlspecialchars($callsign, ENT_QUOTES, 'UTF-8') ?>">
                            <p class="<?= $helpClass ?>">Indicatif lié au compte — distinct de l’indicatif de personnage.</p>
                        </div>
                    </div>
                </section>

                <section class="<?= $sectionClass ?>" aria-labelledby="sec-account">
                    <h2 id="sec-account" class="<?= $sectionTitleClass ?>">Compte &amp; accès</h2>
                    <p class="<?= $sectionLeadClass ?>">Adresse de connexion, mot de passe et état du compte dans la communauté.</p>
                    <div class="mt-6 grid gap-5 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="email" class="<?= $labelClass ?>">Adresse e-mail <span class="text-rose-600">*</span></label>
                            <input type="email" id="email" name="email" required class="<?= $inputClass ?>" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" autocomplete="email">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="password" class="<?= $labelClass ?>">Nouveau mot de passe</label>
                            <input type="password" id="password" name="password" minlength="6" class="<?= $inputClass ?>" autocomplete="new-password" placeholder="Laisser vide pour ne pas changer">
                            <p class="<?= $helpClass ?>">Au moins 6 caractères si vous en définissez un nouveau.</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="status" class="<?= $labelClass ?>">Statut du compte</label>
                            <select id="status" name="status" class="<?= htmlspecialchars(bo_select_class('mt-1.5'), ENT_QUOTES, 'UTF-8') ?>">
                                <option value="pending_verification" <?= $ust === 'pending_verification' ? 'selected' : '' ?>>En attente de vérification de l’e-mail</option>
                                <option value="active" <?= $ust === 'active' ? 'selected' : '' ?>>Compte actif</option>
                                <option value="inactive" <?= $ust === 'inactive' ? 'selected' : '' ?>>Compte inactif</option>
                            </select>
                            <p class="<?= $helpClass ?>">Un compte inactif ne peut plus se connecter à cette communauté.</p>
                        </div>
                    </div>
                </section>

                <section class="<?= $sectionClass ?>" aria-labelledby="sec-grade">
                    <h2 id="sec-grade" class="<?= $sectionTitleClass ?>">Grade &amp; doctrine</h2>
                    <p class="<?= $sectionLeadClass ?>">Référentiel de grade affiché côté compte administratif.</p>
                    <div class="mt-6 grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="nationality_code" class="<?= $labelClass ?>">Nationalité / doctrine</label>
                            <select id="nationality_code" name="nationality_code" class="<?= htmlspecialchars(bo_select_class('mt-1.5'), ENT_QUOTES, 'UTF-8') ?>">
                                <option value="">Non renseignée</option>
                                <option value="FR" <?= ($user['nationality_code'] ?? '') === 'FR' ? 'selected' : '' ?>>Française</option>
                                <option value="US" <?= ($user['nationality_code'] ?? '') === 'US' ? 'selected' : '' ?>>Américaine</option>
                            </select>
                        </div>
                        <div>
                            <label for="professional_category_code" class="<?= $labelClass ?>">Catégorie de personnel</label>
                            <select id="professional_category_code" name="professional_category_code" class="<?= htmlspecialchars(bo_select_class('mt-1.5'), ENT_QUOTES, 'UTF-8') ?>">
                                <option value="">Non renseignée</option>
                                <?php foreach ($gradeCategories as $c): ?>
                                <option value="<?= htmlspecialchars((string) $c['code'], ENT_QUOTES, 'UTF-8') ?>" <?= ($user['professional_category_code'] ?? '') === $c['code'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $c['label'], ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="grade_id" class="<?= $labelClass ?>">Grade</label>
                            <select id="grade_id" name="grade_id" class="<?= htmlspecialchars(bo_select_class('mt-1.5'), ENT_QUOTES, 'UTF-8') ?>">
                                <option value="">Aucun grade</option>
                                <?php foreach ($grades as $g): ?>
                                <option value="<?= (int) $g['id'] ?>" <?= (int) ($user['grade_id'] ?? 0) === (int) $g['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) ($g['label_long'] ?? $g['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="preferred_grade_format" class="<?= $labelClass ?>">Format d’affichage du grade</label>
                            <select id="preferred_grade_format" name="preferred_grade_format" class="<?= htmlspecialchars(bo_select_class('mt-1.5'), ENT_QUOTES, 'UTF-8') ?>">
                                <option value="classic" <?= ($user['preferred_grade_format'] ?? 'classic') === 'classic' ? 'selected' : '' ?>>Classique (texte)</option>
                                <option value="otan" <?= ($user['preferred_grade_format'] ?? '') === 'otan' ? 'selected' : '' ?>>OTAN</option>
                                <option value="hybrid" <?= ($user['preferred_grade_format'] ?? '') === 'hybrid' ? 'selected' : '' ?>>Hybride (ex. Capitaine (OF-2))</option>
                            </select>
                        </div>
                    </div>
                </section>

                <section class="<?= $sectionClass ?>" aria-labelledby="sec-roles">
                    <h2 id="sec-roles" class="<?= $sectionTitleClass ?>">Rôles dans la communauté</h2>
                    <p class="<?= $sectionLeadClass ?>">Cochez un ou plusieurs rôles. Les droits effectifs sont l’union des permissions. Les rôles site / plateforme ne sont pas listés ici.</p>

                    <div class="mt-6 grid gap-6 lg:grid-cols-2">
                        <?php if (!empty($byLayer['community'])): ?>
                        <div class="rounded-xl border border-slate-100 bg-slate-50/80 p-4">
                            <p class="mb-3 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500"><?= htmlspecialchars(OrganizationRoleLabels::layerGroupLabel('community', $organizationRoleLabelMode), ENT_QUOTES, 'UTF-8') ?></p>
                            <div class="space-y-2.5">
                                <?php foreach ($byLayer['community'] as $r):
                                    $rid = (int) $r['id'];
                                    $chk = in_array($rid, $selectedRoleIds, true);
                                    $rDisp = OrganizationRoleLabels::displayName($r, $organizationRoleLabelMode);
                                ?>
                                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-transparent bg-white px-3 py-2.5 shadow-sm ring-1 ring-slate-200/80 transition hover:ring-emerald-300 <?= $chk ? 'ring-emerald-400' : '' ?>">
                                    <input type="checkbox" name="role_ids[]" value="<?= $rid ?>" class="role-pick mt-0.5 rounded border-slate-300 text-emerald-700 focus:ring-emerald-500" <?= $chk ? 'checked' : '' ?> data-role-name="<?= htmlspecialchars($rDisp, ENT_QUOTES, 'UTF-8') ?>">
                                    <span>
                                        <span class="block text-sm font-semibold text-slate-900"><?= htmlspecialchars($rDisp, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if (!empty($r['description'])): ?>
                                        <span class="mt-0.5 block text-xs leading-relaxed text-slate-500"><?= htmlspecialchars((string) $r['description'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($byLayer['intra'])): ?>
                        <div class="rounded-xl border border-slate-100 bg-slate-50/80 p-4">
                            <p class="mb-3 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500"><?= htmlspecialchars(OrganizationRoleLabels::layerGroupLabel('intra', $organizationRoleLabelMode), ENT_QUOTES, 'UTF-8') ?></p>
                            <div class="space-y-2.5">
                                <?php foreach ($byLayer['intra'] as $r):
                                    $rid = (int) $r['id'];
                                    $chk = in_array($rid, $selectedRoleIds, true);
                                    $rDisp = OrganizationRoleLabels::displayName($r, $organizationRoleLabelMode);
                                ?>
                                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-transparent bg-white px-3 py-2.5 shadow-sm ring-1 ring-slate-200/80 transition hover:ring-emerald-300 <?= $chk ? 'ring-emerald-400' : '' ?>">
                                    <input type="checkbox" name="role_ids[]" value="<?= $rid ?>" class="role-pick mt-0.5 rounded border-slate-300 text-emerald-700 focus:ring-emerald-500" <?= $chk ? 'checked' : '' ?> data-role-name="<?= htmlspecialchars($rDisp, ENT_QUOTES, 'UTF-8') ?>">
                                    <span>
                                        <span class="block text-sm font-semibold text-slate-900"><?= htmlspecialchars($rDisp, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if (!empty($r['description'])): ?>
                                        <span class="mt-0.5 block text-xs leading-relaxed text-slate-500"><?= htmlspecialchars((string) $r['description'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div id="role-matrix-wrap" class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white">
                        <p class="border-b border-slate-100 px-4 py-3 text-xs font-semibold text-slate-700">Aperçu des droits (union selon les cases cochées)</p>
                        <table class="min-w-full text-xs">
                            <thead class="bg-slate-50 text-slate-600">
                                <tr>
                                    <th class="sticky left-0 z-10 border-r border-slate-100 bg-slate-50 p-2 text-left font-semibold">Droit</th>
                                    <?php foreach ($roleMatrix['roles'] as $rr): ?>
                                    <th class="role-col whitespace-nowrap p-2 text-center font-medium" data-role-id="<?= (int) $rr['id'] ?>"><?= htmlspecialchars(OrganizationRoleLabels::displayName($rr, $organizationRoleLabelMode), ENT_QUOTES, 'UTF-8') ?></th>
                                    <?php endforeach; ?>
                                    <th class="bg-emerald-50/80 p-2 text-center font-bold text-emerald-800">Union</th>
                                </tr>
                            </thead>
                            <tbody id="role-matrix-body">
                                <?php foreach ($roleMatrix['permissions'] as $p):
                                    $pid = (int) ($p['id'] ?? 0);
                                    $mod = trim((string) ($p['module'] ?? ''));
                                ?>
                                <tr class="perm-row border-t border-slate-100" data-perm-id="<?= $pid ?>">
                                    <td class="sticky left-0 z-10 border-r border-slate-100 bg-white p-2 align-top">
                                        <span class="font-medium text-slate-800"><?= htmlspecialchars((string) ($p['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if ($mod !== ''): ?><span class="mt-0.5 block text-[10px] text-slate-400"><?= htmlspecialchars($mod, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                                    </td>
                                    <?php foreach ($roleMatrix['roles'] as $rr):
                                        $rid = (int) $rr['id'];
                                        $has = !empty($roleMatrix['byRole'][$rid][$pid]);
                                    ?>
                                    <td class="role-cell p-2 text-center" data-role-id="<?= $rid ?>" data-perm-id="<?= $pid ?>"><?= $has ? '✓' : '—' ?></td>
                                    <?php endforeach; ?>
                                    <td class="union-cell bg-emerald-50/40 p-2 text-center font-semibold" data-perm-id="<?= $pid ?>">—</td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($roleMatrix['permissions'])): ?>
                                <tr><td colspan="99" class="p-4 text-center text-slate-500">Aucun droit lié aux rôles de cette communauté.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <?php if (!$isServiceAccount): ?>
                <aside class="rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 via-white to-cyan-50 p-5 shadow-sm sm:p-6">
                    <h2 class="text-base font-black text-slate-950">Personnage &amp; dossier opérationnel</h2>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">
                        Indicatif de personnage, unité, clearance et forum — distinct du compte ci-dessus. Ouvrez la fiche personnelle pour les modifier.
                    </p>
                    <a href="<?= htmlspecialchars($personnelEditUrl, ENT_QUOTES, 'UTF-8') ?>"
                       class="mt-4 inline-flex items-center gap-2 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-800">
                        Ouvrir la fiche personnelle
                        <span aria-hidden="true">→</span>
                    </a>
                </aside>
                <?php endif; ?>

                <div class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white/95 backdrop-blur-md">
                    <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-3.5 sm:px-6 lg:px-8">
                        <p class="hidden text-xs text-slate-500 sm:block">Les modifications du compte sont enregistrées sur cette page uniquement.</p>
                        <div class="flex w-full flex-wrap items-center justify-end gap-2 sm:w-auto">
                            <a href="<?= htmlspecialchars($showUrl, ENT_QUOTES, 'UTF-8') ?>"
                               class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                Annuler
                            </a>
                            <button type="submit"
                                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-950 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
                                <svg class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                Enregistrer
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            <?php if (!$isServiceAccount && $positionsList !== []): ?>
            <section class="<?= $sectionClass ?>" aria-labelledby="sec-positions">
                <h2 id="sec-positions" class="<?= $sectionTitleClass ?>">Poste organisationnel</h2>
                <p class="<?= $sectionLeadClass ?>">Affectation de fonction (distincte des rôles). <a href="<?= htmlspecialchars(url('back-office/positions'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-800 underline decoration-emerald-200 underline-offset-2 hover:decoration-emerald-600">Gérer les postes</a></p>
                <?php if ($userActivePositions !== []): ?>
                <ul class="mt-4 space-y-1.5 text-sm text-slate-700">
                    <?php foreach ($userActivePositions as $up): ?>
                    <li class="flex gap-2">
                        <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-500" aria-hidden="true"></span>
                        <span>
                            <?= htmlspecialchars((string) ($up['position_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            — depuis <?= htmlspecialchars((string) ($up['starts_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            <?php if (!empty($up['ends_at'])): ?> jusqu’au <?= htmlspecialchars((string) $up['ends_at'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <form method="post" action="<?= htmlspecialchars(url('back-office/users/' . $uid . '/assign-position'), ENT_QUOTES, 'UTF-8') ?>" class="mt-5 grid items-end gap-3 sm:grid-cols-2">
                    <?= \App\Core\Csrf::field() ?>
                    <div class="sm:col-span-2">
                        <label for="position_id" class="<?= $labelClass ?>">Poste</label>
                        <select id="position_id" name="position_id" required class="<?= htmlspecialchars(bo_select_class('mt-1.5'), ENT_QUOTES, 'UTF-8') ?>">
                            <option value="">Choisir un poste</option>
                            <?php foreach ($positionsList as $pos): ?>
                            <option value="<?= (int) ($pos['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($pos['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="starts_at" class="<?= $labelClass ?>">Date de début</label>
                        <input type="date" id="starts_at" name="starts_at" required class="<?= $inputClass ?>">
                    </div>
                    <div>
                        <label for="ends_at" class="<?= $labelClass ?>">Date de fin (optionnel)</label>
                        <input type="date" id="ends_at" name="ends_at" class="<?= $inputClass ?>">
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="inline-flex items-center rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-900">
                            Ajouter l’affectation
                        </button>
                    </div>
                </form>
            </section>
            <?php endif; ?>

            <?php if (!$isServiceAccount && $roleSetsList !== []): ?>
            <section class="rounded-2xl border border-amber-200 bg-amber-50/50 p-5 shadow-sm sm:p-6" aria-labelledby="sec-role-sets">
                <h2 id="sec-role-sets" class="<?= $sectionTitleClass ?>">Pack de rôles</h2>
                <p class="<?= $sectionLeadClass ?>">Ajoute en une fois les rôles du pack, <strong class="font-semibold text-slate-900">sans retirer</strong> ceux déjà cochés ci-dessus. Pensez ensuite à Enregistrer si vous avez aussi modifié le compte.</p>
                <form method="post" action="<?= htmlspecialchars(url('back-office/users/' . $uid . '/apply-role-set'), ENT_QUOTES, 'UTF-8') ?>" class="mt-5 flex flex-wrap items-end gap-3">
                    <?= \App\Core\Csrf::field() ?>
                    <div class="min-w-[200px] flex-1">
                        <label for="role_set_id" class="<?= $labelClass ?>">Pack</label>
                        <select id="role_set_id" name="role_set_id" required class="<?= htmlspecialchars(bo_select_class('mt-1.5'), ENT_QUOTES, 'UTF-8') ?>">
                            <option value="">Choisir un pack</option>
                            <?php foreach ($roleSetsList as $rs): ?>
                            <option value="<?= (int) ($rs['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($rs['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="inline-flex items-center rounded-xl bg-amber-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-amber-950">
                        Appliquer le pack
                    </button>
                </form>
            </section>
            <?php endif; ?>

            <p>
                <a href="<?= htmlspecialchars(url('back-office/users'), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-semibold text-slate-600 underline underline-offset-4 hover:text-slate-900">Retour à la liste des membres</a>
            </p>
        </div>
    </div>
</div>
<script>
(function () {
    var matrix = <?= json_encode($roleMatrix, JSON_UNESCAPED_UNICODE) ?>;
    var picks = document.querySelectorAll('.role-pick');
    function selectedIds() {
        var ids = [];
        picks.forEach(function (cb) { if (cb.checked) ids.push(parseInt(cb.value, 10)); });
        return ids;
    }
    function refreshUnion() {
        var ids = selectedIds();
        var byRole = matrix.byRole || {};
        document.querySelectorAll('.union-cell').forEach(function (cell) {
            var pid = parseInt(cell.getAttribute('data-perm-id'), 10);
            var ok = false;
            for (var i = 0; i < ids.length; i++) {
                var rid = ids[i];
                if (byRole[rid] && byRole[rid][pid]) { ok = true; break; }
            }
            cell.textContent = ok ? '✓' : '—';
            cell.classList.toggle('text-emerald-700', ok);
            cell.classList.toggle('text-slate-300', !ok);
        });
        document.querySelectorAll('.role-col').forEach(function (th) {
            var rid = parseInt(th.getAttribute('data-role-id'), 10);
            var on = ids.indexOf(rid) !== -1;
            th.classList.toggle('ring-2', on);
            th.classList.toggle('ring-emerald-400', on);
            th.classList.toggle('bg-emerald-50', on);
        });
    }
    picks.forEach(function (cb) { cb.addEventListener('change', refreshUnion); });
    refreshUnion();
})();
</script>
