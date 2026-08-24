<?php
declare(strict_types=1);
/** @var array<string, mixed> $dispatch */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$groups = implode(' ', array_map('strval', $dispatch['filter_groups'] ?? []));
?>
<a
    class="tr-card"
    href="<?= $h($dispatch['href'] ?? '#') ?>"
    data-cl-card
    data-cl-reveal
    data-groups="<?= $h($groups) ?>"
    data-year="<?= $h((string) ($dispatch['year'] ?? '')) ?>"
    data-search="<?= $h($dispatch['search'] ?? '') ?>"
>
    <span class="tr-card__kind"><?= $h($dispatch['kind_label'] ?? '') ?> #<?= $h($dispatch['number_pad'] ?? '') ?></span>
    <span class="tr-card__date"><?= $h($dispatch['date_label'] ?? '') ?></span>
    <strong class="tr-card__title"><?= $h($dispatch['title'] ?? '') ?></strong>
    <span class="tr-card__act"><?= $h($dispatch['activity'] ?? '') ?></span>
</a>
