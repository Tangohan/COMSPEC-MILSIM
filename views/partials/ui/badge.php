<?php
declare(strict_types=1);
/**
 * Badge compact.
 *
 * - $ui_badge_label (string)
 * - $ui_badge_variant : neutral | success | warning | info (défaut neutral)
 */
$ui_badge_label = isset($ui_badge_label) ? trim((string) $ui_badge_label) : '';
$ui_badge_variant = $ui_badge_variant ?? 'neutral';
if ($ui_badge_label === '') {
    return;
}
$map = [
    'neutral' => 'bg-slate-100 text-slate-700 ring-slate-200/80',
    'success' => 'bg-emerald-100 text-emerald-900 ring-emerald-200/80',
    'warning' => 'bg-amber-100 text-amber-950 ring-amber-200/80',
    'info' => 'bg-sky-100 text-sky-900 ring-sky-200/80',
];
$wrap = $map[$ui_badge_variant] ?? $map['neutral'];
?>
<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide ring-1 ring-inset <?= htmlspecialchars($wrap, ENT_QUOTES, 'UTF-8') ?>">
    <?= htmlspecialchars($ui_badge_label, ENT_QUOTES, 'UTF-8') ?>
</span>
