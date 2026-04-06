<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $tenantName */
/** @var string $activityUrl */

$body = '<p>Bonjour ' . htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8') . ',</p>'
    . '<p>Nous avons bien reçu votre signalement ou votre demande sur <strong>' . htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8') . '</strong>. '
    . 'L’équipe de modération en prendra connaissance dans les meilleurs délais.</p>'
    . email_html_callout('Vous recevrez un message lorsque la situation aura été examinée, si vous avez activé les notifications correspondantes dans vos préférences.', 'info')
    . email_html_button($activityUrl, 'Voir mes notifications', 'slate')
    . email_html_url_fallback($activityUrl);

$html = email_html_layout(
    'Votre demande a bien été transmise',
    'Demande transmise',
    $body,
    ['accent' => 'rose']
);

$text = "Bonjour {$displayName},\n\n"
    . "Nous avons bien reçu votre signalement ou votre demande concernant « {$tenantName} ».\n"
    . "L’équipe de modération en prendra connaissance.\n\n"
    . "Centre d’activité : {$activityUrl}\n";

return ['html' => $html, 'text' => $text];
