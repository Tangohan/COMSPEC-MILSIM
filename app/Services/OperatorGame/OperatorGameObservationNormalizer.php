<?php

declare(strict_types=1);

namespace App\Services\OperatorGame;

/**
 * Maps the Overwatch observed-operator payload onto the register/sync contract.
 * Nested identity / face / equipment / environment aliases are flattened; empty
 * values stay empty (never invented).
 */
final class OperatorGameObservationNormalizer
{
    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function normalize(array $body): array
    {
        $identity = $this->assoc($body['identity'] ?? null);
        $face = $this->assoc($body['face'] ?? null);
        $medical = $this->assoc($body['medical'] ?? null);
        $equipment = $this->assoc($body['equipment'] ?? null);
        $versions = $this->assoc($body['versions'] ?? null);
        $environment = $this->assoc($body['environment'] ?? null);

        $playerUid = $this->firstString(
            $identity['player_uid'] ?? null,
            $identity['arma_player_uid'] ?? null,
            $body['player_uid'] ?? null,
        );
        $playerName = $this->firstString(
            $identity['player_name'] ?? null,
            $identity['arma_player_name'] ?? null,
        );
        $sex = $this->firstString(
            $identity['sex'] ?? null,
            $identity['sex_detected'] ?? null,
        );
        $identity['player_uid'] = $playerUid;
        $identity['player_name'] = $playerName;
        $identity['sex'] = $sex;
        $identity['sex_detected'] = $this->firstString($identity['sex_detected'] ?? null, $sex);
        $identity['arma_player_uid'] = $this->firstString($identity['arma_player_uid'] ?? null, $playerUid);
        $identity['arma_player_name'] = $this->firstString($identity['arma_player_name'] ?? null, $playerName);
        $identity['face_class'] = $this->firstString($identity['face_class'] ?? null, $face['face_class'] ?? null);
        $identity['face_texture'] = $this->firstString($identity['face_texture'] ?? null, $face['face_texture'] ?? null);
        $identity['display_name'] = $this->firstString(
            $identity['display_name'] ?? null,
            $identity['profile_name'] ?? null,
            $playerName,
        );
        $identity['callsign'] = $this->firstString($identity['callsign'] ?? null);
        $identity['steam_uid'] = $this->firstString(
            $identity['steam_uid'] ?? null,
            $body['steam_uid'] ?? null,
            $body['steam_id'] ?? null,
            $body['steam_uid_session'] ?? null,
        );

        $equipment['uniform'] = $this->firstString($equipment['uniform'] ?? null, $equipment['uniform_class'] ?? null);
        $equipment['vest'] = $this->firstString($equipment['vest'] ?? null, $equipment['vest_class'] ?? null);
        $equipment['backpack'] = $this->firstString($equipment['backpack'] ?? null, $equipment['backpack_class'] ?? null);
        $equipment['headgear'] = $this->firstString(
            $equipment['headgear'] ?? null,
            $equipment['helmet_class'] ?? null,
        );
        $equipment['goggles'] = $this->firstString($equipment['goggles'] ?? null, $equipment['goggles_class'] ?? null);
        $equipment['nvgs'] = $this->firstString($equipment['nvgs'] ?? null, $equipment['nvgs_class'] ?? null);
        $equipment['primary_weapon'] = $this->firstString(
            $equipment['primary_weapon'] ?? null,
            $this->weaponClass($equipment['primary'] ?? null),
        );
        $equipment['secondary_weapon'] = $this->firstString(
            $equipment['secondary_weapon'] ?? null,
            $this->weaponClass($equipment['secondary'] ?? null),
        );
        $equipment['handgun_weapon'] = $this->firstString(
            $equipment['handgun_weapon'] ?? null,
            is_string($equipment['handgun'] ?? null) ? $equipment['handgun'] : null,
            $this->weaponClass($equipment['handgun'] ?? null),
        );
        if (!is_array($equipment['handgun'] ?? null)) {
            $equipment['handgun'] = $this->firstString(
                $equipment['handgun'] ?? null,
                $equipment['handgun_weapon'] ?? null,
            );
        }

        $loadout = $body['loadout'] ?? $equipment['loadout'] ?? [];
        if (!is_array($loadout)) {
            $loadout = [];
        }
        $equipment['loadout'] = is_array($equipment['loadout'] ?? null) ? $equipment['loadout'] : $loadout;

        $steam = $this->firstString(
            $body['steam_id'] ?? null,
            $body['steam_uid'] ?? null,
            $body['steam_uid_session'] ?? null,
            $identity['steam_uid'] ?? null,
            $playerUid,
            $body['player_uid'] ?? null,
        );

        $missionFolder = $this->firstString($environment['mission_name'] ?? null, $body['mission_id'] ?? null);
        $missionTitle = $this->firstString(
            $body['mission_name'] ?? null,
            $environment['briefing_name'] ?? null,
            $environment['mission_name'] ?? null,
        );

        $out = $body;
        $out['identity'] = $identity;
        $out['face'] = $face;
        $out['medical'] = $medical;
        $out['equipment'] = $equipment;
        $out['versions'] = $versions;
        $out['environment'] = $environment;
        $out['loadout'] = $loadout;
        $out['steam_id'] = $steam;
        $out['steam_uid'] = $this->firstString($body['steam_uid'] ?? null, $steam);
        $out['player_uid'] = $this->firstString($body['player_uid'] ?? null, $playerUid);
        $out['server_name'] = $this->firstString($body['server_name'] ?? null, $environment['server_name'] ?? null);
        $out['mission_name'] = $missionTitle;
        $out['mission_id'] = $this->firstString($body['mission_id'] ?? null, $environment['mission_id'] ?? null, $missionFolder);
        $out['world_name'] = $this->firstString($body['world_name'] ?? null, $environment['world_name'] ?? null);

        return $out;
    }

    /**
     * Observed fields compared to the HR reference (never mutates personnel).
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function observedForReconcile(array $payload, string $steamId): array
    {
        $identity = $this->assoc($payload['identity'] ?? null);
        $medical = $this->assoc($payload['medical'] ?? null);
        $versions = $this->assoc($payload['versions'] ?? null);

        return array_merge($identity, [
            'steam_id' => $steamId,
            'blood_type' => $this->firstString($medical['blood_type'] ?? null, $identity['blood_type'] ?? null),
            'sex' => $this->firstString($identity['sex'] ?? null, $identity['sex_detected'] ?? null),
            'versions' => $versions,
        ]);
    }

    /** @return array<string, mixed> */
    private function assoc(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function firstString(mixed ...$candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (!is_scalar($candidate)) {
                continue;
            }
            $text = trim((string) $candidate);
            if ($text !== '') {
                return $text;
            }
        }

        return null;
    }

    private function weaponClass(mixed $slot): ?string
    {
        if (is_string($slot) || is_int($slot)) {
            return $this->firstString($slot);
        }
        if (!is_array($slot)) {
            return null;
        }

        return $this->firstString($slot['class'] ?? null, $slot[0] ?? null);
    }
}
