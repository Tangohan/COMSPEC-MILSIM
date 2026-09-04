<?php

declare(strict_types=1);

/** @var string $memberEmail */
/** @var string $displayName */
/** @var string $tenantName */
/** @var string $phase */
/** @var string $occurredAt */

$verified = $phase === 'email_verified';
$title = $verified ? 'Adresse e-mail vérifiée' : 'Nouveau compte créé';
$status = $verified
    ? 'Le membre vient de confirmer son adresse e-mail.'
    : 'Le membre doit encore confirmer son adresse e-mail.';
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$body = '<p>' . $escape($status) . '</p>'
    . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="font-size:15px;margin-top:8px;">'
    . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#64748b;width:32%;">Membre</td>'
    . '<td style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-weight:600;color:#0f172a;">' . $escape($displayName) . '</td></tr>'
    . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#64748b;">Adresse e-mail</td>'
    . '<td style="padding:8px 0;border-bottom:1px solid #e2e8f0;">' . $escape($memberEmail) . '</td></tr>'
    . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#64748b;">Communauté</td>'
    . '<td style="padding:8px 0;border-bottom:1px solid #e2e8f0;">' . $escape($tenantName) . '</td></tr>'
    . '<tr><td style="padding:8px 0;color:#64748b;">Date</td>'
    . '<td style="padding:8px 0;">' . $escape($occurredAt) . '</td></tr>'
    . '</table>';

$html = email_html_layout($title . ' — ' . $displayName, $title, $body, ['accent' => $verified ? 'emerald' : 'blue']);
$text = $title . "\n\n"
    . $status . "\n"
    . 'Membre : ' . $displayName . "\n"
    . 'Adresse e-mail : ' . $memberEmail . "\n"
    . 'Communauté : ' . $tenantName . "\n"
    . 'Date : ' . $occurredAt . "\n";

return ['html' => $html, 'text' => $text];
