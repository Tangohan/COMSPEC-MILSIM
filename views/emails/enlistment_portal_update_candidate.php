<?php

declare(strict_types=1);

/** @var string $tenantName */
/** @var string $portalUrl */
/** @var string $activityKind */
/** @var string $excerpt */
/** @var string $stepBeforeLabel */
/** @var string $stepAfterLabel */
/** @var int $enlistmentId */

$tn = htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8');
$kind = (string) $activityKind;
$intro = match ($kind) {
    'upload_audio' => 'L’équipe recrutement a déposé un <strong>message vocal</strong> ou un enregistrement sur votre fil de suivi.',
    'upload_file' => 'L’équipe recrutement a déposé un <strong>fichier</strong> sur votre fil de suivi.',
    default => 'L’équipe recrutement vous a <strong>répondu par message</strong> sur votre fil de suivi.',
};
$excerptTrim = trim((string) $excerpt);
$safeExcerpt = $excerptTrim !== '' ? nl2br(htmlspecialchars($excerptTrim, ENT_QUOTES, 'UTF-8')) : '';

$sb = trim((string) $stepBeforeLabel);
$sa = trim((string) $stepAfterLabel);
$stepBlock = '';
if ($sb !== '' && $sa !== '' && $sb !== $sa) {
    $stepBlock = email_html_callout(
        '<strong>Progression du parcours</strong> (affichée sur votre page de suivi) : « ' . htmlspecialchars($sb, ENT_QUOTES, 'UTF-8') . ' » → « ' . htmlspecialchars($sa, ENT_QUOTES, 'UTF-8') . ' ».',
        'info'
    );
}

$body = '<p style="margin:0 0 16px;">Bonjour,</p>'
    . '<p style="margin:0 0 16px;">' . $intro . '</p>'
    . '<p style="margin:0 0 12px;font-size:13px;color:#64748b;">Référence dossier n°' . (int) $enlistmentId . ' — communauté <strong>' . $tn . '</strong>.</p>'
    . $stepBlock;

if ($safeExcerpt !== '') {
    $body .= '<p style="margin:20px 0 8px;font-size:13px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;">Aperçu</p>'
        . '<div style="padding:14px 16px;background-color:#f8fafc;border-radius:10px;border:1px solid #e2e8f0;font-size:15px;line-height:1.55;color:#334155;">' . $safeExcerpt . '</div>';
}

$body .= '<p style="margin:24px 0 12px;">Ouvrez votre lien de suivi sécurisé pour lire la réponse en entier et continuer l’échange.</p>'
    . email_html_button($portalUrl, 'Ouvrir mon suivi candidature', 'indigo')
    . email_html_url_fallback($portalUrl)
    . '<p style="margin:20px 0 0;font-size:13px;color:#64748b;">Si vous ne parvenez pas à ouvrir le bouton, copiez-collez l’adresse ci-dessus. En cas de lien expiré, reprenez contact avec la communauté par les canaux habituels.</p>';

$html = email_html_layout(
    'Nouvelle activité sur votre candidature — ' . $tenantName,
    'Activité sur votre candidature',
    $body,
    ['accent' => 'emerald']
);

$text = "Nouvelle activité sur votre candidature — « {$tenantName} »\n"
    . "Dossier n°" . (int) $enlistmentId . "\n\n";
if ($sb !== '' && $sa !== '' && $sb !== $sa) {
    $text .= "Progression du parcours : « {$sb} » → « {$sa} ».\n\n";
}
if ($excerptTrim !== '') {
    $text .= "Aperçu :\n{$excerptTrim}\n\n";
}
$text .= "Lien de suivi :\n{$portalUrl}\n";

return ['html' => $html, 'text' => $text];
