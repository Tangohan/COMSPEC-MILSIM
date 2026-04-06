<?php

declare(strict_types=1);

/** @var string $tenantName */
/** @var string $fromEmail */
/** @var string $messageBody */

$tn = htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8');

$body = '<p>Message reçu depuis la <strong>page publique</strong> de la communauté <strong>' . $tn . '</strong>.</p>'
    . '<p style="font-size:14px;color:#64748b;margin-bottom:8px;">Expéditeur déclaré</p>'
    . '<p style="margin-top:0;font-weight:600;color:#0f172a;">' . htmlspecialchars($fromEmail, ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p style="font-size:13px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;margin:24px 0 10px;">Message</p>'
    . '<div style="padding:16px 18px;background-color:#f8fafc;border-radius:10px;border:1px solid #e2e8f0;font-size:15px;line-height:1.6;color:#334155;">'
    . nl2br(htmlspecialchars($messageBody, ENT_QUOTES, 'UTF-8'))
    . '</div>';

$html = email_html_layout(
    'Message — ' . $tenantName,
    'Message depuis le site',
    $body,
    ['accent' => 'slate']
);

$text = "Message — « {$tenantName} »\nExpéditeur : {$fromEmail}\n\n{$messageBody}\n";

return ['html' => $html, 'text' => $text];
