<?php
declare(strict_types=1);

if (!function_exists('eff_unit_code_html')) {
    /**
     * Plaque du code unité : un segment par échelon (SOAR | B | A | 1), puis l'identifiant
     * administratif (0042-0137) en plus petit. Vide si l'unité n'a pas encore de code.
     */
    function eff_unit_code_html(string $code, string $adminId = '', string $modifier = ''): string
    {
        $code = trim($code);
        if ($code === '') {
            return '';
        }
        $segments = explode('-', $code);
        $html = '<span class="eff-ucode' . ($modifier !== '' ? ' eff-ucode--' . htmlspecialchars($modifier, ENT_QUOTES, 'UTF-8') : '') . '" translate="no"'
            . ' title="' . htmlspecialchars('Code unité ' . $code . ($adminId !== '' ? ' · identifiant ' . $adminId : ''), ENT_QUOTES, 'UTF-8') . '">'
            . '<span class="eff-ucode__plate" aria-label="' . htmlspecialchars('Code unité ' . $code, ENT_QUOTES, 'UTF-8') . '">';
        foreach ($segments as $i => $seg) {
            $html .= '<span class="eff-ucode__seg' . ($i === 0 ? ' is-root' : '') . '">' . htmlspecialchars($seg, ENT_QUOTES, 'UTF-8') . '</span>';
        }
        $html .= '</span>';
        if ($adminId !== '') {
            $html .= '<span class="eff-ucode__id" aria-label="' . htmlspecialchars('Identifiant ' . $adminId, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($adminId, ENT_QUOTES, 'UTF-8') . '</span>';
        }

        return $html . '</span>';
    }
}
