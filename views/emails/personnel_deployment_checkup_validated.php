<?php
/** @var string $displayName */
/** @var string $tenantName */
/** @var string $deploymentUrl */

$bodyHtml = ''
    . '<p>Le check-up de déploiement pour <strong>' . htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8') . '</strong> a été <strong>validé</strong>.</p>'
    . '<p>Communauté : <strong>' . htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
    . '<p><a href="' . htmlspecialchars((string) $deploymentUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:10px 14px;border-radius:8px;background:#0f172a;color:#fff;text-decoration:none;font-weight:700;">Consulter le suivi de déploiement</a></p>';

$bodyText = "Check-up validé pour {$displayName} ({$tenantName}).\n"
    . "Consulter : {$deploymentUrl}\n";
