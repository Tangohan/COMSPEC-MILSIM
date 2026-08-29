<?php

declare(strict_types=1);

/** @var string $recipientDisplayName */
/** @var string $tenantName */
/** @var string $targetDisplayName */
/** @var string $decisionLabel */
/** @var string $resolverDisplayName */
/** @var list<string> $diffLines */
/** @var string $resolutionNote */
/** @var string $ficheUrl */

$who = htmlspecialchars((string) $recipientDisplayName, ENT_QUOTES, 'UTF-8');
$tn = htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8');
$target = htmlspecialchars((string) $targetDisplayName, ENT_QUOTES, 'UTF-8');
$decision = htmlspecialchars((string) $decisionLabel, ENT_QUOTES, 'UTF-8');
$resolver = htmlspecialchars((string) $resolverDisplayName, ENT_QUOTES, 'UTF-8');
$noteHtml = trim((string) $resolutionNote) !== ''
    ? '<p><strong>Commentaire :</strong> ' . htmlspecialchars((string) $resolutionNote, ENT_QUOTES, 'UTF-8') . '</p>'
    : '';
$diffHtml = '';
foreach ($diffLines as $line) {
    $diffHtml .= '<li>' . htmlspecialchars((string) $line, ENT_QUOTES, 'UTF-8') . '</li>';
}

$body = '<p>Bonjour ' . $who . ',</p>'
    . '<p>La demande de correction RH pour <strong>' . $target . '</strong> a été <strong>' . $decision . '</strong>'
    . ' par <strong>' . $resolver . '</strong> (« ' . $tn . ' »).</p>'
    . '<p>Champs concernés :</p><ul>' . $diffHtml . '</ul>'
    . $noteHtml
    . email_html_button($ficheUrl, 'Ouvrir la fiche', 'emerald')
    . email_html_url_fallback($ficheUrl);

$html = email_html_layout(
    'Correction RH ' . $decisionLabel . ' — ' . $targetDisplayName,
    'Décision fiche opérateur',
    $body,
    ['accent' => 'blue']
);

$text = "Bonjour {$recipientDisplayName},\n\n"
    . "Correction RH pour « {$targetDisplayName} » : {$decisionLabel} par {$resolverDisplayName} ({$tenantName}).\n\n"
    . implode("\n", $diffLines) . "\n\n"
    . (trim((string) $resolutionNote) !== '' ? "Commentaire : {$resolutionNote}\n\n" : '')
    . "Fiche : {$ficheUrl}\n";

return ['html' => $html, 'text' => $text];
