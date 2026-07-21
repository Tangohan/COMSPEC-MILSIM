<?php

declare(strict_types=1);

/** @var string $recipientDisplayName */
/** @var string $tenantName */
/** @var list<array{name: string, next_due_label: string, overdue: bool}> $members */
/** @var string $roleplayFollowupUrl */

$name = htmlspecialchars((string) $recipientDisplayName, ENT_QUOTES, 'UTF-8');
$tn = htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8');
$members = is_array($members ?? null) ? $members : [];

$listHtml = '<ul>';
foreach ($members as $m) {
    $mn = htmlspecialchars((string) ($m['name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $due = htmlspecialchars((string) ($m['next_due_label'] ?? ''), ENT_QUOTES, 'UTF-8');
    $tag = !empty($m['overdue']) ? ' <strong style="color:#b91c1c;">(en retard)</strong>' : '';
    $listHtml .= '<li><strong>' . $mn . '</strong> — ' . $due . $tag . '</li>';
}
$listHtml .= '</ul>';

$body = '<p>Bonjour ' . $name . ',</p>'
    . '<p>Un bilan roleplay est dû pour ' . (count($members) > 1 ? 'ces membres' : 'ce membre') . ' de <strong>' . $tn . '</strong> :</p>'
    . $listHtml
    . '<p style="margin:16px 0 0;font-size:13px;color:#64748b;">Cadence indicative : tous les 6 mois la première année, 8 mois la deuxième, puis une fois par an.</p>'
    . email_html_button($roleplayFollowupUrl, 'Ouvrir le suivi roleplay', 'indigo')
    . email_html_url_fallback($roleplayFollowupUrl);

$html = email_html_layout(
    'Bilans roleplay dus — ' . $tenantName,
    'Suivi roleplay',
    $body,
    ['accent' => 'indigo']
);

$textLines = [];
foreach ($members as $m) {
    $tag = !empty($m['overdue']) ? ' (en retard)' : '';
    $textLines[] = ($m['name'] ?? '') . ' — ' . ($m['next_due_label'] ?? '') . $tag;
}

$text = "Bonjour {$recipientDisplayName},\n\n"
    . "Bilans roleplay dus pour « {$tenantName} » :\n\n"
    . implode("\n", $textLines) . "\n\n"
    . "Suivi roleplay : {$roleplayFollowupUrl}\n";

return ['html' => $html, 'text' => $text];
