<?php
declare(strict_types=1);
/** @var array<string, mixed> $dispatch */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$indexHref = url('nouveautes') . '#journal';
?>
<div class="cl tr-page" data-cl-root>
    <div class="cl-wrap tr-page__nav">
        <a href="<?= $h($indexHref) ?>"><?= $h(__('site.cl_dispatch_back')) ?></a>
    </div>
    <div class="cl-wrap tr-page__sheet">
        <?php require base_path('views/partials/dispatch_article.php'); ?>
    </div>
</div>
