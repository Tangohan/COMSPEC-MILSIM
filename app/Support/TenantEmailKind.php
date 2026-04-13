<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Email\EmailEvents;

/**
 * Familles de messages e-mail aux membres (tenant) : alignement kind → permission, préférence, code d’événement.
 */
final class TenantEmailKind
{
    public const ORBAT = 'orbat';

    public const MISSION = 'mission';

    public const ACTIVITY = 'activity';

    public const CUSTOM = 'custom';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::ORBAT, self::MISSION, self::ACTIVITY, self::CUSTOM];
    }

    public static function isValid(string $kind): bool
    {
        return in_array($kind, self::all(), true);
    }

    public static function permissionForKind(string $kind): string
    {
        return match ($kind) {
            self::ORBAT => 'comms.email.send.orbat',
            self::MISSION => 'comms.email.send.mission',
            self::ACTIVITY => 'comms.email.send.activity',
            self::CUSTOM => 'comms.email.send.custom',
            default => 'comms.email.send.custom',
        };
    }

    /** Clé préférence canal email (user_notification_preferences.event_key). */
    public static function notificationPreferenceKey(string $kind): string
    {
        return match ($kind) {
            self::ORBAT => 'tenant.email.orbat',
            self::MISSION => 'tenant.email.mission',
            self::ACTIVITY => 'tenant.email.activity',
            self::CUSTOM => 'tenant.email.custom',
            default => 'tenant.email.custom',
        };
    }

    public static function label(string $kind): string
    {
        return match ($kind) {
            self::ORBAT => 'Structure (ORBAT)',
            self::MISSION => 'Pilotage opérationnel',
            self::ACTIVITY => 'Activités',
            self::CUSTOM => 'Message libre',
            default => 'Message',
        };
    }

    public static function eventCode(string $kind): string
    {
        return match ($kind) {
            self::ORBAT => EmailEvents::TENANT_EMAIL_ORBAT,
            self::MISSION => EmailEvents::TENANT_EMAIL_MISSION,
            self::ACTIVITY => EmailEvents::TENANT_EMAIL_ACTIVITY,
            self::CUSTOM => EmailEvents::TENANT_EMAIL_CUSTOM,
            default => EmailEvents::TENANT_EMAIL_CUSTOM,
        };
    }
}
