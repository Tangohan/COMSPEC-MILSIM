<?php

declare(strict_types=1);

/** @var string $staffDisplayName */
/** @var string $targetDisplayName */
/** @var string $tenantName */
/** @var string $actorName */
/** @var list<string> $changeLines */
/** @var string $memberUrl */

$staff = htmlspecialchars((string) $staffDisplayName, ENT_QUOTES, 'UTF-8');
$target = htmlspecialchars((string) $targetDisplayName, ENT_QUOTES, 'UTF-8');
$tn = htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8');
$actor = trim((string) ($actorName ?? ''));
$lines = is_array($changeLines ?? null) ? $changeLines : [];
$memberUrl = (string) ($memberUrl ?? '');

$listHtml = '<ul style="margin:12px 0;padding-left:20px;">';
foreach ($lines as $line) {
    $listHtml .= '<li>' . htmlspecialchars((string) $line, ENT_QUOTES, 'UTF-8') . '</li>';
}
$listHtml .= '</ul>';

$actorHtml = $actor !== ''
    ? '<p>Enregistré par <strong>' . htmlspecialchars($actor, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
    : '';

$body = '<p>Bonjour ' . $staff . ',</p>'
    . '<p>Le dossier de <strong>' . $target . '</strong> a été mis à jour dans la communauté <strong>' . $tn . '</strong>.</p>'
    . $listHtml
    . $actorHtml;

if ($memberUrl !== '') {
    $body .= email_html_button($memberUrl, 'Ouvrir la fiche effectifs', 'blue');
    $body .= email_html_url_fallback($memberUrl);
}

$html = email_html_layout(
    'Dossier mis à jour — ' . $targetDisplayName,
    'Effectifs / RH',
    $body,
    ['accent' => 'blue']
);

$text = "Bonjour {$staffDisplayName},\n\n"
    . "Le dossier de « {$targetDisplayName} » a été mis à jour sur « {$tenantName} ».\n\n";
foreach ($lines as $line) {
    $text .= '- ' . $line . "\n";
}
if ($actor !== '') {
    $text .= "\nEnregistré par : {$actor}\n";
}
if ($memberUrl !== '') {
    $text .= "\nFiche effectifs : {$memberUrl}\n";
}

return ['html' => $html, 'text' => $text];
