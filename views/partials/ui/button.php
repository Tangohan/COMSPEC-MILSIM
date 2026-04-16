<?php
declare(strict_types=1);
/**
 * Bouton ou lien stylé (design system léger).
 *
 * Variables :
 * - $ui_btn_variant : primary | secondary | danger (défaut primary)
 * - $ui_btn_label : libellé (obligatoire)
 * - $ui_btn_href : si défini, rendu en <a>
 * - $ui_btn_type : type bouton submit|button (défaut button) si pas href
 * - $ui_btn_class : classes Tailwind additionnelles
 * - $ui_btn_attrs : attributs HTML bruts optionnels (ex. aria-*)
 */
$ui_btn_variant = $ui_btn_variant ?? 'primary';
$ui_btn_label = isset($ui_btn_label) ? trim((string) $ui_btn_label) : '';
$ui_btn_href = isset($ui_btn_href) ? trim((string) $ui_btn_href) : '';
$ui_btn_type = $ui_btn_type ?? 'button';
$ui_btn_class = trim((string) ($ui_btn_class ?? ''));
$ui_btn_attrs = trim((string) ($ui_btn_attrs ?? ''));
if ($ui_btn_label === '') {
    return;
}
$base = 'inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 ';
$variants = [
    'primary' => 'bg-slate-900 text-white hover:bg-slate-800 focus-visible:ring-slate-500',
    'secondary' => 'border border-slate-200 bg-white text-slate-800 hover:bg-slate-50 focus-visible:ring-slate-400',
    'danger' => 'border border-rose-200 bg-rose-50 text-rose-900 hover:bg-rose-100 focus-visible:ring-rose-400',
];
$cls = $base . ($variants[$ui_btn_variant] ?? $variants['primary']) . ($ui_btn_class !== '' ? ' ' . $ui_btn_class : '');
if ($ui_btn_href !== '') {
    echo '<a href="' . htmlspecialchars($ui_btn_href, ENT_QUOTES, 'UTF-8') . '" class="' . htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') . '"' . ($ui_btn_attrs !== '' ? ' ' . $ui_btn_attrs : '') . '>' . htmlspecialchars($ui_btn_label, ENT_QUOTES, 'UTF-8') . '</a>';

    return;
}
$typeAttr = $ui_btn_type === 'submit' ? 'submit' : 'button';
echo '<button type="' . htmlspecialchars($typeAttr, ENT_QUOTES, 'UTF-8') . '" class="' . htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') . '"' . ($ui_btn_attrs !== '' ? ' ' . $ui_btn_attrs : '') . '>' . htmlspecialchars($ui_btn_label, ENT_QUOTES, 'UTF-8') . '</button>';
