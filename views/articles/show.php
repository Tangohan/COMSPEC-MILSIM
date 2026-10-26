<?php
declare(strict_types=1);

/**
 * Lecture d’un article : colonne de lecture confortable, couverture, chapeau, galerie.
 *
 * @var array<string, mixed> $miniArticle
 */
$item = is_array($miniArticle ?? null) ? $miniArticle : [];
$canManage = !empty($canManageMiniArticles);
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$title = (string) ($item['title'] ?? '');
$excerpt = (string) ($item['excerpt'] ?? '');
// Re-nettoyage à l'affichage : articles enregistrés avec l'ancien filtre regex (on*= sans guillemets).
$body = \App\Support\MiniArticleHtml::sanitize((string) ($item['body_html'] ?? ''));
$cover = is_string($item['cover_url'] ?? null) ? (string) $item['cover_url'] : '';
$gallery = is_array($item['gallery'] ?? null) ? $item['gallery'] : [];
$tags = is_array($item['tags'] ?? null) ? $item['tags'] : [];
$pub = (string) ($item['published_at'] ?? '');
$t = $pub !== '' ? strtotime($pub) : false;
$months = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
$pubLabel = $t ? 'Publié le ' . (int) date('j', $t) . ' ' . $months[(int) date('n', $t) - 1] . ' ' . date('Y', $t) : '';
$minutes = (int) ($item['reading_minutes'] ?? 0);
$id = (int) ($item['id'] ?? 0);
?>
<link href="<?= $h(asset_url('assets/css/mini-articles.css')) ?>" rel="stylesheet">
<article class="ma-read">
    <nav class="ma-read__nav" aria-label="Navigation article">
        <a href="<?= $h(url('articles')) ?>">← Tous les articles</a>
        <?php if ($canManage && $id > 0): ?>
        <a href="<?= $h(url('back-office/articles/' . $id . '/edit')) ?>">Modifier</a>
        <?php endif; ?>
    </nav>

    <?php if ($cover !== ''): ?>
    <div class="ma-read__cover"><img src="<?= $h($cover) ?>" alt=""></div>
    <?php endif; ?>

    <header>
        <?php if (!empty($item['pinned'])): ?><span class="ma-read__pin">Épinglé</span><?php endif; ?>
        <h1 class="ma-read__title"><?= $h($title) ?></h1>
        <?php if ($excerpt !== ''): ?><p class="ma-read__lead"><?= $h($excerpt) ?></p><?php endif; ?>
        <div class="ma-read__meta">
            <?php if ($pubLabel !== ''): ?><span><?= $h($pubLabel) ?></span><?php endif; ?>
            <?php if ($minutes > 0): ?><span><?= $minutes ?> min de lecture</span><?php endif; ?>
            <?php foreach ($tags as $tag): ?><span class="ma-chip">#<?= $h((string) $tag) ?></span><?php endforeach; ?>
        </div>
    </header>

    <div class="ma-read__content">
        <?= $body ?>
    </div>

    <?php if ($gallery !== []): ?>
    <div class="ma-read__gallery">
        <?php foreach ($gallery as $url): ?>
        <a href="<?= $h((string) $url) ?>" target="_blank" rel="noopener"><img src="<?= $h((string) $url) ?>" alt="" loading="lazy"></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</article>
