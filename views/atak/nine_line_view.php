<?php
/** @var string $apiBase */
/** @var string $assetVer */
/** @var string $lang */
$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string) ($apiBase ?? url('')), '/');
$ver = rawurlencode((string) ($assetVer ?? '1'));
$jsM = @filemtime(dirname(__DIR__, 2) . '/public/assets/js/atak-nine-line.js');
if ($jsM) {
    $ver .= '.' . (int) $jsM;
}
$fr = ($lang ?? 'en') === 'fr';
?>
<!DOCTYPE html>
<html lang="<?= $fr ? 'fr' : 'en' ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#070a0e">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <title><?= $h($title ?? '9-LINE') ?></title>
  <style>
    :root { --bg: #070a0e; --panel: #10161d; --line: #1f2a33; --txt: #dfe7e3; --mute: #8a9a93; --acc: #4dd9ff; --ok: #5cc76b; --warn: #f2ab33; --red: #e5483a; }
    * { box-sizing: border-box; }
    html, body { margin: 0; background: var(--bg); color: var(--txt); font: 15px/1.4 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
    body { padding: max(12px, env(safe-area-inset-top)) 14px max(16px, env(safe-area-inset-bottom)); max-width: 980px; margin: 0 auto; }
    header { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 12px; }
    header b { letter-spacing: .14em; font-size: 13px; color: var(--acc); }
    header span { font-size: 12px; color: var(--mute); }
    h2 { font-size: 12px; letter-spacing: .12em; text-transform: uppercase; color: var(--mute); margin: 20px 2px 8px; font-weight: 600; }
    .layout { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); gap: 16px; }
    @media (max-width: 760px) { .layout { grid-template-columns: minmax(0, 1fr); } }
    .card { background: var(--panel); border: 1px solid var(--line); border-radius: 14px; padding: 14px 16px; }
    .meta { color: var(--mute); font-size: 13px; margin-bottom: 10px; overflow-wrap: anywhere; }
    .nl { list-style: none; margin: 0; padding: 0; }
    .nl li { display: grid; grid-template-columns: 30px minmax(0, 9.5em) minmax(0, 1fr); gap: 10px; align-items: baseline; padding: 9px 0; border-bottom: 1px solid var(--line); }
    .nl li:last-child { border-bottom: 0; }
    .nl i { font-style: normal; font-weight: 700; color: #07141a; background: var(--acc); border-radius: 6px; text-align: center; font-size: 14px; padding: 1px 0; }
    .nl em { font-style: normal; color: var(--mute); font-size: 13px; text-transform: uppercase; letter-spacing: .06em; }
    .nl b { font: 600 clamp(16px, 2.4vw, 22px)/1.25 ui-monospace, "SFMono-Regular", Consolas, monospace; overflow-wrap: anywhere; }
    .badge { display: inline-block; border-radius: 999px; padding: 2px 9px; font-size: 12px; border: 1px solid var(--line); margin-left: 6px; }
    .badge.active { border-color: var(--ok); color: var(--ok); }
    .hist { list-style: none; margin: 0; padding: 0; }
    .hist button { width: 100%; text-align: left; background: var(--panel); color: var(--txt); border: 1px solid var(--line); border-radius: 10px; padding: 9px 12px; margin-bottom: 6px; font: inherit; cursor: pointer; }
    .hist button.on { border-color: var(--acc); }
    .hist small { color: var(--mute); display: block; }
    label { display: block; font-size: 12px; color: var(--mute); margin: 8px 0 4px; text-transform: uppercase; letter-spacing: .06em; }
    input { width: 100%; background: #0b1117; color: var(--txt); border: 1px solid var(--line); border-radius: 10px; padding: 10px 12px; font: 600 17px ui-monospace, Consolas, monospace; }
    .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .out { margin-top: 12px; font: 600 17px/1.6 ui-monospace, Consolas, monospace; }
    .out span { color: var(--mute); font: 13px system-ui, sans-serif; }
    .hint { color: var(--mute); font-size: 13px; margin: 8px 2px; }
    .empty { text-align: center; padding: 28px 12px; color: var(--mute); }
  </style>
</head>
<body>
  <header><b>COMSPEC · JTAC · 9-LINE</b><span id="nl-live"><?= $fr ? 'connexion…' : 'connecting…' ?></span></header>
  <div class="layout">
    <section>
      <div class="card" id="nl-main"><p class="empty"><?= $fr ? 'Chargement…' : 'Loading…' ?></p></div>
      <h2><?= $fr ? 'Précédents' : 'Previous' ?></h2>
      <ul class="hist" id="nl-hist"></ul>
    </section>
    <section>
      <div class="card">
        <h2 style="margin-top:0"><?= $fr ? 'Calculateur IP → cible' : 'IP → target calculator' ?></h2>
        <label for="c-ip"><?= $fr ? 'Grille de l\'IP' : 'IP grid' ?></label>
        <input id="c-ip" inputmode="numeric" autocomplete="off" placeholder="012 345">
        <label for="c-tg"><?= $fr ? 'Grille de la cible' : 'Target grid' ?></label>
        <input id="c-tg" inputmode="numeric" autocomplete="off" placeholder="0234 5678">
        <div class="row2">
          <div><label for="c-decl"><?= $fr ? 'Déclinaison (° est)' : 'Declination (° east)' ?></label><input id="c-decl" inputmode="decimal" value="0"></div>
          <div><label for="c-alt"><?= $fr ? 'Altitude cible (m)' : 'Target elevation (m)' ?></label><input id="c-alt" inputmode="decimal" placeholder="—"></div>
        </div>
        <div class="out" id="c-out"></div>
        <p class="hint"><?= $fr
            ? 'Grilles de 4 à 10 chiffres, avec ou sans espaces ni tirets. Cartes à grille standard (origine en bas à gauche, nord en haut). Les cartes Arma n\'ont pas de déclinaison : 0, sauf consigne de votre mission.'
            : 'Grids of 4 to 10 digits, with or without spaces or dashes. Standard-grid maps (origin bottom left, north up). Arma maps have no declination: 0 unless your mission says otherwise.' ?></p>
      </div>
    </section>
  </div>
  <script>
    window.NINE_LINE = <?= json_encode(['base' => $base, 'lang' => $fr ? 'fr' : 'en'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  </script>
  <script src="<?= $h($base) ?>/assets/js/atak-nine-line.js?v=<?= $h($ver) ?>"></script>
</body>
</html>
