<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $tenantName */
/** @var string $editUrl */

$body = '<p>Bonjour ' . htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8') . ',</p>'
    . '<p>Votre <strong>fiche personnelle</strong> sur <strong>' . htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8') . '</strong> est encore <strong>incomplète</strong>. Une fiche à jour facilite la coordination et les affectations.</p>'
    . email_html_callout('Quelques minutes suffisent pour renseigner l’essentiel (identité, disponibilités, etc.).', 'warning')
    . email_html_button($editUrl, 'Compléter ma fiche personnelle', 'amber')
    . email_html_url_fallback($editUrl);

$html = email_html_layout(
    'Complétez votre fiche — ' . $tenantName,
    'Fiche personnelle à compléter',
    $body,
    ['accent' => 'amber']
);

$text = "Bonjour {$displayName},\n\n"
    . "Votre fiche personnelle sur « {$tenantName} » est encore incomplète.\n\n"
    . "Complétez-la ici :\n{$editUrl}\n\n"
    . '— ' . email_brand_name() . "\n";

return ['html' => $html, 'text' => $text];
