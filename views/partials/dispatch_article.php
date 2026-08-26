<?php
declare(strict_types=1);
/** @var array<string, mixed> $dispatch */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$kind = (string) ($dispatch['kind'] ?? 'spotrep');
$dispatchHeadingTag = $dispatchHeadingTag ?? 'h1';
$headingTag = in_array($dispatchHeadingTag, ['h1', 'h2', 'h3'], true) ? $dispatchHeadingTag : 'h1';
?>
<article class="tr <?= $kind === 'techrep' ? 'tr--tech' : ($kind === 'update' ? 'tr--upd' : 'tr--spot') ?>">
    <header class="tr__head">
        <p class="tr__stamp"><span class="tr__signal" aria-hidden="true"></span><?= $h($dispatch['kind_label'] ?? 'SPOTREP') ?> #<?= $h($dispatch['number_pad'] ?? '00000') ?></p>
        <p class="tr__reported">reported by <strong><?= $h($dispatch['reporter'] ?? 'Athena Operations') ?></strong> <?= $h($dispatch['reported_on'] ?? '') ?></p>
        <?php if (($dispatch['companion_href'] ?? '') !== ''): ?>
            <p class="tr__companion"><a href="<?= $h($dispatch['companion_href']) ?>"><?= $h($dispatch['companion_label']) ?></a></p>
        <?php endif; ?>
        <<?= $headingTag ?> class="tr__title"><?= $h($dispatch['title'] ?? '') ?></<?= $headingTag ?>>
        <pre class="tr__meta" tabindex="0">FROM:     <?= $h($dispatch['from'] ?? '') . "\n" ?>TO:       <?= $h($dispatch['to'] ?? '') . "\n" ?>UNIT:     <?= $h($dispatch['category'] ?? '') . "\n" ?>ACTIVITY: <?= $h($dispatch['activity'] ?? '') . "\n" ?>SIZE:     <?= $h($dispatch['size'] ?? '') ?></pre>
    </header>

    <?php if (!empty($dispatch['notes'])): ?>
    <section class="tr__block">
        <h2>NOTES</h2>
        <ul class="tr__notes">
            <?php foreach ($dispatch['notes'] as $note): ?>
                <li><?= $h($note) ?></li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <?php if (!empty($dispatch['sections'])): ?>
    <section class="tr__block">
        <h2>CHANGELOG</h2>
        <?php foreach ($dispatch['sections'] as $section): ?>
            <?php if (($section['title'] ?? '') !== '' && strtoupper((string) $section['title']) !== 'CHANGELOG'): ?>
                <h3><?= $h($section['title'] ?? '') ?></h3>
            <?php endif; ?>
            <ul class="tr__log">
                <?php foreach ($section['items'] ?? [] as $item): ?>
                    <li>
                        <span class="tr__verb tr__verb--<?= $h(strtolower((string) ($item['verb'] ?? 'added'))) ?>"><?= $h($item['verb'] ?? '') ?></span>
                        <span><?= $h($item['text'] ?? '') ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>
</article>
