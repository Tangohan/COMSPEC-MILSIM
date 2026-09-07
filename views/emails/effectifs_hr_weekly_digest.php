<?php

declare(strict_types=1);

/** @var string $staffDisplayName */
/** @var string $tenantName */
/** @var int $incompleteProfiles */
/** @var int $withoutUnit */
/** @var int $withoutRole */
/** @var int $pendingElevations */
/** @var string $rosterUrl */

$staff = htmlspecialchars((string) $staffDisplayName, ENT_QUOTES, 'UTF-8');
$tn = htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8');

$rows = [
    ['label' => 'Dossiers incomplets', 'count' => (int) $incompleteProfiles],
    ['label' => 'Membres sans unité', 'count' => (int) $withoutUnit],
    ['label' => 'Membres sans rôle', 'count' => (int) $withoutRole],
    ['label' => 'Élévations en attente', 'count' => (int) $pendingElevations],
];

$listHtml = '<ul>';
foreach ($rows as $r) {
    if ($r['count'] < 1) {
        continue;
    }
    $listHtml .= '<li><strong>' . $r['count'] . '</strong> — ' . htmlspecialchars($r['label'], ENT_QUOTES, 'UTF-8') . '</li>';
}
$listHtml .= '</ul>';

$body = '<p>Bonjour ' . $staff . ',</p>'
    . '<p>Voici le résumé hebdomadaire du bureau effectifs pour <strong>' . $tn . '</strong> :</p>'
    . $listHtml
    . email_html_button($rosterUrl, 'Ouvrir le tableur des effectifs', 'emerald')
    . email_html_url_fallback($rosterUrl);

$html = email_html_layout(
    'Digest RH hebdomadaire — ' . $tenantName,
    'Bureau effectifs',
    $body,
    ['accent' => 'blue']
);

$textLines = [];
foreach ($rows as $r) {
    if ($r['count'] < 1) {
        continue;
    }
    $textLines[] = $r['count'] . ' — ' . $r['label'];
}

$text = "Bonjour {$staffDisplayName},\n\n"
    . "Résumé hebdomadaire du bureau effectifs pour « {$tenantName} » :\n\n"
    . implode("\n", $textLines) . "\n\n"
    . "Tableur des effectifs : {$rosterUrl}\n";

return ['html' => $html, 'text' => $text];
