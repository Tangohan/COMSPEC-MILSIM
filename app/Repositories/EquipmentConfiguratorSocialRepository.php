<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Services\GameEquipment\Configurator\EquipmentTrends;
use App\Support\LazyDatabaseConnection;
use PDO;

/**
 * Configurateur d'équipement, partie « membres » (étape equipment_configurator_social) :
 * - objets favoris de chaque membre (game_item_favorites), même principe que les tenues favorites ;
 * - pré-kits réutilisables (game_item_prekits), personnels ou partagés à la communauté ;
 * - lectures des tendances (objets portés en jeu, ajoutés aux tenues, mis en favori ; tenues mises en favori).
 *
 * Toute requête est limitée à la communauté (tenant_id = ?), jamais lue dans la requête HTTP. Les tendances ne
 * rendent que des nombres de membres distincts par classe ou par tenue visible de la communauté (EquipmentTrends).
 */
final class EquipmentConfiguratorSocialRepository
{
    use LazyDatabaseConnection;

    public const MAX_FAVORITES = 500;

    public const MAX_PREKITS_PER_MEMBER = 60;

    /** @var array<string, bool> */
    private array $tables = [];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public function favoritesReady(): bool
    {
        return $this->ready('game_item_favorites');
    }

    public function prekitsReady(): bool
    {
        return $this->ready('game_item_prekits');
    }

    // ------------------------------------------------------------------ favoris d'objets

