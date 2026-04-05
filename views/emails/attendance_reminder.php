<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $tenantName */
/** @var string $eventTitle */
/** @var string $startsAt */
/** @var string $pointageUrl */

$html = '<p>Bonjour ' . htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8') . ',</p>'
    . '<p>Rappel : vous êtes inscrit(e) à <strong>' . htmlspecialchars((string) $eventTitle, ENT_QUOTES, 'UTF-8') . '</strong> '
    . 'sur <strong>' . htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
    . '<p>Début prévu : ' . htmlspecialchars((string) $startsAt, ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p>Le jour J, ouvrez le pointage pour enregistrer votre présence sur place : '
    . '<a href="' . htmlspecialchars((string) $pointageUrl, ENT_QUOTES, 'UTF-8') . '">Pointage</a></p>';

$text = "Bonjour {$displayName},\n\n"
    . "Rappel : {$eventTitle} — {$tenantName}\n"
    . "Début prévu : {$startsAt}\n\n"
    . "Pointage : {$pointageUrl}\n";

return ['html' => $html, 'text' => $text];
