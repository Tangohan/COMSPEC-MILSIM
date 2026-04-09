<?php

declare(strict_types=1);

/** @var string $tenantName */
/** @var string $areaLabel */
/** @var string $reason */
/** @var string $requesterName */
/** @var string $requesterEmail */
/** @var string $backOfficeUsersUrl */

$tn = htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8');
$al = htmlspecialchars($areaLabel, ENT_QUOTES, 'UTF-8');
$rn = htmlspecialchars($requesterName, ENT_QUOTES, 'UTF-8');
$re = htmlspecialchars($requesterEmail, ENT_QUOTES, 'UTF-8');
$reasonSafe = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');
$reasonHtml = nl2br($reasonSafe);
$url = htmlspecialchars($backOfficeUsersUrl, ENT_QUOTES, 'UTF-8');

$body = '<p>Un membre demande une <strong>habilitation supplémentaire</strong> sur la communauté <strong>' . $tn . '</strong>.</p>'
    . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="font-size:15px;margin-top:8px;">'
    . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#64748b;width:36%;">Zone concernée</td>'
    . '<td style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-weight:600;color:#0f172a;">' . $al . '</td></tr>'
    . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#64748b;vertical-align:top;">Motif</td>'
    . '<td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#334155;">' . $reasonHtml . '</td></tr>'
    . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#64748b;">Membre</td>'
    . '<td style="padding:8px 0;border-bottom:1px solid #e2e8f0;">' . $rn . '<br/><span style="font-size:13px;color:#64748b;">' . $re . '</span></td></tr>'
    . '</table>'
    . '<p style="margin-top:16px;font-size:14px;"><a href="' . $url . '" style="color:#2563eb;font-weight:600;">Ouvrir la gestion des membres</a> pour attribuer les rôles ou habilitations adaptés.</p>';

$html = email_html_layout(
    'Demande d’accès — ' . $tenantName,
    'Demande d’habilitation',
    $body,
    ['accent' => 'blue']
);

$text = "Demande d’accès — « {$tenantName} »\n"
    . "Zone : {$areaLabel}\n"
    . "Membre : {$requesterName} <{$requesterEmail}>\n\n"
    . "Motif :\n{$reason}\n\n"
    . "Gestion des membres : {$backOfficeUsersUrl}\n";

return ['html' => $html, 'text' => $text];
