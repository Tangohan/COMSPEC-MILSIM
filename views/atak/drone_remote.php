<?php
/** @var int $mapId */
/** @var string $steamUid */
/** @var string $apiBase */
/** @var string $assetVer */
$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string) ($apiBase ?? url('')), '/');
$ver = rawurlencode((string) ($assetVer ?? '1'));
$jsM = @filemtime(dirname(__DIR__, 2) . '/public/assets/js/atak-drone-remote.js');
if ($jsM) {
    $ver .= '.' . (int) $jsM;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1">
  <meta name="theme-color" content="#070a0e">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="csrf-token" content="<?= $h(\App\Core\Csrf::token()) ?>">
  <title><?= $h($title ?? 'Télécommande drone') ?></title>
  <style>
    :root { --bg: #070a0e; --panel: #10161d; --line: #1f2a33; --txt: #dfe7e3; --mute: #8a9a93; --acc: #4da3ff; --ok: #5cc76b; --warn: #f2ab33; --red: #e5483a; }
    * { box-sizing: border-box; }
    html, body { margin: 0; background: var(--bg); color: var(--txt); font: 15px/1.35 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; -webkit-tap-highlight-color: transparent; }
    body { padding: max(12px, env(safe-area-inset-top)) 12px max(16px, env(safe-area-inset-bottom)); max-width: 560px; margin: 0 auto; }
    header { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 10px; }
    header b { letter-spacing: .14em; font-size: 13px; color: var(--acc); }
    header span { font-size: 12px; color: var(--mute); }
    h2 { font-size: 12px; letter-spacing: .12em; text-transform: uppercase; color: var(--mute); margin: 18px 2px 8px; font-weight: 600; }
    .chips { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 4px; }
    .chip { flex: 0 0 auto; border: 1px solid var(--line); background: var(--panel); color: var(--txt); border-radius: 999px; padding: 8px 14px; font-size: 14px; }
    .chip.on { border-color: var(--acc); color: #fff; background: #12304d; }
    .chip small { color: var(--mute); margin-left: 6px; }
    .card { background: var(--panel); border: 1px solid var(--line); border-radius: 14px; padding: 12px 14px; }
    .card .name { font-weight: 700; font-size: 17px; }
    .card .meta { color: var(--mute); font-size: 13px; margin-top: 4px; }
    .gauge { height: 6px; border-radius: 3px; background: #1b242c; margin-top: 10px; overflow: hidden; }
    .gauge i { display: block; height: 100%; background: var(--ok); }
    .gauge.mid i { background: var(--warn); } .gauge.low i { background: var(--red); }
    .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
    .grid.two { grid-template-columns: repeat(2, 1fr); }
    button.b { min-height: 58px; border: 1px solid var(--line); background: #151d25; color: var(--txt); border-radius: 12px; font: 600 14px/1.15 inherit; padding: 6px; touch-action: manipulation; }
    button.b:active { transform: scale(.97); background: #1b2731; }
    button.b.main { background: #12304d; border-color: var(--acc); }
    button.b.red { background: #3a1512; border-color: var(--red); }
    button.b.on { border-color: var(--ok); color: #fff; }
    button.b[disabled] { opacity: .45; }
    .presets { display: flex; gap: 6px; flex-wrap: wrap; }
    .presets button.b { min-height: 44px; flex: 1 1 18%; }
    .log { list-style: none; margin: 0; padding: 0; font-size: 13px; }
    .log li { padding: 7px 0; border-bottom: 1px solid var(--line); display: flex; gap: 8px; }
    .log li em { font-style: normal; color: var(--mute); min-width: 56px; }
    .log .done { color: var(--ok); } .log .failed, .log .expired { color: var(--red); } .log .pending, .log .accepted { color: var(--warn); }
    .hint { color: var(--mute); font-size: 13px; margin: 8px 2px; }
    .toast { position: fixed; left: 12px; right: 12px; bottom: max(14px, env(safe-area-inset-bottom)); background: #1d2731; border: 1px solid var(--line); border-radius: 12px; padding: 12px 14px; font-size: 14px; opacity: 0; transition: opacity .2s; pointer-events: none; }
    .toast.show { opacity: 1; }
    .empty { text-align: center; padding: 28px 12px; color: var(--mute); }
  </style>
</head>
<body>
  <header><b>COMSPEC · DRONE</b><span id="dr-live">connexion…</span></header>
  <div id="dr-app"><p class="empty">Chargement…</p></div>
  <div class="toast" id="dr-toast" role="status" aria-live="polite"></div>
  <script>
    window.DRONE_REMOTE = <?= json_encode([
        'base' => $base,
        'mapId' => (int) ($mapId ?? 1),
        'steamUid' => (string) ($steamUid ?? ''),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  </script>
  <script src="<?= $h($base) ?>/assets/js/atak-drone-remote.js?v=<?= $h($ver) ?>"></script>
</body>
</html>
