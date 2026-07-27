<?php
declare(strict_types=1);

/**
 * Structure & recrutement — charte ATHENA.
 *
 * L’en-tête de page est rendu par la coque back-office. La page pose la barre d’actions,
 * délègue l’organigramme interactif à `views/partials/orbat/orbat_canvas.php`, puis
 * fournit les trois fenêtres de création (membre, regroupement, équipe).
 *
 * @var string $structureHubOpen Fenêtre à ouvrir au chargement ('membre' | 'groupe' | 'equipe' | '')
 * @var list<array{id: int, name: string}> $groupParents
 * @var list<array{id: int, name: string}> $teamParents
 * @var list<array<string, mixed>> $usersForCommander
 * @var list<array<string, mixed>> $roles
 * @var array{roles: list<mixed>, permissions: list<mixed>, byRole: array<mixed>} $roleMatrix
 * @var list<array<string, mixed>> $grades
 * @var list<array<string, mixed>> $gradeCategories
 * @var string $organizationRoleLabelMode
 */

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$structureHubOpen = (string) ($structureHubOpen ?? '');
$groupParents = is_array($groupParents ?? null) ? $groupParents : [];
$teamParents = is_array($teamParents ?? null) ? $teamParents : [];
$usersForCommander = is_array($usersForCommander ?? null) ? $usersForCommander : [];
$roles = is_array($roles ?? null) ? $roles : [];
$roleMatrix = is_array($roleMatrix ?? null) ? $roleMatrix : ['roles' => [], 'permissions' => [], 'byRole' => []];
$grades = is_array($grades ?? null) ? $grades : [];
$gradeCategories = is_array($gradeCategories ?? null) ? $gradeCategories : [];
$organizationRoleLabelMode = $organizationRoleLabelMode ?? \App\Support\OrganizationRoleLabels::MODE_FR;

$okOpen = in_array($structureHubOpen, ['membre', 'groupe', 'equipe'], true);
$hubFlashError = \App\Core\Session::getFlash('error');
$hubFlashSuccess = \App\Core\Session::getFlash('success');

/**
 * Libellé d’un responsable dans les listes déroulantes.
 *
 * `display_name` peut être une chaîne vide plutôt que `null` : un simple `??` laissait
 * alors une option sans texte, impossible à choisir à l’aveugle.
 *
 * @param array<string, mixed> $user
 */
$commanderLabel = static function (array $user): string {
    $name = trim((string) ($user['display_name'] ?? ''));
    if ($name !== '') {
        return $name;
    }
    $email = trim((string) ($user['email'] ?? ''));

    return $email !== '' ? $email : 'Membre #' . (int) ($user['id'] ?? 0);
};

/**
 * Les fenêtres « regroupement » et « équipe » ne diffèrent que par leur cible et leurs
 * parents possibles : une seule description évite d’entretenir deux fois le même
 * formulaire de six champs.
 *
 * @var array<string, array{id: string, title: string, action: string, prefix: string, parents: list<array{id: int, name: string}>, submit: string}>
 */
$unitDialogs = [
    'groupe' => [
        'id' => 'hub-dlg-groupe',
        'title' => 'Nouveau regroupement',
        'action' => url('back-office/groups/store'),
        'prefix' => 'hub_grp',
        'parents' => $groupParents,
        'submit' => 'Créer le regroupement',
    ],
    'equipe' => [
        'id' => 'hub-dlg-equipe',
        'title' => 'Nouvelle équipe',
        'action' => url('back-office/teams/store'),
        'prefix' => 'hub_team',
        'parents' => $teamParents,
        'submit' => 'Créer l’équipe',
    ],
];
?>
<?php if ($hubFlashError): ?>
<p class="ath-flash ath-flash--err" role="alert"><?= $h((string) $hubFlashError) ?></p>
<?php endif; ?>
<?php if ($hubFlashSuccess): ?>
<p class="ath-flash ath-flash--ok" role="status"><?= $h((string) $hubFlashSuccess) ?></p>
<?php endif; ?>

<div class="ath-note">
    <p class="ath-note__title">Organigramme interactif</p>
    <p class="ath-note__text">
        Créez un regroupement, une équipe ou invitez un membre depuis la barre d’actions,
        ou par clic droit sur une carte du type correspondant dans l’organigramme.
    </p>
</div>

<div class="ath-form__actions" style="border-top:0;margin:0 0 16px;padding-top:0;">
    <button type="button" id="hub-btn-membre" class="ath-btn ath-btn--solid">Inviter un membre</button>
    <button type="button" id="hub-btn-groupe" class="ath-btn">Nouveau regroupement</button>
    <button type="button" id="hub-btn-equipe" class="ath-btn">Nouvelle équipe</button>
    <a href="<?= $h(url('back-office/organisation-effectifs')) ?>" class="ath-btn">Structure &amp; grades</a>
</div>

<?php require base_path('views/partials/orbat/orbat_canvas.php'); ?>

