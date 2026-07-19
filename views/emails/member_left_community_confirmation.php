<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $tenantName */
/** @var string $joinUrl */
/** @var string $dashboardUrl */

$name = htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8');
$tn = htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8');
$joinUrl = (string) ($joinUrl ?? '');
$dashboardUrl = (string) ($dashboardUrl ?? '');

$body = '<p>Bonjour ' . $name . ',</p>'
    . '<p>Votre départ de la communauté <strong>' . $tn . '</strong> est bien enregistré. '
    . 'Vous n’avez plus accès à cette communauté.</p>'
    . email_html_callout(
        'Votre compte Athena reste actif. Si vous appartenez à d’autres communautés, elles restent disponibles. Sinon, vous pourrez en rejoindre une nouvelle quand vous le souhaitez.',
        'info'
    );

if ($joinUrl !== '') {
    $body .= email_html_button($joinUrl, 'Rejoindre une communauté', 'emerald');
    $body .= email_html_url_fallback($joinUrl);
}
if ($dashboardUrl !== '') {
    $body .= '<p style="margin-top:16px;font-size:14px;color:#64748b;">Ou retournez sur votre espace&nbsp;: '
        . '<a href="' . htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#047857;font-weight:600;">ouvrir mon tableau de bord</a>.</p>';
}

$html = email_html_layout(
    'Vous avez quitté « ' . $tenantName . ' »',
    'Départ confirmé',
    $body,
    ['accent' => 'emerald']
);

$text = "Bonjour {$displayName},\n\n"
    . "Votre départ de la communauté « {$tenantName} » est bien enregistré.\n"
    . "Vous n’avez plus accès à cette communauté. Votre compte Athena reste actif.\n\n";
if ($joinUrl !== '') {
    $text .= "Rejoindre une communauté : {$joinUrl}\n";
}
if ($dashboardUrl !== '') {
    $text .= "Tableau de bord : {$dashboardUrl}\n";
}

return ['html' => $html, 'text' => $text];
