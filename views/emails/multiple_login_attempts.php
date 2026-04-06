<?php

declare(strict_types=1);

/** @var string $email */
/** @var string $ip */
/** @var string $when */
/** @var string $forgotUrl */

$body = email_html_callout(
    '<strong>Plusieurs tentatives de connexion ont échoué</strong> récemment pour le compte associé à '
    . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '.',
    'warning'
)
    . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="font-size:15px;">'
    . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#64748b;width:40%;">Adresse IP</td>'
    . '<td style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-family:Consolas,monospace;font-size:13px;">' . htmlspecialchars($ip, ENT_QUOTES, 'UTF-8') . '</td></tr>'
    . '<tr><td style="padding:8px 0;color:#64748b;">Dernière tentative</td>'
    . '<td style="padding:8px 0;">' . htmlspecialchars($when, ENT_QUOTES, 'UTF-8') . '</td></tr>'
    . '</table>'
    . '<p style="margin-top:20px;">Si vous avez oublié votre mot de passe, vous pouvez le réinitialiser :</p>'
    . email_html_button($forgotUrl, 'Réinitialiser mon mot de passe', 'blue');

$html = email_html_layout(
    'Tentatives de connexion — ' . $email,
    'Sécurité du compte',
    $body,
    ['accent' => 'rose', 'footer_note' => 'Si c’était bien vous, aucune action n’est requise.']
);

$text = "Alerte sécurité : plusieurs échecs de connexion pour {$email}.\nIP : {$ip}\nDernière tentative : {$when}\n\nRéinitialisation : {$forgotUrl}\n";

return ['html' => $html, 'text' => $text];
