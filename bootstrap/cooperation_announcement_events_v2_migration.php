<?php

declare(strict_types=1);

/**
 * Semis idempotent des nouveaux gabarits d’annonces coopération
 * (désignation, contre-proposition, étape de conduite).
 */
return function (PDO $pdo): void {
    $hasTpl = $pdo->query(
        "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cooperation_announcement_templates' LIMIT 1"
    );
    if (!$hasTpl || !$hasTpl->fetch()) {
        echo "cooperation_announcement_templates absente — saut semis v2.\n";

        return;
    }

    $events = [
        [
            'coop_member_designated',
            'Rôle attribué — {titre_cooperation}',
            "Vous avez été désigné(e) comme {role_attribue} sur la coopération « {titre_cooperation} ».\n\nOuvrir la synthèse : {lien_synthese}",
            true,
        ],
        [
            'coop_co_lead_designated',
            'Co-pilotage — {titre_cooperation}',
            "Votre communauté est désormais co-pilote de « {titre_cooperation} » (unité support : {unite_support}).\n\n{lien_synthese}",
            true,
        ],
        [
            'coop_counter_proposal_submitted',
            'Contre-proposition — {titre_cooperation}',
            "{unite_destinataire} a transmis une contre-proposition pour « {titre_cooperation} ».\n\nTraiter dans Négociation : {lien_negociation}",
            true,
        ],
        [
            'coop_counter_proposal_accepted',
            'Contre-proposition acceptée — {titre_cooperation}',
            "L’unité support a pris en compte votre contre-proposition pour « {titre_cooperation} ».\n\n{lien_negociation}",
            false,
        ],
        [
            'coop_counter_proposal_declined',
            'Contre-proposition refusée — {titre_cooperation}',
            "L’unité support a refusé la contre-proposition pour « {titre_cooperation} ».\n\n{lien_negociation}",
            false,
        ],
        [
            'coop_operational_stage_updated',
            'Étape mise à jour — {titre_cooperation}',
            "L’étape de conduite de « {titre_cooperation} » est maintenant : {etape_conduite}.\n\n{lien_synthese}",
            false,
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
    foreach ($events as [$key, $subject, $body, $emailActive]) {
        $exists->execute([$key, 'email']);
        if (!$exists->fetchColumn()) {
            $insEmail->execute([$key, $subject, $body, $emailActive ? 1 : 0]);
            $added++;
        }
        $exists->execute([$key, 'in_app']);
        if (!$exists->fetchColumn()) {
            $short = $subject . ' — ' . mb_substr(preg_replace('/\s+/', ' ', $body) ?? $body, 0, 200);
            $insInApp->execute([$key, $subject, $short]);
            $added++;
        }
    }

    echo $added > 0
        ? "Semis cooperation_announcement_templates v2 : {$added} gabarit(s) ajouté(s).\n"
        : "Semis cooperation_announcement_templates v2 : déjà à jour.\n";
};
