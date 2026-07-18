<?php
/**
 * Tag / pastille.
 * @var string $label
 * @var string $variant neutral|success|warning|danger|info|locked
 */
$label = (string) ($label ?? '');
$variant = (string) ($variant ?? 'neutral');
$map = [
    'neutral' => '',
    'success' => 'ds-tag--success',
    'warning' => 'ds-tag--warning',
    'danger' => 'ds-tag--danger',
    'info' => 'ds-tag--info',
    'locked' => 'ds-tag--locked',
];
$extra = $map[$variant] ?? '';
?>
<span class="ds-tag <?= htmlspecialchars($extra, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
