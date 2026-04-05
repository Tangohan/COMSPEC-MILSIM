<?php

declare(strict_types=1);

/** @var string $email */
/** @var string $ip */
/** @var string $when */
/** @var string $forgotUrl */

$html = '<p>Plusieurs tentatives de connexion ont échoué récemment pour le compte associé à <strong>'
    . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
    . '<ul><li>IP : ' . htmlspecialchars($ip, ENT_QUOTES, 'UTF-8') . '</li>'
    . '<li>Dernière tentative : ' . htmlspecialchars($when, ENT_QUOTES, 'UTF-8') . '</li></ul>'
    . '<p>Si vous avez oublié votre mot de passe : <a href="' . htmlspecialchars($forgotUrl, ENT_QUOTES, 'UTF-8') . '">Réinitialiser</a></p>';

$text = "Alerte sécurité : plusieurs échecs de connexion pour {$email}.\nIP : {$ip}\nDernière tentative : {$when}\n\nRéinitialisation : {$forgotUrl}\n";

return ['html' => $html, 'text' => $text];
