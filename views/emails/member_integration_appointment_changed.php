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
    . '<p>Le rendez-vous <strong>' . $t . '</strong> a été modifié'
    . ($when !== '' ? ' (nouvelle horaire : ' . $w . ')' : '') . '.</p>'
    . email_html_button($actionUrl, 'Voir le rendez-vous', 'emerald')
    . email_html_url_fallback($actionUrl)
    . email_html_button($icsUrl, 'Ajouter au calendrier', 'slate');

$html = email_html_layout('Rendez-vous modifié', 'Modification', $body, ['accent' => 'amber']);
$text = "Bonjour {$displayName},\n\nLe rendez-vous « {$title} » a été modifié.\n{$actionUrl}\nCalendrier : {$icsUrl}\n";

return ['html' => $html, 'text' => $text];
