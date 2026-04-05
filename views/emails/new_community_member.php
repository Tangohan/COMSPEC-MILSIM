<?php

declare(strict_types=1);

/** @var string $tenantName */
/** @var string $memberEmail */
/** @var string $ip */
/** @var string $context */

$tn = htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8');
$html = '<p>Nouvelle inscription / membre sur <strong>' . $tn . '</strong>.</p>'
    . '<ul>'
    . '<li>Email : ' . htmlspecialchars($memberEmail, ENT_QUOTES, 'UTF-8') . '</li>'
    . '<li>IP : ' . htmlspecialchars($ip, ENT_QUOTES, 'UTF-8') . '</li>'
    . '<li>Contexte : ' . htmlspecialchars($context, ENT_QUOTES, 'UTF-8') . '</li>'
    . '</ul>';

$text = "Nouvelle inscription / membre — « {$tenantName} »\n"
    . "- Email : {$memberEmail}\n- IP : {$ip}\n- Contexte : {$context}\n";

return ['html' => $html, 'text' => $text];
