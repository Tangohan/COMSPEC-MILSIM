<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $tenantName */
/** @var string $activityUrl */

$body = '<p>Bonjour ' . htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8') . ',</p>'
    . '<p>L’équipe de modération de <strong>' . htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8') . '</strong> a <strong>rouvert</strong> l’examen de votre signalement. Vous n’avez rien à refaire pour l’instant : la communauté traite à nouveau le dossier.</p>'
    . '<p class="text-slate-600 text-sm">Vous recevrez un autre message lorsque le traitement sera terminé.</p>'
    . email_html_button($activityUrl, 'Voir mon activité', 'emerald')
    . email_html_url_fallback($activityUrl);

$html = email_html_layout(
    'Votre signalement est à nouveau examiné',
    'Dossier rouvert',
    $body,
    ['accent' => 'emerald']
);

$text = "Bonjour {$displayName},\n\n"
    . "L’équipe a rouvert l’examen de votre signalement sur « {$tenantName} ».\n"
    . "Vous serez informé lorsque le traitement sera terminé.\n\n"
    . "Votre activité : {$activityUrl}\n";

return ['html' => $html, 'text' => $text];
