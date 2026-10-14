<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Données du hub inter-communautés : recommandations, avis après coopération et bourse aux coopérations.
 * Toutes les lectures tolèrent l’absence des tables (migration pas encore passée) en renvoyant du vide.
 */
class CommunityEcosystemRepository
{
    public const NEED_TYPES = [
        'renfort' => 'Renfort de joueurs',
        'adversaire' => 'Force adverse (OPFOR)',
        'aviation' => 'Appui aérien / hélicoptères',
        'appui' => 'Appui (feux, génie, santé, logistique)',
        'entrainement' => 'Entraînement commun',
        'operation' => 'Opération conjointe',
        'evenement' => 'Événement ou campagne',
        'autre' => 'Autre besoin',
    ];

    private PDO $pdo;

    /** @var array<string, bool> */
    private array $tables = [];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    public function tableExists(string $table): bool
    {
        if (!isset($this->tables[$table])) {
            $st = $this->pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
            $st->execute([$table]);
            $this->tables[$table] = (bool) $st->fetchColumn();
        }

        return $this->tables[$table];
    }

    // ——— Recommandations ———

    /** @return array<int, int> tenant_id => nombre de recommandations */
    public function recommendationCounts(): array
    {
        if (!$this->tableExists('tenant_recommendations')) {
            return [];
        }
        $out = [];
        foreach ($this->pdo->query('SELECT tenant_id, COUNT(*) n FROM tenant_recommendations GROUP BY tenant_id')->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['tenant_id']] = (int) $r['n'];
        }

