<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $tenantName */
/** @var string $dashboardUrl */
/** @var string $communitySettingsUrl */

$name = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
$tenant = htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8');

$body = '<p>Bonjour ' . $name . ',</p>'
    . '<p>Votre communauté <strong>' . $tenant . '</strong> est bien créée. Voici les prochaines actions recommandées :</p>'
    . '<ol>'
    . '<li>Vérifier les rôles d’administration (OTP e-mail demandé à la connexion).</li>'
    . '<li>Compléter la présentation et les accès publics.</li>'
    . '<li>Inviter le staff fondateur puis synchroniser les profils Steam utiles.</li>'
    . '</ol>'
    . email_html_button($dashboardUrl, 'Ouvrir le tableau de bord', 'blue')
    . email_html_button($communitySettingsUrl, 'Paramètres communauté', 'slate')
    . email_html_url_fallback($dashboardUrl);

$html = email_html_layout(
    'Communauté prête',
    'Checklist post-création',
    $body,
    ['accent' => 'blue']
);

$text = "Bonjour {$displayName},\n\n"
    . "Votre communauté « {$tenantName} » est créée.\n"
    . "Checklist:\n"
    . "1) Vérifier les rôles admin (OTP e-mail de connexion).\n"
    . "2) Compléter la présentation publique.\n"
    . "3) Inviter le staff + synchroniser les profils Steam.\n\n"
    . "Dashboard: {$dashboardUrl}\n"
    . "Paramètres: {$communitySettingsUrl}\n";

return ['html' => $html, 'text' => $text];
