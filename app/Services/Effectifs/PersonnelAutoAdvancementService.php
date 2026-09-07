<?php

declare(strict_types=1);

namespace App\Services\Effectifs;

use App\Repositories\ElevationRequestRepository;
use App\Repositories\GradeRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Personnel\SenioritySummaryService;
use App\Support\PersonnelHrPdfService;

/**
 * Avancement de grade selon l’ancienneté réelle : proposer une demande, ou appliquer si réglé.
 */
final class PersonnelAutoAdvancementService
{
    public function __construct(
        private ?TenantRepository $tenants = null,
        private ?UserRepository $users = null,
        private ?GradeRepository $grades = null,
        private ?ElevationRequestRepository $elevations = null,
        private ?EffectifsStaffAlertService $staffAlerts = null,
        private ?ElevationApprovalService $approval = null,
        private ?SenioritySummaryService $seniority = null,
        private ?PersonnelHrPdfService $pdf = null,
    ) {
        $this->tenants ??= new TenantRepository();
        $this->users ??= new UserRepository();
        $this->grades ??= new GradeRepository();
        $this->elevations ??= new ElevationRequestRepository();
        $this->seniority ??= new SenioritySummaryService(
            new \App\Repositories\SeniorityRepository(),
            new \App\Services\Personnel\SeniorityEngine()
        );
        $this->pdf ??= new PersonnelHrPdfService();
    }

    /**
     * @return array{tenants: int, proposed: int, applied: int, skipped: int, eligible: int, preview: list<array<string, mixed>>}
     */
    public function evaluateAllActiveTenants(bool $commit = true): array
    {
        $totals = ['tenants' => 0, 'proposed' => 0, 'applied' => 0, 'skipped' => 0, 'eligible' => 0, 'preview' => []];
        try {
            $list = $this->tenants->listBasicAll();
        } catch (\Throwable) {
            return $totals;
        }
        foreach ($list as $tenant) {
            $tenantId = (int) ($tenant['id'] ?? 0);
            if ($tenantId < 1) {
                continue;
            }
            $out = $this->evaluateTenant($tenantId, $commit);
            $totals['tenants']++;
            $totals['proposed'] += (int) ($out['proposed'] ?? 0);
            $totals['applied'] += (int) ($out['applied'] ?? 0);
            $totals['skipped'] += (int) ($out['skipped'] ?? 0);
            $totals['eligible'] += (int) ($out['eligible'] ?? 0);
        }

        return $totals;
    }

