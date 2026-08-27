<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Libellés et étapes du portail missions (UI métier, jamais d’enums bruts).
 */
final class MissionsPortalLabels
{
    /** @var list<string> */
    public const PLAN_STEPS = ['draft', 'published', 'live', 'closed'];

    /**
     * @return array{index:int,total:int,label:string,next_label:?string,tone:string,filled:int}
     */
    public static function planProgress(string $status): array
    {
        $status = strtolower(trim($status));
        if (!in_array($status, self::PLAN_STEPS, true)) {
            $status = 'draft';
        }
        $index = (int) array_search($status, self::PLAN_STEPS, true);
        $total = count(self::PLAN_STEPS);
        $filled = $index + 1;
        $next = $index + 1 < $total ? self::PLAN_STEPS[$index + 1] : null;

        $tone = match ($status) {
            'closed' => 'done',
            'live' => 'live',
            'published' => 'progress',
            default => 'prep',
        };

        return [
            'index' => $index,
            'total' => $total,
            'label' => MissionPlanningLabels::status($status),
            'next_label' => $next !== null ? MissionPlanningLabels::status($next) : null,
            'tone' => $tone,
            'filled' => $filled,
        ];
    }

    /**
     * @return array{index:int,total:int,label:string,next_label:?string,tone:string,filled:int}
     */
    public static function cycleProgress(string $status): array
    {
        $steps = ['preparation', 'en_cours', 'cloturee'];
        $status = strtolower(trim($status));
        if (!in_array($status, $steps, true)) {
            $status = 'preparation';
        }
        $index = (int) array_search($status, $steps, true);
        $total = count($steps);
        $next = $index + 1 < $total ? $steps[$index + 1] : null;
        $tone = match ($status) {
            'cloturee' => 'done',
            'en_cours' => 'live',
            default => 'prep',
        };

        return [
            'index' => $index,
            'total' => $total,
            'label' => match ($status) {
                'preparation' => 'Préparation',
                'en_cours' => 'En cours',
                'cloturee' => 'Clôturée',
                default => 'Préparation',
            },
            'next_label' => match ($next) {
                'en_cours' => 'En cours',
                'cloturee' => 'Clôturée',
                default => null,
            },
            'tone' => $tone,
            'filled' => $index + 1,
        ];
    }

    public static function atakUnitStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'linked' => 'En liaison',
            'delayed' => 'Liaison différée',
            'offline' => 'Hors liaison',
            default => 'Hors liaison',
        };
    }

    public static function gatewayStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'active' => 'Active',
            'pending_validation' => 'En attente de validation',
            'open' => 'Ouverte',
            'expired' => 'Expirée',
            'revoked' => 'Annulée',
            default => 'Inconnue',
        };
    }

    public static function takServerLabel(bool $online): string
    {
        return $online ? 'Opérationnel' : 'Maintenance';
    }
}
