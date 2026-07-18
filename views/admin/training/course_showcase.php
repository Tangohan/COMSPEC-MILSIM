<?php
$course = $course ?? [];
$tenant = $tenant ?? null;
$id = (int) ($course['id'] ?? 0);
$cycle = $course['showcase_cycle_date'] ?? '';
if (is_string($cycle) && strlen($cycle) >= 10) {
    $cycle = substr($cycle, 0, 10);
}
require base_path('views/admin/training/partials/command_shell_open.php');
?>
                <header class="tc-panel p-6 md:p-8">
                    <p class="text-sm text-slate-500 mb-3">
                        <a href="<?= htmlspecialchars(training_lms_admin_url('courses')) ?>" class="font-semibold text-emerald-700 hover:underline">← Catalogue formations</a>
                    </p>
                    <p class="tc-kicker">Vitrine publique</p>
                    <h1 class="tc-hero-title mb-4">Carte « Nos formations »</h1>
                    <p class="text-slate-600 text-sm max-w-2xl leading-relaxed">
                        Ajustez l’apparence sur le dashboard public
                        <?php if ($tenant): ?>
                        (communauté <strong><?= htmlspecialchars(community_display_name($tenant)) ?></strong>).
                        <?php else: ?>
                        de votre communauté.
                        <?php endif; ?>
                        Visibilité <strong>publiée</strong> requise pour l’affichage catalogue.
                    </p>
                </header>

                <form method="post" action="<?= htmlspecialchars(training_lms_admin_url('courses/' . $id . '/showcase')) ?>" enctype="multipart/form-data" class="space-y-6">
                    <?= \App\Core\Csrf::field() ?>

                    <section class="tc-panel p-6 md:p-8 space-y-6">
                        <div>
                            <h2 class="tc-module-header">Médias</h2>
                            <p class="text-xs text-slate-500">Joignez vos visuels directement depuis votre poste. Formats acceptés : JPG, PNG, WEBP, GIF — 4 Mo maximum.</p>
                        </div>
                        <?php
                        $showcaseMediaFields = [
                            'thumbnail' => [
                                'label' => 'Miniature (carte)',
                                'help' => 'Affichée sur la carte de la formation dans le catalogue public.',
                                'value' => (string) ($course['thumbnail_path'] ?? ''),
                                'ratio' => 'aspect-[4/3]',
                            ],
                            'banner' => [
                                'label' => 'Bannière (modale)',
                                'help' => 'Affichée en grand format quand un stagiaire ouvre la fiche de la formation.',
                                'value' => (string) ($course['banner_path'] ?? ''),
                                'ratio' => 'aspect-[16/6]',
                            ],
                        ];
                        foreach ($showcaseMediaFields as $mediaKey => $mf):
                            $mediaUrl = trim($mf['value']) !== '' ? training_media_url($mf['value']) : null;
                        ?>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4 sm:p-5" data-media-field="<?= htmlspecialchars($mediaKey) ?>">
                            <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700"><?= htmlspecialchars($mf['label']) ?></label>
                                    <p class="text-[11px] text-slate-500 mt-0.5"><?= htmlspecialchars($mf['help']) ?></p>
                                </div>
                                <button type="button" class="tc-btn-primary tc-btn-ghost media-remove-btn <?= $mediaUrl ? '' : 'hidden' ?>" data-media-remove-btn="<?= htmlspecialchars($mediaKey) ?>">Retirer l’image</button>
                            </div>
                            <input type="hidden" name="<?= htmlspecialchars($mediaKey) ?>_remove" value="0" data-media-remove-input="<?= htmlspecialchars($mediaKey) ?>">
                            <div class="grid grid-cols-1 sm:grid-cols-[minmax(0,220px)_1fr] gap-4 items-stretch">
                                <div class="<?= $mf['ratio'] ?> w-full rounded-xl border border-slate-200 bg-white overflow-hidden flex items-center justify-center" data-media-preview-wrap="<?= htmlspecialchars($mediaKey) ?>">
                                    <img src="<?= htmlspecialchars((string) $mediaUrl) ?>" alt="" class="w-full h-full object-cover <?= $mediaUrl ? '' : 'hidden' ?>" data-media-preview-img="<?= htmlspecialchars($mediaKey) ?>">
                                    <span class="text-[11px] text-slate-400 px-3 text-center <?= $mediaUrl ? 'hidden' : '' ?>" data-media-preview-empty="<?= htmlspecialchars($mediaKey) ?>">Aucune image pour l’instant</span>
                                </div>
                                <label class="media-dropzone flex flex-col items-center justify-center text-center gap-1.5 rounded-xl border-2 border-dashed border-slate-300 bg-white px-4 py-8 cursor-pointer transition hover:border-emerald-400 hover:bg-emerald-50/40" data-media-dropzone="<?= htmlspecialchars($mediaKey) ?>">
                                    <svg viewBox="0 0 24 24" fill="none" class="w-6 h-6 text-emerald-600" aria-hidden="true"><path d="M12 16V4m0 0-4 4m4-4 4 4M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    <span class="text-sm font-semibold text-slate-700">Joindre une image</span>
                                    <span class="text-[11px] text-slate-500">Glissez-déposez ici, ou cliquez pour parcourir votre poste</span>
                                    <input type="file" name="<?= htmlspecialchars($mediaKey) ?>_upload" accept="image/jpeg,image/png,image/webp,image/gif" class="sr-only" data-media-input="<?= htmlspecialchars($mediaKey) ?>">
                                </label>
                            </div>
                            <p class="mt-2 text-[11px] text-slate-500 empty:hidden" data-media-filename="<?= htmlspecialchars($mediaKey) ?>"></p>
                        </div>
                        <?php endforeach; ?>
                    </section>

                    <section class="tc-panel p-6 md:p-8 space-y-4">
                        <h2 class="tc-module-header">Textes &amp; détail</h2>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">Accroche courte</label>
                            <input type="text" name="short_description" maxlength="500" value="<?= htmlspecialchars((string) ($course['short_description'] ?? '')) ?>" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">Description (modale)</label>
                            <textarea name="description" rows="6" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-mono"><?= htmlspecialchars((string) ($course['description'] ?? '')) ?></textarea>
                        </div>
                    </section>

                    <section class="tc-panel p-6 md:p-8 space-y-4">
                        <h2 class="tc-module-header">Bandeau carte</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-600 mb-1">Date du cycle</label>
                                <input type="date" name="showcase_cycle_date" value="<?= htmlspecialchars($cycle) ?>" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 mb-1">Lieu / modalité</label>
                                <input type="text" name="showcase_location" value="<?= htmlspecialchars((string) ($course['showcase_location'] ?? '')) ?>" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm" placeholder="Paris / Visio">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-600 mb-1">Badge</label>
                                <select name="showcase_badge" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm">
                                    <?php
                                    $b = (string) ($course['showcase_badge'] ?? 'open');
                                    foreach (['open' => 'Ouvert', 'full' => 'Complet', 'coming_soon' => 'Bientôt', 'closed' => 'Fermé'] as $val => $lab):
                                    ?>
                                    <option value="<?= htmlspecialchars($val) ?>" <?= $b === $val ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 mb-1">Style visuel carte</label>
                                <select name="showcase_card_style" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm">
                                    <?php
                                    $s = (string) ($course['showcase_card_style'] ?? 'default');
                                    foreach (['default' => 'Couleur', 'grayscale' => 'Noir & blanc (hover couleur)'] as $val => $lab):
                                    ?>
                                    <option value="<?= htmlspecialchars($val) ?>" <?= $s === $val ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">Ordre d’affichage (optionnel)</label>
                            <?php
                            $sortVal = $course['showcase_sort_order'] ?? null;
                            $sortOut = ($sortVal === null || $sortVal === '') ? '' : (string) (int) $sortVal;
                            ?>
                            <input type="number" name="showcase_sort_order" min="0" step="1" value="<?= htmlspecialchars($sortOut) ?>" class="w-full max-w-xs border border-slate-200 rounded-xl px-3 py-2.5 text-sm" placeholder="Plus petit = en premier">
                        </div>
                    </section>

                    <div class="flex flex-wrap gap-3">
                        <button type="submit" class="tc-btn-primary">Enregistrer</button>
                        <a href="<?= url('formations/' . htmlspecialchars($course['slug'] ?? '')) ?>" class="tc-btn-primary tc-btn-ghost" target="_blank" rel="noopener">Fiche formation ↗</a>
                    </div>
                </form>
