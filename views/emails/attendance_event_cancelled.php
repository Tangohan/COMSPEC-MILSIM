<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $tenantName */
/** @var string $eventTitle */
/** @var string $startsAt */
/** @var string $reason */
/** @var string $pointageUrl */

$reasonHtml = trim((string) $reason) !== ''
    ? email_html_callout('<strong>Motif :</strong><br>' . nl2br(htmlspecialchars((string) $reason, ENT_QUOTES, 'UTF-8')), 'warning')
    : '';

$body = '<p>Bonjour ' . htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8') . ',</p>'
    . '<p>L’événement <strong>' . htmlspecialchars((string) $eventTitle, ENT_QUOTES, 'UTF-8') . '</strong> '
    . '(' . htmlspecialchars((string) $startsAt, ENT_QUOTES, 'UTF-8') . ') sur '
    . '<strong>' . htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8') . '</strong> a été <strong>annulé</strong>.</p>'
    . $reasonHtml
    . email_html_button($pointageUrl, 'Agenda / pointage', 'slate');

$html = email_html_layout(
    'Événement annulé — ' . $eventTitle,
    'Événement annulé',
    $body,
    ['accent' => 'amber']
);

$text = "Bonjour {$displayName},\n\n"
    . "L’événement « {$eventTitle} » ({$startsAt}) sur « {$tenantName} » a été annulé.\n"
    . (trim((string) $reason) !== '' ? "Motif : {$reason}\n\n" : '')
    . "{$pointageUrl}\n";

return ['html' => $html, 'text' => $text];
