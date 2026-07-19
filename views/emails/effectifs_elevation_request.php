<?php

declare(strict_types=1);

/** @var string $staffDisplayName */
/** @var string $requesterDisplayName */
/** @var string $requesterEmail */
/** @var string $tenantName */
/** @var string $targetDisplayName */
/** @var string $elevationKindLabel */
/** @var string $note */
/** @var string $memberUrl */
/** @var string $editUrl */

$staff = htmlspecialchars((string) $staffDisplayName, ENT_QUOTES, 'UTF-8');
$req = htmlspecialchars((string) $requesterDisplayName, ENT_QUOTES, 'UTF-8');
$reqMail = htmlspecialchars((string) $requesterEmail, ENT_QUOTES, 'UTF-8');
$tn = htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8');
$target = htmlspecialchars((string) $targetDisplayName, ENT_QUOTES, 'UTF-8');
$kind = htmlspecialchars((string) $elevationKindLabel, ENT_QUOTES, 'UTF-8');
$noteHtml = trim((string) $note) !== ''
    ? '<p><strong>Message :</strong> ' . htmlspecialchars((string) $note, ENT_QUOTES, 'UTF-8') . '</p>'
    : '';

$body = '<p>Bonjour ' . $staff . ',</p>'
    . '<p><strong>' . $req . '</strong>'
    . ($requesterEmail !== '' ? ' (<a href="mailto:' . $reqMail . '">' . $reqMail . '</a>)' : '')
    . ' demande une <strong>élévation</strong> (« ' . $kind . ' ») pour le membre <strong>' . $target . '</strong>'
    . ' dans la communauté <strong>' . $tn . '</strong>.</p>'
    . $noteHtml
    . '<p>Vous pouvez traiter la demande depuis la fiche effectifs ou la fiche membre (administration de la communauté).</p>'
    . email_html_button($memberUrl, 'Ouvrir la fiche effectifs', 'emerald')
    . email_html_url_fallback($memberUrl)
    . email_html_button($editUrl, 'Ouvrir la fiche membre', 'blue')
    . email_html_url_fallback($editUrl);

$html = email_html_layout(
    'Élévation demandée — ' . $targetDisplayName,
    'Élévation RH effectifs',
    $body,
    ['accent' => 'blue']
);

$text = "Bonjour {$staffDisplayName},\n\n"
    . "« {$requesterDisplayName} »"
    . ($requesterEmail !== '' ? " ({$requesterEmail})" : '')
    . " demande une élévation (« {$elevationKindLabel} ») pour « {$targetDisplayName} » sur « {$tenantName} ».\n\n"
    . (trim((string) $note) !== '' ? "Message : {$note}\n\n" : '')
    . "Fiche effectifs : {$memberUrl}\n"
    . "Fiche membre : {$editUrl}\n";

return ['html' => $html, 'text' => $text];
