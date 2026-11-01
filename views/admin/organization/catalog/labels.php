<?php
declare(strict_types=1);

/** @var list<array{key: string, kind: string, defaults: array<string, string>, own: array<string, string>, used: bool}> $labelRows */
/** @var array<string, string> $labelLangs */
/** @var array<string, string> $labelKinds */

$rows = is_array($labelRows ?? null) ? $labelRows : [];
$langs = is_array($labelLangs ?? null) ? $labelLangs : ['fr' => 'Français', 'en' => 'English'];
$kinds = is_array($labelKinds ?? null) ? $labelKinds : [];
$ready = !empty($labelsReady);
$flashOk = trim((string) ($flashOk ?? ''));
$flashErr = trim((string) ($flashErr ?? ''));
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$usedCount = 0;
$ownCount = 0;
foreach ($rows as $r) {
    $usedCount += $r['used'] ? 1 : 0;
    $ownCount += $r['own'] !== [] ? 1 : 0;
}
$onlyUsed = $usedCount > 0;
?>
<div class="bo-catalog bo-labels">
    <header class="bo-catalog__hero">
        <p class="bo-catalog__kicker">Organisation</p>
        <h1 class="bo-catalog__title">Libellés des unités et postes</h1>
        <p class="bo-catalog__lead">
            Les noms créés par Athena (unités et emplois copiés d’un modèle, postes types de l’ORBAT, types d’unité)
            s’affichent dans la langue de chaque joueur : celle de son compte, sinon celle de son navigateur.
            Vous pouvez les remplacer, langue par langue. Les noms que votre communauté a saisis ou modifiés
            ne sont jamais traduits.
        </p>
        <div class="bo-catalog__hero-actions">
            <a class="ath-btn" href="<?= $h(url('back-office/organisation/catalogue')) ?>">Retour au catalogue</a>
            <a class="ath-btn" href="<?= $h(url('orbat')) ?>">Ouvrir l’ORBAT</a>
        </div>
    </header>

    <?php if ($flashOk !== ''): ?>
        <p class="ath-flash ath-flash--ok" role="status"><?= $h($flashOk) ?></p>
    <?php endif; ?>
    <?php if ($flashErr !== ''): ?>
        <p class="ath-flash ath-flash--err" role="alert"><?= $h($flashErr) ?></p>
    <?php endif; ?>
    <?php if (!$ready): ?>
        <p class="ath-flash ath-flash--err" role="alert">La personnalisation sera disponible après la prochaine mise à jour de la base. Les traductions du site s’appliquent déjà.</p>
    <?php endif; ?>

    <section class="bo-catalog__section" aria-labelledby="bo-labels-title">
        <h2 id="bo-labels-title" class="bo-catalog__section-title">Libellés</h2>
        <p class="bo-catalog__section-lead">
            <?= $h(sprintf($usedCount > 1 ? '%d libellés présents dans votre communauté' : '%d libellé présent dans votre communauté', (int) $usedCount)) ?>
            · <?= $h(sprintf($ownCount > 1 ? '%d personnalisés' : '%d personnalisé', (int) $ownCount)) ?>
            · <?= count($rows) ?> au total. Laissez un champ vide pour garder le libellé du site.
        </p>

        <div class="bo-labels__tools" role="search">
            <div class="bo-catalog__field">
                <label for="bo-labels-q">Rechercher</label>
                <input type="search" id="bo-labels-q" placeholder="Nom, traduction…" autocomplete="off">
            </div>
            <div class="bo-catalog__field bo-labels__narrow">
                <label for="bo-labels-kind">Type</label>
                <select id="bo-labels-kind">
                    <option value="">Tous</option>
                    <?php foreach ($kinds as $k => $kl): ?>
                        <option value="<?= $h((string) $k) ?>"><?= $h((string) $kl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <label class="bo-labels__toggle">
                <input type="checkbox" id="bo-labels-used" <?= $onlyUsed ? 'checked' : '' ?>>
                <span>Seulement ceux de ma communauté</span>
            </label>
            <label class="bo-labels__toggle">
                <input type="checkbox" id="bo-labels-own">
                <span>Seulement les personnalisés</span>
            </label>
        </div>
        <p class="bo-catalog__empty" id="bo-labels-count" aria-live="polite"></p>

        <form method="post" action="<?= $h(url('back-office/organisation/catalogue/libelles/enregistrer')) ?>" id="bo-labels-form">
            <?= \App\Core\Csrf::field() ?>
            <div class="bo-labels__list">
                <div class="bo-labels__row bo-labels__row--head" aria-hidden="true">
                    <span>Libellé du site</span>
                    <?php foreach ($langs as $lang => $langName): ?>
                        <span>Votre libellé · <?= $h((string) $langName) ?></span>
                    <?php endforeach; ?>
                    <span>Aperçu</span>
                </div>
                <?php foreach ($rows as $r):
                    $key = $r['key'];
                    $search = mb_strtolower(implode(' ', array_merge(array_values($r['defaults']), array_values($r['own']))));
                    ?>
                    <div class="bo-labels__row" data-kind="<?= $h($r['kind']) ?>" data-used="<?= $r['used'] ? '1' : '0' ?>"
                         data-own="<?= $r['own'] !== [] ? '1' : '0' ?>" data-search="<?= $h($search) ?>">
                        <div class="bo-labels__default">
                            <span class="bo-labels__kind"><?= $h((string) ($kinds[$r['kind']] ?? $r['kind'])) ?><?= $r['used'] ? ' · dans votre communauté' : '' ?></span>
                            <?php foreach ($langs as $lang => $langName): ?>
                                <span class="bo-labels__def"><b><?= $h(strtoupper((string) $lang)) ?></b> <span translate="no"><?= $h((string) ($r['defaults'][$lang] ?? '')) ?></span></span>
                            <?php endforeach; ?>
                        </div>
                        <?php foreach ($langs as $lang => $langName):
                            $own = (string) ($r['own'][$lang] ?? '');
                            ?>
                            <div class="bo-catalog__field">
                                <label class="bo-labels__sr" for="lbl-<?= $h($key . '-' . $lang) ?>">Votre libellé (<?= $h((string) $langName) ?>) pour « <?= $h((string) ($r['defaults']['fr'] ?? '')) ?> »</label>
                                <input type="text" translate="no" id="lbl-<?= $h($key . '-' . $lang) ?>" maxlength="150"
                                       name="labels[<?= $h($key) ?>][<?= $h((string) $lang) ?>]"
                                       value="<?= $h($own) ?>" data-initial="<?= $h($own) ?>" data-lang="<?= $h((string) $lang) ?>"
                                       placeholder="<?= $h((string) ($r['defaults'][$lang] ?? '')) ?>" <?= $ready ? '' : 'disabled' ?>>
                            </div>
                        <?php endforeach; ?>
                        <div class="bo-labels__preview">
                            <?php foreach ($langs as $lang => $langName): ?>
                                <span data-preview="<?= $h((string) $lang) ?>"><b><?= $h(strtoupper((string) $lang)) ?></b> <span translate="no"><?= $h((string) (($r['own'][$lang] ?? '') !== '' ? $r['own'][$lang] : ($r['defaults'][$lang] ?? ''))) ?></span></span>
                            <?php endforeach; ?>
                            <?php if ($r['own'] !== [] && $ready): ?>
                                <button type="submit" class="ath-btn bo-labels__reset" name="reset" value="<?= $h($key) ?>">Rétablir le libellé du site</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="bo-labels__bar">
                <span id="bo-labels-dirty" aria-live="polite"></span>
                <button type="submit" class="ath-btn ath-btn--solid" <?= $ready ? '' : 'disabled' ?>>Enregistrer les libellés</button>
            </div>
        </form>
    </section>
</div>
<script>
(function () {
  var form = document.getElementById('bo-labels-form');
  if (!form) return;
  var rows = Array.prototype.slice.call(form.querySelectorAll('.bo-labels__row[data-kind]'));
  var q = document.getElementById('bo-labels-q');
  var kind = document.getElementById('bo-labels-kind');
  var used = document.getElementById('bo-labels-used');
  var own = document.getElementById('bo-labels-own');
  var count = document.getElementById('bo-labels-count');
  var dirty = document.getElementById('bo-labels-dirty');
  function inputs() { return Array.prototype.slice.call(form.querySelectorAll('input[data-initial]')); }
  function filter() {
    var term = (q.value || '').trim().toLowerCase();
    var shown = 0;
    rows.forEach(function (r) {
      var hay = r.getAttribute('data-search') + ' ' + Array.prototype.map.call(r.querySelectorAll('input[data-initial]'), function (i) { return i.value.toLowerCase(); }).join(' ');
      var ok = (!term || hay.indexOf(term) !== -1)
        && (!kind.value || r.getAttribute('data-kind') === kind.value)
        && (!used.checked || r.getAttribute('data-used') === '1')
        && (!own.checked || r.getAttribute('data-own') === '1');
      r.hidden = !ok;
      if (ok) shown++;
    });
    count.textContent = shown === 0 ? 'Aucun libellé ne correspond.' : (shown === 1 ? '1 libellé affiché.' : shown + ' libellés affichés.');
  }
  function preview(input) {
    var row = input.closest('.bo-labels__row');
    var out = row && row.querySelector('[data-preview="' + input.getAttribute('data-lang') + '"] span');
    if (out) out.textContent = input.value.trim() || input.getAttribute('placeholder') || '';
    var n = inputs().filter(function (i) { return i.value.trim() !== i.getAttribute('data-initial'); }).length;
    dirty.textContent = n === 0 ? '' : (n === 1 ? '1 modification non enregistrée' : n + ' modifications non enregistrées');
  }
  [q, kind, used, own].forEach(function (el) { el.addEventListener('input', filter); el.addEventListener('change', filter); });
  form.addEventListener('input', function (e) { if (e.target.matches && e.target.matches('input[data-initial]')) preview(e.target); });
  form.addEventListener('submit', function (e) {
    // N’envoyer que les champs modifiés (des centaines de libellés).
    var resetting = e.submitter && e.submitter.name === 'reset';
    inputs().forEach(function (i) { if (resetting || i.value.trim() === i.getAttribute('data-initial')) i.disabled = true; });
  });
  filter();
})();
</script>
