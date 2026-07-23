<?php

declare(strict_types=1);

/**
 * Sélecteur de langue compact (FR / EN).
 *
 * Variables optionnelles :
 * - $localeSwitcherClass : classes CSS additionnelles sur le conteneur
 * - $localeSwitcherVariant : 'light' | 'dark' (défaut dark)
 */
$currentLocale = function_exists('locale') ? locale() : 'fr';
$variant = $localeSwitcherVariant ?? 'dark';
$extraClass = trim((string) ($localeSwitcherClass ?? ''));
$isDark = $variant !== 'light';
$wrapClass = $isDark
    ? 'inline-flex items-center gap-0.5 rounded-lg border border-white/15 bg-white/5 p-0.5 text-[10px] font-bold uppercase tracking-[0.14em]'
    : 'inline-flex items-center gap-0.5 rounded-lg border border-slate-200 bg-white p-0.5 text-[10px] font-bold uppercase tracking-[0.14em] shadow-sm';
$activeClass = $isDark
    ? 'rounded-md bg-emerald-500/20 px-2 py-1 text-emerald-300'
    : 'rounded-md bg-slate-900 px-2 py-1 text-white';
$idleClass = $isDark
    ? 'rounded-md px-2 py-1 text-white/45 transition hover:bg-white/5 hover:text-white'
    : 'rounded-md px-2 py-1 text-slate-500 transition hover:bg-slate-50 hover:text-slate-900';
?>
<nav class="<?= htmlspecialchars(trim($wrapClass . ' ' . $extraClass), ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars(__('common.choose_language'), ENT_QUOTES, 'UTF-8') ?>">
    <?php foreach (['fr' => __('common.language_fr'), 'en' => __('common.language_en')] as $code => $label): ?>
        <?php if ($currentLocale === $code): ?>
            <span class="<?= $activeClass ?>" aria-current="true"><?= htmlspecialchars($code === 'fr' ? 'FR' : 'EN', ENT_QUOTES, 'UTF-8') ?></span>
        <?php else: ?>
            <a href="<?= htmlspecialchars(locale_switch_url($code), ENT_QUOTES, 'UTF-8') ?>" class="<?= $idleClass ?>" hreflang="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" lang="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($code === 'fr' ? 'FR' : 'EN', ENT_QUOTES, 'UTF-8') ?></a>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
