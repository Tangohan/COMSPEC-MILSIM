<?php

declare(strict_types=1);

/**
 * Annexes du compte rendu : BDA et comptes rendus du terrain à joindre, textes rédigés.
 *
 * @var list<array<string, mixed>> $aarAnnexSources rapports du terrain de la communauté
 * @var list<array<string, mixed>> $aarAnnexes      annexes déjà enregistrées (édition)
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$sources = is_array($aarAnnexSources ?? null) ? $aarAnnexSources : [];
$saved = is_array($aarAnnexes ?? null) ? $aarAnnexes : [];
$checked = [];
$notes = [];
foreach ($saved as $a) {
    if (($a['kind'] ?? '') === 'note') {
        $notes[] = $a;
    } else {
        $checked[(string) $a['kind'] . ':' . (int) $a['source_id']] = true;
    }
}
$bda = array_values(array_filter($sources, static fn (array $s): bool => strtoupper((string) ($s['report_type'] ?? '')) === 'BDA'));
$others = array_values(array_filter($sources, static fn (array $s): bool => strtoupper((string) ($s['report_type'] ?? '')) !== 'BDA'));
$row = static function (string $kind, array $s) use ($h, $checked): string {
    $key = $kind . ':' . (int) $s['id'];
    $label = trim((string) ($s['report_number'] ?? '')) !== '' ? (string) $s['report_number'] : 'CR-' . (int) $s['id'];
    $when = strtotime((string) ($s['created_at'] ?? '')) ?: 0;

    return '<label class="bo-aar-annex__row"><input type="checkbox" name="annex_links[]" value="' . $h($key) . '"' . (isset($checked[$key]) ? ' checked' : '') . '> '
        . '<b>' . $h($label) . '</b> <span>' . $h(mb_substr((string) ($s['summary'] ?? ''), 0, 90)) . '</span> '
        . '<small>' . $h((string) ($s['submitter_callsign'] ?? '')) . ($when > 0 ? ' · ' . $h(date('d/m/Y', $when)) : '') . '</small></label>';
};
?>
<section class="ath-panel bo-aar-step" id="etape-annexes">
  <header class="bo-aar-step__head"><span class="bo-aar-step__n" aria-hidden="true"></span>
    <span class="bo-aar-step__txt"><b class="bo-aar-step__t">Annexes</b><span class="bo-aar-step__lead">Joignez les BDA et les comptes rendus du terrain qui appuient ce retour, et rédigez vos propres annexes.</span></span></header>
  <input type="hidden" name="annex_form" value="1">
  <div class="bo-aar-tabs" role="tablist" data-annex-tabs>
    <button type="button" class="bo-aar-tabs__tab is-active" role="tab" data-annex-tab="bda">BDA <span class="bo-aar-tabs__n"><?= count($bda) ?></span></button>
    <button type="button" class="bo-aar-tabs__tab" role="tab" data-annex-tab="cr">Comptes rendus du terrain <span class="bo-aar-tabs__n"><?= count($others) ?></span></button>
    <button type="button" class="bo-aar-tabs__tab" role="tab" data-annex-tab="note">Annexes rédigées <span class="bo-aar-tabs__n" data-annex-note-count><?= count($notes) ?></span></button>
  </div>
  <div data-annex-pane="bda" class="bo-aar-annex__list">
    <?php if ($bda === []): ?><p class="bo-aar-sublabel">Aucun BDA envoyé depuis le terrain pour l’instant.</p><?php endif; ?>
    <?php foreach ($bda as $s) { echo $row('bda', $s); } ?>
  </div>
  <div data-annex-pane="cr" class="bo-aar-annex__list" hidden>
    <?php if ($others === []): ?><p class="bo-aar-sublabel">Aucun compte rendu du terrain à joindre pour l’instant.</p><?php endif; ?>
    <?php foreach ($others as $s) { echo $row('cr', $s); } ?>
  </div>
  <div data-annex-pane="note" hidden>
    <div data-annex-notes>
      <?php foreach ($notes as $n): ?>
        <div class="bo-aar-annex__note">
          <input name="annex_note_title[]" maxlength="200" value="<?= $h($n['title'] ?? '') ?>" placeholder="Titre de l’annexe" aria-label="Titre de l’annexe">
          <textarea name="annex_note_body[]" rows="4" maxlength="20000" placeholder="Texte de l’annexe" aria-label="Texte de l’annexe"><?= $h($n['body'] ?? '') ?></textarea>
        </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="ath-btn" data-annex-add>+ Ajouter une annexe</button>
  </div>
</section>
<script>
(function () {
  var root = document.getElementById('etape-annexes');
  if (!root) { return; }
  var tabs = root.querySelectorAll('[data-annex-tab]');
  tabs.forEach(function (t) {
    t.addEventListener('click', function () {
      tabs.forEach(function (o) { o.classList.toggle('is-active', o === t); });
      root.querySelectorAll('[data-annex-pane]').forEach(function (p) { p.hidden = p.getAttribute('data-annex-pane') !== t.getAttribute('data-annex-tab'); });
    });
  });
  var add = root.querySelector('[data-annex-add]');
  var box = root.querySelector('[data-annex-notes]');
  if (add && box) {
    add.addEventListener('click', function () {
      var d = document.createElement('div');
      d.className = 'bo-aar-annex__note';
      d.innerHTML = '<input name="annex_note_title[]" maxlength="200" placeholder="Titre de l’annexe" aria-label="Titre de l’annexe"><textarea name="annex_note_body[]" rows="4" maxlength="20000" placeholder="Texte de l’annexe" aria-label="Texte de l’annexe"></textarea>';
      box.appendChild(d);
      d.querySelector('input').focus();
    });
  }
})();
</script>
