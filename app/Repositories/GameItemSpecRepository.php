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
 *
 * Catalogue de base (étape equipment_vanilla_catalog, colonne source = 'vanilla') : livré avec le site, chargé par
 * lots au premier accès (VanillaCatalogLoader). Il ne remplace jamais une fiche importée ; un import le remplace.
 */
final class GameItemSpecRepository
{
    use LazyDatabaseConnection;

    private static ?bool $ready = null;

    private static ?bool $vanillaReady = null;

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
            $ok = $this->pdo()->query('SELECT class_name FROM game_item_specs WHERE 1 = 0') !== false;
        } catch (\Throwable) {
            $ok = false;
        }
        if ($ok) {
            self::$ready = true;
        }

        return $ok;
    }

    /** Colonnes du catalogue de base présentes (étape equipment_vanilla_catalog). */
    public function vanillaReady(): bool
    {
        if (self::$vanillaReady === true) {
            return true;
        }
        if (!$this->tableReady()) {
            return false;
        }
        // Requêtes vides : marchent sur MariaDB comme sur sqlite (tests) ; une colonne ou une table absente échoue.
        try {
            $ok = $this->pdo()->query('SELECT source, name_en FROM game_item_specs WHERE 1 = 0') !== false
                && $this->pdo()->query('SELECT tenant_id FROM game_item_vanilla_loads WHERE 1 = 0') !== false;
        } catch (\Throwable) {
            $ok = false;
        }
        if ($ok) {
            self::$vanillaReady = true;
        }

        return $ok;
    }

    /**
     * Lignes du catalogue de base (source « vanilla ») : ajoutées si la classe manque, mises à jour seulement si la
     * ligne existante est elle-même « vanilla ». Une fiche importée par un responsable n'est jamais touchée.
     *
     * Trois requêtes simples par lot, dans une transaction (même SQL sur MariaDB et sqlite) : classes protégées
     * (fiche non « vanilla »), retrait des anciennes fiches de base du lot, insertion des fiches non protégées.
     *
     * @param list<array<string, mixed>> $rows lignes de VanillaCatalog::load
     * @return int fiches de base écrites
     */
    public function importVanilla(int $tenantId, array $rows): int
    {
        if ($tenantId < 1 || $rows === [] || !$this->vanillaReady()) {
            return 0;
        }
        $pdo = $this->pdo();
        $now = date('Y-m-d H:i:s');
        $written = 0;
        $pdo->beginTransaction();
        try {
            foreach (array_chunk($rows, 300) as $chunk) {
                $written += $this->importVanillaChunk($pdo, $tenantId, $chunk, $now);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $written;
    }

    /**
     * @param list<array<string, mixed>> $chunk
     */
    private function importVanillaChunk(PDO $pdo, int $tenantId, array $chunk, string $now): int
    {
        $classes = array_map(static fn (array $r): string => (string) $r['class_name'], $chunk);
        $in = implode(', ', array_fill(0, count($classes), '?'));
        $st = $pdo->prepare("SELECT class_name FROM game_item_specs WHERE tenant_id = ? AND source <> 'vanilla' AND class_name IN (" . $in . ')');
        $st->execute(array_merge([$tenantId], $classes));
        $protected = [];
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) ?: [] as $c) {
            $protected[strtolower((string) $c)] = true;
        }
        $pdo->prepare("DELETE FROM game_item_specs WHERE tenant_id = ? AND source = 'vanilla' AND class_name IN (" . $in . ')')
            ->execute(array_merge([$tenantId], $classes));
        $values = [];
        $params = [];
        foreach ($chunk as $r) {
            if (isset($protected[strtolower((string) $r['class_name'])])) {
                continue;
            }
            $values[] = "(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'vanilla', NULL, ?)";
            array_push(
                $params,
                $tenantId,
                (string) $r['class_name'],
                (string) $r['root'],
                (string) $r['type_main'],
                (string) $r['type_sub'],
                (string) $r['name'],
                (string) ($r['name_en'] ?? ''),
                $r['mass'],
                $r['max_load'],
                $r['slot_type'],
                $r['ammo_count'],
                implode(',', array_map('intval', (array) $r['allowed_slots'])),
                $r['compat'] !== null ? json_encode($r['compat'], JSON_UNESCAPED_SLASHES) : null,
                $r['mags'] !== null ? json_encode($r['mags'], JSON_UNESCAPED_SLASHES) : null,
                (string) $r['mod_name'],
                $now,
            );
        }
        if ($values === []) {
            return 0;
        }
        $pdo->prepare(
            'INSERT INTO game_item_specs
                (tenant_id, class_name, root, type_main, type_sub, name, name_en, mass, max_load, slot_type, ammo_count,
                 allowed_slots, compat_json, mags_json, mod_name, source, imported_by, imported_at)
             VALUES ' . implode(', ', $values)
        )->execute($params);

        return count($values);
    }

    /**
     * Retire les fiches de base dont la classe n'est plus dans le fichier livré.
     *
     * @param list<string> $keep
     */
    public function pruneVanilla(int $tenantId, array $keep): int
    {
        if ($tenantId < 1 || $keep === [] || !$this->vanillaReady()) {
            return 0;
        }
        $st = $this->pdo()->prepare(
            "DELETE FROM game_item_specs WHERE tenant_id = ? AND source = 'vanilla' AND class_name NOT IN (" . implode(', ', array_fill(0, count($keep), '?')) . ')'
        );
        $st->execute(array_merge([$tenantId], array_values($keep)));

        return $st->rowCount();
    }

    /** Oublie l'état de chargement du catalogue de base (il sera rechargé au prochain accès). */
    public function resetVanillaState(int $tenantId): void
    {
        if ($tenantId < 1 || !$this->vanillaReady()) {
            return;
        }
        $this->pdo()->prepare('DELETE FROM game_item_vanilla_loads WHERE tenant_id = ?')->execute([$tenantId]);
    }

    /**
     * @return array{version: string, total: int, done: int, finished: bool}|null
     */
    public function vanillaState(int $tenantId): ?array
    {
        if ($tenantId < 1 || !$this->vanillaReady()) {
            return null;
        }
        $st = $this->pdo()->prepare('SELECT version, total, done, finished_at FROM game_item_vanilla_loads WHERE tenant_id = ?');
        $st->execute([$tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return ['version' => (string) $row['version'], 'total' => (int) $row['total'], 'done' => (int) $row['done'], 'finished' => $row['finished_at'] !== null];
    }

    public function saveVanillaState(int $tenantId, string $version, int $total, int $done, bool $finished): void
    {
        if ($tenantId < 1 || !$this->vanillaReady()) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $pdo = $this->pdo();
        $st = $pdo->prepare('SELECT 1 FROM game_item_vanilla_loads WHERE tenant_id = ?');
        $st->execute([$tenantId]);
        if ($st->fetchColumn()) {
            $pdo->prepare('UPDATE game_item_vanilla_loads SET version = ?, total = ?, done = ?, finished_at = ?' . ($done === 0 ? ', started_at = ?' : '') . ' WHERE tenant_id = ?')
                ->execute(array_merge([$version, $total, $done, $finished ? $now : null], $done === 0 ? [$now] : [], [$tenantId]));

            return;
        }
        $pdo->prepare('INSERT INTO game_item_vanilla_loads (tenant_id, version, total, done, started_at, finished_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$tenantId, $version, $total, $done, $now, $finished ? $now : null]);
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
        $vanilla = $this->vanillaReady();
        $pdo->beginTransaction();
        try {
            if ($replace) {
                // Le catalogue de base reste (il comble ce que l'export n'a pas) ; il est rechargé au prochain accès
                // pour remettre les fiches de base qu'un import précédent avait remplacées.
                $pdo->prepare('DELETE FROM game_item_specs WHERE tenant_id = ?' . ($vanilla ? " AND source <> 'vanilla'" : ''))->execute([$tenantId]);
                if ($vanilla) {
                    $pdo->prepare('DELETE FROM game_item_vanilla_loads WHERE tenant_id = ?')->execute([$tenantId]);
                }
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
                        // Une fiche de base remplacée par l'export du jeu devient « import » : le catalogue de base n'y touche plus.
                        . ($vanilla ? ", source = 'import'" : '')
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
     * count = toutes les fiches ; imported = fiches venues d'un export fait en jeu (hors catalogue de base).
     *
     * @return array{count: int, imported: int, vanilla: int, last: string|null}
     */
    public function summary(int $tenantId): array
    {
        if ($tenantId < 1 || !$this->tableReady()) {
            return ['count' => 0, 'imported' => 0, 'vanilla' => 0, 'last' => null];
        }
        $vanilla = $this->vanillaReady();
        $st = $this->pdo()->prepare(
            'SELECT COUNT(*) AS n, ' . ($vanilla ? "SUM(CASE WHEN source = 'vanilla' THEN 1 ELSE 0 END)" : '0') . ' AS v,
                MAX(' . ($vanilla ? "CASE WHEN source = 'vanilla' THEN NULL ELSE imported_at END" : 'imported_at') . ') AS last
             FROM game_item_specs WHERE tenant_id = ?'
        );
        $st->execute([$tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $n = (int) ($row['n'] ?? 0);
        $v = (int) ($row['v'] ?? 0);

        return ['count' => $n, 'imported' => $n - $v, 'vanilla' => $v, 'last' => isset($row['last']) ? (string) $row['last'] : null];
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
        $cols = 'class_name, root, type_main, type_sub, name, mass, max_load, slot_type, ammo_count, allowed_slots, compat_json, mags_json, mod_name'
            . ($this->vanillaReady() ? ', name_en, source' : '');
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
