<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Core\Database;
use App\Repositories\GradeDefinitionRepository;
use App\Repositories\PersonnelCareerEventRepository;
use App\Repositories\PersonnelGradeHistoryRepository;
use App\Repositories\TenantMessageRepository;
use App\Repositories\UserRepository;
use App\Support\AdvancementCodes;
use DateTimeImmutable;
use PDO;

final class AdvancementSeniorityService
{
    public function __construct(
        private GradeDefinitionRepository $grades,
        private PersonnelGradeHistoryRepository $history,
        private AdvancementEligibilityService $eligibility,
        private PersonnelCareerEventRepository $careerEvents,
        private UserRepository $users,
        private ?TenantMessageRepository $messages = null,
    ) {
        $this->messages ??= new TenantMessageRepository();
    }

    /**
     * @return array{tenants: int, promoted: int, skipped: int}
     */
    public function promoteEligible(?DateTimeImmutable $now = null, bool $commit = true): array
    {
        $now = $now ?? new DateTimeImmutable('today');
        $totals = ['tenants' => 0, 'promoted' => 0, 'skipped' => 0];
        try {
            $pdo = Database::getPdo();
            $st = $pdo->query('SELECT id, owner_user_id FROM tenants');
            $tenants = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (\Throwable) {
            return $totals;
        }
        foreach ($tenants as $tenant) {
            $tenantId = (int) ($tenant['id'] ?? 0);
            if ($tenantId < 1) {
                continue;
            }
            $totals['tenants']++;
            $actorId = (int) ($tenant['owner_user_id'] ?? 0);
            $out = $this->promoteTenant($tenantId, $actorId > 0 ? $actorId : null, $now, $commit);
            $totals['promoted'] += $out['promoted'];
            $totals['skipped'] += $out['skipped'];
        }

        return $totals;
    }

    /**
     * @return array{promoted: int, skipped: int}
     */
    public function promoteTenant(
        int $tenantId,
        ?int $actorUserId,
        ?DateTimeImmutable $now = null,
        bool $commit = true
    ): array {
        $now = $now ?? new DateTimeImmutable('today');
        $out = ['promoted' => 0, 'skipped' => 0];
        if (!$this->grades->schemaReady() || !$this->history->schemaReady()) {
            return $out;
        }
        try {
            $actives = $this->history->listActiveForTenant($tenantId);
        } catch (\Throwable) {
            return $out;
        }
        foreach ($actives as $row) {
            $personnelId = (int) ($row['personnel_id'] ?? 0);
            $currentGrade = [
                'id' => (int) ($row['grade_id'] ?? 0),
                'rank_order' => (int) ($row['rank_order'] ?? 0),
                'filiere_id' => $row['filiere_id'] ?? null,
            ];
            $next = $this->grades->findImmediateSuccessor($tenantId, $currentGrade);
            if ($next === null || empty($next['advancement_seniority_enabled'])) {
                $out['skipped']++;
                continue;
            }
            $eval = $this->eligibility->evaluate($tenantId, $personnelId, (int) $next['id'], $now);
            if (empty($eval['is_eligible'])) {
                $out['skipped']++;
                continue;
            }
            if (!$commit) {
                $out['promoted']++;
                continue;
            }
            $this->history->append($tenantId, $personnelId, (int) $next['id'], [
                'obtained_at' => $now->format('Y-m-d'),
                'obtained_via' => AdvancementCodes::VIA_SENIORITY,
                'created_by' => $actorUserId,
            ]);
            $legacyId = (int) ($next['source_catalog_grade_id'] ?? 0);
            if ($legacyId > 0) {
                try {
                    $this->users->update($personnelId, $tenantId, ['grade_id' => $legacyId]);
                } catch (\Throwable) {
                }
            }
            $this->careerEvents->record($tenantId, $personnelId, 'grade_advancement_seniority', $actorUserId, [
                'grade_id' => (int) $next['id'],
                'via' => AdvancementCodes::VIA_SENIORITY,
            ]);
            if ($actorUserId !== null && $actorUserId > 0) {
                try {
                    $threadId = $this->messages->createThread(
                        $tenantId,
                        $actorUserId,
                        'Avancement à l’ancienneté : ' . trim((string) ($next['label'] ?? '')),
                        [$personnelId]
                    );
                    $this->messages->addMessage(
                        $threadId,
                        $actorUserId,
                        'Les conditions d’ancienneté sont réunies. Votre grade est désormais : '
                            . trim((string) ($next['label'] ?? '')) . '.'
                    );
                } catch (\Throwable) {
                }
            }
            $out['promoted']++;
        }

        return $out;
    }
}
