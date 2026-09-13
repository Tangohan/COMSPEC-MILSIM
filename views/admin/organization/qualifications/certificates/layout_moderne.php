<?php
/** @var string $holder_name */
/** @var string $certificate_number */
/** @var string|null $badge_path */
/** @var string $category_name */
/** @var string $qualification_name */
/** @var string $level_name */
/** @var string $issuer_name */
/** @var string $obtained_at */
/** @var string $expires_at */
/** @var string $temporal_label */
/** @var string $generated_at */
/** @var string $primary_hex */
/** @var string $accent_hex */

$primary = htmlspecialchars($primary_hex ?? '#0f172a');
$accent = htmlspecialchars($accent_hex ?? '#059669');
$cat = strtoupper(htmlspecialchars($category_name !== '' ? $category_name : 'QUALIFICATION'));
$badgeSrc = $badge_path && is_file($badge_path)
    ? 'data:' . (str_ends_with(strtolower($badge_path), '.svg') ? 'image/svg+xml' : 'image/png') . ';base64,' . base64_encode((string) file_get_contents($badge_path))
    : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  @page { margin: 0; }
  body { font-family: DejaVu Sans, sans-serif; color: <?= $primary ?>; margin: 0; font-size: 12px; }
  .top { background: <?= $primary ?>; color: #f8fafc; padding: 36px 40px 28px; }
  .brand { font-size: 28px; font-weight: 700; letter-spacing: 0.18em; }
  .sub { margin-top: 6px; font-size: 10px; letter-spacing: 0.22em; opacity: 0.8; text-transform: uppercase; }
  .body { padding: 36px 40px; }
  .row { width: 100%; }
  .row td { vertical-align: top; }
  .cat { display: inline-block; background: <?= $accent ?>; color: #fff; padding: 6px 14px; font-size: 10px; letter-spacing: 0.18em; font-weight: 700; }
  .badge { text-align: right; }
  .badge img { width: 88px; height: 88px; }
  .badge-ph { width: 88px; height: 88px; border: 1px dashed #94a3b8; display: inline-block; text-align: center; line-height: 88px; color: #94a3b8; font-size: 10px; }
  h1 { font-size: 13px; letter-spacing: 0.28em; color: #64748b; margin: 36px 0 10px; }
  .qual { font-size: 26px; font-weight: 700; margin: 0 0 6px; }
  .level { color: #64748b; margin-bottom: 28px; }
  .awarded { color: #64748b; margin-bottom: 6px; }
  .holder { font-size: 24px; font-weight: 700; margin-bottom: 36px; border-bottom: 2px solid <?= $accent ?>; display: inline-block; padding-bottom: 6px; }
  .grid { width: 100%; border-collapse: collapse; margin-top: 12px; }
  .grid td { width: 50%; padding: 14px 12px; border-top: 1px solid #e2e8f0; }
  .lbl { font-size: 9px; letter-spacing: 0.16em; color: #64748b; text-transform: uppercase; }
  .val { font-size: 14px; font-weight: 700; margin-top: 6px; }
  .gen { margin-top: 48px; font-size: 9px; color: #94a3b8; text-align: center; }
</style>
</head>
<body>
  <div class="top">
    <div class="brand">ATHENA</div>
    <div class="sub">Plateforme de gestion d’unité</div>
  </div>
  <div class="body">
    <table class="row">
      <tr>
        <td><span class="cat"><?= $cat ?></span></td>
        <td class="badge">
          <?php if ($badgeSrc !== ''): ?>
            <img src="<?= $badgeSrc ?>" alt="Insigne">
          <?php else: ?>
            <div class="badge-ph">INSIGNE</div>
          <?php endif; ?>
        </td>
      </tr>
    </table>
    <h1>CERTIFICAT DE QUALIFICATION</h1>
    <div class="qual"><?= htmlspecialchars($qualification_name) ?></div>
    <div class="level"><?= htmlspecialchars($level_name !== '' ? $level_name : '—') ?></div>
    <div class="awarded">Décerné à</div>
    <div class="holder"><?= htmlspecialchars($holder_name) ?></div>
    <table class="grid">
      <tr>
        <td>
          <div class="lbl">Organisme émetteur</div>
          <div class="val"><?= htmlspecialchars($issuer_name !== '' ? $issuer_name : '—') ?></div>
        </td>
        <td>
          <div class="lbl">Date d’obtention</div>
          <div class="val"><?= htmlspecialchars($obtained_at) ?></div>
        </td>
      </tr>
      <tr>
        <td>
          <div class="lbl">Validité</div>
          <div class="val"><?= htmlspecialchars($expires_at) ?></div>
        </td>
        <td>
          <div class="lbl">N° de brevet</div>
          <div class="val"><?= htmlspecialchars($certificate_number) ?></div>
        </td>
      </tr>
      <tr>
        <td colspan="2">
          <div class="lbl">Statut</div>
          <div class="val"><?= htmlspecialchars($temporal_label) ?></div>
        </td>
      </tr>
    </table>
    <div class="gen">Généré par ATHENA le <?= htmlspecialchars($generated_at) ?></div>
  </div>
</body>
</html>
