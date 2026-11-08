<?php

declare(strict_types=1);

namespace App\Services\GameEquipment;

use App\Support\ArsenalLoadoutItems;
use App\Support\LazyDatabaseConnection;
use PDO;

/**
 * Ce que les joueurs de la communauté portent réellement avec chaque arme : chargeurs, optiques et accessoires relevés
 * dans les tenues envoyées depuis l'arsenal et dans les derniers kits portés en jeu (operator_game_profiles).
 *
 * Sert à l'assistant de création : le catalogue d'objets dit ce qui existe, ces relevés disent quel chargeur va avec
 * quelle arme. Aucune liste écrite à la main. Ne lève jamais : sans données, le résultat est vide.
 */
final class ObservedLoadouts
{
    use LazyDatabaseConnection;

    private const MAX_PAYLOADS = 300;

    /** Emplacements d'une arme dans un loadout : 1 bouche, 2 pointeur, 3 optique, 4 chargeur, 6 bipied. */
    private const PARTS = ['muzzle' => 1, 'pointer' => 2, 'optic' => 3, 'bipod' => 6];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array<string, array{class: string, seen: int, magazine: array<string, int>, muzzle: array<string, int>, pointer: array<string, int>, optic: array<string, int>, bipod: array<string, int>}>
     *         clé = classe de l'arme en minuscules
     */
    public function weaponSetups(int $tenantId): array
    {
        if ($tenantId < 1) {
            return [];
        }

        return self::tally($this->payloads($tenantId));
    }

    /**
     * Dépouille des tenues : pour chaque arme, combien de fois chaque chargeur, optique, bouche, pointeur et bipied
     * l'accompagnent. Pur : testable sans base.
     *
     * @param iterable<string> $payloads
     * @return array<string, array{class: string, seen: int, magazine: array<string, int>, muzzle: array<string, int>, pointer: array<string, int>, optic: array<string, int>, bipod: array<string, int>}>
     */
    public static function tally(iterable $payloads): array
    {
        $out = [];
        foreach ($payloads as $payload) {
            $loadout = ArsenalLoadoutItems::aceLoadout((string) $payload);
            if ($loadout === null) {
                continue;
            }
            foreach ([0, 1, 2] as $slot) {
                $weapon = $loadout[0][$slot] ?? [];
                $class = is_array($weapon) && is_string($weapon[0] ?? null) ? trim($weapon[0]) : '';
                if ($class === '' || GameItemUsageExtractor::cleanClass($class) === '') {
                    continue;
                }
                $key = strtolower($class);
                $out[$key] ??= ['class' => $class, 'seen' => 0, 'magazine' => [], 'muzzle' => [], 'pointer' => [], 'optic' => [], 'bipod' => []];
                $out[$key]['seen']++;
                $mag = is_array($weapon[4] ?? null) ? ($weapon[4][0] ?? '') : '';
                self::count($out[$key]['magazine'], $mag);
                foreach (self::PARTS as $part => $index) {
                    self::count($out[$key][$part], $weapon[$index] ?? '');
                }
            }
        }

        return $out;
    }

    /** Valeur la plus fréquente d'un décompte (égalité : ordre alphabétique), ou '' s'il est vide. */
    public static function top(array $counts): string
    {
        if ($counts === []) {
            return '';
        }
        $keys = array_keys($counts);
        usort($keys, static fn (string $a, string $b): int => ($counts[$b] <=> $counts[$a]) ?: strcasecmp($a, $b));

        return (string) $keys[0];
    }

    /**
     * @param array<string, int> $bucket
     */
    private static function count(array &$bucket, mixed $value): void
    {
        $class = GameItemUsageExtractor::cleanClass(is_string($value) ? $value : '');
        if ($class !== '') {
            $bucket[$class] = ($bucket[$class] ?? 0) + 1;
        }
    }

    /**
     * @return list<string>
     */
    private function payloads(int $tenantId): array
    {
        $out = [];
        try {
            $st = $this->pdo()->prepare(
                'SELECT payload_text FROM arsenal_wardrobes WHERE tenant_id = ? ORDER BY updated_at DESC LIMIT ' . self::MAX_PAYLOADS
            );
            $st->execute([$tenantId]);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) ?: [] as $text) {
                $out[] = (string) $text;
            }
        } catch (\Throwable) {
            // Tenues d'arsenal absentes : on continue avec les kits portés.
        }
        try {
            $st = $this->pdo()->prepare(
                'SELECT loadout_json FROM operator_game_profiles
                 WHERE tenant_id = ? AND loadout_json IS NOT NULL AND loadout_json NOT IN (\'\', \'[]\', \'{}\')
                 ORDER BY last_seen_at DESC LIMIT ' . self::MAX_PAYLOADS
            );
            $st->execute([$tenantId]);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) ?: [] as $json) {
                $payload = PlayerKitService::payloadFrom((string) $json);
                if ($payload !== '') {
                    $out[] = $payload;
                }
            }
        } catch (\Throwable) {
            // Profils opérateur absents : rien de plus à relever.
        }

        return $out;
    }
}
