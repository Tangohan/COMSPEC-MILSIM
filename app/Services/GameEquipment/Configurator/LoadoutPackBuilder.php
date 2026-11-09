<?php

declare(strict_types=1);

namespace App\Services\GameEquipment\Configurator;

use App\Services\GameEquipment\KitsAccess;
use App\Services\GameEquipment\RoleKitService;

/**
 * Bloc « loadouts » du paquet d'identité Athena (settings.loadouts, lu par l'addon loadout du mod SSE) : kits de la
 * communauté et kits proposés à chaque joueur (Steam ID), pour l'action « Prendre mon kit » d'une caisse en jeu.
 *
 *   {kits: [{id, name, loadout: "<getUnitLoadout en JSON>"}], players: [{uid, kits: [id…], default: id|0}]}
 *
 * Le paquet passe par COMSPEC Link (GetIdentityPack), jamais par une requête du mod : la règle de licence est
 * respectée. Rien n'est ajouté si la formule ne comprend pas les kits ou si aucun kit n'existe.
 */
final class LoadoutPackBuilder
{
    public const MAX_KITS = 200;

    public const MAX_PLAYERS = 500;

    public function __construct(private ?RoleKitService $kits = null)
    {
    }

    /**
     * @return array{kits: list<array{id: int, name: string, loadout: string}>, players: list<array{uid: string, kits: list<int>, default: int}>}|null
     */
    public function build(int $tenantId): ?array
    {
        try {
            if (!KitsAccess::allows($tenantId)) {
                return null;
            }
            $service = $this->kits ?? \App\Core\Container::get(RoleKitService::class);
            $all = $service->listForTenant($tenantId);
            if ($all === []) {
                return null;
            }
            $offers = [];
            foreach ((new \App\Repositories\UserRepository())->allForTenant($tenantId) as $u) {
                $uid = (string) preg_replace('/\D/', '', (string) ($u['steam_id'] ?? ''));
                if ($uid === '' || (string) ($u['status'] ?? 'active') !== 'active') {
                    continue;
                }
                $offers[$uid] = $service->listForMember($tenantId, (int) $u['id']);
                if (count($offers) >= self::MAX_PLAYERS) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            error_log('[loadout-pack] ' . $e->getMessage());

            return null;
        }

        return self::assemble($all, $offers);
    }

    /**
     * @param list<array<string, mixed>> $kits kits de la communauté (payload_text, name, id)
     * @param array<string, list<array<string, mixed>>> $offers Steam ID => kits proposés (ordre de listForMember)
     * @return array{kits: list<array{id: int, name: string, loadout: string}>, players: list<array{uid: string, kits: list<int>, default: int}>}|null
     */
    public static function assemble(array $kits, array $offers): ?array
    {
        $outKits = [];
        foreach ($kits as $k) {
            $doc = LoadoutDocument::fromText((string) ($k['payload_text'] ?? ''));
            if (!$doc['ok']) {
                continue;
            }
            $outKits[(int) $k['id']] = ['id' => (int) $k['id'], 'name' => mb_substr((string) $k['name'], 0, 90), 'loadout' => LoadoutDocument::toJson($doc['loadout'])];
            if (count($outKits) >= self::MAX_KITS) {
                break;
            }
        }
        if ($outKits === []) {
            return null;
        }
        $players = [];
        foreach ($offers as $uid => $rows) {
            $ids = [];
            foreach ($rows as $r) {
                if (isset($outKits[(int) ($r['id'] ?? 0)])) {
                    $ids[] = (int) $r['id'];
                }
            }
            if ($ids === []) {
                continue;
            }
            $default = RoleKitService::defaultKitId(array_values(array_filter($rows, static fn (array $r): bool => isset($outKits[(int) ($r['id'] ?? 0)]))));
            $players[] = ['uid' => (string) $uid, 'kits' => array_values(array_unique($ids)), 'default' => $default];
        }

        return ['kits' => array_values($outKits), 'players' => $players];
    }
}
