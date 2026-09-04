<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SqlText;
use PDO;

/**
 * Tags partagés entre formations (training_courses) et Documentations HTML
 * (training_formation_custom_pages), liaison polymorphe via content_tag_links.
 */
final class ContentTagRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    private function slugify(string $name): string
    {
        $s = strtolower(trim($name));
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';

        return trim(substr($s, 0, 80), '-');
    }

    /** @return list<array{id:int,name:string,slug:string}> */
    public function listForTenant(int $tenantId): array
    {
        $st = $this->pdo->prepare('SELECT id, name, slug FROM content_tags WHERE tenant_id = ? ORDER BY name ASC');
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array{id:int,name:string,slug:string}> */
    public function listForContent(int $tenantId, string $contentType, int $contentId): array
    {
        $st = $this->pdo->prepare(
            'SELECT t.id, t.name, t.slug
             FROM content_tags t
             INNER JOIN content_tag_links l ON l.tag_id = t.id
             WHERE t.tenant_id = ? AND l.content_type = ? AND l.content_id = ?
             ORDER BY t.name ASC'
        );
        $st->execute([$tenantId, $contentType, $contentId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Résout une liste de noms de tags libres (texte séparé par virgules côté appelant) vers
     * des tags existants ou nouvellement créés pour ce tenant.
     *
     * @param list<string> $names
     * @return list<int> ids des tags
     */
    private function findOrCreateByNames(int $tenantId, array $names): array
    {
        $ids = [];
        foreach ($names as $name) {
            $name = mb_substr(trim($name), 0, 60);
            if ($name === '') {
                continue;
            }
            $slug = $this->slugify($name);
            if ($slug === '') {
                continue;
            }
            $slugEq = SqlText::equals($this->pdo, 'slug');
            $st = $this->pdo->prepare('SELECT id FROM content_tags WHERE tenant_id = ? AND ' . $slugEq . ' LIMIT 1');
            $st->execute([$tenantId, $slug]);
            $id = (int) $st->fetchColumn();
            if ($id < 1) {
                $ins = $this->pdo->prepare('INSERT INTO content_tags (tenant_id, name, slug) VALUES (?, ?, ?)');
                $ins->execute([$tenantId, $name, $slug]);
                $id = (int) $this->pdo->lastInsertId();
            }
            $ids[] = $id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * Remplace l'ensemble des tags attachés à un contenu par la liste fournie (texte libre,
     * séparé par virgules).
     */
    public function setTagsFromCommaText(int $tenantId, string $contentType, int $contentId, string $commaSeparated): void
    {
        $names = array_filter(array_map('trim', explode(',', $commaSeparated)), static fn (string $n): bool => $n !== '');
        $tagIds = $this->findOrCreateByNames($tenantId, array_values($names));

        $del = $this->pdo->prepare('DELETE FROM content_tag_links WHERE content_type = ? AND content_id = ?');
        $del->execute([$contentType, $contentId]);

        if ($tagIds === []) {
            return;
        }
        $ins = $this->pdo->prepare('INSERT IGNORE INTO content_tag_links (tag_id, content_type, content_id) VALUES (?, ?, ?)');
        foreach ($tagIds as $tagId) {
            $ins->execute([$tagId, $contentType, $contentId]);
        }
    }

    /** @return list<int> content_id des contenus portant ce tag (par slug) */
    public function listContentIdsForTagSlug(int $tenantId, string $contentType, string $tagSlug): array
    {
        $slugEq = SqlText::equals($this->pdo, 't.slug');
        $typeEq = SqlText::equals($this->pdo, 'l.content_type');
        $st = $this->pdo->prepare(
            'SELECT l.content_id
             FROM content_tag_links l
             INNER JOIN content_tags t ON t.id = l.tag_id
             WHERE t.tenant_id = ? AND ' . $slugEq . ' AND ' . $typeEq
        );
        $st->execute([$tenantId, $tagSlug, $contentType]);

        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /**
     * Tags de plusieurs contenus en une requête (évite le N+1 sur les listes).
     *
     * @param list<int> $contentIds
     * @return array<int, list<array{id:int,name:string,slug:string}>> indexé par content_id
     */
    public function listForContentIds(string $contentType, array $contentIds): array
    {
        $contentIds = array_values(array_unique(array_filter(array_map('intval', $contentIds), static fn (int $v): bool => $v > 0)));
        if ($contentIds === []) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($contentIds), '?'));
        $st = $this->pdo->prepare(
            "SELECT l.content_id, t.id, t.name, t.slug
             FROM content_tag_links l
             INNER JOIN content_tags t ON t.id = l.tag_id
             WHERE l.content_type = ? AND l.content_id IN ({$ph})
             ORDER BY t.name ASC"
        );
        $st->execute([$contentType, ...$contentIds]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $cid = (int) $row['content_id'];
            $out[$cid][] = ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'slug' => (string) $row['slug']];
        }

        return $out;
    }
}
