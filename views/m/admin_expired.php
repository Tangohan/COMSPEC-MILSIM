<?php

declare(strict_types=1);

/** @var string $why */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$adminView = 'admin_expired';
$adminLink = null;
require __DIR__ . '/_admin_head.php';
?>
    <section class="mm-card mm-card--warn">
      <h1 class="mm-h1">QR code à refaire</h1>
      <p><?= $h($why) ?></p>
      <p class="mm-muted">Dans le jeu : ouvrez l’app Admin du téléphone, appuyez sur « Sur mon téléphone », puis scannez le QR code avec l’appareil photo de ce téléphone. Il est valable deux minutes et ne sert qu’une fois.</p>
    </section>
<?php require __DIR__ . '/_admin_foot.php'; ?>
