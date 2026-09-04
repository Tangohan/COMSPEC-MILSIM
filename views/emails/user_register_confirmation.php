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
    . '<div style="margin-top:24px;padding:16px;border-left:4px solid #ca8a04;background:#fefce8;color:#3f3f46;">'
    . '<p style="margin:0 0 8px;"><strong>Information importante — preview ouverte</strong></p>'
    . '<p style="margin:0 0 8px;">Athena et son mod sont encore en preview. L’intuitivité est en cours d’amélioration et certaines fonctions peuvent évoluer.</p>'
    . '<p style="margin:0;">Selon les versions du portail web et du mod, une fonction peut marcher puis devenir temporairement indisponible. Vos conseils et retours sont les bienvenus à <a href="mailto:no-reply@athena.ttrd.fr">no-reply@athena.ttrd.fr</a>.</p>'
    . '</div>'
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
    . "Information preview ouverte : Athena et son mod sont encore en cours d’amélioration. Une fonction peut devenir temporairement indisponible en cas de décalage entre les versions web et mod. Envoyez vos conseils et retours à no-reply@athena.ttrd.fr.\n\n"
    . "Si vous n’êtes pas à l’origine de cette inscription, ignorez ce message.\n";

return ['html' => $html, 'text' => $text];
