<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Étiquettes PARTAGÉES du bureau SSE (table sse_tags, rangée par portée : « field_note » pour les fiches de renseignement,
 * « attach » pour les rattachements), liaisons étiquette ↔ objet (sse_tag_links) et réglages des fiches
 * (sse_field_note_settings). Toute requête porte la communauté et la portée. Sans les tables (étape de base de données en
 * attente), les lectures renvoient « vide » et les écritures ne font rien : ready() le dit aux appelants.
 * SQL portable (MySQL et sqlite des tests) : ni ON DUPLICATE KEY, ni fonction de date du moteur.
 */
final class SseTagRepository
{
    private Database $db;

    /** @var \WeakMap<PDO, array{ok: bool, at: int}>|null */
    private static ?\WeakMap $readyByConnection = null;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public static function ready(): bool
    {
        try {
            $pdo = Database::getPdo();
            self::$readyByConnection ??= new \WeakMap();
            $known = self::$readyByConnection[$pdo] ?? null;
            if (is_array($known) && ($known['ok'] || time() - $known['at'] < 20)) {
                return $known['ok'];
            }
            try {
                $pdo->query('SELECT 1 FROM sse_tags WHERE 1 = 0');
                $pdo->query('SELECT 1 FROM sse_tag_links WHERE 1 = 0');
                $pdo->query('SELECT 1 FROM sse_field_note_settings WHERE 1 = 0');
                self::$readyByConnection[$pdo] = ['ok' => true, 'at' => time()];

                return true;
            } catch (\Throwable) {
                self::$readyByConnection[$pdo] = ['ok' => false, 'at' => time()];

                return false;
            }
        } catch (\Throwable) {
            return false;
        }
    }

    public static function forgetReady(): void
    {
        self::$readyByConnection = null;
    }

    private static function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    // ——————————————————————————— Étiquettes ———————————————————————————

    /** @return list<array<string, mixed>> */
    public function rows(int $tenantId, string $scope): array
    {
        if ($tenantId < 1 || !self::ready()) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT * FROM sse_tags WHERE tenant_id = :t AND scope = :s ORDER BY sort_order ASC, id ASC',
            ['t' => $tenantId, 's' => $scope]
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, string $scope, string $slug): ?array
    {
        if ($tenantId < 1 || $slug === '' || !self::ready()) {
            return null;
        }

        return $this->db->fetchOne(
            'SELECT * FROM sse_tags WHERE tenant_id = :t AND scope = :s AND slug = :k LIMIT 1',
            ['t' => $tenantId, 's' => $scope, 'k' => $slug]
        );
    }

    /**
     * Crée ou met à jour la ligne d'une étiquette (clé : communauté + portée + slug).
     *
     * @param array<string, mixed> $data family, code, name, name_en, description, description_en, color, qualifier, sort_order, origin, status, merged_into
     */
    public function save(int $tenantId, string $scope, string $slug, array $data, ?int $userId): bool
    {
        if ($tenantId < 1 || $slug === '' || !self::ready()) {
            return false;
        }
        $cols = ['family', 'code', 'name', 'name_en', 'description', 'description_en', 'color', 'qualifier', 'sort_order', 'origin', 'status', 'merged_into'];
        $existing = $this->find($tenantId, $scope, $slug);
        if ($existing === null) {
            $row = [
                'tenant_id' => $tenantId, 'scope' => $scope, 'slug' => $slug,
                'family' => (string) ($data['family'] ?? ''), 'code' => (string) ($data['code'] ?? ''), 'name' => (string) ($data['name'] ?? ''),
                'name_en' => (string) ($data['name_en'] ?? ''), 'description' => (string) ($data['description'] ?? ''),
                'description_en' => (string) ($data['description_en'] ?? ''), 'color' => (string) ($data['color'] ?? 'slate'),
                'qualifier' => (string) ($data['qualifier'] ?? ''), 'sort_order' => (int) ($data['sort_order'] ?? 0),
                'origin' => (string) ($data['origin'] ?? 'custom'), 'status' => (string) ($data['status'] ?? 'active'),
                'merged_into' => $data['merged_into'] ?? null, 'created_by_user_id' => $userId, 'created_at' => self::now(),
            ];
            $this->db->insert(
                'INSERT INTO sse_tags (' . implode(', ', array_keys($row)) . ') VALUES (:' . implode(', :', array_keys($row)) . ')',
                $row
            );

            return true;
        }
        $sets = [];
        $params = ['t' => $tenantId, 's' => $scope, 'k' => $slug, 'u' => $userId, 'at' => self::now()];
        foreach ($cols as $col) {
            if (array_key_exists($col, $data)) {
                $sets[] = $col . ' = :' . $col;
                $params[$col] = $col === 'sort_order' ? (int) $data[$col] : $data[$col];
            }
        }
        if ($sets === []) {
            return false;
        }
        $this->db->execute(
            'UPDATE sse_tags SET ' . implode(', ', $sets) . ', updated_by_user_id = :u, updated_at = :at WHERE tenant_id = :t AND scope = :s AND slug = :k',
            $params
        );

        return true;
    }

