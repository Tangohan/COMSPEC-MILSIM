<?php
/**
 * Brevet — gabarit « moderne » (A4 paysage, bandeau latéral).
 * Compatible Dompdf : positions absolues et tableaux, unités en points.
 *
 * @var array $award
 * @var string $holder_name
 * @var string $certificate_number
 */
require __DIR__ . '/_prepare.php';
$blue = '#1f3a7a';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  @page { margin: 0; size: A4 landscape; }
  * { margin: 0; padding: 0; }
  body { font-family: Helvetica, Arial, sans-serif; color: #1b2433; font-size: 10pt; }
  .page { position: relative; width: 842pt; height: 595pt; overflow: hidden; }

  /* Bandeau latéral */
  .band { position: absolute; left: 0; top: 0; width: 168pt; height: 595pt; background: <?= $blue ?>; color: #fff; }
  .band-stripe { position: absolute; left: 168pt; top: 0; width: 4pt; height: 595pt; background: #c9a24a; }
  .brand { position: absolute; left: 28pt; top: 40pt; width: 120pt; }
  .brand-name { font-size: 15pt; font-weight: bold; letter-spacing: 1.5pt; }
  .brand-sub { margin-top: 4pt; font-size: 7pt; line-height: 1.35; color: #c9d4ee; text-transform: uppercase; letter-spacing: 0.6pt; }
  .tenant { margin-top: 14pt; padding-top: 10pt; border-top: 0.75pt solid #3d5799; font-size: 8.5pt; font-weight: bold; color: #ffffff; line-height: 1.3; }

  .seal { position: absolute; left: 39pt; top: 214pt; width: 90pt; height: 90pt; border: 2pt solid #ffffff; border-radius: 45pt; background: #ffffff; text-align: center; }
  .seal img { width: 64pt; height: 64pt; margin-top: 13pt; }
  .seal-code { padding-top: 34pt; font-size: 12pt; font-weight: bold; color: <?= $blue ?>; letter-spacing: 1pt; }
  .seal-ring { position: absolute; left: 33pt; top: 208pt; width: 102pt; height: 102pt; border: 0.75pt solid #6f86c2; border-radius: 51pt; }
  .seal-caption { position: absolute; left: 14pt; top: 322pt; width: 140pt; text-align: center; font-size: 7pt; color: #c9d4ee; letter-spacing: 0.8pt; text-transform: uppercase; }

  .category { position: absolute; left: 14pt; bottom: 40pt; width: 140pt; text-align: center; font-size: 9pt; font-weight: bold; letter-spacing: 1pt; line-height: 1.3; }

  /* Contenu */
  .main { position: absolute; left: 212pt; top: 46pt; width: 586pt; }
  .kicker { font-size: 8.5pt; letter-spacing: 1.6pt; color: #4b5873; text-transform: uppercase; }
  .kicker-rule { width: 84pt; height: 1.5pt; background: <?= $blue ?>; margin-top: 6pt; }
  .qual { margin-top: 22pt; font-size: 26pt; font-weight: bold; color: #13203d; line-height: 1.12; }
  .level { margin-top: 6pt; font-size: 12pt; color: #5b6880; }
  .awarded { margin-top: 26pt; font-size: 10pt; color: #5b6880; }
  .holder { margin-top: 4pt; font-size: 22pt; font-weight: bold; color: <?= $blue ?>; }
  .holder-line { margin-top: 3pt; font-size: 10pt; color: #4b5873; }

  .fields { position: absolute; left: 212pt; top: 300pt; width: 586pt; border-collapse: collapse; }
  .fields td { width: 50%; padding: 0 22pt 0 0; vertical-align: top; }
  .field { padding: 10pt 0 8pt; border-bottom: 0.75pt dashed #b9c2d3; }
  .lbl { font-size: 7pt; letter-spacing: 1pt; color: #6b7690; text-transform: uppercase; }
  .val { margin-top: 4pt; font-size: 12pt; font-weight: bold; color: #13203d; }
  .val-mono { font-family: Courier, monospace; font-size: 11.5pt; }

  .sign { position: absolute; right: 44pt; top: 424pt; width: 210pt; text-align: center; }
  .sign-line { border-top: 0.75pt solid #8592ab; margin-bottom: 5pt; }
  .sign-label { font-size: 8pt; color: #5b6880; }
  .sign-tenant { font-size: 8.5pt; font-weight: bold; color: #13203d; margin-top: 2pt; }

  .footer { position: absolute; left: 212pt; top: 520pt; width: 586pt; border-top: 0.75pt solid #dfe4ec; padding-top: 12pt; }
  .footer td { vertical-align: middle; }
  .status { display: inline-block; padding: 5pt 14pt; border-radius: 4pt; font-size: 8.5pt; font-weight: bold; }
  .gen { text-align: right; font-size: 7pt; color: #8a94a8; line-height: 1.5; }
</style>
</head>
<body>
<div class="page">
  <div class="band">
    <div class="brand">
      <div class="brand-name">ATHENA</div>
      <div class="brand-sub">Plateforme de gestion d’unité</div>
      <div class="tenant"><?= $b['tenant'] ?></div>
    </div>
    <div class="seal-ring"></div>
    <div class="seal">
      <?php if ($b['badge_src'] !== ''): ?>
        <img src="<?= $b['badge_src'] ?>" alt="Insigne">
      <?php else: ?>
        <div class="seal-code"><?= $b['badge_code'] ?></div>
      <?php endif; ?>
    </div>
    <div class="seal-caption">Insigne de qualification</div>
    <div class="category"><?= $b['category'] ?></div>
  </div>
  <div class="band-stripe"></div>

  <div class="main">
    <div class="kicker">Certificat de qualification</div>
    <div class="kicker-rule"></div>
    <div class="qual"><?= $b['qualification'] ?></div>
    <?php if ($b['level'] !== ''): ?>
    <div class="level">Niveau : <?= $b['level'] ?></div>
    <?php endif; ?>
    <div class="awarded">Décerné à</div>
    <div class="holder"><?= $b['holder'] ?></div>
    <?php if ($b['holder_line'] !== ''): ?>
    <div class="holder-line"><?= $b['holder_line'] ?></div>
    <?php endif; ?>
  </div>

  <table class="fields">
    <tr>
      <td><div class="field"><div class="lbl">Organisme émetteur</div><div class="val"><?= $b['issuer'] ?></div></div></td>
      <td><div class="field"><div class="lbl">Date d’obtention</div><div class="val"><?= $b['obtained'] ?></div></div></td>
    </tr>
    <tr>
      <td><div class="field"><div class="lbl">Validité</div><div class="val"><?= $b['validity'] ?></div></div></td>
      <td><div class="field"><div class="lbl">N° de brevet</div><div class="val val-mono"><?= $b['number'] ?></div></div></td>
    </tr>
  </table>

  <div class="sign">
    <div class="sign-line"></div>
    <div class="sign-label">Signature de l’autorité émettrice</div>
    <div class="sign-tenant"><?= $b['issuer'] ?></div>
  </div>

  <table class="footer">
    <tr>
      <td><span class="status" style="background: <?= $b['status_bg'] ?>; color: <?= $b['status_fg'] ?>;"><?= $b['status'] ?></span></td>
      <td class="gen">Brevet n° <?= $b['number'] ?> — délivré par <?= $b['tenant'] ?><br>Généré par ATHENA le <?= $b['generated'] ?></td>
    </tr>
  </table>
</div>
</body>
</html>
