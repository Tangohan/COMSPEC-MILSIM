<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $tenantName */
/** @var string $eventTitle */
/** @var string $startsAt */
/** @var string $pointageUrl */

$body = '<p>Bonjour ' . htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8') . ',</p>'
    . '<p>Votre <strong>présence</strong> a bien été enregistrée.</p>'
    . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="font-size:15px;margin:8px 0 20px;">'
    . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#64748b;">Événement</td>'
    . '<td style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-weight:600;color:#0f172a;">' . htmlspecialchars((string) $eventTitle, ENT_QUOTES, 'UTF-8') . '</td></tr>'
    . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#64748b;">Horaire</td>'
    . '<td style="padding:8px 0;border-bottom:1px solid #e2e8f0;">' . htmlspecialchars((string) $startsAt, ENT_QUOTES, 'UTF-8') . '</td></tr>'
    . '<tr><td style="padding:8px 0;color:#64748b;">Communauté</td>'
    . '<td style="padding:8px 0;font-weight:600;">' . htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8') . '</td></tr>'
    . '</table>'
    . email_html_button($pointageUrl, 'Retour au pointage', 'emerald');

$html = email_html_layout(
    'Présence enregistrée — ' . $eventTitle,
    'Présence confirmée',
    $body,
    ['accent' => 'emerald']
);

$text = "Bonjour {$displayName},\n\n"
    . "Présence enregistrée : {$eventTitle} ({$startsAt}) — {$tenantName}\n\n"
    . "{$pointageUrl}\n";

return ['html' => $html, 'text' => $text];
