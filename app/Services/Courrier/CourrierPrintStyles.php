<?php

declare(strict_types=1);

namespace App\Services\Courrier;

/**
 * Feuille de style partagée : aperçu éditeur, impression navigateur, PDF Dompdf.
 */
final class CourrierPrintStyles
{
    public static function cssPath(): string
    {
        return base_path('public/assets/css/courrier-document.css');
    }

    public static function inlineCss(): string
    {
        $path = self::cssPath();
        if (!is_file($path)) {
            return '/* courrier-document.css manquant */';
        }
        return (string) file_get_contents($path);
    }

    /**
     * Lien Google Fonts Inter (identique au layout principal).
     */
    public static function interFontLink(): string
    {
        return 'https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap';
    }
}
