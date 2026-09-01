<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $title */
/** @var string $actionUrl */

$name = htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8');
$t = htmlspecialchars((string) $title, ENT_QUOTES, 'UTF-8');

$body = '<p>Bonjour ' . $name . ',</p>'
    . '<p>Le rendez-vous <strong>' . $t . '</strong> a été annulé.</p>'
    . email_html_button($actionUrl, 'Voir mon intégration', 'slate')
    . email_html_url_fallback($actionUrl);

$html = email_html_layout('Rendez-vous annulé', 'Annulation', $body, ['accent' => 'rose']);
$text = "Bonjour {$displayName},\n\nLe rendez-vous « {$title} » a été annulé.\n{$actionUrl}\n";

return ['html' => $html, 'text' => $text];
