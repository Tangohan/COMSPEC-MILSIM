<?php

declare(strict_types=1);

namespace App\Services\Personnel;

/** Paramètres tenant du parcours administratif, distincts des rôles d'habilitation. */
final class PersonnelLifecycleSettings
{
    public const SETTINGS_KEY = 'personnel_lifecycle';

    /** @return array{training_days: int, active_service_days: int} */
    public static function resolve(array $settings): array
    {
        $raw = is_array($settings[self::SETTINGS_KEY] ?? null) ? $settings[self::SETTINGS_KEY] : [];

        return [
            'training_days' => self::days($raw['training_days'] ?? 14, 14),
            'active_service_days' => self::days($raw['active_service_days'] ?? 0, 0),
        ];
    }

    /** @return array{training_days: int, active_service_days: int} */
    public static function fromInput(mixed $trainingDays, mixed $activeServiceDays): array
    {
        return [
            'training_days' => self::days($trainingDays, 14),
            'active_service_days' => self::days($activeServiceDays, 0),
        ];
    }

    private static function days(mixed $value, int $default): int
    {
        if (!is_numeric($value)) {
            return $default;
        }

        return max(0, min(3650, (int) $value));
    }
}
