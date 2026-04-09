<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Libellés métier centralisés pour le module coopération inter-unités (phases, journal, rôles participant).
 * Les clés techniques restent en anglais en base ; l’UI ne doit afficher que les libellés retournés ici.
 */
final class CooperationDictionary
{
    /** @return list<string> */
    public static function phaseKeys(): array
    {
        return [
            'draft',
            'proposed',
            'negotiating',
            'validated_pending',
            'preparing',
            'active',
            'suspended',
            'closed',
            'archived',
            'cancelled',
        ];
    }

    public static function phaseLabel(string $phase): string
    {
        return match ($phase) {
            'draft' => 'Brouillon',
            'proposed' => 'Proposition envoyée',
            'negotiating' => 'En discussion',
            'validated_pending' => 'Validée sous conditions',
            'preparing' => 'Préparation',
            'active' => 'Coopération en cours',
            'suspended' => 'Suspendue',
            'closed' => 'Coopération clôturée',
            'archived' => 'Archivée',
            'cancelled' => 'Annulée',
            default => 'Autre état',
        };
    }

    /**
     * Phase effective à partir d’une ligne mission (colonne cooperation_phase ou repli sur status historique).
     *
     * @param array<string, mixed> $mission
     */
    public static function effectivePhase(array $mission): string
    {
        if (($mission['counter_proposal_status'] ?? '') === 'pending_host') {
            return 'negotiating';
        }
        $p = trim((string) ($mission['cooperation_phase'] ?? ''));
        if ($p !== '' && in_array($p, self::phaseKeys(), true)) {
            return $p;
        }

        return match ((string) ($mission['status'] ?? '')) {
            'draft' => 'draft',
            'pending' => 'proposed',
            'active' => 'active',
            'archived' => 'closed',
            default => 'draft',
        };
    }

    /** Libellé d’état à partir du seul champ status (ex. listes sans colonne phase). */
    public static function labelFromLegacyStatus(string $status): string
    {
        return self::phaseLabel(match ($status) {
            'draft' => 'draft',
            'pending' => 'proposed',
            'active' => 'active',
            'archived' => 'closed',
            default => 'draft',
        });
    }

    public static function eventTypeLabel(string $eventType): string
    {
        return match ($eventType) {
            'mission_created' => 'Coopération créée',
            'partner_invited' => 'Unité partenaire invitée',
            'partner_accepted' => 'Participation acceptée',
            'partner_declined' => 'Invitation refusée',
            'mission_activated' => 'Coopération lancée',
            'coop_forum_opened' => 'Espace commun ouvert sur le brief',
            'topic_shared' => 'Autorisation d’accès à un espace d’échange du brief',
            'grant_revoked' => 'Autorisation d’accès retirée',
            'mission_closed' => 'Coopération clôturée',
            'mission_meta_updated' => 'Coordination ou liaisons mises à jour',
            'mission_proposal_updated' => 'Proposition ou cadrage mis à jour',
            'co_lead_promoted' => 'Co-pilote désigné',
            'meeting_started' => 'Réunion enregistrée dans le journal',
            'consent_verified' => 'Autorisation de partage confirmée',
            'coop_forum_reply' => 'Message sur l’espace commun',
            'counter_proposal_submitted' => 'Contre-proposition transmise',
            'counter_proposal_accepted' => 'Contre-proposition intégrée par l’unité support',
            'counter_proposal_declined' => 'Contre-proposition refusée par l’unité support',
            'proposal_deadline_elapsed' => 'Date limite de réponse à la proposition dépassée',
            'meeting_scheduled' => 'Réunion planifiée ou ajoutée au journal',
            'rex_submitted' => 'Retour d’expérience enregistré',
            'decision_published' => 'Décision publiée sur l’espace commun',
            default => 'Événement',
        };
    }

    /** Catégorie pour filtrer la chronologie (clé technique). */
    public static function timelineEventCategory(string $eventType): string
    {
        return match ($eventType) {
            'topic_shared', 'grant_revoked', 'consent_verified' => 'access',
            'meeting_started', 'meeting_scheduled' => 'meetings',
            'mission_activated', 'mission_closed', 'counter_proposal_accepted', 'counter_proposal_declined',
            'partner_accepted', 'partner_declined', 'co_lead_promoted', 'decision_published' => 'decisions',
            'counter_proposal_submitted', 'proposal_deadline_elapsed', 'mission_proposal_updated',
            'partner_invited' => 'negotiation',
            'mission_meta_updated', 'coop_forum_opened' => 'coordination',
            'coop_forum_reply' => 'messages',
            'rex_submitted' => 'rex',
            default => 'other',
        };
    }

    /** @return array<string, string> */
    public static function timelineFilterLabels(): array
    {
        return [
            'all' => 'Tout',
            'decisions' => 'Décisions & statuts',
            'negotiation' => 'Proposition & négociation',
            'access' => 'Accès & autorisations',
            'meetings' => 'Réunions',
            'coordination' => 'Coordination & espace commun',
            'messages' => 'Messages',
            'rex' => 'Retours d’expérience',
            'other' => 'Autres',
        ];
    }

    public static function participantRoleLabel(string $role): string
    {
        return match ($role) {
            'lead' => 'Unité support',
            'co_lead' => 'Unité co-pilote',
            'partner' => 'Unité partenaire',
            default => 'Unité engagée',
        };
    }

