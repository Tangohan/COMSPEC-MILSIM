<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $ip */
/** @var string $userAgent */
/** @var string $geo */
/** @var string $denyUrl */

$name = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');

$body = '<p>Bonjour ' . $name . ',</p>'
    . '<p>Une <strong>nouvelle connexion</strong> à votre compte a été détectée. Voici le détail :</p>'
    . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="font-size:14px;">'
    . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#64748b;width:34%;vertical-align:top;">Adresse IP</td>'
    . '<td style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-family:Consolas,monospace;font-size:13px;">' . htmlspecialchars($ip, ENT_QUOTES, 'UTF-8') . '</td></tr>'
    . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#64748b;vertical-align:top;">Navigateur</td>'
    . '<td style="padding:8px 0;border-bottom:1px solid #e2e8f0;word-break:break-word;">' . htmlspecialchars(mb_substr($userAgent, 0, 400), ENT_QUOTES, 'UTF-8') . '</td></tr>'
    . '<tr><td style="padding:8px 0;color:#64748b;vertical-align:top;">Localisation</td>'
    . '<td style="padding:8px 0;">' . htmlspecialchars($geo, ENT_QUOTES, 'UTF-8') . '</td></tr>'
    . '</table>'
    . '<p style="margin-top:20px;">Si ce n’était pas vous, sécurisez votre compte :</p>'
    . email_html_button($denyUrl, 'Ce n’est pas moi — agir', 'rose');

$html = email_html_layout(
    'Nouvelle connexion à votre compte',
    'Nouvelle connexion détectée',
    $body,
    ['accent' => 'rose']
);

$text = "Bonjour {$displayName},\n\nUne nouvelle connexion a été détectée :\n- IP : {$ip}\n- UA : {$userAgent}\n- Localisation indicative : {$geo}\n\nSi ce n'était pas vous : {$denyUrl}\n";

return ['html' => $html, 'text' => $text];
