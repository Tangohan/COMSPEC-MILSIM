<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use DateInterval;
use DateTimeImmutable;

/**
 * Calcule l'état temporel d'une attribution (jamais stocké en base).
 */
final class QualificationTemporalStatusService
{
    public const VALID = 'valid';
    public const EXPIRING_SOON = 'expiring_soon';
    public const EXPIRED_GRACE = 'expired_grace';
    public const EXPIRED = 'expired';
    public const NOT_APPLICABLE = 'not_applicable';

    /**
     * @param array<string, mixed> $award personnel_qualifications (+ grace/alert depuis définition)
     * @return array{code: string, label: string, days_remaining: ?int}
     */
    public function resolve(array $award, ?DateTimeImmutable $now = null): array
    {
        $now = $now ?? new DateTimeImmutable('today');
        $admin = strtolower((string) ($award['admin_status'] ?? $award['status'] ?? ''));
        if (!in_array($admin, ['obtained', 'valid', 'expiring', 'expired'], true)) {
            return [
                'code' => self::NOT_APPLICABLE,
                'label' => '',
                'days_remaining' => null,
            ];
        }

        $expiresRaw = $award['expires_at'] ?? null;
        if ($expiresRaw === null || $expiresRaw === '') {
            return [
                'code' => self::VALID,
                'label' => 'Valide',
                'days_remaining' => null,
            ];
        }

        try {
            $expires = new DateTimeImmutable(substr((string) $expiresRaw, 0, 10));
        } catch (\Throwable) {
            return [
                'code' => self::VALID,
                'label' => 'Valide',
                'days_remaining' => null,
            ];
        }

        $diff = (int) $now->diff($expires)->format('%r%a');
        $alertDays = isset($award['alert_before_expiry_days']) ? (int) $award['alert_before_expiry_days'] : 30;
        if ($alertDays < 0) {
            $alertDays = 30;
        }
        $graceDays = isset($award['grace_period_days']) ? (int) $award['grace_period_days'] : 0;
        if ($graceDays < 0) {
            $graceDays = 0;
        }

        if ($diff > $alertDays) {
            return [
                'code' => self::VALID,
                'label' => 'Valide',
                'days_remaining' => $diff,
            ];
        }

        if ($diff >= 0) {
            return [
                'code' => self::EXPIRING_SOON,
                'label' => $diff === 0
                    ? 'Expiration prochaine — expire aujourd’hui'
                    : 'Expiration prochaine — expire dans ' . $diff . ' jour' . ($diff > 1 ? 's' : ''),
                'days_remaining' => $diff,
            ];
        }

        $daysPast = abs($diff);
        if ($graceDays > 0 && $daysPast <= $graceDays) {
            $left = $graceDays - $daysPast;

            return [
                'code' => self::EXPIRED_GRACE,
                'label' => 'Expirée (période de grâce — ' . $left . ' jour' . ($left > 1 ? 's' : '') . ' restant' . ($left > 1 ? 's' : '') . ')',
                'days_remaining' => -$daysPast,
            ];
        }

        return [
            'code' => self::EXPIRED,
            'label' => 'Expirée',
            'days_remaining' => -$daysPast,
        ];
    }

    public function isEffectivelyActive(array $award, ?DateTimeImmutable $now = null): bool
    {
        $admin = strtolower((string) ($award['admin_status'] ?? $award['status'] ?? ''));
        if (!in_array($admin, ['obtained', 'valid', 'expiring'], true)) {
            return false;
        }
        $t = $this->resolve($award, $now);

        return in_array($t['code'], [self::VALID, self::EXPIRING_SOON, self::EXPIRED_GRACE], true);
    }

    public function computeDefaultExpiresAt(?string $obtainedAt, ?int $validityMonths, bool $isPermanent): ?string
    {
        if ($isPermanent || $validityMonths === null || $validityMonths <= 0) {
            return null;
        }
        $base = $obtainedAt && $obtainedAt !== '' ? $obtainedAt : (new DateTimeImmutable('today'))->format('Y-m-d');
        try {
            $dt = new DateTimeImmutable(substr($base, 0, 10));
        } catch (\Throwable) {
            $dt = new DateTimeImmutable('today');
        }

        return $dt->add(new DateInterval('P' . $validityMonths . 'M'))->format('Y-m-d');
    }
}
