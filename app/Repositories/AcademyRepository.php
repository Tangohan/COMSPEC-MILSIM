<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Services\Academy\AcademyCurriculum;
use App\Services\Academy\AcademyBuiltInCourses;
use PDO;

final class AcademyRepository
{
    public const TRASH_DAYS = 30;
    /** Champs calculés à la lecture, jamais enregistrés dans le contenu. */
    private const META = ['key', 'published', 'demo', 'built_in', 'updated_at', 'archived', 'deleted_at', 'learners'];

    private PDO $pdo;
    private ?bool $lifecycle = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    /** Colonnes archived_at / deleted_at posées par l'étape academy_course_lifecycle_sharing (3270). */
    public function lifecycleReady(): bool
    {
        if ($this->lifecycle === null) {
            try {
                $this->pdo->query('SELECT archived_at, deleted_at FROM academy_courses WHERE 1 = 0');
                $this->lifecycle = true;
            } catch (\PDOException) {
                $this->lifecycle = false;
            }
        }
        return $this->lifecycle;
    }

    private function active(): string
    {
        return $this->lifecycleReady() ? ' AND archived_at IS NULL AND deleted_at IS NULL' : '';
    }

    public function courses(int $tenantId, bool $drafts = false): array
    {
        if ($tenantId < 1) {
            return [];
        }
        // Formations archivées ou à la corbeille : hors catalogue ; les inscrits gardent leur copie du contenu.
        $stmt = $this->pdo->prepare('SELECT course_key, content_json, published FROM academy_courses WHERE tenant_id = ?' . ($drafts ? '' : ' AND published = 1') . $this->active() . ' ORDER BY updated_at DESC');
        $stmt->execute([$tenantId]);
        // Published Academy modules are versioned with the application. No migration or
        // request-time seed is needed; learners still keep a private tenant-scoped snapshot.
        $courses = array_values(AcademyBuiltInCourses::all());
        $reserved = array_fill_keys(array_column($courses, 'key'), true);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (isset($reserved[$row['course_key']])) {
                continue;
            }
            $content = json_decode($row['content_json'], true, 64, JSON_THROW_ON_ERROR);
            $content['key'] = $row['course_key'];
            $content['published'] = (bool) $row['published'];
            $courses[] = $content;
        }
        return $courses;
    }

    public function course(int $tenantId, string $key): ?array
    {
        foreach ($this->courses($tenantId) as $course) {
            if ($course['key'] === $key) {
                return $course;
            }
        }
        return null;
    }

    public function saveCourse(int $tenantId, int $userId, array $content, bool $published): void
    {
        AcademyCurriculum::validate($content);
        // Always create a new revision. Existing enrollments retain their content snapshot.
        $key = 'course-' . bin2hex(random_bytes(8));
        $content['key'] = $key;
        unset($content['demo'], $content['built_in']);
        $stmt = $this->pdo->prepare('INSERT INTO academy_courses (tenant_id, course_key, content_json, published, updated_by) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$tenantId, $key, json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), (int) $published, $userId]);
    }

    public function publish(int $tenantId, string $key): void
    {
        $stmt = $this->pdo->prepare('UPDATE academy_courses SET published = 1 WHERE tenant_id = ? AND course_key = ?');
        $stmt->execute([$tenantId, $key]);
    }

    /** Cours de la communauté, brouillon compris (jamais les modules intégrés à l'application). */
    public function editable(int $tenantId, string $key): ?array
    {
        $lifecycle = $this->lifecycleReady();
        $stmt = $this->pdo->prepare('SELECT content_json, published' . ($lifecycle ? ', archived_at' : '') . ' FROM academy_courses WHERE tenant_id = ? AND course_key = ?' . ($lifecycle ? ' AND deleted_at IS NULL' : ''));
        $stmt->execute([$tenantId, $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $content = json_decode($row['content_json'], true, 64, JSON_THROW_ON_ERROR);
        $content['key'] = $key;
        $content['published'] = (bool) $row['published'];
        $content['archived'] = ($row['archived_at'] ?? null) !== null;
        return $content;
    }

    /** Stagiaires inscrits (leur progression et leurs licences dépendent de la formation). */
    public function learnerCount(int $tenantId, string $key): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM academy_learners WHERE tenant_id = ? AND course_key = ?');
        $stmt->execute([$tenantId, $key]);
        return (int) $stmt->fetchColumn();
    }

    /** Archiver / restaurer : la formation quitte le catalogue et le Studio actif, les inscrits gardent leur version. */
    public function setArchived(int $tenantId, string $key, bool $archived): bool
    {
        $stmt = $this->pdo->prepare('UPDATE academy_courses SET archived_at = ? WHERE tenant_id = ? AND course_key = ? AND deleted_at IS NULL');
        $stmt->execute([$archived ? date('Y-m-d H:i:s') : null, $tenantId, $key]);
        return $stmt->rowCount() > 0;
    }

    /** Corbeille : seule une formation sans inscrit s'y place ; elle reste restaurable TRASH_DAYS jours. */
    public function trash(int $tenantId, string $key): bool
    {
        if ($this->learnerCount($tenantId, $key) > 0) {
            throw new \DomainException('has_learners');
        }
        $stmt = $this->pdo->prepare('UPDATE academy_courses SET deleted_at = ? WHERE tenant_id = ? AND course_key = ? AND deleted_at IS NULL');
        $stmt->execute([date('Y-m-d H:i:s'), $tenantId, $key]);
        return $stmt->rowCount() > 0;
    }

    public function restoreFromTrash(int $tenantId, string $key): bool
    {
        $stmt = $this->pdo->prepare('UPDATE academy_courses SET deleted_at = NULL WHERE tenant_id = ? AND course_key = ? AND deleted_at IS NOT NULL');
        $stmt->execute([$tenantId, $key]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Supprime définitivement ce qui est à la corbeille depuis plus de TRASH_DAYS jours.
     *
     * @return list<string> clés supprimées (pour retirer leurs partages)
     */
    public function purgeTrash(int $tenantId, ?int $now = null): array
    {
        $cutoff = date('Y-m-d H:i:s', ($now ?? time()) - self::TRASH_DAYS * 86400);
        $stmt = $this->pdo->prepare('SELECT course_key FROM academy_courses WHERE tenant_id = ? AND deleted_at IS NOT NULL AND deleted_at < ?');
        $stmt->execute([$tenantId, $cutoff]);
        $keys = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        if ($keys !== []) {
            $stmt = $this->pdo->prepare('DELETE FROM academy_courses WHERE tenant_id = ? AND deleted_at IS NOT NULL AND deleted_at < ?');
            $stmt->execute([$tenantId, $cutoff]);
        }
        return $keys;
    }

    /**
     * Accueil du Studio : cours de la communauté (brouillons compris) avec leur date de modification.
     *
     * @return list<array<string,mixed>>
     */
    public function studioList(int $tenantId): array
    {
        $lifecycle = $this->lifecycleReady();
        $stmt = $this->pdo->prepare('SELECT c.course_key, c.content_json, c.published, c.updated_at' . ($lifecycle ? ', c.archived_at, c.deleted_at' : '')
            . ', (SELECT COUNT(*) FROM academy_learners l WHERE l.tenant_id = c.tenant_id AND l.course_key = c.course_key) AS learners'
            . ' FROM academy_courses c WHERE c.tenant_id = ? ORDER BY c.updated_at DESC');
        $stmt->execute([$tenantId]);
        $list = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $content = json_decode($row['content_json'], true, 64, JSON_THROW_ON_ERROR);
            $content['key'] = $row['course_key'];
            $content['published'] = (bool) $row['published'];
            $content['updated_at'] = (string) $row['updated_at'];
            $content['archived'] = ($row['archived_at'] ?? null) !== null;
            $content['deleted_at'] = $row['deleted_at'] ?? null;
            $content['learners'] = (int) $row['learners'];
            $list[] = $content;
        }
        return $list;
    }

    /** Brouillon du Studio : il peut être incomplet, la publication le valide. */
    public function createDraft(int $tenantId, int $userId, array $content): string
    {
        $key = 'course-' . bin2hex(random_bytes(8));
        $content = array_diff_key($content, array_flip(self::META));
        $stmt = $this->pdo->prepare('INSERT INTO academy_courses (tenant_id, course_key, content_json, published, updated_by) VALUES (?, ?, ?, 0, ?)');
        $stmt->execute([$tenantId, $key, json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $userId]);
        return $key;
    }

    /** Seul un brouillon se modifie : un cours publié change par une nouvelle version. */
    public function updateDraft(int $tenantId, string $key, int $userId, array $content): bool
    {
        $content = array_diff_key($content, array_flip(self::META));
        $stmt = $this->pdo->prepare('UPDATE academy_courses SET content_json = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE tenant_id = ? AND course_key = ? AND published = 0');
        $stmt->execute([json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $userId, $tenantId, $key]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Publie un brouillon complet. Une nouvelle version retire l'ancienne du catalogue ;
     * les stagiaires déjà inscrits gardent leur copie du contenu.
     */
    public function publishDraft(int $tenantId, string $key): void
    {
        $course = $this->editable($tenantId, $key);
        if ($course === null || $course['published']) {
            throw new \DomainException('locked');
        }
        AcademyCurriculum::validate($course);
        $this->pdo->beginTransaction();
        try {
            $this->publish($tenantId, $key);
            if (isset($course['replaces']) && is_string($course['replaces']) && $course['replaces'] !== $key) {
                $stmt = $this->pdo->prepare('UPDATE academy_courses SET published = 0 WHERE tenant_id = ? AND course_key = ?');
                $stmt->execute([$tenantId, $course['replaces']]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function enroll(int $tenantId, int $userId, array $course): void
    {
        $stmt = $this->pdo->prepare('INSERT IGNORE INTO academy_learners (tenant_id, user_id, course_key, state_json) VALUES (?, ?, ?, ?)');
        $stmt->execute([$tenantId, $userId, $course['key'], json_encode(['chapter' => 0, 'stage' => 0, 'tenant_id' => $tenantId, 'learner_id' => $userId, 'snapshot' => $course, 'enrolled' => time(), 'updated' => time()], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
    }

    public function states(int $tenantId, int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT course_key, state_json FROM academy_learners WHERE tenant_id = ? AND user_id = ? ORDER BY updated_at DESC');
        $stmt->execute([$tenantId, $userId]);
        $states = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $states[$row['course_key']] = AcademyBuiltInCourses::refreshSnapshot(json_decode($row['state_json'], true, 64, JSON_THROW_ON_ERROR), (string) $row['course_key']);
        }
        return $states;
    }

    /** Serialize state mutations: concurrent submits cannot replay an exam or skip a stage. */
    public function mutate(int $tenantId, int $userId, string $key, callable $change): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT state_json FROM academy_learners WHERE tenant_id = ? AND user_id = ? AND course_key = ? FOR UPDATE');
            $stmt->execute([$tenantId, $userId, $key]);
            $json = $stmt->fetchColumn();
            if ($json === false) {
                throw new \DomainException('not_enrolled');
            }
            $state = $change(AcademyBuiltInCourses::refreshSnapshot(json_decode($json, true, 64, JSON_THROW_ON_ERROR), $key));
            $stmt = $this->pdo->prepare('UPDATE academy_learners SET state_json = ?, updated_at = CURRENT_TIMESTAMP WHERE tenant_id = ? AND user_id = ? AND course_key = ?');
            $stmt->execute([json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $tenantId, $userId, $key]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Passage d’un parcours à la version courante du cours (AcademyVersionUpgrade). Une nouvelle version du Studio a
     * une autre clé : l’inscription la rejoint, sauf si le stagiaire y est déjà inscrit (« already_enrolled »).
     *
     * @param callable(array<string, mixed>): array<string, mixed> $change
     */
    public function moveEnrollment(int $tenantId, int $userId, string $key, string $newKey, callable $change): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT state_json FROM academy_learners WHERE tenant_id = ? AND user_id = ? AND course_key = ? FOR UPDATE');
            $stmt->execute([$tenantId, $userId, $key]);
            $json = $stmt->fetchColumn();
            if ($json === false) {
                throw new \DomainException('not_enrolled');
            }
            if ($newKey !== $key) {
                $stmt->execute([$tenantId, $userId, $newKey]);
                if ($stmt->fetchColumn() !== false) {
                    throw new \DomainException('already_enrolled');
                }
            }
            $state = $change(json_decode($json, true, 64, JSON_THROW_ON_ERROR));
            $stmt = $this->pdo->prepare('UPDATE academy_learners SET course_key = ?, state_json = ?, updated_at = CURRENT_TIMESTAMP WHERE tenant_id = ? AND user_id = ? AND course_key = ?');
            $stmt->execute([$newKey, json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $tenantId, $userId, $key]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function saveNotes(int $tenantId, int $userId, string $text): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO academy_learners (tenant_id, user_id, course_key, state_json) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE state_json = VALUES(state_json), updated_at = CURRENT_TIMESTAMP');
        $stmt->execute([$tenantId, $userId, '__notes', json_encode(['text' => $text], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
    }

    /**
     * Lecture légère pour le badge « à traiter » de l'instructeur : étape et fin de cours de chaque inscription, sans
     * transférer le contenu des cours (MariaDB extrait les deux champs).
     *
     * @return list<array{user_id: int|string, course_key: string, stage: mixed, completed: mixed}>
     */
    public function reviewRows(int $tenantId): array
    {
        $stmt = $this->pdo->prepare("SELECT user_id, course_key, JSON_EXTRACT(state_json, '$.stage') AS stage, JSON_EXTRACT(state_json, '$.completed') AS completed FROM academy_learners WHERE tenant_id = ? AND course_key <> '__notes' ORDER BY updated_at DESC LIMIT 500");
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Tableau de suivi : une seule lecture pour toutes les inscriptions des formations affichées. La base n'extrait que
     * l'avancement (chapitre, étape, fin, dispense, évaluation échouée, relance) ; le contenu des cours n'est jamais
     * transféré, et le débriefing seulement pour un chapitre qui attend la validation d'un instructeur.
     *
     * @param list<string> $courseKeys
     * @param int|null $userId une seule personne (le stagiaire voit sa ligne)
     * @return list<array<string, mixed>> lignes brutes, à passer à AcademyTracker::normalizeRow()
     */
    public function trackerRows(int $tenantId, array $courseKeys, ?int $userId = null): array
    {
        $courseKeys = array_values(array_unique(array_filter($courseKeys, static fn (mixed $key): bool => is_string($key) && preg_match('/\A[a-z0-9-]{1,64}\z/', $key) === 1)));
        if ($tenantId < 1 || $courseKeys === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($courseKeys), '?'));
        $stmt = $this->pdo->prepare("SELECT user_id, course_key, updated_at,
                JSON_EXTRACT(state_json, '$.chapter') AS chapter, JSON_EXTRACT(state_json, '$.stage') AS stage,
                JSON_EXTRACT(state_json, '$.completed') AS completed, JSON_EXTRACT(state_json, '$.exempt.at') AS exempt_at,
                JSON_EXTRACT(state_json, '$.feedback') AS feedback, JSON_EXTRACT(state_json, '$.exam.started') AS exam_started,
                JSON_EXTRACT(state_json, '$.reminded') AS reminded, JSON_EXTRACT(state_json, '$.updated') AS updated,
                CASE WHEN JSON_EXTRACT(state_json, '$.stage') = 6 THEN JSON_EXTRACT(state_json, '$.debriefs') END AS debriefs
            FROM academy_learners WHERE tenant_id = ? AND course_key IN ($in)" . ($userId !== null ? ' AND user_id = ?' : '') . ' LIMIT 20000');
        $stmt->execute(array_merge([$tenantId], $courseKeys, $userId !== null ? [$userId] : []));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Relance envoyée depuis le tableau de suivi : notée dans l'inscription sans compter comme une activité du
     * stagiaire (la date de dernière activité, qui signale les retards, reste celle du stagiaire).
     */
    public function markReminded(int $tenantId, int $userId, string $key, int $at): void
    {
        $stmt = $this->pdo->prepare("UPDATE academy_learners SET state_json = JSON_SET(state_json, '$.reminded', ?), updated_at = updated_at WHERE tenant_id = ? AND user_id = ? AND course_key = ?");
        foreach ([$at, $tenantId, $userId] as $i => $value) {
            $stmt->bindValue($i + 1, $value, PDO::PARAM_INT);
        }
        $stmt->bindValue(4, $key);
        $stmt->execute();
    }

    /** Nombre d'inscrits par formation (choix des colonnes du tableau de suivi). @return array<string,int> */
    public function enrollmentCounts(int $tenantId): array
    {
        $stmt = $this->pdo->prepare("SELECT course_key, COUNT(*) AS n FROM academy_learners WHERE tenant_id = ? AND course_key <> '__notes' GROUP BY course_key");
        $stmt->execute([$tenantId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(string) $row['course_key']] = (int) $row['n'];
        }
        return $out;
    }

    /** Staff see educational submissions in their own community, never personal notebooks. */
    public function learners(int $tenantId): array
    {
        $stmt = $this->pdo->prepare("SELECT a.user_id, a.course_key, a.state_json, a.updated_at FROM academy_learners a WHERE a.tenant_id = ? AND a.course_key <> '__notes' ORDER BY a.updated_at DESC LIMIT 200");
        $stmt->execute([$tenantId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['state'] = AcademyBuiltInCourses::refreshSnapshot(json_decode($row['state_json'], true, 64, JSON_THROW_ON_ERROR), (string) $row['course_key']);
            unset($row['state_json']);
        }
        return $rows;
    }
}
