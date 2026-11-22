<?php

declare(strict_types=1);

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$articleTitle = static fn (int $number): string => __('conduct.article', ['number' => $number]) . ' · ' . __('conduct.article_' . $number . '_title');
$articles = range(1, 7);
?>
<h1><?= $escape(__('conduct.title')) ?></h1>
<p><?= $escape(__('conduct.intro')) ?></p>

<nav class="legal-conduct-index" aria-label="<?= $escape(__('conduct.index')) ?>">
    <ul>
        <?php foreach ($articles as $number): ?>
            <li><a href="#article-<?= $number ?>"><?= $escape($articleTitle($number)) ?></a></li>
        <?php endforeach; ?>
    </ul>
</nav>

<?php foreach ($articles as $number): ?>
    <section id="article-<?= $number ?>">
        <h2><?= $escape($articleTitle($number)) ?></h2>
        <p><?= $escape(__('conduct.article_' . $number . '_body')) ?></p>
        <?php if ($number === 5): ?>
            <p><a href="<?= $escape(url('contact')) ?>"><?= $escape(__('conduct.contact')) ?></a></p>
        <?php elseif ($number === 6 || $number === 7): ?>
            <p><a href="<?= $escape(url('contester-sanction')) ?>"><?= $escape(__('conduct.appeal')) ?></a></p>
        <?php endif; ?>
    </section>
<?php endforeach; ?>
<p><a href="<?= $escape(url('legal/site') . '#cgu') ?>"><?= $escape(__('legal.cgu_desc')) ?></a></p>
