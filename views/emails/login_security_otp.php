<?php

declare(strict_types=1);

/** @var string $displayName */
/** @var string $tenantName */
/** @var string $code */
/** @var int $ttlMinutes */

$isMailboxSelfTest = !empty($isMailboxSelfTest);

$name = htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8');
$tname = htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8');
$codeEsc = htmlspecialchars((string) $code, ENT_QUOTES, 'UTF-8');
$ttl = (int) $ttlMinutes;

if ($isMailboxSelfTest) {
    $body = '<p>Bonjour ' . $name . ',</p>'
        . '<p>Vous avez demandé un <strong>envoi de test</strong> depuis la page des préférences du compte pour <strong>' . $tname . '</strong>. Ce message ne correspond pas à une connexion en cours.</p>'
        . '<p style="font-size:1.5rem;font-weight:bold;letter-spacing:0.2em;margin:20px 0;">' . $codeEsc . '</p>'
        . '<p>Ce code est valable environ ' . $ttl . ' minute(s) et sert uniquement à confirmer que vous recevez bien nos messages. Vous pouvez l’ignorer une fois la vérification faite.</p>';
    $preheader = 'Test de réception — code à six chiffres';
    $heading = 'Code de test';
    $text = "Bonjour {$displayName},\n\n"
        . "Vous avez demandé un envoi de test depuis les préférences du compte ({$tenantName}).\n\n"
        . "Code : {$code}\n\n"
        . "Valable environ {$ttl} minute(s). Ce message ne correspond pas à une connexion en cours.\n";
} else {
    $body = '<p>Bonjour ' . $name . ',</p>'
        . '<p>Une tentative de connexion nécessitant une validation renforcée a été détectée pour votre compte sur <strong>' . $tname . '</strong>.</p>'
        . '<p style="font-size:1.5rem;font-weight:bold;letter-spacing:0.2em;margin:20px 0;">' . $codeEsc . '</p>'
        . '<p>Ce code est valable environ ' . $ttl . ' minute(s). Si vous n’êtes pas à l’origine de cette demande, ignorez ce message et changez votre mot de passe.</p>';
    $preheader = 'Code de connexion — ' . $ttl . ' min';
    $heading = 'Double vérification de connexion';
    $text = "Bonjour {$displayName},\n\n"
        . "Une connexion à votre compte ({$tenantName}) demande un code de vérification.\n\n"
        . "Code : {$code}\n\n"
        . "Valable environ {$ttl} minute(s). Si ce n'est pas vous, ignorez ce message et changez votre mot de passe.\n";
}

$html = email_html_layout(
    $preheader,
    $heading,
    $body,
    ['accent' => 'emerald', 'footer_note' => 'En cas de doute sur l’origine de ce message, changez votre mot de passe et prévenez un administrateur de la communauté.']
);

return ['html' => $html, 'text' => $text];
