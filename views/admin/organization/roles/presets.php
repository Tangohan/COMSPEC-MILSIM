<?php
declare(strict_types=1);

/**
 * Profils de permissions — charte ATHENA.
 *
 * L’en-tête de page est rendu par la coque back-office. Trois étapes : choix du rôle,
 * choix du profil, récapitulatif des ajouts et retraits avant confirmation.
 *
 * @var list<array{id: string, label: string, description: string}> $presetMeta
 * @var list<array<string, mixed>> $customPresetKits
 * @var list<array<string, mixed>> $allPermissions
 * @var list<array<string, mixed>> $roles
 * @var string $presetsPreviewUrl
 */

$presetMeta = is_array($presetMeta ?? null) ? $presetMeta : [];
$customPresetKits = is_array($customPresetKits ?? null) ? $customPresetKits : [];
$allPermissions = is_array($allPermissions ?? null) ? $allPermissions : [];
$roles = is_array($roles ?? null) ? $roles : [];
$presetsPreviewUrl = isset($presetsPreviewUrl) ? (string) $presetsPreviewUrl : url('back-office/roles/presets/preview');

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$err = \App\Core\Session::getFlash('error');
$ok = \App\Core\Session::getFlash('success');

$maxKits = 24;
$kitCount = count($customPresetKits);
?>
<?php if ($err): ?>
<p class="ath-flash ath-flash--err" role="alert"><?= $h((string) $err) ?></p>
<?php endif; ?>
<?php if ($ok): ?>
<p class="ath-flash ath-flash--ok" role="status"><?= $h((string) $ok) ?></p>
<?php endif; ?>

<div class="ath-note" style="background:#fdf3e2;border-color:#f2ddb4;">
    <p class="ath-note__title" style="color:#8a5a06;">Toujours exclus des profils automatiques</p>
    <p class="ath-note__text" style="color:#8a5a06;">
        Aucun profil ci-dessous n’accorde les habilitations réservées à l’administration de la plateforme
        pour l’ensemble des communautés, ni la modération forum au niveau global. Cette exclusion est appliquée
        par le service lui-même, pas par cet écran : elle ne peut pas être contournée depuis ici.
    </p>
</div>

<?php if ($roles === []): ?>
<div class="ath-card" style="padding:20px 22px;">
    <p class="ath-panel__lead" style="margin:0;">
        Aucun rôle communauté ou opérationnel n’est disponible. Créez d’abord des rôles depuis
        <a href="<?= $h(url('back-office/roles')) ?>">la liste des rôles</a>.
    </p>
</div>
<?php else: ?>

