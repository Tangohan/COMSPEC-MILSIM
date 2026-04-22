<div class="cp-editor-card">
  <div class="cp-editor-card__head"><p class="cp-editor-card__title">Visibilité</p></div>
  <label class="block text-xs">Niveau
    <select name="visibility_level" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-2">
      <?php foreach (['staff_private'=>'Privé staff','tenant'=>'Membres du tenant','role_based'=>'Rôles ciblés','internal_link'=>'Lien interne'] as $k=>$l): ?>
      <option value="<?= $k ?>" <?= ((string)($customPage['visibility_level'] ?? 'tenant')===$k?'selected':'') ?>><?= $l ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="block text-xs mt-2">Rôles autorisés (JSON)
    <textarea name="allowed_roles_json" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-2 font-mono"><?= htmlspecialchars((string)($customPage['allowed_roles_json'] ?? '')) ?></textarea>
  </label>
</div>
