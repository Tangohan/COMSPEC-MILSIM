<?php
declare(strict_types=1);
/** @var array<string, mixed> $deck */
$deck = $deck ?? [];
$modals = isset($deck['modals']) && is_array($deck['modals']) ? $deck['modals'] : [];
if ($modals === []) {
    echo '<p class="text-slate-500">Aucune modale définie.</p>';

    return;
}
$mid = 'lms-mod-' . bin2hex(random_bytes(4));
?>
<div class="space-y-4" id="<?= htmlspecialchars($mid, ENT_QUOTES, 'UTF-8') ?>">
    <p class="text-sm text-slate-600">Ouvrez chaque fiche pour en lire le contenu.</p>
    <div class="flex flex-wrap gap-2">
        <?php foreach ($modals as $i => $m): ?>
            <?php if (!is_array($m)) {
                continue;
            } ?>
            <button type="button"
                    class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-sm font-bold text-slate-800 hover:border-emerald-300 hover:bg-emerald-50"
                    data-lms-modal-open="<?= (int) $i ?>">
                <?= htmlspecialchars((string) ($m['title'] ?? 'Modale ' . ((int) $i + 1))) ?>
            </button>
        <?php endforeach; ?>
    </div>
</div>

<?php foreach ($modals as $i => $m): ?>
    <?php if (!is_array($m)) {
        continue;
    } ?>
    <dialog class="lms-modal-dialog max-w-lg w-[calc(100%-2rem)] rounded-2xl border border-slate-200 p-0 shadow-2xl backdrop:bg-slate-900/40" data-lms-modal="<?= (int) $i ?>">
        <form method="dialog" class="p-0">
            <div class="border-b border-slate-100 px-5 py-4 flex justify-between items-center gap-4 bg-slate-50/90">
                <h3 class="text-lg font-black text-slate-900"><?= htmlspecialchars((string) ($m['title'] ?? '')) ?></h3>
                <button type="submit" value="cancel" class="text-sm font-bold text-slate-500 hover:text-slate-800">Fermer</button>
            </div>
            <div class="p-5 space-y-4 max-h-[70vh] overflow-y-auto">
                <?php if (!empty($m['imageUrl'])): ?>
                <img src="<?= htmlspecialchars((string) $m['imageUrl']) ?>" alt="" class="w-full rounded-xl object-cover max-h-56 bg-slate-100" loading="lazy">
                <?php endif; ?>
                <div class="prose prose-slate prose-sm max-w-none text-slate-700">
                    <?= function_exists('training_canvas_sanitize_html') ? training_canvas_sanitize_html((string) ($m['body'] ?? '')) : nl2br(htmlspecialchars((string) ($m['body'] ?? ''))) ?>
                </div>
            </div>
        </form>
    </dialog>
<?php endforeach; ?>

<script>
(function () {
  var root = document.getElementById(<?= json_encode($mid, JSON_UNESCAPED_UNICODE) ?>);
  if (!root) return;
  var dialogs = document.querySelectorAll('dialog[data-lms-modal]');
  var total = dialogs.length;
  if (total < 1) return;
  var cfg = window.__LMS_LESSON_PROGRESS__;
  var MIN_OPEN =
    cfg && cfg.strict && typeof cfg.strict.modalMinOpenMs === 'number' && cfg.strict.modalMinOpenMs > 0
      ? cfg.strict.modalMinOpenMs
      : 2600;
  var openAt = Object.create(null);
  var validClose = new Set();

  function checkDone() {
    if (validClose.size < total) return;
    for (var i = 0; i < total; i++) {
      var dlg = dialogs[i];
      var mid = dlg && dlg.getAttribute('data-lms-modal');
      if (mid == null || !validClose.has(String(mid))) return;
    }
    if (window.LmsLessonProgress && typeof window.LmsLessonProgress.signalComplete === 'function') {
      window.LmsLessonProgress.signalComplete();
    }
  }

  root.querySelectorAll('[data-lms-modal-open]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = btn.getAttribute('data-lms-modal-open');
      var dlg = document.querySelector('dialog[data-lms-modal="' + id + '"]');
      if (dlg && typeof dlg.showModal === 'function') {
        dlg.showModal();
        openAt[String(id)] = Date.now();
      }
    });
  });

  dialogs.forEach(function (dlg) {
    dlg.addEventListener('close', function () {
      var id = dlg.getAttribute('data-lms-modal');
      if (id == null) return;
      var key = String(id);
      var t0 = openAt[key];
      if (t0 != null && Date.now() - t0 >= MIN_OPEN) {
        validClose.add(key);
      }
      delete openAt[key];
      checkDone();
    });
  });
})();
</script>