<form method="post" action="<?= $h(url('back-office/roles/presets/apply')) ?>" id="preset-apply-form">
    <?= \App\Core\Csrf::field() ?>

    <div class="ath-panel ath-rise">
        <span class="ath-step">1</span>
        <h2 class="ath-panel__title">Rôle à configurer</h2>
        <p class="ath-panel__lead">Rôles de votre communauté ou opérationnels. Les rôles verrouillés ne sont pas modifiables ici.</p>
        <div class="ath-form__grid" style="margin-top:13px;">
            <label class="ath-field">
                <span class="ath-field__label">Rôle</span>
                <select name="role_id" id="role_id" required class="ath-field__select">
                    <option value="">— Choisir un rôle —</option>
                    <?php foreach ($roles as $r): ?>
                        <?php
                        $rid = (int) ($r['id'] ?? 0);
                        $layer = (string) ($r['role_layer'] ?? '');
                        $layerFr = $layer === 'intra' ? 'Opérationnel' : 'Communauté';
                        $locked = !empty($r['is_locked']);
                        ?>
                        <option value="<?= $rid ?>"<?= $locked ? ' disabled' : '' ?>><?= $h((string) ($r['name'] ?? '')) ?> (<?= $h($layerFr) ?>)<?= $locked ? ' — verrouillé' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
    </div>

    <div class="ath-panel ath-rise">
        <span class="ath-step">2</span>
        <h2 class="ath-panel__title">Profil à appliquer</h2>
        <p class="ath-panel__lead">Chaque profil <strong>remplace intégralement</strong> les habilitations du rôle : il n’y a pas de fusion avec l’existant.</p>
        <div class="ath-choice-grid" style="margin-top:13px;">
            <?php foreach ($presetMeta as $meta): ?>
                <?php
                $pid = (string) ($meta['id'] ?? '');
                if ($pid === '') {
                    continue;
                }
                ?>
                <label class="ath-choice">
                    <input type="radio" name="preset_id" value="<?= $h($pid) ?>" required>
                    <span class="ath-choice__body">
                        <span class="ath-choice__name"><?= $h((string) ($meta['label'] ?? $pid)) ?></span>
                        <span class="ath-choice__desc"><?= $h((string) ($meta['description'] ?? '')) ?></span>
                    </span>
                </label>
            <?php endforeach; ?>
            <?php foreach ($customPresetKits as $kit): ?>
                <?php
                $kid = (string) ($kit['id'] ?? '');
                if ($kid === '') {
                    continue;
                }
                $kDesc = trim((string) ($kit['description'] ?? ''));
                $kCount = is_array($kit['permission_ids'] ?? null) ? count($kit['permission_ids']) : 0;
                ?>
                <label class="ath-choice">
                    <input type="radio" name="preset_id" value="<?= $h('custom:' . $kid) ?>" required>
                    <span class="ath-choice__body">
                        <span class="ath-tag ath-tag--info" style="margin-bottom:5px;">Kit perso</span>
                        <span class="ath-choice__name"><?= $h((string) ($kit['label'] ?? $kid)) ?></span>
                        <span class="ath-choice__desc"><?= $h($kDesc !== '' ? $kDesc : 'Kit personnalisé de permissions.') ?></span>
                        <span class="ath-choice__meta"><?= (int) $kCount ?> droit<?= $kCount > 1 ? 's' : '' ?> inclus</span>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
    </div>

    <section class="ath-panel ath-panel--dashed ath-rise" aria-labelledby="preset-preview-heading">
        <div class="ath-panel__head">
            <div style="min-width:0;">
                <span class="ath-step ath-step--accent">3</span>
                <h2 class="ath-panel__title" id="preset-preview-heading">Récapitulatif avant application</h2>
                <p class="ath-panel__lead">Calcule les <strong>ajouts</strong> et les <strong>retraits</strong> par rapport à l’état actuel du rôle. La confirmation vient ensuite.</p>
            </div>
            <button type="button" id="btn-load-preview" class="ath-btn ath-btn--solid">Afficher le récapitulatif</button>
        </div>
        <p id="preview-status" class="ath-status ath-status--info" role="status" hidden></p>
        <div id="preview-panel" style="margin-top:15px;" hidden></div>
    </section>

    <div class="ath-warn">
        <p class="ath-warn__title">Effet immédiat</p>
        <p class="ath-warn__text">Après confirmation, les membres portant ce rôle disposent aussitôt du nouveau jeu de droits. Vérifiez la fiche du rôle après application.</p>
    </div>

    <div class="ath-form__actions" style="border-top:0;padding-top:0;">
        <button type="button" id="btn-open-confirm" class="ath-btn ath-btn--solid" disabled>Continuer vers la confirmation…</button>
        <a href="<?= $h(url('back-office/roles')) ?>" class="ath-btn">Annuler</a>
    </div>

    <dialog id="preset-confirm-dialog" class="ath-dialog">
        <div class="ath-dialog__head">
            <h2 class="ath-dialog__title">Confirmer l’application du profil</h2>
            <p class="ath-dialog__sub">Cette action remplace toutes les habilitations du rôle sélectionné.</p>
        </div>
        <div id="dialog-summary-body" class="ath-dialog__body"></div>
        <div class="ath-dialog__foot">
            <button type="button" id="dialog-cancel" class="ath-btn">Retour</button>
            <button type="submit" id="dialog-confirm-submit" class="ath-btn ath-btn--accent">Confirmer et appliquer</button>
        </div>
    </dialog>
