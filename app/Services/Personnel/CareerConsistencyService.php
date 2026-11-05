<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\AdvancementRepository;
use PDO;
use Throwable;

/** Écarts recalculés à la lecture : aucune attribution ni promotion n'est modifiée. */
final class CareerConsistencyService
{
    public function __construct(private PDO $pdo) {}

    /** @return list<array{user_id:int,member_label:string,source:string,detail:string}> */
    public function listForTenant(int $tenantId, ?int $userId = null): array
    {
        if ($tenantId < 1) {
            return [];
        }
        $out = [];
        $jobs = new JobPrerequisiteService($this->pdo);
        $options = $jobs->options($tenantId);
        $requirements = $jobs->requirementsByRole($tenantId);
        $filter = $userId === null ? '' : ' AND u.id = ?';
        $params = $userId === null ? [$tenantId] : [$tenantId, $userId];
        $append = static function (array $row, string $source, string $detail) use (&$out): void {
            $out[] = ['user_id' => (int) $row['user_id'], 'member_label' => trim((string) $row['display_name']) ?: 'Membre', 'source' => $source, 'detail' => $detail];
        };
        try {
            $st = $this->pdo->prepare("SELECT DISTINCT u.id AS user_id, u.display_name, r.id AS role_id, r.name
                FROM users u
                INNER JOIN personnel_profile_job_roles pj ON pj.user_id = u.id AND pj.tenant_id = u.tenant_id
                INNER JOIN personnel_job_roles r ON r.id = pj.personnel_job_role_id AND r.tenant_id = u.tenant_id
                WHERE u.tenant_id = ? AND u.status = 'active'" . $filter);
            $st->execute($params);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $eval = $jobs->evaluate($tenantId, (int) $row['user_id'], $requirements[(int) $row['role_id']] ?? []);
                foreach ($eval['items'] as $item) {
                    if ($item['met'] !== false) {
                        continue;
                    }
                    $parts = JobPrerequisiteService::describe($item['criterion'], $options);
                    $append($row, 'Emploi : ' . $row['name'], trim($parts['label'] . ' : ' . $parts['target'] . ' ' . $parts['value'] . ' ' . $parts['unit']));
                }
            }
        } catch (Throwable $e) {
            error_log('[career_consistency] emplois : ' . $e->getMessage());
        }
        // Le grade d'avancement en vigueur porte ses propres qualification et niveau attendus.
        try {
            $st = $this->pdo->prepare("SELECT u.id AS user_id, u.display_name, g.*
                FROM users u
                INNER JOIN personnel_grade_history h ON h.personnel_id = u.id AND h.ends_at IS NULL
                INNER JOIN grade_definitions g ON g.id = h.grade_id AND g.tenant_id = u.tenant_id
                WHERE u.tenant_id = ? AND u.status = 'active' AND g.required_qualification_id IS NOT NULL
                AND h.id = (SELECT h2.id FROM personnel_grade_history h2
                    INNER JOIN grade_definitions g2 ON g2.id = h2.grade_id AND g2.tenant_id = u.tenant_id
                    WHERE h2.personnel_id = u.id AND h2.ends_at IS NULL ORDER BY h2.obtained_at DESC, h2.id DESC LIMIT 1)" . $filter);
            $st->execute($params);
            $repo = new AdvancementRepository($this->pdo);
            $temporal = new QualificationTemporalStatusService();
            $names = $repo->qualificationNames($tenantId);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $qid = (int) $row['required_qualification_id'];
                if ($qid < 1) {
                    continue;
                }
                $award = $repo->latestAward($tenantId, (int) $row['user_id'], $qid);
                $name = $names[$qid] ?? '#' . $qid;
                if ($award === null || !$temporal->isEffectivelyActive($award)) {
                    $append($row, 'Grade : ' . $row['label'], 'Qualification manquante ou inactive : ' . $name);
                } elseif (!$repo->qualificationLevelMet((int) ($award['qualification_level_id'] ?? 0), (int) ($row['required_qualification_level_id'] ?? 0))) {
                    $append($row, 'Grade : ' . $row['label'], 'Niveau de qualification insuffisant : ' . $name);
                }
            }
        } catch (Throwable $e) {
            error_log('[career_consistency] grades : ' . $e->getMessage());
        }
        // Le référentiel de compétences couvre aussi les changements directs du grade du membre.
        try {
            $st = $this->pdo->prepare("SELECT u.id AS user_id, u.display_name, c.id AS requirement_id, c.label
                FROM users u INNER JOIN competency_grade_requirements c ON c.grade_id = u.grade_id AND c.tenant_id = u.tenant_id
                WHERE u.tenant_id = ? AND u.status = 'active'" . $filter);
            $st->execute($params);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $eval = $jobs->evaluate($tenantId, (int) $row['user_id'], [['type' => 'competency', 'requirement_id' => (int) $row['requirement_id']]]);
                if (!$eval['complete']) {
                    $append($row, 'Compétence attendue au grade', (string) $row['label']);
                }
            }
        } catch (Throwable $e) {
            error_log('[career_consistency] compétences : ' . $e->getMessage());
        }
        return $out;
    }
}
