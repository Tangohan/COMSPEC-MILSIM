<?php

declare(strict_types=1);

/** @var string $tenantName */
/** @var string $memberDisplayName */
/** @var string $memberEmail */

$tn = htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8');
$name = htmlspecialchars((string) $memberDisplayName, ENT_QUOTES, 'UTF-8');
$em = htmlspecialchars((string) $memberEmail, ENT_QUOTES, 'UTF-8');

$body = '<p>Un membre a <strong>quitté</strong> la communauté <strong>' . $tn . '</strong> de son plein gré.</p>'
    . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="font-size:15px;margin-top:8px;">'
    . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#64748b;width:32%;">Membre</td>'
    . '<td style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-weight:600;color:#0f172a;">' . $name . '</td></tr>'
    . '<tr><td style="padding:8px 0;color:#64748b;">E-mail</td>'
    . '<td style="padding:8px 0;color:#334155;">' . ($em !== '' ? $em : '—') . '</td></tr>'
    . '</table>'
    . email_html_callout('Son accès à cette communauté est terminé. Les autres communautés auxquelles il appartient ne sont pas concernées.', 'info');

$html = email_html_layout(
    'Départ d’un membre — ' . $tenantName,
    'Départ volontaire',
    $body,
    ['accent' => 'slate']
);

$text = "Départ d’un membre — « {$tenantName} »\n"
    . "- Membre : {$memberDisplayName}\n"
    . "- E-mail : {$memberEmail}\n"
    . "Son accès à cette communauté est terminé.\n";

return ['html' => $html, 'text' => $text];
