<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $tenantName */
/** @var string $eventTitle */
/** @var string $startsAt */
/** @var string $pointageUrl */

$html = '<p>Bonjour ' . htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8') . ',</p>'
    . '<p>Votre <strong>présence</strong> a été enregistrée pour <strong>'
    . htmlspecialchars((string) $eventTitle, ENT_QUOTES, 'UTF-8') . '</strong> '
    . '(' . htmlspecialchars((string) $startsAt, ENT_QUOTES, 'UTF-8') . ') sur '
    . '<strong>' . htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
    . '<p><a href="' . htmlspecialchars((string) $pointageUrl, ENT_QUOTES, 'UTF-8') . '">Retour au pointage</a></p>';

$text = "Bonjour {$displayName},\n\n"
    . "Présence enregistrée : {$eventTitle} ({$startsAt}) — {$tenantName}\n\n"
    . "{$pointageUrl}\n";

return ['html' => $html, 'text' => $text];