        return $out;
    }

    /** @return list<int> communautés recommandées par ce compte */
    public function recommendedByUser(int $userId): array
    {
        if ($userId < 1 || !$this->tableExists('tenant_recommendations')) {
            return [];
        }
        $st = $this->pdo->prepare('SELECT tenant_id FROM tenant_recommendations WHERE user_id = ?');
        $st->execute([$userId]);

        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Ajoute ou retire la recommandation ; renvoie l’état final (true = recommandée). */
    public function toggleRecommendation(int $tenantId, int $userId, ?int $voterTenantId): bool
    {
        if (!$this->tableExists('tenant_recommendations')) {
            return false;
        }
        $del = $this->pdo->prepare('DELETE FROM tenant_recommendations WHERE tenant_id = ? AND user_id = ?');
        $del->execute([$tenantId, $userId]);
        if ($del->rowCount() > 0) {
            return false;
        }
        $ins = $this->pdo->prepare('INSERT IGNORE INTO tenant_recommendations (tenant_id, user_id, voter_tenant_id) VALUES (?, ?, ?)');
        $ins->execute([$tenantId, $userId, $voterTenantId]);

        return true;
    }

    // ——— Avis après coopération ———

    /** @return array<int, array{avg: float, count: int, reliability: ?float, organisation: ?float, ambiance: ?float}> */
    public function reviewSummaries(): array
    {
        if (!$this->tableExists('cooperation_reviews')) {
            return [];
        }
        $out = [];
        $rows = $this->pdo->query(
            'SELECT reviewed_tenant_id t, AVG(rating) a, COUNT(*) n, AVG(reliability) r, AVG(organisation) o, AVG(ambiance) m
             FROM cooperation_reviews GROUP BY reviewed_tenant_id'
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $out[(int) $r['t']] = [
                'avg' => round((float) $r['a'], 1),
                'count' => (int) $r['n'],
                'reliability' => $r['r'] !== null ? round((float) $r['r'], 1) : null,
                'organisation' => $r['o'] !== null ? round((float) $r['o'], 1) : null,
                'ambiance' => $r['m'] !== null ? round((float) $r['m'], 1) : null,
            ];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> avis reçus par une communauté, du plus récent au plus ancien */
    public function reviewsFor(int $tenantId, int $limit = 20): array
    {
        if (!$this->tableExists('cooperation_reviews')) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT r.rating, r.reliability, r.organisation, r.ambiance, r.comment, r.created_at,
                    m.title AS mission_title, t.name AS reviewer_name, t.slug AS reviewer_slug
             FROM cooperation_reviews r
             JOIN interteam_missions m ON m.id = r.mission_id
             JOIN tenants t ON t.id = r.reviewer_tenant_id
             WHERE r.reviewed_tenant_id = ?
             ORDER BY r.created_at DESC LIMIT ' . max(1, min(100, $limit))
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Partenaires que la communauté peut évaluer : coopérations clôturées (hors annulation) auxquelles
     * les deux ont réellement pris part, et pas encore évaluées par elle.
     *
     * @return list<array{mission_id: int, mission_title: string, tenant_id: int, tenant_name: string, closed_at: ?string}>
     */
    public function pendingReviews(int $tenantId): array
    {
        if ($tenantId < 1 || !$this->tableExists('cooperation_reviews') || !$this->tableExists('interteam_mission_participants')) {
            return [];
        }
        $phaseFilter = $this->columnExists('interteam_missions', 'cooperation_phase')
            ? " AND COALESCE(m.cooperation_phase, '') <> 'cancelled'"
            : '';
        $st = $this->pdo->prepare(
            "SELECT m.id AS mission_id, m.title AS mission_title, m.updated_at AS closed_at, t.id AS tenant_id, t.name AS tenant_name
             FROM interteam_missions m
             JOIN (
                 SELECT mission_id, tenant_id FROM interteam_mission_participants WHERE status = 'active'
                 UNION SELECT id, created_by_tenant_id FROM interteam_missions
             ) other ON other.mission_id = m.id AND other.tenant_id <> :me
             JOIN tenants t ON t.id = other.tenant_id
             WHERE m.status = 'archived'{$phaseFilter}
               AND (m.created_by_tenant_id = :me2 OR EXISTS (
                   SELECT 1 FROM interteam_mission_participants p
                   WHERE p.mission_id = m.id AND p.tenant_id = :me3 AND p.status = 'active'))
               AND NOT EXISTS (
                   SELECT 1 FROM cooperation_reviews r
                   WHERE r.mission_id = m.id AND r.reviewer_tenant_id = :me4 AND r.reviewed_tenant_id = other.tenant_id)
             ORDER BY m.updated_at DESC LIMIT 20"
        );
        $st->execute(['me' => $tenantId, 'me2' => $tenantId, 'me3' => $tenantId, 'me4' => $tenantId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $out[] = [
                'mission_id' => (int) $r['mission_id'],
                'mission_title' => (string) $r['mission_title'],
                'tenant_id' => (int) $r['tenant_id'],
                'tenant_name' => (string) $r['tenant_name'],
                'closed_at' => $r['closed_at'] !== null ? (string) $r['closed_at'] : null,
            ];
        }

        return $out;
    }

    public function canReview(int $missionId, int $reviewerTenantId, int $reviewedTenantId): bool
    {
        foreach ($this->pendingReviews($reviewerTenantId) as $p) {
            if ($p['mission_id'] === $missionId && $p['tenant_id'] === $reviewedTenantId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{rating: int, reliability: ?int, organisation: ?int, ambiance: ?int, comment: string} $review
     */
    public function saveReview(int $missionId, int $reviewerTenantId, int $reviewedTenantId, int $userId, array $review): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO cooperation_reviews
                (mission_id, reviewer_tenant_id, reviewed_tenant_id, reviewer_user_id, rating, reliability, organisation, ambiance, comment)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE rating = VALUES(rating), reliability = VALUES(reliability),
                organisation = VALUES(organisation), ambiance = VALUES(ambiance), comment = VALUES(comment),
                reviewer_user_id = VALUES(reviewer_user_id)'
        );
        $st->execute([
            $missionId, $reviewerTenantId, $reviewedTenantId, $userId,
            $review['rating'], $review['reliability'], $review['organisation'], $review['ambiance'],
            $review['comment'] !== '' ? $review['comment'] : null,
        ]);
    }

    // ——— Bourse aux coopérations ———

    /**
     * @param array{title: string, need_type: string, game_label: string, starts_at: ?string, slots: ?int, details: string} $post
     */
    public function createPost(int $tenantId, int $userId, array $post): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO cooperation_board_posts (tenant_id, user_id, title, need_type, game_label, starts_at, slots, details)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $tenantId, $userId, $post['title'], $post['need_type'],
            $post['game_label'] !== '' ? $post['game_label'] : null,
            $post['starts_at'], $post['slots'],
            $post['details'] !== '' ? $post['details'] : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function listPosts(string $scope = 'open', int $limit = 60): array
    {
        if (!$this->tableExists('cooperation_board_posts')) {
            return [];
        }
        $where = $scope === 'all' ? '1 = 1' : "p.status = 'open' AND (p.starts_at IS NULL OR p.starts_at >= DATE_SUB(NOW(), INTERVAL 1 DAY))";
        $hasResp = $this->tableExists('cooperation_board_responses');
        $responses = $hasResp ? '(SELECT COUNT(*) FROM cooperation_board_responses r WHERE r.post_id = p.id)' : '0';
        // Joueurs proposés (hors propositions déclinées) : alimente la jauge de renfort.
        $offered = $hasResp ? "(SELECT COALESCE(SUM(r.slots), 0) FROM cooperation_board_responses r WHERE r.post_id = p.id AND r.status <> 'declined')" : '0';
        $logo = $this->columnExists('tenants', 'logo_url') ? 't.logo_url' : 'NULL';
        $st = $this->pdo->query(
            "SELECT p.*, t.name AS tenant_name, t.slug AS tenant_slug, {$logo} AS tenant_logo,
                {$responses} AS responses_count, {$offered} AS offered_slots
             FROM cooperation_board_posts p JOIN tenants t ON t.id = p.tenant_id
             WHERE {$where}
             ORDER BY (p.starts_at IS NULL), p.starts_at ASC, p.created_at DESC
             LIMIT " . max(1, min(200, $limit))
        );

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> besoins publiés par la communauté, avec leurs réponses */
    public function postsOfTenant(int $tenantId): array
    {
        if (!$this->tableExists('cooperation_board_posts')) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT p.* FROM cooperation_board_posts p WHERE p.tenant_id = ? ORDER BY p.created_at DESC LIMIT 30'
        );
        $st->execute([$tenantId]);
        $posts = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($posts as &$post) {
            $post['responses'] = $this->responsesFor((int) $post['id']);
        }
        unset($post);

        return $posts;
    }

    /** @return array<string, mixed>|null */
    public function findPost(int $postId): ?array
    {
        if (!$this->tableExists('cooperation_board_posts')) {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT p.*, t.name AS tenant_name, t.slug AS tenant_slug FROM cooperation_board_posts p
             JOIN tenants t ON t.id = p.tenant_id WHERE p.id = ? LIMIT 1'
        );
        $st->execute([$postId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function responsesFor(int $postId): array
    {
        if (!$this->tableExists('cooperation_board_responses')) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT r.*, t.name AS tenant_name, t.slug AS tenant_slug FROM cooperation_board_responses r
             JOIN tenants t ON t.id = r.tenant_id WHERE r.post_id = ? ORDER BY r.created_at ASC'
        );
        $st->execute([$postId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<int, string> post_id => statut de la réponse de cette communauté */
    public function responseStatusesOfTenant(int $tenantId): array
    {
        if ($tenantId < 1 || !$this->tableExists('cooperation_board_responses')) {
            return [];
        }
        $st = $this->pdo->prepare('SELECT post_id, status FROM cooperation_board_responses WHERE tenant_id = ?');
        $st->execute([$tenantId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $out[(int) $r['post_id']] = (string) $r['status'];
        }

        return $out;
    }

    public function respond(int $postId, int $tenantId, int $userId, string $message, ?int $slots): void
    {
        $st = $this->pdo->prepare(
            "INSERT INTO cooperation_board_responses (post_id, tenant_id, user_id, message, slots, status)
             VALUES (?, ?, ?, ?, ?, 'pending')
             ON DUPLICATE KEY UPDATE message = VALUES(message), slots = VALUES(slots), user_id = VALUES(user_id),
                status = IF(status = 'declined', 'pending', status)"
        );
        $st->execute([$postId, $tenantId, $userId, $message !== '' ? $message : null, $slots]);
    }

    public function withdrawResponse(int $postId, int $tenantId): void
    {
        $st = $this->pdo->prepare("DELETE FROM cooperation_board_responses WHERE post_id = ? AND tenant_id = ? AND status = 'pending'");
        $st->execute([$postId, $tenantId]);
    }

    /** @return array<string, mixed>|null */
    public function findResponse(int $responseId): ?array
    {
        if (!$this->tableExists('cooperation_board_responses')) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT * FROM cooperation_board_responses WHERE id = ? LIMIT 1');
        $st->execute([$responseId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function setResponseStatus(int $responseId, string $status): void
    {
        $st = $this->pdo->prepare('UPDATE cooperation_board_responses SET status = ? WHERE id = ?');
        $st->execute([$status, $responseId]);
    }

    public function setPostStatus(int $postId, string $status): void
    {
        $st = $this->pdo->prepare('UPDATE cooperation_board_posts SET status = ? WHERE id = ?');
        $st->execute([$status, $postId]);
    }

    private function columnExists(string $table, string $column): bool
    {
        $st = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    }
}
