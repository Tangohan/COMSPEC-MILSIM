<?php

declare(strict_types=1);

namespace App\Services\Game;

use App\Repositories\TenantAtakConfigRepository;
use App\Support\CommunityMediaDetails;
use App\Support\SilentSchemaMigration;

/**
 * Personnalisation de la fenêtre Arma (image, méthodes d’auth, modules Overwatch).
 */
final class GameOverwatchExperienceService
{
    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'display_name' => '',
            'welcome_message' => '',
            'login_image_path' => '',
            'logo_path' => '',
            'auth_password' => true,
            'auth_otp' => true,
            'auth_steam' => true,
            'allow_auto_reconnect' => true,
            'sync_profile' => true,
            'sync_grade' => true,
            'sync_unit' => true,
            'sync_callsign' => true,
            'sync_avatar' => true,
            'sync_clearances' => true,
            'sync_c2' => true,
            'min_mod_version' => '1.5.0',
            'channel' => 'PROD',
            'update_interval' => 5,
            'bft_enabled' => true,
            'chat_enabled' => true,
            'intel_enabled' => true,
            'photos_enabled' => true,
            'markers_enabled' => true,
            'jtac_enabled' => true,
            'reviewed' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function get(int $tenantId): array
    {
        $merged = $this->defaults();
        $raw = $this->repo()->getOverwatchGameExperienceRaw($tenantId);
        if (is_array($raw)) {
            foreach ($merged as $key => $default) {
                if (array_key_exists($key, $raw)) {
                    $merged[$key] = is_bool($default) ? (bool) $raw[$key] : $raw[$key];
                }
            }
        }

        return $merged;
    }

    /**
     * @param array<string, mixed> $incoming
     * @return array<string, mixed>
     */
    public function put(int $tenantId, array $incoming): array
    {
        $merged = $this->get($tenantId);
        $bools = [
            'auth_password', 'auth_otp', 'auth_steam', 'allow_auto_reconnect',
            'sync_profile', 'sync_grade', 'sync_unit', 'sync_callsign', 'sync_avatar',
            'sync_clearances', 'sync_c2', 'bft_enabled', 'chat_enabled', 'intel_enabled',
            'photos_enabled', 'markers_enabled', 'jtac_enabled',
        ];
        foreach ($bools as $key) {
            if (array_key_exists($key, $incoming)) {
                $merged[$key] = (bool) $incoming[$key];
            }
        }
        if (isset($incoming['display_name'])) {
            $merged['display_name'] = mb_substr(trim((string) $incoming['display_name']), 0, 80);
        }
        if (isset($incoming['welcome_message'])) {
            $merged['welcome_message'] = mb_substr(trim((string) $incoming['welcome_message']), 0, 280);
        }
        if (isset($incoming['login_image_path'])) {
            $merged['login_image_path'] = trim((string) $incoming['login_image_path']);
        }
        if (isset($incoming['logo_path'])) {
            $merged['logo_path'] = trim((string) $incoming['logo_path']);
        }
        if (isset($incoming['min_mod_version'])) {
            $ver = trim((string) $incoming['min_mod_version']);
            $merged['min_mod_version'] = preg_match('/^\d+\.\d+(\.\d+)?$/', $ver) ? $ver : $merged['min_mod_version'];
        }
        $channel = strtoupper(trim((string) ($incoming['channel'] ?? $merged['channel'])));
        $merged['channel'] = in_array($channel, ['PROD', 'BETA', 'DEV'], true) ? $channel : 'PROD';
        $interval = (int) ($incoming['update_interval'] ?? $merged['update_interval']);
        $merged['update_interval'] = max(2, min(60, $interval));
        $merged['reviewed'] = true;
        $this->repo()->saveOverwatchGameExperience($tenantId, $merged);

        return $merged;
    }

    public function loginImageUrl(int $tenantId, array $cfg, ?string $fallbackLogo = null): string
    {
        $fromCfg = CommunityMediaDetails::publicUrl((string) ($cfg['login_image_path'] ?? ''));
        if (is_string($fromCfg) && $fromCfg !== '') {
            return $fromCfg;
        }
        $logo = CommunityMediaDetails::publicUrl((string) ($cfg['logo_path'] ?? ''));
        if (is_string($logo) && $logo !== '') {
            return $logo;
        }
        $fb = trim((string) $fallbackLogo);

        return $fb !== '' ? $fb : '';
    }

    private function repo(): TenantAtakConfigRepository
    {
        SilentSchemaMigration::run(base_path('bootstrap/athena_game_auth_migration.php'));

        return new TenantAtakConfigRepository();
    }
}
