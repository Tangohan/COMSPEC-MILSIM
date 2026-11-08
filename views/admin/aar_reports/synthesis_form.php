<?php

declare(strict_types=1);

/**
 * Nouvelle synthèse (/back-office/atak/comptes-rendus/syntheses/nouvelle?ids[]=…) : textes repris des comptes rendus
 * choisis, à relire et corriger avant enregistrement.
 *
 * @var list<array<string, mixed>> $aarSynthReports
 * @var array{improvements: string, lessons: string, conclusion: string} $aarSynthDraft
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$base = url('back-office/atak/comptes-rendus');
$reports = is_array($aarSynthReports ?? null) ? $aarSynthReports : [];
$draft = is_array($aarSynthDraft ?? null) ? $aarSynthDraft : ['improvements' => '', 'lessons' => '', 'conclusion' => ''];
$defaultTitle = 'Synthèse de ' . count($reports) . ' comptes rendus';
?>
<form method="post" action="<?= $h($base . '/syntheses') ?>" class="bo-aar bo-aar-form">
  <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken ?? '') ?>">
  <?php foreach ($reports as $r): ?><input type="hidden" name="report_ids[]" value="<?= (int) $r['id'] ?>"><?php endforeach; ?>
  <section class="ath-panel bo-aar-step">
    <label class="bo-aar-field"><span>Titre de la synthèse <em>obligatoire</em></span>
      <input name="title" required maxlength="200" value="<?= $h($defaultTitle) ?>"></label>
    <p class="bo-aar-sublabel">Comptes rendus condensés</p>
    <ul class="bo-aar-points">
      <?php foreach ($reports as $r): ?><li><?= $h((string) ($r['reference_code'] ?? '')) ?> · <?= $h((string) (($r['operation_label'] ?? '') !== '' ? $r['operation_label'] : ($r['title'] ?? ''))) ?></li><?php endforeach; ?>
    </ul>
  </section>
  <section class="ath-panel bo-aar-step">
    <div class="bo-aar-tabs" role="tablist" data-synth-tabs>
      <button type="button" class="bo-aar-tabs__tab is-active" data-synth-tab="improvements">Améliorations</button>
      <button type="button" class="bo-aar-tabs__tab" data-synth-tab="lessons">Enseignements</button>
      <button type="button" class="bo-aar-tabs__tab" data-synth-tab="conclusion">Conclusion</button>
    </div>
    <label class="bo-aar-field" data-synth-pane="improvements"><span>Ce qu’il faut améliorer</span>
      <textarea name="improvements" rows="12" maxlength="20000"><?= $h($draft['improvements']) ?></textarea></label>
    <label class="bo-aar-field" data-synth-pane="lessons" hidden><span>Ce qu’on en retient</span>
      <textarea name="lessons" rows="12" maxlength="20000"><?= $h($draft['lessons']) ?></textarea></label>
    <label class="bo-aar-field" data-synth-pane="conclusion" hidden><span>Conclusion</span>
      <textarea name="conclusion" rows="12" maxlength="20000"><?= $h($draft['conclusion']) ?></textarea></label>
  </section>
  <div><a class="ath-btn" href="<?= $h($base . '?vue=syntheses') ?>">Annuler</a> <button type="submit" class="ath-btn ath-btn--solid">Enregistrer la synthèse</button></div>
</form>
<script>
(function () {
  var tabs = document.querySelectorAll('[data-synth-tab]');
  tabs.forEach(function (t) {
    t.addEventListener('click', function () {
      tabs.forEach(function (o) { o.classList.toggle('is-active', o === t); });
      document.querySelectorAll('[data-synth-pane]').forEach(function (p) { p.hidden = p.getAttribute('data-synth-pane') !== t.getAttribute('data-synth-tab'); });
    });
  });
})();
</script>
