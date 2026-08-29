<?php

declare(strict_types=1);

/** @var string $memberDisplayName */
/** @var string $tenantName */
/** @var list<string> $diffLines */
/** @var string $note */
/** @var string $ficheUrl */

$member = htmlspecialchars((string) $memberDisplayName, ENT_QUOTES, 'UTF-8');
$tn = htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8');
$noteHtml = trim((string) $note) !== ''
    ? '<p><strong>Message :</strong> ' . htmlspecialchars((string) $note, ENT_QUOTES, 'UTF-8') . '</p>'
    : '';
$diffHtml = '';
foreach ($diffLines as $line) {
    $diffHtml .= '<li>' . htmlspecialchars((string) $line, ENT_QUOTES, 'UTF-8') . '</li>';
}

$body = '<p>Bonjour ' . $member . ',</p>'
    . '<p>Votre <strong>demande de correction RH</strong> sur la fiche opérateur a bien été transmise à l’organisation de <strong>' . $tn . '</strong>.</p>'
    . '<p>Les champs proposés :</p><ul>' . $diffHtml . '</ul>'
    . $noteHtml
    . '<p>La fiche ne sera mise à jour qu’après confirmation par un organisateur habilité. Vous recevrez un e-mail récapitulatif à la décision.</p>'
    . email_html_button($ficheUrl, 'Ouvrir ma fiche', 'emerald')
    . email_html_url_fallback($ficheUrl);

$html = email_html_layout(
    'Demande de correction RH envoyée',
    'Correction fiche opérateur',
    $body,
    ['accent' => 'blue']
);

$text = "Bonjour {$memberDisplayName},\n\n"
    . "Votre demande de correction RH a été transmise ({$tenantName}).\n\n"
    . implode("\n", $diffLines) . "\n\n"
    . (trim((string) $note) !== '' ? "Message : {$note}\n\n" : '')
    . "Fiche : {$ficheUrl}\n";

return ['html' => $html, 'text' => $text];
