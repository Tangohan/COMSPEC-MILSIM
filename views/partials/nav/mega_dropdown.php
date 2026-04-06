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
$slots = navigation_group_sections_by_slot($item['sections'] ?? []);
$feat = is_array($item['featured'] ?? null) ? $item['featured'] : null;

$renderLinks = static function (array $section) use ($currentPath): void {
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
                <p class="text-[13px] font-bold leading-snug text-slate-950 sm:text-sm">
                    <?= htmlspecialchars((string) ($link['label'] ?? '')) ?>
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

$renderSectionColumn = static function (array $sections, string $colClass) use ($renderLinks): void {
    if ($sections === []) {
        return;
    }
    ?>
    <div class="<?= htmlspecialchars($colClass) ?>">
        <?php foreach ($sections as $section): ?>
            <?php if (!is_array($section)) {
                continue;
            } ?>
            <div class="nav-mega-col border-b border-slate-200/80 p-4 last:border-b-0 sm:p-5">
                <p class="mb-2.5 text-[10px] font-black uppercase tracking-[0.18em] text-slate-500 sm:mb-3 sm:text-[11px]">
                    <?= htmlspecialchars((string) ($section['title'] ?? '')) ?>
                </p>
                <div class="space-y-1">
                    <?php $renderLinks($section); ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
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

$primary = $slots['primary'];
$center = $slots['center'];
$secondary = $slots['secondary'];

?>
<div class="nav-mega-surface overflow-hidden rounded-[1.75rem] border border-slate-200/90">
    <div class="<?= htmlspecialchars($layoutClass) ?>">
        <div class="nav-mega-col nav-mega-col--muted border-b border-slate-200/80 lg:border-b-0 lg:border-r">
            <?php $renderSectionColumn($primary, ''); ?>
        </div>
        <div class="nav-mega-col border-b border-slate-200/80 lg:border-b-0 lg:border-r">
            <?php $renderSectionColumn($center, ''); ?>
            <?php if ($secondary !== []) {
                $renderSectionColumn($secondary, '');
            } ?>
        </div>
        <?php $renderFeatured($feat); ?>
    </div>
</div>
