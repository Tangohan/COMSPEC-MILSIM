<?php

declare(strict_types=1);

namespace App\Repositories;

final class TenantAdminSettingsRepository
{
    public function __construct(
        private ?TenantRepository $tenantRepository = null,
    ) {
        $this->tenantRepository ??= new TenantRepository();
    }

    /**
     * @return array<string, mixed>
     */
    public function getForTenant(int $tenantId): array
    {
        $settings = $this->tenantRepository->getSettings($tenantId);
        $raw = is_array($settings['admin_runtime'] ?? null) ? $settings['admin_runtime'] : [];

        return array_replace_recursive($this->defaults(), $raw);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function sanitize(array $input): array
    {
        $current = array_replace_recursive($this->defaults(), $input);

        return [
            'portal' => [
                'public_registrations' => $this->bool($current['portal']['public_registrations'] ?? true),
                'manual_validation' => $this->bool($current['portal']['manual_validation'] ?? true),
                'public_wall' => $this->bool($current['portal']['public_wall'] ?? false),
                'timezone' => $this->timezone((string) ($current['portal']['timezone'] ?? 'Europe/Paris')),
            ],
            'notifications' => [
                'automatic_rsvp_reminders' => $this->bool($current['notifications']['automatic_rsvp_reminders'] ?? true),
                'discord_notifications' => $this->bool($current['notifications']['discord_notifications'] ?? false),
                'emergency_sms' => $this->bool($current['notifications']['emergency_sms'] ?? false),
                'weekly_summary' => $this->bool($current['notifications']['weekly_summary'] ?? true),
            ],
            'security' => [
                'two_factor_auth' => $this->bool($current['security']['two_factor_auth'] ?? false),
                'session_expiration_minutes' => $this->boundedInt($current['security']['session_expiration_minutes'] ?? 120, 15, 1440),
                'account_lockout_attempts' => $this->boundedInt($current['security']['account_lockout_attempts'] ?? 5, 3, 20),
                'extended_audit_logging' => $this->bool($current['security']['extended_audit_logging'] ?? false),
            ],
            'atak_defaults' => [
                'automatic_pairing' => $this->bool($current['atak_defaults']['automatic_pairing'] ?? true),
                'minimum_client_version' => $this->clip((string) ($current['atak_defaults']['minimum_client_version'] ?? '5.1.8'), 32),
                'certificate_duration_days' => $this->boundedInt($current['atak_defaults']['certificate_duration_days'] ?? 365, 1, 1825),
                'off_op_position_sharing' => $this->bool($current['atak_defaults']['off_op_position_sharing'] ?? false),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $settings
     */
    public function saveForTenant(int $tenantId, array $settings): void
    {
        $this->tenantRepository->mergeSettings($tenantId, [
            'admin_runtime' => $this->sanitize($settings),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'portal' => [
                'public_registrations' => true,
                'manual_validation' => true,
                'public_wall' => false,
                'timezone' => 'Europe/Paris',
            ],
            'notifications' => [
                'automatic_rsvp_reminders' => true,
                'discord_notifications' => false,
                'emergency_sms' => false,
                'weekly_summary' => true,
            ],
            'security' => [
                'two_factor_auth' => false,
                'session_expiration_minutes' => 120,
                'account_lockout_attempts' => 5,
                'extended_audit_logging' => false,
            ],
            'atak_defaults' => [
                'automatic_pairing' => true,
                'minimum_client_version' => '5.1.8',
                'certificate_duration_days' => 365,
                'off_op_position_sharing' => false,
            ],
        ];
    }

    private function bool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    private function boundedInt(mixed $value, int $min, int $max): int
    {
        return max($min, min($max, (int) $value));
    }

    private function clip(string $value, int $max): string
    {
        $value = trim($value);
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max);
    }

    private function timezone(string $value): string
    {
        $value = trim($value);
        $zones = \DateTimeZone::listIdentifiers();

        return in_array($value, $zones, true) ? $value : 'Europe/Paris';
    }
}
