<?php

declare(strict_types=1);

/**
 * SMS du téléphone en jeu : ils ne sont pas transmis à Athena. Dit clairement, sans rien afficher d'inventé.
 *
 * @var int $privateCount messages privés lisibles dans /m/radio/prives
 */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$subSet = 'radio';
$subActive = 'sms';
require __DIR__ . '/_head.php';
require __DIR__ . '/_sub.php';
?>
    <section class="mm-card">
      <h1 class="mm-h1">SMS entre joueurs</h1>
      <p class="mm-muted">Les SMS passent de téléphone en téléphone dans le jeu. Aucun n’est transmis à Athena, il n’y a donc rien à afficher.</p>
      <p class="mm-state" data-st="pending">Pas encore transmis par le jeu</p>
    </section>
    <a class="mm-tile" href="<?= $h(url('m/radio/prives')) ?>"><strong>Messages privés</strong><span><?= (int) $privateCount ?> message(s) transmis à Athena pour vous</span></a>
<?php require __DIR__ . '/_foot.php'; ?>
