<?php
$unsubscribeUrl = (string) ($unsubscribeUrl ?? '#');
$html = '<h1>Bienvenue sur la newsletter Athena</h1>'
    . '<p>Votre inscription est confirmée. Vous recevrez les prochaines nouveautés produit et guides opérationnels.</p>'
    . '<p>Vous pouvez vous désabonner à tout moment : '
    . '<a href="' . htmlspecialchars($unsubscribeUrl, ENT_QUOTES, 'UTF-8') . '">lien de désabonnement</a>.</p>';
$text = "Bienvenue sur la newsletter Athena\n\n"
    . "Inscription confirmée.\n"
    . "Désabonnement: {$unsubscribeUrl}\n";

return ['html' => $html, 'text' => $text];
