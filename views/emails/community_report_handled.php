<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $tenantName */
/** @var string $activityUrl */

$body = '<p>Bonjour ' . htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8') . ',</p>'
    . '<p>L’équipe de modération de <strong>' . htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8') . '</strong> a traité le signalement ou la demande que vous aviez envoyé.</p>'
    . '<p style="margin:16px 0 0;">Si vous pensez qu’il reste un problème, vous pouvez à nouveau utiliser le canal d’aide ou de signalement du portail.</p>'
    . email_html_button($activityUrl, 'Ouvrir mon activité', 'emerald')
    . email_html_url_fallback($activityUrl);

$html = email_html_layout(
    'Votre signalement a été traité',
    'Demande traitée',
    $body,
    ['accent' => 'emerald']
);

$text = "Bonjour {$displayName},\n\n"
    . "L’équipe de modération de « {$tenantName} » a traité votre signalement ou votre demande.\n\n"
    . "Mon activité : {$activityUrl}\n";

return ['html' => $html, 'text' => $text];
