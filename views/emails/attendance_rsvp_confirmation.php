<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $tenantName */
/** @var string $eventTitle */
/** @var string $startsAt */
/** @var string $statusLabel */
/** @var string $pointageUrl */

$html = '<p>Bonjour ' . htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8') . ',</p>'
    . '<p>Votre participation à <strong>' . htmlspecialchars((string) $eventTitle, ENT_QUOTES, 'UTF-8') . '</strong> '
    . 'sur <strong>' . htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8') . '</strong> est enregistrée : '
    . '<strong>' . htmlspecialchars((string) $statusLabel, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
    . '<p>Début prévu : ' . htmlspecialchars((string) $startsAt, ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p><a href="' . htmlspecialchars((string) $pointageUrl, ENT_QUOTES, 'UTF-8') . '">Voir le pointage et agenda</a></p>';

$text = "Bonjour {$displayName},\n\n"
    . "Participation : {$eventTitle} — {$statusLabel}\n"
    . "Communauté : {$tenantName}\n"
    . "Début prévu : {$startsAt}\n\n"
    . "Pointage : {$pointageUrl}\n";

return ['html' => $html, 'text' => $text];
