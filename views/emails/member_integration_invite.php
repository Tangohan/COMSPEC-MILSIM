<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $title */
/** @var string $when */
/** @var string $actionUrl */
/** @var string $icsUrl */

$name = htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8');
$t = htmlspecialchars((string) $title, ENT_QUOTES, 'UTF-8');
$w = htmlspecialchars((string) $when, ENT_QUOTES, 'UTF-8');

$body = '<p>Bonjour ' . $name . ',</p>'
    . '<p>Vous êtes invité au rendez-vous <strong>' . $t . '</strong>'
    . ($when !== '' ? ' le ' . $w : '') . '.</p>'
    . '<p>Répondez Oui, Peut-être ou Non depuis le lien ci-dessous. Aucun autre destinataire n’est mentionné.</p>'
    . email_html_button($actionUrl, 'Répondre à l’invitation', 'emerald')
    . email_html_url_fallback($actionUrl)
    . email_html_button($icsUrl, 'Ajouter au calendrier', 'slate')
    . email_html_url_fallback($icsUrl);

$html = email_html_layout('Invitation à un rendez-vous', 'Invitation', $body, ['accent' => 'emerald']);
$text = "Bonjour {$displayName},\n\nInvitation : {$title}"
    . ($when !== '' ? "\nQuand : {$when}" : '')
    . "\n\nRépondre : {$actionUrl}\nAjouter au calendrier : {$icsUrl}\n";

return ['html' => $html, 'text' => $text];
