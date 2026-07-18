<?php

declare(strict_types=1);

$confirmUrl = (string) ($confirmUrl ?? '#');
$unsubscribeUrl = (string) ($unsubscribeUrl ?? '#');
$expiresInHours = (int) ($expiresInHours ?? 48);
$brand = email_brand_name();

$expiryCallout = email_html_callout(
    '<strong>À noter.</strong> Ce lien de confirmation reste valable <strong>' . $expiresInHours . ' heures</strong>. Passé ce délai, il vous suffira de vous réinscrire depuis le site.',
    'warning'
);

$body = '<p>Bonjour,</p>'
    . '<p>Vous avez demandé à recevoir les nouveautés <strong>' . htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') . '</strong> : évolutions de la plateforme, modules tactiques, guides Arma&nbsp;3 et actualités pour les communautés MILSIM.</p>'
    . '<p>Pour activer l’envoi, confirmez votre adresse e-mail en cliquant sur le bouton ci-dessous.</p>'
    . $expiryCallout
    . email_html_button($confirmUrl, 'Confirmer mon inscription', 'indigo')
    . email_html_url_fallback($confirmUrl)
    . '<p style="margin-top:24px;font-size:14px;color:#64748b;">Vous n’avez pas demandé ces communications&nbsp;? Ignorez cet e-mail, ou <a href="' . htmlspecialchars($unsubscribeUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#64748b;text-decoration:underline;">annulez cette demande</a>.</p>';

$preheader = 'Confirmez votre adresse pour recevoir les nouveautés Athena';
$html = email_html_layout(
    $preheader,
    'Confirmez votre inscription aux communications Athena',
    $body,
    [
        'accent' => 'indigo',
        'footer_note' => 'Vous recevez cet e-mail suite à une inscription sur le site Athena. Vous pourrez vous désabonner à tout moment depuis chaque message.',
    ]
);

$text = "Bonjour,\n\n"
    . "Vous avez demandé à recevoir les nouveautés « {$brand} » (évolutions du portail, modules tactiques, guides Arma 3, actualités MILSIM).\n"
    . "Pour activer l’envoi, ouvrez ce lien dans votre navigateur (valable {$expiresInHours} h) :\n\n"
    . "{$confirmUrl}\n\n"
    . "Vous n’êtes pas à l’origine de cette demande ? Ignorez ce message.\n"
    . "Pour annuler la demande : {$unsubscribeUrl}\n";

return ['html' => $html, 'text' => $text];
