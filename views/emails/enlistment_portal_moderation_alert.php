<?php

declare(strict_types=1);

/** @var string $tenantName */
/** @var int $enlistmentId */
/** @var string $recipientAudience */
/** @var string $sourceSideLabel */
/** @var string $categoryLabel */
/** @var string $maskedPreview */

$tn = htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8');
$eid = (int) $enlistmentId;
$cat = htmlspecialchars($categoryLabel, ENT_QUOTES, 'UTF-8');
$prev = htmlspecialchars($maskedPreview, ENT_QUOTES, 'UTF-8');
$side = htmlspecialchars($sourceSideLabel, ENT_QUOTES, 'UTF-8');

$who = $recipientAudience === 'candidate'
    ? 'Vous êtes identifié comme <strong>le candidat</strong> concerné par ce dossier.'
    : 'Vous êtes identifié comme <strong>membre de l’équipe</strong> (recrutement, pilotage ou administration) de cette communauté.';

$body = '<p style="margin:0 0 16px;">' . $who . '</p>'
    . email_html_callout(
        '<strong>Modération automatique</strong> sur le portail de suivi des candidatures de <strong>' . $tn . '</strong>.'
            . '<br><br>Origine du message bloqué : <strong>' . $side . '</strong>.'
            . '<br>Motif : <strong>' . $cat . '</strong>.',
        'warning'
    )
    . '<p style="margin:16px 0 8px;font-size:13px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;">Extrait (partiellement masqué)</p>'
    . '<div style="padding:12px 14px;background-color:#f8fafc;border-radius:10px;border:1px solid #e2e8f0;font-size:14px;color:#475569;">' . $prev . '</div>'
    . '<p style="margin:20px 0 0;font-size:14px;line-height:1.6;color:#334155;">Des mesures automatiques ont été appliquées sur cette communauté (liste restreinte d’adresses IP et d’adresses e-mail).'
    . ' Si vous pensez qu’il s’agit d’une erreur, contactez les responsables par un canal officiel déjà connu de votre organisation.</p>';

$html = email_html_layout(
    'Alerte modération — dossier recrutement #' . $eid,
    'Alerte modération — recrutement',
    $body,
    ['accent' => 'amber']
);

$text = "Alerte modération — « {$tenantName} » — dossier n°{$eid}\n"
    . "Rôle destinataire : {$recipientAudience}\n"
    . "Origine du contenu refusé : {$sourceSideLabel}\n"
    . "Motif : {$categoryLabel}\n"
    . "Extrait : {$maskedPreview}\n";

return ['html' => $html, 'text' => $text];
