<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $tenantName */
/** @var string $eventTitle */
/** @var string $startsAt */
/** @var string $pointageUrl */

$et = htmlspecialchars((string) $eventTitle, ENT_QUOTES, 'UTF-8');
$tn = htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8');
$st = htmlspecialchars((string) $startsAt, ENT_QUOTES, 'UTF-8');

$body = '<p>Bonjour ' . htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8') . ',</p>'
    . '<p>Rappel : vous êtes inscrit(e) à <strong>' . $et . '</strong> sur <strong>' . $tn . '</strong>.</p>'
    . email_html_callout('<strong>Début prévu :</strong> ' . $st, 'info')
    . '<p>Le jour J, ouvrez le pointage pour enregistrer votre présence sur place.</p>'
    . email_html_button($pointageUrl, 'Ouvrir le pointage', 'emerald')
    . email_html_url_fallback($pointageUrl);

$html = email_html_layout(
    'Rappel événement — ' . $eventTitle,
    'Rappel — pointage',
    $body,
    ['accent' => 'emerald']
);

$text = "Bonjour {$displayName},\n\n"
    . "Rappel : {$eventTitle} — {$tenantName}\n"
    . "Début prévu : {$startsAt}\n\n"
    . "Pointage : {$pointageUrl}\n";

return ['html' => $html, 'text' => $text];
