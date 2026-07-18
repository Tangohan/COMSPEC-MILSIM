<?php
/**
 * Stepper / assistant multi-étapes.
 * @var list<array{label: string, done?: bool, active?: bool}> $steps
 */
$steps = is_array($steps ?? null) ? $steps : [];
?>
<ol class="ds-stepper" aria-label="Étapes">
    <?php foreach ($steps as $i => $step): ?>
        <?php
        $label = (string) ($step['label'] ?? ('Étape ' . ($i + 1)));
        $done = !empty($step['done']);
        $active = !empty($step['active']);
        $cls = 'ds-stepper__item';
        if ($done) {
            $cls .= ' ds-stepper__item--done';
        }
        if ($active) {
            $cls .= ' ds-stepper__item--active';
        }
        ?>
        <li class="<?= htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') ?>">
            <span class="ds-stepper__num" aria-hidden="true"><?= $done ? '✓' : (string) ($i + 1) ?></span>
            <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
        </li>
    <?php endforeach; ?>
</ol>
