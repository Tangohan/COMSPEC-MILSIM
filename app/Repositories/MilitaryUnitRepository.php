<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Référentiel militaire global (SOF) — distinct de l'ORBAT tenant (`units`).
 */
class MilitaryUnitRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    public function tablesReady(): bool
    {
        try {
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'military_units' LIMIT 1"
            );

            return (bool) ($st && $st->fetchColumn());
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return list<array<string, mixed>> */
    public function listCountries(bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM countries';
        if ($activeOnly) {
            $sql .= ' WHERE active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, name_fr ASC';

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findCountryByIso2(string $iso2): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM countries WHERE iso2 = ? LIMIT 1');
        $st->execute([strtoupper(trim($iso2))]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function listServices(?int $countryId = null, bool $activeOnly = true): array
    {
        $sql = 'SELECT s.*, c.iso2 AS country_iso2, c.name_fr AS country_name_fr
                FROM military_services s
                INNER JOIN countries c ON c.id = s.country_id
                WHERE 1=1';
        $params = [];
        if ($countryId !== null) {
            $sql .= ' AND s.country_id = ?';
            $params[] = $countryId;
        }
        if ($activeOnly) {
            $sql .= ' AND s.active = 1';
        }
        $sql .= ' ORDER BY s.sort_order ASC, s.name ASC';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listEntityTypes(): array
    {
        return $this->pdo->query(
            'SELECT * FROM military_entity_types ORDER BY sort_order ASC, label_fr ASC'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listFunctions(): array
    {
        return $this->pdo->query(
            'SELECT * FROM military_functions ORDER BY sort_order ASC, label_fr ASC'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listSpecialties(): array
    {
        return $this->pdo->query(
            'SELECT * FROM military_specialties ORDER BY sort_order ASC, label_fr ASC'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listDomains(): array
    {
        return $this->pdo->query(
            'SELECT * FROM military_domains ORDER BY sort_order ASC, label_fr ASC'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listClassifications(): array
    {
        return $this->pdo->query(
            'SELECT * FROM military_classifications ORDER BY sort_order ASC, label_fr ASC'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listSources(): array
    {
        return $this->pdo->query(
            'SELECT * FROM military_sources ORDER BY name ASC'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id): ?array
    {
        $st = $this->pdo->prepare($this->unitSelectSql() . ' WHERE u.id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByCode(string $code): ?array
    {
        $st = $this->pdo->prepare($this->unitSelectSql() . ' WHERE u.code = ? LIMIT 1');
        $st->execute([trim($code)]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function listByCountryIso2(string $iso2, bool $activeOnly = true): array
    {
        $sql = $this->unitSelectSql() . ' WHERE c.iso2 = ?';
        $params = [strtoupper(trim($iso2))];
        if ($activeOnly) {
            $sql .= ' AND u.active = 1';
        }
        $sql .= ' ORDER BY u.hierarchy_level ASC, u.sort_order ASC, u.display_name ASC';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listAll(bool $activeOnly = false): array
    {
        $sql = $this->unitSelectSql();
        if ($activeOnly) {
            $sql .= ' WHERE u.active = 1';
        }
        $sql .= ' ORDER BY c.sort_order ASC, u.hierarchy_level ASC, u.sort_order ASC, u.display_name ASC';

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listAliases(int $unitId): array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM military_unit_aliases WHERE unit_id = ? ORDER BY is_primary DESC, alias ASC'
        );
        $st->execute([$unitId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Chaîne d'ancêtres (racine → parent immédiat), hors unité elle-même. */
    /** @return list<array<string, mixed>> */
    public function getAncestors(int $unitId): array
    {
        $sql = 'WITH RECURSIVE chain AS (
            SELECT u.id, u.parent_id, u.code, u.display_name, u.short_name, u.official_name, u.hierarchy_level, 0 AS depth
            FROM military_units u WHERE u.id = ?
            UNION ALL
            SELECT p.id, p.parent_id, p.code, p.display_name, p.short_name, p.official_name, p.hierarchy_level, c.depth + 1
            FROM military_units p
            INNER JOIN chain c ON c.parent_id = p.id
          )
          SELECT * FROM chain WHERE id <> ? ORDER BY depth DESC';
        try {
            $st = $this->pdo->prepare($sql);
            $st->execute([$unitId, $unitId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            // Fallback sans CTE
            return $this->getAncestorsIterative($unitId);
        }
    }

    /** @return list<array<string, mixed>> */
    public function getDescendants(int $unitId, bool $includeSelf = false): array
    {
        $sql = 'WITH RECURSIVE tree AS (
            SELECT u.id, u.parent_id, u.code, u.display_name, u.short_name, u.official_name, u.hierarchy_level, 0 AS depth
            FROM military_units u WHERE u.id = ?
            UNION ALL
            SELECT c.id, c.parent_id, c.code, c.display_name, c.short_name, c.official_name, c.hierarchy_level, t.depth + 1
            FROM military_units c
            INNER JOIN tree t ON c.parent_id = t.id
          )
          SELECT * FROM tree' . ($includeSelf ? '' : ' WHERE id <> ?') . ' ORDER BY depth ASC, display_name ASC';
        try {
            $st = $this->pdo->prepare($sql);
            if ($includeSelf) {
                $st->execute([$unitId]);
            } else {
                $st->execute([$unitId, $unitId]);
            }

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return $this->getDescendantsIterative($unitId, $includeSelf);
        }
    }

    /**
     * Recherche multi-champs (§33).
     *
     * @return list<array<string, mixed>>
     */
    public function search(string $query, ?string $countryIso2 = null, int $limit = 100): array
    {
        $q = trim($query);
        if ($q === '') {
            return [];
        }
        $like = '%' . $q . '%';
        $likeCompact = '%' . preg_replace('/\s+/', '', $q) . '%';
        $params = [$like, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like, $likeCompact, $likeCompact];
        $matchSql = '(
                u.official_name LIKE ?
                OR u.display_name LIKE ?
                OR u.short_name LIKE ?
                OR u.international_name LIKE ?
                OR u.code LIKE ?
                OR c.name_fr LIKE ?
                OR c.name_en LIKE ?
                OR s.name LIKE ?
                OR s.short_name LIKE ?
                OR et.label_fr LIKE ?
                OR et.label_en LIKE ?
                OR REPLACE(COALESCE(u.short_name, \'\'), \' \', \'\') LIKE ?
                OR REPLACE(COALESCE(u.display_name, \'\'), \' \', \'\') LIKE ?
                OR EXISTS (
                  SELECT 1 FROM military_unit_aliases a
                  WHERE a.unit_id = u.id AND a.searchable = 1
                    AND (a.alias LIKE ? OR REPLACE(a.alias, \' \', \'\') LIKE ?)
                )
                OR EXISTS (
                  SELECT 1 FROM military_unit_specialties us
                  INNER JOIN military_specialties sp ON sp.id = us.specialty_id
                  WHERE us.unit_id = u.id AND (sp.label_fr LIKE ? OR sp.label_en LIKE ? OR sp.code LIKE ?)
                )
                OR EXISTS (
                  SELECT 1 FROM military_unit_functions uf
                  INNER JOIN military_functions fn ON fn.id = uf.function_id
                  WHERE uf.unit_id = u.id AND (fn.label_fr LIKE ? OR fn.label_en LIKE ? OR fn.code LIKE ?)
                )
                OR EXISTS (
                  SELECT 1 FROM military_unit_domains ud
                  INNER JOIN military_domains d ON d.id = ud.domain_id
                  WHERE ud.unit_id = u.id AND (d.label_fr LIKE ? OR d.label_en LIKE ? OR d.code LIKE ?)
                )
              )';
        $params[] = $like;
        $params[] = $likeCompact;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;

        // Inclure les descendants d'une entité dont le nom/alias matche (ex. USASOC)
        $rootMatches = $this->findIdsMatchingNameOrAlias($q, $countryIso2);
        $extraIds = [];
        foreach ($rootMatches as $rid) {
            foreach ($this->getDescendants($rid, true) as $d) {
                $extraIds[(int) $d['id']] = true;
            }
        }
        if ($extraIds !== []) {
            $placeholders = implode(',', array_fill(0, count($extraIds), '?'));
            $matchSql = '(' . $matchSql . " OR u.id IN ({$placeholders}))";
            foreach (array_keys($extraIds) as $eid) {
                $params[] = $eid;
            }
        }

        $sql = $this->unitSelectSql() . ' WHERE u.active = 1 AND ' . $matchSql;
        if ($countryIso2 !== null && $countryIso2 !== '') {
            $sql .= ' AND c.iso2 = ?';
            $params[] = strtoupper(trim($countryIso2));
        }

        $sql .= ' ORDER BY u.hierarchy_level ASC, u.sort_order ASC, u.display_name ASC LIMIT ' . max(1, min(500, $limit));
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<int> */
    private function findIdsMatchingNameOrAlias(string $q, ?string $countryIso2): array
    {
        $like = '%' . $q . '%';
        $sql = 'SELECT DISTINCT u.id FROM military_units u
                INNER JOIN countries c ON c.id = u.country_id
                LEFT JOIN military_unit_aliases a ON a.unit_id = u.id AND a.searchable = 1
                WHERE u.active = 1 AND (
                  u.display_name LIKE ? OR u.short_name LIKE ? OR u.code LIKE ? OR a.alias LIKE ?
                )';
        $params = [$like, $like, $like, $like];
        if ($countryIso2 !== null && $countryIso2 !== '') {
            $sql .= ' AND c.iso2 = ?';
            $params[] = strtoupper(trim($countryIso2));
        }
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $ids = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $ids[] = (int) $row['id'];
        }

        return $ids;
    }

    public function create(array $data): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO military_units
             (parent_id, country_id, service_id, entity_type_id, code, slug, official_name, short_name, display_name,
              international_name, description_short, description_long, mission_summary, functions_summary,
              status, active, hierarchy_level, sort_order, founded_at, dissolved_at, official_website, verified_at, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())'
        );
        $st->execute([
            $data['parent_id'] ?? null,
            $data['country_id'],
            $data['service_id'] ?? null,
            $data['entity_type_id'],
            $data['code'],
            $data['slug'],
            $data['official_name'],
            $data['short_name'] ?? null,
            $data['display_name'],
            $data['international_name'] ?? null,
            $data['description_short'] ?? null,
            $data['description_long'] ?? null,
            $data['mission_summary'] ?? null,
            $data['functions_summary'] ?? null,
            $data['status'] ?? 'active',
            isset($data['active']) ? (int) (bool) $data['active'] : 1,
            (int) ($data['hierarchy_level'] ?? 0),
            (int) ($data['sort_order'] ?? 0),
            $data['founded_at'] ?? null,
            $data['dissolved_at'] ?? null,
            $data['official_website'] ?? null,
            $data['verified_at'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $fields = [
            'parent_id', 'country_id', 'service_id', 'entity_type_id', 'code', 'slug',
            'official_name', 'short_name', 'display_name', 'international_name',
            'description_short', 'description_long', 'mission_summary', 'functions_summary',
            'status', 'active', 'hierarchy_level', 'sort_order', 'founded_at', 'dissolved_at',
            'official_website', 'verified_at',
        ];
        $sets = [];
        $params = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                $sets[] = "{$f} = ?";
                $params[] = $data[$f];
            }
        }
        if ($sets === []) {
            return;
        }
        $sets[] = 'updated_at = NOW()';
        $params[] = $id;
        $st = $this->pdo->prepare('UPDATE military_units SET ' . implode(', ', $sets) . ' WHERE id = ?');
        $st->execute($params);
    }

    public function addAlias(int $unitId, string $alias, string $type = 'COMMON_NAME', ?string $lang = null, bool $searchable = true): void
    {
        $st = $this->pdo->prepare(
            'INSERT IGNORE INTO military_unit_aliases (unit_id, alias, alias_type, language, is_primary, searchable)
             VALUES (?,?,?,?,0,?)'
        );
        $st->execute([$unitId, trim($alias), $type, $lang, $searchable ? 1 : 0]);
    }

    public function deleteAlias(int $aliasId): void
    {
        $st = $this->pdo->prepare('DELETE FROM military_unit_aliases WHERE id = ?');
        $st->execute([$aliasId]);
    }

    public function syncUnitFunctions(int $unitId, array $functionIds, ?int $primaryId = null): void
    {
        $this->pdo->prepare('DELETE FROM military_unit_functions WHERE unit_id = ?')->execute([$unitId]);
        $ins = $this->pdo->prepare(
            'INSERT INTO military_unit_functions (unit_id, function_id, is_primary) VALUES (?,?,?)'
        );
        foreach ($functionIds as $fid) {
            $fid = (int) $fid;
            if ($fid <= 0) {
                continue;
            }
            $ins->execute([$unitId, $fid, ($primaryId !== null && $fid === $primaryId) ? 1 : 0]);
        }
    }

    public function syncUnitSpecialties(int $unitId, array $specialtyIds, ?int $primaryId = null): void
    {
        $this->pdo->prepare('DELETE FROM military_unit_specialties WHERE unit_id = ?')->execute([$unitId]);
        $ins = $this->pdo->prepare(
            'INSERT INTO military_unit_specialties (unit_id, specialty_id, is_primary) VALUES (?,?,?)'
        );
        foreach ($specialtyIds as $sid) {
            $sid = (int) $sid;
            if ($sid <= 0) {
                continue;
            }
            $ins->execute([$unitId, $sid, ($primaryId !== null && $sid === $primaryId) ? 1 : 0]);
        }
    }

    public function syncUnitDomains(int $unitId, array $domainIds): void
    {
        $this->pdo->prepare('DELETE FROM military_unit_domains WHERE unit_id = ?')->execute([$unitId]);
        $ins = $this->pdo->prepare('INSERT INTO military_unit_domains (unit_id, domain_id) VALUES (?,?)');
        foreach ($domainIds as $did) {
            $did = (int) $did;
            if ($did > 0) {
                $ins->execute([$unitId, $did]);
            }
        }
    }

    public function syncUnitClassifications(int $unitId, array $classificationIds, ?int $primaryId = null): void
    {
        $this->pdo->prepare('DELETE FROM military_unit_classifications WHERE unit_id = ?')->execute([$unitId]);
        $ins = $this->pdo->prepare(
            'INSERT INTO military_unit_classifications (unit_id, classification_id, is_primary) VALUES (?,?,?)'
        );
        foreach ($classificationIds as $cid) {
            $cid = (int) $cid;
            if ($cid <= 0) {
                continue;
            }
            $ins->execute([$unitId, $cid, ($primaryId !== null && $cid === $primaryId) ? 1 : 0]);
        }
    }

    public function addUnitSource(int $unitId, int $sourceId, string $informationType = 'IDENTITY', ?string $notes = null): void
    {
        $st = $this->pdo->prepare(
            'INSERT IGNORE INTO military_unit_sources (unit_id, source_id, information_type, notes) VALUES (?,?,?,?)'
        );
        $st->execute([$unitId, $sourceId, $informationType, $notes]);
    }

    /** @return list<int> */
    public function getUnitFunctionIds(int $unitId): array
    {
        $st = $this->pdo->prepare('SELECT function_id FROM military_unit_functions WHERE unit_id = ?');
        $st->execute([$unitId]);

        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /** @return list<int> */
    public function getUnitSpecialtyIds(int $unitId): array
    {
        $st = $this->pdo->prepare('SELECT specialty_id FROM military_unit_specialties WHERE unit_id = ?');
        $st->execute([$unitId]);

        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /** @return list<int> */
    public function getUnitDomainIds(int $unitId): array
    {
        $st = $this->pdo->prepare('SELECT domain_id FROM military_unit_domains WHERE unit_id = ?');
        $st->execute([$unitId]);

        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /** @return list<int> */
    public function getUnitClassificationIds(int $unitId): array
    {
        $st = $this->pdo->prepare('SELECT classification_id FROM military_unit_classifications WHERE unit_id = ?');
        $st->execute([$unitId]);

        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /** @return list<array<string, mixed>> */
    public function listUnitSources(int $unitId): array
    {
        $st = $this->pdo->prepare(
            'SELECT us.*, s.name AS source_name, s.publisher, s.url, s.source_type
             FROM military_unit_sources us
             INNER JOIN military_sources s ON s.id = us.source_id
             WHERE us.unit_id = ?
             ORDER BY us.information_type ASC, s.name ASC'
        );
        $st->execute([$unitId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array{id: string, name: string, tier: string, tier_order: int, indent: int, numeric_id: int}> */
    public function catalogRowsForCountry(string $iso2): array
    {
        $units = $this->listByCountryIso2($iso2, true);
        // Ordre arborescent (DFS) : enfants juste après le parent
        $byParent = [];
        $roots = [];
        foreach ($units as $u) {
            $pid = $u['parent_id'] ?? null;
            $key = $pid === null || $pid === '' ? 0 : (int) $pid;
            if ($key === 0) {
                $roots[] = $u;
            } else {
                $byParent[$key][] = $u;
            }
        }
        $ordered = [];
        $walk = static function (array $node) use (&$walk, &$ordered, $byParent): void {
            $ordered[] = $node;
            $id = (int) ($node['id'] ?? 0);
            foreach ($byParent[$id] ?? [] as $child) {
                $walk($child);
            }
        };
        foreach ($roots as $root) {
            $walk($root);
        }
        // Orphelins (parent hors pays / inactif)
        $seen = [];
        foreach ($ordered as $u) {
            $seen[(int) $u['id']] = true;
        }
        foreach ($units as $u) {
            if (!isset($seen[(int) $u['id']])) {
                $ordered[] = $u;
            }
        }

        $out = [];
        foreach ($ordered as $u) {
            $typeCode = (string) ($u['entity_type_code'] ?? '');
            $tier = match (true) {
                in_array($typeCode, ['COMMAND', 'JOINT_COMMAND'], true) => 'command',
                in_array($typeCode, ['COMPONENT_COMMAND', 'FORCE', 'TRAINING_UNIT'], true) => 'component',
                in_array($typeCode, ['COMMANDO', 'COMPANY', 'DETACHMENT', 'BATTALION', 'SQUADRON', 'AIR_SQUADRON'], true)
                    && (int) ($u['hierarchy_level'] ?? 0) >= 2 => 'subunit',
                default => 'unit',
            };
            $indent = max(0, (int) ($u['hierarchy_level'] ?? 0));
            $name = (string) ($u['display_name'] ?? $u['official_name'] ?? '');
            if (!empty($u['official_name']) && $u['official_name'] !== $u['display_name']) {
                $name = (string) $u['official_name'];
                if (!empty($u['short_name'])) {
                    $name .= ' (' . $u['short_name'] . ')';
                }
            } elseif (!empty($u['short_name']) && !str_contains($name, (string) $u['short_name'])) {
                $name = $name . ' (' . $u['short_name'] . ')';
            }
            $out[] = [
                'id' => (string) $u['code'],
                'name' => $name,
                'tier' => $tier,
                'tier_order' => (int) ($u['hierarchy_level'] ?? 0),
                'indent' => $indent,
                'numeric_id' => (int) $u['id'],
                'parent_code' => $u['parent_code'] ?? null,
                'short_name' => $u['short_name'] ?? null,
                'aliases' => [],
            ];
        }

        // Attach searchable aliases for client-side search
        if ($out !== []) {
            $ids = array_column($out, 'numeric_id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $st = $this->pdo->prepare(
                "SELECT unit_id, alias FROM military_unit_aliases WHERE searchable = 1 AND unit_id IN ({$placeholders})"
            );
            $st->execute($ids);
            $byUnit = [];
            while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $byUnit[(int) $row['unit_id']][] = (string) $row['alias'];
            }
            foreach ($out as &$row) {
                $row['aliases'] = $byUnit[$row['numeric_id']] ?? [];
            }
            unset($row);
        }

        return $out;
    }

    /** @return list<array{id: string, name: string}> */
    public function resolveSelectedByCodes(string $iso2, array $codes): array
    {
        $out = [];
        $seen = [];
        foreach ($codes as $raw) {
            if (!is_string($raw) && !is_int($raw)) {
                continue;
            }
            $code = trim((string) $raw);
            if ($code === '' || isset($seen[$code])) {
                continue;
            }
            $u = $this->findByCode($code);
            if ($u === null) {
                continue;
            }
            $countryIso = strtoupper((string) ($u['country_iso2'] ?? ''));
            if ($countryIso !== '' && $countryIso !== strtoupper(trim($iso2))) {
                continue;
            }
            $seen[$code] = true;
            $label = (string) ($u['official_name'] ?? $u['display_name'] ?? $code);
            if (!empty($u['short_name']) && !str_contains($label, (string) $u['short_name'])) {
                $label .= ' (' . $u['short_name'] . ')';
            }
            $out[] = ['id' => (string) $u['code'], 'name' => $label];
        }

        return $out;
    }

    public function replaceTenantAffiliations(int $tenantId, array $unitCodes): void
    {
        $this->pdo->prepare('DELETE FROM tenant_military_unit_affiliations WHERE tenant_id = ?')->execute([$tenantId]);
        $ins = $this->pdo->prepare(
            'INSERT INTO tenant_military_unit_affiliations (tenant_id, military_unit_id, sort_order, created_at)
             VALUES (?,?,?,NOW())'
        );
        $sort = 0;
        foreach ($unitCodes as $code) {
            $u = $this->findByCode((string) $code);
            if ($u === null) {
                continue;
            }
            $ins->execute([$tenantId, (int) $u['id'], $sort++]);
        }
    }

    /** @return list<array<string, mixed>> */
    public function listTenantAffiliations(int $tenantId): array
    {
        $st = $this->pdo->prepare(
            'SELECT u.*, c.iso2 AS country_iso2, c.name_fr AS country_name_fr, a.sort_order AS affiliation_sort
             FROM tenant_military_unit_affiliations a
             INNER JOIN military_units u ON u.id = a.military_unit_id
             INNER JOIN countries c ON c.id = u.country_id
             WHERE a.tenant_id = ?
             ORDER BY a.sort_order ASC, u.display_name ASC'
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createSource(array $data): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO military_sources (name, publisher, url, source_type, published_at, checked_at, created_at)
             VALUES (?,?,?,?,?,?,NOW())'
        );
        $st->execute([
            $data['name'],
            $data['publisher'] ?? null,
            $data['url'] ?? null,
            $data['source_type'] ?? null,
            $data['published_at'] ?? null,
            $data['checked_at'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function unitSelectSql(): string
    {
        return 'SELECT u.*,
                       c.iso2 AS country_iso2, c.name_fr AS country_name_fr, c.name_en AS country_name_en,
                       s.code AS service_code, s.name AS service_name, s.short_name AS service_short_name,
                       et.code AS entity_type_code, et.label_fr AS entity_type_label_fr, et.label_en AS entity_type_label_en,
                       p.code AS parent_code, p.display_name AS parent_display_name
                FROM military_units u
                INNER JOIN countries c ON c.id = u.country_id
                LEFT JOIN military_services s ON s.id = u.service_id
                INNER JOIN military_entity_types et ON et.id = u.entity_type_id
                LEFT JOIN military_units p ON p.id = u.parent_id';
    }

    /** @return list<array<string, mixed>> */
    private function getAncestorsIterative(int $unitId): array
    {
        $chain = [];
        $current = $unitId;
        $guard = 0;
        while ($guard++ < 50) {
            $st = $this->pdo->prepare(
                'SELECT id, parent_id, code, display_name, short_name, official_name, hierarchy_level
                 FROM military_units WHERE id = ?'
            );
            $st->execute([$current]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row || empty($row['parent_id'])) {
                break;
            }
            $st = $this->pdo->prepare(
                'SELECT id, parent_id, code, display_name, short_name, official_name, hierarchy_level
                 FROM military_units WHERE id = ?'
            );
            $st->execute([(int) $row['parent_id']]);
            $parent = $st->fetch(PDO::FETCH_ASSOC);
            if (!$parent) {
                break;
            }
            array_unshift($chain, $parent);
            $current = (int) $parent['id'];
        }

        return $chain;
    }

    /** @return list<array<string, mixed>> */
    private function getDescendantsIterative(int $unitId, bool $includeSelf): array
    {
        $out = [];
        if ($includeSelf) {
            $self = $this->findById($unitId);
            if ($self) {
                $out[] = $self;
            }
        }
        $queue = [$unitId];
        $seen = [$unitId => true];
        while ($queue !== []) {
            $pid = array_shift($queue);
            $st = $this->pdo->prepare(
                'SELECT id, parent_id, code, display_name, short_name, official_name, hierarchy_level
                 FROM military_units WHERE parent_id = ? ORDER BY sort_order ASC, display_name ASC'
            );
            $st->execute([$pid]);
            while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $id = (int) $row['id'];
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $out[] = $row;
                $queue[] = $id;
            }
        }

        return $out;
    }
}
