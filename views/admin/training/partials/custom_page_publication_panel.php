<div class="cp-editor-card">
  <div class="cp-editor-card__head"><p class="cp-editor-card__title">Publication</p></div>
  <div class="space-y-3 text-xs">
    <label class="block">Statut
      <select name="status" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-2">
        <?php foreach (['draft'=>'Brouillon','review'=>'En revue','scheduled'=>'Programmé','published'=>'Publié','archived'=>'Archivé'] as $k=>$l): ?>
          <option value="<?= $k ?>" <?= ((string)($customPage['status'] ?? 'draft')===$k?'selected':'') ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="block">Publication planifiée
      <input type="datetime-local" name="scheduled_publish_at" value="<?= htmlspecialchars(str_replace(' ', 'T', substr((string)($customPage['scheduled_publish_at'] ?? ''),0,16))) ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-2">
    </label>
    <label class="inline-flex items-center gap-2"><input type="checkbox" name="publish_now" value="1">Publier maintenant</label>
  </div>
</div>
