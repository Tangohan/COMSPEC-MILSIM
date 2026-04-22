<div class="cp-editor-card">
  <div class="cp-editor-card__head"><p class="cp-editor-card__title">Thème</p></div>
  <label class="block text-xs">Thème visuel
    <select name="theme_id" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-2">
      <option value="">Aucun</option>
      <?php foreach (($customPageThemes ?? []) as $th): ?>
      <option value="<?= (int)$th['id'] ?>" <?= ((int)($customPage['theme_id'] ?? 0)===(int)$th['id']?'selected':'') ?>><?= htmlspecialchars((string)$th['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <div class="grid grid-cols-2 gap-2 mt-2 text-xs">
    <label>Accent<input type="color" name="accent_color" value="<?= htmlspecialchars((string)($customPage['accent_color'] ?? '#0f766e')) ?>" class="w-full h-9 mt-1"></label>
    <label>Icône<input type="text" name="icon" value="<?= htmlspecialchars((string)($customPage['icon'] ?? '')) ?>" class="w-full rounded border px-2 py-2 mt-1" placeholder="book"></label>
  </div>
</div>
