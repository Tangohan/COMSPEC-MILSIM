<?php
/**
 * Page /9-line : rédaction d'un 9-LINE (CAS), d'un 5-LINE ou d'un MEDEVAC ligne par ligne (saisie libre possible),
 * envoi aux pilotes (appareils remontés par le jeu ou indicatifs saisis à la main), carte de transmission et historique
 * avec le statut de chaque destinataire (envoyé, reçu, lu, WILCO, UNABLE).
 * Thème et composants du back-office (jetons --ath-* de back-office-shell.css, mode nuit back-office-dark.css et
 * back-office-theme.js, kit effectifs-kit.css : en-tête effk-ph, panneaux, boutons, pastilles), en densité compacte et
 * valeurs en chasse fixe (atak-nine-line.css). Page autonome (sans menu du back-office) : ouverte par le QR code de
 * l'app JTAC, par tout membre connecté, souvent sur un téléphone ou un second écran.
 *
 * @var string $apiBase
 * @var string $assetVer
 * @var string $lang
 * @var ?string $jtacCallsign
 * @var string $defaultCallsign
 */
$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string) ($apiBase ?? url('')), '/');
$root = dirname(__DIR__, 2) . '/public/assets/';
$ver = static function (string $file) use ($root, $assetVer): string {
    $m = @filemtime($root . $file);

    return rawurlencode((string) ($assetVer ?? '1')) . ($m ? '.' . (int) $m : '');
};
$css = static fn (string $file): string => '<link rel="stylesheet" href="' . $h($base) . '/assets/css/' . $h($file) . '?v=' . $h($ver('css/' . $file)) . '">';
$fr = ($lang ?? 'en') === 'fr';
$csrf = \App\Core\Csrf::token();
$required = \App\Services\Tactical\NineLineBoardService::REQUIRED_LINES;
$suggestJtac = \App\Services\Tactical\AirBriefService::SUGGESTED_JTAC;
$suggestAir = \App\Services\Tactical\AirBriefService::SUGGESTED_AIRCRAFT;
$jtacCs = (string) ($jtacCallsign ?? '');
$defaultCs = (string) ($defaultCallsign ?? 'JTAC');
?>
<!DOCTYPE html>
<html lang="<?= $fr ? 'fr' : 'en' ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#0d1110">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <title><?= $h($title ?? '9-LINE') ?></title>
  <script>
  /* Mode nuit : même préférence que le back-office (athena.bo.theme), posé avant l'affichage. */
  (function () {
    var pref = 'auto';
    try { pref = localStorage.getItem('athena.bo.theme') || 'auto'; } catch (e) {}
    var dark = pref === 'dark' || (pref === 'auto' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.setAttribute('data-bo-theme', dark ? 'dark' : 'light');
  })();
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;600;700&display=swap">
  <?= $css('back-office-shell.css') ?>
  <?= $css('back-office-dark.css') ?>
  <?= $css('effectifs-kit.css') ?>
  <?= $css('atak-nine-line.css') ?>
</head>
<body class="bo-shell nl-page">
<div class="bo-eff-workspace nl-wrap">
<div class="effk">
  <header class="effk-ph nl-ph">
    <div class="effk-ph__copy">
      <span class="effk-k">ATAK · JTAC</span>
      <h2><span id="nl-top-kind">9-LINE</span> · demande d’appui aérien</h2>
    </div>
    <div class="effk-ph__acts nl-ph__acts">
      <label class="nl-cs" for="nl-jtac-cs">
        <span class="effk-k">Indicatif JTAC</span>
        <span class="nl-inline">
          <input class="effk-sel nl-input nl-mono" id="nl-jtac-cs" type="text" maxlength="32" list="nl-jtac-suggest" autocomplete="off" spellcheck="false"
            value="<?= $h($jtacCs) ?>" placeholder="<?= $h($defaultCs) ?>" data-default="<?= $h($defaultCs) ?>">
          <button type="button" class="effk-btn is-sm" id="nl-jtac-save">OK</button>
        </span>
      </label>
      <datalist id="nl-jtac-suggest"><?php foreach ($suggestJtac as $s): ?><option value="<?= $h($s) ?>"><?php endforeach; ?></datalist>
      <span class="effk-pill is-ok nl-live" id="nl-live" role="status">connexion…</span>
      <button type="button" class="ath-theme-toggle nl-theme" data-ath-theme-toggle aria-pressed="false" title="Passer en mode nuit" aria-label="Passer en mode nuit">
        <svg class="ath-theme-toggle__sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"></path></svg>
        <svg class="ath-theme-toggle__moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"></path></svg>
      </button>
    </div>
  </header>

  <div class="effk-stats nl-stats" role="list" aria-label="Demande en cours">
    <div class="effk-stat" role="listitem"><span class="effk-k">Type</span><b id="nl-rail-kind">CAS</b></div>
    <div class="effk-stat" role="listitem"><span class="effk-k">Destinataires</span><b id="nl-rail-dest">0</b></div>
    <div class="effk-stat" role="listitem"><span class="effk-k">Lignes</span><b id="nl-rail-lines">0 / 9</b></div>
    <div class="effk-stat" role="listitem"><span class="effk-k">Obligatoires</span><b id="nl-progress-text">0 / 3</b></div>
  </div>

  <div class="nl-grid">
    <section class="effk-panel nl-panel nl-col-air" id="nl-sec-aircraft" aria-labelledby="nl-aircraft-title">
      <div class="nl-panel__h">
        <span class="nl-step" aria-hidden="true">1</span>
        <div><h3 id="nl-aircraft-title">Destinataires</h3><small><span id="nl-aircraft-count">—</span> · <span id="nl-aircraft-fresh">—</span></small></div>
      </div>
      <div class="nl-panel__b">
        <div class="effk-bar nl-tools">
          <label class="effk-check"><input type="checkbox" id="nl-players-only" checked> <span>Joueurs uniquement</span></label>
          <span class="nl-hint" id="nl-ai-hidden"></span>
        </div>
        <div id="nl-aircraft" class="nl-aircraft" aria-live="polite"></div>
        <div class="nl-manual">
          <label class="effk-k" for="nl-manual-cs">Indicatif saisi à la main</label>
          <div class="nl-inline">
            <input class="effk-sel nl-input nl-mono" id="nl-manual-cs" type="text" maxlength="32" list="nl-air-suggest" autocomplete="off" spellcheck="false" placeholder="ex. HOG 21">
            <button type="button" class="effk-btn is-sm" id="nl-manual-add">Ajouter</button>
          </div>
          <datalist id="nl-air-suggest"><?php foreach ($suggestAir as $s): ?><option value="<?= $h($s) ?>"><?php endforeach; ?></datalist>
          <p class="nl-hint">Appareil absent de la liste : le pilote dont l’appareil ou l’indicatif ATAK porte ce nom le reçoit.</p>
        </div>
        <div id="nl-dest" class="nl-chips" aria-live="polite"></div>
      </div>
    </section>

    <form class="effk-panel nl-panel nl-col-form" id="nl-form" novalidate autocomplete="off" aria-labelledby="nl-form-title">
      <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
      <input type="hidden" name="kind" id="nl-kind" value="cas">
      <input type="hidden" name="map_id" id="nl-map" value="1">
      <div class="nl-panel__h">
        <span class="nl-step" aria-hidden="true">2</span>
        <div><h3 id="nl-form-title"><span id="nl-kind-title">9-LINE · Appui aérien rapproché (CAS)</span></h3><small><span id="nl-draft-state">Brouillon vide</span> · <span id="nl-req-note">lignes obligatoires marquées *</span></small></div>
      </div>
      <div class="nl-panel__b">
        <div class="effk-bar nl-bar">
          <div class="effk-seg" role="group" aria-label="Type de demande">
            <button type="button" class="is-on" data-kind="cas" aria-pressed="true">9-LINE</button>
            <button type="button" data-kind="fiveline" aria-pressed="false">5-LINE</button>
            <button type="button" data-kind="medevac" aria-pressed="false">MEDEVAC</button>
          </div>
          <label class="effk-check" title="Aucune ligne obligatoire ni contrôle de grille : un avertissement seulement">
            <input type="checkbox" id="nl-free" name="free" value="1"> <span>Saisie libre</span>
          </label>
        </div>
        <div class="nl-lines" id="nl-lines"></div>
        <div class="nl-remarks" id="nl-remarks-row">
          <label class="effk-k" for="nl-remarks">Remarques et restrictions</label>
          <textarea class="effk-sel nl-input" id="nl-remarks" name="remarks" rows="2" maxlength="1000" placeholder="ex. FAH 270 ± 20, menace SA-7 au sud, DANGER CLOSE"></textarea>
        </div>
        <details class="nl-calc" data-only="cas">
          <summary>Aide au calcul · lignes 2 et 3 (depuis les grilles des lignes 1 et 6)</summary>
          <div class="nl-inline">
            <label class="effk-k" for="nl-decl">Déclinaison (° est)</label>
            <input class="effk-sel nl-input nl-mono nl-narrow" id="nl-decl" type="text" inputmode="decimal" value="0">
            <button type="button" class="effk-btn is-sm" id="nl-calc-btn">Calculer</button>
          </div>
          <p class="nl-hint">Cartes à grille standard (origine en bas à gauche, nord en haut). Les cartes Arma n’ont pas de déclinaison : 0, sauf consigne de votre mission.</p>
          <p class="nl-calc__out" id="nl-calc-out" aria-live="polite"></p>
        </details>
        <p class="effk-note nl-msg" id="nl-form-msg" role="status" hidden></p>
        <div class="nl-actions">
          <button type="submit" class="effk-btn is-primary" id="nl-submit">Transmettre aux pilotes</button>
          <button type="button" class="effk-btn is-sm" id="nl-reset">Réinitialiser</button>
        </div>
      </div>
    </form>

    <div class="nl-col-side">
      <section class="effk-panel nl-panel" id="nl-sec-card" aria-labelledby="nl-card-title">
        <div class="nl-panel__h">
          <span class="nl-step" aria-hidden="true">3</span>
          <div><h3 id="nl-card-title">Carte de transmission</h3><small>À lire à la radio, dans l’ordre</small></div>
        </div>
        <div class="nl-panel__b"><div id="nl-preview"></div></div>
      </section>

      <section class="effk-panel nl-panel" id="nl-sec-history" aria-labelledby="nl-history-title">
        <div class="nl-panel__h">
          <span class="nl-step" aria-hidden="true">4</span>
          <div><h3 id="nl-history-title">Historique et accusés</h3><small id="nl-history-count">—</small></div>
        </div>
        <div class="nl-panel__b">
          <div class="effk-bar nl-tools">
            <label class="effk-k" for="nl-history-map">Carte</label>
            <select class="effk-sel" id="nl-history-map"><option value="0">Toutes les cartes</option></select>
            <button type="button" class="effk-btn is-sm nl-danger" id="nl-clear" hidden>Vider</button>
          </div>
          <div id="nl-selected"></div>
          <ul class="nl-history" id="nl-history"></ul>
        </div>
      </section>
    </div>
  </div>

  <dialog class="effk-md nl-dialog" id="nl-dialog" aria-labelledby="nl-dialog-title">
    <form method="dialog" class="nl-dialog__body" id="nl-dialog-form">
      <span class="effk-k">Confirmation</span>
      <h3 id="nl-dialog-title"></h3>
      <p class="nl-dialog__text" id="nl-dialog-text"></p>
      <div class="nl-dialog__word" id="nl-dialog-word" hidden>
        <label class="effk-k" for="nl-dialog-input">Tapez SUPPRIMER pour confirmer</label>
        <input class="effk-sel nl-input" id="nl-dialog-input" type="text" autocomplete="off" spellcheck="false">
      </div>
      <div class="nl-actions">
        <button type="submit" value="ok" class="effk-btn nl-danger-btn" id="nl-dialog-ok">Supprimer</button>
        <button type="submit" value="cancel" class="effk-btn">Annuler</button>
      </div>
    </form>
  </dialog>
</div>
</div>

  <script>
    window.NINE_LINE = <?= json_encode([
        'base' => $base,
        'lang' => $fr ? 'fr' : 'en',
        'csrf' => $csrf,
        'required' => $required,
        'jtac' => $jtacCs,
        'jtacDefault' => $defaultCs,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  </script>
  <script src="<?= $h($base) ?>/assets/js/back-office-theme.js?v=<?= $h($ver('js/back-office-theme.js')) ?>" defer></script>
  <script src="<?= $h($base) ?>/assets/js/atak-nine-line.js?v=<?= $h($ver('js/atak-nine-line.js')) ?>" defer></script>
</body>
</html>
