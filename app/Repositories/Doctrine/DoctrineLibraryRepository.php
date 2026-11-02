<?php

declare(strict_types=1);

namespace App\Repositories\Doctrine;

use App\Core\Database;
use App\Support\Migrations\SchemaInspector;
use InvalidArgumentException;
use PDO;
use Throwable;

/**
 * Personnalisation du référentiel doctrinal par communauté (tables de l’étape 3560) : références ajoutées, références
 * du site masquées, catégories propres, rattachements d’une référence à un document de doctrine.
 * Toutes les lectures et écritures sont limitées à la communauté passée en paramètre. Rien n’est jamais supprimé :
 * archivage (archived_at), masquage réversible (hidden = 0/1), rattachement retiré (unlinked_at).
 * Chaque écriture est journalisée (audit_logs, action « doctrine.library_* »).
 */
final class DoctrineLibraryRepository
{
    public const TABLES = ['doctrine_library_categories', 'doctrine_library_entries', 'doctrine_library_hidden', 'doctrine_library_links'];

    private ?bool $ready = null;

    public function __construct(private ?PDO $pdo = null)
    {
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    public function schemaReady(): bool
    {
        if ($this->ready !== null) {
            return $this->ready;
        }
        try {
            $schema = new SchemaInspector($this->pdo());
            foreach (self::TABLES as $table) {
                if (!$schema->tableExists($table)) {
                    return $this->ready = false;
                }
            }

            return $this->ready = true;
        } catch (Throwable) {
            return $this->ready = false;
        }
    }

    private function requireSchema(): void
    {
        if (!$this->schemaReady()) {
            throw new InvalidArgumentException('La personnalisation du référentiel arrive avec la prochaine mise à jour du site.');
        }
    }

    private static function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    /** @return list<array<string, mixed>> */
    private function rows(string $sql, array $params): array
    {
        $st = $this->pdo()->prepare($sql);
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /* ---------- Catégories ---------- */

    /** @return list<array<string, mixed>> */
    public function categories(int $tenantId): array
    {
        if (!$this->schemaReady() || $tenantId < 1) {
            return [];
        }

        return $this->rows('SELECT * FROM doctrine_library_categories WHERE tenant_id = ? ORDER BY label, id', [$tenantId]);
    }

    public function findCategory(int $tenantId, int $id): ?array
    {
        if (!$this->schemaReady() || $id < 1) {
            return null;
        }

        return $this->rows('SELECT * FROM doctrine_library_categories WHERE tenant_id = ? AND id = ?', [$tenantId, $id])[0] ?? null;
    }

    public function saveCategory(int $tenantId, int $actorId, int $id, string $label, ?string $color): int
    {
        $this->requireSchema();
        $old = $id > 0 ? $this->findCategory($tenantId, $id) : null;
        if ($id > 0 && $old === null) {
            throw new InvalidArgumentException('Catégorie introuvable.');
        }
        $same = $this->rows('SELECT id FROM doctrine_library_categories WHERE tenant_id = ? AND LOWER(label) = LOWER(?) AND id <> ?', [$tenantId, $label, $id]);
        if ($same !== []) {
            throw new InvalidArgumentException('Une catégorie porte déjà ce nom.');
        }
        if ($old === null) {
            $this->pdo()->prepare('INSERT INTO doctrine_library_categories (tenant_id, label, color, created_by, created_at) VALUES (?,?,?,?,?)')
                ->execute([$tenantId, $label, $color, $actorId ?: null, self::now()]);
            $id = (int) $this->pdo()->lastInsertId();
        } else {
            $this->pdo()->prepare('UPDATE doctrine_library_categories SET label = ?, color = ?, updated_at = ? WHERE tenant_id = ? AND id = ?')
                ->execute([$label, $color, self::now(), $tenantId, $id]);
        }
        $this->audit($tenantId, $actorId, $old === null ? 'category_created' : 'category_updated', 'doctrine_library_category', $id,
            $old === null ? [] : ['label' => $old['label'], 'color' => $old['color']], ['label' => $label, 'color' => $color]);

        return $id;
    }

    public function setCategoryArchived(int $tenantId, int $actorId, int $id, bool $archived): void
    {
        $this->requireSchema();
        if ($this->findCategory($tenantId, $id) === null) {
            throw new InvalidArgumentException('Catégorie introuvable.');
        }
        $this->pdo()->prepare('UPDATE doctrine_library_categories SET archived_at = ?, updated_at = ? WHERE tenant_id = ? AND id = ?')
            ->execute([$archived ? self::now() : null, self::now(), $tenantId, $id]);
        $this->audit($tenantId, $actorId, $archived ? 'category_archived' : 'category_restored', 'doctrine_library_category', $id, [], []);
    }

    /* ---------- Références de la communauté ---------- */

    /** @return list<array<string, mixed>> */
    public function entries(int $tenantId): array
    {
        if (!$this->schemaReady() || $tenantId < 1) {
            return [];
        }

        return $this->rows('SELECT * FROM doctrine_library_entries WHERE tenant_id = ? ORDER BY organization, code, id', [$tenantId]);
    }

    public function findEntry(int $tenantId, int $id): ?array
    {
        if (!$this->schemaReady() || $id < 1) {
            return null;
        }

        return $this->rows('SELECT * FROM doctrine_library_entries WHERE tenant_id = ? AND id = ?', [$tenantId, $id])[0] ?? null;
    }

    /**
     * Crée ou modifie une référence de la communauté (valeurs déjà contrôlées par DoctrineLibraryService::normalizeEntry).
     * Un fichier vaut : chaîne = nouveau chemin, '' = retiré, null = inchangé.
     *
     * @param array<string, mixed> $values
     */
    public function saveEntry(int $tenantId, int $actorId, int $id, array $values): int
    {
        $this->requireSchema();
        $old = $id > 0 ? $this->findEntry($tenantId, $id) : null;
        if ($id > 0 && $old === null) {
            throw new InvalidArgumentException('Référence introuvable.');
        }
        $same = $this->rows('SELECT id FROM doctrine_library_entries WHERE tenant_id = ? AND LOWER(organization) = LOWER(?) AND LOWER(code) = LOWER(?) AND id <> ?',
            [$tenantId, $values['organization'], $values['code'], $id]);
        if ($same !== []) {
            throw new InvalidArgumentException('Cette organisation a déjà une référence avec ce code.');
        }
        if ($values['category_id'] !== null && $this->findCategory($tenantId, (int) $values['category_id']) === null) {
            throw new InvalidArgumentException('Catégorie introuvable.');
        }
        $cols = ['organization', 'code', 'title', 'title_translation', 'lang', 'domain', 'category_id', 'type', 'level', 'summary', 'links_json'];
        $data = [];
        foreach ($cols as $c) {
            $data[$c] = $values[$c] ?? null;
        }
        if (array_key_exists('file_path', $values) && $values['file_path'] !== null) {
            $data['file_path'] = $values['file_path'] === '' ? null : $values['file_path'];
            $data['file_name'] = $values['file_path'] === '' ? null : ($values['file_name'] ?? null);
        }
        if ($old === null) {
            $data += ['tenant_id' => $tenantId, 'created_by' => $actorId ?: null, 'created_at' => self::now()];
            $this->pdo()->prepare('INSERT INTO doctrine_library_entries (' . implode(', ', array_keys($data)) . ') VALUES (' . implode(',', array_fill(0, count($data), '?')) . ')')
                ->execute(array_values($data));
            $id = (int) $this->pdo()->lastInsertId();
        } else {
            $data += ['updated_by' => $actorId ?: null, 'updated_at' => self::now()];
            $set = implode(', ', array_map(static fn (string $c): string => $c . ' = ?', array_keys($data)));
            $this->pdo()->prepare('UPDATE doctrine_library_entries SET ' . $set . ' WHERE tenant_id = ? AND id = ?')
                ->execute([...array_values($data), $tenantId, $id]);
        }
        $this->audit($tenantId, $actorId, $old === null ? 'entry_created' : 'entry_updated', 'doctrine_library_entry', $id,
            $old === null ? [] : ['organization' => $old['organization'], 'code' => $old['code'], 'title' => $old['title']],
            ['organization' => $data['organization'], 'code' => $data['code'], 'title' => $data['title']]);

        return $id;
    }

    public function setEntryArchived(int $tenantId, int $actorId, int $id, bool $archived): void
    {
        $this->requireSchema();
        if ($this->findEntry($tenantId, $id) === null) {
            throw new InvalidArgumentException('Référence introuvable.');
        }
        $this->pdo()->prepare('UPDATE doctrine_library_entries SET archived_at = ?, updated_by = ?, updated_at = ? WHERE tenant_id = ? AND id = ?')
            ->execute([$archived ? self::now() : null, $actorId ?: null, self::now(), $tenantId, $id]);
        $this->audit($tenantId, $actorId, $archived ? 'entry_archived' : 'entry_restored', 'doctrine_library_entry', $id, [], []);
    }

    /* ---------- Masquage des références du site ---------- */

    /** @return array<string, true> clés masquées */
    public function hiddenKeys(int $tenantId): array
    {
        if (!$this->schemaReady() || $tenantId < 1) {
            return [];
        }
        $out = [];
        foreach ($this->rows('SELECT entry_key FROM doctrine_library_hidden WHERE tenant_id = ? AND hidden = 1', [$tenantId]) as $r) {
            $out[(string) $r['entry_key']] = true;
        }

        return $out;
    }

    public function setHidden(int $tenantId, int $actorId, string $key, bool $hidden): void
    {
        $this->requireSchema();
        $row = $this->rows('SELECT id FROM doctrine_library_hidden WHERE tenant_id = ? AND entry_key = ?', [$tenantId, $key])[0] ?? null;
        if ($row === null) {
            if (!$hidden) {
                return;
            }
            $this->pdo()->prepare('INSERT INTO doctrine_library_hidden (tenant_id, entry_key, hidden, updated_by, created_at) VALUES (?,?,1,?,?)')
                ->execute([$tenantId, $key, $actorId ?: null, self::now()]);
        } else {
            $this->pdo()->prepare('UPDATE doctrine_library_hidden SET hidden = ?, updated_by = ?, updated_at = ? WHERE tenant_id = ? AND id = ?')
                ->execute([$hidden ? 1 : 0, $actorId ?: null, self::now(), $tenantId, (int) $row['id']]);
        }
        $this->audit($tenantId, $actorId, $hidden ? 'hidden' : 'shown', 'doctrine_library_reference', 0, [], ['key' => $key]);
    }

    /* ---------- Rattachements aux documents de doctrine ---------- */

    /** @return array<string, list<int>> clé => documents rattachés */
    public function links(int $tenantId): array
    {
        if (!$this->schemaReady() || $tenantId < 1) {
            return [];
        }
        $out = [];
        foreach ($this->rows('SELECT entry_key, document_id FROM doctrine_library_links WHERE tenant_id = ? AND unlinked_at IS NULL ORDER BY id', [$tenantId]) as $r) {
            $out[(string) $r['entry_key']][] = (int) $r['document_id'];
        }

        return $out;
    }

    public function link(int $tenantId, int $actorId, string $key, int $documentId): void
    {
        $this->requireSchema();
        if (!$this->documentBelongsTo($tenantId, $documentId)) {
            throw new InvalidArgumentException('Document de doctrine introuvable dans votre communauté.');
        }
        $row = $this->rows('SELECT id FROM doctrine_library_links WHERE tenant_id = ? AND entry_key = ? AND document_id = ?', [$tenantId, $key, $documentId])[0] ?? null;
        if ($row === null) {
            $this->pdo()->prepare('INSERT INTO doctrine_library_links (tenant_id, entry_key, document_id, linked_by, created_at) VALUES (?,?,?,?,?)')
                ->execute([$tenantId, $key, $documentId, $actorId ?: null, self::now()]);
        } else {
            $this->pdo()->prepare('UPDATE doctrine_library_links SET unlinked_at = NULL, linked_by = ?, updated_at = ? WHERE tenant_id = ? AND id = ?')
                ->execute([$actorId ?: null, self::now(), $tenantId, (int) $row['id']]);
        }
        $this->audit($tenantId, $actorId, 'linked', 'document', $documentId, [], ['key' => $key]);
    }

    public function unlink(int $tenantId, int $actorId, string $key, int $documentId): void
    {
        $this->requireSchema();
        $this->pdo()->prepare('UPDATE doctrine_library_links SET unlinked_at = ?, updated_at = ? WHERE tenant_id = ? AND entry_key = ? AND document_id = ? AND unlinked_at IS NULL')
            ->execute([self::now(), self::now(), $tenantId, $key, $documentId]);
        $this->audit($tenantId, $actorId, 'unlinked', 'document', $documentId, ['key' => $key], []);
    }

    /**
     * Documents de doctrine de la communauté (pour « Rattacher ») : id, référence, titre, état.
     *
     * @return list<array{document_id: int, reference: string, title: string, status: string}>
     */
    public function doctrineDocuments(int $tenantId): array
    {
        if ($tenantId < 1) {
            return [];
        }
        try {
            $rows = $this->rows('SELECT dd.document_id, dd.reference_code, dd.doctrine_status, d.title FROM document_doctrines dd
                INNER JOIN documents d ON d.id = dd.document_id WHERE dd.tenant_id = ? ORDER BY d.title, dd.document_id', [$tenantId]);
        } catch (Throwable) {
            return [];
        }

        return array_map(static fn (array $r): array => [
            'document_id' => (int) $r['document_id'],
            'reference' => (string) ($r['reference_code'] ?? ''),
            'title' => (string) ($r['title'] ?? ''),
            'status' => (string) ($r['doctrine_status'] ?? ''),
        ], $rows);
    }

    private function documentBelongsTo(int $tenantId, int $documentId): bool
    {
        if ($documentId < 1) {
            return false;
        }
        try {
            return $this->rows('SELECT 1 FROM document_doctrines WHERE tenant_id = ? AND document_id = ?', [$tenantId, $documentId]) !== [];
        } catch (Throwable) {
            return false;
        }
    }

    /** @param array<string, mixed> $before @param array<string, mixed> $after */
    private function audit(int $tenantId, int $actorId, string $operation, string $entity, int $id, array $before, array $after): void
    {
        try {
            $this->pdo()->prepare('INSERT INTO audit_logs (tenant_id,user_id,action,entity_type,entity_id,old_value,new_value,created_at) VALUES (?,?,?,?,?,?,?,CURRENT_TIMESTAMP)')
                ->execute([$tenantId, $actorId ?: null, 'doctrine.library_' . $operation, $entity, $id,
                    $before === [] ? null : json_encode($before, JSON_UNESCAPED_UNICODE), $after === [] ? null : json_encode($after, JSON_UNESCAPED_UNICODE)]);
        } catch (Throwable $e) {
            error_log('Doctrine library audit: ' . $e->getMessage());
        }
    }
}
