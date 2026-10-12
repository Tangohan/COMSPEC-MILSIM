<?php
$base = url('');
$message = trim((string) ($maintenanceMessage ?? ''));
if ($message === '') {
    $message = 'L’accès à la carte est suspendu pour le moment. Un administrateur de votre communauté a activé la maintenance.';
}
$canAccessAdminAtakConfig = !empty($canAccessAdminAtakConfig);
$tenantLabel = trim((string) ($tenantName ?? ''));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Carte indisponible | ATHENA</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@500;600;700;800&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet" />
  <style>
    :root {
      --ink: #07110c;
      --panel: rgba(8, 18, 14, 0.72);
      --line: rgba(110, 231, 183, 0.22);
      --line-strong: rgba(110, 231, 183, 0.45);
      --amber: #f5b942;
      --amber-soft: rgba(245, 185, 66, 0.18);
      --mint: #6ee7b7;
      --mint-dim: #34d399;
      --fog: #c8d5cc;
      --fog-dim: #8fa396;
      --paper: #f4f7f2;
    }

    * { box-sizing: border-box; }
    html, body { margin: 0; min-height: 100%; }
    body {
      min-height: 100vh;
      color: var(--fog);
      font-family: "IBM Plex Sans", sans-serif;
      background: var(--ink);
      overflow-x: hidden;
    }

    .atak-maint {
      position: relative;
      min-height: 100vh;
      display: grid;
      grid-template-rows: auto 1fr auto;
      isolation: isolate;
    }

    /* Fond carte / HUD */
    .atak-maint__sky {
      position: absolute;
      inset: 0;
      z-index: 0;
      background:
        radial-gradient(ellipse 90% 60% at 70% 18%, rgba(52, 211, 153, 0.14), transparent 55%),
        radial-gradient(ellipse 70% 50% at 12% 85%, rgba(245, 185, 66, 0.08), transparent 50%),
        linear-gradient(165deg, #0a1612 0%, #07110c 42%, #050c09 100%);
    }

    .atak-maint__grid {
      position: absolute;
      inset: -10%;
      z-index: 1;
      opacity: 0.35;
      background-image:
        linear-gradient(rgba(110, 231, 183, 0.09) 1px, transparent 1px),
        linear-gradient(90deg, rgba(110, 231, 183, 0.09) 1px, transparent 1px);
      background-size: 56px 56px;
      mask-image: radial-gradient(ellipse 75% 70% at 50% 45%, #000 20%, transparent 75%);
      animation: grid-drift 28s linear infinite;
    }

    .atak-maint__scan {
      position: absolute;
      inset: 0;
      z-index: 2;
      pointer-events: none;
      background: linear-gradient(
        180deg,
        transparent 0%,
        rgba(110, 231, 183, 0.03) 46%,
        rgba(110, 231, 183, 0.12) 50%,
        rgba(110, 231, 183, 0.03) 54%,
        transparent 100%
      );
      background-size: 100% 220%;
      animation: scan-sweep 5.5s ease-in-out infinite;
    }

    .atak-maint__noise {
      position: absolute;
      inset: 0;
      z-index: 3;
      pointer-events: none;
      opacity: 0.07;
      background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
      mix-blend-mode: overlay;
    }

    .atak-maint__vignette {
      position: absolute;
      inset: 0;
      z-index: 4;
      pointer-events: none;
      background: radial-gradient(ellipse at center, transparent 35%, rgba(0, 0, 0, 0.55) 100%);
    }

    /* Coins HUD */
    .atak-maint__frame {
      position: absolute;
      inset: 1rem;
      z-index: 5;
      pointer-events: none;
      border: 1px solid rgba(110, 231, 183, 0.12);
    }
    .atak-maint__frame::before,
    .atak-maint__frame::after,
    .atak-maint__frame span::before,
    .atak-maint__frame span::after {
      content: "";
      position: absolute;
      width: 1.75rem;
      height: 1.75rem;
      border-color: var(--mint);
      border-style: solid;
      opacity: 0.7;
    }
    .atak-maint__frame::before { top: -1px; left: -1px; border-width: 2px 0 0 2px; }
    .atak-maint__frame::after { top: -1px; right: -1px; border-width: 2px 2px 0 0; }
    .atak-maint__frame span::before { bottom: -1px; left: -1px; border-width: 0 0 2px 2px; }
    .atak-maint__frame span::after { bottom: -1px; right: -1px; border-width: 0 2px 2px 0; }
    .atak-maint__frame span { position: absolute; inset: 0; }

    .atak-maint__top,
    .atak-maint__bottom,
    .atak-maint__center {
      position: relative;
      z-index: 10;
    }

    .atak-maint__top {
      display: flex;
      flex-wrap: wrap;
      align-items: flex-start;
      justify-content: space-between;
      gap: 1rem 1.5rem;
      padding: clamp(1.5rem, 4vw, 2.75rem) clamp(1.5rem, 5vw, 3.5rem);
    }

    .atak-maint__brand {
      display: flex;
      flex-direction: column;
      gap: 0.35rem;
    }

    .atak-maint__brand-mark {
      font-family: "Barlow Condensed", sans-serif;
      font-weight: 800;
      font-size: clamp(2.4rem, 7vw, 4.25rem);
      line-height: 0.9;
      letter-spacing: 0.06em;
      color: var(--paper);
      text-transform: uppercase;
      margin: 0;
      animation: brand-in 0.9s cubic-bezier(0.22, 1, 0.36, 1) both;
    }

    .atak-maint__brand-sub {
      font-family: "IBM Plex Mono", monospace;
      font-size: 0.72rem;
      letter-spacing: 0.28em;
      text-transform: uppercase;
      color: var(--mint);
      margin: 0;
      animation: brand-in 0.9s 0.12s cubic-bezier(0.22, 1, 0.36, 1) both;
    }

    .atak-maint__meta {
      font-family: "IBM Plex Mono", monospace;
      font-size: 0.7rem;
      letter-spacing: 0.08em;
      text-align: right;
      color: var(--fog-dim);
      line-height: 1.7;
      animation: brand-in 0.9s 0.2s cubic-bezier(0.22, 1, 0.36, 1) both;
    }
    .atak-maint__meta strong {
      color: var(--amber);
      font-weight: 500;
    }

    .atak-maint__center {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 1rem clamp(1.25rem, 5vw, 3rem) 2rem;
      max-width: 44rem;
      margin: 0 auto;
      width: 100%;
    }

    .atak-maint__radar {
      position: relative;
      width: min(11rem, 42vw);
      height: min(11rem, 42vw);
      margin-bottom: 1.75rem;
      animation: brand-in 1s 0.15s cubic-bezier(0.22, 1, 0.36, 1) both;
    }

    .atak-maint__radar-ring {
      position: absolute;
      inset: 0;
      border-radius: 50%;
      border: 1px solid var(--line);
    }
    .atak-maint__radar-ring--mid {
      inset: 18%;
      border-color: var(--line-strong);
    }
    .atak-maint__radar-ring--core {
      inset: 36%;
      border-color: rgba(245, 185, 66, 0.45);
      background: radial-gradient(circle, var(--amber-soft), transparent 70%);
    }
    .atak-maint__radar-cross::before,
    .atak-maint__radar-cross::after {
      content: "";
      position: absolute;
      background: var(--line);
      left: 50%;
      top: 50%;
    }
    .atak-maint__radar-cross::before {
      width: 1px;
      height: 100%;
      transform: translate(-50%, -50%);
    }
    .atak-maint__radar-cross::after {
      width: 100%;
      height: 1px;
      transform: translate(-50%, -50%);
    }
    .atak-maint__radar-sweep {
      position: absolute;
      inset: 8%;
      border-radius: 50%;
      background: conic-gradient(from 0deg, transparent 0deg, rgba(52, 211, 153, 0.28) 50deg, transparent 70deg);
      animation: radar-spin 3.2s linear infinite;
    }
    .atak-maint__radar-dot {
      position: absolute;
      top: 50%;
      left: 50%;
      width: 0.55rem;
      height: 0.55rem;
      margin: -0.275rem 0 0 -0.275rem;
      border-radius: 50%;
      background: var(--amber);
      box-shadow: 0 0 0 0 rgba(245, 185, 66, 0.55);
      animation: pulse-dot 1.8s ease-out infinite;
    }

    .atak-maint__status {
      display: inline-flex;
      align-items: center;
      gap: 0.55rem;
      padding: 0.4rem 0.85rem;
      margin: 0 0 1rem;
      border: 1px solid rgba(245, 185, 66, 0.4);
      background: var(--amber-soft);
      color: var(--amber);
      font-family: "IBM Plex Mono", monospace;
      font-size: 0.68rem;
      font-weight: 500;
      letter-spacing: 0.22em;
      text-transform: uppercase;
      animation: brand-in 0.85s 0.25s cubic-bezier(0.22, 1, 0.36, 1) both;
    }
    .atak-maint__status-pip {
      width: 0.45rem;
      height: 0.45rem;
      border-radius: 50%;
      background: var(--amber);
      animation: pulse-dot 1.4s ease-out infinite;
    }

    .atak-maint__title {
      margin: 0 0 0.85rem;
      font-family: "Barlow Condensed", sans-serif;
      font-weight: 800;
      font-size: clamp(2rem, 6.5vw, 3.4rem);
      line-height: 0.95;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: var(--paper);
      animation: brand-in 0.85s 0.32s cubic-bezier(0.22, 1, 0.36, 1) both;
    }

    .atak-maint__body {
      margin: 0 0 1.75rem;
      max-width: 34rem;
      font-size: clamp(0.95rem, 2.2vw, 1.05rem);
      line-height: 1.65;
      color: var(--fog);
      white-space: pre-wrap;
      animation: brand-in 0.85s 0.4s cubic-bezier(0.22, 1, 0.36, 1) both;
    }

    .atak-maint__actions {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 0.75rem;
      animation: brand-in 0.85s 0.48s cubic-bezier(0.22, 1, 0.36, 1) both;
    }

    .atak-maint__btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 2.85rem;
      padding: 0.7rem 1.35rem;
      border-radius: 0.2rem;
      font-family: "Barlow Condensed", sans-serif;
      font-size: 1.05rem;
      font-weight: 700;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      text-decoration: none;
      transition: transform 0.2s ease, background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
    }
    .atak-maint__btn:hover { transform: translateY(-1px); }
    .atak-maint__btn:focus-visible {
      outline: 2px solid var(--mint);
      outline-offset: 3px;
    }
    .atak-maint__btn--primary {
      background: var(--paper);
      color: var(--ink);
      border: 1px solid var(--paper);
    }
    .atak-maint__btn--primary:hover {
      background: var(--mint);
      border-color: var(--mint);
    }
    .atak-maint__btn--ghost {
      background: transparent;
      color: var(--fog);
      border: 1px solid var(--line-strong);
    }
    .atak-maint__btn--ghost:hover {
      border-color: var(--mint);
      color: var(--paper);
      background: rgba(110, 231, 183, 0.08);
    }

    .atak-maint__bottom {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem 1.5rem;
      padding: 1rem clamp(1.5rem, 5vw, 3.5rem) clamp(1.5rem, 4vw, 2.5rem);
      font-family: "IBM Plex Mono", monospace;
      font-size: 0.65rem;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: var(--fog-dim);
      border-top: 1px solid rgba(110, 231, 183, 0.12);
      background: linear-gradient(180deg, transparent, rgba(0, 0, 0, 0.25));
    }
    .atak-maint__bottom span { color: var(--mint-dim); }

    @keyframes grid-drift {
      from { transform: translate3d(0, 0, 0); }
      to { transform: translate3d(56px, 56px, 0); }
    }
    @keyframes scan-sweep {
      0%, 100% { background-position: 0 -40%; }
      50% { background-position: 0 140%; }
    }
    @keyframes radar-spin {
      to { transform: rotate(360deg); }
    }
    @keyframes pulse-dot {
      0% { box-shadow: 0 0 0 0 rgba(245, 185, 66, 0.55); opacity: 1; }
      70% { box-shadow: 0 0 0 12px rgba(245, 185, 66, 0); opacity: 0.85; }
      100% { box-shadow: 0 0 0 0 rgba(245, 185, 66, 0); opacity: 1; }
    }
    @keyframes brand-in {
      from { opacity: 0; transform: translateY(14px); }
      to { opacity: 1; transform: translateY(0); }
    }

    @media (prefers-reduced-motion: reduce) {
      .atak-maint__grid,
      .atak-maint__scan,
      .atak-maint__radar-sweep,
      .atak-maint__radar-dot,
      .atak-maint__status-pip,
      .atak-maint__brand-mark,
      .atak-maint__brand-sub,
      .atak-maint__meta,
      .atak-maint__radar,
      .atak-maint__status,
      .atak-maint__title,
      .atak-maint__body,
      .atak-maint__actions {
        animation: none !important;
      }
    }

    @media (max-width: 640px) {
      .atak-maint__meta { text-align: left; }
      .atak-maint__frame { inset: 0.65rem; }
      .atak-maint__bottom { justify-content: center; text-align: center; }
    }
  </style>
</head>
<body>
  <main class="atak-maint" role="main">
    <div class="atak-maint__sky" aria-hidden="true"></div>
    <div class="atak-maint__grid" aria-hidden="true"></div>
    <div class="atak-maint__scan" aria-hidden="true"></div>
    <div class="atak-maint__noise" aria-hidden="true"></div>
    <div class="atak-maint__vignette" aria-hidden="true"></div>
    <div class="atak-maint__frame" aria-hidden="true"><span></span></div>

    <header class="atak-maint__top">
      <div class="atak-maint__brand">
        <p class="atak-maint__brand-mark">Athena</p>
        <p class="atak-maint__brand-sub">Carte tactique · ATAK</p>
      </div>
      <div class="atak-maint__meta">
        <div>Canal · <strong>hors service</strong></div>
        <?php if ($tenantLabel !== ''): ?>
          <div><?= htmlspecialchars($tenantLabel, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <div id="atak-maint-clock">—</div>
      </div>
    </header>

    <section class="atak-maint__center" aria-labelledby="atak-maint-title">
      <div class="atak-maint__radar" aria-hidden="true">
        <div class="atak-maint__radar-ring"></div>
        <div class="atak-maint__radar-ring atak-maint__radar-ring--mid"></div>
        <div class="atak-maint__radar-ring atak-maint__radar-ring--core"></div>
        <div class="atak-maint__radar-cross"></div>
        <div class="atak-maint__radar-sweep"></div>
        <div class="atak-maint__radar-dot"></div>
      </div>

      <p class="atak-maint__status">
        <span class="atak-maint__status-pip" aria-hidden="true"></span>
        Signal carte interrompu
      </p>
      <h1 id="atak-maint-title" class="atak-maint__title">Carte indisponible</h1>
      <p class="atak-maint__body"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>

      <div class="atak-maint__actions">
        <a class="atak-maint__btn atak-maint__btn--primary" href="<?= htmlspecialchars(url('dashboard'), ENT_QUOTES, 'UTF-8') ?>">Retour au tableau de bord</a>
        <?php if ($canAccessAdminAtakConfig): ?>
          <a class="atak-maint__btn atak-maint__btn--ghost" href="<?= htmlspecialchars(url('admin/atak-config'), ENT_QUOTES, 'UTF-8') ?>">Gérer la maintenance</a>
        <?php endif; ?>
      </div>
    </section>

    <footer class="atak-maint__bottom">
      <div>Statut · <span>maintenance communauté</span></div>
      <div>La carte redeviendra accessible dès que la maintenance sera levée</div>
    </footer>
  </main>
  <script>
    (function () {
      var el = document.getElementById('atak-maint-clock');
      if (!el) return;
      function tick() {
        var d = new Date();
        var pad = function (n) { return String(n).padStart(2, '0'); };
        el.textContent = 'Zulu · ' + pad(d.getUTCHours()) + ':' + pad(d.getUTCMinutes()) + ':' + pad(d.getUTCSeconds());
      }
      tick();
      setInterval(tick, 1000);
    })();
  </script>
</body>
</html>
