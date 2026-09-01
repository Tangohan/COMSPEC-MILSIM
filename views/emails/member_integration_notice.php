<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $title */
/** @var string $actionUrl */

$name = htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8');
$t = htmlspecialchars((string) $title, ENT_QUOTES, 'UTF-8');

$body = '<p>Bonjour ' . $name . ',</p>'
    . '<p>' . $t . '</p>'
    . email_html_button($actionUrl, 'Ouvrir mon intégration', 'emerald')
    . email_html_url_fallback($actionUrl);

$html = email_html_layout('Parcours d’intégration', $title, $body, ['accent' => 'emerald']);
$text = "Bonjour {$displayName},\n\n{$title}\n\n{$actionUrl}\n";

return ['html' => $html, 'text' => $text];
