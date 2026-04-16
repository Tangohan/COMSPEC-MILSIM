<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Cartographie interne des canaux de feedback utilisateur (flash, toasts, inline)
 * et des écrans encore dépendants de `window.confirm` / `confirm()` natif.
 *
 * Sert de référence unique pour les chantiers d’homogénéisation UX.
 */
final class UxFeedbackAudit
{
    /** Chemins des partiels / layout pour les notifications session. */
    public const FLASH_TOAST_LAYOUT = 'views/partials/layout_flash_toasts.php';

    public const FLASH_TOAST_PARTIAL = 'views/partials/flash_toasts.php';

    public const FLASH_INLINE_CARD = 'views/partials/flash_message.php';

    /**
     * Vues et scripts listés comme utilisant encore une confirmation native
     * (à migrer vers le dialogue réutilisable + {@see public/assets/js/ui_confirm_modal.js}).
     *
     * @return list<string>
     */
    public static function nativeConfirmLocations(): array
    {
        return [
            'views/training/my-training.php',
            'views/training/partials/lms_course_sidebar.php',
            'views/forum/topic.php',
            'views/admin/organization/dashboard_pins.php',
            'views/back_office/cooperation/missions/exchange.php',
            'views/back_office/cooperation/missions/negotiate.php',
            'views/partials/orbat/orbat_canvas.php',
            'public/assets/js/training_canvas_editor.js',
            'public/assets/js/forum/forum_category_context.js',
            'public/assets/js/forum-composer.js',
        ];
    }
}
