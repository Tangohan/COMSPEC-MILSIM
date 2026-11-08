<?php

declare(strict_types=1);

/**
 * Fil de messages d'un canal, ou messages privés du joueur : lecture seule, défilement dans le fil.
 *
 * @var string $threadTitle
 * @var list<array<string, mixed>> $messages
 * @var bool $private
 */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$when = static function (string $s): string {
    $t = strtotime($s);

    return $t ? date('d/m H:i', $t) : '';
};
$subSet = 'radio';
$subActive = $private ? 'private' : 'channels';
require __DIR__ . '/_head.php';
require __DIR__ . '/_sub.php';
?>
    <section class="mm-thread-wrap">
      <h1 class="mm-h1"><?php if ($private): ?>Messages privés<?php else: ?><span translate="no"><?= $h($threadTitle) ?></span><?php endif; ?></h1>
      <?php if ($messages === []): ?>
        <p class="mm-muted"><?= $private ? 'Aucun message privé pour vous dans cette partie.' : 'Aucun message dans ce canal.' ?></p>
      <?php else: ?>
        <ol class="mm-thread" data-thread>
          <?php foreach ($messages as $m): ?>
            <li class="mm-msg<?= !empty($m['mine']) ? ' is-mine' : '' ?>">
              <span class="mm-msg__head mm-mono"><span translate="no"><?= $h($m['author']) ?></span><?php if ($private && ($m['to'] ?? '') !== ''): ?> → <span translate="no"><?= $h($m['to']) ?></span><?php endif; ?> · <?= $h($when((string) $m['at'])) ?></span>
              <span class="mm-msg__text" translate="no"><?= nl2br($h($m['text'])) ?></span>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </section>
<?php require __DIR__ . '/_foot.php'; ?>