</form>

<h2 class="ath-section-title">Kits personnalisés</h2>

<form method="post" action="<?= $h(url('back-office/roles/presets/kits/save')) ?>" class="ath-form ath-rise">
    <div class="ath-form__head">
        <span class="ath-form__title">Nouveau kit</span>
        <span class="ath-form__hint"><?= $kitCount ?> / <?= $maxKits ?> kits · réutilisables comme un profil à l’étape 2</span>
    </div>
    <?= \App\Core\Csrf::field() ?>
    <div class="ath-form__grid">
        <label class="ath-field">
            <span class="ath-field__label">Nom du kit</span>
            <input type="text" name="kit_label" maxlength="90" required class="ath-field__input" placeholder="Cellule OPS">
        </label>
        <label class="ath-field">
            <span class="ath-field__label">Description</span>
            <input type="text" name="kit_description" maxlength="180" class="ath-field__input" placeholder="Courte phrase explicative">
        </label>
    </div>
    <details class="ath-disclosure" style="margin-top:14px;">
        <summary>
            <span>Sélectionner les droits <span class="ath-disclosure__count">(<?= count($allPermissions) ?> disponibles)</span></span>
            <span aria-hidden="true">▼</span>
        </summary>
        <div class="ath-picklist" style="padding:9px 12px 11px;">
            <?php foreach ($allPermissions as $perm): ?>
            <label class="ath-picklist__item">
                <input type="checkbox" name="kit_permission_ids[]" value="<?= (int) ($perm['id'] ?? 0) ?>">
                <span style="min-width:0;">
                    <span class="ath-picklist__name"><?= $h((string) ($perm['name'] ?? '')) ?></span>
                    <span class="ath-picklist__ref"><?= $h((string) ($perm['module'] ?? '')) ?> · <?= $h((string) ($perm['slug'] ?? '')) ?></span>
                </span>
            </label>
            <?php endforeach; ?>
        </div>
    </details>
    <div class="ath-form__actions">
        <button type="submit" class="ath-btn ath-btn--solid"<?= $kitCount >= $maxKits ? ' disabled' : '' ?>>Enregistrer le kit</button>
        <?php if ($kitCount >= $maxKits): ?>
        <span class="ath-field__help" style="align-self:center;">Limite de <?= $maxKits ?> kits atteinte : supprimez-en un pour en créer un autre.</span>
        <?php endif; ?>
    </div>
</form>

<?php if ($customPresetKits !== []): ?>
<?php
$csrf = \App\Core\Csrf::token();
$deleteUrl = url('back-office/roles/presets/kits/delete');

$athTableTitle = 'Kits enregistrés';
$athTableCount = $kitCount;
$athTableCols = ['KIT', 'DESCRIPTION', 'DROITS INCLUS|r'];
$athTableRows = [];
$athTableRowActions = [];
foreach ($customPresetKits as $kit) {
    $kid = (string) ($kit['id'] ?? '');
    if ($kid === '') {
        continue;
    }
    $kDesc = trim((string) ($kit['description'] ?? ''));
    $athTableRows[] = [
        (string) ($kit['label'] ?? $kid),
        $kDesc !== '' ? $kDesc : '—',
        (string) (is_array($kit['permission_ids'] ?? null) ? count($kit['permission_ids']) : 0),
    ];
    // Balisage d’action construit ici, échappements compris (cf. contrat de ath_table.php).
    $athTableRowActions[] = '<form method="post" action="' . $h($deleteUrl) . '"'
        . ' onsubmit="return confirm(\'Supprimer ce kit personnalisé ? Les rôles déjà configurés avec ce kit ne changent pas.\');">'
        . '<input type="hidden" name="_csrf_token" value="' . $h($csrf) . '">'
        . '<input type="hidden" name="kit_id" value="' . $h($kid) . '">'
        . '<button type="submit" class="ath-row-action ath-row-action--danger">Supprimer</button>'
        . '</form>';
}
$athTableActionsLabel = 'SUPPRESSION';
$athTableFilters = [];
$athTableMinWidth = '880px';
$athTableShowCheckbox = false;
$athTableExportUrl = null;
$athTablePager = null;
$athTableRowHrefs = null;
$athTableFoot = 'Supprimer un kit ne retire aucun droit : les rôles déjà configurés conservent leurs habilitations.';
require base_path('views/partials/ath_table.php');
?>
<?php endif; ?>

