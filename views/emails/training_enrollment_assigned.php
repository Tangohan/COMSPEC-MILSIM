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
$cUrl = htmlspecialchars((string) $courseUrl, ENT_QUOTES, 'UTF-8');
$mUrl = htmlspecialchars((string) $myTrainingUrl, ENT_QUOTES, 'UTF-8');

$body = '<p>Bonjour ' . $name . ',</p>'
    . '<p>Une formation vous a été assignée sur <strong>' . $tn . '</strong> : <strong>' . $ct . '</strong>.</p>'
    . '<p>Vous pouvez commencer ou reprendre le parcours quand vous le souhaitez depuis votre espace.</p>'
    . email_html_button($courseUrl, 'Ouvrir la formation', 'emerald')
    . email_html_url_fallback($courseUrl)
    . '<p style="margin-top:20px;font-size:14px;color:#64748b;">Retrouvez aussi toutes vos formations sous « Mes formations ».</p>'
    . email_html_button($myTrainingUrl, 'Mes formations', 'slate')
    . email_html_url_fallback($myTrainingUrl);

$html = email_html_layout(
    'Nouvelle formation — ' . $courseTitle,
    'Formation assignée',
    $body,
    ['accent' => 'emerald']
);

$text = "Bonjour {$displayName},\n\n"
    . "Une formation vous a été assignée sur « {$tenantName} » : « {$courseTitle} ».\n\n"
    . "Ouvrir la formation : {$courseUrl}\n\n"
    . "Mes formations : {$myTrainingUrl}\n";

return ['html' => $html, 'text' => $text];
