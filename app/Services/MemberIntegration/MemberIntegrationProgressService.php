<?php

declare(strict_types=1);

namespace App\Services\MemberIntegration;

use App\Support\MemberIntegrationCatalog;

/**
 * Progression : seules les étapes obligatoires déterminent la complétion.
 * Une facultative terminée s’affiche mais ne bloque pas.
 */
final class MemberIntegrationProgressService
{
    /**
     * @param list<array<string, mixed>> $steps
     * @return array{
     *   progress_percent: int,
     *   required_total: int,
     *   required_completed: int,
     *   optional_completed: int,
     *   overdue_count: int,
     *   blocked: bool,
     *   status: string,
     *   current_step: ?array<string, mixed>,
     *   can_complete: bool
     * }
     */
    public function compute(array $steps, ?string $now = null): array
    {
        $nowTs = strtotime($now ?? 'now') ?: time();
        $requiredTotal = 0;
        $requiredCompleted = 0;
        $optionalCompleted = 0;
        $overdue = 0;
        $blocked = false;
        $waitingMember = false;
        $waitingStaff = false;
        $anyStarted = false;
        $current = null;

        foreach ($steps as $step) {
            if (!is_array($step)) {
                continue;
            }
            $required = !empty($step['is_required']);
            $status = (string) ($step['status'] ?? MemberIntegrationCatalog::STEP_PENDING);
            $done = MemberIntegrationCatalog::isStepDone($status);
            if ($required) {
                $requiredTotal++;
                if ($done) {
                    $requiredCompleted++;
                }
            } elseif ($done) {
                $optionalCompleted++;
            }
            if ($status === MemberIntegrationCatalog::STEP_BLOCKED && $required) {
                $blocked = true;
            }
            if (in_array($status, [
                MemberIntegrationCatalog::STEP_IN_PROGRESS,
                MemberIntegrationCatalog::STEP_WAITING_MEMBER,
                MemberIntegrationCatalog::STEP_WAITING_STAFF,
                MemberIntegrationCatalog::STEP_BLOCKED,
            ], true)) {
                $anyStarted = true;
            }
            if ($status === MemberIntegrationCatalog::STEP_WAITING_MEMBER) {
                $waitingMember = true;
            }
            if ($status === MemberIntegrationCatalog::STEP_WAITING_STAFF) {
                $waitingStaff = true;
            }
            $due = trim((string) ($step['due_at'] ?? ''));
            if ($due !== '' && !$done) {
                $dueTs = strtotime($due);
                if ($dueTs !== false && $dueTs < $nowTs) {
                    $overdue++;
                }
            }
            if ($current === null && !$done && $status !== MemberIntegrationCatalog::STEP_CANCELLED) {
                $current = $step;
            }
        }

        $percent = $requiredTotal > 0
            ? (int) round(100 * $requiredCompleted / $requiredTotal)
            : ($steps === [] ? 0 : 100);
        $canComplete = $requiredTotal > 0 && $requiredCompleted >= $requiredTotal && !$blocked;

        $status = MemberIntegrationCatalog::STATUS_TO_START;
        if ($canComplete) {
            $status = MemberIntegrationCatalog::STATUS_COMPLETED;
        } elseif ($blocked) {
            $status = MemberIntegrationCatalog::STATUS_BLOCKED;
        } elseif ($waitingStaff) {
            $status = MemberIntegrationCatalog::STATUS_WAITING_STAFF;
        } elseif ($waitingMember) {
            $status = MemberIntegrationCatalog::STATUS_WAITING_MEMBER;
        } elseif ($anyStarted || $requiredCompleted > 0) {
            $status = MemberIntegrationCatalog::STATUS_IN_PROGRESS;
        }

        return [
            'progress_percent' => min(100, max(0, $percent)),
            'required_total' => $requiredTotal,
            'required_completed' => $requiredCompleted,
            'optional_completed' => $optionalCompleted,
            'overdue_count' => $overdue,
            'blocked' => $blocked,
            'status' => $status,
            'current_step' => $current,
            'can_complete' => $canComplete,
        ];
    }
}
