<?php

declare(strict_types=1);

/** @var string $tenantName */
/** @var string $acceptUrl */
/** @var string $roleLabel */
/** @var string $inviterLabel */
/** @var string $personalMessage */
/** @var int $validDays */

$personalMessage = trim((string) ($personalMessage ?? ''));
$validDays = max(1, (int) ($validDays ?? 7));
$validLabel = $validDays . ' jour' . ($validDays > 1 ? 's' : '');

$tn = htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8');
$rl = htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8');
$il = htmlspecialchars($inviterLabel, ENT_QUOTES, 'UTF-8');

$roleLine = $roleLabel !== ''
    ? '<p style="margin:0 0 16px;">Rôle proposé : <strong>' . $rl . '</strong></p>'
    : '';
$messageBlock = $personalMessage !== ''
    ? '<p style="margin:0 0 6px;font-size:13px;color:#64748b;">Message de ' . $il . ' :</p>'
        . '<blockquote style="margin:0 0 16px;padding:10px 14px;border-left:3px solid #6366f1;background:#f8fafc;white-space:pre-line;">' . htmlspecialchars($personalMessage, ENT_QUOTES, 'UTF-8') . '</blockquote>'
    : '';

$body = '<p>Bonjour,</p>'
    . '<p>Vous êtes invité(e) à rejoindre la communauté <strong>' . $tn . '</strong>.</p>'
    . $roleLine
    . $messageBlock
    . '<p style="font-size:14px;color:#64748b;">Invitation envoyée par <strong>' . $il . '</strong>.</p>'
    . email_html_button($acceptUrl, 'Accepter l’invitation', 'indigo')
    . email_html_url_fallback($acceptUrl)
    . email_html_callout('<strong>Validité : ' . $validLabel . '.</strong> Passé ce délai, demandez une nouvelle invitation à un administrateur.', 'info');

$html = email_html_layout(
    'Invitation — ' . $tenantName,
    'Vous êtes invité(e)',
    $body,
    ['accent' => 'indigo']
);

$text = "Bonjour,\n\nVous êtes invité à rejoindre la communauté « {$tenantName} »"
    . ($roleLabel !== '' ? " (rôle proposé : {$roleLabel})" : '')
    . ". Invitation envoyée par {$inviterLabel}.\n\n"
    . ($personalMessage !== '' ? "Message :\n{$personalMessage}\n\n" : '')
    . "Acceptez l’invitation :\n{$acceptUrl}\n\n(Lien valable {$validLabel}.)\n";

return ['html' => $html, 'text' => $text];
