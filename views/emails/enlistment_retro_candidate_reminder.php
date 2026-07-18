<?php

declare(strict_types=1);

/** @var string $tenantName */
/** @var string $firstName */
/** @var int $ageDays */
/** @var string $portalUrl */

$tn = htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8');
$fn = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
$age = max(30, (int) $ageDays);
$greeting = $fn !== '' && strtolower($fn) !== 'bonjour'
    ? 'Bonjour ' . $fn . ','
    : 'Bonjour,';

$body = '<p>' . $greeting . '</p>'
    . '<p>Votre candidature auprès de <strong>' . $tn . '</strong> a plus de <strong>' . $age . ' jours</strong>.</p>'
    . email_html_callout(
        'Si vous le souhaitez, vous pouvez laisser un court avis sur le déroulement du processus. Cela nous aide à mieux accueillir les prochaines personnes.',
        'info'
    )
    . email_html_button($portalUrl, 'Laisser mon avis', 'emerald')
    . email_html_url_fallback($portalUrl);

$html = email_html_layout(
    'Votre avis sur le recrutement — ' . $tenantName,
    'Votre avis compte',
    $body,
    ['accent' => 'emerald']
);

$text = "Votre avis sur le recrutement — « {$tenantName} »\n\n"
    . "{$greeting}\n\n"
    . "Votre candidature a plus de {$age} jours. Si vous le souhaitez, laissez un court avis :\n"
    . "{$portalUrl}\n";

return ['html' => $html, 'text' => $text];
