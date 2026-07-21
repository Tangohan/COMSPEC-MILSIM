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
    echo '<p>Membre introuvable.</p>';
    return;
}
$uid = (int) $user['id'];
$isServiceAccount = !empty($isServiceAccount);
$personnelEditUrl = url('personnel/' . $uid . '/edit');
$showUrl = url('back-office/users/' . $uid);
$updateUrl = url('back-office/users/' . $uid . '/update');
$listUrl = url('back-office/users');

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
$statusBadgeMod = match ($ust) {
    'active' => 'bo-user-edit__badge--ok',
    'inactive' => 'bo-user-edit__badge--muted',
    default => 'bo-user-edit__badge--warn',
};

$avatarSrc = function_exists('user_media_public_url')
    ? user_media_public_url($user['avatar_url'] ?? null)
    : null;
$initialsSource = $displayName !== '' ? $displayName : $email;
$initials = function_exists('user_display_initials')
    ? user_display_initials($initialsSource, 2)
    : mb_strtoupper(mb_substr($initialsSource, 0, 2, 'UTF-8'), 'UTF-8');

$roleBucketLabel = static function (array $r): string {
    $tier = (string) ($r['semantic_tier'] ?? 'function');
    $sub = trim((string) ($r['subcategory'] ?? ''));
    $name = trim((string) ($r['name'] ?? ''));
    $slug = strtolower(trim((string) ($r['slug'] ?? '')));

    if ($tier === 'status' || $sub === 'Affichage' || str_contains($slug, 'probation') || str_contains($slug, 'trial')) {
        return 'Statut';
    }
    if (
        $sub === 'Commandement'
        || $sub === 'Encadrement'
        || $tier === 'authority'
        || preg_match('/^Chef(\s|$|’|\')/iu', $name) === 1
        || str_starts_with(mb_strtolower($name, 'UTF-8'), 'chef ')
    ) {
        return 'Chefs';
    }
    if (
        $sub === 'Combattant'
        || $sub === 'Spécialités'
        || $sub === 'Specialites'
        || $tier === 'specialty'
        || preg_match('/^(Fusilier|Grenadier|Mitrailleur|Tireur|Éclaireur|Eclaireur|Opérateur|Operateur|Spécialiste|Specialiste)\b/iu', $name) === 1
    ) {
        return 'Spécificité';
    }
    if ($sub !== '') {
        return $sub;
    }

    return match ($tier) {
        'support' => 'Soutien',
        'liaison' => 'Liaison',
        'function' => 'Fonction',
        default => 'Autres',
    };
};

$roleCategoryLabel = static function (array $r) use ($roleBucketLabel): string {
    $cat = trim((string) ($r['category'] ?? ''));
    if ($cat !== '') {
        return $cat;
    }
    $bucket = $roleBucketLabel($r);
    if ($bucket === 'Statut') {
        return 'Statut';
    }

    return 'Autres attributions';
};

$bucketSortOrder = [
    'Statut' => 10,
    'Chefs' => 20,
    'Spécificité' => 30,
    'Fonction' => 40,
    'Soutien' => 50,
    'Liaison' => 60,
    'Autres' => 90,
];

/** @var array<string, array<string, array<string, list<array<string, mixed>>>>> $rolesByLayerCategoryBucket */
$rolesByLayerCategoryBucket = ['community' => [], 'intra' => []];
foreach ($roles as $r) {
    $ly = (string) ($r['role_layer'] ?? 'community');
    if ($ly !== 'community' && $ly !== 'intra') {
        $ly = 'community';
    }
    $cat = $roleCategoryLabel($r);
    $bucket = $roleBucketLabel($r);
    $rolesByLayerCategoryBucket[$ly][$cat][$bucket][] = $r;
}

