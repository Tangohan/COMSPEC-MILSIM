<?php
/**
 * Ma situation — bandeau sombre + indicateurs (même langage que Mon avancement).
 *
 * @var array{
 *   kicker?: string, title?: string, lead?: string,
 *   focus?: array{label: string, value: string, meta?: string, badge?: string, badge_html?: string, empty?: bool},
 *   links?: list<array{label: string, href: string, primary?: bool}>,
 *   stats?: list<array{label: string, value: string|int, note?: string, small?: bool}>,
 *   wide?: bool
 * } $summary
 */
$summary = is_array($summary ?? null) ? $summary : [];
$sh = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$focus = is_array($summary['focus'] ?? null) ? $summary['focus'] : null;
$links = is_array($summary['links'] ?? null) ? $summary['links'] : [];
$stats = is_array($summary['stats'] ?? null) ? $summary['stats'] : [];
?>
<section class="msd-summary<?= !empty($summary['wide']) ? ' msd-summary--wide' : '' ?>" aria-label="Résumé">
    <div class="msd-hero">
        <?php if (($summary['kicker'] ?? '') !== ''): ?>
            <p class="msd-hero__kicker"><?= $sh($summary['kicker']) ?></p>
        <?php endif; ?>
        <?php if ($focus !== null): ?>
            <div class="msd-hero__focus">
                <span class="msd-hero__badge<?= !empty($focus['empty']) ? ' is-empty' : '' ?>" aria-hidden="true"><?= isset($focus['badge_html']) ? (string) $focus['badge_html'] : $sh($focus['badge'] ?? '—') ?></span>
                <div class="msd-hero__focus-text">
                    <span class="msd-hero__focus-label"><?= $sh($focus['label'] ?? '') ?></span>
                    <span class="msd-hero__focus-value"><?= $sh($focus['value'] ?? '') ?></span>
                    <?php if (($focus['meta'] ?? '') !== ''): ?>
                        <span class="msd-hero__focus-meta"><?= $sh($focus['meta']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif (($summary['title'] ?? '') !== ''): ?>
            <h2 class="msd-hero__title"><?= $sh($summary['title']) ?></h2>
        <?php endif; ?>
        <?php if (($summary['lead'] ?? '') !== ''): ?>
            <p class="msd-hero__lead"><?= $sh($summary['lead']) ?></p>
        <?php endif; ?>
        <?php if ($links !== []): ?>
            <nav class="msd-hero__links" aria-label="Autres pages du dossier">
                <?php foreach ($links as $link): ?>
                    <a class="msd-hero__link<?= !empty($link['primary']) ? ' msd-hero__link--primary' : '' ?>" href="<?= $sh($link['href'] ?? '') ?>"><?= $sh($link['label'] ?? '') ?></a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
    </div>
    <?php if ($stats !== []): ?>
        <div class="msd-stats">
            <?php foreach ($stats as $stat): ?>
                <div class="msd-stat">
                    <span class="msd-stat__label"><?= $sh($stat['label'] ?? '') ?></span>
                    <span class="msd-stat__value<?= !empty($stat['small']) ? ' msd-stat__value--sm' : '' ?>"><?= $sh((string) ($stat['value'] ?? '')) ?></span>
                    <?php if (($stat['note'] ?? '') !== ''): ?>
                        <span class="msd-stat__note"><?= $sh($stat['note']) ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
