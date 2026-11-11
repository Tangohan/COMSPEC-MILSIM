<?php
/** Liste des plans d'opération (/atak/toc/plans). */
$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$a = static fn (string $p): string => function_exists('asset_url') ? asset_url($p) : url($p);
?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
<?php require __DIR__ . '/_head.php'; ?>
<script src="<?= $h($a('assets/js/toc/toc-plans.js')) ?>" defer></script>
</head>
<body class="toc-body" style="overflow:auto">
<div class="plans-page">
  <header class="toc-top">
    <div class="toc-post"><h1>Plans d’opération</h1><p>Préparer sur la carte, briefer par phases, envoyer en jeu</p></div>
    <div></div>
    <div class="toc-actions">
      <button type="button" class="toc-btn is-primary" data-new>Nouveau plan <kbd>N</kbd></button>
      <label class="toc-btn" for="plans-import" title="Fichier .json exporté d’un plan Athena">Importer un fichier</label>
      <input type="file" id="plans-import" accept="application/json,.json" class="toc-sr">
      <a class="toc-btn" href="<?= $h(url('atak/toc')) ?>">Poste TOC</a>
      <a class="toc-btn" href="<?= $h(url('atak')) ?>">Fermer</a>
    </div>
  </header>
  <main class="plans-wrap">
    <p class="toc-status-msg" id="plans-msg" role="status" aria-live="polite" style="min-height:24px;margin:8px 0 0"></p>
    <div class="toc-sec"><h2>Mes plans et plans partagés</h2><span id="plans-count"></span></div>
    <div class="plans-grid" id="plans-list" style="padding:0"></div>
    <div class="toc-sec" style="margin-top:24px"><h2>Modèles de la communauté</h2><span id="plans-tcount"></span></div>
    <div class="plans-grid" id="plans-templates" style="padding:0"></div>
  </main>
</div>
<div class="toc-overlay" id="plans-new" hidden role="dialog" aria-modal="true" aria-labelledby="plans-new-title">
  <form class="toc-dialog" id="plans-new-form">
    <div class="toc-sec"><h2 id="plans-new-title">Nouveau plan</h2><span><button type="button" class="toc-btn is-small" data-close>Fermer</button></span></div>
    <div class="toc-form">
      <label>Titre<input class="toc-input" id="new-title" maxlength="120" placeholder="Opération RAVEN, phase d’assaut…" required></label>
      <div class="toc-row" style="--cols:3">
        <label>Terrain<select class="toc-select" id="new-terrain"></select></label>
        <label>Camp<select class="toc-select" id="new-side"><option value="WEST">BLUFOR</option><option value="EAST">OPFOR</option><option value="GUER">INDFOR</option></select></label>
        <label>Départ<select class="toc-select" id="new-template"></select></label>
      </div>
      <label><span><input type="checkbox" id="new-is-template"> Modèle de la communauté (réutilisable par tous)</span></label>
      <button type="submit" class="toc-btn is-primary is-block">Créer et ouvrir</button>
    </div>
  </form>
</div>
</body>
</html>
