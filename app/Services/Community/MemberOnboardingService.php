<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Core\Database;
use PDO;

/**
 * Onboarding membre transverse (profil/forum/document/formation/événement) sans persistance dédiée.
 */
final class MemberOnboardingService
{
    private PDO $pdo;

    /** @var array<string, bool> */
    private array $tableExistsCache = [];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    /**
     * @return array{
     *   plan: string,
     *   completed_count: int,
     *   total_count: int,
     *   percent: int,
     *   status: string,
     *   steps: list<array{key: string, label: string, module: string, done: bool, critical: bool, href: string}>,
     *   modules_done_count: int,
     *   nudge: string,
     *   created_at: string
     * }
     */
    public function buildMemberSnapshot(int $userId, int $tenantId, string $createdAt = ''): array
    {
        $roleSlugs = $this->userRoleSlugs($userId, $tenantId);
        $plan = $this->resolvePlanByRole($roleSlugs);

        $profileDone = $this->isProfileStepDone($userId);
        $forumDone = $this->isForumStepDone($userId, $tenantId);
        $docDone = $this->isDocumentStepDone($userId, $tenantId);
        $trainingDone = $this->isTrainingStepDone($userId, $tenantId);
        $eventDone = $this->isEventStepDone($userId, $tenantId);

        $steps = [
            ['key' => 'profil_complet', 'label' => 'Profil complété', 'module' => 'Profil / RH', 'done' => $profileDone, 'critical' => true, 'href' => url('account/preferences')],
            ['key' => 'presentation_forum', 'label' => 'Présentation sur le forum', 'module' => 'Forum', 'done' => $forumDone, 'critical' => false, 'href' => url('forum')],
            ['key' => 'document_essentiel_lu', 'label' => 'Document essentiel validé', 'module' => 'Documents', 'done' => $docDone, 'critical' => true, 'href' => url('account/charte-formations')],
            ['key' => 'formation_entree_completee', 'label' => 'Formation d’entrée terminée', 'module' => 'Formations', 'done' => $trainingDone, 'critical' => true, 'href' => url('formations/mes-formations')],
            ['key' => 'evenement_rejoint', 'label' => 'Événement rejoint', 'module' => 'Événements', 'done' => $eventDone, 'critical' => false, 'href' => url('evenements')],
        ];

        $completed = 0;
        $modulesDone = 0;
        foreach ($steps as $s) {
            if (!empty($s['done'])) {
                $completed++;
                $modulesDone++;
            }
        }
        $total = count($steps);
        $percent = $total > 0 ? (int) floor(($completed / $total) * 100) : 0;

        $status = 'à démarrer';
        if ($completed >= $total && $total > 0) {
            $status = 'terminé';
        } elseif ($completed > 0) {
            $status = 'en cours';
        }

        return [
            'plan' => $plan,
            'completed_count' => $completed,
            'total_count' => $total,
            'percent' => $percent,
            'status' => $status,
            'steps' => $steps,
            'modules_done_count' => $modulesDone,
            'nudge' => $this->buildNudge($steps, $createdAt),
            'created_at' => $createdAt,
        ];
    }