    /**
     * @return array{proposed: int, applied: int, skipped: int, eligible: int, preview: list<array<string, mixed>>, message?: string}
     */
    public function evaluateTenant(int $tenantId, bool $commit = true, ?int $actorUserId = null): array
    {
        $hr = PersonnelHrWorkspaceSettings::forTenant($tenantId);
        $empty = ['proposed' => 0, 'applied' => 0, 'skipped' => 0, 'eligible' => 0, 'preview' => []];
        if (empty($hr['advancement_enabled'])) {
            $empty['message'] = 'Les avancements automatiques sont en pause pour cette communauté.';

            return $empty;
        }
        $months = max(3, (int) ($hr['advancement_months'] ?? 12));
        $needDays = $months * 30;
        $mode = (string) ($hr['advancement_mode'] ?? PersonnelHrWorkspaceSettings::MODE_PROPOSE);
        $grades = $this->grades->listForTenant($tenantId);
        if ($grades === []) {
            $empty['message'] = 'Aucun grade n’est disponible pour cette communauté.';

            return $empty;
        }
        try {
            $members = $this->users->listForTenant($tenantId, null, 'active', null, 500, 0, true);
        } catch (\Throwable) {
            return $empty;
        }
        $userIds = [];
        foreach ($members as $member) {
            $uid = (int) ($member['id'] ?? 0);
            if ($uid > 0) {
                $userIds[] = $uid;
            }
        }
        $seniority = [];
        try {
            $seniority = $this->seniority->dashboardLabelsByUsers($tenantId, $userIds);
        } catch (\Throwable) {
            $seniority = [];
        }
        $actorUserId = $actorUserId !== null && $actorUserId > 0
            ? $actorUserId
            : $this->firstStaffId($tenantId);
        $proposed = 0;
        $applied = 0;
        $skipped = 0;
        $preview = [];
        foreach ($members as $member) {
            $uid = (int) ($member['id'] ?? 0);
            $gradeId = (int) ($member['grade_id'] ?? 0);
            if ($uid < 1 || $gradeId < 1) {
                $skipped++;
                continue;
            }
            $days = (int) ($seniority[$uid]['community_days'] ?? $seniority[$uid]['days'] ?? 0);
            if ($days < 1) {
                $created = trim((string) ($member['created_at'] ?? ''));
                if ($created !== '' && !str_starts_with($created, '0000-00-00')) {
                    $ts = strtotime($created);
                    if ($ts !== false) {
                        $days = (int) floor((time() - $ts) / 86400);
                    }
                }
            }
            if ($days < $needDays) {
                $skipped++;
                continue;
            }
            $next = $this->nextGrade($grades, $gradeId);
            if ($next === null) {
                $skipped++;
                continue;
            }
            if ($this->elevations->hasOpenForTarget($tenantId, $uid)) {
                $skipped++;
                continue;
            }
            $name = trim((string) ($member['display_name'] ?? ''));
            if ($name === '') {
                $name = (string) ($member['email'] ?? 'Membre');
            }
            $currentLabel = $this->gradeLabelFromList($grades, $gradeId);
            $nextLabel = trim((string) ($next['label_short'] ?? $next['label_long'] ?? 'Grade suivant'));
            $row = [
                'user_id' => $uid,
                'name' => $name,
                'days' => $days,
                'from' => $currentLabel,
                'to' => $nextLabel,
                'next_grade_id' => (int) ($next['id'] ?? 0),
            ];
            $preview[] = $row;
            if (!$commit) {
                continue;
            }
            if ($actorUserId < 1) {
                $skipped++;
                continue;
            }
            $note = 'Proposition automatique d’avancement : ancienneté d’environ '
                . $months . ' mois dans la communauté. Grade proposé : ' . $nextLabel . '.';
            if ($mode === PersonnelHrWorkspaceSettings::MODE_APPLY) {
                $ok = $this->applyGrade($tenantId, $uid, (int) $next['id'], $actorUserId);
                if ($ok) {
                    $applied++;
                    $this->pdf->maybeAutoIssue($tenantId, $uid, 'elevation', $actorUserId, [
                        'title' => 'Décision d’avancement',
                        'detail' => $currentLabel . ' → ' . $nextLabel,
                        'grade_label' => $nextLabel,
                    ]);
                } else {
                    $skipped++;
                }
                continue;
            }
            try {
                $this->elevations->create(
                    $tenantId,
                    $uid,
                    $actorUserId,
                    'grade',
                    $note,
                    ['grade_id' => (int) $next['id']]
                );
                $proposed++;
            } catch (\Throwable) {
                $skipped++;
            }
        }

        return [
            'proposed' => $proposed,
            'applied' => $applied,
            'skipped' => $skipped,
            'eligible' => count($preview),
            'preview' => $preview,
        ];
    }

    /**
     * @param list<array<string, mixed>> $grades
     * @return array<string, mixed>|null
     */
    public function nextGrade(array $grades, int $currentGradeId): ?array
    {
        $current = null;
        foreach ($grades as $grade) {
            if ((int) ($grade['id'] ?? 0) === $currentGradeId) {
                $current = $grade;
                break;
            }
        }
        if ($current === null) {
            return null;
        }
        $categoryId = (int) ($current['grade_category_id'] ?? 0);
        $sort = (int) ($current['sort_order'] ?? 0);
        $best = null;
        foreach ($grades as $grade) {
            if ((int) ($grade['id'] ?? 0) === $currentGradeId) {
                continue;
            }
            if ($categoryId > 0 && (int) ($grade['grade_category_id'] ?? 0) !== $categoryId) {
                continue;
            }
            $gSort = (int) ($grade['sort_order'] ?? 0);
            if ($gSort <= $sort) {
                continue;
            }
            if ($best === null || $gSort < (int) ($best['sort_order'] ?? 0)) {
                $best = $grade;
            }
        }

        return $best;
    }

    /**
     * @param list<array<string, mixed>> $grades
     */
    private function gradeLabelFromList(array $grades, int $gradeId): string
    {
        foreach ($grades as $grade) {
            if ((int) ($grade['id'] ?? 0) === $gradeId) {
                $short = trim((string) ($grade['label_short'] ?? ''));

                return $short !== '' ? $short : trim((string) ($grade['label_long'] ?? ''));
            }
        }

        return '';
    }

    private function applyGrade(int $tenantId, int $userId, int $gradeId, int $actorUserId): bool
    {
        if ($this->approval === null) {
            try {
                $this->approval = \App\Core\Container::get(ElevationApprovalService::class);
            } catch (\Throwable) {
                return false;
            }
        }
        $out = $this->approval->applyApprovedChanges(
            $tenantId,
            $userId,
            ['grade_id' => $gradeId],
            $actorUserId
        );

        return !empty($out['ok']);
    }

    private function firstStaffId(int $tenantId): int
    {
        if ($this->staffAlerts === null) {
            try {
                $this->staffAlerts = \App\Core\Container::get(EffectifsStaffAlertService::class);
            } catch (\Throwable) {
                return 0;
            }
        }
        $recipients = $this->staffAlerts->listElevationRecipients($tenantId, null);

        return (int) ($recipients[0]['user_id'] ?? 0);
    }
}
