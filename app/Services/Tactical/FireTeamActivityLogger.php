<?php

declare(strict_types=1);

namespace App\Services\Tactical;

/**
 * Journal Liaison — événements équipes de feu (attribution, composition, couleur).
 */
final class FireTeamActivityLogger
{
    public function __construct(
        private AtakActivityLogService $activityLog,
    ) {
    }

    /**
     * @param array<string, mixed> $team
     * @param array<string, mixed> $extraMeta
     */
    public function record(
        int $tenantId,
        array $team,
        string $action,
        string $label,
        ?string $actor = null,
        array $extraMeta = []
    ): void {
        if ($tenantId < 1 || $label === '') {
            return;
        }
        $mapId = isset($team['map_id']) && $team['map_id'] !== null && (int) $team['map_id'] > 0
            ? (int) $team['map_id']
            : AtakActivityLogService::AUTH_MAP_ID;
        $meta = array_merge([
            'action' => $action,
            'fire_team_id' => (int) ($team['id'] ?? 0),
            'fire_team_label' => (string) ($team['label'] ?? ''),
            'fire_team_color' => (string) ($team['color'] ?? '#2563EB'),
            'fire_team_kind' => (string) ($team['kind'] ?? ''),
        ], $extraMeta);
        $this->activityLog->record(
            $tenantId,
            $mapId,
            AtakActivityLogService::TYPE_FIRE_TEAM,
            $label,
            $actor,
            $meta
        );
    }

    public function actorFromSession(): ?string
    {
        $call = trim((string) (\App\Core\Session::get('callsign') ?? ''));
        if ($call !== '') {
            return $call;
        }
        $dn = trim((string) (\App\Core\Session::get('display_name') ?? ''));

        return $dn !== '' ? $dn : null;
    }
}
