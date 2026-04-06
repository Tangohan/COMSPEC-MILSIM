<?php

declare(strict_types=1);

/** @var string $tenantName */
/** @var string $memberEmail */
/** @var string $ip */
/** @var string $context */

$tn = htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8');

$body = '<p>Un <strong>nouveau membre</strong> rejoint ou s’inscrit sur <strong>' . $tn . '</strong>.</p>'
    . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="font-size:15px;margin-top:8px;">'
    . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#64748b;width:32%;">E-mail</td>'
    . '<td style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-weight:600;color:#0f172a;">' . htmlspecialchars($memberEmail, ENT_QUOTES, 'UTF-8') . '</td></tr>'
    . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#64748b;">IP</td>'
    . '<td style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-family:Consolas,monospace;font-size:13px;">' . htmlspecialchars($ip, ENT_QUOTES, 'UTF-8') . '</td></tr>'
    . '<tr><td style="padding:8px 0;color:#64748b;vertical-align:top;">Contexte</td>'
    . '<td style="padding:8px 0;color:#334155;">' . htmlspecialchars($context, ENT_QUOTES, 'UTF-8') . '</td></tr>'
    . '</table>';

$html = email_html_layout(
    'Nouveau membre — ' . $tenantName,
    'Nouvelle inscription',
    $body,
    ['accent' => 'emerald']
);

$text = "Nouvelle inscription / membre — « {$tenantName} »\n"
    . "- Email : {$memberEmail}\n- IP : {$ip}\n- Contexte : {$context}\n";

return ['html' => $html, 'text' => $text];
