<?php

declare(strict_types=1);

/**
 * Ce que le jeu ne transmet pas à Athena : dit clairement, sans rien afficher d'inventé.
 *
 * @var list<array{label: string, why: string}> $absent
 */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$subSet = 'radio';
$subActive = 'absent';
require __DIR__ . '/_head.php';
require __DIR__ . '/_sub.php';
?>
    <?php foreach ($absent as $a): ?>
      <section class="mm-card">
        <h2 class="mm-h1"><?= $h($a['label']) ?></h2>
        <p class="mm-muted"><?= $h($a['why']) ?></p>
        <p class="mm-state" data-st="pending">Pas encore transmis par le jeu</p>
      </section>
    <?php endforeach; ?>
<?php require __DIR__ . '/_foot.php'; ?>
