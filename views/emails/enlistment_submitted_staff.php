<?php

declare(strict_types=1);

/** @var string $tenantName */
/** @var string $candidateFullName */
/** @var string $candidateEmail */
/** @var string|null $availability */
/** @var string|null $motivation */
/** @var int $enlistmentId */
/** @var string $reviewUrl */

$tn = htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8');
$name = htmlspecialchars($candidateFullName, ENT_QUOTES, 'UTF-8');
$mail = htmlspecialchars($candidateEmail, ENT_QUOTES, 'UTF-8');
$av = $availability !== null && $availability !== '' ? htmlspecialchars($availability, ENT_QUOTES, 'UTF-8') : null;
$mot = $motivation !== null && $motivation !== '' ? nl2br(htmlspecialchars($motivation, ENT_QUOTES, 'UTF-8')) : null;
$eid = (int) $enlistmentId;

$detailRows = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="font-size:15px;">'
    . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#64748b;width:38%;">N° dossier</td>'
    . '<td style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-weight:600;color:#0f172a;">#' . $eid . '</td></tr>'
    . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#64748b;">Nom</td>'
    . '<td style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-weight:600;color:#0f172a;">' . $name . '</td></tr>'
    . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#64748b;">E-mail</td>'
    . '<td style="padding:8px 0;border-bottom:1px solid #e2e8f0;"><a href="mailto:' . $mail . '" style="color:#2563eb;font-weight:600;">' . $mail . '</a></td></tr>';
if ($av !== null) {
    $detailRows .= '<tr><td style="padding:8px 0;color:#64748b;">Disponibilité</td>'
        . '<td style="padding:8px 0;color:#334155;">' . $av . '</td></tr>';
}
$detailRows .= '</table>';

$motBlock = $mot !== null
    ? '<p style="margin:20px 0 8px;font-size:13px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;">Motivation</p>'
        . '<div style="padding:14px 16px;background-color:#f8fafc;border-radius:10px;border:1px solid #e2e8f0;font-size:15px;line-height:1.55;color:#334155;">' . $mot . '</div>'
    : '';

$body = '<p>Une <strong>nouvelle candidature</strong> vient d’être soumise sur <strong>' . $tn . '</strong>.</p>'
    . email_html_callout('<strong>À traiter</strong> — ouvrez le dossier dans le back-office pour accepter, refuser ou demander des précisions.', 'info')
    . $detailRows
    . $motBlock
    . email_html_button($reviewUrl, 'Ouvrir la candidature', 'indigo')
    . email_html_url_fallback($reviewUrl);

$html = email_html_layout(
    'Nouvelle candidature #' . $eid . ' — ' . $tenantName,
    'Nouvelle candidature',
    $body,
    ['accent' => 'indigo']
);

$text = "Nouvelle candidature — « {$tenantName} »\n"
    . "- N° dossier : #{$eid}\n"
    . "- Nom : {$candidateFullName}\n"
    . "- Email : {$candidateEmail}\n";
if ($availability !== null && $availability !== '') {
    $text .= "- Disponibilité : {$availability}\n";
}
if ($motivation !== null && $motivation !== '') {
    $text .= "\nMotivation :\n{$motivation}\n";
}
$text .= "\nConsulter : {$reviewUrl}\n";

return ['html' => $html, 'text' => $text];
