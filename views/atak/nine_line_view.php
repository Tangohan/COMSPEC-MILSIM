<?php
/**
 * Page /9-line : rédaction d'un 9-LINE (CAS), d'un 5-LINE ou d'un MEDEVAC ligne par ligne (saisie libre possible),
 * envoi aux pilotes (appareils remontés par le jeu ou indicatifs saisis à la main), carte de transmission et historique
 * avec le statut de chaque destinataire (envoyé, reçu, lu, WILCO, UNABLE).
 * Mise en page et thème de la maquette « 9-Line - Athena » (2026-11-02) : noir #050505, vert #34d399, Inter et
 * JetBrains Mono servis par le site (public/assets/fonts/nine-line), bandeau photo (assets/images/night-team.jpg),
 * cartes numérotées, carte de transmission collante à droite. Styles : public/assets/css/atak-nine-line.css ;
 * comportement : public/assets/js/atak-nine-line.js. Barre du haut réduite : marque Athena, titre, langue, compte.
 *
 * @var string $apiBase
 * @var string $assetVer
 * @var string $lang
 * @var ?string $jtacCallsign
 * @var string $defaultCallsign
 * @var array<string, array<string, mixed>> $mySpecs pilote et JTAC détectés au dossier de ce membre
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
$required = \App\Services\Tactical\NineLineBoardService::REQUIRED_LINES;
$suggestJtac = \App\Services\Tactical\AirBriefService::SUGGESTED_JTAC;
$suggestAir = \App\Services\Tactical\AirBriefService::SUGGESTED_AIRCRAFT;
$jtacCs = (string) ($jtacCallsign ?? '');
$defaultCs = (string) ($defaultCallsign ?? 'JTAC');
$langHref = static fn (string $code): string => function_exists('locale_switch_url') ? (string) locale_switch_url($code) : (string) url('locale/' . $code);
$loggedIn = (int) (\App\Core\Session::get('user_id') ?? 0) > 0;
?>
<!DOCTYPE html>
<html lang="<?= $fr ? 'fr' : 'en' ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#050505">
  <meta name="color-scheme" content="dark">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <title><?= $h($title ?? '9-LINE') ?></title>
  <link rel="stylesheet" href="<?= $h($base) ?>/assets/css/atak-nine-line.css?v=<?= $h($ver('css/atak-nine-line.css')) ?>">
</head>
<body class="nl-page">
<svg class="nl-sprite" aria-hidden="true" focusable="false">
  <symbol id="nl-s-x" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"></path></symbol>
  <symbol id="nl-s-send" viewBox="0 0 24 24"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4z"></path></symbol>
  <symbol id="nl-s-chev" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"></path></symbol>
  <symbol id="nl-s-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"></path></symbol>
  <symbol id="nl-s-alert" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"></circle><path d="M12 8v5M12 16.5v.5"></path></symbol>
  <symbol id="nl-s-copy" viewBox="0 0 24 24"><rect x="9" y="9" width="12" height="12" rx="1"></rect><path d="M5 15V4a1 1 0 0 1 1-1h11"></path></symbol>
</svg>

<header class="hd"><div class="w">
  <a class="brand" href="<?= $h(url('')) ?>/" aria-label="Athena — <?= $h(__('common.home')) ?>"><img src="<?= $h($base) ?>/assets/brand/athena-embleme-blanc.svg" alt="" width="30" height="26">ATHENA<em>.</em></a>
  <span class="hd-title" translate="no">9-LINE · JTAC</span>
  <div class="hr">
    <nav class="lang" aria-label="<?= $h(__('common.choose_language')) ?>">
      <?php foreach (['fr', 'en'] as $code): ?>
        <?php if (($fr ? 'fr' : 'en') === $code): ?>
          <span class="on" aria-current="true"><?= strtoupper($code) ?></span>
        <?php else: ?>
          <a href="<?= $h($langHref($code)) ?>" hreflang="<?= $code ?>" lang="<?= $code ?>"><?= strtoupper($code) ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>
    <?php if ($loggedIn): ?>
      <a class="cta" href="<?= $h(url('account')) ?>"><?= $h(__('common.account')) ?></a>
    <?php else: ?>
      <a class="cta" href="<?= $h(url('login')) ?>"><?= $h(__('common.login')) ?></a>
    <?php endif; ?>
  </div>
</div></header>

<section class="hero"><div class="w">
  <div class="htop">
    <div>
      <div class="kk"><i></i>ATAK · JTAC · <span id="nl-kick">Appui aérien rapproché</span></div>
      <h1><span id="nl-top-kind" translate="no">9-LINE</span><span class="dot">.</span></h1>
      <p class="lede" id="nl-lede">Rédigez la demande ligne par ligne, vérifiez la carte de transmission, puis envoyez-la aux pilotes à l’écoute.</p>
    </div>
    <div class="jtac">
      <label class="lbl" for="nl-jtac-cs">Indicatif JTAC</label>
      <div class="jrow">
        <input class="inp" id="nl-jtac-cs" type="text" maxlength="32" list="nl-jtac-suggest" autocomplete="off" spellcheck="false"
          value="<?= $h($jtacCs) ?>" placeholder="<?= $h($defaultCs) ?>" data-default="<?= $h($defaultCs) ?>">
        <button type="button" class="btn2" id="nl-jtac-save">OK</button>
        <span class="sync" role="status"><i></i><span id="nl-live">connexion…</span></span>
      </div>
      <p class="me-spec" id="nl-me-spec" aria-live="polite"></p>
      <datalist id="nl-jtac-suggest"><?php foreach ($suggestJtac as $s): ?><option value="<?= $h($s) ?>"><?php endforeach; ?></datalist>
    </div>
  </div>
  <div class="stats" role="list" aria-label="Demande en cours">
    <div class="stat" role="listitem"><span class="lbl">Type</span><b class="mono" id="nl-rail-kind" translate="no">CAS</b></div>
    <div class="stat" role="listitem"><span class="lbl">Destinataires</span><b id="nl-rail-dest">0</b></div>
    <div class="stat" role="listitem"><span class="lbl">Lignes saisies</span><b id="nl-rail-lines">0<small> / 9</small></b></div>
    <div class="stat" role="listitem" id="nl-req-stat"><span class="lbl">Obligatoires</span><b id="nl-progress-text">0<small> / 3</small></b></div>
  </div>
</div></section>

<main class="main">
  <div class="col">
    <section class="card" id="nl-sec-aircraft" aria-labelledby="nl-aircraft-title">
      <div class="ch"><span class="num">01</span><div><h2 id="nl-aircraft-title">Destinataires</h2><p><span id="nl-aircraft-count">—</span> · <span id="nl-aircraft-fresh">—</span></p></div></div>
      <div class="cb">
        <div class="bar"><label class="chk"><input type="checkbox" id="nl-players-only" checked>Joueurs uniquement</label><span class="meta" id="nl-ai-hidden"></span></div>
        <div id="nl-aircraft" aria-live="polite"></div>
        <details class="roster" id="nl-roster-box">
          <summary><svg class="i"><use href="#nl-s-chev"></use></svg>Pilotes et JTAC de la communauté <span class="meta" id="nl-roster-count"></span></summary>
          <p class="hint">Lu dans les dossiers : qualifications et fonctions attribuées. Les heures de vol viennent du jeu. Une détection informe, elle n’empêche jamais un envoi.</p>
          <div id="nl-roster"></div>
        </details>
        <div class="manual">
          <label class="lbl" for="nl-manual-cs">Indicatif saisi à la main</label>
          <div class="jrow">
            <input class="inp" id="nl-manual-cs" type="text" maxlength="32" list="nl-air-suggest" autocomplete="off" spellcheck="false" placeholder="ex. HOG 21">
            <button type="button" class="btn2" id="nl-manual-add"><svg class="i"><use href="#nl-s-plus"></use></svg>Ajouter</button>
          </div>
          <datalist id="nl-air-suggest"><?php foreach ($suggestAir as $s): ?><option value="<?= $h($s) ?>"><?php endforeach; ?></datalist>
          <div class="chips" id="nl-dest" aria-live="polite"></div>
          <span class="hint">Appareil absent de la liste : le pilote dont l’appareil ou l’indicatif ATAK porte ce nom reçoit la demande.</span>
        </div>
      </div>
    </section>

    <form class="card" id="nl-form" novalidate autocomplete="off" aria-labelledby="nl-kind-title">
      <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
      <input type="hidden" name="kind" id="nl-kind" value="cas">
      <input type="hidden" name="map_id" id="nl-map" value="1">
      <div class="ch"><span class="num">02</span><div><h2 id="nl-kind-title">9-LINE · Appui aérien rapproché</h2><p id="nl-draft-state">Brouillon vide · lignes obligatoires marquées</p></div></div>
      <div class="cb">
        <div class="bar">
          <div class="seg" role="group" aria-label="Type de demande">
            <button type="button" class="on" data-kind="cas" aria-pressed="true">9-LINE</button>
            <button type="button" data-kind="fiveline" aria-pressed="false">5-LINE</button>
            <button type="button" data-kind="medevac" aria-pressed="false">MEDEVAC</button>
          </div>
          <label class="chk push" title="Aucune ligne obligatoire ni contrôle de grille : un avertissement seulement"><input type="checkbox" id="nl-free" name="free" value="1">Saisie libre</label>
        </div>
        <div class="lines" id="nl-lines"></div>
        <details class="calc" id="nl-calc" data-only="cas">
          <summary><svg class="i"><use href="#nl-s-chev"></use></svg>Aide au calcul · lignes 2 et 3 depuis les grilles des lignes 1 et 6</summary>
          <div class="cres">
            <div><span class="lbl">Cap IP → cible</span><b id="nl-c-brg" class="na">—</b></div>
            <div><span class="lbl">Distance</span><b id="nl-c-nm" class="na">—</b></div>
            <div><span class="lbl">Distance (m)</span><b id="nl-c-m" class="na">—</b></div>
            <button type="button" class="btn2" id="nl-calc-btn" disabled>Appliquer</button>
          </div>
          <p class="hint calc-hint">Saisissez des grilles numériques de même précision (4 à 10 chiffres) en lignes 1 et 6. Sur Arma, le nord magnétique est confondu avec le nord grille.</p>
        </details>
      </div>
    </form>

    <section class="card" id="nl-sec-history" aria-labelledby="nl-history-title">
      <div class="ch"><span class="num">03</span><div><h2 id="nl-history-title">Historique et accusés</h2><p id="nl-history-count">—</p></div>
        <div class="sp hist-tools">
          <label class="lbl" for="nl-history-map">Carte</label>
          <select class="inp sel" id="nl-history-map"><option value="0">Toutes les cartes</option></select>
          <button type="button" class="btn2 dng" id="nl-clear" hidden>Vider</button>
        </div>
      </div>
      <div class="cb">
        <div id="nl-selected"></div>
        <ul class="hist" id="nl-history"></ul>
      </div>
    </section>
  </div>

  <aside class="side" aria-label="Carte de transmission">
    <div class="tx">
      <div class="txh"><h3 id="nl-tx-title" translate="no">9-LINE · CAS</h3><span class="pill drf" id="nl-tx-state"><i></i>Brouillon</span></div>
      <div class="txb" id="nl-preview"></div>
      <div class="txf"><span id="nl-tx-to">Tous les appareils à l’écoute</span><span id="nl-tx-save">—</span></div>
    </div>
    <div class="qc" id="nl-qc"></div>
    <div class="acts">
      <button type="submit" form="nl-form" class="cta big" id="nl-submit"><svg class="i"><use href="#nl-s-send"></use></svg>Transmettre aux pilotes</button>
      <button type="button" class="btn2 sq" id="nl-copy" title="Copier le texte radio" aria-label="Copier le texte radio"><svg class="i"><use href="#nl-s-copy"></use></svg></button>
    </div>
    <button type="button" class="btn2 wide" id="nl-reset">Réinitialiser</button>
  </aside>
</main>

<div class="toast" id="nl-toast" role="status" aria-live="polite"><span id="nl-toast-t"></span><button type="button" id="nl-toast-u" hidden>Annuler</button></div>

<dialog class="dlg" id="nl-dialog" aria-labelledby="nl-dialog-title">
  <form method="dialog" class="dlg-b" id="nl-dialog-form">
    <span class="lbl">Confirmation</span>
    <h3 id="nl-dialog-title"></h3>
    <p class="meta" id="nl-dialog-text"></p>
    <div class="dlg-word" id="nl-dialog-word" hidden>
      <label class="lbl" for="nl-dialog-input">Tapez SUPPRIMER pour confirmer</label>
      <input class="inp" id="nl-dialog-input" type="text" autocomplete="off" spellcheck="false">
    </div>
    <div class="dlg-a">
      <button type="submit" value="cancel" class="btn2">Annuler</button>
      <button type="submit" value="ok" class="cta dng" id="nl-dialog-ok">Supprimer</button>
    </div>
  </form>
</dialog>

<script>
  window.NINE_LINE = <?= json_encode([
      'base' => $base,
      'lang' => $fr ? 'fr' : 'en',
      'csrf' => $csrf,
      'required' => $required,
      'jtac' => $jtacCs,
      'jtacDefault' => $defaultCs,
      'mySpecs' => $mySpecs ?? [],
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>
<script src="<?= $h($base) ?>/assets/js/atak-nine-line.js?v=<?= $h($ver('js/atak-nine-line.js')) ?>" defer></script>
</body>
</html>