foreach ($rolesByLayerCategoryBucket as $ly => &$cats) {
    uksort($cats, static function (string $a, string $b): int {
        if ($a === 'Statut') {
            return -1;
        }
        if ($b === 'Statut') {
            return 1;
        }

        return strcasecmp($a, $b);
    });
    foreach ($cats as &$buckets) {
        uksort($buckets, static function (string $a, string $b) use ($bucketSortOrder): int {
            $oa = $bucketSortOrder[$a] ?? 80;
            $ob = $bucketSortOrder[$b] ?? 80;
            if ($oa !== $ob) {
                return $oa <=> $ob;
            }

            return strcasecmp($a, $b);
        });
        foreach ($buckets as &$list) {
            usort($list, static function (array $a, array $b): int {
                $pa = (int) ($a['display_priority'] ?? 0);
                $pb = (int) ($b['display_priority'] ?? 0);
                if ($pa !== $pb) {
                    return $pa <=> $pb;
                }

                return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
            });
        }
        unset($list);
    }
    unset($buckets);
}
unset($cats);

$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');
$flashWarn = \App\Core\Session::getFlash('warning');
$gradeValidationIssues = $gradeValidationIssues ?? [];

$formatDateFr = static function (?string $raw): string {
    if ($raw === null || $raw === '') {
        return '';
    }
    $t = strtotime($raw);

    return $t ? date('d/m/Y', $t) : $raw;
};
?>
<link href="<?= htmlspecialchars(asset_url('assets/css/back-office-users.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
<div class="bo-user-edit">
    <header class="bo-user-edit__hero">
        <div class="bo-user-edit__hero-inner">
            <div class="min-w-0">
                <a href="<?= htmlspecialchars($listUrl, ENT_QUOTES, 'UTF-8') ?>" class="bo-user-edit__back">← Liste des membres</a>
                <p class="bo-user-edit__eyebrow">Réglages du compte</p>
                <div class="bo-user-edit__identity">
                    <div class="bo-user-edit__avatar" aria-hidden="true">
                        <?php if ($avatarSrc): ?>
                            <img src="<?= htmlspecialchars($avatarSrc, ENT_QUOTES, 'UTF-8') ?>" alt="">
                        <?php else: ?>
                            <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                    </div>
                    <div class="min-w-0">
                        <h1 class="bo-user-edit__title">
                            <?= htmlspecialchars($displayName !== '' ? $displayName : 'Compte membre', ENT_QUOTES, 'UTF-8') ?>
                        </h1>
                        <p class="bo-user-edit__email"><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></p>
                        <div class="bo-user-edit__badges">
                            <span class="bo-user-edit__badge <?= htmlspecialchars($statusBadgeMod, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <?php if ($callsign !== ''): ?>
                            <span class="bo-user-edit__badge bo-user-edit__badge--ok">Indicatif <?= htmlspecialchars($callsign, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                            <?php if ($isServiceAccount): ?>
                            <span class="bo-user-edit__badge bo-user-edit__badge--muted">Compte technique</span>
                            <?php endif; ?>
                            <?php if (!$avatarSrc): ?>
                            <span class="bo-user-edit__badge bo-user-edit__badge--warn">Pas de photo de profil</span>
                            <?php endif; ?>
                        </div>
                        <p class="bo-user-edit__lead">
                            Compte de connexion, rôles et statut.
                            <?php if (!$isServiceAccount): ?>
                            Le personnage et l’affectation opérationnelle se règlent sur la
                            <a href="<?= htmlspecialchars($personnelEditUrl, ENT_QUOTES, 'UTF-8') ?>">fiche personnelle</a>.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="bo-user-edit__hero-actions">
                <a href="<?= htmlspecialchars($showUrl, ENT_QUOTES, 'UTF-8') ?>" class="bo-user-edit__btn bo-user-edit__btn--ghost">Voir la fiche</a>
                <?php if (!$isServiceAccount): ?>
                <a href="<?= htmlspecialchars($personnelEditUrl, ENT_QUOTES, 'UTF-8') ?>" class="bo-user-edit__btn bo-user-edit__btn--solid">Fiche personnelle</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="bo-user-edit__deck">
        <div class="bo-user-edit__stack">
            <?php if ($flashOk): ?>
            <div class="bo-user-edit__flash bo-user-edit__flash--ok" role="status"><?= htmlspecialchars((string) $flashOk, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if ($flashWarn): ?>
            <div class="bo-user-edit__flash bo-user-edit__flash--warn" role="status"><?= htmlspecialchars((string) $flashWarn, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if ($flashErr): ?>
            <div class="bo-user-edit__flash bo-user-edit__flash--err" role="alert"><?= htmlspecialchars((string) $flashErr, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php foreach ($gradeValidationIssues as $i):
                $issueMod = ($i['type'] ?? '') === 'error'
                    ? 'bo-user-edit__flash--err'
                    : 'bo-user-edit__flash--warn';
            ?>
            <div class="bo-user-edit__flash <?= $issueMod ?>"><?= htmlspecialchars((string) ($i['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
            <?php endforeach; ?>

            <?php /* Formulaire principal — aucun autre <form> à l’intérieur (sinon le navigateur ferme trop tôt). */ ?>
            <form id="user-admin-edit-form" method="post" action="<?= htmlspecialchars($updateUrl, ENT_QUOTES, 'UTF-8') ?>" class="bo-user-edit__stack">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="user_roles_form" value="1">

                <section class="bo-user-edit__panel" aria-labelledby="sec-identity">
                    <h2 id="sec-identity" class="bo-user-edit__panel-title">Identité affichée</h2>
                    <p class="bo-user-edit__panel-lead">Nom et indicatif visibles sur le portail. L’identité civile détaillée reste sur la fiche personnelle.</p>
                    <div class="bo-user-edit__grid">
                        <div>
                            <label for="display_name" class="bo-user-edit__label">Nom d’affichage</label>
                            <input type="text" id="display_name" name="display_name" class="bo-user-edit__input" value="<?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>" autocomplete="nickname" maxlength="160">
                            <p class="bo-user-edit__hint">Tel qu’il apparaît dans les listes et le forum.</p>
                        </div>
                        <div>
                            <label for="callsign" class="bo-user-edit__label">Indicatif (compte)</label>
                            <input type="text" id="callsign" name="callsign" class="bo-user-edit__input" value="<?= htmlspecialchars($callsign, ENT_QUOTES, 'UTF-8') ?>" maxlength="80">
                            <p class="bo-user-edit__hint">Indicatif lié au compte — distinct de l’indicatif de personnage.</p>
                        </div>
                    </div>
                </section>

                <section class="bo-user-edit__panel" aria-labelledby="sec-account">
                    <h2 id="sec-account" class="bo-user-edit__panel-title">Compte et accès</h2>
                    <p class="bo-user-edit__panel-lead">Adresse de connexion, mot de passe et état du compte dans la communauté.</p>
                    <div class="bo-user-edit__grid">
                        <div>
                            <label for="email" class="bo-user-edit__label">Adresse e-mail <span class="req">*</span></label>
                            <input type="email" id="email" name="email" required class="bo-user-edit__input" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" autocomplete="email" maxlength="190">
                        </div>
                        <div>
                            <label for="password" class="bo-user-edit__label">Nouveau mot de passe</label>
                            <input type="password" id="password" name="password" minlength="6" class="bo-user-edit__input" autocomplete="new-password" placeholder="Laisser vide pour ne pas changer">
                            <p class="bo-user-edit__hint">Au moins 6 caractères si vous en définissez un nouveau.</p>
                        </div>
                        <div>
                            <label for="status" class="bo-user-edit__label">Statut du compte</label>
                            <select id="status" name="status" class="bo-user-edit__select">
                                <option value="pending_verification" <?= $ust === 'pending_verification' ? 'selected' : '' ?>>En attente de vérification de l’e-mail</option>
                                <option value="active" <?= $ust === 'active' ? 'selected' : '' ?>>Compte actif</option>
                                <option value="inactive" <?= $ust === 'inactive' ? 'selected' : '' ?>>Compte inactif</option>
                            </select>
                            <p class="bo-user-edit__hint">Un compte inactif ne peut plus se connecter à cette communauté.</p>
                        </div>
                    </div>
                </section>

                <section class="bo-user-edit__panel" aria-labelledby="sec-grade">
                    <h2 id="sec-grade" class="bo-user-edit__panel-title">Grade et doctrine</h2>
                    <p class="bo-user-edit__panel-lead">Référentiel de grade affiché côté compte administratif.</p>
                    <div class="bo-user-edit__grid">
                        <div>
                            <label for="nationality_code" class="bo-user-edit__label">Nationalité / doctrine</label>
                            <select id="nationality_code" name="nationality_code" class="bo-user-edit__select">
                                <option value="">Non renseignée</option>
                                <option value="FR" <?= ($user['nationality_code'] ?? '') === 'FR' ? 'selected' : '' ?>>Française</option>
                                <option value="US" <?= ($user['nationality_code'] ?? '') === 'US' ? 'selected' : '' ?>>Américaine</option>
                            </select>
                        </div>
                        <div>
                            <label for="professional_category_code" class="bo-user-edit__label">Catégorie de personnel</label>
                            <select id="professional_category_code" name="professional_category_code" class="bo-user-edit__select">
                                <option value="">Non renseignée</option>
                                <?php foreach ($gradeCategories as $c): ?>
                                <option value="<?= htmlspecialchars((string) $c['code'], ENT_QUOTES, 'UTF-8') ?>" <?= ($user['professional_category_code'] ?? '') === $c['code'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $c['label'], ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="grade_id" class="bo-user-edit__label">Grade</label>
                            <select id="grade_id" name="grade_id" class="bo-user-edit__select">
                                <option value="">Aucun grade</option>
                                <?php foreach ($grades as $g): ?>
                                <option value="<?= (int) $g['id'] ?>" <?= (int) ($user['grade_id'] ?? 0) === (int) $g['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) ($g['label_long'] ?? $g['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="preferred_grade_format" class="bo-user-edit__label">Format d’affichage du grade</label>
                            <select id="preferred_grade_format" name="preferred_grade_format" class="bo-user-edit__select">
                                <option value="classic" <?= ($user['preferred_grade_format'] ?? 'classic') === 'classic' ? 'selected' : '' ?>>Classique (texte)</option>
                                <option value="otan" <?= ($user['preferred_grade_format'] ?? '') === 'otan' ? 'selected' : '' ?>>OTAN</option>
                                <option value="hybrid" <?= ($user['preferred_grade_format'] ?? '') === 'hybrid' ? 'selected' : '' ?>>Hybride (ex. Capitaine (OF-2))</option>
                            </select>
                        </div>
                    </div>
                </section>

                <section class="bo-user-edit__panel" aria-labelledby="sec-roles">
                    <h2 id="sec-roles" class="bo-user-edit__panel-title">Rôles dans la communauté</h2>
                    <p class="bo-user-edit__panel-lead">
                        Les rôles sont regroupés par domaine, puis par type d’attribution&nbsp;:
                        <strong>Statut</strong> (période d’essai, service, etc.),
                        <strong>Chefs</strong> (commandement et encadrement),
                        <strong>Spécificité</strong> (fusilier, grenadier, tireurs…).
                        Les droits effectifs restent l’union de tous les rôles cochés.
                    </p>

                    <div class="bo-user-edit__roles">
                        <?php
                        $hasAnyOrgRole = false;
                        foreach (['community', 'intra'] as $layerKey):
                            $cats = $rolesByLayerCategoryBucket[$layerKey] ?? [];
                            if ($cats === []) {
                                continue;
                            }
                            $hasAnyOrgRole = true;
                        ?>
                        <div class="bo-user-edit__role-layer">
                            <p class="bo-user-edit__role-layer-title"><?= htmlspecialchars(OrganizationRoleLabels::layerGroupLabel($layerKey, $organizationRoleLabelMode), ENT_QUOTES, 'UTF-8') ?></p>
                            <?php foreach ($cats as $catLabel => $buckets): ?>
                            <div class="bo-user-edit__role-group">
                                <p class="bo-user-edit__role-group-title"><?= htmlspecialchars((string) $catLabel, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php foreach ($buckets as $bucketLabel => $bucketRoles): ?>
                                <div class="bo-user-edit__role-bucket">
                                    <p class="bo-user-edit__role-bucket-title"><?= htmlspecialchars((string) $bucketLabel, ENT_QUOTES, 'UTF-8') ?></p>
                                    <div class="bo-user-edit__role-list">
                                        <?php foreach ($bucketRoles as $r):
                                            $rid = (int) $r['id'];
                                            $chk = in_array($rid, $selectedRoleIds, true);
                                            $rDisp = OrganizationRoleLabels::displayName($r, $organizationRoleLabelMode);
                                        ?>
                                        <label class="bo-user-edit__role <?= $chk ? 'is-on' : '' ?>">
                                            <input type="checkbox" name="role_ids[]" value="<?= $rid ?>" class="role-pick" <?= $chk ? 'checked' : '' ?> data-role-name="<?= htmlspecialchars($rDisp, ENT_QUOTES, 'UTF-8') ?>">
                                            <span>
                                                <span class="bo-user-edit__role-name"><?= htmlspecialchars($rDisp, ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php if (!empty($r['description'])): ?>
                                                <span class="bo-user-edit__role-desc"><?= htmlspecialchars((string) $r['description'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php endif; ?>
                                            </span>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>
                        <?php if (!$hasAnyOrgRole): ?>
                        <p class="bo-user-edit__panel-lead">Aucun rôle communauté n’est encore défini.</p>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($roleMatrix['permissions'])): ?>
                    <div id="role-matrix-wrap" class="bo-user-edit__matrix">
                        <p class="bo-user-edit__matrix-cap">Aperçu des droits cumulés selon les cases cochées</p>
                        <table>
                            <thead>
                                <tr>
                                    <th>Droit</th>
                                    <?php foreach ($roleMatrix['roles'] as $rr): ?>
                                    <th class="role-col" data-role-id="<?= (int) $rr['id'] ?>"><?= htmlspecialchars(OrganizationRoleLabels::displayName($rr, $organizationRoleLabelMode), ENT_QUOTES, 'UTF-8') ?></th>
                                    <?php endforeach; ?>
                                    <th>Cumulé</th>
                                </tr>
                            </thead>
                            <tbody id="role-matrix-body">
                                <?php foreach ($roleMatrix['permissions'] as $p):
                                    $pid = (int) ($p['id'] ?? 0);
                                ?>
                                <tr class="perm-row" data-perm-id="<?= $pid ?>">
                                    <td>
                                        <span><?= htmlspecialchars((string) ($p['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                    </td>
                                    <?php foreach ($roleMatrix['roles'] as $rr):
                                        $rid = (int) $rr['id'];
                                        $has = !empty($roleMatrix['byRole'][$rid][$pid]);
                                    ?>
                                    <td class="role-cell" data-role-id="<?= $rid ?>" data-perm-id="<?= $pid ?>"><?= $has ? '✓' : '—' ?></td>
                                    <?php endforeach; ?>
                                    <td class="union-cell is-off" data-perm-id="<?= $pid ?>">—</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </section>

                <?php if (!$isServiceAccount): ?>
                <aside class="bo-user-edit__panel bo-user-edit__panel--soft">
                    <div>
                        <h2 class="bo-user-edit__panel-title">Personnage et dossier opérationnel</h2>
                        <p class="bo-user-edit__panel-lead">
                            Indicatif de personnage, unité, habilitation et forum — distinct du compte ci-dessus.
                        </p>
                    </div>
                    <a href="<?= htmlspecialchars($personnelEditUrl, ENT_QUOTES, 'UTF-8') ?>" class="bo-user-edit__btn bo-user-edit__btn--dark">Ouvrir la fiche personnelle →</a>
                </aside>
                <?php endif; ?>

                <div class="bo-user-edit__dock">
                    <div class="bo-user-edit__dock-inner">
                        <p class="bo-user-edit__dock-hint">Les modifications du compte sont enregistrées sur cette page uniquement.</p>
                        <div class="bo-user-edit__dock-actions">
                            <a href="<?= htmlspecialchars($showUrl, ENT_QUOTES, 'UTF-8') ?>" class="bo-user-edit__btn bo-user-edit__btn--ghost">Annuler</a>
                            <button type="submit" class="bo-user-edit__btn bo-user-edit__btn--dark">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                Enregistrer
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            <?php if (!$isServiceAccount && $positionsList !== []): ?>
            <section class="bo-user-edit__panel" aria-labelledby="sec-positions">
                <h2 id="sec-positions" class="bo-user-edit__panel-title">Poste organisationnel</h2>
                <p class="bo-user-edit__panel-lead">Affectation de fonction (distincte des rôles). <a href="<?= htmlspecialchars(url('back-office/positions'), ENT_QUOTES, 'UTF-8') ?>">Gérer les postes</a></p>
                <?php if ($userActivePositions !== []): ?>
                <ul class="bo-user-edit__pos-list">
                    <?php foreach ($userActivePositions as $up):
                        $startFr = $formatDateFr((string) ($up['starts_at'] ?? ''));
                        $endFr = $formatDateFr((string) ($up['ends_at'] ?? ''));
                    ?>
                    <li>
                        <span class="bo-user-edit__pos-dot" aria-hidden="true"></span>
                        <span>
                            <?= htmlspecialchars((string) ($up['position_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            <?php if ($startFr !== ''): ?> — depuis <?= htmlspecialchars($startFr, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                            <?php if ($endFr !== ''): ?> jusqu’au <?= htmlspecialchars($endFr, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <p class="bo-user-edit__panel-lead" style="margin-top: 0.85rem;">Aucune affectation active pour le moment.</p>
                <?php endif; ?>
                <form method="post" action="<?= htmlspecialchars(url('back-office/users/' . $uid . '/assign-position'), ENT_QUOTES, 'UTF-8') ?>" class="bo-user-edit__subform bo-user-edit__subform--2">
                    <?= \App\Core\Csrf::field() ?>
                    <div class="bo-user-edit__field--full">
                        <label for="position_id" class="bo-user-edit__label">Poste</label>
                        <select id="position_id" name="position_id" required class="bo-user-edit__select">
                            <option value="">Choisir un poste</option>
                            <?php foreach ($positionsList as $pos): ?>
                            <option value="<?= (int) ($pos['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($pos['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="starts_at" class="bo-user-edit__label">Date de début</label>
                        <input type="date" id="starts_at" name="starts_at" required class="bo-user-edit__input" value="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div>
                        <label for="ends_at" class="bo-user-edit__label">Date de fin (optionnel)</label>
                        <input type="date" id="ends_at" name="ends_at" class="bo-user-edit__input">
                    </div>
                    <div class="bo-user-edit__field--full">
                        <button type="submit" class="bo-user-edit__btn bo-user-edit__btn--dark">Ajouter l’affectation</button>
                    </div>
                </form>
            </section>
            <?php endif; ?>

            <?php if (!$isServiceAccount && $roleSetsList !== []): ?>
            <section class="bo-user-edit__panel bo-user-edit__panel--amber" aria-labelledby="sec-role-sets">
                <h2 id="sec-role-sets" class="bo-user-edit__panel-title">Pack de rôles</h2>
                <p class="bo-user-edit__panel-lead">Ajoute en une fois les rôles du pack, <strong>sans retirer</strong> ceux déjà cochés ci-dessus. Enregistrez ensuite si vous avez aussi modifié le compte.</p>
                <form method="post" action="<?= htmlspecialchars(url('back-office/users/' . $uid . '/apply-role-set'), ENT_QUOTES, 'UTF-8') ?>" class="bo-user-edit__subform bo-user-edit__subform--pack">
                    <?= \App\Core\Csrf::field() ?>
                    <div>
                        <label for="role_set_id" class="bo-user-edit__label">Pack</label>
                        <select id="role_set_id" name="role_set_id" required class="bo-user-edit__select">
                            <option value="">Choisir un pack</option>
                            <?php foreach ($roleSetsList as $rs): ?>
                            <option value="<?= (int) ($rs['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($rs['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="bo-user-edit__btn bo-user-edit__btn--amber">Appliquer le pack</button>
                </form>
            </section>
            <?php endif; ?>

            <p class="bo-user-edit__footer-link">
                <a href="<?= htmlspecialchars($listUrl, ENT_QUOTES, 'UTF-8') ?>">Retour à la liste des membres</a>
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
            cell.classList.toggle('is-on', ok);
            cell.classList.toggle('is-off', !ok);
        });
        document.querySelectorAll('.role-col').forEach(function (th) {
            var rid = parseInt(th.getAttribute('data-role-id'), 10);
            th.classList.toggle('is-on', ids.indexOf(rid) !== -1);
        });
        picks.forEach(function (cb) {
            var lab = cb.closest('.bo-user-edit__role');
            if (lab) lab.classList.toggle('is-on', cb.checked);
        });
    }
    picks.forEach(function (cb) { cb.addEventListener('change', refreshUnion); });
    refreshUnion();
})();
</script>
