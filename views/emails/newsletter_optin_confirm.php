<?php
$confirmUrl = (string) ($confirmUrl ?? '#');
$unsubscribeUrl = (string) ($unsubscribeUrl ?? '#');
$expiresInHours = (int) ($expiresInHours ?? 48);
$html = '<h1>Confirmez votre inscription</h1>'
    . '<p>Merci. Cliquez sur le bouton ci-dessous pour activer votre inscription newsletter.</p>'
    . '<p><a href="' . htmlspecialchars($confirmUrl, ENT_QUOTES, 'UTF-8') . '">Confirmer mon inscription</a></p>'
    . '<p>Ce lien expire dans ' . $expiresInHours . ' heures.</p>'
    . '<p>Si vous n’êtes pas à l’origine de cette demande, ignorez ce message ou désabonnez-vous ici : '
    . '<a href="' . htmlspecialchars($unsubscribeUrl, ENT_QUOTES, 'UTF-8') . '">se désabonner</a>.</p>';
$text = "Confirmez votre inscription newsletter\n\n"
    . "Lien de confirmation: {$confirmUrl}\n"
    . "Expire dans {$expiresInHours} heures.\n\n"
    . "Désabonnement: {$unsubscribeUrl}\n";

return ['html' => $html, 'text' => $text];
