<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use PDO;

/**
 * Configuration des objets Arma importée depuis le jeu (table game_item_specs, étape equipment_configurator) : masse,
 * charge maximale, emplacements autorisés, accessoires et chargeurs compatibles. Une ligne par communauté et par
 * classe ; un nouvel import met à jour les classes envoyées et garde les autres (plusieurs jeux de mods se cumulent),
 * sauf en mode remplacement.
 */
final class GameItemSpecRepository
{
    use LazyDatabaseConnection;

    private static ?bool $ready = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public function tableReady(): bool
    {
        if (self::$ready === true) {
            return true;
        }
        try {
            $st = $this->pdo()->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'game_item_specs' LIMIT 1"
            );
            $ok = (bool) ($st && $st->fetchColumn());
        } catch (\Throwable) {
            $ok = false;
        }
        if ($ok) {
            self::$ready = true;
        }

        return $ok;
    }

    /**
     * @param list<array<string, mixed>> $rows lignes de ItemCatalogImport::parse
     * @return int lignes écrites
     */
    public function import(int $tenantId, int $userId, array $rows, bool $replace): int
    {
        if ($tenantId < 1 || !$this->tableReady()) {
            return 0;
        }
        $pdo = $this->pdo();
        $now = date('Y-m-d H:i:s');
        $pdo->beginTransaction();
        try {
            if ($replace) {
                $pdo->prepare('DELETE FROM game_item_specs WHERE tenant_id = ?')->execute([$tenantId]);
            }
            $written = 0;
            foreach (array_chunk($rows, 400) as $chunk) {
                $values = [];
                $params = [];
                foreach ($chunk as $r) {
                    $values[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
                    array_push(
                        $params,
                        $tenantId,
                        (string) $r['class_name'],
                        (string) $r['root'],
                        (string) $r['type_main'],
                        (string) $r['type_sub'],
                        (string) $r['name'],
                        $r['mass'],
                        $r['max_load'],
                        $r['slot_type'],
                        $r['ammo_count'],
                        implode(',', array_map('intval', (array) $r['allowed_slots'])),
                        $r['compat'] !== null ? json_encode($r['compat'], JSON_UNESCAPED_SLASHES) : null,
                        $r['mags'] !== null ? json_encode($r['mags'], JSON_UNESCAPED_SLASHES) : null,
                        (string) $r['mod_name'],
                        $userId > 0 ? $userId : null,
                        $now,
                    );
                }
                $pdo->prepare(
                    'INSERT INTO game_item_specs
                        (tenant_id, class_name, root, type_main, type_sub, name, mass, max_load, slot_type, ammo_count,
                         allowed_slots, compat_json, mags_json, mod_name, imported_by, imported_at)
                     VALUES ' . implode(', ', $values) . '
                     ON DUPLICATE KEY UPDATE root = VALUES(root), type_main = VALUES(type_main), type_sub = VALUES(type_sub),
                        name = IF(VALUES(name) <> \'\', VALUES(name), name), mass = VALUES(mass), max_load = VALUES(max_load),
                        slot_type = VALUES(slot_type), ammo_count = VALUES(ammo_count), allowed_slots = VALUES(allowed_slots),
                        compat_json = VALUES(compat_json), mags_json = VALUES(mags_json),
                        mod_name = IF(VALUES(mod_name) <> \'\', VALUES(mod_name), mod_name),
                        imported_by = VALUES(imported_by), imported_at = VALUES(imported_at)'
                )->execute($params);
                $written += count($chunk);
            }
            $pdo->commit();

            return $written;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @return array{count: int, last: string|null}
     */
    public function summary(int $tenantId): array
    {
        if ($tenantId < 1 || !$this->tableReady()) {
            return ['count' => 0, 'last' => null];
        }
        $st = $this->pdo()->prepare('SELECT COUNT(*) AS n, MAX(imported_at) AS last FROM game_item_specs WHERE tenant_id = ?');
        $st->execute([$tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        return ['count' => (int) ($row['n'] ?? 0), 'last' => isset($row['last']) ? (string) $row['last'] : null];
    }

    /**
     * Toutes les lignes de la communauté, ou celles des classes demandées.
     *
     * @param list<string>|null $classes
     * @return list<array<string, mixed>>
     */
    public function rows(int $tenantId, ?array $classes = null): array
    {
        if ($tenantId < 1 || !$this->tableReady()) {
            return [];
        }
        $cols = 'class_name, root, type_main, type_sub, name, mass, max_load, slot_type, ammo_count, allowed_slots, compat_json, mags_json, mod_name';
        if ($classes === null) {
            $st = $this->pdo()->prepare('SELECT ' . $cols . ' FROM game_item_specs WHERE tenant_id = ? LIMIT 30000');
            $st->execute([$tenantId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        $classes = array_values(array_unique(array_filter($classes, static fn ($c): bool => is_string($c) && $c !== '')));
        $out = [];
        foreach (array_chunk($classes, 500) as $chunk) {
            $st = $this->pdo()->prepare(
                'SELECT ' . $cols . ' FROM game_item_specs WHERE tenant_id = ? AND class_name IN (' . implode(', ', array_fill(0, count($chunk), '?')) . ')'
            );
            $st->execute(array_merge([$tenantId], $chunk));
            $out = array_merge($out, $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
        }

        return $out;
    }
}
