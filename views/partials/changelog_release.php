<?php
declare(strict_types=1);
/** @var array<string, mixed> $release */
/** @var array<string, string> $kindLabels */
/** @var array<string, string> $categoryLabels */
/** @var array<string, string> $typeLabels */
$h = static fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$groups = implode(' ', array_map('strval', $release['filter_groups'] ?? []));
$imgSrc = is_string($release['image'] ?? null) && $release['image'] !== ''
    ? asset_url((string) $release['image'])
    : '';
?>
<article
    class="cl-card"
    id="<?= $h($release['id'] ?? '') ?>"
    data-cl-card
    data-cl-reveal
    data-groups="<?= $h($groups) ?>"
    data-year="<?= $h((string) ($release['year'] ?? '')) ?>"
    data-search="<?= $h($release['search'] ?? '') ?>"
>
    <div class="cl-card__top">
        <span class="cl-card__month"><?= $h($release['month_label'] ?? '') ?></span>
        <span class="cl-card__ver"><?= $h(($release['version_label'] ?? '') . ' ' . ($release['version'] ?? '')) ?></span>
    </div>
    <div class="cl-badges">
        <?php if (($release['type'] ?? '') === 'major'): ?>
            <span class="cl-badge cl-badge--new"><?= $h($typeLabels['major'] ?? '') ?></span>
        <?php endif; ?>
        <?php foreach ($release['kinds'] ?? [] as $kind): ?>
            <span class="cl-badge cl-badge--<?= $h($kind) ?>"><?= $h($kindLabels[$kind] ?? $kind) ?></span>
        <?php endforeach; ?>
        <?php foreach ($release['categories'] ?? [] as $cat): ?>
            <span class="cl-badge"><?= $h($categoryLabels[$cat] ?? $cat) ?></span>
        <?php endforeach; ?>
    </div>
    <h3><?= $h($release['title'] ?? '') ?></h3>
    <p class="cl-card__sum"><?= $h($release['summary'] ?? '') ?></p>
    <?php if (!empty($release['features']) && is_array($release['features'])): ?>
        <ul class="cl-card__points">
            <?php foreach ($release['features'] as $feature): ?>
                <li><?= $h(is_array($feature) ? ($feature['text'] ?? '') : $feature) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php if ($imgSrc !== ''): ?>
        <div class="cl-gallery">
            <button type="button" data-cl-img="<?= $h($imgSrc) ?>" data-cl-alt="<?= $h($release['title'] ?? '') ?>">
                <img src="<?= $h($imgSrc) ?>" alt="<?= $h($release['title'] ?? '') ?>" width="176" height="115" loading="lazy">
            </button>
            <?php foreach ($release['gallery'] ?? [] as $shot): ?>
                <?php $gSrc = asset_url((string) ($shot['src'] ?? '')); ?>
                <button type="button" data-cl-img="<?= $h($gSrc) ?>" data-cl-alt="<?= $h($shot['alt'] ?? '') ?>">
                    <img src="<?= $h($gSrc) ?>" alt="<?= $h($shot['alt'] ?? '') ?>" width="176" height="115" loading="lazy">
                </button>
            <?php endforeach; ?>
        </div>
    <?php elseif (!empty($release['gallery'])): ?>
        <div class="cl-gallery">
            <?php foreach ($release['gallery'] as $shot): ?>
                <?php $gSrc = asset_url((string) ($shot['src'] ?? '')); ?>
                <button type="button" data-cl-img="<?= $h($gSrc) ?>" data-cl-alt="<?= $h($shot['alt'] ?? '') ?>">
                    <img src="<?= $h($gSrc) ?>" alt="<?= $h($shot['alt'] ?? '') ?>" width="176" height="115" loading="lazy">
                </button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($release['video']) && is_string($release['video'])): ?>
        <div class="cl-video">
            <video src="<?= $h(asset_url($release['video'])) ?>" controls preload="none"></video>
        </div>
    <?php endif; ?>
    <?php if (is_array($release['before_after'] ?? null) && !empty($release['before_after']['before']) && !empty($release['before_after']['after'])): ?>
        <div class="cl-ba">
            <figure>
                <button type="button" data-cl-img="<?= $h(asset_url((string) $release['before_after']['before'])) ?>" data-cl-alt="<?= $h(__('site.cl_ba_before')) ?>">
                    <img src="<?= $h(asset_url((string) $release['before_after']['before'])) ?>" alt="<?= $h(__('site.cl_ba_before')) ?>" loading="lazy">
                </button>
                <figcaption><?= $h(__('site.cl_ba_before')) ?></figcaption>
            </figure>
            <figure>
                <button type="button" data-cl-img="<?= $h(asset_url((string) $release['before_after']['after'])) ?>" data-cl-alt="<?= $h(__('site.cl_ba_after')) ?>">
                    <img src="<?= $h(asset_url((string) $release['before_after']['after'])) ?>" alt="<?= $h(__('site.cl_ba_after')) ?>" loading="lazy">
                </button>
                <figcaption><?= $h(__('site.cl_ba_after')) ?></figcaption>
            </figure>
        </div>
    <?php endif; ?>
    <?php if (($release['why'] ?? '') !== ''): ?>
        <div class="cl-why">
            <h4><?= $h(__('site.cl_why')) ?></h4>
            <p><?= $h($release['why']) ?></p>
            <?php if (!empty($release['audiences']) && is_array($release['audiences'])): ?>
                <div class="cl-audiences">
                    <?php if (!empty($release['audiences']['ops'])): ?>
                        <div class="cl-aud"><strong><?= $h(__('site.cl_aud_ops')) ?></strong><span><?= $h($release['audiences']['ops']) ?></span></div>
                    <?php endif; ?>
                    <?php if (!empty($release['audiences']['cmd'])): ?>
                        <div class="cl-aud"><strong><?= $h(__('site.cl_aud_cmd')) ?></strong><span><?= $h($release['audiences']['cmd']) ?></span></div>
                    <?php endif; ?>
                    <?php if (!empty($release['audiences']['admin'])): ?>
                        <div class="cl-aud"><strong><?= $h(__('site.cl_aud_admin')) ?></strong><span><?= $h($release['audiences']['admin']) ?></span></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <div class="cl-card__foot">
        <?php foreach ($release['links'] ?? [] as $link): ?>
            <a href="<?= $h($link['href'] ?? '#') ?>"><?= $h($link['label'] ?? '') ?></a>
        <?php endforeach; ?>
        <?php if (($release['availability'] ?? '') !== ''): ?>
            <div class="cl-avail">
                <p><span class="sr-only"><?= $h(__('site.cl_avail')) ?> — </span><?= $h($release['availability']) ?></p>
            </div>
        <?php endif; ?>
    </div>
</article>
