<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $actorDisplayName */
/** @var string $tenantName */
/** @var string $stepTitle */
/** @var string $stepDetail */
/** @var string $actionUrl */
/** @var string $actionLabel */

$name = htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8');
$actor = htmlspecialchars((string) $actorDisplayName, ENT_QUOTES, 'UTF-8');
$tn = htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8');
$step = htmlspecialchars((string) $stepTitle, ENT_QUOTES, 'UTF-8');
$detail = htmlspecialchars((string) $stepDetail, ENT_QUOTES, 'UTF-8');

$body = '<p>Bonjour ' . $name . ',</p>'
    . '<p><strong>' . $actor . '</strong> vous a nommé sur <strong>' . $tn . '</strong> à l’étape « <strong>' . $step . '</strong> » de la chaîne pédagogique.</p>'
    . '<p>' . $detail . '</p>'
    . email_html_button($actionUrl, $actionLabel, 'emerald')
    . email_html_url_fallback($actionUrl)
    . '<p style="margin-top:20px;font-size:14px;color:#64748b;">Si cette nomination vous surprend, répondez à la personne qui vous a nommé ou à l’équipe formation de votre communauté.</p>';

$html = email_html_layout(
    'Nomination — ' . $stepTitle,
    'Chaîne pédagogique',
    $body,
    ['accent' => 'emerald']
);

$text = "Bonjour {$displayName},\n\n"
    . "{$actorDisplayName} vous a nommé sur « {$tenantName} » à l’étape « {$stepTitle} » de la chaîne pédagogique.\n\n"
    . "{$stepDetail}\n\n"
    . "{$actionLabel} : {$actionUrl}\n";

return ['html' => $html, 'text' => $text];
