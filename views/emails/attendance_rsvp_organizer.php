<?php

declare(strict_types=1);

/** @var string $organizerName */
/** @var string $tenantName */
/** @var string $eventTitle */
/** @var string $startsAt */
/** @var string $participantName */
/** @var string $statusLabel */
/** @var string $eventsUrl */

$body = '<p>Bonjour ' . htmlspecialchars((string) $organizerName, ENT_QUOTES, 'UTF-8') . ',</p>'
    . '<p><strong>' . htmlspecialchars((string) $participantName, ENT_QUOTES, 'UTF-8') . '</strong> a mis à jour sa participation à l’activité '
    . '<strong>' . htmlspecialchars((string) $eventTitle, ENT_QUOTES, 'UTF-8') . '</strong> sur <strong>' . htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
    . email_html_callout('<strong>Nouveau statut :</strong> ' . htmlspecialchars((string) $statusLabel, ENT_QUOTES, 'UTF-8') . '<br><br><strong>Début prévu :</strong> ' . htmlspecialchars((string) $startsAt, ENT_QUOTES, 'UTF-8'), 'info')
    . email_html_button($eventsUrl, 'Voir les événements', 'emerald')
    . email_html_url_fallback($eventsUrl);

$html = email_html_layout(
    'Participation mise à jour — ' . $eventTitle,
    'Participation mise à jour',
    $body,
    ['accent' => 'emerald']
);

$text = "Bonjour {$organizerName},\n\n"
    . "{$participantName} a mis à jour sa participation à « {$eventTitle} » ({$tenantName}).\n"
    . "Statut : {$statusLabel}\n"
    . "Début prévu : {$startsAt}\n\n"
    . "Événements : {$eventsUrl}\n";

return ['html' => $html, 'text' => $text];
