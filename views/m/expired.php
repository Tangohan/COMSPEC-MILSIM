<?php

declare(strict_types=1);
/** @var string $why */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$mobileView = 'expired';
$mobileLink = null;
require __DIR__ . '/_head.php';
?>
    <section class="mm-card mm-card--warn">
      <h1 class="mm-h1">QR code à refaire</h1>
      <p><?= $h($why) ?></p>
      <p class="mm-muted">Dans le jeu : ouvrez l’app (Comptes rendus, CR fin d’op ou Carte), appuyez sur « Sur mon téléphone », puis scannez le QR code affiché avec l’appareil photo de ce téléphone.</p>
    </section>
<?php require __DIR__ . '/_foot.php'; ?>
