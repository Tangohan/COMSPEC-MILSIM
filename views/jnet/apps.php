<?php
/** @var list<array{label:string,desc:string,href:string}> $apps */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$apps = is_array($apps ?? null) ? $apps : [];
?>
<div class="jnet-apps">
    <?php foreach ($apps as $app): ?>
        <a class="jnet-app-card" href="<?= $h((string) ($app['href'] ?? '#')) ?>">
            <strong><?= $h((string) ($app['label'] ?? '')) ?></strong>
            <span><?= $h((string) ($app['desc'] ?? '')) ?></span>
        </a>
    <?php endforeach; ?>
</div>
