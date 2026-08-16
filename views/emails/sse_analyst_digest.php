<?php

declare(strict_types=1);

/** @var string $staffDisplayName */
/** @var string $tenantName */
/** @var int $pendingSuggestions */
/** @var int $openSignals */
/** @var int $newPersons */
/** @var int $interestOpen */
/** @var string $workspaceUrl */
/** @var string $suggestionsUrl */

$staff = htmlspecialchars((string) $staffDisplayName, ENT_QUOTES, 'UTF-8');
$tn = htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8');

$rows = [
    ['label' => 'Rapprochements à trancher', 'count' => (int) $pendingSuggestions],
    ['label' => 'Signaux ouverts (moteur)', 'count' => (int) $openSignals],
    ['label' => 'Nouvelles fiches terrain (24 h)', 'count' => (int) $newPersons],
    ['label' => 'Dossiers d’intérêt actifs', 'count' => (int) $interestOpen],
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
    . '<p>Voici le point renseignement SSE pour <strong>' . $tn . '</strong> :</p>'
    . $listHtml
    . '<p>Ces propositions restent à valider par un analyste — rien n’est fusionné automatiquement.</p>'
    . email_html_button($workspaceUrl, 'Ouvrir l’espace de renseignement', 'emerald')
    . email_html_button($suggestionsUrl, 'Voir les rapprochements', 'blue')
    . email_html_url_fallback($workspaceUrl);

$html = email_html_layout(
    'Point renseignement SSE — ' . $tenantName,
    'Sensitive Site Exploitation',
    $body,
    ['accent' => 'emerald']
);

$textLines = [];
foreach ($rows as $r) {
    if ($r['count'] < 1) {
        continue;
    }
    $textLines[] = $r['count'] . ' — ' . $r['label'];
}

$text = "Bonjour {$staffDisplayName},\n\n"
    . "Point renseignement SSE pour « {$tenantName} » :\n\n"
    . implode("\n", $textLines) . "\n\n"
    . "Espace de renseignement : {$workspaceUrl}\n"
    . "Rapprochements : {$suggestionsUrl}\n";

return ['html' => $html, 'text' => $text];
