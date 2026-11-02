<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\JobCatalog\CommunityJobCatalog;
use App\Support\Migrations\SchemaInspector;
use InvalidArgumentException;
use PDO;
use Throwable;

/**
 * Catalogues d’emplois de communauté (tables job_catalog_collections / job_catalog_collection_entries).
 * Écritures toujours limitées à la communauté du lecteur ; lecture : ses catalogues et ceux que d’autres communautés
 * partagent publiquement. Chaque création, modification ou suppression est journalisée (audit_logs).
 */
final class JobCatalogCollectionRepository
{
    private ?bool $ready = null;

    private ?bool $linksReady = null;

    public function __construct(private ?PDO $pdo = null)
    {
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    /** Tables créées par l’étape 3480 (sinon la fenêtre montre seulement les catalogues officiels). */
    public function schemaReady(): bool
    {
        if ($this->ready !== null) {
            return $this->ready;
        }
        try {
            $schema = new SchemaInspector($this->pdo());

            return $this->ready = $schema->tableExists('job_catalog_collections') && $schema->tableExists('job_catalog_collection_entries');
        } catch (Throwable) {
            return $this->ready = false;
        }
    }

    /** Colonne des qualifications reliées (étape 3550) : sans elle, les qualifications restent du texte. */
    public function linksReady(): bool
    {
        if ($this->linksReady !== null) {
            return $this->linksReady;
        }
        try {
            return $this->linksReady = $this->schemaReady() && (new SchemaInspector($this->pdo()))->columnExists('job_catalog_collection_entries', 'qualification_links_json');
        } catch (Throwable) {
            return $this->linksReady = false;
        }
    }

    /**
     * Qualifications du référentiel de la communauté qu’un emploi de ses catalogues peut citer (non archivées).
     *
     * @return array<int, array{id: int, code: string, name: string}>
     */
    public function referentielDefinitions(int $tenantId): array
    {
        try {
            $st = $this->pdo()->prepare('SELECT id, code, name FROM personnel_qualification_definitions WHERE tenant_id = ? AND archived_at IS NULL ORDER BY name, id');
            $st->execute([$tenantId]);
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $out[(int) $row['id']] = ['id' => (int) $row['id'], 'code' => (string) $row['code'], 'name' => (string) $row['name']];
            }

            return $out;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Relie (ou délie : $definitionId = 0) une qualification citée par un emploi d’un catalogue de la communauté à une
     * qualification de son référentiel.
     *
     * @return array<string, int> texte => fiche du référentiel, après l’enregistrement
     */
    public function linkQualification(int $tenantId, int $entryId, string $text, int $definitionId, int $actorId): array
    {
        if (!$this->linksReady()) {
            throw new InvalidArgumentException('Relier une qualification arrive avec la prochaine mise à jour du site.');
        }
        $row = $this->findOwnEntry($tenantId, $entryId);
        if ($row === null) {
            throw new InvalidArgumentException('Emploi du catalogue introuvable.');
        }
        $text = trim($text);
        $listed = json_decode((string) ($row['qualifications_json'] ?? ''), true);
        if ($text === '' || !is_array($listed) || !in_array($text, array_map(static fn ($v): string => trim((string) $v), $listed), true)) {
            throw new InvalidArgumentException('Cette qualification n’est plus citée par l’emploi.');
        }
        if ($definitionId > 0 && !isset($this->referentielDefinitions($tenantId)[$definitionId])) {
            throw new InvalidArgumentException('Qualification introuvable dans votre référentiel.');
        }
        $links = CommunityJobCatalog::links($row['qualification_links_json'] ?? null);
        $before = $links;
        if ($definitionId > 0) {
            $links[$text] = $definitionId;
        } else {
            unset($links[$text]);
        }
        $this->pdo()->prepare('UPDATE job_catalog_collection_entries SET qualification_links_json = ?, updated_by = ?, updated_at = ? WHERE id = ? AND tenant_id = ?')
            ->execute([$links !== [] ? json_encode($links, JSON_UNESCAPED_UNICODE) : null, $actorId, self::now(), $entryId, $tenantId]);
        $this->audit($tenantId, $actorId, 'updated', 'job_catalog_entry', $entryId, ['qualification_links' => $before], ['qualification_links' => $links]);

        return $links;
    }

    private static function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    /** @return list<array<string, mixed>> catalogues de la communauté puis catalogues partagés des autres (avec author_name) */
    public function visibleCollections(int $tenantId): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $st = $this->pdo()->prepare('SELECT c.*, t.name AS author_name FROM job_catalog_collections c LEFT JOIN tenants t ON t.id = c.tenant_id
            WHERE c.tenant_id = ? OR c.is_public = 1 ORDER BY CASE WHEN c.tenant_id = ? THEN 0 ELSE 1 END, c.name, c.id');
        $st->execute([$tenantId, $tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param list<int> $collectionIds
     * @return list<array<string, mixed>>
     */
    public function entriesFor(array $collectionIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $collectionIds), static fn (int $id): bool => $id > 0)));
        if ($ids === [] || !$this->schemaReady()) {
            return [];
        }
        $st = $this->pdo()->prepare('SELECT * FROM job_catalog_collection_entries WHERE collection_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY collection_id, name, id');
        $st->execute($ids);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null catalogue de la communauté (jamais celui d’une autre) */
    public function findOwn(int $tenantId, int $id): ?array
    {
        $st = $this->pdo()->prepare('SELECT * FROM job_catalog_collections WHERE id = ? AND tenant_id = ?');
        $st->execute([$id, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null emploi d’un catalogue de la communauté */
    public function findOwnEntry(int $tenantId, int $id): ?array
    {
        $st = $this->pdo()->prepare('SELECT * FROM job_catalog_collection_entries WHERE id = ? AND tenant_id = ?');
        $st->execute([$id, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function nameTaken(string $table, string $scopeColumn, int $scopeId, string $name, int $exceptId): bool
    {
        $st = $this->pdo()->prepare('SELECT id, name FROM ' . $table . ' WHERE ' . $scopeColumn . ' = ? AND id <> ?');
        $st->execute([$scopeId, $exceptId]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            if (mb_strtolower(trim((string) $row['name'])) === mb_strtolower($name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Crée ou modifie un catalogue de la communauté ($id = 0 : création). Images : chemins déjà enregistrés,
     * null = inchangé, '' = retiré.
     *
     * @param array{name: string, description: string, icon: string, color: string, is_public: int} $data
     * @return array{id: int, old: array<string, mixed>|null}
     */
    public function saveCollection(int $tenantId, int $id, array $data, ?string $emblemPath, ?string $bannerPath, int $actorId): array
    {
        $old = null;
        if ($id > 0) {
            $old = $this->findOwn($tenantId, $id);
            if ($old === null) {
                throw new InvalidArgumentException('Catalogue introuvable.');
            }
        } else {
            $st = $this->pdo()->prepare('SELECT COUNT(*) FROM job_catalog_collections WHERE tenant_id = ?');
            $st->execute([$tenantId]);
            if ((int) $st->fetchColumn() >= CommunityJobCatalog::MAX_COLLECTIONS) {
                throw new InvalidArgumentException(sprintf('%d catalogues maximum par communauté.', CommunityJobCatalog::MAX_COLLECTIONS));
            }
        }
        if ($this->nameTaken('job_catalog_collections', 'tenant_id', $tenantId, $data['name'], $id)) {
            throw new InvalidArgumentException('Un catalogue de votre communauté porte déjà ce nom.');
        }
        $values = [
            'name' => $data['name'], 'description' => $data['description'] !== '' ? $data['description'] : null,
            'icon' => $data['icon'] !== '' ? $data['icon'] : null, 'color' => $data['color'] !== '' ? $data['color'] : null,
            'is_public' => $data['is_public'],
        ];
        if ($emblemPath !== null) {
            $values['emblem_path'] = $emblemPath !== '' ? $emblemPath : null;
        }
        if ($bannerPath !== null) {
            $values['banner_path'] = $bannerPath !== '' ? $bannerPath : null;
        }
        if ($id > 0) {
            $values['updated_by'] = $actorId;
            $values['updated_at'] = self::now();
            $sets = implode(', ', array_map(static fn (string $k): string => $k . ' = ?', array_keys($values)));
            $this->pdo()->prepare('UPDATE job_catalog_collections SET ' . $sets . ' WHERE id = ? AND tenant_id = ?')
                ->execute([...array_values($values), $id, $tenantId]);
        } else {
            $values += ['tenant_id' => $tenantId, 'created_by' => $actorId, 'created_at' => self::now(), 'updated_at' => self::now()];
            $this->pdo()->prepare('INSERT INTO job_catalog_collections (' . implode(', ', array_keys($values)) . ') VALUES (' . implode(', ', array_fill(0, count($values), '?')) . ')')
                ->execute(array_values($values));
            $id = (int) $this->pdo()->lastInsertId();
        }
        $this->audit($tenantId, $actorId, $old === null ? 'created' : 'updated', 'job_catalog_collection', $id,
            $old === null ? [] : self::auditCollection($old), self::auditCollection($values));

        return ['id' => $id, 'old' => $old];
    }

    /** Supprime un catalogue de la communauté et ses emplois ; renvoie les chemins d’images à effacer. */
    public function deleteCollection(int $tenantId, int $id, int $actorId): array
    {
        $old = $this->findOwn($tenantId, $id);
        if ($old === null) {
            throw new InvalidArgumentException('Catalogue introuvable.');
        }
        $st = $this->pdo()->prepare('SELECT image_path FROM job_catalog_collection_entries WHERE collection_id = ? AND tenant_id = ?');
        $st->execute([$id, $tenantId]);
        $paths = array_merge([(string) ($old['emblem_path'] ?? ''), (string) ($old['banner_path'] ?? '')], array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []));
        $this->pdo()->prepare('DELETE FROM job_catalog_collection_entries WHERE collection_id = ? AND tenant_id = ?')->execute([$id, $tenantId]);
        $this->pdo()->prepare('DELETE FROM job_catalog_collections WHERE id = ? AND tenant_id = ?')->execute([$id, $tenantId]);
        $this->audit($tenantId, $actorId, 'deleted', 'job_catalog_collection', $id, self::auditCollection($old), []);

        return array_values(array_filter($paths, static fn (string $p): bool => $p !== ''));
    }

    /**
     * Crée ou modifie un emploi d’un catalogue de la communauté ($id = 0 : création). Image : null = inchangée,
     * '' = retirée.
     *
     * @param array{name: string, abbreviation: string, category: string, unit: string, type: string, description: string, qualifications: list<string>, icon: string, tags: list<string>} $data
     * @return array{id: int, old: array<string, mixed>|null}
     */
    public function saveEntry(int $tenantId, int $collectionId, int $id, array $data, ?string $imagePath, int $actorId): array
    {
        if ($this->findOwn($tenantId, $collectionId) === null) {
            throw new InvalidArgumentException('Catalogue introuvable.');
        }
        $old = null;
        if ($id > 0) {
            $old = $this->findOwnEntry($tenantId, $id);
            if ($old === null || (int) $old['collection_id'] !== $collectionId) {
                throw new InvalidArgumentException('Emploi du catalogue introuvable.');
            }
        } else {
            $st = $this->pdo()->prepare('SELECT COUNT(*) FROM job_catalog_collection_entries WHERE collection_id = ?');
            $st->execute([$collectionId]);
            if ((int) $st->fetchColumn() >= CommunityJobCatalog::MAX_ENTRIES) {
                throw new InvalidArgumentException(sprintf('%d emplois maximum par catalogue.', CommunityJobCatalog::MAX_ENTRIES));
            }
        }
        if ($this->nameTaken('job_catalog_collection_entries', 'collection_id', $collectionId, $data['name'], $id)) {
            throw new InvalidArgumentException('Un emploi de ce catalogue porte déjà ce nom.');
        }
        $values = [
            'name' => $data['name'], 'abbreviation' => $data['abbreviation'] !== '' ? $data['abbreviation'] : null,
            'category' => $data['category'] !== '' ? $data['category'] : null, 'unit' => $data['unit'] !== '' ? $data['unit'] : null,
            'type' => $data['type'], 'description' => $data['description'] !== '' ? $data['description'] : null,
            'qualifications_json' => $data['qualifications'] !== [] ? json_encode($data['qualifications'], JSON_UNESCAPED_UNICODE) : null,
            'icon' => $data['icon'] !== '' ? $data['icon'] : null,
            'tags_json' => $data['tags'] !== [] ? json_encode($data['tags'], JSON_UNESCAPED_UNICODE) : null,
        ];
        if ($imagePath !== null) {
            $values['image_path'] = $imagePath !== '' ? $imagePath : null;
        }
        // Qualifications reliées : seules restent celles que l’emploi cite encore.
        if ($old !== null && $this->linksReady()) {
            $links = array_intersect_key(CommunityJobCatalog::links($old['qualification_links_json'] ?? null), array_flip($data['qualifications']));
            $values['qualification_links_json'] = $links !== [] ? json_encode($links, JSON_UNESCAPED_UNICODE) : null;
        }
        if ($id > 0) {
            $values['updated_by'] = $actorId;
            $values['updated_at'] = self::now();
            $sets = implode(', ', array_map(static fn (string $k): string => $k . ' = ?', array_keys($values)));
            $this->pdo()->prepare('UPDATE job_catalog_collection_entries SET ' . $sets . ' WHERE id = ? AND tenant_id = ?')
                ->execute([...array_values($values), $id, $tenantId]);
        } else {
            $values += ['tenant_id' => $tenantId, 'collection_id' => $collectionId, 'created_by' => $actorId, 'created_at' => self::now(), 'updated_at' => self::now()];
            $this->pdo()->prepare('INSERT INTO job_catalog_collection_entries (' . implode(', ', array_keys($values)) . ') VALUES (' . implode(', ', array_fill(0, count($values), '?')) . ')')
                ->execute(array_values($values));
            $id = (int) $this->pdo()->lastInsertId();
        }
        $this->pdo()->prepare('UPDATE job_catalog_collections SET updated_at = ? WHERE id = ? AND tenant_id = ?')->execute([self::now(), $collectionId, $tenantId]);
        $this->audit($tenantId, $actorId, $old === null ? 'created' : 'updated', 'job_catalog_entry', $id,
            $old === null ? [] : ['name' => (string) $old['name'], 'collection_id' => $collectionId], ['name' => $data['name'], 'collection_id' => $collectionId]);

        return ['id' => $id, 'old' => $old];
    }

    /** Supprime un emploi d’un catalogue de la communauté ; renvoie le chemin de son image à effacer (ou ''). */
    public function deleteEntry(int $tenantId, int $id, int $actorId): string
    {
        $old = $this->findOwnEntry($tenantId, $id);
        if ($old === null) {
            throw new InvalidArgumentException('Emploi du catalogue introuvable.');
        }
        $this->pdo()->prepare('DELETE FROM job_catalog_collection_entries WHERE id = ? AND tenant_id = ?')->execute([$id, $tenantId]);
        $this->audit($tenantId, $actorId, 'deleted', 'job_catalog_entry', $id, ['name' => (string) $old['name'], 'collection_id' => (int) $old['collection_id']], []);

        return (string) ($old['image_path'] ?? '');
    }

    /** @param array<string, mixed> $row */
    private static function auditCollection(array $row): array
    {
        return array_intersect_key($row, array_flip(['name', 'description', 'icon', 'color', 'is_public', 'emblem_path', 'banner_path']));
    }

    /** Journal d’audit (onglet « Historique et archives » des emplois) ; ne bloque jamais l’enregistrement. */
    private function audit(int $tenantId, int $actorId, string $operation, string $entity, int $id, array $before, array $after): void
    {
        try {
            $this->pdo()->prepare('INSERT INTO audit_logs (tenant_id,user_id,action,entity_type,entity_id,old_value,new_value,created_at) VALUES (?,?,?,?,?,?,?,CURRENT_TIMESTAMP)')
                ->execute([$tenantId, $actorId, 'personnel.' . $entity . '.' . $operation, $entity, $id,
                    $before === [] ? null : json_encode($before, JSON_UNESCAPED_UNICODE), $after === [] ? null : json_encode($after, JSON_UNESCAPED_UNICODE)]);
        } catch (Throwable $e) {
            error_log('Job catalog collection audit: ' . $e->getMessage());
        }
    }
}
