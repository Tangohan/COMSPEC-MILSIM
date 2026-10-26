<?php
declare(strict_types=1);

/**
 * Toasts « accès refusé » (AccessDeniedStayMiddleware) pour les shells qui affichent les autres
 * messages en bandeau dans la page (back-office, administration, messagerie).
 */
$flash_toasts = [];
foreach (['error', 'warning'] as $toastKey) {
    $athToastMsg = \App\Core\Session::getFlash('toast_' . $toastKey);
    if ($athToastMsg !== null && trim((string) $athToastMsg) !== '') {
        $flash_toasts[] = ['variant' => $toastKey, 'message' => (string) $athToastMsg];
    }
}
require base_path('views/partials/flash_toasts.php');