    /** @return list<string> classes mises en favori par ce membre */
    public function favoriteClasses(int $tenantId, int $userId): array
    {
        if ($tenantId < 1 || $userId < 1 || !$this->favoritesReady()) {
            return [];
        }
        $st = $this->pdo()->prepare('SELECT class_name FROM game_item_favorites WHERE tenant_id = ? AND user_id = ? ORDER BY created_at DESC LIMIT ' . self::MAX_FAVORITES);
        $st->execute([$tenantId, $userId]);

        return array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /**
     * @return bool|null true/false = état enregistré, null = refusé (table absente, limite atteinte)
     */
    public function setFavorite(int $tenantId, int $userId, string $class, bool $on): ?bool
    {
        if ($tenantId < 1 || $userId < 1 || $class === '' || !$this->favoritesReady()) {
            return null;
        }
        $pdo = $this->pdo();
        if (!$on) {
            $pdo->prepare('DELETE FROM game_item_favorites WHERE tenant_id = ? AND user_id = ? AND class_name = ?')->execute([$tenantId, $userId, $class]);

            return false;
        }
        $st = $pdo->prepare('SELECT COUNT(*) FROM game_item_favorites WHERE tenant_id = ? AND user_id = ?');
        $st->execute([$tenantId, $userId]);
        if ((int) $st->fetchColumn() >= self::MAX_FAVORITES) {
            return null;
        }
        $exists = $pdo->prepare('SELECT 1 FROM game_item_favorites WHERE tenant_id = ? AND user_id = ? AND class_name = ?');
        $exists->execute([$tenantId, $userId, $class]);
        if (!$exists->fetchColumn()) {
            $pdo->prepare('INSERT INTO game_item_favorites (tenant_id, user_id, class_name, created_at) VALUES (?, ?, ?, ?)')
                ->execute([$tenantId, $userId, $class, date('Y-m-d H:i:s')]);
        }

        return true;
    }

    // ------------------------------------------------------------------ pré-kits

    /**
     * Pré-kits visibles par ce membre : les siens et ceux partagés à la communauté.
     *
     * @return list<array{id: int, user_id: int, name: string, description: string, items_json: string, shared: bool, updated_at: string}>
     */
    public function prekits(int $tenantId, int $userId): array
    {
        if ($tenantId < 1 || !$this->prekitsReady()) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT id, user_id, name, description, items_json, shared, updated_at FROM game_item_prekits
             WHERE tenant_id = ? AND (user_id = ? OR shared = 1) ORDER BY shared ASC, name ASC LIMIT 300'
        );
        $st->execute([$tenantId, $userId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $out[] = [
                'id' => (int) $r['id'],
                'user_id' => (int) $r['user_id'],
                'name' => (string) $r['name'],
                'description' => (string) $r['description'],
                'items_json' => (string) $r['items_json'],
                'shared' => (int) $r['shared'] === 1,
                'updated_at' => (string) $r['updated_at'],
            ];
        }

        return $out;
    }

    /** @return array{id: int, user_id: int, shared: bool}|null */
    public function findPrekit(int $tenantId, int $id): ?array
    {
        if ($tenantId < 1 || $id < 1 || !$this->prekitsReady()) {
            return null;
        }
        $st = $this->pdo()->prepare('SELECT id, user_id, shared FROM game_item_prekits WHERE tenant_id = ? AND id = ?');
        $st->execute([$tenantId, $id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($r) ? ['id' => (int) $r['id'], 'user_id' => (int) $r['user_id'], 'shared' => (int) $r['shared'] === 1] : null;
    }

    public function countOwnPrekits(int $tenantId, int $userId): int
    {
        if (!$this->prekitsReady()) {
            return 0;
        }
        $st = $this->pdo()->prepare('SELECT COUNT(*) FROM game_item_prekits WHERE tenant_id = ? AND user_id = ?');
        $st->execute([$tenantId, $userId]);

        return (int) $st->fetchColumn();
    }

    /**
     * Crée ($id null) ou met à jour un pré-kit. Les droits sont vérifiés par le contrôleur.
     *
     * @param list<array{0: string, 1: int}> $items
     */
    public function savePrekit(int $tenantId, int $userId, ?int $id, string $name, string $description, array $items, bool $shared): int
    {
        $now = date('Y-m-d H:i:s');
        $json = (string) json_encode($items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $pdo = $this->pdo();
        if ($id !== null) {
            $pdo->prepare('UPDATE game_item_prekits SET name = ?, description = ?, items_json = ?, shared = ?, updated_at = ? WHERE tenant_id = ? AND id = ?')
                ->execute([$name, $description, $json, $shared ? 1 : 0, $now, $tenantId, $id]);

            return $id;
        }
        $pdo->prepare('INSERT INTO game_item_prekits (tenant_id, user_id, name, description, items_json, shared, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$tenantId, $userId, $name, $description, $json, $shared ? 1 : 0, $now, $now]);

        return (int) $pdo->lastInsertId();
    }

    public function deletePrekit(int $tenantId, int $id): bool
    {
        if (!$this->prekitsReady()) {
            return false;
        }
        $st = $this->pdo()->prepare('DELETE FROM game_item_prekits WHERE tenant_id = ? AND id = ?');
        $st->execute([$tenantId, $id]);

        return $st->rowCount() > 0;
    }

    // ------------------------------------------------------------------ tendances

    /**
     * Tendances de la communauté sur la période (EquipmentTrends::DAYS jours par défaut).
     *
     * @return array{days: int, worn: list<array{class: string, members: int}>, added: list<array{class: string, members: int}>, favored: list<array{class: string, members: int}>, wardrobes: list<array{ref: string, name: string, members: int}>}
     */
    public function trends(int $tenantId, int $days = EquipmentTrends::DAYS): array
    {
        $days = max(1, min(365, $days));
        $out = ['days' => $days, 'worn' => [], 'added' => [], 'favored' => [], 'wardrobes' => []];
        if ($tenantId < 1) {
            return $out;
        }
        $since = date('Y-m-d H:i:s', time() - $days * 86400);
        $min = EquipmentTrends::MIN_MEMBERS;
        // Portés en jeu (remontés par COMSPEC Link) : joueurs distincts par objet.
        if ($this->ready('game_item_usage')) {
            $out['worn'] = EquipmentTrends::fromAggregates($this->fetch(
                'SELECT tenant_id, class_name, COUNT(DISTINCT steam_id) AS members FROM game_item_usage
                 WHERE tenant_id = ? AND usage_day >= ? GROUP BY tenant_id, class_name HAVING COUNT(DISTINCT steam_id) >= ?
                 ORDER BY members DESC LIMIT 80',
                [$tenantId, GameEquipmentRepository::sinceDay($days), $min]
            ), $tenantId);
        }
        // Ajoutés aux tenues : tenues enregistrées ou modifiées sur la période (configurateur, arsenal en jeu).
        if ($this->ready('arsenal_wardrobes')) {
            $out['added'] = EquipmentTrends::fromLoadouts($this->fetch(
                'SELECT tenant_id, user_id, payload_text FROM arsenal_wardrobes
                 WHERE tenant_id = ? AND updated_at >= ? ORDER BY updated_at DESC LIMIT 600',
                [$tenantId, $since]
            ), $tenantId);
        }
        if ($this->favoritesReady()) {
            $out['favored'] = EquipmentTrends::fromAggregates($this->fetch(
                'SELECT tenant_id, class_name, COUNT(DISTINCT user_id) AS members FROM game_item_favorites
                 WHERE tenant_id = ? AND created_at >= ? GROUP BY tenant_id, class_name HAVING COUNT(DISTINCT user_id) >= ?
                 ORDER BY members DESC LIMIT 80',
                [$tenantId, $since, $min]
            ), $tenantId);
        }
        if ($this->ready('arsenal_wardrobe_favorites') && $this->ready('arsenal_wardrobes')) {
            $vis = $this->column('arsenal_wardrobes', 'visibility') ? 'w.visibility' : "'tenant'";
            $out['wardrobes'] = EquipmentTrends::wardrobes($this->fetch(
                'SELECT w.tenant_id, w.id, w.name, ' . $vis . ' AS visibility, COUNT(DISTINCT f.user_id) AS members
                 FROM arsenal_wardrobe_favorites f
                 JOIN arsenal_wardrobes w ON w.id = f.wardrobe_id AND w.tenant_id = f.tenant_id
                 WHERE f.tenant_id = ? AND f.created_at >= ?' . ($vis === 'w.visibility' ? " AND w.visibility <> 'personal'" : '') . '
                 GROUP BY w.tenant_id, w.id, w.name' . ($vis === 'w.visibility' ? ', w.visibility' : '') . '
                 HAVING COUNT(DISTINCT f.user_id) >= ? ORDER BY members DESC LIMIT 40',
                [$tenantId, $since, $min]
            ), $tenantId);
        }

        return $out;
    }

    /**
     * @param list<mixed> $params
     * @return list<array<string, mixed>>
     */
    private function fetch(string $sql, array $params): array
    {
        try {
            $st = $this->pdo()->prepare($sql);
            $st->execute($params);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log('[equipment-trends] ' . $e->getMessage());

            return [];
        }
    }

    private function ready(string $table): bool
    {
        if (isset($this->tables[$table])) {
            return $this->tables[$table];
        }
        try {
            $ok = $this->pdo()->query('SELECT 1 FROM ' . $table . ' WHERE 1 = 0') !== false;
        } catch (\Throwable) {
            $ok = false;
        }

        return $this->tables[$table] = $ok;
    }

    private function column(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (isset($this->tables[$key])) {
            return $this->tables[$key];
        }
        try {
            $ok = $this->pdo()->query('SELECT ' . $column . ' FROM ' . $table . ' WHERE 1 = 0') !== false;
        } catch (\Throwable) {
            $ok = false;
        }

        return $this->tables[$key] = $ok;
    }
}
