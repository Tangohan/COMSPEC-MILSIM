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
$steamId = trim((string) ($user['steam_id'] ?? ''));
$steamWebConfigured = !empty($steamWebConfigured);
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
                            La situation RH (unité, ancienneté) se gère aussi depuis la fiche Effectifs.
                        </p>
                    </div>
                </div>
                <?php
                $memberHubUserId = $uid;
                $memberHubCurrent = 'account';
                $memberHubTheme = 'bo';
                require base_path('views/partials/member_hub_nav.php');
                ?>
            </div>
            <div class="bo-user-edit__hero-actions">
                <a href="<?= htmlspecialchars(effectifs_workspace_url('membres/' . $uid), ENT_QUOTES, 'UTF-8') ?>" class="bo-user-edit__btn bo-user-edit__btn--solid">Fiche Effectifs</a>
                <a href="<?= htmlspecialchars($showUrl, ENT_QUOTES, 'UTF-8') ?>" class="bo-user-edit__btn bo-user-edit__btn--ghost">Aperçu</a>
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
            <?php
            $rh = is_array($rhSituation ?? null) ? $rhSituation : [];
            $rhSeniority = trim((string) ($rh['seniority_label'] ?? ''));
            $rhUnit = trim((string) ($rh['unit_name'] ?? ''));
            $rhEnlist = trim((string) ($rh['enlistment_date'] ?? ''));
            $rhPre = trim((string) ($rh['pre_platform_start'] ?? ''));
            $rhOrg = trim((string) ($rh['org_founded_on'] ?? ''));
            ?>
            <?php if (!$isServiceAccount): ?>
            <section class="bo-user-edit__panel" aria-labelledby="sec-rh">
                <h2 id="sec-rh" class="bo-user-edit__panel-title">Situation RH</h2>
                <p class="bo-user-edit__panel-lead">Unité et ancienneté : les mêmes informations que sur la fiche Effectifs, pour ne pas jongler entre les écrans.</p>
                <div class="bo-user-edit__rh">
                    <dl class="bo-user-edit__rh-facts">
                        <dt>Unité</dt>
                        <dd><?= htmlspecialchars($rhUnit !== '' ? $rhUnit : 'Non renseignée', ENT_QUOTES, 'UTF-8') ?></dd>
                        <dt>Ancienneté réelle</dt>
                        <dd><?= htmlspecialchars($rhSeniority !== '' ? $rhSeniority : 'Non renseignée', ENT_QUOTES, 'UTF-8') ?></dd>
                        <?php if ($rhOrg !== ''): ?>
                        <dt>Création de l’organisation</dt>
                        <dd><?= htmlspecialchars(date('d/m/Y', strtotime($rhOrg)), ENT_QUOTES, 'UTF-8') ?></dd>
                        <?php endif; ?>
                    </dl>
                    <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('membres/' . $uid . '/anciennete'), ENT_QUOTES, 'UTF-8') ?>" class="bo-user-edit__subform bo-user-edit__subform--2">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="return_url" value="<?= htmlspecialchars(url('back-office/users/' . $uid . '/edit'), ENT_QUOTES, 'UTF-8') ?>">
                        <div>
                            <label for="edit-enlistment-date" class="bo-user-edit__label">Arrivée dans la communauté (sur Athena)</label>
                            <input type="date" id="edit-enlistment-date" name="enlistment_date" class="bo-user-edit__input" value="<?= htmlspecialchars($rhEnlist, ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div>
                            <label for="edit-pre-platform" class="bo-user-edit__label">Arrivée avant le site</label>
                            <input type="date" id="edit-pre-platform" name="pre_platform_start_date" class="bo-user-edit__input" value="<?= htmlspecialchars($rhPre, ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="bo-user-edit__field--full">
                            <p class="bo-user-edit__hint">Laissez vide s’il n’était pas membre avant l’ouverture du site.</p>
                            <button type="submit" class="bo-user-edit__btn bo-user-edit__btn--dark">Enregistrer l’ancienneté</button>
                        </div>
                    </form>
                </div>
            </section>
            <?php endif; ?>

            <form id="user-admin-edit-form" method="post" action="<?= htmlspecialchars($updateUrl, ENT_QUOTES, 'UTF-8') ?>" class="bo-user-edit__stack">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="user_roles_form" value="1">

                <section class="bo-user-edit__panel" aria-labelledby="sec-identity">
                    <h2 id="sec-identity" class="bo-user-edit__panel-title">Identité personnage</h2>
                    <p class="bo-user-edit__panel-lead">Prénom et nom du personnage uniquement — utilisés partout (listes, forum, dossier). L’indicatif reste optionnel.</p>
                    <div class="bo-user-edit__grid">
                        <div>
                            <label for="first_name" class="bo-user-edit__label">Prénom</label>
                            <input type="text" id="first_name" name="first_name" class="bo-user-edit__input" value="<?= htmlspecialchars(trim((string) ($userProfile['first_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?>" autocomplete="off" maxlength="100" required>
                        </div>
                        <div>
                            <label for="last_name" class="bo-user-edit__label">Nom</label>
                            <input type="text" id="last_name" name="last_name" class="bo-user-edit__input" value="<?= htmlspecialchars(trim((string) ($userProfile['last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?>" autocomplete="off" maxlength="100" required>
                        </div>
                        <div>
                            <label for="callsign" class="bo-user-edit__label">Indicatif (optionnel)</label>
                            <input type="text" id="callsign" name="callsign" class="bo-user-edit__input" value="<?= htmlspecialchars($callsign, ENT_QUOTES, 'UTF-8') ?>" maxlength="80">
                            <p class="bo-user-edit__hint">Surnom radio / callsign en mission.</p>
                        </div>
                    </div>
                </section>

                <section class="bo-user-edit__panel" aria-labelledby="sec-account">
                    <h2 id="sec-account" class="bo-user-edit__panel-title">Compte et accès</h2>
                    <p class="bo-user-edit__panel-lead">Adresse de connexion, mot de passe, état du compte et rattachement Steam pour la carte.</p>
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

                <section class="bo-user-edit__panel" aria-labelledby="sec-steam">
                    <h2 id="sec-steam" class="bo-user-edit__panel-title">Liaison Steam</h2>
                    <p class="bo-user-edit__panel-lead">Rattachez l’identifiant Steam du membre pour le retrouver sur la carte et parmi les opérateurs en liaison.</p>
                    <div class="bo-user-edit__grid">
                        <div style="grid-column: 1 / -1;">
                            <label for="steam_id" class="bo-user-edit__label">Identifiant Steam</label>
                            <input type="text" id="steam_id" name="steam_id" class="bo-user-edit__input" value="<?= htmlspecialchars($steamId, ENT_QUOTES, 'UTF-8') ?>" placeholder="Numéro Steam, format classique, ou adresse du profil" autocomplete="off" maxlength="512">
                            <p class="bo-user-edit__hint">Laissez vide pour retirer la liaison.</p>
                        </div>
                        <div>
                            <label class="bo-user-edit__label" style="display:flex;align-items:flex-start;gap:.55rem;font-weight:600;text-transform:none;letter-spacing:0;">
                                <input type="checkbox" name="sync_steam_profile" value="1" style="margin-top:.2rem" <?= $steamWebConfigured ? '' : 'disabled' ?>>
                                <span>Synchroniser photo / profil public à l’enregistrement</span>
                            </label>
                        </div>
                    </div>
                    <?php if (!$steamWebConfigured): ?>
                    <p class="bo-user-edit__hint" style="margin-top:.75rem">La lecture du profil public Steam n’est pas configurée sur ce serveur : l’identifiant peut tout de même être enregistré.</p>
                    <?php endif; ?>
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
                        Recherchez et ajoutez les rôles via la liste. Les droits effectifs restent l’union de tous les rôles sélectionnés.
                    </p>

                    <?php if ($roles === []): ?>
                    <p class="bo-user-edit__panel-lead">Aucun rôle communauté n’est encore défini.</p>
                    <?php else: ?>
                    <div class="bo-user-edit__roles">
                        <?php
                        $pickerId = 'user-edit-org-role-picker';
                        $matrixRootId = 'role-matrix-wrap';
                        $showMatrix = true;
                        $matrixOpen = false;
                        require base_path('views/admin/organization/partials/org_role_multi_picker.php');
                        ?>
                    </div>
                    <?php endif; ?>
                </section>

                <?php if (!$isServiceAccount): ?>
                <aside class="bo-user-edit__panel bo-user-edit__panel--soft">
                    <div>
                        <h2 class="bo-user-edit__panel-title">Dossier opérationnel</h2>
                        <p class="bo-user-edit__panel-lead">
                            Identité de personnage, habilitation détaillée et forum — distinct du compte ci-dessus.
                        </p>
                    </div>
                    <a href="<?= htmlspecialchars($personnelEditUrl, ENT_QUOTES, 'UTF-8') ?>" class="bo-user-edit__btn bo-user-edit__btn--dark">Ouvrir le dossier personnel →</a>
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
                <p class="bo-user-edit__panel-lead">Titre dans l’organisation (opérationnel, état-major ou administratif) — distinct des habilitations d’accès. <a href="<?= htmlspecialchars(url('back-office/positions'), ENT_QUOTES, 'UTF-8') ?>">Gérer les postes</a></p>
                <?php if ($userActivePositions !== []): ?>
                <ul class="bo-user-edit__pos-list">
                    <?php foreach ($userActivePositions as $up):
                        $startFr = $formatDateFr((string) ($up['starts_at'] ?? ''));
                        $endFr = $formatDateFr((string) ($up['ends_at'] ?? ''));
                        $upCat = (string) ($up['position_category'] ?? '');
                        $upCatLabel = $upCat !== '' ? \App\Repositories\PositionRepository::categoryLabel($upCat) : '';
                    ?>
                    <li>
                        <span class="bo-user-edit__pos-dot" aria-hidden="true"></span>
                        <span>
                            <?= htmlspecialchars((string) ($up['position_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            <?php if ($upCatLabel !== ''): ?>
                                <span class="bo-user-edit__muted"> (<?= htmlspecialchars($upCatLabel, ENT_QUOTES, 'UTF-8') ?>)</span>
                            <?php endif; ?>
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
                            <?php foreach ($positionsList as $pos):
                                $posCat = (string) ($pos['category'] ?? '');
                                $posCatLabel = $posCat !== '' ? \App\Repositories\PositionRepository::categoryLabel($posCat) : '';
                                $posLabel = (string) ($pos['name'] ?? '');
                                if ($posCatLabel !== '') {
                                    $posLabel .= ' — ' . $posCatLabel;
                                }
                            ?>
                            <option value="<?= (int) ($pos['id'] ?? 0) ?>"><?= htmlspecialchars($posLabel, ENT_QUOTES, 'UTF-8') ?></option>
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
                <p class="bo-user-edit__panel-lead">Ajoute en une fois les rôles du pack, <strong>sans retirer</strong> ceux déjà sélectionnés ci-dessus. Enregistrez ensuite si vous avez aussi modifié le compte.</p>
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
