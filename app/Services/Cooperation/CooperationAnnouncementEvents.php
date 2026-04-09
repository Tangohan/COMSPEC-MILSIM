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
}
