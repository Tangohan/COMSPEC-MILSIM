<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $tenantName */
/** @var string $summaryLine */
/** @var string $moderationUrl */

$summarySafe = htmlspecialchars((string) $summaryLine, ENT_QUOTES, 'UTF-8');

$body = '<p>Bonjour ' . htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8') . ',</p>'
    . '<p>Un dossier de signalement a été <strong>rouvert</strong> sur <strong>' . htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8') . '</strong> et est de nouveau à traiter dans la file.</p>'
    . email_html_callout('<strong>Rappel du motif :</strong><br>' . $summarySafe, 'warning')
    . email_html_button($moderationUrl, 'Ouvrir la console de modération', 'rose')
    . email_html_url_fallback($moderationUrl);

$html = email_html_layout(
    'Signalement rouvert — action requise',
    'Dossier rouvert',
    $body,
    ['accent' => 'rose']
);

$text = "Bonjour {$displayName},\n\n"
    . "Un dossier de signalement a été rouvert sur « {$tenantName} ».\n"
    . "Rappel : {$summaryLine}\n\n"
    . "Console modération : {$moderationUrl}\n";

return ['html' => $html, 'text' => $text];
