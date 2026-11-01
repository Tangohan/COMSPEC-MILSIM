<?php
/**
 * Suggestions du champ « Jeu » : liste des jeux connus (datalist) et rappel du nom harmonisé
 * (« arma3 » → « Arma 3 »). À inclure une fois par page ; le champ porte list="athena-game-list"
 * et data-game-suggest.
 */
if (!empty($GLOBALS['athenaGameSuggestRendered'])) {
    return;
}
$GLOBALS['athenaGameSuggestRendered'] = true;
?>
<datalist id="athena-game-list">
  <?php foreach (\App\Support\GameCatalog::labels() as $gsLabel): ?>
  <option value="<?= htmlspecialchars($gsLabel, ENT_QUOTES, 'UTF-8') ?>"></option>
  <?php endforeach; ?>
</datalist>
<script>
(function () {
  var names = <?= json_encode(\App\Support\GameCatalog::aliasIndex(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  function key(v) {
    return (v || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '');
  }
  function harmonize(v) {
    var seen = {};
    return v.split(/\s*[,;\/|+&]\s*/).map(function (p) { return p.trim().replace(/\s+/g, ' '); })
      .filter(function (p) { return key(p) !== ''; })
      .map(function (p) { return names[key(p)] || p; })
      .filter(function (p) { var k = key(p); if (seen[k]) return false; seen[k] = true; return true; })
      .join(' / ');
  }
  function wire(input) {
    var hint = document.createElement('p');
    hint.className = 'athena-game-hint';
    hint.setAttribute('aria-live', 'polite');
    hint.style.cssText = 'margin:.35rem 0 0;font-size:12px;opacity:.85;';
    hint.hidden = true;
    var text = document.createElement('span');
    var use = document.createElement('button');
    use.type = 'button';
    use.textContent = 'Utiliser';
    use.style.cssText = 'margin-left:.5rem;font:inherit;font-weight:700;text-decoration:underline;background:none;border:0;padding:0;cursor:pointer;color:inherit;';
    hint.appendChild(text);
    hint.appendChild(use);
    input.insertAdjacentElement('afterend', hint);
    function refresh() {
      var raw = input.value.trim();
      var fixed = raw ? harmonize(raw) : '';
      hint.hidden = !fixed || fixed === raw;
      text.textContent = 'Sera enregistré sous « ' + fixed + ' ».';
    }
    use.addEventListener('click', function () { input.value = harmonize(input.value); refresh(); input.focus(); });
    input.addEventListener('input', refresh);
    refresh();
  }
  function init() {
    Array.prototype.forEach.call(document.querySelectorAll('input[data-game-suggest]'), wire);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
</script>
