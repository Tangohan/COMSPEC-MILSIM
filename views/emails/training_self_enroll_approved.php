<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $tenantName */
/** @var string $courseTitle */
/** @var string $courseUrl */
/** @var string $myTrainingUrl */

$name = htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8');
$tn = htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8');
$ct = htmlspecialchars((string) $courseTitle, ENT_QUOTES, 'UTF-8');

$body = '<p>Bonjour ' . $name . ',</p>'
    . '<p>Votre demande d’accès à la formation <strong>' . $ct . '</strong> sur <strong>' . $tn . '</strong> a été <strong>acceptée</strong>.</p>'
    . '<p>Vous pouvez commencer le parcours dès maintenant.</p>'
    . email_html_button($courseUrl, 'Ouvrir la formation', 'emerald')
    . email_html_url_fallback($courseUrl)
    . '<p style="margin-top:20px;font-size:14px;color:#64748b;">Retrouvez aussi toutes vos formations sous « Mes formations ».</p>'
    . email_html_button($myTrainingUrl, 'Mes formations', 'slate')
    . email_html_url_fallback($myTrainingUrl);

$html = email_html_layout(
    'Inscription acceptée — ' . $courseTitle,
    'Accès confirmé',
    $body,
    ['accent' => 'emerald']
);

$text = "Bonjour {$displayName},\n\n"
    . "Votre demande d’accès à « {$courseTitle} » sur « {$tenantName} » a été acceptée.\n\n"
    . "Ouvrir la formation : {$courseUrl}\n\n"
    . "Mes formations : {$myTrainingUrl}\n";

return ['html' => $html, 'text' => $text];