    public function delete(int $tenantId, string $scope, string $slug): bool
    {
        if ($tenantId < 1 || $slug === '' || !self::ready()) {
            return false;
        }

        return $this->db->execute(
            'DELETE FROM sse_tags WHERE tenant_id = :t AND scope = :s AND slug = :k',
            ['t' => $tenantId, 's' => $scope, 'k' => $slug]
        ) > 0;
    }

    /** Retire les réglages posés sur le catalogue livré d'une famille (les étiquettes propres restent). */
    public function deleteCatalogOverrides(int $tenantId, string $scope, string $family): int
    {
        if ($tenantId < 1 || !self::ready()) {
            return 0;
        }

        return $this->db->execute(
            'DELETE FROM sse_tags WHERE tenant_id = :t AND scope = :s AND family = :f AND origin = :o',
            ['t' => $tenantId, 's' => $scope, 'f' => $family, 'o' => 'catalog']
        );
    }

    // ——————————————————————————— Liaisons ———————————————————————————

    /**
     * @param list<string> $objectIds
     * @return array<string, list<array{slug: string, qualifier: string}>> par identifiant d'objet
     */
    public function linksFor(int $tenantId, string $scope, string $objectType, array $objectIds): array
    {
        $ids = array_slice(array_values(array_unique(array_filter(array_map('strval', $objectIds), static fn (string $i): bool => $i !== ''))), 0, 500);
        if ($tenantId < 1 || $ids === [] || !self::ready()) {
            return [];
        }
        $params = ['t' => $tenantId, 's' => $scope, 'o' => $objectType];
        $marks = [];
        foreach ($ids as $k => $id) {
            $marks[] = ':i' . $k;
            $params['i' . $k] = $id;
        }
        $out = [];
        foreach ($this->db->fetchAll(
            'SELECT object_id, tag_slug, qualifier FROM sse_tag_links WHERE tenant_id = :t AND scope = :s AND object_type = :o AND object_id IN (' . implode(', ', $marks) . ') ORDER BY id ASC',
            $params
        ) as $row) {
            $out[(string) $row['object_id']][] = ['slug' => (string) $row['tag_slug'], 'qualifier' => (string) ($row['qualifier'] ?? '')];
        }

        return $out;
    }

    /**
     * Remplace les étiquettes d'un objet.
     *
     * @param list<array{slug: string, qualifier: string}> $links
     */
    public function replaceLinks(int $tenantId, string $scope, string $objectType, string $objectId, array $links, ?int $userId): bool
    {
        if ($tenantId < 1 || $objectId === '' || !self::ready()) {
            return false;
        }
        $this->db->execute(
            'DELETE FROM sse_tag_links WHERE tenant_id = :t AND scope = :s AND object_type = :o AND object_id = :i',
            ['t' => $tenantId, 's' => $scope, 'o' => $objectType, 'i' => $objectId]
        );
        $seen = [];
        foreach ($links as $link) {
            $slug = (string) $link['slug'];
            if ($slug === '' || isset($seen[$slug])) {
                continue;
            }
            $seen[$slug] = true;
            $this->db->insert(
                'INSERT INTO sse_tag_links (tenant_id, scope, object_type, object_id, tag_slug, qualifier, created_by_user_id, created_at)
                 VALUES (:t, :s, :o, :i, :k, :q, :u, :at)',
                ['t' => $tenantId, 's' => $scope, 'o' => $objectType, 'i' => $objectId, 'k' => $slug, 'q' => (string) $link['qualifier'], 'u' => $userId, 'at' => self::now()]
            );
        }

        return true;
    }

    /** @return array<string, int> nombre d'objets par clé d'étiquette */
    public function usage(int $tenantId, string $scope): array
    {
        if ($tenantId < 1 || !self::ready()) {
            return [];
        }
        $out = [];
        foreach ($this->db->fetchAll(
            'SELECT tag_slug, COUNT(*) AS n FROM sse_tag_links WHERE tenant_id = :t AND scope = :s GROUP BY tag_slug',
            ['t' => $tenantId, 's' => $scope]
        ) as $row) {
            $out[(string) $row['tag_slug']] = (int) $row['n'];
        }

        return $out;
    }

