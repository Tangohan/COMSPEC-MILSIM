<?php

declare(strict_types=1);

$success = $success ?? null;
$error = $error ?? null;
$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
?>
<div class="bo-member-situation atk-dev">
    <p class="bo-member-situation__lead">
        <a href="<?= $h(url('back-office/ma-situation/liaison-atak')) ?>">← Ma liaison ATAK</a>
    </p>

    <?php if ($success): ?>
        <p class="bo-member-situation__flash bo-member-situation__flash--ok"><?= $h($success) ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="bo-member-situation__flash bo-member-situation__flash--err"><?= $h($error) ?></p>
    <?php endif; ?>

    <?php require base_path('views/partials/atak_device_cards.php'); ?>
</div>
