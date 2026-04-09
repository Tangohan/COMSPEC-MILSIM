<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $tenantName */
/** @var string $senderLabel */
/** @var string $previewLine */
/** @var string $conversationUrl */

$previewSafe = htmlspecialchars((string) $previewLine, ENT_QUOTES, 'UTF-8');

$body = '<p>Bonjour ' . htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8') . ',</p>'
    . '<p>Nouveau message sur la messagerie interne de <strong>' . htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8') . '</strong>, de la part de <strong>' . htmlspecialchars((string) $senderLabel, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
    . email_html_callout('<strong>Aperçu :</strong><br>' . $previewSafe, 'info')
    . email_html_button($conversationUrl, 'Ouvrir la conversation', 'emerald')
    . email_html_url_fallback($conversationUrl);

$html = email_html_layout(
    'Nouveau message — messagerie interne',
    'Messagerie interne',
    $body,
    ['accent' => 'emerald']
);

$text = "Bonjour {$displayName},\n\n"
    . "Nouveau message sur « {$tenantName} » ({$senderLabel}).\n"
    . "Aperçu : {$previewLine}\n\n"
    . "Conversation : {$conversationUrl}\n";

return ['html' => $html, 'text' => $text];
