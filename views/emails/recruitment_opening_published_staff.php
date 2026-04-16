<?php

declare(strict_types=1);

/** @var string $tenantName */
/** @var string $openingTitle */
/** @var string $referencePublic */
/** @var string $publicAvisUrl */
/** @var string $candidaterUrl */
/** @var int $openingId */

$tn = htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8');
$title = htmlspecialchars($openingTitle, ENT_QUOTES, 'UTF-8');
$ref = htmlspecialchars($referencePublic, ENT_QUOTES, 'UTF-8');
$oid = (int) $openingId;

$linksBlock = '';
if ($publicAvisUrl !== '') {
    $linksBlock .= email_html_button($publicAvisUrl, 'Voir l’avis de poste (page publique)', 'indigo');
    $linksBlock .= email_html_url_fallback($publicAvisUrl);
}
if ($candidaterUrl !== '') {
    $linksBlock .= email_html_button($candidaterUrl, 'Lien pour postuler', 'emerald');
    $linksBlock .= email_html_url_fallback($candidaterUrl);
}
if ($linksBlock === '') {
    $linksBlock = email_html_callout('Les liens publics ne sont pas disponibles (paramètres de la communauté). Vérifiez la vitrine depuis le back-office.', 'info');
}

$body = '<p>Une <strong>nouvelle offre de poste</strong> vient d’être <strong>publiée</strong> sur <strong>' . $tn . '</strong>.</p>'
    . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="font-size:15px;">'
    . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#64748b;width:38%;">Intitulé</td>'
    . '<td style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-weight:600;color:#0f172a;">' . $title . '</td></tr>'
    . '<tr><td style="padding:8px 0;color:#64748b;">Référence</td>'
    . '<td style="padding:8px 0;font-weight:600;color:#0f172a;">' . $ref . '</td></tr>'
    . '</table>'
    . $linksBlock;

$html = email_html_layout(
    'Nouvelle offre publiée — ' . $tenantName,
    'Offre publiée',
    $body,
    ['accent' => 'indigo']
);

$text = "Nouvelle offre publiée — « {$tenantName} »\n"
    . "- Intitulé : {$openingTitle}\n"
    . "- Référence : {$referencePublic}\n"
    . "- Réf. interne offre : #{$oid}\n";
if ($publicAvisUrl !== '') {
    $text .= "\nPage publique : {$publicAvisUrl}\n";
}
if ($candidaterUrl !== '') {
    $text .= "Postuler : {$candidaterUrl}\n";
}

return ['html' => $html, 'text' => $text];
