<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $tenantName */
/** @var string $eventTitle */
/** @var string $startsAt */
/** @var string $statusLabel */
/** @var string $pointageUrl */

$body = '<p>Bonjour ' . htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8') . ',</p>'
    . '<p>Votre participation à <strong>' . htmlspecialchars((string) $eventTitle, ENT_QUOTES, 'UTF-8') . '</strong> '
    . 'sur <strong>' . htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8') . '</strong> est enregistrée.</p>'
    . email_html_callout('<strong>Statut :</strong> ' . htmlspecialchars((string) $statusLabel, ENT_QUOTES, 'UTF-8') . '<br><br><strong>Début prévu :</strong> ' . htmlspecialchars((string) $startsAt, ENT_QUOTES, 'UTF-8'), 'success')
    . email_html_button($pointageUrl, 'Voir le pointage et l’agenda', 'emerald');

$html = email_html_layout(
    'Participation enregistrée — ' . $eventTitle,
    'Participation enregistrée',
    $body,
    ['accent' => 'emerald']
);

$text = "Bonjour {$displayName},\n\n"
    . "Participation : {$eventTitle} — {$statusLabel}\n"
    . "Communauté : {$tenantName}\n"
    . "Début prévu : {$startsAt}\n\n"
    . "Pointage : {$pointageUrl}\n";

return ['html' => $html, 'text' => $text];
