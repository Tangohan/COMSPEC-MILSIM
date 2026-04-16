<?php
declare(strict_types=1);

/**
 * Notifications flash globales pour les pages utilisant layout.main.
 * À inclure une seule fois par requête (consomme les clés session).
 */
$flash_toasts = [];
$s = \App\Core\Session::getFlash('success');
$e = \App\Core\Session::getFlash('error');
$i = \App\Core\Session::getFlash('info');
$w = \App\Core\Session::getFlash('warning');
if ($s !== null && trim((string) $s) !== '') {
    $flash_toasts[] = ['variant' => 'success', 'message' => (string) $s];
}
if ($e !== null && trim((string) $e) !== '') {
    $flash_toasts[] = ['variant' => 'error', 'message' => (string) $e];
}
if ($i !== null && trim((string) $i) !== '') {
    $flash_toasts[] = ['variant' => 'info', 'message' => (string) $i];
}
if ($w !== null && trim((string) $w) !== '') {
    $flash_toasts[] = ['variant' => 'warning', 'message' => (string) $w];
}
require base_path('views/partials/flash_toasts.php');
