<?php

declare(strict_types=1);

/** @var string $tenantName */
/** @var int $enlistmentId */
/** @var string $candidateFullName */
/** @var string $candidateEmail */
/** @var string $messageExcerpt */
/** @var string $reviewUrl */

$tn = htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8');
$eid = (int) $enlistmentId;
$name = htmlspecialchars($candidateFullName, ENT_QUOTES, 'UTF-8');
$mail = htmlspecialchars($candidateEmail, ENT_QUOTES, 'UTF-8');
$excerpt = trim($messageExcerpt);
$excerptSafe = $excerpt !== '' ? nl2br(htmlspecialchars(mb_substr($excerpt, 0, 1200), ENT_QUOTES, 'UTF-8')) : '—';

$body = '<p style="margin:0 0 16px;">Le candidat <strong>' . $name . '</strong> a envoyé un <strong>nouveau message</strong> depuis le portail de suivi sur <strong>' . $tn . '</strong>.</p>'
    . email_html_callout('Répondez depuis le dossier recrutement ou laissez une trace dans le journal du dossier pour garder la cohérence du suivi.', 'info')
    . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="font-size:15px;margin-top:8px;">'
    . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#64748b;width:38%;">N° dossier</td>'
    . '<td style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-weight:600;color:#0f172a;">#' . $eid . '</td></tr>'
    . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#64748b;">E-mail dossier</td>'
    . '<td style="padding:8px 0;border-bottom:1px solid #e2e8f0;"><a href="mailto:' . $mail . '" style="color:#2563eb;font-weight:600;">' . $mail . '</a></td></tr>'
    . '</table>'
    . '<p style="margin:20px 0 8px;font-size:13px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;">Extrait du message</p>'
    . '<div style="padding:14px 16px;background-color:#f0fdf4;border-radius:10px;border:1px solid #bbf7d0;font-size:15px;line-height:1.55;color:#14532d;">' . $excerptSafe . '</div>'
    . email_html_button($reviewUrl, 'Ouvrir le dossier', 'indigo')
    . email_html_url_fallback($reviewUrl);

$html = email_html_layout(
    'Réponse candidat sur le dossier #' . $eid,
    'Nouveau message candidat',
    $body,
    ['accent' => 'emerald']
);

$text = "Nouveau message candidat — « {$tenantName} »\n"
    . "- N° dossier : #{$eid}\n"
    . "- Candidat : {$candidateFullName}\n"
    . "- E-mail : {$candidateEmail}\n\n"
    . "Message :\n" . ($excerpt !== '' ? $excerpt : '—') . "\n\n"
    . "Ouvrir le dossier : {$reviewUrl}\n";

return ['html' => $html, 'text' => $text];
