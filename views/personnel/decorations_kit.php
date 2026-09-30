<?php

declare(strict_types=1);

use App\Support\DecorationCatalog;

$ribbons = DecorationCatalog::ribbons();
$medals = DecorationCatalog::medals();
$all = DecorationCatalog::all();
$kitCss = asset_url('assets/css/decorations-kit.css');
$kitJs = asset_url('assets/js/decorations-rack.js');

$hexIsLight = static function (string $hex): bool {
    $h = ltrim($hex, '#');
    if (strlen($h) === 3) {
        $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
    }
    if (strlen($h) !== 6) {
        return false;
    }
    $r = hexdec(substr($h, 0, 2));
    $g = hexdec(substr($h, 2, 2));
    $b = hexdec(substr($h, 4, 2));

    return (($r * 299) + ($g * 587) + ($b * 114)) / 1000 > 200;
};

$jsonEscape = static function (string $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '""', ENT_QUOTES, 'UTF-8');
};

$demoRackIds = [
    'rbn_service_distingue',
    'rbn_merite',
    'rbn_unite_citee',
    'rbn_action_combat',
    'rbn_service_multinational_nato',
    'rbn_conduite_service',
    'rbn_qualification_speciale',
    'rbn_anciennete_service',
];
$demoItems = [];
foreach ($demoRackIds as $id) {
    $hit = DecorationCatalog::find($id);
    if ($hit !== null) {
        $demoItems[] = $hit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Pack UI — Rubans &amp; médailles (v2)</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= htmlspecialchars($kitCss, ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="dk-kit dk-kit-page">

<div class="dk-hero">
  <div class="dk-eyebrow">ATHENA · UI KIT · v2</div>
  <h1>Rubans, médailles &amp; frise de carrière</h1>
  <div class="dk-caution">
    <?= htmlspecialchars(DecorationCatalog::CAUTION, ENT_QUOTES, 'UTF-8') ?>
  </div>
</div>

<div class="dk-wrap">

  <section class="dk-section">
    <div class="dk-section-head"><h2>A. Rubans</h2><span class="dk-tag"><?= count($ribbons) ?> modèles</span></div>
    <p class="dk-section-desc">Chaque ruban est un motif CSS pur (pas d’image), redimensionnable sans perte. Dimension recommandée : 46–52px × 16–18px en rack, ×2 en fiche détaillée.</p>

    <div class="dk-ribbon-grid">
      <?php foreach ($ribbons as $ribbon):
          $fidClass = ($ribbon['family'] === 'NATO_INSPIRED') ? 'dk-fid--nato' : 'dk-fid--generic';
          ?>
      <div class="dk-ribbon-card">
        <div class="dk-ribbon-swatch <?= htmlspecialchars($ribbon['patternClass'], ENT_QUOTES, 'UTF-8') ?>"></div>
        <div class="dk-ribbon-name"><?= htmlspecialchars($ribbon['name'], ENT_QUOTES, 'UTF-8') ?></div>
        <div class="dk-ribbon-id"><?= htmlspecialchars($ribbon['id'], ENT_QUOTES, 'UTF-8') ?></div>
        <span class="dk-fid <?= $fidClass ?>"><?= htmlspecialchars($ribbon['family'], ENT_QUOTES, 'UTF-8') ?></span>
        <div class="dk-ribbon-desc"><?= htmlspecialchars($ribbon['description'], ENT_QUOTES, 'UTF-8') ?></div>
        <div class="dk-ribbon-hex">
          <?php foreach ($ribbon['colors'] as $hex):
              $hex = (string) $hex;
              $light = $hexIsLight($hex);
              ?>
          <span class="dk-hexdot<?= $light ? ' dk-hexdot--light' : '' ?>" style="background:<?= htmlspecialchars($hex, ENT_QUOTES, 'UTF-8') ?>"></span>
          <?php endforeach; ?>
        </div>
        <div class="dk-ribbon-dim"><?= (int) $ribbon['cardWidthPx'] ?>×<?= (int) $ribbon['cardHeightPx'] ?>px</div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="dk-section">
    <div class="dk-section-head"><h2>B. Médailles</h2><span class="dk-tag"><?= count($medals) ?> modèles</span></div>
    <p class="dk-section-desc">Disque métallique + bordure + relief + ombre douce + bélière + ruban séparé. Deux tailles : carte UI (34px) et fiche détaillée (62px), construites sur le même disque.</p>

    <div class="dk-medal-grid">
      <?php foreach ($medals as $dkMedal):
          $dkShowSmall = true;
          require base_path('views/partials/personnel/decoration_medal.php');
      endforeach; ?>
    </div>
  </section>

  <section class="dk-section">
    <div class="dk-section-head"><h2>Affichage médaille + ruban</h2><span class="dk-tag">exemple</span></div>
    <div class="dk-single-display">
      <?php
      $example = DecorationCatalog::find('med_croix_merite_or') ?? $medals[1] ?? $medals[0];
      ?>
      <div>
        <div class="dk-bel"></div>
        <div class="dk-m-neck"></div>
        <div class="dk-m-ribbon <?= htmlspecialchars((string) ($example['dropClass'] ?? 'dk-drop-gold'), ENT_QUOTES, 'UTF-8') ?>"></div>
        <div class="dk-m-disc <?= htmlspecialchars((string) ($example['discClass'] ?? 'dk-disc-gold'), ENT_QUOTES, 'UTF-8') ?>">
          <?= DecorationCatalog::glyphSvg((string) ($example['glyph'] ?? 'cross')) ?>
        </div>
      </div>
      <div>
        <div class="dk-m-name" style="font-size:15px;">Croix du mérite — échelon or</div>
        <div class="dk-m-fam" style="margin-top:4px;">GENERIC · med_croix_merite_or · attribuée le 18/09/2026</div>
      </div>
    </div>
  </section>

  <section class="dk-section">
    <div class="dk-section-head"><h2>C. Rack de rubans</h2><span class="dk-tag"><?= count($demoItems) ?> décorations, ordre protocolaire</span></div>
    <p class="dk-section-desc">3 rubans par ligne, tri configurable de gauche à droite / haut en bas. États hover (survol) et sélectionné. Dispositifs (étoile, chiffre, feuille de chêne) en overlay, génériques.</p>
    <?php
    $dkItems = $demoItems;
    $dkShowDemoDevices = true;
    $dkShowDetail = false;
    $dkCaption = 'Survolez un ruban — cliquez pour l’état « sélectionné » (déjà appliqué au 1er ruban)';
    require base_path('views/partials/personnel/decoration_rack.php');
    ?>
  </section>

  <section class="dk-section">
    <div class="dk-section-head"><h2>D. Table de données</h2><span class="dk-tag"><?= count($all) ?> entrées</span></div>
    <div class="dk-code-panel"><pre>[<?php foreach ($all as $i => $row): ?>

  {
    <span class="dk-k">id</span>: <span class="dk-s"><?= $jsonEscape($row['id']) ?></span>,
    <span class="dk-k">name</span>: <span class="dk-s"><?= $jsonEscape($row['name']) ?></span>,
    <span class="dk-k">family</span>: <span class="dk-s"><?= $jsonEscape($row['family']) ?></span>,
    <span class="dk-k">type</span>: <span class="dk-s"><?= $jsonEscape($row['type']) ?></span>,
    <span class="dk-k">level</span>: <span class="dk-s"><?= $jsonEscape($row['level']) ?></span>,
    <span class="dk-k">colors</span>: [<?php
        $cols = [];
        foreach ($row['colors'] as $c) {
            $cols[] = '<span class="dk-s">' . $jsonEscape((string) $c) . '</span>';
        }
        echo implode(', ', $cols);
        ?>],
    <span class="dk-k">pattern</span>: <span class="dk-s"><?= $jsonEscape($row['pattern']) ?></span>,
    <span class="dk-k">description</span>: <span class="dk-s"><?= $jsonEscape($row['description']) ?></span>,
    <span class="dk-k">referenceUrl</span>: <span class="dk-s"><?= $jsonEscape($row['referenceUrl']) ?></span>,
    <span class="dk-k">isOfficialReference</span>: <span class="dk-k">false</span>
  }<?= $i < count($all) - 1 ? ',' : '' ?><?php endforeach; ?>

]</pre></div>
  </section>

  <footer class="dk-refs">
    <?= htmlspecialchars(DecorationCatalog::FOOTER, ENT_QUOTES, 'UTF-8') ?>
  </footer>

</div>
<script src="<?= htmlspecialchars($kitJs, ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
