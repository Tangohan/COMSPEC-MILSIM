<?php

declare(strict_types=1);

/** @var string $tenantName */
/** @var string $statusLabel */
/** @var string $comment */
/** @var string $portalUrl */

$tn = htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8');
$st = htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8');
$commentTrim = trim($comment);
$safeComment = $commentTrim !== '' ? nl2br(htmlspecialchars($commentTrim, ENT_QUOTES, 'UTF-8')) : '';

$body = '<p style="margin:0 0 16px;">Votre dossier de candidature sur <strong>' . $tn . '</strong> a été mis à jour.</p>'
    . email_html_callout('<strong>Statut indiqué par l’équipe :</strong> ' . $st . '.', 'info');

if ($safeComment !== '') {
    $body .= '<p style="margin:20px 0 8px;font-size:13px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;">Message de l’équipe</p>'
        . '<div style="padding:14px 16px;background-color:#f8fafc;border-radius:10px;border:1px solid #e2e8f0;font-size:15px;line-height:1.55;color:#334155;">' . $safeComment . '</div>';
}

$body .= '<p style="margin:24px 0 12px;">Vous pouvez consulter l’historique, répondre et poser vos questions dans votre espace de suivi sécurisé.</p>'
    . email_html_button($portalUrl, 'Ouvrir mon suivi candidature', 'indigo')
    . email_html_url_fallback($portalUrl)
    . '<p style="margin:20px 0 0;font-size:13px;color:#64748b;">Le lien de suivi reste valable <strong>7 jours</strong> à compter de sa dernière régénération. Si le lien expire, contactez la communauté par les canaux habituels.</p>';

$html = email_html_layout(
    'Mise à jour de votre candidature — ' . $tenantName,
    'Mise à jour de votre candidature',
    $body,
    ['accent' => 'emerald']
);

$text = "Mise à jour de votre candidature — « {$tenantName} »\n"
    . "Statut : {$statusLabel}\n\n";
if ($commentTrim !== '') {
    $text .= "Message de l’équipe :\n{$commentTrim}\n\n";
}
$text .= "Suivi candidature (lien sécurisé) :\n{$portalUrl}\n";

return ['html' => $html, 'text' => $text];
