<?php

declare(strict_types=1);

/**
 * @var array<string, mixed> $item
 * @var string $currentPath
 */
if (!isset($currentPath)) {
    $currentPath = navigation_current_path();
}

$variant = (string) ($item['variant'] ?? 'operations');
$layoutClass = 'nav-mega-layout nav-mega-layout--' . preg_replace('/[^a-z0-9_-]/', '', $variant);
$submenuStyle = preg_replace('/[^a-z0-9_-]/', '', (string) ($item['submenu_style'] ?? 'standard'));
if ($submenuStyle === '') {
    $submenuStyle = 'standard';
}
$slots = navigation_group_sections_by_slot($item['sections'] ?? []);
$orderedSections = array_merge($slots['primary'], $slots['center'], $slots['secondary']);
$feat = is_array($item['featured'] ?? null) ? $item['featured'] : null;
$hasFeatured = is_array($feat) && trim((string) ($feat['title'] ?? '')) !== '';

$megaId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($item['id'] ?? 'nav-mega'));
if ($megaId === '') {
    $megaId = 'nav-mega';
}

$renderLinksFragment = static function (array $section) use ($currentPath): void {
    foreach ($section['links'] ?? [] as $link) {
        if (!is_array($link)) {
            continue;
        }
        $isActive = nav_link_is_active($link, $currentPath) ? '1' : '0';
        ?>
        <a href="<?= htmlspecialchars((string) ($link['href'] ?? '#')) ?>"
           class="nav-mega-link group/item flex items-start justify-between gap-2 rounded-xl px-2.5 py-2 sm:gap-3 sm:px-3 sm:py-2.5"
           data-active="<?= $isActive ?>">
            <div class="min-w-0">
                <p class="flex flex-wrap items-center gap-1.5 text-[13px] font-bold leading-snug text-slate-950 sm:text-sm">
                    <span><?= htmlspecialchars((string) ($link['label'] ?? '')) ?></span>
                    <?php if (!empty($link['badge'])): ?>
                        <span class="inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-emerald-600 px-1.5 py-0.5 text-[9px] font-black tabular-nums text-white"><?= htmlspecialchars((string) $link['badge']) ?></span>
                    <?php endif; ?>
                </p>
                <?php if (!empty($link['description'])): ?>
                    <p class="mt-1 text-[11px] leading-snug text-slate-500 sm:text-xs sm:leading-5">
                        <?= htmlspecialchars((string) $link['description']) ?>
                    </p>
                <?php endif; ?>
            </div>
            <svg class="nav-mega-link__chevron mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M7.22 14.78a.75.75 0 010-1.06L10.94 10 7.22 6.28a.75.75 0 111.06-1.06l4.25 4.25a.75.75 0 010 1.06l-4.25 4.25a.75.75 0 01-1.06 0z" clip-rule="evenodd"/>
            </svg>
        </a>
        <?php
    }
};

