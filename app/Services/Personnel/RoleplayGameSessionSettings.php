<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\TenantRepository;

final class RoleplayGameSessionSettings
{
    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'hour_categories' => ['Opération', 'Instruction', 'Entraînement', 'Libre'],
            'min_minutes' => 45,
            'min_percent' => 50,
            'late_tolerance_minutes' => 15,
            'heartbeat_timeout_minutes' => 15,
            'sync_enabled' => true,
            // Sessions de jeu des joueurs (PlayerSessionService) : durée qui compte pour la disponibilité et coupure tolérée.
            'player_min_minutes' => PlayerSessionService::DEFAULT_MIN_MINUTES,
            'player_gap_minutes' => PlayerSessionService::DEFAULT_GAP_MINUTES,
        ];
    }

    /**
     * Seuil et coupure tolérée des sessions de jeu d'un joueur, bornés (5 min à 8 h ; 5 à 90 min).
     *
     * @return array{min_minutes: int, gap_minutes: int}
     */
    public static function playerSessionRules(int $tenantId, ?TenantRepository $tenants = null): array
    {
        $cfg = self::forTenant($tenantId, $tenants);

        return ['min_minutes' => (int) $cfg['player_min_minutes'], 'gap_minutes' => (int) $cfg['player_gap_minutes']];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forTenant(int $tenantId, ?TenantRepository $tenants = null): array
    {
        $d = self::defaults();
        if ($tenantId < 1) {
            return $d;
        }
        $tenants ??= new TenantRepository();
        try {
            $settings = $tenants->getSettings($tenantId);
        } catch (\Throwable) {
            return $d;
        }
        $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];
        $stored = is_array($community['roleplay_game_sessions'] ?? null) ? $community['roleplay_game_sessions'] : [];
        $cats = [];
        foreach (($stored['hour_categories'] ?? $d['hour_categories']) as $c) {
            $v = trim((string) $c);
            if ($v !== '' && !in_array($v, $cats, true)) {
                $cats[] = mb_substr($v, 0, 80);
            }
        }

        return [
            'hour_categories' => $cats !== [] ? $cats : $d['hour_categories'],
            'min_minutes' => max(0, min(240, (int) ($stored['min_minutes'] ?? $d['min_minutes']))),
            'min_percent' => max(0, min(100, (int) ($stored['min_percent'] ?? $d['min_percent']))),
            'late_tolerance_minutes' => max(0, min(120, (int) ($stored['late_tolerance_minutes'] ?? $d['late_tolerance_minutes']))),
            'heartbeat_timeout_minutes' => max(5, min(60, (int) ($stored['heartbeat_timeout_minutes'] ?? $d['heartbeat_timeout_minutes']))),
            'sync_enabled' => array_key_exists('sync_enabled', $stored) ? !empty($stored['sync_enabled']) : true,
            'player_min_minutes' => max(5, min(480, (int) ($stored['player_min_minutes'] ?? $d['player_min_minutes']))),
            'player_gap_minutes' => max(5, min(90, (int) ($stored['player_gap_minutes'] ?? $d['player_gap_minutes']))),
        ];
    }

    /**
     * @param array<string, mixed> $post
     */
    public static function saveFromPost(int $tenantId, array $post, TenantRepository $tenants): void
    {
        $cats = [];
        $raw = (string) ($post['rp_session_hour_categories'] ?? '');
        foreach (preg_split('/\R/u', $raw) ?: [] as $line) {
            $v = trim((string) $line);
            if ($v !== '' && !in_array($v, $cats, true)) {
                $cats[] = mb_substr($v, 0, 80);
            }
        }
        $patch = [
            'hour_categories' => $cats !== [] ? $cats : self::defaults()['hour_categories'],
            'min_minutes' => (int) ($post['rp_session_min_minutes'] ?? 45),
            'min_percent' => (int) ($post['rp_session_min_percent'] ?? 50),
            'late_tolerance_minutes' => (int) ($post['rp_session_late_tolerance'] ?? 15),
            'heartbeat_timeout_minutes' => (int) ($post['rp_session_heartbeat_timeout'] ?? 15),
            'sync_enabled' => !empty($post['rp_session_sync_enabled']),
            'player_min_minutes' => max(5, min(480, (int) ($post['rp_player_min_minutes'] ?? PlayerSessionService::DEFAULT_MIN_MINUTES))),
            'player_gap_minutes' => max(5, min(90, (int) ($post['rp_player_gap_minutes'] ?? PlayerSessionService::DEFAULT_GAP_MINUTES))),
        ];
        $all = $tenants->getSettings($tenantId);
        $community = is_array($all['community'] ?? null) ? $all['community'] : [];
        $community['roleplay_game_sessions'] = $patch;
        $all['community'] = $community;
        $tenants->replaceSettings($tenantId, $all);
    }
}