    /**
     * Identifiants des objets qui portent au moins une des étiquettes.
     *
     * @param list<string> $slugs
     * @return list<string>
     */
    public function objectIdsWith(int $tenantId, string $scope, string $objectType, array $slugs, int $limit = 2000): array
    {
        $slugs = array_slice(array_values(array_unique(array_filter($slugs, static fn (string $s): bool => $s !== ''))), 0, 200);
        if ($tenantId < 1 || $slugs === [] || !self::ready()) {
            return [];
        }
        $params = ['t' => $tenantId, 's' => $scope, 'o' => $objectType];
        $marks = [];
        foreach ($slugs as $k => $slug) {
            $marks[] = ':k' . $k;
            $params['k' . $k] = $slug;
        }
        $rows = $this->db->fetchAll(
            'SELECT DISTINCT object_id FROM sse_tag_links WHERE tenant_id = :t AND scope = :s AND object_type = :o AND tag_slug IN (' . implode(', ', $marks) . ') LIMIT ' . max(1, min(5000, $limit)),
            $params
        );

        return array_map(static fn (array $r): string => (string) $r['object_id'], $rows);
    }

    /** Fusion : les objets qui portaient $from portent $into (sans doublon). @return int liaisons déplacées */
    public function moveLinks(int $tenantId, string $scope, string $from, string $into): int
    {
        if ($tenantId < 1 || $from === '' || $into === '' || $from === $into || !self::ready()) {
            return 0;
        }
        $moved = 0;
        foreach ($this->db->fetchAll(
            'SELECT id, object_type, object_id FROM sse_tag_links WHERE tenant_id = :t AND scope = :s AND tag_slug = :k',
            ['t' => $tenantId, 's' => $scope, 'k' => $from]
        ) as $row) {
            $has = $this->db->fetchOne(
                'SELECT id FROM sse_tag_links WHERE tenant_id = :t AND scope = :s AND object_type = :o AND object_id = :i AND tag_slug = :k LIMIT 1',
                ['t' => $tenantId, 's' => $scope, 'o' => (string) $row['object_type'], 'i' => (string) $row['object_id'], 'k' => $into]
            );
            if ($has !== null) {
                $this->db->execute('DELETE FROM sse_tag_links WHERE id = :id AND tenant_id = :t', ['id' => (int) $row['id'], 't' => $tenantId]);
            } else {
                $this->db->execute('UPDATE sse_tag_links SET tag_slug = :k WHERE id = :id AND tenant_id = :t', ['k' => $into, 'id' => (int) $row['id'], 't' => $tenantId]);
            }
            $moved++;
        }

        return $moved;
    }

    // ——————————————————————————— Réglages des fiches ———————————————————————————

    /** @return array<string, array<string, mixed>> réglages décodés par clé */
    public function settings(int $tenantId): array
    {
        if ($tenantId < 1 || !self::ready()) {
            return [];
        }
        $out = [];
        foreach ($this->db->fetchAll('SELECT setting_key, value_json FROM sse_field_note_settings WHERE tenant_id = :t', ['t' => $tenantId]) as $row) {
            $decoded = json_decode((string) ($row['value_json'] ?? ''), true);
            $out[(string) $row['setting_key']] = is_array($decoded) ? $decoded : [];
        }

        return $out;
    }

    /** @param array<string, mixed> $value */
    public function saveSetting(int $tenantId, string $key, array $value, ?int $userId): bool
    {
        if ($tenantId < 1 || $key === '' || !self::ready()) {
            return false;
        }
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        $existing = $this->db->fetchOne(
            'SELECT id FROM sse_field_note_settings WHERE tenant_id = :t AND setting_key = :k LIMIT 1',
            ['t' => $tenantId, 'k' => $key]
        );
        if ($existing === null) {
            $this->db->insert(
                'INSERT INTO sse_field_note_settings (tenant_id, setting_key, value_json, updated_by_user_id, updated_at) VALUES (:t, :k, :v, :u, :at)',
                ['t' => $tenantId, 'k' => $key, 'v' => $json, 'u' => $userId, 'at' => self::now()]
            );
        } else {
            $this->db->execute(
                'UPDATE sse_field_note_settings SET value_json = :v, updated_by_user_id = :u, updated_at = :at WHERE tenant_id = :t AND setting_key = :k',
                ['v' => $json, 'u' => $userId, 'at' => self::now(), 't' => $tenantId, 'k' => $key]
            );
        }

        return true;
    }

    // ——————————————————————————— Fiches ———————————————————————————

    /** Nombre de fiches par catégorie existante (colonne themes de la fiche), sans toucher aux fiches. @return array{total: int, themes: array<string, int>} */
    public function noteThemeUsage(int $tenantId): array
    {
        $out = ['total' => 0, 'themes' => []];
        if ($tenantId < 1) {
            return $out;
        }
        try {
            foreach ($this->db->fetchAll('SELECT themes FROM sse_field_notes WHERE tenant_id = :t', ['t' => $tenantId]) as $row) {
                $out['total']++;
                foreach (\App\Support\SseFieldNoteCatalog::normalizeThemes($row['themes'] ?? '[]') as $code) {
                    $out['themes'][$code] = ($out['themes'][$code] ?? 0) + 1;
                }
            }
        } catch (\Throwable) {
            // Table des fiches absente : aucun compte.
        }

        return $out;
    }
}
