<?php

declare(strict_types=1);

/**
 * Radio et messages : canaux de tchat de la partie.
 *
 * @var list<array{key: string, label: string}> $channels
 */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$subSet = 'radio';
$subActive = 'channels';
require __DIR__ . '/_head.php';
require __DIR__ . '/_sub.php';
?>
    <?php if ($channels === []): ?>
      <section class="mm-card">
        <h1 class="mm-h1">Aucun canal</h1>
        <p class="mm-muted">Aucun canal de tchat n’existe pour cette partie.</p>
      </section>
    <?php else: ?>
      <div class="mm-tiles mm-tiles--2">
        <?php foreach ($channels as $c): ?>
          <a class="mm-tile" href="<?= $h(url('m/radio/canal/' . rawurlencode($c['key']))) ?>"><strong translate="no"><?= $h($c['label']) ?></strong><span>Lire les messages</span></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
<?php require __DIR__ . '/_foot.php'; ?>
