<?php

declare(strict_types=1);

/**
 * Historique des comptes rendus : ceux que j'ai envoyés, ou ceux qui m'ont été adressés.
 *
 * @var string $mode sent | received
 * @var list<array<string, mixed>> $rows
 */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$when = static function (string $s): string {
    $t = strtotime($s);

    return $t ? date('d/m H:i', $t) : '';
};
$subSet = 'cr';
$subActive = $mode === 'received' ? 'received' : 'sent';
require __DIR__ . '/_head.php';
require __DIR__ . '/_sub.php';
?>
    <?php if ($rows === []): ?>
      <section class="mm-card">
        <h1 class="mm-h1"><?= $mode === 'received' ? 'Aucun compte rendu reçu' : 'Aucun compte rendu envoyé' ?></h1>
        <p class="mm-muted"><?= $mode === 'received'
            ? 'Les comptes rendus adressés à vous, à votre rôle ou à votre unité apparaissent ici.'
            : 'Dès que votre ATAK en a transmis un à Athena, il apparaît ici. Un envoi fait à l’instant peut mettre une minute.' ?></p>
        <a class="mm-btn" href="<?= $h(url('m/cr')) ?>">NOUVEAU COMPTE RENDU</a>
      </section>
    <?php else: ?>
      <ul class="mm-list">
        <?php foreach ($rows as $r): ?>
          <li>
            <a class="mm-row-card<?= $mode === 'received' && empty($r['read']) ? ' is-unread' : '' ?>" href="<?= $h(url('m/cr/lire/' . (int) $r['id'])) ?>">
              <span class="mm-row-card__top">
                <strong><?= $h($r['type']) ?></strong>
                <span class="mm-badge<?= !empty($r['urgent']) ? ' mm-badge--hot' : '' ?>"><?= $h($r['priority']) ?></span>
                <?php if ($mode === 'received' && empty($r['read'])): ?><span class="mm-badge mm-badge--new">Non lu</span><?php endif; ?>
              </span>
              <?php if ($r['summary'] !== ''): ?><span class="mm-row-card__text"><?= $h($r['summary']) ?></span><?php endif; ?>
              <span class="mm-row-card__meta mm-mono">
                <?= $h($when((string) $r['at'])) ?>
                <?php if ($mode === 'received' && $r['by'] !== ''): ?> · <span translate="no"><?= $h($r['by']) ?></span><?php endif; ?>
                <?php if ($r['grid'] !== ''): ?> · <?= $h($r['grid']) ?><?php endif; ?>
                <?php if ($mode === 'sent' && $r['status'] !== ''): ?> · <?= $h($r['status']) ?><?php endif; ?>
              </span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
<?php require __DIR__ . '/_foot.php'; ?>
