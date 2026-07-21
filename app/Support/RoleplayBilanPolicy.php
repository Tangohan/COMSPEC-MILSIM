<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use Throwable;

/**
 * Politique de cadence des bilans roleplay : l’intervalle entre deux bilans se resserre
 * pour les membres récents et s’espace avec l’ancienneté dans la communauté.
 * Ancienneté = depuis la date de création du compte (users.created_at).
 */
final class RoleplayBilanPolicy
{
    /** Ancienneté < 1 an : bilan tous les 6 mois. */
    public const FIRST_YEAR_INTERVAL_DAYS = 180;

    /** Ancienneté entre 1 et 2 ans : bilan tous les 8 mois. */
    public const SECOND_YEAR_INTERVAL_DAYS = 240;

    /** Ancienneté > 2 ans : bilan une fois par an. */
    public const ONGOING_INTERVAL_DAYS = 365;

    /** Marge avant de considérer un bilan en retard (et pas seulement dû). */
    public const OVERDUE_GRACE_DAYS = 14;

    public static function intervalDaysForSeniority(int $seniorityDays): int
    {
        if ($seniorityDays < 365) {
            return self::FIRST_YEAR_INTERVAL_DAYS;
        }
        if ($seniorityDays < 730) {
            return self::SECOND_YEAR_INTERVAL_DAYS;
        }

        return self::ONGOING_INTERVAL_DAYS;
    }

    public static function nextReviewDueAt(?string $joinedAt, ?string $lastReviewAt): ?DateTimeImmutable
    {
        $joinedAt = trim((string) $joinedAt);
        $lastReviewAt = trim((string) $lastReviewAt);
        $base = $lastReviewAt !== '' ? $lastReviewAt : $joinedAt;
        if ($base === '') {
            return null;
        }
        try {
            $baseDate = new DateTimeImmutable($base);
            $joinedDate = $joinedAt !== '' ? new DateTimeImmutable($joinedAt) : $baseDate;
        } catch (Throwable) {
            return null;
        }
        $seniorityDays = (int) $joinedDate->diff(new DateTimeImmutable('now'))->days;
        $interval = self::intervalDaysForSeniority($seniorityDays);

        return $baseDate->modify('+' . $interval . ' days');
    }

    public static function isDue(?string $joinedAt, ?string $lastReviewAt): bool
    {
        $due = self::nextReviewDueAt($joinedAt, $lastReviewAt);

        return $due !== null && $due <= new DateTimeImmutable('now');
    }

    public static function isOverdue(?string $joinedAt, ?string $lastReviewAt): bool
    {
        $due = self::nextReviewDueAt($joinedAt, $lastReviewAt);

        return $due !== null && $due->modify('+' . self::OVERDUE_GRACE_DAYS . ' days') < new DateTimeImmutable('now');
    }
}
