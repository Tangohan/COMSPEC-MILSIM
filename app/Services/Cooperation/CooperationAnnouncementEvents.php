<?php

declare(strict_types=1);

namespace App\Services\Cooperation;

/**
 * Clés d’événements pour les gabarits d’annonces (stockage technique — libellés métier côté UI).
 */
final class CooperationAnnouncementEvents
{
    public const MISSION_CREATED = 'coop_mission_created';

    public const PROPOSAL_UPDATED = 'coop_proposal_updated';

    public const INVITATION_SENT = 'coop_invitation_sent';

    public const PARTNER_ACCEPTED = 'coop_partner_accepted';

    public const PARTNER_DECLINED = 'coop_partner_declined';

    public const MISSION_ACTIVATED = 'coop_mission_activated';

    public const MISSION_CLOSED = 'coop_mission_closed';

    public const MEMBER_DESIGNATED = 'coop_member_designated';

    public const CO_LEAD_DESIGNATED = 'coop_co_lead_designated';

    public const COUNTER_PROPOSAL_SUBMITTED = 'coop_counter_proposal_submitted';

    public const COUNTER_PROPOSAL_ACCEPTED = 'coop_counter_proposal_accepted';

    public const COUNTER_PROPOSAL_DECLINED = 'coop_counter_proposal_declined';

    public const OPERATIONAL_STAGE_UPDATED = 'coop_operational_stage_updated';

    /** @return array<string, string> key => libellé interface */
    public static function labels(): array
    {
        return [
            self::MISSION_CREATED => 'Création d’un dossier de coopération',
            self::PROPOSAL_UPDATED => 'Mise à jour de la proposition',
            self::INVITATION_SENT => 'Invitation envoyée à une autre communauté',
            self::PARTNER_ACCEPTED => 'Une communauté a accepté l’invitation',
            self::PARTNER_DECLINED => 'Une communauté a décliné l’invitation',
            self::MISSION_ACTIVATED => 'La coopération est ouverte (active)',
            self::MISSION_CLOSED => 'La coopération est clôturée',
            self::MEMBER_DESIGNATED => 'Désignation d’un rôle sur la coopération',
            self::CO_LEAD_DESIGNATED => 'Désignation d’une unité co-pilote',
            self::COUNTER_PROPOSAL_SUBMITTED => 'Contre-proposition reçue',
            self::COUNTER_PROPOSAL_ACCEPTED => 'Contre-proposition acceptée',
            self::COUNTER_PROPOSAL_DECLINED => 'Contre-proposition refusée',
            self::OPERATIONAL_STAGE_UPDATED => 'Changement d’étape de conduite',
        ];
    }

    /** @return list<string> */
    public static function allKeys(): array
    {
        return array_keys(self::labels());
    }

    public static function isKnown(string $key): bool
    {
        return isset(self::labels()[$key]);
    }

    /** @return array<string, string> */
    public static function channelLabels(): array
    {
        return [
            'email' => 'Courriel',
            'in_app' => 'Notifications du portail',
            'forum' => 'Publication sur le forum',
        ];
    }

    /**
     * Gabarits intégrés (portail) si aucun message type actif n’est configuré.
     *
     * @return array{subject: string, body: string}|null
     */
    public static function builtinInApp(string $eventKey): ?array
    {
        $label = self::labels()[$eventKey] ?? null;
        if ($label === null) {
            return null;
        }

        return match ($eventKey) {
            self::MEMBER_DESIGNATED => [
                'subject' => 'Rôle attribué — {titre_cooperation}',
                'body' => "Vous avez été désigné(e) comme {role_attribue} sur la coopération « {titre_cooperation} ».\n\nOuvrir la synthèse : {lien_synthese}",
            ],
            self::CO_LEAD_DESIGNATED => [
                'subject' => 'Co-pilotage — {titre_cooperation}',
                'body' => "Votre communauté est désormais co-pilote de « {titre_cooperation} » (unité support : {unite_support}).\n\n{lien_synthese}",
            ],
            self::COUNTER_PROPOSAL_SUBMITTED => [
                'subject' => 'Contre-proposition — {titre_cooperation}',
                'body' => "{unite_destinataire} a transmis une contre-proposition pour « {titre_cooperation} ».\n\nTraiter dans Négociation : {lien_negociation}",
            ],
            self::COUNTER_PROPOSAL_ACCEPTED => [
                'subject' => 'Contre-proposition acceptée — {titre_cooperation}',
                'body' => "L’unité support a pris en compte votre contre-proposition pour « {titre_cooperation} ».\n\n{lien_negociation}",
            ],
            self::COUNTER_PROPOSAL_DECLINED => [
                'subject' => 'Contre-proposition refusée — {titre_cooperation}',
                'body' => "L’unité support a refusé la contre-proposition pour « {titre_cooperation} ».\n\n{lien_negociation}",
            ],
            self::OPERATIONAL_STAGE_UPDATED => [
                'subject' => 'Étape mise à jour — {titre_cooperation}',
                'body' => "L’étape de conduite de « {titre_cooperation} » est maintenant : {etape_conduite}.\n\n{lien_synthese}",
            ],
            default => [
                'subject' => $label . ' — {titre_cooperation}',
                'body' => $label . " pour « {titre_cooperation} ».\n\nVoir la synthèse : {lien_synthese}",
            ],
        };
    }

    /**
     * Gabarits intégrés (courriel) pour les événements critiques personnels.
     *
     * @return array{subject: string, body: string}|null
     */
    public static function builtinEmail(string $eventKey): ?array
    {
        return match ($eventKey) {
            self::MEMBER_DESIGNATED => [
                'subject' => 'Rôle attribué sur « {titre_cooperation} »',
                'body' => "Bonjour,\n\nVous avez été désigné(e) comme {role_attribue} sur la coopération « {titre_cooperation} ».\n\nConsulter le dossier : {lien_synthese}",
            ],
            self::CO_LEAD_DESIGNATED => [
                'subject' => 'Votre communauté co-pilote « {titre_cooperation} »',
                'body' => "Bonjour,\n\nVotre communauté a été désignée co-pilote de la coopération « {titre_cooperation} ».\n\nOuvrir la synthèse : {lien_synthese}",
            ],
            self::COUNTER_PROPOSAL_SUBMITTED => [
                'subject' => 'Contre-proposition reçue — {titre_cooperation}',
                'body' => "{unite_destinataire} a transmis une contre-proposition pour « {titre_cooperation} ».\n\nTraiter : {lien_negociation}",
            ],
            default => null,
        };
    }
}
