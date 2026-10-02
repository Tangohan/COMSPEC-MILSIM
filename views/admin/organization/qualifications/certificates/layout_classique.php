<?php
/**
 * Brevet — gabarit « classique » (A4 paysage, diplôme encadré).
 * Compatible Dompdf : positions absolues et tableaux, unités en points.
 *
 * @var array $award
 * @var string $holder_name
 * @var string $certificate_number
 */
require __DIR__ . '/_prepare.php';
$navy = '#14213d';
$gold = '#a8843b';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  @page { margin: 0; size: A4 landscape; }
  * { margin: 0; padding: 0; }
  body { font-family: Times, 'Times New Roman', serif; color: <?= $navy ?>; font-size: 11pt; }
  .page { position: relative; width: 842pt; height: 595pt; overflow: hidden; background: #fdfbf6; }
  .frame-out { position: absolute; left: 20pt; top: 20pt; width: 798pt; height: 551pt; border: 2.5pt solid <?= $navy ?>; }
  .frame-in { position: absolute; left: 28pt; top: 28pt; width: 782pt; height: 535pt; border: 0.75pt solid <?= $gold ?>; }
  .corner { position: absolute; width: 18pt; height: 18pt; border-color: <?= $gold ?>; border-style: solid; }
  .c-tl { left: 36pt; top: 36pt; border-width: 1.5pt 0 0 1.5pt; }
  .c-tr { right: 36pt; top: 36pt; border-width: 1.5pt 1.5pt 0 0; }
  .c-bl { left: 36pt; bottom: 36pt; border-width: 0 0 1.5pt 1.5pt; }
  .c-br { right: 36pt; bottom: 36pt; border-width: 0 1.5pt 1.5pt 0; }

  .center { position: absolute; left: 60pt; width: 722pt; text-align: center; }
  .head { top: 58pt; font-family: Helvetica, Arial, sans-serif; font-size: 8pt; letter-spacing: 2.4pt; color: #55607a; text-transform: uppercase; }
  .head b { color: <?= $navy ?>; }
  .title { top: 84pt; font-size: 34pt; font-weight: bold; letter-spacing: 1pt; }
  .rule { top: 132pt; }
  .rule span { display: inline-block; width: 120pt; height: 1pt; background: <?= $gold ?>; }
  .cat { top: 142pt; font-family: Helvetica, Arial, sans-serif; font-size: 8pt; letter-spacing: 2pt; color: <?= $gold ?>; font-weight: bold; }
  .intro { top: 172pt; font-size: 12pt; font-style: italic; color: #55607a; }
  .holder { top: 192pt; font-size: 30pt; font-weight: bold; font-style: italic; }
  .holder-line { top: 232pt; font-family: Helvetica, Arial, sans-serif; font-size: 9.5pt; color: #55607a; }
  .intro2 { top: 260pt; font-size: 12pt; font-style: italic; color: #55607a; }
  .qual { top: 280pt; font-size: 21pt; font-weight: bold; line-height: 1.15; }
  .level { top: 312pt; font-size: 12pt; color: #3b4660; }

  .fields { position: absolute; left: 96pt; top: 352pt; width: 650pt; border-collapse: collapse; font-family: Helvetica, Arial, sans-serif; }
  .fields td { width: 25%; text-align: center; padding: 8pt 6pt; border-top: 0.75pt solid #d8cfb8; border-bottom: 0.75pt solid #d8cfb8; vertical-align: top; }
  .lbl { font-size: 6.5pt; letter-spacing: 1.2pt; color: #6b7690; text-transform: uppercase; }
  .val { margin-top: 4pt; font-size: 10.5pt; font-weight: bold; color: <?= $navy ?>; }
  .val-mono { font-family: Courier, monospace; font-size: 10pt; }

  .seal { position: absolute; left: 375pt; top: 430pt; width: 92pt; height: 92pt; border-radius: 46pt; background: <?= $navy ?>; text-align: center; }
  .seal-in { position: absolute; left: 6pt; top: 6pt; width: 78pt; height: 78pt; border-radius: 39pt; border: 1pt solid <?= $gold ?>; background: #ffffff; }
  .seal img { width: 60pt; height: 60pt; margin-top: 9pt; }
  .seal-code { padding-top: 31pt; font-family: Helvetica, Arial, sans-serif; font-size: 11pt; font-weight: bold; color: <?= $navy ?>; letter-spacing: 1pt; }

  .sign { position: absolute; top: 470pt; width: 220pt; text-align: center; font-family: Helvetica, Arial, sans-serif; }
  .sign-left { left: 96pt; }
  .sign-right { right: 96pt; }
  .sign-line { border-top: 0.75pt solid #8592ab; margin-bottom: 5pt; }
  .sign-label { font-size: 7.5pt; color: #55607a; }
  .sign-name { font-size: 8.5pt; font-weight: bold; color: <?= $navy ?>; margin-top: 2pt; }

  .foot { position: absolute; left: 60pt; top: 540pt; width: 722pt; text-align: center; font-family: Helvetica, Arial, sans-serif; font-size: 6.5pt; color: #8a94a8; }
  .status { display: inline-block; padding: 2pt 8pt; border-radius: 3pt; font-weight: bold; font-size: 7pt; }
</style>
</head>
<body>
<div class="page">
  <div class="frame-out"></div>
  <div class="frame-in"></div>
  <div class="corner c-tl"></div><div class="corner c-tr"></div><div class="corner c-bl"></div><div class="corner c-br"></div>

  <div class="center head"><b><?= $b['tenant_upper'] ?></b> &nbsp;·&nbsp; Registre du personnel</div>
  <div class="center title">Brevet de qualification</div>
  <div class="center rule"><span></span></div>
  <div class="center cat"><?= $b['category'] ?></div>

  <div class="center intro">Le présent brevet est décerné à</div>
  <div class="center holder"><?= $b['holder'] ?></div>
  <?php if ($b['holder_line'] !== ''): ?>
  <div class="center holder-line"><?= $b['holder_line'] ?></div>
  <?php endif; ?>
  <div class="center intro2">pour avoir obtenu la qualification</div>
  <div class="center qual"><?= $b['qualification'] ?></div>
  <?php if ($b['level'] !== ''): ?>
  <div class="center level">Niveau : <?= $b['level'] ?></div>
  <?php endif; ?>

  <table class="fields">
    <tr>
      <td><div class="lbl">Organisme émetteur</div><div class="val"><?= $b['issuer'] ?></div></td>
      <td><div class="lbl">Date d’obtention</div><div class="val"><?= $b['obtained'] ?></div></td>
      <td><div class="lbl">Validité</div><div class="val"><?= $b['validity'] ?></div></td>
      <td><div class="lbl">N° de brevet</div><div class="val val-mono"><?= $b['number'] ?></div></td>
    </tr>
  </table>

  <div class="sign sign-left">
    <div class="sign-line"></div>
    <div class="sign-label">Le titulaire</div>
    <div class="sign-name"><?= $b['holder'] ?></div>
  </div>
  <div class="seal">
    <div class="seal-in">
      <?php if ($b['badge_src'] !== ''): ?>
        <img src="<?= $b['badge_src'] ?>" alt="Insigne">
      <?php else: ?>
        <div class="seal-code"><?= $b['badge_code'] ?></div>
      <?php endif; ?>
    </div>
  </div>
  <div class="sign sign-right">
    <div class="sign-line"></div>
    <div class="sign-label">Pour l’autorité émettrice</div>
    <div class="sign-name"><?= $b['issuer'] ?></div>
  </div>

  <div class="foot">
    <span class="status" style="background: <?= $b['status_bg'] ?>; color: <?= $b['status_fg'] ?>;"><?= $b['status'] ?></span>
    &nbsp; Brevet n° <?= $b['number'] ?> · Généré par ATHENA le <?= $b['generated'] ?>
  </div>
</div>
</body>
</html>
