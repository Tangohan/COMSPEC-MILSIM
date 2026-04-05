<?php

declare(strict_types=1);

/** @var string $resetUrl */
/** @var int $hoursValid */

$html = '<p>Bonjour,</p>'
    . '<p>Cliquez sur le lien suivant pour réinitialiser votre mot de passe (valide ' . (int) $hoursValid . ' h) :</p>'
    . '<p><a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '">Réinitialiser mon mot de passe</a></p>'
    . '<p>Si vous n’êtes pas à l’origine de cette demande, ignorez ce message.</p>';

$text = "Bonjour,\n\nCliquez sur le lien suivant pour réinitialiser votre mot de passe (valide {$hoursValid} h) :\n\n"
    . $resetUrl . "\n\nSi vous n’êtes pas à l’origine de cette demande, ignorez ce message.\n";

return ['html' => $html, 'text' => $text];
