<?php
/**
 * Skeleton de chargement Athena Command.
 * @var string $variant text|title|card|avatar|rows
 * @var int $rows
 */
$variant = $variant ?? 'rows';
$rows = max(1, (int) ($rows ?? 3));
?>
<?php if ($variant === 'avatar'): ?>
    <div class="ds-skeleton ds-skeleton--avatar" aria-hidden="true"></div>
<?php elseif ($variant === 'title'): ?>
    <div class="ds-skeleton ds-skeleton--title" aria-hidden="true"></div>
<?php elseif ($variant === 'card'): ?>
    <div class="ds-card p-5 space-y-3" aria-busy="true" aria-label="Chargement">
        <div class="ds-skeleton ds-skeleton--title"></div>
        <?php for ($i = 0; $i < $rows; $i++): ?>
            <div class="ds-skeleton ds-skeleton--text" style="width: <?= 100 - ($i * 12) ?>%"></div>
        <?php endfor; ?>
    </div>
<?php else: ?>
    <div class="space-y-2" aria-busy="true" aria-label="Chargement">
        <?php for ($i = 0; $i < $rows; $i++): ?>
            <div class="ds-skeleton ds-skeleton--text" style="width: <?= max(40, 100 - ($i * 15)) ?>%"></div>
        <?php endfor; ?>
    </div>
<?php endif; ?>
