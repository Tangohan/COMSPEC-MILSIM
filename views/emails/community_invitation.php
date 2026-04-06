<?php

declare(strict_types=1);

/** @var string $tenantName */
/** @var string $acceptUrl */
/** @var string $roleLabel */
/** @var string $inviterLabel */

$tn = htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8');
$rl = htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8');
$il = htmlspecialchars($inviterLabel, ENT_QUOTES, 'UTF-8');

$roleLine = $roleLabel !== ''
    ? '<p style="margin:0 0 16px;">Rôle proposé : <strong>' . $rl . '</strong></p>'
    : '';

$body = '<p>Bonjour,</p>'
    . '<p>Vous êtes invité(e) à rejoindre la communauté <strong>' . $tn . '</strong>.</p>'
    . $roleLine
    . '<p style="font-size:14px;color:#64748b;">Invitation envoyée par <strong>' . $il . '</strong>.</p>'
    . email_html_button($acceptUrl, 'Accepter l’invitation', 'indigo')
    . email_html_url_fallback($acceptUrl)
    . email_html_callout('<strong>Validité : 7 jours.</strong> Passé ce délai, demandez une nouvelle invitation à un administrateur.', 'info');

$html = email_html_layout(
    'Invitation — ' . $tenantName,
    'Vous êtes invité(e)',
    $body,
    ['accent' => 'indigo']
);

$text = "Bonjour,\n\nVous êtes invité à rejoindre la communauté « {$tenantName} »"
    . ($roleLabel !== '' ? " (rôle proposé : {$roleLabel})" : '')
    . ". Invitation envoyée par {$inviterLabel}.\n\nAcceptez l’invitation :\n{$acceptUrl}\n\n(Lien valable 7 jours.)\n";

return ['html' => $html, 'text' => $text];