    /**
     * @return array{
     *   rows: list<array<string,mixed>>,
     *   kpis: array{j7_completion_rate: float, j14_completion_rate: float, cross_modules_rate: float, cohort_j7: int, cohort_j14: int, cohort_cross: int}
     * }
     */
    public function buildStaffDashboard(int $tenantId, int $limit = 80): array
    {
        $rows = [];
        if ($tenantId < 1 || !$this->hasTable('users')) {
            return [
                'rows' => [],
                'kpis' => [
                    'j7_completion_rate' => 0.0,
                    'j14_completion_rate' => 0.0,
                    'cross_modules_rate' => 0.0,
                    'cohort_j7' => 0,
                    'cohort_j14' => 0,
                    'cohort_cross' => 0,
                ],
            ];
        }

        $st = $this->pdo->prepare(
            "SELECT id, display_name, email, created_at
             FROM users
             WHERE tenant_id = ?
               AND status = 'active'
               AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             ORDER BY created_at DESC
             LIMIT ?"
        );
        $st->bindValue(1, $tenantId, PDO::PARAM_INT);
        $st->bindValue(2, max(1, min(250, $limit)), PDO::PARAM_INT);
        $st->execute();
        $members = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $j7Num = 0;
        $j7Den = 0;
        $j14Num = 0;
        $j14Den = 0;
        $crossNum = 0;
        $crossDen = 0;

        foreach ($members as $m) {
            $uid = (int) ($m['id'] ?? 0);
            $createdAt = (string) ($m['created_at'] ?? '');
            if ($uid < 1) {
                continue;
            }
            $snap = $this->buildMemberSnapshot($uid, $tenantId, $createdAt);
            $ageDays = $this->ageDays($createdAt);
            $rows[] = [
                'user_id' => $uid,
                'display_name' => trim((string) ($m['display_name'] ?? '')),
                'email' => trim((string) ($m['email'] ?? '')),
                'created_at' => $createdAt,
                'age_days' => $ageDays,
                'plan' => $snap['plan'],
                'percent' => $snap['percent'],
                'completed_count' => $snap['completed_count'],
                'total_count' => $snap['total_count'],
                'modules_done_count' => $snap['modules_done_count'],
                'nudge' => $snap['nudge'],
            ];

            if ($ageDays >= 7) {
                $j7Den++;
                if ($snap['completed_count'] >= $snap['total_count']) {
                    $j7Num++;
                }
            }
            if ($ageDays >= 14) {
                $j14Den++;
                if ($snap['completed_count'] >= $snap['total_count']) {
                    $j14Num++;
                }
            }
            if ($ageDays >= 0 && $ageDays <= 14) {
                $crossDen++;
                if ($snap['modules_done_count'] >= 3) {
                    $crossNum++;
                }
            }
        }

        return [
            'rows' => $rows,
            'kpis' => [
                'j7_completion_rate' => $j7Den > 0 ? ($j7Num / $j7Den) * 100.0 : 0.0,
                'j14_completion_rate' => $j14Den > 0 ? ($j14Num / $j14Den) * 100.0 : 0.0,
                'cross_modules_rate' => $crossDen > 0 ? ($crossNum / $crossDen) * 100.0 : 0.0,
                'cohort_j7' => $j7Den,
                'cohort_j14' => $j14Den,
                'cohort_cross' => $crossDen,
            ],
        ];
    }

    /** @param list<string> $roleSlugs */
    private function resolvePlanByRole(array $roleSlugs): string
    {
        $s = array_map(static fn ($v) => strtolower(trim((string) $v)), $roleSlugs);
        foreach ($s as $slug) {
            if (str_contains($slug, 'instruct')) {
                return 'instructeur';
            }
            if (str_contains($slug, 'recruit') || str_contains($slug, 'hr')) {
                return 'recruteur';
            }
            if (in_array($slug, ['officer', 'tenant_admin', 'community_owner'], true) || str_contains($slug, 'cadre')) {
                return 'cadre';
            }
        }

        return 'membre';
    }

    private function isProfileStepDone(int $userId): bool
    {
        if ($userId < 1 || !$this->hasTable('users')) {
            return false;
        }

        $core = $this->pdo->prepare('SELECT display_name, callsign FROM users WHERE id = ? LIMIT 1');
        $core->execute([$userId]);
        $u = $core->fetch(PDO::FETCH_ASSOC) ?: [];

        $filled = 0;
        if (trim((string) ($u['display_name'] ?? '')) !== '') {
            $filled++;
        }
        if (trim((string) ($u['callsign'] ?? '')) !== '') {
            $filled++;
        }

        if ($this->hasTable('user_profiles')) {
            $st = $this->pdo->prepare('SELECT timezone, language, phone FROM user_profiles WHERE user_id = ? LIMIT 1');
            $st->execute([$userId]);
            $p = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            if (trim((string) ($p['timezone'] ?? '')) !== '') {
                $filled++;
            }
            if (trim((string) ($p['phone'] ?? '')) !== '') {
                $filled++;
            }
            if (trim((string) ($p['language'] ?? '')) !== '') {
                $filled++;
            }
        }

        return $filled >= 3;
    }

    private function isForumStepDone(int $userId, int $tenantId): bool
    {
        if ($userId < 1 || $tenantId < 1) {
            return false;
        }
        if ($this->hasTable('forum_topics')) {
            $st = $this->pdo->prepare('SELECT 1 FROM forum_topics WHERE tenant_id = ? AND user_id = ? LIMIT 1');
            $st->execute([$tenantId, $userId]);
            if ($st->fetchColumn()) {
                return true;
            }
        }
        if ($this->hasTable('forum_posts')) {
            $st = $this->pdo->prepare('SELECT 1 FROM forum_posts WHERE tenant_id = ? AND user_id = ? LIMIT 1');
            $st->execute([$tenantId, $userId]);
            if ($st->fetchColumn()) {
                return true;
            }
        }

        return false;
    }

