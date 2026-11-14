<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Outils de travail de l'espace d'une unité JNET : tâches, pages écrites ensemble, réactions, commentaires et votes de
 * sondage. Tolère l'absence des tables (étape jnet_unit_workspace non passée) : lectures vides, écritures refusées.
 * Toutes les requêtes sont bornées par communauté et par unité.
 */
class JnetUnitWorkspaceRepository
{
    public const TASK_STATES = ['todo', 'doing', 'done'];

    private ?bool $ready = null;

    public function __construct(private ?PDO $pdo = null)
    {
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    public function ready(): bool
    {
        if ($this->ready !== null) {
            return $this->ready;
        }
        try {
            foreach (['jnet_unit_tasks', 'jnet_unit_pages', 'jnet_unit_page_reactions', 'jnet_unit_page_comments', 'jnet_unit_page_votes'] as $table) {
                $this->pdo()->query('SELECT 1 FROM ' . $table . ' LIMIT 1');
            }
            $this->ready = true;
        } catch (\Throwable) {
            $this->ready = false;
        }

        return $this->ready;
    }

    // ——— Tâches ———

    /** @return list<array<string, mixed>> */
    public function tasks(int $tenantId, int $unitId): array
    {
        if (!$this->ready()) {
            return [];
        }
        $st = $this->pdo()->prepare(
            "SELECT t.id, t.title, t.status, t.assignee_user_id, t.created_by,
                    COALESCE(NULLIF(a.callsign, ''), a.display_name) AS assignee_label
             FROM jnet_unit_tasks t
             LEFT JOIN users a ON a.id = t.assignee_user_id
             WHERE t.tenant_id = ? AND t.unit_id = ?
             ORDER BY t.id DESC
             LIMIT 300"
        );
        $st->execute([$tenantId, $unitId]);

        return array_map(static function (array $r): array {
            $r['id'] = (int) $r['id'];
            $r['assignee_user_id'] = $r['assignee_user_id'] !== null ? (int) $r['assignee_user_id'] : null;
            $r['created_by'] = (int) $r['created_by'];

            return $r;
        }, $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /** @return array<string, mixed>|null */
    public function task(int $tenantId, int $unitId, int $taskId): ?array
    {
        if (!$this->ready()) {
            return null;
        }
        $st = $this->pdo()->prepare('SELECT id, title, status, assignee_user_id, created_by FROM jnet_unit_tasks WHERE tenant_id = ? AND unit_id = ? AND id = ?');
        $st->execute([$tenantId, $unitId, $taskId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function addTask(int $tenantId, int $unitId, int $userId, string $title, string $status, ?int $assignee): int
    {
        $this->pdo()->prepare(
            'INSERT INTO jnet_unit_tasks (tenant_id, unit_id, title, status, assignee_user_id, created_by) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$tenantId, $unitId, $title, $status, $assignee, $userId]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function setTaskStatus(int $tenantId, int $unitId, int $taskId, string $status): void
    {
        $this->pdo()->prepare('UPDATE jnet_unit_tasks SET status = ? WHERE tenant_id = ? AND unit_id = ? AND id = ?')
            ->execute([$status, $tenantId, $unitId, $taskId]);
    }

    public function setTaskAssignee(int $tenantId, int $unitId, int $taskId, ?int $assignee): void
    {
        $this->pdo()->prepare('UPDATE jnet_unit_tasks SET assignee_user_id = ? WHERE tenant_id = ? AND unit_id = ? AND id = ?')
            ->execute([$assignee, $tenantId, $unitId, $taskId]);
    }

    public function deleteTask(int $tenantId, int $unitId, int $taskId): void
    {
        $this->pdo()->prepare('DELETE FROM jnet_unit_tasks WHERE tenant_id = ? AND unit_id = ? AND id = ?')
            ->execute([$tenantId, $unitId, $taskId]);
    }

    // ——— Pages ———

    /** @return list<array<string, mixed>> */
    public function pages(int $tenantId, int $unitId): array
    {
        if (!$this->ready()) {
            return [];
        }
        $st = $this->pdo()->prepare(
            "SELECT p.id, p.title, p.blocks, p.updated_at, p.updated_by,
                    COALESCE(NULLIF(u.callsign, ''), u.display_name) AS updated_by_label,
                    (SELECT COUNT(*) FROM jnet_unit_page_comments c WHERE c.page_id = p.id) AS comment_count
             FROM jnet_unit_pages p
             LEFT JOIN users u ON u.id = p.updated_by
             WHERE p.tenant_id = ? AND p.unit_id = ? AND p.deleted_at IS NULL
             ORDER BY p.sort_order, p.id
             LIMIT 200"
        );
        $st->execute([$tenantId, $unitId]);

        return array_map(static function (array $r): array {
            $r['id'] = (int) $r['id'];
            $r['comment_count'] = (int) $r['comment_count'];

            return $r;
        }, $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /** @return array<string, mixed>|null */
    public function page(int $tenantId, int $unitId, int $pageId): ?array
    {
        if (!$this->ready()) {
            return null;
        }
        $st = $this->pdo()->prepare(
            "SELECT p.id, p.title, p.blocks, p.created_by, p.updated_at, p.updated_by,
                    COALESCE(NULLIF(u.callsign, ''), u.display_name) AS updated_by_label
             FROM jnet_unit_pages p
             LEFT JOIN users u ON u.id = p.updated_by
             WHERE p.tenant_id = ? AND p.unit_id = ? AND p.id = ? AND p.deleted_at IS NULL"
        );
        $st->execute([$tenantId, $unitId, $pageId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $row['id'] = (int) $row['id'];
        $row['created_by'] = (int) $row['created_by'];

        return $row;
    }

    public function createPage(int $tenantId, int $unitId, int $userId, string $title, string $blocksJson): int
    {
        $pdo = $this->pdo();
        $next = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM jnet_unit_pages WHERE tenant_id = ? AND unit_id = ?');
        $next->execute([$tenantId, $unitId]);
        $pdo->prepare(
            'INSERT INTO jnet_unit_pages (tenant_id, unit_id, title, blocks, sort_order, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$tenantId, $unitId, $title, $blocksJson, (int) $next->fetchColumn(), $userId, $userId]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Enregistre une page si elle n'a pas été modifiée depuis son ouverture (même horodatage). Retourne false sinon.
     */
    public function savePage(int $tenantId, int $unitId, int $pageId, int $userId, string $title, string $blocksJson, string $expectedUpdatedAt): bool
    {
        $st = $this->pdo()->prepare(
            'UPDATE jnet_unit_pages SET title = ?, blocks = ?, updated_by = ?
             WHERE tenant_id = ? AND unit_id = ? AND id = ? AND deleted_at IS NULL AND updated_at = ?'
        );
        $st->execute([$title, $blocksJson, $userId, $tenantId, $unitId, $pageId, $expectedUpdatedAt]);

        return $st->rowCount() === 1;
    }

    /** Écrit les blocs sans contrôle de version (case cochée, vote : gestes courts qui ne perdent rien). */
    public function touchBlocks(int $tenantId, int $unitId, int $pageId, int $userId, string $blocksJson): void
    {
        $this->pdo()->prepare('UPDATE jnet_unit_pages SET blocks = ?, updated_by = ? WHERE tenant_id = ? AND unit_id = ? AND id = ? AND deleted_at IS NULL')
            ->execute([$blocksJson, $userId, $tenantId, $unitId, $pageId]);
    }

    public function deletePage(int $tenantId, int $unitId, int $pageId): void
    {
        $this->pdo()->prepare('UPDATE jnet_unit_pages SET deleted_at = NOW() WHERE tenant_id = ? AND unit_id = ? AND id = ? AND deleted_at IS NULL')
            ->execute([$tenantId, $unitId, $pageId]);
    }

    /**
     * Réordonne les pages : la page prend la place de sa voisine ($direction -1 vers le haut, 1 vers le bas).
     */
    public function movePage(int $tenantId, int $unitId, int $pageId, int $direction): void
    {
        $ids = array_map(static fn (array $p): int => (int) $p['id'], $this->pages($tenantId, $unitId));
        $i = array_search($pageId, $ids, true);
        if ($i === false) {
            return;
        }
        $j = $i + ($direction < 0 ? -1 : 1);
        if (!isset($ids[$j])) {
            return;
        }
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        $up = $this->pdo()->prepare('UPDATE jnet_unit_pages SET sort_order = ? WHERE tenant_id = ? AND unit_id = ? AND id = ?');
        foreach ($ids as $order => $id) {
            $up->execute([$order + 1, $tenantId, $unitId, $id]);
        }
    }

    /**
     * Applique l'ordre donné aux pages de l'unité (glisser-déposer). Seules les pages de l'unité comptent : les
     * identifiants inconnus sont ignorés, les pages non citées gardent leur rang relatif, à la suite.
     *
     * @param list<int> $orderedIds
     */
    public function setPageOrder(int $tenantId, int $unitId, array $orderedIds): void
    {
        $current = array_map(static fn (array $p): int => (int) $p['id'], $this->pages($tenantId, $unitId));
        $first = array_values(array_filter(array_unique(array_map('intval', $orderedIds)), static fn (int $id): bool => in_array($id, $current, true)));
        $ids = array_merge($first, array_values(array_diff($current, $first)));
        $up = $this->pdo()->prepare('UPDATE jnet_unit_pages SET sort_order = ? WHERE tenant_id = ? AND unit_id = ? AND id = ? AND deleted_at IS NULL');
        foreach ($ids as $order => $id) {
            $up->execute([$order + 1, $tenantId, $unitId, $id]);
        }
    }

    /** Un fil de messagerie de même objet a-t-il été créé dans les dernières heures ? (rappels non répétés) */
    public function reminderSentRecently(int $tenantId, string $subject, int $hours): bool
    {
        try {
            $st = $this->pdo()->prepare('SELECT 1 FROM tenant_message_threads WHERE tenant_id = ? AND subject = ? AND created_at > (NOW() - INTERVAL ' . max(1, $hours) . ' HOUR) LIMIT 1');
            $st->execute([$tenantId, $subject]);

            return $st->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    // ——— Réactions, commentaires, votes ———

    /** @return array{counts: array<string,int>, mine: list<string>} */
    public function reactions(int $pageId, int $userId): array
    {
        $out = ['counts' => [], 'mine' => []];
        if (!$this->ready()) {
            return $out;
        }
        $st = $this->pdo()->prepare('SELECT kind, user_id FROM jnet_unit_page_reactions WHERE page_id = ?');
        $st->execute([$pageId]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $out['counts'][(string) $r['kind']] = ($out['counts'][(string) $r['kind']] ?? 0) + 1;
            if ((int) $r['user_id'] === $userId) {
                $out['mine'][] = (string) $r['kind'];
            }
        }

        return $out;
    }

    public function toggleReaction(int $pageId, int $userId, string $kind): void
    {
        $del = $this->pdo()->prepare('DELETE FROM jnet_unit_page_reactions WHERE page_id = ? AND user_id = ? AND kind = ?');
        $del->execute([$pageId, $userId, $kind]);
        if ($del->rowCount() === 0) {
            $this->pdo()->prepare('INSERT INTO jnet_unit_page_reactions (page_id, user_id, kind) VALUES (?, ?, ?)')->execute([$pageId, $userId, $kind]);
        }
    }

    /** @return list<array{id:int, user_id:int, label:string, body:string, created_at:string}> */
    public function comments(int $pageId): array
    {
        if (!$this->ready()) {
            return [];
        }
        $st = $this->pdo()->prepare(
            "SELECT c.id, c.user_id, c.body, c.created_at, COALESCE(NULLIF(u.callsign, ''), u.display_name) AS label
             FROM jnet_unit_page_comments c LEFT JOIN users u ON u.id = c.user_id
             WHERE c.page_id = ? ORDER BY c.id LIMIT 200"
        );
        $st->execute([$pageId]);

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'user_id' => (int) $r['user_id'],
            'label' => trim((string) ($r['label'] ?? '')) !== '' ? (string) $r['label'] : 'Membre',
            'body' => (string) $r['body'],
            'created_at' => (string) $r['created_at'],
        ], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function addComment(int $pageId, int $userId, string $body): void
    {
        $this->pdo()->prepare('INSERT INTO jnet_unit_page_comments (page_id, user_id, body) VALUES (?, ?, ?)')->execute([$pageId, $userId, $body]);
    }

    /** @return array<string, array<int, list<int>>> bloc => option => votants */
    public function votes(int $pageId): array
    {
        if (!$this->ready()) {
            return [];
        }
        $st = $this->pdo()->prepare('SELECT block_id, option_idx, user_id FROM jnet_unit_page_votes WHERE page_id = ?');
        $st->execute([$pageId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $out[(string) $r['block_id']][(int) $r['option_idx']][] = (int) $r['user_id'];
        }

        return $out;
    }

    /** Un vote par membre et par sondage ; revoter pour la même option retire le vote. */
    public function castVote(int $pageId, string $blockId, int $userId, int $option): void
    {
        $cur = $this->pdo()->prepare('SELECT option_idx FROM jnet_unit_page_votes WHERE page_id = ? AND block_id = ? AND user_id = ?');
        $cur->execute([$pageId, $blockId, $userId]);
        $had = $cur->fetchColumn();
        $this->pdo()->prepare('DELETE FROM jnet_unit_page_votes WHERE page_id = ? AND block_id = ? AND user_id = ?')->execute([$pageId, $blockId, $userId]);
        if ($had !== false && (int) $had === $option) {
            return;
        }
        $this->pdo()->prepare('INSERT INTO jnet_unit_page_votes (page_id, block_id, user_id, option_idx) VALUES (?, ?, ?, ?)')
            ->execute([$pageId, $blockId, $userId, $option]);
    }

    /** Membres de la communauté par identifiant : libellé court (indicatif, sinon nom affiché). @param list<int> $ids @return array<int,string> */
    public function userLabels(int $tenantId, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0)));
        if ($ids === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        try {
            $st = $this->pdo()->prepare("SELECT id, COALESCE(NULLIF(callsign, ''), display_name) AS label FROM users WHERE id IN ($in)");
            $st->execute($ids);
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $out[(int) $r['id']] = (string) $r['label'];
        }

        return $out;
    }
}