$renderFeatured = static function (?array $f): void {
    if ($f === null || ($f['title'] ?? '') === '') {
        return;
    }
    $imgUrl = ($f['image'] ?? '') !== '' ? navigation_public_image_url((string) $f['image']) : '';
    $showImg = !empty($f['image_enabled']) && ($f['image'] ?? '') !== '' && navigation_image_file_exists((string) $f['image']);
    $pos = $f['image_position'] ?? 'center';
    $overlay = $f['overlay'] ?? 'dark';
    $eyebrow = trim((string) ($f['eyebrow'] ?? ''));
    if ($eyebrow === '') {
        $eyebrow = 'Module';
    }
    $ctaClass = (string) ($f['cta_classes'] ?? 'nav-cta nav-cta--open');
    $overlayClass = match ($overlay) {
        'light' => 'from-white/95 via-slate-50/90 to-slate-100/95',
        'none' => 'from-slate-800/75 via-slate-900/70 to-slate-950/80',
        default => 'from-slate-900/92 via-slate-900/85 to-slate-950/88',
    };
    if (!$showImg) {
        ?>
        <aside class="nav-mega-aside flex min-h-[200px] flex-col justify-between border-l p-4 sm:p-5 md:min-h-full">
            <div>
                <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-black uppercase tracking-[0.14em]"
                      style="background: var(--nav-accent-soft); color: var(--nav-accent); border: 1px solid color-mix(in srgb, var(--nav-accent) 25%, transparent);">
                    <?= htmlspecialchars($eyebrow) ?>
                </span>
                <h3 class="mt-3 text-lg font-black uppercase tracking-tight leading-snug text-slate-950">
                    <?= htmlspecialchars((string) $f['title']) ?>
                </h3>
                <?php if (($f['description'] ?? '') !== ''): ?>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">
                        <?= htmlspecialchars((string) $f['description']) ?>
                    </p>
                <?php endif; ?>
            </div>
            <?php if (!empty($f['cta_href']) && ($f['cta_label'] ?? '') !== ''): ?>
                <a href="<?= htmlspecialchars((string) $f['cta_href']) ?>" class="<?= htmlspecialchars($ctaClass) ?> mt-5 w-full">
                    <?= htmlspecialchars((string) $f['cta_label']) ?>
                </a>
            <?php endif; ?>
        </aside>
        <?php

        return;
    }
    ?>
    <aside class="nav-mega-aside relative flex min-h-[200px] flex-col justify-between border-l p-4 text-white sm:p-5 md:min-h-full">
        <div class="absolute inset-0 bg-cover opacity-45"
             style="background-image:url('<?= htmlspecialchars($imgUrl) ?>');background-position:<?= htmlspecialchars((string) $pos) ?>"></div>
        <div class="absolute inset-0 bg-gradient-to-br <?= $overlayClass ?>"></div>
        <div class="relative flex flex-1 flex-col justify-between">
            <div>
                <span class="inline-flex rounded-full bg-white/20 px-2 py-0.5 text-[10px] font-black uppercase tracking-[0.14em] text-white backdrop-blur-sm">
                    <?= htmlspecialchars($eyebrow) ?>
                </span>
                <h3 class="mt-3 text-lg font-black uppercase tracking-tight leading-snug">
                    <?= htmlspecialchars((string) $f['title']) ?>
                </h3>
                <?php if (($f['description'] ?? '') !== ''): ?>
                    <p class="mt-2 text-sm leading-relaxed text-white/90">
                        <?= htmlspecialchars((string) $f['description']) ?>
                    </p>
                <?php endif; ?>
            </div>
            <?php if (!empty($f['cta_href']) && ($f['cta_label'] ?? '') !== ''): ?>
                <a href="<?= htmlspecialchars((string) $f['cta_href']) ?>"
                   class="<?= htmlspecialchars($ctaClass) ?> mt-5 w-full shadow-lg ring-1 ring-white/20">
                    <?= htmlspecialchars((string) $f['cta_label']) ?>
                </a>
            <?php endif; ?>
        </div>
    </aside>
    <?php
};

