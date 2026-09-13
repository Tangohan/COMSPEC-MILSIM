<?php
/** @var array $award */
/** @var string $holder_name */
/** @var string $certificate_number */
/** @var string|null $badge_path */
/** @var string $category_name */
/** @var string $qualification_name */
/** @var string $level_name */
/** @var string $issuer_name */
/** @var string $obtained_at */
/** @var string $expires_at */
/** @var string $generated_at */
/** @var string $primary_hex */
/** @var string $accent_hex */

$primary = htmlspecialchars($primary_hex ?? '#0f172a');
$accent = htmlspecialchars($accent_hex ?? '#334155');
$cat = strtoupper(htmlspecialchars($category_name !== '' ? $category_name : 'QUALIFICATION'));
$qualLine = htmlspecialchars($qualification_name);
if ($level_name !== '') {
    $qualLine .= ' — ' . htmlspecialchars($level_name);
}
$badgeSrc = $badge_path && is_file($badge_path)
    ? 'data:' . (str_ends_with(strtolower($badge_path), '.svg') ? 'image/svg+xml' : 'image/png') . ';base64,' . base64_encode((string) file_get_contents($badge_path))
    : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  @page { margin: 28px; }
  body { font-family: DejaVu Sans, sans-serif; color: <?= $primary ?>; margin: 0; font-size: 12px; }
  .frame { border: 2px solid <?= $accent ?>; padding: 28px 32px; min-height: 900px; }
  .hdr { text-align: center; letter-spacing: 0.28em; font-size: 11px; font-weight: 700; color: <?= $accent ?>; }
  .cat { margin-top: 18px; text-align: center; }
  .cat span { display: inline-block; border: 1px solid <?= $accent ?>; padding: 4px 14px; font-size: 10px; letter-spacing: 0.2em; }
  .badge { text-align: center; margin: 28px 0 18px; }
  .badge img { width: 96px; height: 96px; }
  .badge-ph { width: 96px; height: 96px; border: 1px dashed #94a3b8; margin: 0 auto; line-height: 96px; color: #94a3b8; font-size: 10px; }
  h1 { text-align: center; font-size: 22px; letter-spacing: 0.12em; margin: 12px 0 28px; }
  .certify { text-align: center; color: #475569; margin-bottom: 8px; }
  .holder { text-align: center; font-size: 26px; font-weight: 700; margin: 8px 0 18px; }
  .obtained { text-align: center; color: #475569; margin-bottom: 6px; }
  .qual { text-align: center; font-size: 16px; font-weight: 700; margin-bottom: 36px; }
  .meta { width: 100%; border-collapse: collapse; margin-top: 24px; }
  .meta td { width: 33%; text-align: center; vertical-align: top; padding: 8px; }
  .meta .lbl { font-size: 9px; letter-spacing: 0.16em; color: #64748b; text-transform: uppercase; }
  .meta .val { font-size: 13px; font-weight: 700; margin-top: 6px; }
  .foot { margin-top: 64px; width: 100%; }
  .foot td { width: 50%; vertical-align: top; font-size: 11px; }
  .sig { margin-top: 48px; border-top: 1px solid #cbd5e1; padding-top: 8px; width: 70%; }
  .gen { margin-top: 48px; text-align: center; font-size: 9px; color: #94a3b8; }
</style>
</head>
<body>
<div class="frame">
  <div class="hdr">ATHENA — UNIT PERSONNEL RECORD</div>
  <div class="cat"><span>CATEGORIE : <?= $cat ?></span></div>
  <div class="badge">
    <?php if ($badgeSrc !== ''): ?>
      <img src="<?= $badgeSrc ?>" alt="Insigne">
    <?php else: ?>
      <div class="badge-ph">INSIGNE</div>
    <?php endif; ?>
  </div>
  <h1>BREVET DE QUALIFICATION</h1>
  <div class="certify">Le présent brevet certifie que</div>
  <div class="holder"><?= htmlspecialchars($holder_name) ?></div>
  <div class="obtained">a obtenu la qualification</div>
  <div class="qual"><?= $qualLine ?></div>
  <table class="meta">
    <tr>
      <td>
        <div class="lbl">Organisme émetteur</div>
        <div class="val"><?= htmlspecialchars($issuer_name !== '' ? $issuer_name : '—') ?></div>
      </td>
      <td>
        <div class="lbl">Date d’obtention</div>
        <div class="val"><?= htmlspecialchars($obtained_at) ?></div>
      </td>
      <td>
        <div class="lbl">Validité</div>
        <div class="val"><?= htmlspecialchars($expires_at) ?></div>
      </td>
    </tr>
  </table>
  <table class="foot">
    <tr>
      <td>N° de brevet : <strong><?= htmlspecialchars($certificate_number) ?></strong></td>
      <td>
        <div class="sig">Signature de l’autorité émettrice</div>
      </td>
    </tr>
  </table>
  <div class="gen">Généré par ATHENA le <?= htmlspecialchars($generated_at) ?></div>
</div>
</body>
</html>
