<?php
/** @var string $displayName */
/** @var string $tenantName */
/** @var string $deploymentUrl */
/** @var string|null $campaignTag */
/** @var string|null $eventTitle */

$campaignTag = trim((string) ($campaignTag ?? ''));
$eventTitle = trim((string) ($eventTitle ?? ''));

$contextHtml = '';
if ($campaignTag !== '') {
    $contextHtml .= '<p><strong>Campagne :</strong> ' . htmlspecialchars($campaignTag, ENT_QUOTES, 'UTF-8') . '</p>';
}
if ($eventTitle !== '') {
    $contextHtml .= '<p><strong>Événement lié :</strong> ' . htmlspecialchars($eventTitle, ENT_QUOTES, 'UTF-8') . '</p>';
}

$bodyHtml = ''
    . '<p>Bonjour ' . htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8') . ',</p>'
    . '<p>Vous avez été marqué comme <strong>déployé</strong> sur <strong>' . htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
    . $contextHtml
    . '<p>Un <strong>check-up obligatoire</strong> doit être complété avant validation opérationnelle (mods, qualifications, recyclage, VMP, entretien + données personnelles).</p>'
    . '<p><a href="' . htmlspecialchars((string) $deploymentUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:10px 14px;border-radius:8px;background:#0f172a;color:#fff;text-decoration:none;font-weight:700;">Ouvrir la page de déploiement</a></p>'
    . '<p style="color:#64748b;font-size:12px;">Si vous constatez une erreur, utilisez le formulaire de signalement d’anomalie sur cette page.</p>';

$bodyText = "Bonjour {$displayName},\n\n"
    . "Vous avez été marqué comme déployé sur {$tenantName}.\n"
    . ($campaignTag !== '' ? "Campagne: {$campaignTag}\n" : '')
    . ($eventTitle !== '' ? "Événement lié: {$eventTitle}\n" : '')
    . "Un check-up obligatoire doit être complété avant validation.\n\n"
    . "Accès : {$deploymentUrl}\n";