    private function isDocumentStepDone(int $userId, int $tenantId): bool
    {
        if ($userId < 1 || $tenantId < 1) {
            return false;
        }

        if ($this->hasTable('lms_hr_charter_acceptances')) {
            $st = $this->pdo->prepare('SELECT 1 FROM lms_hr_charter_acceptances WHERE tenant_id = ? AND user_id = ? LIMIT 1');
            $st->execute([$tenantId, $userId]);
            if ($st->fetchColumn()) {
                return true;
            }
        }

        if ($this->hasTable('hr_charter_acceptances')) {
            $st = $this->pdo->prepare('SELECT 1 FROM hr_charter_acceptances WHERE tenant_id = ? AND user_id = ? LIMIT 1');
            $st->execute([$tenantId, $userId]);
            if ($st->fetchColumn()) {
                return true;
            }
        }

        return false;
    }

    private function isTrainingStepDone(int $userId, int $tenantId): bool
    {
        if ($userId < 1 || $tenantId < 1 || !$this->hasTable('training_progress')) {
            return false;
        }
        $st = $this->pdo->prepare(
            "SELECT 1 FROM training_progress
             WHERE tenant_id = ? AND user_id = ?
               AND (completed_at IS NOT NULL OR status IN ('completed','validated'))
             LIMIT 1"
        );
        $st->execute([$tenantId, $userId]);

        return (bool) $st->fetchColumn();
    }

    private function isEventStepDone(int $userId, int $tenantId): bool
    {
        if ($userId < 1 || $tenantId < 1 || !$this->hasTable('community_event_rsvps') || !$this->hasTable('community_events')) {
            return false;
        }
        $st = $this->pdo->prepare(
            "SELECT 1
             FROM community_event_rsvps r
             INNER JOIN community_events e ON e.id = r.event_id
             WHERE r.user_id = ? AND e.tenant_id = ?
             LIMIT 1"
        );
        $st->execute([$userId, $tenantId]);

        return (bool) $st->fetchColumn();
    }

    /** @return list<string> */
    private function userRoleSlugs(int $userId, int $tenantId): array
    {
        if ($userId < 1 || $tenantId < 1 || !$this->hasTable('tenant_user_roles') || !$this->hasTable('roles')) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT r.slug
             FROM tenant_user_roles tur
             INNER JOIN roles r ON r.id = tur.role_id
             WHERE tur.tenant_id = ? AND tur.user_id = ?'
        );
        $st->execute([$tenantId, $userId]);
        $rows = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];

        return array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $rows), static fn ($v) => $v !== ''));
    }

    /** @param list<array{critical: bool, done: bool}> $steps */
    private function buildNudge(array $steps, string $createdAt): string
    {
        $age = $this->ageDays($createdAt);
        $done = 0;
        $criticalMissing = 0;
        foreach ($steps as $s) {
            if (!empty($s['done'])) {
                $done++;
            }
            if (!empty($s['critical']) && empty($s['done'])) {
                $criticalMissing++;
            }
        }

        if ($done === 0 && $age >= 2) {
            return 'Inactivité détectée (J+2) — relance douce recommandée.';
        }
        if ($criticalMissing > 0 && $age >= 5) {
            return 'Tâche critique manquante (J+5) — relance contextualisée.';
        }
        if ($age >= 7 && $done < 2) {
            return 'Risque onboarding (J+7) — escalade staff recommandée.';
        }

        return 'RAS';
    }

    private function ageDays(string $createdAt): int
    {
        if ($createdAt === '') {
            return 0;
        }
        try {
            $d = new \DateTimeImmutable($createdAt);
            $now = new \DateTimeImmutable('now');
            $diff = $d->diff($now);

            return max(0, (int) $diff->days);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function hasTable(string $table): bool
    {
        if (isset($this->tableExistsCache[$table])) {
            return $this->tableExistsCache[$table];
        }
        try {
            $st = $this->pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
            $st->execute([$table]);
            $this->tableExistsCache[$table] = (bool) $st->fetchColumn();
        } catch (\Throwable) {
            $this->tableExistsCache[$table] = false;
        }

        return $this->tableExistsCache[$table];
    }
}
