<?php
declare(strict_types=1);
/** @var array<string, mixed> $quiz */
$quiz = $quiz ?? [];
$questions = isset($quiz['questions']) && is_array($quiz['questions']) ? $quiz['questions'] : [];
$passing = (float) ($quiz['passingPercent'] ?? 70);
if ($questions === []) {
    echo '<p class="text-slate-500">Aucune question définie.</p>';

    return;
}
$qid = 'lms-quiz-' . bin2hex(random_bytes(4));
?>
<div class="lms-quiz space-y-8" id="<?= htmlspecialchars($qid, ENT_QUOTES, 'UTF-8') ?>" data-passing="<?= htmlspecialchars((string) $passing, ENT_QUOTES, 'UTF-8') ?>">
    <?php foreach ($questions as $qi => $q): ?>
        <?php
        if (!is_array($q)) {
            continue;
        }
        $prompt = (string) ($q['prompt'] ?? '');
        $choices = isset($q['choices']) && is_array($q['choices']) ? $q['choices'] : [];
        $correct = isset($q['correct']) && is_array($q['correct']) ? $q['correct'] : [];
        $fieldBase = 'q_' . (int) $qi;
        $multi = count($correct) > 1;
        ?>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm" data-quiz-q data-correct="<?= htmlspecialchars(json_encode($correct, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">
            <p class="text-xs font-black uppercase tracking-wider text-emerald-700 mb-2">Question <?= (int) ($qi + 1) ?></p>
            <p class="text-base font-bold text-slate-900 mb-4"><?= htmlspecialchars($prompt) ?></p>
            <div class="space-y-2">
                <?php foreach ($choices as $ch): ?>
                    <?php if (!is_array($ch)) {
                        continue;
                    } ?>
                    <label class="flex items-start gap-3 cursor-pointer rounded-lg border border-slate-100 bg-slate-50/80 px-3 py-2 hover:border-emerald-200">
                        <?php if ($multi): ?>
                        <input type="checkbox" name="<?= htmlspecialchars($fieldBase) ?>[]" value="<?= htmlspecialchars((string) ($ch['id'] ?? '')) ?>" class="mt-1">
                        <?php else: ?>
                        <input type="radio" name="<?= htmlspecialchars($fieldBase) ?>" value="<?= htmlspecialchars((string) ($ch['id'] ?? '')) ?>" class="mt-1">
                        <?php endif; ?>
                        <span class="text-sm text-slate-700"><?= htmlspecialchars((string) ($ch['label'] ?? '')) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="flex flex-wrap items-center gap-4">
        <button type="button" class="px-5 py-2.5 bg-emerald-600 text-white text-sm font-bold rounded-xl hover:bg-emerald-700" data-quiz-submit>
            Valider le quiz
        </button>
        <p class="text-sm text-slate-600 hidden" data-quiz-result></p>
    </div>
</div>
<script>
(function () {
  var root = document.getElementById(<?= json_encode($qid, JSON_UNESCAPED_UNICODE) ?>);
  if (!root) return;
  var passing = parseFloat(root.getAttribute('data-passing') || '70') || 70;
  root.querySelector('[data-quiz-submit]')?.addEventListener('click', function () {
    var blocks = root.querySelectorAll('[data-quiz-q]');
    var ok = 0, total = 0;
    blocks.forEach(function (block) {
      total++;
      var expected = [];
      try { expected = JSON.parse(block.getAttribute('data-correct') || '[]'); } catch (e) { return; }
      var picked = [];
      block.querySelectorAll('input:checked').forEach(function (inp) { picked.push(inp.value); });
      picked.sort();
      var exp = expected.slice().sort();
      var same = picked.length === exp.length && picked.every(function (v, i) { return v === exp[i]; });
      if (same) ok++;
    });
    var pct = total ? (100 * ok / total) : 0;
    var res = root.querySelector('[data-quiz-result]');
    if (res) {
      res.classList.remove('hidden');
      res.textContent = 'Score : ' + Math.round(pct) + ' % (seuil : ' + passing + ' %).';
      res.classList.toggle('text-emerald-700', pct >= passing);
      res.classList.toggle('text-rose-600', pct < passing);
    }
    if (pct >= passing && window.LmsLessonProgress && typeof window.LmsLessonProgress.signalComplete === 'function') {
      window.LmsLessonProgress.signalComplete();
    }
  });
})();
</script>