<dialog id="hub-dlg-membre" class="ath-dialog ath-dialog--wide">
    <form method="post" action="<?= $h(url('back-office/users/store')) ?>" class="ath-dialog__form">
        <div class="ath-dialog__head ath-dialog__head--split">
            <div>
                <h2 class="ath-dialog__title">Inviter un membre</h2>
                <p class="ath-dialog__sub">Un e-mail permettra à la personne de définir son mot de passe.</p>
            </div>
            <button type="button" class="ath-dialog__close" data-hub-close="hub-dlg-membre" aria-label="Fermer">✕</button>
        </div>
        <div class="ath-dialog__body">
            <?= \App\Core\Csrf::field() ?>
            <?php
            $fieldIdPrefix = 'hub-user-';
            $matrixRootId = 'hub-role-matrix-wrap';
            require base_path('views/admin/organization/partials/user_invite_form_fields.php');
            ?>
        </div>
        <div class="ath-dialog__foot">
            <button type="button" class="ath-btn" data-hub-close="hub-dlg-membre">Annuler</button>
            <button type="submit" class="ath-btn ath-btn--solid">Créer et envoyer l’e-mail</button>
        </div>
    </form>
</dialog>

<?php foreach ($unitDialogs as $dialog): ?>
<dialog id="<?= $h($dialog['id']) ?>" class="ath-dialog">
    <form method="post" action="<?= $h($dialog['action']) ?>" class="ath-dialog__form">
        <div class="ath-dialog__head ath-dialog__head--split">
            <h2 class="ath-dialog__title"><?= $h($dialog['title']) ?></h2>
            <button type="button" class="ath-dialog__close" data-hub-close="<?= $h($dialog['id']) ?>" aria-label="Fermer">✕</button>
        </div>
        <div class="ath-dialog__body">
            <?= \App\Core\Csrf::field() ?>
            <div class="ath-form__grid ath-form__grid--wide">
                <label class="ath-field">
                    <span class="ath-field__label">Nom *</span>
                    <input type="text" id="<?= $h($dialog['prefix']) ?>_name" name="name" required class="ath-field__input">
                </label>
                <label class="ath-field">
                    <span class="ath-field__label">Adresse courte dans l’URL</span>
                    <input type="text" id="<?= $h($dialog['prefix']) ?>_slug" name="slug" class="ath-field__input">
                    <span class="ath-field__help">Laissez vide pour la déduire du nom.</span>
                </label>
                <label class="ath-field">
                    <span class="ath-field__label">Code</span>
                    <input type="text" id="<?= $h($dialog['prefix']) ?>_code" name="code" class="ath-field__input">
                </label>
                <label class="ath-field">
                    <span class="ath-field__label">Rattaché sous</span>
                    <select id="<?= $h($dialog['prefix']) ?>_parent_id" name="parent_id" class="ath-field__select">
                        <option value="">— Racine —</option>
                        <?php foreach ($dialog['parents'] as $p): ?>
                        <option value="<?= (int) ($p['id'] ?? 0) ?>"><?= $h((string) ($p['name'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="ath-field">
                    <span class="ath-field__label">Responsable</span>
                    <select id="<?= $h($dialog['prefix']) ?>_commander" name="commander_user_id" class="ath-field__select">
                        <option value="">—</option>
                        <?php foreach ($usersForCommander as $u): ?>
                        <option value="<?= (int) ($u['id'] ?? 0) ?>"><?= $h($commanderLabel($u)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="ath-field">
                    <span class="ath-field__label">Ordre d’affichage</span>
                    <input type="number" id="<?= $h($dialog['prefix']) ?>_order" name="display_order" value="0" class="ath-field__input">
                </label>
            </div>
        </div>
        <div class="ath-dialog__foot">
            <button type="button" class="ath-btn" data-hub-close="<?= $h($dialog['id']) ?>">Annuler</button>
            <button type="submit" class="ath-btn ath-btn--solid"><?= $h($dialog['submit']) ?></button>
        </div>
    </form>
</dialog>
<?php endforeach; ?>

<script>
/*
 * `orbatHubOpenRecruitmentModal` est appelée depuis l’organigramme (clic droit sur une
 * carte) : elle reste exposée sur `window` et présélectionne l’unité de rattachement.
 */
(function () {
  var initialOpen = <?= json_encode($okOpen ? $structureHubOpen : '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>;

  var showDialog = function (id) {
    var el = document.getElementById(id);
    if (el && typeof el.showModal === 'function') {
      el.showModal();
    }
  };

  var presetParent = function (selectId, parentUnitId) {
    var select = document.getElementById(selectId);
    if (!select) return;
    var value = parentUnitId > 0 ? String(parentUnitId) : '';
    // Un parent hors de la liste (autre type d’unité) retombe sur la racine.
    select.value = value && select.querySelector('option[value="' + value + '"]') ? value : '';
  };

  window.orbatHubOpenRecruitmentModal = function (kind, parentUnitId) {
    parentUnitId = parentUnitId || 0;
    if (kind === 'groupe') {
      presetParent('hub_grp_parent_id', parentUnitId);
      showDialog('hub-dlg-groupe');
    } else if (kind === 'equipe') {
      presetParent('hub_team_parent_id', parentUnitId);
      showDialog('hub-dlg-equipe');
    } else if (kind === 'membre') {
      showDialog('hub-dlg-membre');
    }
  };

  ['membre', 'groupe', 'equipe'].forEach(function (kind) {
    var btn = document.getElementById('hub-btn-' + kind);
    if (btn) {
      btn.addEventListener('click', function () {
        window.orbatHubOpenRecruitmentModal(kind, 0);
      });
    }
  });

  document.querySelectorAll('[data-hub-close]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var dialog = document.getElementById(btn.getAttribute('data-hub-close'));
      if (dialog && typeof dialog.close === 'function') {
        dialog.close();
      }
    });
  });

  if (initialOpen) {
    document.addEventListener('DOMContentLoaded', function () {
      window.orbatHubOpenRecruitmentModal(initialOpen, 0);
    });
  }
})();
</script>
