<?php

declare(strict_types=1);

/** @var string $tenantName */
/** @var string $fromEmail */
/** @var string $messageBody */

$tn = htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8');
$html = '<p>Message depuis la page publique — <strong>' . $tn . '</strong></p>'
    . '<p>Expéditeur déclaré : ' . htmlspecialchars($fromEmail, ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p>' . nl2br(htmlspecialchars($messageBody, ENT_QUOTES, 'UTF-8')) . '</p>';

$text = "Message — « {$tenantName} »\nExpéditeur : {$fromEmail}\n\n{$messageBody}\n";

return ['html' => $html, 'text' => $text];
