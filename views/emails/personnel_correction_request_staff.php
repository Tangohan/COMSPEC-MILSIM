<?php

declare(strict_types=1);

/** @var string $staffDisplayName */
/** @var string $requesterDisplayName */
/** @var string $tenantName */
/** @var string $targetDisplayName */
/** @var list<string> $diffLines */
/** @var string $note */
/** @var string $queueUrl */
/** @var string $ficheUrl */

$staff = htmlspecialchars((string) $staffDisplayName, ENT_QUOTES, 'UTF-8');
$req = htmlspecialchars((string) $requesterDisplayName, ENT_QUOTES, 'UTF-8');
$tn = htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8');
$target = htmlspecialchars((string) $targetDisplayName, ENT_QUOTES, 'UTF-8');
$noteHtml = trim((string) $note) !== ''
    ? '<p><strong>Message :</strong> ' . htmlspecialchars((string) $note, ENT_QUOTES, 'UTF-8') . '</p>'
    : '';
$diffHtml = '';
foreach ($diffLines as $line) {
    $diffHtml .= '<li>' . htmlspecialchars((string) $line, ENT_QUOTES, 'UTF-8') . '</li>';
}

$body = '<p>Bonjour ' . $staff . ',</p>'
    . '<p><strong>' . $req . '</strong>'
    . ' propose une <strong>correction RH</strong> sur la fiche de <strong>' . $target . '</strong>'
    . ' (« ' . $tn . ' »).</p>'
    . '<p>Modifications demandées :</p><ul>' . $diffHtml . '</ul>'
    . $noteHtml
    . '<p>Confirmez ou refusez depuis la file de validation. Aucune modification n’est appliquée avant votre décision.</p>'
    . email_html_button($queueUrl, 'Traiter les corrections', 'emerald')
    . email_html_url_fallback($queueUrl)
    . email_html_button($ficheUrl, 'Ouvrir la fiche', 'blue')
    . email_html_url_fallback($ficheUrl);

$html = email_html_layout(
    'Correction RH à valider — ' . $targetDisplayName,
    'Validation fiche opérateur',
    $body,
    ['accent' => 'blue']
);

$text = "Bonjour {$staffDisplayName},\n\n"
    . "« {$requesterDisplayName} »"
    . " propose une correction RH pour « {$targetDisplayName} » ({$tenantName}).\n\n"
    . implode("\n", $diffLines) . "\n\n"
    . (trim((string) $note) !== '' ? "Message : {$note}\n\n" : '')
    . "File : {$queueUrl}\n"
    . "Fiche : {$ficheUrl}\n";

return ['html' => $html, 'text' => $text];
