<?php

declare(strict_types=1);

/**
 * Semis idempotent des gabarits d’annonces coopération — v3 :
 *   - nouveaux événements (retrait, annulation, relance, suspension, reprise, expiration
 *     d’autorisation, point de situation) : portail actif, courriel actif sauf « point de
 *     situation » (facultatif, livré désactivé) ;
 *   - courriels « invitation », « refus » et « lancement » : activés seulement s’ils n’ont
 *     jamais été modifiés par l’administration (updated_at nul), car un gabarit désactivé
 *     coupe désormais le canal au lieu de retomber sur le texte intégré.
 * Les gabarits restent modifiables dans Coopération > Annonces.
 */
return function (PDO $pdo): void {
    $hasTpl = $pdo->query(
        "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cooperation_announcement_templates' LIMIT 1"
    );
    if (!$hasTpl || !$hasTpl->fetch()) {
        echo "cooperation_announcement_templates absente — saut semis v3.\n";

        return;
    }

    // [clé, objet, corps courriel, courriel actif, corps portail]
    $events = [
        [
            'coop_partner_removed',
            'Retrait de la coopération « {titre_cooperation} »',
            "Bonjour,\n\n{unite_support} a retiré votre communauté de la coopération « {titre_cooperation} ». Les accès partagés liés à cette coopération sont fermés.\n{motif}",
            true,
            "{unite_support} a retiré votre communauté de « {titre_cooperation} ». {motif}",
        ],
        [
            'coop_proposal_cancelled',
            'Proposition annulée — {titre_cooperation}',
            "Bonjour,\n\n{unite_support} a annulé la proposition de coopération « {titre_cooperation} » à laquelle votre communauté était invitée.\n{motif}",
            true,
            "{unite_support} a annulé la proposition « {titre_cooperation} ». {motif}",
        ],
        [
            'coop_invitation_reminder',
            'Réponse attendue : « {titre_cooperation} »',
            "Bonjour,\n\n{unite_support} a invité votre communauté à la coopération « {titre_cooperation} » et attend votre réponse{echeance_texte}.",
            true,
            "{unite_support} attend votre réponse à l’invitation « {titre_cooperation} »{echeance_texte}.",
        ],
        [
            'coop_mission_suspended',
            'Coopération suspendue : « {titre_cooperation} »',
            "Bonjour,\n\n{unite_support} a suspendu la coopération « {titre_cooperation} ». L’espace commun passe en lecture seule jusqu’à la reprise.\n{motif}",
            true,
            "{unite_support} a suspendu « {titre_cooperation} » : espace commun en lecture seule. {motif}",
        ],
        [
            'coop_mission_resumed',
            'Reprise de la coopération « {titre_cooperation} »',
            "Bonjour,\n\nLa coopération « {titre_cooperation} » reprend : l’espace commun est de nouveau ouvert.",
            false,
            "La coopération « {titre_cooperation} » reprend : l’espace commun est de nouveau ouvert.",
        ],
        [
            'coop_consent_expiring',
            'Votre autorisation de partage expire bientôt — {titre_cooperation}',
            "Bonjour,\n\nVotre autorisation de partage pour la coopération « {titre_cooperation} » expire le {fin_autorisation}. Sans renouvellement, l’espace commun repassera en lecture seule pour vous.",
            true,
            "Votre autorisation de partage pour « {titre_cooperation} » expire le {fin_autorisation}.",
        ],
        [
            'coop_sitrep_added',
            'Point de situation — {titre_cooperation}',
            "Bonjour,\n\nNouveau point de situation sur la coopération « {titre_cooperation} » :\n{resume_sitrep}",
            false,
            "Nouveau point de situation sur « {titre_cooperation} » : {resume_sitrep}",
        ],
    ];

    $exists = $pdo->prepare(
        'SELECT 1 FROM cooperation_announcement_templates WHERE tenant_id = 0 AND event_key = ? AND channel = ? LIMIT 1'
    );
    $insEmail = $pdo->prepare(
        'INSERT INTO cooperation_announcement_templates (tenant_id, event_key, channel, subject, body, min_interval_hours, is_active, created_at)
         VALUES (0, ?, \'email\', ?, ?, 24, ?, NOW())'
    );
    $insInApp = $pdo->prepare(
        'INSERT INTO cooperation_announcement_templates (tenant_id, event_key, channel, subject, body, min_interval_hours, is_active, created_at)
         VALUES (0, ?, \'in_app\', ?, ?, 0, 1, NOW())'
    );

    $added = 0;
    foreach ($events as [$key, $subject, $body, $emailActive, $inApp]) {
        $exists->execute([$key, 'email']);
        if (!$exists->fetchColumn()) {
            $insEmail->execute([$key, $subject, $body, $emailActive ? 1 : 0]);
            $added++;
        }
        $exists->execute([$key, 'in_app']);
        if (!$exists->fetchColumn()) {
            $insInApp->execute([$key, $subject, $inApp]);
            $added++;
        }
    }

    // Courriels structurants livrés désactivés en v1 et jamais retouchés : on les active.
    $activate = $pdo->prepare(
        'UPDATE cooperation_announcement_templates SET is_active = 1
          WHERE tenant_id = 0 AND channel = \'email\' AND event_key = ? AND is_active = 0 AND updated_at IS NULL'
    );
    $activated = 0;
    foreach (['coop_invitation_sent', 'coop_partner_declined', 'coop_mission_activated'] as $key) {
        $activate->execute([$key]);
        $activated += $activate->rowCount();
    }

    echo ($added + $activated) > 0
        ? "Semis cooperation_announcement_templates v3 : {$added} gabarit(s) ajouté(s), {$activated} courriel(s) activé(s).\n"
        : "Semis cooperation_announcement_templates v3 : déjà à jour.\n";
};
