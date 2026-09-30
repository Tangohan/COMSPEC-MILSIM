<?php

declare(strict_types=1);

namespace App\Support;

final class AdvancementCodes
{
    public const VIA_INITIAL = 'initial';
    public const VIA_SENIORITY = 'seniority';
    public const VIA_CHOICE = 'choice';

    public const CAMPAIGN_OPEN = 'open';
    public const CAMPAIGN_CLOSED = 'closed';
    public const CAMPAIGN_IN_COMMISSION = 'in_commission';
    public const CAMPAIGN_PUBLISHED = 'published';
    public const CAMPAIGN_ARCHIVED = 'archived';

    public const OPINION_PROPOSED = 'proposed';
    public const OPINION_NOT_PROPOSED = 'not_proposed';

    public const DECISION_INSCRIBED = 'inscribed';
    public const DECISION_NOT_INSCRIBED = 'not_inscribed';

    public const MEMBER_TITULAR = 'titular';
    public const MEMBER_DEPUTY = 'deputy';

    public const EQUIP_ISSUED = 'issued';
    public const EQUIP_REPAIR = 'repair';
    public const EQUIP_LOST = 'lost';
    public const EQUIP_RETURNED = 'returned';

    public static function campaignLabel(string $status): string
    {
        return match ($status) {
            'ouverte', self::CAMPAIGN_OPEN => 'Ouverte',
            'cloturee', self::CAMPAIGN_CLOSED => 'Clôturée',
            'en_commission', self::CAMPAIGN_IN_COMMISSION => 'En commission',
            'publiee', self::CAMPAIGN_PUBLISHED => 'Publiée',
            'archivee', self::CAMPAIGN_ARCHIVED => 'Archivée',
            default => $status,
        };
    }

    public static function viaLabel(string $via): string
    {
        return match ($via) {
            self::VIA_INITIAL => 'Initial',
            self::VIA_SENIORITY, 'anciennete' => 'Ancienneté',
            self::VIA_CHOICE, 'choix' => 'Choix',
            default => $via,
        };
    }

    public static function opinionLabel(?string $opinion): string
    {
        return match ($opinion) {
            self::OPINION_PROPOSED, 'propose' => 'Proposé',
            self::OPINION_NOT_PROPOSED, 'non_propose' => 'Non proposé',
            default => '—',
        };
    }

    public static function decisionLabel(?string $decision): string
    {
        return match ($decision) {
            self::DECISION_INSCRIBED, 'inscrit' => 'Inscrit',
            self::DECISION_NOT_INSCRIBED, 'non_inscrit' => 'Non inscrit',
            default => '—',
        };
    }

    public static function equipmentLabel(string $status): string
    {
        return match ($status) {
            self::EQUIP_ISSUED => 'En dotation',
            self::EQUIP_REPAIR => 'En réparation',
            self::EQUIP_LOST => 'Perdu',
            self::EQUIP_RETURNED => 'Restitué',
            default => $status,
        };
    }
}
