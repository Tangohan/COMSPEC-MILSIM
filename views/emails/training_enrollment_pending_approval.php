<?php

declare(strict_types=1);

/** @var string $staffDisplayName */
/** @var string $learnerDisplayName */
/** @var string $learnerEmail */
/** @var string $tenantName */
/** @var string $courseTitle */
/** @var string $reviewUrl */
/** @var int $enrollmentId */

$staff = htmlspecialchars((string) $staffDisplayName, ENT_QUOTES, 'UTF-8');
$learner = htmlspecialchars((string) $learnerDisplayName, ENT_QUOTES, 'UTF-8');
$lem = htmlspecialchars((string) $learnerEmail, ENT_QUOTES, 'UTF-8');
$tn = htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8');
$ct = htmlspecialchars((string) $courseTitle, ENT_QUOTES, 'UTF-8');
$rUrl = htmlspecialchars((string) $reviewUrl, ENT_QUOTES, 'UTF-8');

$body = '<p>Bonjour ' . $staff . ',</p>'
    . '<p><strong>' . $learner . '</strong> souhaite rejoindre la formation <strong>' . $ct . '</strong> sur <strong>' . $tn . '</strong>.</p>'
    . '<p>Coordonnées de l’apprenant : <a href="mailto:' . $lem . '">' . $lem . '</a></p>'
    . '<p>Vous pouvez accepter ou refuser cette demande depuis l’espace d’administration des formations.</p>'
    . email_html_button($reviewUrl, 'Traiter les inscriptions', 'emerald')
    . email_html_url_fallback($reviewUrl);

$html = email_html_layout(
    'Inscription à valider — ' . $courseTitle,
    'Demande d’inscription',
    $body,
    ['accent' => 'amber']
);

$text = "Bonjour {$staffDisplayName},\n\n"
    . "« {$learnerDisplayName} » ({$learnerEmail}) demande l’accès à la formation « {$courseTitle} » sur « {$tenantName} ».\n\n"
    . "Traiter la demande : {$reviewUrl}\n";

return ['html' => $html, 'text' => $text];
