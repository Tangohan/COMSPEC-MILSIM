<?php
declare(strict_types=1);

$user = $user ?? [];
$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');

$accountNavKey = 'devices';
$accountTitle = 'Appareils ATAK';
$accountLead = 'Votre téléphone ATAK tel que le réseau le voit : identité, liaison, certificat et diagnostic. Retirez un appareil s’il n’est plus le vôtre.';
$accountUser = $user;
require base_path('views/partials/account/shell_open.php');

// Fiches partagées avec le back-office membre (Mes appareils) ; ici les actions restent sur la page compte.
$revokeUrl = url('account/security/devices/revoke');
$setupUrl = url('atak/premiere-liaison');
?>

<div class="bo-member-situation atk-dev account-atak-devices">
    <?php require base_path('views/partials/atak_device_cards.php'); ?>
</div>

<?php require base_path('views/partials/account/shell_close.php'); ?>
