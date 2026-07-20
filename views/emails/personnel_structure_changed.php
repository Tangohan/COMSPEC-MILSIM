<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $tenantName */
/** @var string $actorName */
/** @var list<string> $changeLines */
/** @var string $dossierUrl */

$name = htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8');
$tn = htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8');
$actor = trim((string) ($actorName ?? ''));
$lines = is_array($changeLines ?? null) ? $changeLines : [];
$dossierUrl = (string) ($dossierUrl ?? '');

$listHtml = '<ul style="margin:12px 0;padding-left:20px;">';
foreach ($lines as $line) {
    $listHtml .= '<li>' . htmlspecialchars((string) $line, ENT_QUOTES, 'UTF-8') . '</li>';
}
$listHtml .= '</ul>';

$actorHtml = $actor !== ''
    ? '<p>Cette mise à jour a été enregistrée par <strong>' . htmlspecialchars($actor, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
    : '';

$body = '<p>Bonjour ' . $name . ',</p>'
    . '<p>Votre dossier dans la communauté <strong>' . $tn . '</strong> a été mis à jour.</p>'
    . $listHtml
    . $actorHtml
    . email_html_callout(
        'Si vous constatez une erreur, contactez un responsable RH ou d’effectif de votre communauté.',
        'info'
    );

if ($dossierUrl !== '') {
    $body .= email_html_button($dossierUrl, 'Voir mon dossier', 'emerald');
    $body .= email_html_url_fallback($dossierUrl);
}

$html = email_html_layout(
    'Mise à jour de votre dossier — ' . $tenantName,
    'Dossier personnel',
    $body,
    ['accent' => 'emerald']
);

$text = "Bonjour {$displayName},\n\n"
    . "Votre dossier dans la communauté « {$tenantName} » a été mis à jour.\n\n";
foreach ($lines as $line) {
    $text .= '- ' . $line . "\n";
}
if ($actor !== '') {
    $text .= "\nEnregistré par : {$actor}\n";
}
if ($dossierUrl !== '') {
    $text .= "\nVoir mon dossier : {$dossierUrl}\n";
}

return ['html' => $html, 'text' => $text];
