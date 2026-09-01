<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $tenantName */
/** @var string $pageUrl */

$name = htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8');
$tn = htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8');
$url = htmlspecialchars((string) $pageUrl, ENT_QUOTES, 'UTF-8');

$body = '<p>Bonjour ' . $name . ',</p>'
    . '<p>Votre parcours d’intégration dans <strong>' . $tn . '</strong> est ouvert. Consultez les prochaines étapes et votre référent depuis votre espace.</p>'
    . email_html_button($pageUrl, 'Voir mon intégration', 'emerald')
    . email_html_url_fallback($pageUrl);

$html = email_html_layout('Parcours d’intégration', 'Bienvenue', $body, ['accent' => 'emerald']);
$text = "Bonjour {$displayName},\n\nVotre parcours d’intégration dans « {$tenantName} » est ouvert.\n\n{$pageUrl}\n";

return ['html' => $html, 'text' => $text];
