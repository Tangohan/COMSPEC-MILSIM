<?php

declare(strict_types=1);

/** @var string $tenantName */
/** @var string $candidateFullName */
/** @var int $ageDays */
/** @var int $enlistmentId */
/** @var string $reviewUrl */

$tn = htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8');
$name = htmlspecialchars($candidateFullName, ENT_QUOTES, 'UTF-8');
$age = max(30, (int) $ageDays);
$eid = (int) $enlistmentId;

$body = '<p>Sur <strong>' . $tn . '</strong>, le dossier de <strong>' . $name . '</strong> a plus de <strong>' . $age . ' jours</strong>.</p>'
    . email_html_callout(
        'Une courte note de bilan aide à améliorer le processus de recrutement pour les prochaines candidatures.',
        'info'
    )
    . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="font-size:15px;">'
    . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#64748b;width:38%;">N° dossier</td>'
    . '<td style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-weight:600;color:#0f172a;">#' . $eid . '</td></tr>'
    . '<tr><td style="padding:8px 0;color:#64748b;">Candidat</td>'
    . '<td style="padding:8px 0;font-weight:600;color:#0f172a;">' . $name . '</td></tr>'
    . '</table>'
    . email_html_button($reviewUrl, 'Renseigner le bilan', 'emerald')
    . email_html_url_fallback($reviewUrl);

$html = email_html_layout(
    'Bilan recrutement — #' . $eid . ' — ' . $tenantName,
    'Bilan à renseigner',
    $body,
    ['accent' => 'emerald']
);

$text = "Bilan recrutement à renseigner — « {$tenantName} »\n"
    . "- N° dossier : #{$eid}\n"
    . "- Candidat : {$candidateFullName}\n"
    . "- Ancienneté : {$age} jours\n\n"
    . "Ouvrir le dossier : {$reviewUrl}\n";

return ['html' => $html, 'text' => $text];
