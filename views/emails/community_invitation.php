<?php

declare(strict_types=1);

/** @var string $tenantName */
/** @var string $acceptUrl */
/** @var string $roleLabel */
/** @var string $inviterLabel */

$tn = htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8');
$rl = htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8');
$il = htmlspecialchars($inviterLabel, ENT_QUOTES, 'UTF-8');

$html = '<p>Bonjour,</p>'
    . '<p>Vous êtes invité à rejoindre la communauté <strong>' . $tn . '</strong>'
    . ($rl !== '' ? ' (rôle proposé : ' . $rl . ')' : '')
    . '. Invitation envoyée par ' . $il . '.</p>'
    . '<p><a href="' . htmlspecialchars($acceptUrl, ENT_QUOTES, 'UTF-8') . '">Accepter l’invitation</a></p>'
    . '<p>Ce lien est valable 7 jours.</p>';

$text = "Bonjour,\n\nVous êtes invité à rejoindre la communauté « {$tenantName} »"
    . ($roleLabel !== '' ? " (rôle proposé : {$roleLabel})" : '')
    . ". Invitation envoyée par {$inviterLabel}.\n\nAcceptez l’invitation :\n{$acceptUrl}\n\n(Lien valable 7 jours.)\n";

return ['html' => $html, 'text' => $text];
