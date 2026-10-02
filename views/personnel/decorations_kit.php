<?php

declare(strict_types=1);

use App\Support\DecorationCatalog;

$tenantId = isset($decorationTenantId) ? (int) $decorationTenantId : null;
$ribbons = DecorationCatalog::ribbons($tenantId);
$medals = DecorationCatalog::medals($tenantId);
$all = DecorationCatalog::all($tenantId);
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

$demoRackIds = [
    'rbn_service_distingue',
    'rbn_merite',
    'rbn_unite_citee',
    'rbn_action_combat',
    'rbn_service_multinational_nato',
    'rbn_honneur_pourpre',
    'rbn_sauvetage',
    'rbn_reconnaissance',
];
$demoItems = [];
foreach ($demoRackIds as $id) {
    $hit = DecorationCatalog::find($id, $tenantId);
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
<title>Rubans et médailles — modèles</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= htmlspecialchars($kitCss, ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="dk-kit dk-kit-page">

<div class="dk-hero">
  <div class="dk-eyebrow">Dossier personnel</div>
  <h1>Rubans, médailles et placards</h1>
  <div class="dk-caution">
    <?= htmlspecialchars(DecorationCatalog::CAUTION, ENT_QUOTES, 'UTF-8') ?>
  </div>
</div>

<div class="dk-wrap">

  <section class="dk-section">
    <div class="dk-section-head"><h2>Rubans</h2><span class="dk-tag"><?= count($ribbons) ?> modèles</span></div>
    <p class="dk-section-desc">Motifs proposés pour le placard du dossier. Chaque ruban peut être choisi lors de l’édition d’une fiche.</p>

    <div class="dk-ribbon-grid">
      <?php foreach ($ribbons as $ribbon):
          $imageUrl = DecorationCatalog::imageUrl($ribbon);
          $swatchClass = $imageUrl !== null ? 'dk-ribbon-swatch dk-rb-image' : 'dk-ribbon-swatch ' . $ribbon['patternClass'];
          $swatchStyle = $imageUrl !== null
              ? ' style="background-image:url(\'' . htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') . '\')"'
              : '';
          ?>
      <div class="dk-ribbon-card">
        <div class="<?= htmlspecialchars($swatchClass, ENT_QUOTES, 'UTF-8') ?>"<?= $swatchStyle ?>></div>
        <div class="dk-ribbon-name"><?= htmlspecialchars($ribbon['name'], ENT_QUOTES, 'UTF-8') ?></div>
        <span class="dk-fid"><?= htmlspecialchars(DecorationCatalog::familyLabel($ribbon['family']), ENT_QUOTES, 'UTF-8') ?></span>
        <div class="dk-ribbon-desc"><?= htmlspecialchars($ribbon['description'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php if ($imageUrl === null): ?>
        <div class="dk-ribbon-hex">
          <?php foreach ($ribbon['colors'] as $hex):
              $hex = (string) $hex;
              $light = $hexIsLight($hex);
              ?>
          <span class="dk-hexdot<?= $light ? ' dk-hexdot--light' : '' ?>" style="background:<?= htmlspecialchars($hex, ENT_QUOTES, 'UTF-8') ?>"></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="dk-section">
    <div class="dk-section-head"><h2>Médailles</h2><span class="dk-tag"><?= count($medals) ?> modèles</span></div>
    <p class="dk-section-desc">Présentation détaillée avec ruban et disque. Deux tailles selon la fiche ou la carte.</p>

    <div class="dk-medal-grid">
      <?php foreach ($medals as $dkMedal):
          $dkShowSmall = true;
          require base_path('views/partials/personnel/decoration_medal.php');
      endforeach; ?>
    </div>
  </section>

  <section class="dk-section">
    <div class="dk-section-head"><h2>Exemple de présentation</h2><span class="dk-tag">aperçu</span></div>
    <div class="dk-single-display">
      <?php
      $example = DecorationCatalog::find('med_croix_merite_or', $tenantId) ?? $medals[1] ?? $medals[0];
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
        <div class="dk-m-fam" style="margin-top:4px;"><?= htmlspecialchars(DecorationCatalog::detailLine($example), ENT_QUOTES, 'UTF-8') ?> · attribuée le 18/09/2026</div>
      </div>
    </div>
  </section>

  <section class="dk-section">
    <div class="dk-section-head"><h2>Rack de rubans</h2><span class="dk-tag"><?= count($demoItems) ?> décorations</span></div>
    <p class="dk-section-desc">Trois rubans par ligne. Survolez ou sélectionnez un ruban pour voir le détail.</p>
    <?php
    $dkItems = $demoItems;
    $dkShowDemoDevices = true;
    $dkShowDetail = false;
    $dkCaption = 'Survolez un ruban — cliquez pour l’état « sélectionné » (déjà appliqué au 1er ruban)';
    require base_path('views/partials/personnel/decoration_rack.php');
    ?>
  </section>

  <footer class="dk-refs">
    <?= htmlspecialchars(DecorationCatalog::FOOTER, ENT_QUOTES, 'UTF-8') ?>
  </footer>

</div>
<script src="<?= htmlspecialchars($kitJs, ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