?>
<div class="nav-mega-surface overflow-hidden rounded-[1.75rem] border border-slate-200/90">
    <?php if ($orderedSections === []): ?>
        <div class="<?= htmlspecialchars($layoutClass) ?> nav-mega-layout--featured-only">
            <?php $renderFeatured($feat); ?>
        </div>
    <?php else: ?>
        <div class="<?= htmlspecialchars($layoutClass) ?> nav-mega-layout--drill<?= $hasFeatured ? '' : ' nav-mega-layout--drill-solo' ?>">
            <div class="nav-mega-col nav-mega-col--muted border-b border-slate-200/80 lg:border-b-0 lg:border-r">
                <div class="nav-mega-drill nav-mega-drill--<?= htmlspecialchars($submenuStyle) ?>" data-nav-drill>
                    <div class="nav-mega-drill__viewport">
                        <div class="nav-mega-drill__pane nav-mega-drill__pane--root">
                            <div class="p-4 sm:p-5">
                                <p class="mb-3 text-[10px] font-black uppercase tracking-[0.18em] text-slate-500 sm:mb-3.5 sm:text-[11px]">
                                    Parcourir par thème
                                </p>
                                <div class="space-y-1" role="list">
                                    <?php foreach ($orderedSections as $si => $section): ?>
                                        <?php
                                        if (!is_array($section)) {
                                            continue;
                                        }
                                        $secTitle = trim((string) ($section['title'] ?? ''));
                                        if ($secTitle === '') {
                                            $secTitle = 'Autres accès';
                                        }
                                        $tplId = $megaId . '-sec-' . (string) $si;
                                        $btnId = $megaId . '-cat-' . (string) $si;
                                        $linkCount = count($section['links'] ?? []);
                                        $countLabel = $linkCount === 1 ? '1 accès' : $linkCount . ' accès';
                                        ?>
                                        <div role="listitem">
                                            <button type="button"
                                                    id="<?= htmlspecialchars($btnId) ?>"
                                                    class="nav-mega-drill__category"
                                                    data-nav-drill-target="<?= htmlspecialchars($tplId) ?>"
                                                    data-nav-drill-label="<?= htmlspecialchars($secTitle) ?>"
                                                    aria-expanded="false"
                                                    aria-controls="<?= htmlspecialchars($megaId) ?>-drill-detail">
                                                <span class="nav-mega-drill__category-text min-w-0">
                                                    <span class="nav-mega-drill__category-title"><?= htmlspecialchars($secTitle) ?></span>
                                                    <span class="nav-mega-drill__category-meta"><?= htmlspecialchars($countLabel) ?></span>
                                                </span>
                                                <svg class="nav-mega-drill__category-chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path fill-rule="evenodd" d="M7.22 14.78a.75.75 0 010-1.06L10.94 10 7.22 6.28a.75.75 0 111.06-1.06l4.25 4.25a.75.75 0 010 1.06l-4.25 4.25a.75.75 0 01-1.06 0z" clip-rule="evenodd"/>
                                                </svg>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div class="nav-mega-drill__pane nav-mega-drill__pane--detail"
                             aria-hidden="true"
                             inert>
                            <div class="p-4 sm:p-5">
                                <button type="button" class="nav-mega-drill__back" data-nav-drill-back>
                                    <svg class="nav-mega-drill__back-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M12.78 5.22a.75.75 0 010 1.06L9.06 10l3.72 3.72a.75.75 0 11-1.06 1.06l-4.25-4.25a.75.75 0 010-1.06l4.25-4.25a.75.75 0 011.06 0z" clip-rule="evenodd"/>
                                    </svg>
                                    <span>Retour aux thèmes</span>
                                </button>
                                <h2 class="nav-mega-drill__detail-heading mt-3 text-[10px] font-black uppercase tracking-[0.18em] text-slate-500 sm:text-[11px]"
                                    id="<?= htmlspecialchars($megaId) ?>-drill-detail"
                                    data-nav-drill-detail-title></h2>
                                <div class="nav-mega-drill__detail-body mt-2 space-y-1 sm:mt-2.5" data-nav-drill-detail-body></div>
                            </div>
                        </div>
                    </div>
                    <?php foreach ($orderedSections as $si => $section): ?>
                        <?php
                        if (!is_array($section)) {
                            continue;
                        }
                        $tplId = $megaId . '-sec-' . (string) $si;
                        ?>
                        <template id="<?= htmlspecialchars($tplId) ?>">
                            <?php $renderLinksFragment($section); ?>
                        </template>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php if ($hasFeatured) {
                $renderFeatured($feat);
            } ?>
        </div>
    <?php endif; ?>
</div>
