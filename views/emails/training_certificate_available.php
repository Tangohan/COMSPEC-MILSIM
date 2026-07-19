<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $tenantName */
/** @var string $courseTitle */
/** @var string $certificateUrl */
/** @var string $myTrainingUrl */

$name = htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8');
$tn = htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8');
$ct = htmlspecialchars((string) $courseTitle, ENT_QUOTES, 'UTF-8');
$cUrl = htmlspecialchars((string) $certificateUrl, ENT_QUOTES, 'UTF-8');
$mUrl = htmlspecialchars((string) $myTrainingUrl, ENT_QUOTES, 'UTF-8');

$body = '<p>Bonjour ' . $name . ',</p>'
    . '<p>Votre attestation pour la formation <strong>' . $ct . '</strong> est désormais disponible sur <strong>' . $tn . '</strong>.</p>'
    . '<p>Vous pouvez la consulter et la télécharger depuis votre espace formations.</p>'
    . email_html_button($certificateUrl, 'Voir mon attestation', 'emerald')
    . email_html_url_fallback($certificateUrl)
    . '<p style="margin-top:20px;font-size:14px;color:#64748b;">Retrouvez aussi l’ensemble de vos parcours sous « Mes formations ».</p>'
    . email_html_button($myTrainingUrl, 'Mes formations', 'slate')
    . email_html_url_fallback($myTrainingUrl);

$html = email_html_layout(
    'Attestation disponible — ' . $courseTitle,
    'Document prêt',
    $body,
    ['accent' => 'emerald']
);

$text = "Bonjour {$displayName},\n\n"
    . "Votre attestation pour la formation « {$courseTitle} » est désormais disponible sur « {$tenantName} ».\n\n"
    . "Consulter l’attestation : {$certificateUrl}\n\n"
    . "Mes formations : {$myTrainingUrl}\n";

return ['html' => $html, 'text' => $text];