<?php require base_path('views/admin/training/partials/command_shell_close.php'); ?>
<script>
(function() {
  var MAX_BYTES = 4 * 1024 * 1024;
  var ALLOWED = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

  document.querySelectorAll('[data-media-field]').forEach(function(field) {
    var key = field.getAttribute('data-media-field');
    var input = field.querySelector('[data-media-input="' + key + '"]');
    var dropZone = field.querySelector('[data-media-dropzone="' + key + '"]');
    var img = field.querySelector('[data-media-preview-img="' + key + '"]');
    var empty = field.querySelector('[data-media-preview-empty="' + key + '"]');
    var removeBtn = field.querySelector('[data-media-remove-btn="' + key + '"]');
    var removeInput = field.querySelector('[data-media-remove-input="' + key + '"]');
    var filenameEl = field.querySelector('[data-media-filename="' + key + '"]');
    if (!input || !dropZone) return;

    function showPreview(file) {
      if (!img || !empty) return;
      img.src = URL.createObjectURL(file);
      img.classList.remove('hidden');
      empty.classList.add('hidden');
      if (filenameEl) {
        filenameEl.textContent = file.name + ' — ' + Math.max(1, Math.round(file.size / 1024)) + ' Ko';
      }
      if (removeBtn) removeBtn.classList.remove('hidden');
      if (removeInput) removeInput.value = '0';
    }

    function rejectFile(message) {
      window.alert(message);
      input.value = '';
    }

    function handleFile(file) {
      if (!file) return;
      if (ALLOWED.indexOf(file.type) === -1) {
        rejectFile('Cette image n’est pas dans un format pris en charge. Utilisez un JPG, PNG, WEBP ou GIF.');
        return;
      }
      if (file.size > MAX_BYTES) {
        rejectFile('Cette image est trop volumineuse (maximum 4 Mo).');
        return;
      }
      showPreview(file);
    }

    input.addEventListener('change', function() {
      if (input.files && input.files[0]) handleFile(input.files[0]);
    });

    ['dragenter', 'dragover'].forEach(function(evt) {
      dropZone.addEventListener(evt, function(e) {
        e.preventDefault();
        dropZone.classList.add('border-emerald-500', 'bg-emerald-50');
      });
    });
    ['dragleave', 'drop'].forEach(function(evt) {
      dropZone.addEventListener(evt, function(e) {
        e.preventDefault();
        dropZone.classList.remove('border-emerald-500', 'bg-emerald-50');
      });
    });
    dropZone.addEventListener('drop', function(e) {
      if (!e.dataTransfer || !e.dataTransfer.files || !e.dataTransfer.files[0]) return;
      var dt = new DataTransfer();
      dt.items.add(e.dataTransfer.files[0]);
      input.files = dt.files;
      handleFile(e.dataTransfer.files[0]);
    });

    if (removeBtn) {
      removeBtn.addEventListener('click', function() {
        input.value = '';
        if (img) { img.classList.add('hidden'); img.removeAttribute('src'); }
        if (empty) empty.classList.remove('hidden');
        if (filenameEl) filenameEl.textContent = '';
        if (removeInput) removeInput.value = '1';
        removeBtn.classList.add('hidden');
      });
    }
  });
})();
</script>
