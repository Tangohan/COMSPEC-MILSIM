<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $tenantName */
/** @var string $verifyUrl */
/** @var int $ttlMinutes */

$name = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
$tenant = htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8');
$html = '<p>Bonjour ' . $name . ',</p>'
    . '<p>Votre compte sur <strong>' . $tenant . '</strong> a été créé. Pour activer l’accès au portail, confirmez votre adresse e-mail en cliquant sur le lien ci-dessous (valide ' . (int) $ttlMinutes . ' min) :</p>'
    . '<p><a href="' . htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8') . '">Confirmer mon e-mail</a></p>'
    . '<p>Si vous n’êtes pas à l’origine de cette inscription, ignorez ce message.</p>';

$text = "Bonjour {$displayName},\n\n"
    . "Votre compte sur « {$tenantName} » a été créé. Confirmez votre adresse e-mail en ouvrant ce lien (valide {$ttlMinutes} min) :\n\n"
    . $verifyUrl . "\n\n"
    . "Si vous n’êtes pas à l’origine de cette inscription, ignorez ce message.\n";

return ['html' => $html, 'text' => $text];
