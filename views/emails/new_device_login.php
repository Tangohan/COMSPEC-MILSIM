<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $ip */
/** @var string $userAgent */
/** @var string $geo */
/** @var string $denyUrl */

$name = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
$html = '<p>Bonjour ' . $name . ',</p>'
    . '<p>Une nouvelle connexion à votre compte a été détectée :</p><ul>'
    . '<li>IP : ' . htmlspecialchars($ip, ENT_QUOTES, 'UTF-8') . '</li>'
    . '<li>Navigateur / UA : ' . htmlspecialchars(mb_substr($userAgent, 0, 400), ENT_QUOTES, 'UTF-8') . '</li>'
    . '<li>Localisation indicative : ' . htmlspecialchars($geo, ENT_QUOTES, 'UTF-8') . '</li>'
    . '</ul>'
    . '<p>Si ce n\'était pas vous, révoquez les sessions : <a href="' . htmlspecialchars($denyUrl, ENT_QUOTES, 'UTF-8') . '">Ce n’est pas moi</a></p>';

$text = "Bonjour {$displayName},\n\nUne nouvelle connexion a été détectée :\n- IP : {$ip}\n- UA : {$userAgent}\n- Localisation indicative : {$geo}\n\nSi ce n'était pas vous : {$denyUrl}\n";

return ['html' => $html, 'text' => $text];
