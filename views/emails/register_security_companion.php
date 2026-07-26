<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $tenantName */
/** @var string $accountPreferencesUrl */
/** @var string $communityCreateUrl */

$brand = function_exists('email_brand_name') ? email_brand_name() : 'Athena';
$name = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
$tenant = htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8');
$isPlatformWelcome = strcasecmp(trim($tenantName), $brand) === 0;

if ($isPlatformWelcome) {
    $intro = '<p>Bienvenue sur <strong>' . htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') . '</strong>. Pendant que vous confirmez votre e-mail, vous pouvez déjà préparer votre compte.</p>';
    $textIntro = "Bienvenue sur {$brand}.\n";
} else {
    $intro = '<p>Bienvenue dans la communauté <strong>' . $tenant . '</strong>. Pendant que vous confirmez votre e-mail, vous pouvez déjà préparer votre compte.</p>';
    $textIntro = "Bienvenue dans la communauté {$tenantName}.\n";
}

$body = '<p>Bonjour ' . $name . ',</p>'
    . $intro
    . '<ul>'
    . '<li><strong>Synchroniser Steam</strong> (profil / identifiant Steam) pour faciliter les candidatures et la cohérence des identités.</li>'
    . '<li><strong>Renforcer la sécurité OTP</strong> : le portail exige un code OTP e-mail pour les rôles administratifs.</li>'
    . '<li><strong>Créer une communauté</strong> si vous lancez une unité avec votre équipe.</li>'
    . '</ul>'
    . email_html_button($accountPreferencesUrl, 'Configurer mon profil', 'blue')
    . email_html_button($communityCreateUrl, 'Créer une communauté', 'slate')
    . email_html_url_fallback($accountPreferencesUrl);

$html = email_html_layout(
    'Démarrage sécurisé',
    'Checklist de démarrage',
    $body,
    ['accent' => 'blue']
);

$text = "Bonjour {$displayName},\n\n"
    . $textIntro
    . "- Configurez votre profil + Steam : {$accountPreferencesUrl}\n"
    . "- Créez une communauté : {$communityCreateUrl}\n"
    . "- Les rôles administratifs utilisent un OTP e-mail à la connexion.\n";

return ['html' => $html, 'text' => $text];