    public static function participantStateLabel(string $status): string
    {
        return match ($status) {
            'invited' => 'Invitation en attente',
            'active' => 'Confirmée',
            'declined' => 'Refusée',
            'left' => 'Retirée',
            default => 'Autre',
        };
    }

    /** Libellé métier pour un type d’autorisation forum stocké en base. */
    public static function forumGrantTypeLabel(string $grantType): string
    {
        return match ($grantType) {
            'topic' => 'Espace d’échange lié',
            'category' => 'Rubrique partagée',
            default => 'Autorisation d’accès',
        };
    }

    /** @return array<string, string> slug => libellé */
    public static function typologyChoices(): array
    {
        return [
            'formation' => 'Formation',
            'exercice' => 'Exercice',
            'appui_operationnel' => 'Appui opérationnel',
            'coordination_renseignement' => 'Coordination renseignement',
            'liaison_interservices' => 'Liaison interservices',
            'soutien_logistique' => 'Soutien logistique',
            'preparation_mission' => 'Préparation de mission',
            'retour_experience' => 'Retour d’expérience',
        ];
    }

    /** @return array<string, string> */
    public static function priorityChoices(): array
    {
        return [
            'routine' => 'Routine',
            'planifiee' => 'Planifiée',
            'prioritaire' => 'Prioritaire',
            'urgente' => 'Urgente',
        ];
    }

    public static function normalizeTypology(?string $raw): ?string
    {
        $t = trim((string) $raw);
        if ($t === '') {
            return null;
        }

        return array_key_exists($t, self::typologyChoices()) ? $t : null;
    }

    public static function normalizePriority(?string $raw): string
    {
        $t = trim((string) $raw);
        if ($t === '' || !array_key_exists($t, self::priorityChoices())) {
            return 'routine';
        }

        return $t;
    }

    /** Libellé métier pour une famille de données (clé stockée côté serveur). */
    public static function dataSharingFamilyLabel(string $key): string
    {
        return match ($key) {
            'brief' => 'Éléments du brief partagé',
            'liaison' => 'Fréquences et liaisons',
            'competency' => 'Compétences et qualifications utiles',
            'identity' => 'Identité fonctionnelle des personnels engagés',
            'org_structure' => 'Structure d’organisation engagée',
            'qualification' => 'Niveaux de qualification',
            'readiness' => 'Disponibilité opérationnelle',
            'material' => 'Moyens matériels engagés',
            'map' => 'Éléments cartographiques de coordination',
            'documents' => 'Documents préparatoires',
            'minutes' => 'Comptes rendus et synthèses',
            'meeting' => 'Liens ou enregistrements de réunion',
            'cert_excerpt' => 'Extraits de certifications',
            default => 'Autre donnée listée',
        };
    }

    public static function exchangeLockModeLabel(string $mode): string
    {
        return match ($mode) {
            'none' => 'Aucun verrou supplémentaire',
            'full' => 'Espace commun en consultation seule pour tous',
            'main_only' => 'Fil principal : partenaires en consultation seule',
            'after_close' => 'Verrou renforcé après clôture',
            default => 'Autre mode',
        };
    }

    /** @return array<string, string> */
    public static function exchangeLockModeChoices(): array
    {
        return [
            'none' => self::exchangeLockModeLabel('none'),
            'full' => self::exchangeLockModeLabel('full'),
            'main_only' => self::exchangeLockModeLabel('main_only'),
            'after_close' => self::exchangeLockModeLabel('after_close'),
        ];
    }

    /** @return array<string, string> */
    public static function officialMessageKindChoices(): array
    {
        return [
            'discussion' => 'Échange libre',
            'information' => 'Information',
            'decision' => 'Décision',
            'coordination_order' => 'Ordre de coordination',
            'sitrep' => 'Point de situation',
            'alert' => 'Alerte',
            'minutes' => 'Compte rendu',
        ];
    }

    /** @return array<string, string> */
    public static function missionMemberRoleChoices(): array
    {
        return [
            'referent' => 'Référent coopération',
            'validating_authority' => 'Autorité de validation',
            'observer' => 'Observateur',
            'writer' => 'Rédacteur',
            'liaison_officer' => 'Officier liaison',
        ];
    }

    /** @return array<string, string> */
    public static function competencyNeedLabels(): array
    {
        return [
            'chef_mission' => 'Chef de mission',
            'jtac' => 'JTAC / appui feu',
            'medic' => 'Soutien santé',
            'pilote' => 'Pilote / conducteur',
            'analyste' => 'Analyste',
            'radio' => 'Liaison radio',
            'instructeur' => 'Instructeur',
            'logisticien' => 'Logisticien',
        ];
    }

    /** États de réunion enregistrés (journal). */
    public static function meetingStateLabels(): array
    {
        return [
            'planned' => 'Prévue',
            'open' => 'Notée / en cours',
            'ended' => 'Terminée',
            'cancelled' => 'Annulée',
        ];
    }

    /** Titre de section pour affichage d’informations techniques réservées au pilotage. */
    public static function uiTechnicalDetailsSectionTitle(): string
    {
        return 'Détails techniques (pilotage)';
    }
}
