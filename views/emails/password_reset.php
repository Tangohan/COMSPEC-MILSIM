<?php

declare(strict_types=1);

/** @var string $resetUrl */
/** @var int $hoursValid */

$body = '<p>Bonjour,</p>'
    . '<p>Vous avez demandé à <strong>réinitialiser votre mot de passe</strong>. Utilisez le bouton ci-dessous — le lien reste valable <strong>' . (int) $hoursValid . ' heure(s)</strong>.</p>'
    . email_html_button($resetUrl, 'Réinitialiser mon mot de passe', 'blue')
    . email_html_url_fallback($resetUrl)
    . '<p style="margin-top:24px;font-size:14px;color:#64748b;">Si vous n’êtes pas à l’origine de cette demande, ignorez simplement cet e-mail : votre mot de passe actuel reste inchangé.</p>';

$html = email_html_layout(
    'Réinitialisation du mot de passe — ' . (int) $hoursValid . ' h',
    'Réinitialisation du mot de passe',
    $body,
    ['accent' => 'slate']
);

$text = "Bonjour,\n\nCliquez sur le lien suivant pour réinitialiser votre mot de passe (valide {$hoursValid} h) :\n\n"
    . $resetUrl . "\n\nSi vous n’êtes pas à l’origine de cette demande, ignorez ce message.\n";

return ['html' => $html, 'text' => $text];
