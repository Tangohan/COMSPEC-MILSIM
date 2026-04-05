<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $tenantName */
/** @var string $eventTitle */
/** @var string $startsAt */
/** @var string $reason */
/** @var string $pointageUrl */

$reasonHtml = trim((string) $reason) !== ''
    ? '<p>Motif : ' . nl2br(htmlspecialchars((string) $reason, ENT_QUOTES, 'UTF-8')) . '</p>'
    : '';

$html = '<p>Bonjour ' . htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8') . ',</p>'
    . '<p>L’événement <strong>' . htmlspecialchars((string) $eventTitle, ENT_QUOTES, 'UTF-8') . '</strong> '
    . '(' . htmlspecialchars((string) $startsAt, ENT_QUOTES, 'UTF-8') . ') prévu sur '
    . '<strong>' . htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8') . '</strong> a été <strong>annulé</strong>.</p>'
    . $reasonHtml
    . '<p><a href="' . htmlspecialchars((string) $pointageUrl, ENT_QUOTES, 'UTF-8') . '">Agenda / pointage</a></p>';

$text = "Bonjour {$displayName},\n\n"
    . "L’événement « {$eventTitle} » ({$startsAt}) sur « {$tenantName} » a été annulé.\n"
    . (trim((string) $reason) !== '' ? "Motif : {$reason}\n\n" : '')
    . "{$pointageUrl}\n";

return ['html' => $html, 'text' => $text];
