<?php

declare(strict_types=1);

/** @var string $staffDisplayName */
/** @var string $requesterDisplayName */
/** @var string $tenantName */
/** @var string $courseTitle */
/** @var string $studioFicheUrl */
/** @var string $requesterMemberUrl */

$staff = htmlspecialchars((string) $staffDisplayName, ENT_QUOTES, 'UTF-8');
$req = htmlspecialchars((string) $requesterDisplayName, ENT_QUOTES, 'UTF-8');
$tn = htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8');
$ct = htmlspecialchars((string) $courseTitle, ENT_QUOTES, 'UTF-8');

$body = '<p>Bonjour ' . $staff . ',</p>'
    . '<p><strong>' . $req . '</strong>'
    . ' demande de pouvoir <strong>publier</strong> la formation <strong>' . $ct . '</strong>'
    . ' dans la communauté <strong>' . $tn . '</strong>.</p>'
    . '<p>Vous pouvez soit publier la fiche depuis le Studio, soit lui attribuer le droit de publication via la fiche membre (administration de la communauté).</p>'
    . email_html_button($studioFicheUrl, 'Ouvrir la fiche Studio', 'emerald')
    . email_html_url_fallback($studioFicheUrl)
    . email_html_button($requesterMemberUrl, 'Ouvrir la fiche membre', 'blue')
    . email_html_url_fallback($requesterMemberUrl);

$html = email_html_layout(
    'Publication demandée — ' . $courseTitle,
    'Élévation de droits formation',
    $body,
    ['accent' => 'blue']
);

$text = "Bonjour {$staffDisplayName},\n\n"
    . "« {$requesterDisplayName} »"
    . " demande de pouvoir publier la formation « {$courseTitle} » sur « {$tenantName} ».\n\n"
    . "Fiche Studio : {$studioFicheUrl}\n"
    . "Fiche membre : {$requesterMemberUrl}\n";

return ['html' => $html, 'text' => $text];
