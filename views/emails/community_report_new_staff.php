<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $tenantName */
/** @var string $summaryLine */
/** @var string $moderationUrl */

$summarySafe = htmlspecialchars((string) $summaryLine, ENT_QUOTES, 'UTF-8');

$body = '<p>Bonjour ' . htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8') . ',</p>'
    . '<p>Un nouveau signalement ou une nouvelle demande nécessite votre attention sur <strong>' . htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
    . email_html_callout('<strong>Aperçu :</strong><br>' . $summarySafe, 'warning')
    . email_html_button($moderationUrl, 'Ouvrir la console de modération', 'rose')
    . email_html_url_fallback($moderationUrl);

$html = email_html_layout(
    'Nouveau signalement — action requise',
    'Nouveau signalement',
    $body,
    ['accent' => 'rose']
);

$text = "Bonjour {$displayName},\n\n"
    . "Nouveau signalement sur « {$tenantName} ».\n"
    . "Aperçu : {$summaryLine}\n\n"
    . "Console modération : {$moderationUrl}\n";

return ['html' => $html, 'text' => $text];
