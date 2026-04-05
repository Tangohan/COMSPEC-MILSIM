<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $tenantName */
/** @var string $editUrl */

$html = '<p>Bonjour ' . htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8') . ',</p>'
    . '<p>Votre fiche personnelle sur <strong>' . htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8') . '</strong> est encore incomplète.</p>'
    . '<p>Merci de la compléter pour faciliter la coordination opérationnelle :</p>'
    . '<p><a href="' . htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8') . '">Compléter ma fiche personnelle</a></p>'
    . '<p>Si le lien ne s’ouvre pas, copiez cette adresse dans votre navigateur :<br>'
    . '<span style="word-break:break-all;">' . htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8') . '</span></p>';

$text = "Bonjour {$displayName},\n\n"
    . "Votre fiche personnelle sur « {$tenantName} » est encore incomplète.\n\n"
    . "Complétez-la ici :\n{$editUrl}\n\n"
    . "— Athena\n";

return ['html' => $html, 'text' => $text];
