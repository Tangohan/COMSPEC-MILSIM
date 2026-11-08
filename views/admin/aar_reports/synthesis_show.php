<?php

declare(strict_types=1);

/**
 * Synthèse enregistrée (/back-office/atak/comptes-rendus/syntheses/{id}).
 *
 * @var array<string, mixed> $aarSynth
 * @var list<array<string, mixed>> $aarSynthReports
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$base = url('back-office/atak/comptes-rendus');
$s = is_array($aarSynth ?? null) ? $aarSynth : [];
$reports = is_array($aarSynthReports ?? null) ? $aarSynthReports : [];
$blocks = ['improvements' => 'À améliorer', 'lessons' => 'Enseignements', 'conclusion' => 'Conclusion'];
$when = strtotime((string) ($s['created_at'] ?? '')) ?: 0;
?>
<div class="bo-aar bo-aar-show">
  <p class="bo-aar-muted">Par <?= $h(trim((string) ($s['author_name'] ?? '')) ?: 'auteur inconnu') ?><?= $when > 0 ? ' · ' . $h(date('d/m/Y', $when)) : '' ?></p>
  <section class="ath-panel bo-aar-step">
    <div class="bo-aar-tabs" role="tablist" data-synth-tabs>
      <?php $first = true; foreach ($blocks as $key => $label): ?>
        <button type="button" class="bo-aar-tabs__tab<?= $first ? ' is-active' : '' ?>" data-synth-tab="<?= $h($key) ?>"><?= $h($label) ?></button>
      <?php $first = false; endforeach; ?>
      <button type="button" class="bo-aar-tabs__tab" data-synth-tab="sources">Comptes rendus <span class="bo-aar-tabs__n"><?= count($reports) ?></span></button>
    </div>
    <?php $first = true; foreach ($blocks as $key => $label): $text = trim((string) ($s[$key] ?? '')); ?>
      <div data-synth-pane="<?= $h($key) ?>"<?= $first ? '' : ' hidden' ?>>
        <p class="bo-aar-text"><?= $text !== '' ? nl2br($h($text)) : '<span class="bo-aar-muted">Rien de noté.</span>' ?></p>
      </div>
    <?php $first = false; endforeach; ?>
    <div data-synth-pane="sources" hidden>
      <ul class="bo-aar-points">
        <?php foreach ($reports as $r): ?>
          <li><a href="<?= $h($base . '/' . (int) $r['id']) ?>"><?= $h((string) ($r['reference_code'] ?? '')) ?></a> · <?= $h((string) (($r['operation_label'] ?? '') !== '' ? $r['operation_label'] : ($r['title'] ?? ''))) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
  <?php if (!empty($aarCanConclude)): ?>
    <form method="post" action="<?= $h($base . '/syntheses/' . (int) ($s['id'] ?? 0) . '/supprimer') ?>" data-aar-confirm="Supprimer cette synthèse ?">
      <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken ?? '') ?>">
      <button type="submit" class="ath-btn bo-aar-danger">Supprimer la synthèse</button>
    </form>
  <?php endif; ?>
</div>
<script src="<?= $h(asset_url('assets/js/back-office-aar.js')) ?>" defer></script>
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
