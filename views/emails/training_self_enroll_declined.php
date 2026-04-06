<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $tenantName */
/** @var string $courseTitle */
/** @var string $catalogUrl */

$name = htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8');
$tn = htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8');
$ct = htmlspecialchars((string) $courseTitle, ENT_QUOTES, 'UTF-8');

$body = '<p>Bonjour ' . $name . ',</p>'
    . '<p>Votre demande d’accès à la formation <strong>' . $ct . '</strong> sur <strong>' . $tn . '</strong> n’a <strong>pas été retenue</strong> pour l’instant.</p>'
    . '<p>Si vous pensez qu’il s’agit d’une erreur, contactez un référent formation au sein de votre communauté.</p>'
    . email_html_button($catalogUrl, 'Voir le catalogue des formations', 'slate')
    . email_html_url_fallback($catalogUrl);

$html = email_html_layout(
    'Inscription non retenue — ' . $courseTitle,
    'Réponse à votre demande',
    $body,
    ['accent' => 'slate']
);

$text = "Bonjour {$displayName},\n\n"
    . "Votre demande d’accès à « {$courseTitle} » sur « {$tenantName} » n’a pas été retenue.\n\n"
    . "Catalogue des formations : {$catalogUrl}\n";

return ['html' => $html, 'text' => $text];
