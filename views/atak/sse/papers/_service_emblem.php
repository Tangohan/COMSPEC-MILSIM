<?php
declare(strict_types=1);
/**
 * Emblème générique d'un service : une forme vectorielle simple, la couleur du service et son sigle. Aucun sceau, logo ni insigne :
 * sept formes géométriques, les mêmes pour toutes les communautés. Les tracés sont aussi donnés au script de l'écran (aperçu en
 * direct), pour qu'il n'existe qu'une seule définition.
 */
if (!function_exists('sse_service_emblem_shapes')) {
    /** @return array<string, string> forme => tracé SVG dans une boîte de 48 × 48 */
    function sse_service_emblem_shapes(): array
    {
        return [
            'hex' => 'M24 3l18.2 10.5v21L24 45 5.8 34.5v-21z',
            'circle' => 'M24 3a21 21 0 100 42 21 21 0 000-42z',
            'diamond' => 'M24 2l22 22-22 22L2 24z',
            'bars' => 'M5 5h38v7H5zM5 15h38v18H5zM5 36h38v7H5z',
            'chevron' => 'M6 4h36v25L24 45 6 29z',
            'cross' => 'M15 3h18v12h12v18H33v12H15V33H3V15h12z',
            'ring' => 'M24 2a22 22 0 100 44 22 22 0 000-44zm0 4a18 18 0 110 36 18 18 0 010-36zM24 9a15 15 0 100 30 15 15 0 000-30z',
        ];
    }

    /**
     * @param string $shape forme (clé de sse_service_emblem_shapes)
     * @param string $hex couleur du service
     * @param string $label sigle (ou initiales) écrit dans la forme
     */
    function sse_service_emblem_svg(string $shape, string $hex, string $label, int $size = 40): string
    {
        $shapes = sse_service_emblem_shapes();
        $path = $shapes[$shape] ?? $shapes['hex'];
        $hex = preg_match('/^#[0-9A-Fa-f]{6}$/', $hex) === 1 ? $hex : '#1F3A5F';
        $label = mb_strtoupper(mb_substr(preg_replace('/[^\p{L}\p{N}]+/u', '', $label) ?? '', 0, 4));
        $font = [0 => 0, 1 => 17, 2 => 15, 3 => 12, 4 => 10][mb_strlen($label)] ?? 10;
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

        return '<svg class="svc-emblem" viewBox="0 0 48 48" width="' . $size . '" height="' . $size . '" aria-hidden="true" focusable="false">'
            . '<path d="' . $e($path) . '" fill="' . $e($hex) . '" stroke="rgba(255,255,255,0.42)" stroke-width="1.2" fill-rule="evenodd"/>'
            . ($label !== '' ? '<text x="24" y="24" text-anchor="middle" dominant-baseline="central" font-size="' . $font . '" font-weight="700" fill="#fff" font-family="system-ui, Arial, Helvetica">' . $e($label) . '</text>' : '')
            . '</svg>';
    }
}
