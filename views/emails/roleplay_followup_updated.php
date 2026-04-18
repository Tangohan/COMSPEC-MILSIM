<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $tenantName */
/** @var string $subjectMemberLabel */
/** @var string $recipientRole */
/** @var string $summaryText */
/** @var string $dossierUrl */
/** @var list<string> $lines */

$name = htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8');
$tn = htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8');
$memberLabel = htmlspecialchars((string) $subjectMemberLabel, ENT_QUOTES, 'UTF-8');
$dUrl = htmlspecialchars((string) $dossierUrl, ENT_QUOTES, 'UTF-8');
$role = (string) $recipientRole;

$intro = $role === 'tutor'
    ? '<p>Bonjour ' . $name . ',</p>'
        . '<p>Le suivi roleplay du membre <strong>' . $memberLabel . '</strong> a été mis à jour sur <strong>' . $tn . '</strong>.</p>'
    : '<p>Bonjour ' . $name . ',</p>'
        . '<p>Votre suivi roleplay sur <strong>' . $tn . '</strong> a été mis à jour par l’équipe.</p>';

$listHtml = '<ul style="margin:16px 0;padding-left:20px;">';
foreach ($lines as $line) {
    $listHtml .= '<li style="margin:6px 0;">' . htmlspecialchars((string) $line, ENT_QUOTES, 'UTF-8') . '</li>';
}
$listHtml .= '</ul>';

$body = $intro
    . $listHtml
    . email_html_button($dossierUrl, 'Ouvrir le dossier personnel', 'emerald')
    . email_html_url_fallback($dossierUrl)
    . '<p style="margin-top:20px;font-size:14px;color:#64748b;">Vous pouvez aussi consulter l’historique et les prochaines échéances depuis votre espace.</p>';

$html = email_html_layout(
    'Suivi roleplay — mise à jour',
    $role === 'tutor' ? 'Mise à jour dossier tutoré' : 'Mise à jour de votre suivi',
    $body,
    ['accent' => 'emerald']
);

$linesText = implode("\n", array_map(static fn ($l) => '- ' . (string) $l, $lines));
$textIntro = $role === 'tutor'
    ? "Bonjour {$displayName},\n\nLe suivi roleplay du membre « {$subjectMemberLabel} » a été mis à jour sur « {$tenantName} ».\n\n"
    : "Bonjour {$displayName},\n\nVotre suivi roleplay sur « {$tenantName} » a été mis à jour.\n\n";

$text = $textIntro . $linesText . "\n\nOuvrir le dossier : {$dossierUrl}\n";

return ['html' => $html, 'text' => $text];
