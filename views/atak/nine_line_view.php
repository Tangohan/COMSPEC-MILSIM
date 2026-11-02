<?php
/**
 * Page /9-line : rédaction d'un 9-LINE ligne par ligne, appareils remontés par le jeu, carte de transmission et
 * historique. Thème du questionnaire d'enrôlement (public/assets/css/community-enlistment.css, classes ce-*),
 * complété par public/assets/css/atak-nine-line.css (cartes d'appareils, carte de transmission, mode jour / nuit).
 *
 * @var string $apiBase
 * @var string $assetVer
 * @var string $lang
 */
$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string) ($apiBase ?? url('')), '/');
$root = dirname(__DIR__, 2) . '/public/assets/';
$ver = static function (string $file) use ($root, $assetVer): string {
    $m = @filemtime($root . $file);

    return rawurlencode((string) ($assetVer ?? '1')) . ($m ? '.' . (int) $m : '');
};
$fr = ($lang ?? 'en') === 'fr';
$csrf = \App\Core\Csrf::token();
$groups = [
    ['id' => 'nl-sec-approach', 'n' => '02', 'title' => 'Approche', 'lines' => [1, 2, 3]],
    ['id' => 'nl-sec-target', 'n' => '03', 'title' => 'Cible', 'lines' => [4, 5, 6]],
    ['id' => 'nl-sec-coord', 'n' => '04', 'title' => 'Coordination', 'lines' => [7, 8, 9]],
];
$required = \App\Services\Tactical\NineLineBoardService::REQUIRED_LINES;
?>
<!DOCTYPE html>
<html lang="<?= $fr ? 'fr' : 'en' ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#020617">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <title><?= $h($title ?? '9-LINE') ?></title>
  <script>
  /* Mode nuit : même préférence que le reste du site (athena.bo.theme), posé avant l'affichage. */
  (function () {
    var pref = 'auto';
    try { pref = localStorage.getItem('athena.bo.theme') || 'auto'; } catch (e) {}
    var dark = pref === 'dark' || (pref === 'auto' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.setAttribute('data-bo-theme', dark ? 'dark' : 'light');
  })();
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="<?= $h($base) ?>/assets/css/community-enlistment.css?v=<?= $h($ver('css/community-enlistment.css')) ?>">
  <link rel="stylesheet" href="<?= $h($base) ?>/assets/css/atak-nine-line.css?v=<?= $h($ver('css/atak-nine-line.css')) ?>">
</head>
<body class="ce nl-page">
  <header class="ce-topbar nl-topbar">
    <div class="ce-topbar__brand">
      <span class="nl-mark" aria-hidden="true">9L</span>
      <span class="ce-topbar__name">COMSPEC · JTAC · 9-LINE</span>
    </div>
    <div class="ce-topbar__links">
      <span class="nl-live" id="nl-live" role="status">connexion…</span>
      <button type="button" class="nl-theme" data-ath-theme-toggle aria-pressed="false" title="Passer en mode nuit" aria-label="Passer en mode nuit">
        <svg class="nl-theme__sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"></path></svg>
        <svg class="nl-theme__moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"></path></svg>
      </button>
    </div>
  </header>

  <div class="ce-wrap ce-form-layout nl-layout">
    <aside class="ce-rail" aria-label="Avancement du 9-LINE">
      <div class="ce-rail__block">
        <p class="ce-rail__classif" aria-hidden="true">Demande d’appui · usage opérationnel</p>
        <p class="ce-rail__label">Demande en cours</p>
        <div class="ce-rail__row"><span>Type</span><span id="nl-rail-kind">CAS</span></div>
        <div class="ce-rail__row"><span>Destinataires</span><span id="nl-rail-dest">0</span></div>
        <div class="ce-rail__row"><span>Lignes</span><span id="nl-rail-lines">0 / 9</span></div>
        <div class="ce-progress">
          <div class="ce-progress__bar"><div class="ce-progress__fill" id="nl-progress"></div></div>
          <p class="ce-progress__text" id="nl-progress-text">Lignes obligatoires : 0 / 3</p>
        </div>
        <p class="ce-rail__stamp" aria-hidden="true">VISA JTAC · SAISIE ATHENA</p>
      </div>
      <nav class="ce-rail__nav">
        <p class="ce-rail__nav-label">Parcours</p>
        <a href="#nl-sec-aircraft">01 — Appareils destinataires</a>
        <?php foreach ($groups as $g): ?>
          <a href="#<?= $h($g['id']) ?>"><?= $h($g['n']) ?> — <?= $h($g['title']) ?> (<?= $h(implode(', ', $g['lines'])) ?>)</a>
        <?php endforeach; ?>
        <a href="#nl-sec-card">05 — Carte de transmission</a>
        <a href="#nl-sec-history">06 — Historique</a>
      </nav>
    </aside>

    <main class="nl-main">
      <section class="ce-form-shell nl-shell" id="nl-sec-aircraft" aria-labelledby="nl-aircraft-title">
        <div class="ce-form-head">
          <p class="ce-form-head__doc">Étape 01 · Destinataires</p>
          <h1 class="ce-form-head__title" id="nl-aircraft-title">Appareils remontés par le jeu</h1>
          <div class="ce-form-head__meta">
            <span id="nl-aircraft-count">—</span>
            <span id="nl-aircraft-fresh">—</span>
          </div>
        </div>
        <div class="ce-form">
          <p class="ce-help">Cochez le ou les appareils à qui adresser ce 9-LINE. Les aéronefs avec équipage sont remontés par le mod toutes les quelques secondes ; un appareil sans nouvelle depuis 30 s est signalé « signal perdu ».</p>
          <div id="nl-aircraft" class="nl-aircraft" aria-live="polite"></div>
        </div>
      </section>

      <form class="ce-form-shell nl-shell" id="nl-form" novalidate autocomplete="off">
        <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
        <input type="hidden" name="kind" id="nl-kind" value="cas">
        <input type="hidden" name="map_id" id="nl-map" value="1">
        <div class="ce-form-head">
          <p class="ce-form-head__doc">Étapes 02 à 04 · Rédaction</p>
          <h2 class="ce-form-head__title"><span class="ce-title-verb">9-LINE</span> <span id="nl-kind-title">Appui aérien rapproché (CAS)</span></h2>
          <div class="ce-form-head__meta"><span>Lignes obligatoires marquées *</span><span id="nl-draft-state">Brouillon vide</span></div>
        </div>
        <div class="ce-form">
          <div class="ce-flow" role="group" aria-label="Type de demande">
            <button type="button" class="ce-flow-btn is-active" data-kind="cas" aria-pressed="true">CAS · appui aérien</button>
            <button type="button" class="ce-flow-btn" data-kind="medevac" aria-pressed="false">MEDEVAC · évacuation</button>
          </div>

          <?php foreach ($groups as $g): ?>
            <div class="ce-form-section nl-group" id="<?= $h($g['id']) ?>" role="group" aria-labelledby="<?= $h($g['id']) ?>-t">
              <h3 class="ce-section-title" id="<?= $h($g['id']) ?>-t"><?= $h($g['n']) ?> · <?= $h($g['title']) ?></h3>
              <div class="nl-lines" data-lines="<?= $h(implode(',', $g['lines'])) ?>"></div>
              <?php if ($g['id'] === 'nl-sec-approach'): ?>
                <div class="ce-panel nl-calc" data-only="cas">
                  <p class="ce-panel__title">Aide au calcul · lignes 2 et 3</p>
                  <p class="ce-help">Depuis la grille de l’IP (ligne 1) et celle de la cible (ligne 6). Cartes à grille standard (origine en bas à gauche, nord en haut). Les cartes Arma n’ont pas de déclinaison : 0, sauf consigne de votre mission.</p>
                  <div class="nl-calc__row">
                    <label class="ce-label" for="nl-decl">Déclinaison (° est)</label>
                    <input class="input-field" id="nl-decl" type="text" inputmode="decimal" value="0">
                    <button type="button" class="ce-btn ce-btn--soft" id="nl-calc-btn">Calculer</button>
                  </div>
                  <p class="nl-calc__out" id="nl-calc-out" aria-live="polite"></p>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>

          <div class="ce-notice ce-notice--muted nl-form-msg" id="nl-form-msg" role="status" hidden></div>
          <div class="nl-actions">
            <button type="submit" class="ce-btn ce-btn--primary" id="nl-submit">Transmettre le 9-LINE</button>
            <button type="button" class="ce-btn ce-btn--soft" id="nl-reset">Réinitialiser le formulaire</button>
          </div>
        </div>
      </form>

      <section class="ce-form-shell nl-shell" id="nl-sec-card" aria-labelledby="nl-card-title">
        <div class="ce-form-head">
          <p class="ce-form-head__doc">Étape 05 · Relecture</p>
          <h2 class="ce-form-head__title" id="nl-card-title">Carte de transmission</h2>
          <div class="ce-form-head__meta"><span>À lire à la radio, dans l’ordre</span></div>
        </div>
        <div class="ce-form">
          <div id="nl-preview"></div>
        </div>
      </section>

      <section class="ce-form-shell nl-shell" id="nl-sec-history" aria-labelledby="nl-history-title">
        <div class="ce-form-head">
          <p class="ce-form-head__doc">Étape 06 · Journal</p>
          <h2 class="ce-form-head__title" id="nl-history-title">Historique des 9-LINE</h2>
          <div class="ce-form-head__meta"><span id="nl-history-count">—</span></div>
        </div>
        <div class="ce-form">
          <div class="nl-history-tools">
            <label class="ce-label" for="nl-history-map">Carte</label>
            <select class="input-field" id="nl-history-map"><option value="0">Toutes les cartes</option></select>
            <button type="button" class="ce-btn ce-btn--soft nl-danger" id="nl-clear" hidden>Vider l’historique</button>
          </div>
          <div id="nl-selected"></div>
          <ul class="nl-history" id="nl-history"></ul>
        </div>
      </section>
    </main>
  </div>

  <dialog class="nl-dialog" id="nl-dialog" aria-labelledby="nl-dialog-title">
    <form method="dialog" class="nl-dialog__body" id="nl-dialog-form">
      <p class="ce-notice__kicker">Confirmation</p>
      <h3 class="ce-notice__title" id="nl-dialog-title"></h3>
      <p class="ce-notice__lead" id="nl-dialog-text"></p>
      <div class="nl-dialog__word" id="nl-dialog-word" hidden>
        <label class="ce-label" for="nl-dialog-input">Tapez SUPPRIMER pour confirmer</label>
        <input class="input-field" id="nl-dialog-input" type="text" autocomplete="off" spellcheck="false">
      </div>
      <div class="nl-actions">
        <button type="submit" value="ok" class="ce-btn ce-btn--primary nl-danger-btn" id="nl-dialog-ok">Supprimer</button>
        <button type="submit" value="cancel" class="ce-btn ce-btn--soft">Annuler</button>
      </div>
    </form>
  </dialog>

  <script>
    window.NINE_LINE = <?= json_encode([
        'base' => $base,
        'lang' => $fr ? 'fr' : 'en',
        'csrf' => $csrf,
        'required' => $required,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  </script>
  <script src="<?= $h($base) ?>/assets/js/back-office-theme.js?v=<?= $h($ver('js/back-office-theme.js')) ?>" defer></script>
  <script src="<?= $h($base) ?>/assets/js/atak-nine-line.js?v=<?= $h($ver('js/atak-nine-line.js')) ?>" defer></script>
</body>
</html>