<script>
/*
 * Assistant en trois temps : le récapitulatif est calculé côté serveur puis rendu ici.
 * Le balisage produit reste sur la charte ATHENA (classes ath-*), sans quoi le panneau
 * d'aperçu détonnerait avec le reste de la page.
 */
(function () {
  var previewUrl = <?= json_encode($presetsPreviewUrl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var form = document.getElementById('preset-apply-form');
  var roleEl = document.getElementById('role_id');
  var btnPreview = document.getElementById('btn-load-preview');
  var btnConfirm = document.getElementById('btn-open-confirm');
  var panel = document.getElementById('preview-panel');
  var statusEl = document.getElementById('preview-status');
  var dialog = document.getElementById('preset-confirm-dialog');
  var dialogBody = document.getElementById('dialog-summary-body');
  var dialogCancel = document.getElementById('dialog-cancel');
  if (!form || !roleEl || !btnPreview || !btnConfirm || !panel || !statusEl) return;

  var lastPreview = null;

  var escapeHtml = function (s) {
    if (!s) return '';
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  };

  var setStatus = function (tone, text) {
    statusEl.className = 'ath-status ath-status--' + tone;
    statusEl.textContent = text;
    statusEl.hidden = false;
  };

  var getPresetId = function () {
    var r = form.querySelector('input[name="preset_id"]:checked');
    return r ? r.value : '';
  };

  var renderListGrouped = function (byModule, moduleLabels) {
    var keys = Object.keys(byModule || {}).sort();
    if (!keys.length) {
      return '<p class="ath-panel__lead" style="margin:0;font-style:italic;">Aucune entrée dans cette liste.</p>';
    }
    var html = '';
    keys.forEach(function (mod) {
      var label = (moduleLabels && (moduleLabels[mod] || moduleLabels['autre'])) || mod;
      var items = byModule[mod];
      html += '<details class="ath-disclosure">';
      html += '<summary><span>' + escapeHtml(label) + ' <span class="ath-disclosure__count">(' + items.length + ')</span></span><span aria-hidden="true">▼</span></summary>';
      html += '<ul class="ath-disclosure__list">';
      items.forEach(function (it) {
        html += '<li><span class="ath-picklist__name">' + escapeHtml(it.name) + '</span>';
        if (it.slug) {
          html += '<code class="ath-disclosure__ref">' + escapeHtml(it.slug) + '</code>';
        }
        html += '</li>';
      });
      html += '</ul></details>';
    });
    return html;
  };

  var invalidatePreview = function () {
    lastPreview = null;
    panel.hidden = true;
    panel.innerHTML = '';
    statusEl.hidden = true;
    btnConfirm.disabled = true;
  };

  roleEl.addEventListener('change', invalidatePreview);
  form.querySelectorAll('input[name="preset_id"]').forEach(function (r) {
    r.addEventListener('change', invalidatePreview);
  });

  btnPreview.addEventListener('click', function () {
    var rid = parseInt(roleEl.value, 10);
    var pid = getPresetId();
    if (!rid || !pid) {
      setStatus('warn', 'Choisissez d’abord un rôle et un profil.');
      return;
    }
    setStatus('info', 'Calcul du récapitulatif…');
    btnPreview.disabled = true;

    var u = previewUrl + (previewUrl.indexOf('?') >= 0 ? '&' : '?')
      + 'role_id=' + encodeURIComponent(rid) + '&preset_id=' + encodeURIComponent(pid);

    fetch(u, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (res) {
        return res.text().then(function (text) {
          try {
            return JSON.parse(text);
          } catch (e) {
            return { ok: false, error: 'Réponse inattendue du serveur.' };
          }
        });
      })
      .then(function (j) {
        btnPreview.disabled = false;
        if (!j || !j.ok) {
          setStatus('err', (j && j.error) || 'Impossible de charger le récapitulatif.');
          panel.hidden = true;
          btnConfirm.disabled = true;
          return;
        }
        lastPreview = { roleId: rid, presetId: pid, payload: j };
        setStatus('ok', 'Récapitulatif à jour pour « ' + j.role_name + ' » et le profil « ' + j.preset_label + ' ».');

        var d = j.diff;
        var ml = j.module_labels || {};

        var html = '<div class="ath-stat-grid">';
        html += '<div class="ath-stat"><p class="ath-stat__value">' + d.current_total + '</p><p class="ath-stat__label">Avant</p></div>';
        html += '<div class="ath-stat ath-stat--add"><p class="ath-stat__value">' + d.added_count + '</p><p class="ath-stat__label">Ajouts</p></div>';
        html += '<div class="ath-stat ath-stat--remove"><p class="ath-stat__value">' + d.removed_count + '</p><p class="ath-stat__label">Retraits</p></div>';
        html += '<div class="ath-stat"><p class="ath-stat__value">' + d.preset_total + '</p><p class="ath-stat__label">Après</p></div>';
        html += '</div>';

        if (j.preset_description) {
          html += '<p class="ath-panel__lead" style="margin-top:13px;"><strong>Contenu du profil :</strong> ' + escapeHtml(j.preset_description) + '</p>';
        }

        html += '<div style="display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));margin-top:15px;">';
        html += '<div style="min-width:0;"><p class="ath-field__label">Habilitations ajoutées</p><div style="margin-top:7px;">' + renderListGrouped(d.added_by_module, ml) + '</div></div>';
        html += '<div style="min-width:0;"><p class="ath-field__label">Habilitations retirées</p><div style="margin-top:7px;">' + renderListGrouped(d.removed_by_module, ml) + '</div></div>';
        html += '</div>';

        if (d.unchanged_count > 0) {
          html += '<p class="ath-panel__lead" style="margin-top:13px;">' + d.unchanged_count + ' habilitation(s) déjà présente(s), conservée(s) sans changement.</p>';
        }

        panel.innerHTML = html;
        panel.hidden = false;
        btnConfirm.disabled = false;
      })
      .catch(function () {
        btnPreview.disabled = false;
        setStatus('err', 'Erreur réseau. Réessayez.');
        btnConfirm.disabled = true;
      });
  });

  btnConfirm.addEventListener('click', function () {
    var rid = parseInt(roleEl.value, 10);
    var pid = getPresetId();
    if (!lastPreview || lastPreview.roleId !== rid || lastPreview.presetId !== pid) {
      setStatus('warn', 'Le rôle ou le profil a changé : affichez à nouveau le récapitulatif.');
      return;
    }
    var j = lastPreview.payload;
    var d = j.diff;
    var html = '';
    html += '<p><strong>Rôle :</strong> ' + escapeHtml(j.role_name) + '</p>';
    html += '<p><strong>Profil :</strong> ' + escapeHtml(j.preset_label) + '</p>';
    html += '<ul>';
    html += '<li><strong>' + d.added_count + '</strong> habilitation(s) ajoutée(s)</li>';
    html += '<li><strong>' + d.removed_count + '</strong> habilitation(s) retirée(s)</li>';
    html += '<li><strong>' + d.unchanged_count + '</strong> inchangée(s)</li>';
    html += '<li>Total après application : <strong>' + d.preset_total + '</strong></li>';
    html += '</ul>';
    html += '<p style="margin-top:11px;color:#6d7a80;">En confirmant, vous remplacez l’ensemble des droits actuels de ce rôle.</p>';
    dialogBody.innerHTML = html;
    if (typeof dialog.showModal === 'function') {
      dialog.showModal();
    } else {
      form.submit();
    }
  });

  if (dialogCancel) {
    dialogCancel.addEventListener('click', function () {
      dialog.close();
    });
  }
})();
</script>
<?php endif; ?>
