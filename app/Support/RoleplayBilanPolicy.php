<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Personnel\RoleplayFollowupSettings;
use DateTimeImmutable;
use Throwable;

/**
 * Politique de cadence des bilans roleplay.
 * Les constantes restent les défauts historiques ; un tableau de cadence tenant peut les remplacer.
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

    /**
     * @param array<string, mixed>|null $cadence
     */
    public static function intervalDaysForSeniority(int $seniorityDays, ?array $cadence = null): int
    {
        $c = self::normalizeCadence($cadence);
        if ($seniorityDays < 365) {
            return $c['first_year_days'];
        }
        if ($seniorityDays < 730) {
            return $c['second_year_days'];
        }

        return $c['ongoing_days'];
    }

    /**
     * @param array<string, mixed>|null $cadence
     */
    public static function nextReviewDueAt(?string $joinedAt, ?string $lastReviewAt, ?array $cadence = null): ?DateTimeImmutable
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
        $interval = self::intervalDaysForSeniority($seniorityDays, $cadence);

        return $baseDate->modify('+' . $interval . ' days');
    }

    /**
     * @param array<string, mixed>|null $cadence
     */
    public static function isDue(?string $joinedAt, ?string $lastReviewAt, ?array $cadence = null): bool
    {
        $due = self::nextReviewDueAt($joinedAt, $lastReviewAt, $cadence);

        return $due !== null && $due <= new DateTimeImmutable('now');
    }

    /**
     * @param array<string, mixed>|null $cadence
     */
    public static function isOverdue(?string $joinedAt, ?string $lastReviewAt, ?array $cadence = null): bool
    {
        $c = self::normalizeCadence($cadence);
        $due = self::nextReviewDueAt($joinedAt, $lastReviewAt, $cadence);

        return $due !== null && $due->modify('+' . $c['grace_days'] . ' days') < new DateTimeImmutable('now');
    }

    /**
     * @param array<string, mixed>|null $cadence
     * @return array{enabled: bool, first_year_days: int, second_year_days: int, ongoing_days: int, grace_days: int}
     */
    public static function normalizeCadence(?array $cadence): array
    {
        return RoleplayFollowupSettings::bilanCadence(['bilans' => is_array($cadence) ? $cadence : []]);
    }
}
