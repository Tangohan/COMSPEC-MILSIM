<?php

declare(strict_types=1);

/**
 * Lecture d'un compte rendu (le mien, ou adressé à moi) ; « J'ai lu » sur un compte rendu reçu.
 *
 * @var array<string, mixed>|null $report
 */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$when = static function (string $s): string {
    $t = strtotime($s);

    return $t ? date('d/m/Y H:i', $t) : '';
};
$subSet = 'cr';
$subActive = $report !== null && !$report['mine'] ? 'received' : 'sent';
require __DIR__ . '/_head.php';
require __DIR__ . '/_sub.php';
?>
    <?php if ($report === null): ?>
      <section class="mm-card mm-card--warn">
        <h1 class="mm-h1">Compte rendu introuvable</h1>
        <p class="mm-muted">Il n’existe plus, ou il ne vous est pas adressé.</p>
        <a class="mm-btn" href="<?= $h(url('m/cr/envoyes')) ?>">RETOUR À L’HISTORIQUE</a>
      </section>
    <?php else: ?>
      <article class="mm-card">
        <h1 class="mm-h1"><?= $h($report['type']) ?> <span class="mm-badge<?= !empty($report['urgent']) ? ' mm-badge--hot' : '' ?>"><?= $h($report['priority']) ?></span></h1>
        <p class="mm-muted mm-mono">
          <?= $h($when((string) $report['at'])) ?>
          <?php if ($report['by'] !== ''): ?> · <span translate="no"><?= $h($report['by']) ?></span><?php endif; ?>
          <?php if ($report['grid'] !== ''): ?> · <?= $h($report['grid']) ?><?php endif; ?>
        </p>
        <?php if ($report['status'] !== ''): ?><p class="mm-state"><?= $h($report['status']) ?></p><?php endif; ?>
        <?php if ($report['summary'] !== ''): ?><p class="mm-read"><?= nl2br($h($report['summary'])) ?></p><?php endif; ?>
        <?php if ($report['lines'] !== []): ?>
          <dl class="mm-dl">
            <?php foreach ($report['lines'] as [$label, $value]): ?>
              <dt><?= $h($label) ?></dt><dd><?= $h($value) ?></dd>
            <?php endforeach; ?>
          </dl>
        <?php endif; ?>
        <?php if ($report['details'] !== ''): ?><p class="mm-read"><?= nl2br($h($report['details'])) ?></p><?php endif; ?>
        <?php if ($report['remarks'] !== ''): ?><p class="mm-read"><?= nl2br($h($report['remarks'])) ?></p><?php endif; ?>
      </article>
      <?php if (!$report['mine']): ?>
        <?php if ($report['read']): ?>
          <p class="mm-state" data-st="acked">Lu ✓</p>
        <?php else: ?>
          <button type="button" class="mm-btn mm-btn--send" data-mm-read="<?= (int) $report['id'] ?>">J’AI LU</button>
        <?php endif; ?>
      <?php endif; ?>
    <?php endif; ?>
<?php require __DIR__ . '/_foot.php'; ?>
