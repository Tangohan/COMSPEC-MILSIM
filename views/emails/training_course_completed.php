<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $tenantName */
/** @var string $courseTitle */
/** @var string $courseUrl */
/** @var string $myTrainingUrl */
/** @var bool $isCertifying */

$name = htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8');
$tn = htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8');
$ct = htmlspecialchars((string) $courseTitle, ENT_QUOTES, 'UTF-8');
$cUrl = htmlspecialchars((string) $courseUrl, ENT_QUOTES, 'UTF-8');
$mUrl = htmlspecialchars((string) $myTrainingUrl, ENT_QUOTES, 'UTF-8');
$cert = !empty($isCertifying);

$certBlock = $cert
    ? email_html_callout('Cette formation est certifiante : votre attestation est disponible depuis la page de la formation ou la section dédiée du portail, selon les réglages de votre communauté.', 'success')
    : '';

$body = '<p>Félicitations ' . $name . ',</p>'
    . '<p>Vous avez mené à bien l’ensemble du parcours <strong>' . $ct . '</strong> sur <strong>' . $tn . '</strong>. Bravo pour votre engagement.</p>'
    . $certBlock
    . email_html_button($courseUrl, 'Voir la formation', 'emerald')
    . email_html_url_fallback($courseUrl)
    . '<p style="margin-top:20px;font-size:14px;color:#64748b;">Conservez ce message comme trace de votre accomplissement si besoin.</p>'
    . email_html_button($myTrainingUrl, 'Mes formations', 'slate')
    . email_html_url_fallback($myTrainingUrl);

$html = email_html_layout(
    'Félicitations — ' . $courseTitle,
    'Parcours terminé',
    $body,
    ['accent' => 'emerald']
);

$certLine = $cert ? "Cette formation est certifiante : consultez le portail pour votre attestation.\n\n" : '';

$text = "Bonjour {$displayName},\n\n"
    . "Félicitations : vous avez mené à bien l’ensemble du parcours « {$courseTitle} » sur « {$tenantName} ».\n\n"
    . $certLine
    . "Page de la formation : {$courseUrl}\n\n"
    . "Mes formations : {$myTrainingUrl}\n";

return ['html' => $html, 'text' => $text];
