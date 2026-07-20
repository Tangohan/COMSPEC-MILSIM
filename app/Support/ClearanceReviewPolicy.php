<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use Throwable;

/**
 * Politique de péremption de la revue d’habilitation — une clearance accordée doit être
 * reconfirmée périodiquement (ou changée via le circuit d’élévation), faute de quoi elle
 * est considérée « à revoir » dans le tableur et le digest RH.
 */
final class ClearanceReviewPolicy
{
    /** Intervalle avant péremption d’une revue d’habilitation. */
    public const REVIEW_INTERVAL_DAYS = 180;

    /** Aucune habilitation accordée = rien à revoir. */
    public static function isOverdue(?string $clearanceLevel, ?string $reviewedAt): bool
    {
        if (trim((string) $clearanceLevel) === '') {
            return false;
        }
        $reviewedAt = trim((string) $reviewedAt);
        if ($reviewedAt === '') {
            return true;
        }
        try {
            $deadline = (new DateTimeImmutable($reviewedAt))->modify('+' . self::REVIEW_INTERVAL_DAYS . ' days');
        } catch (Throwable) {
            return true;
        }

        return $deadline < new DateTimeImmutable('now');
    }
}
