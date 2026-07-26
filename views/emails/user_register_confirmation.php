<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $tenantName */
/** @var string $verifyUrl */
/** @var int $ttlMinutes */

$brand = function_exists('email_brand_name') ? email_brand_name() : 'Athena';
$name = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
$tenant = htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8');
$isPlatformWelcome = strcasecmp(trim($tenantName), $brand) === 0;

if ($isPlatformWelcome) {
    $accountLine = '<p>Votre compte sur <strong>' . htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') . '</strong> est prêt. Une dernière étape : confirmez votre adresse e-mail pour activer l’accès au portail. Le lien ci-dessous expire dans <strong>' . (int) $ttlMinutes . ' minutes</strong>.</p>';
    $textAccount = "Votre compte sur {$brand} a été créé. Confirmez votre adresse e-mail en ouvrant ce lien (valide {$ttlMinutes} min) :\n\n";
} else {
    $accountLine = '<p>Votre compte dans la communauté <strong>' . $tenant . '</strong> est prêt. Une dernière étape : confirmez votre adresse e-mail pour activer l’accès. Le lien ci-dessous expire dans <strong>' . (int) $ttlMinutes . ' minutes</strong>.</p>';
    $textAccount = "Votre compte dans la communauté « {$tenantName} » a été créé. Confirmez votre adresse e-mail en ouvrant ce lien (valide {$ttlMinutes} min) :\n\n";
}

$body = '<p>Bonjour ' . $name . ',</p>'
    . $accountLine
    . email_html_button($verifyUrl, 'Confirmer mon e-mail', 'blue')
    . email_html_url_fallback($verifyUrl)
    . '<p style="margin-top:24px;font-size:14px;color:#64748b;">Si vous n’êtes pas à l’origine de cette inscription, vous pouvez ignorer ce message en toute sécurité.</p>';

$html = email_html_layout(
    'Confirmez votre e-mail — lien valable ' . (int) $ttlMinutes . ' min',
    'Confirmez votre adresse e-mail',
    $body,
    ['accent' => 'blue', 'footer_note' => 'Besoin d’aide ? Contactez un administrateur depuis le portail.']
);

$text = "Bonjour {$displayName},\n\n"
    . $textAccount
    . $verifyUrl . "\n\n"
    . "Si vous n’êtes pas à l’origine de cette inscription, ignorez ce message.\n";

return ['html' => $html, 'text' => $text];
