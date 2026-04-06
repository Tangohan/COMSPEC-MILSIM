<?php

declare(strict_types=1);

/** @var string $tenantName */
/** @var int $enlistmentId */
/** @var string $candidateFullName */
/** @var string $candidateEmail */
/** @var string $summaryLine */
/** @var string $reviewUrl */

$tn = htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8');
$eid = (int) $enlistmentId;
$name = htmlspecialchars($candidateFullName, ENT_QUOTES, 'UTF-8');
$mail = htmlspecialchars($candidateEmail, ENT_QUOTES, 'UTF-8');
$sum = htmlspecialchars($summaryLine, ENT_QUOTES, 'UTF-8');

$body = '<p>Une candidature sur <strong>' . $tn . '</strong> vient d’être <strong>acceptée</strong>.</p>'
    . email_html_callout($sum, 'success')
    . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="font-size:15px;">'
    . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#64748b;width:38%;">N° dossier</td>'
    . '<td style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-weight:600;color:#0f172a;">#' . $eid . '</td></tr>'
    . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#64748b;">Nom</td>'
    . '<td style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-weight:600;color:#0f172a;">' . $name . '</td></tr>'
    . '<tr><td style="padding:8px 0;color:#64748b;">E-mail</td>'
    . '<td style="padding:8px 0;"><a href="mailto:' . $mail . '" style="color:#2563eb;font-weight:600;">' . $mail . '</a></td></tr>'
    . '</table>'
    . email_html_button($reviewUrl, 'Voir la candidature', 'emerald')
    . email_html_url_fallback($reviewUrl);

$html = email_html_layout(
    'Candidature acceptée — #' . $eid . ' — ' . $tenantName,
    'Candidature acceptée',
    $body,
    ['accent' => 'emerald']
);

$text = "Candidature acceptée — « {$tenantName} »\n"
    . "- N° dossier : #{$eid}\n"
    . "- Nom : {$candidateFullName}\n"
    . "- Email : {$candidateEmail}\n\n"
    . "{$summaryLine}\n\n"
    . "Voir : {$reviewUrl}\n";

return ['html' => $html, 'text' => $text];
