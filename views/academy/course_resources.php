<?php
/** Resources are escaped text authored in FR/EN, validated HTTPS links, or slides imported into the community storage. */
$resources = $resources ?? [];
?>
<?php foreach ($resources as $resource): ?>
<details class="resource academy-reference">
    <summary><?= $h($local($resource['title'])) ?> <span><?= $h($t((string)($resource['type']??'document'))) ?></span></summary>
    <?php if (isset($resource['body'])): ?><div class="lesson-text academy-reference__body"><?= nl2br($h($local($resource['body']))) ?></div>
    <?php elseif (isset($resource['file'])): ?><p><a href="<?= $h($link('media/' . rawurlencode((string) $resource['file']))) ?>" target="_blank" rel="noopener"><?= $h($t('open_slides')) ?> ↗</a> · <?= (int) ($resource['pages'] ?? 0) ?> <?= $h($t('slides_count')) ?></p>
    <?php elseif (isset($resource['url'][$lang])): ?><p><a href="<?= $h($resource['url'][$lang]) ?>" target="_blank" rel="noopener noreferrer"><?= $h($t('open_resource')) ?> ↗</a></p><?php if (($resource['type']??'')==='audio'): ?><audio controls preload="none" src="<?= $h($resource['url'][$lang]) ?>"></audio><?php endif ?><?php endif ?>
</details>
<?php endforeach ?>
