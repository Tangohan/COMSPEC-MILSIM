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
    . '<p>Merci de votre intérêt pour les actualités <strong>' . htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') . '</strong>. Pour recevoir nos prochains messages, une dernière étape : confirmez votre inscription en cliquant sur le bouton ci-dessous.</p>'
    . $expiryCallout
    . email_html_button($confirmUrl, 'Confirmer mon inscription', 'indigo')
    . email_html_url_fallback($confirmUrl)
    . '<p style="margin-top:24px;font-size:14px;color:#64748b;">Vous n’avez pas demandé à recevoir ces messages ? Ignorez simplement cet e-mail. Vous pouvez aussi <a href="' . htmlspecialchars($unsubscribeUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#64748b;text-decoration:underline;">annuler cette demande</a>.</p>';

$preheader = 'Confirmez votre inscription — lien valable ' . $expiresInHours . ' h';
$html = email_html_layout(
    $preheader,
    'Une étape pour finaliser votre inscription',
    $body,
    [
        'accent' => 'indigo',
        'footer_note' => 'Vous recevez cet e-mail suite à une inscription sur notre site. Ce message est envoyé automatiquement.',
    ]
);

$text = "Bonjour,\n\n"
    . "Merci de votre intérêt pour les actualités « {$brand} ».\n"
    . "Pour finaliser votre inscription, ouvrez ce lien dans votre navigateur (valable {$expiresInHours} h) :\n\n"
    . "{$confirmUrl}\n\n"
    . "Vous n’êtes pas à l’origine de cette demande ? Ignorez ce message.\n"
    . "Pour annuler la demande : {$unsubscribeUrl}\n";

return ['html' => $html, 'text' => $text];
