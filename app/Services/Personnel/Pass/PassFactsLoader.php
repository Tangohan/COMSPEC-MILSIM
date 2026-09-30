<?php

declare(strict_types=1);

namespace App\Services\Personnel\Pass;

use App\Repositories\ArmaPlaytimeRepository;
use App\Repositories\PersonnelPassRepository;
use App\Repositories\PersonnelQualificationRepository;
use App\Repositories\RoleplayGameSessionRepository;
use App\Repositories\TrainingEnrollmentRepository;
use App\Repositories\UserRepository;
use App\Core\Database;
use PDO;

final class PassFactsLoader
{
    public function __construct(
        private UserRepository $users,
        private PersonnelQualificationRepository $qualifications,
        private TrainingEnrollmentRepository $enrollments,
        private ArmaPlaytimeRepository $playtime,
        private RoleplayGameSessionRepository $sessions,
        private PersonnelPassRepository $passes,
    ) {}

    /**
     * @param array<string, mixed> $condition
     * @param array<string, mixed>|null $pass
     */
    public function load(int $tenantId, int $userId, array $condition = [], ?array $pass = null): PassEvaluationContext
    {
        $rawHours = 0.0;
        $validatedHours = 0.0;
        $categoryHours = 0.0;
        try {
            if ($this->playtime->schemaReady()) {
                $sum = $this->playtime->getSummaryForUser($tenantId, $userId);
                $rawHours = round(((int) ($sum['total_seconds'] ?? 0)) / 3600, 2);
            }
        } catch (\Throwable) {
        }
        try {
            if ($this->sessions->schemaReady()) {
                $validatedHours = round($this->sessions->sumValidatedSeconds($tenantId, $userId, null, null, null) / 3600, 2);
                $cat = trim((string) ($condition['hour_category'] ?? ''));
                if ($cat !== '') {
                    $categoryHours = round($this->sessions->sumValidatedSeconds($tenantId, $userId, $cat, null, null) / 3600, 2);
                }
            }
        } catch (\Throwable) {
        }

        $qualId = (int) ($condition['qualification_id'] ?? 0);
        $heldId = null;
        $qualValid = false;
        $qualLabel = 'la qualification demandée';
        if ($qualId > 0) {
            foreach ($this->qualifications->listForUser($userId) as $q) {
                if ((int) ($q['tenant_id'] ?? $tenantId) !== $tenantId && (int) ($q['tenant_id'] ?? 0) > 0) {
                    continue;
                }
                $defId = (int) ($q['definition_id'] ?? 0);
                $rowId = (int) ($q['id'] ?? 0);
                if ($defId !== $qualId && $rowId !== $qualId) {
                    continue;
                }
                $heldId = $qualId;
                $qualLabel = trim((string) ($q['qualification_name'] ?? '')) ?: $qualLabel;
                $status = strtolower(trim((string) ($q['status'] ?? 'valid')));
                $expires = trim((string) ($q['expires_at'] ?? ''));
                $expired = $expires !== '' && strtotime($expires) !== false && strtotime($expires) < time();
                $qualValid = in_array($status, ['valid', 'expiring'], true) && !$expired;
                break;
            }
        }

        $lmsId = (int) ($condition['training_module_id'] ?? 0);
        $lmsDone = false;
        $lmsLabel = 'le module demandé';
        if ($lmsId > 0) {
            try {
                foreach ($this->enrollments->listByUserId($userId, $tenantId) as $enr) {
                    $courseId = (int) ($enr['course_id'] ?? 0);
                    $moduleId = (int) ($enr['module_id'] ?? 0);
                    if ($courseId !== $lmsId && $moduleId !== $lmsId) {
                        continue;
                    }
                    $lmsLabel = trim((string) ($enr['course_title'] ?? '')) ?: ('module #' . $lmsId);
                    $lmsDone = strtolower(trim((string) ($enr['status'] ?? ''))) === 'completed';
                    if ($lmsDone) {
                        break;
                    }
                }
            } catch (\Throwable) {
                $lmsDone = false;
            }
        }

        $gradeMeta = $this->resolveGrade($tenantId, $userId, (int) ($condition['grade_id'] ?? 0));
        $bilanKind = trim((string) ($condition['bilan_kind'] ?? ''));
        $window = (int) ($condition['window_days'] ?? 0) ?: null;
        $bilan = $this->passes->bilanAverage($tenantId, $userId, $bilanKind !== '' ? $bilanKind : null, $window);
        $bilanLabel = 'notation';
        foreach (PassConditionEngine::bilanKinds() as $bk) {
            if ($bk['value'] === $bilanKind) {
                $bilanLabel = $bk['label'];
                break;
            }
        }

        $avisKind = trim((string) ($condition['avis_kind'] ?? ''));
        $favorable = [];
        if ($avisKind !== '') {
            $passId = $pass !== null ? (int) ($pass['id'] ?? 0) : 0;
            $favorable[$avisKind] = $this->passes->hasFavorableAvis(
                $tenantId,
                $userId,
                $avisKind,
                $passId > 0 ? $passId : null
            );
        }

        return new PassEvaluationContext(
            tenantId: $tenantId,
            userId: $userId,
            qualificationHeldId: $heldId,
            qualificationValid: $qualValid,
            lmsCompleted: $lmsDone,
            rawHours: $rawHours,
            validatedHours: $validatedHours,
            categoryHours: $categoryHours,
            currentGradeId: $gradeMeta['current_id'],
            currentGradeOrder: $gradeMeta['current_order'],
            requiredGradeOrder: $gradeMeta['required_order'],
            gradeMet: $gradeMeta['met'],
            bilanAverage: $bilan['average'],
            bilanCount: $bilan['count'],
            favorableAvisByKind: $favorable,
            qualificationLabel: $qualLabel,
            lmsLabel: $lmsLabel,
            categoryLabel: trim((string) ($condition['hour_category'] ?? '')) ?: 'cette catégorie',
            gradeLabel: $gradeMeta['label'],
            bilanLabel: $bilanLabel,
        );
    }

    /**
     * @return array{current_id: int, current_order: int, required_order: int, met: bool, label: string}
     */
    private function resolveGrade(int $tenantId, int $userId, int $requiredGradeId): array
    {
        $empty = [
            'current_id' => 0,
            'current_order' => 0,
            'required_order' => 0,
            'met' => $requiredGradeId < 1,
            'label' => 'le grade demandé',
        ];
        if ($requiredGradeId < 1) {
            return $empty;
        }
        try {
            $pdo = Database::getPdo();
            $user = $this->users->findById($userId, $tenantId) ?? [];
            $currentId = (int) ($user['grade_id'] ?? 0);
            $req = $pdo->prepare(
                'SELECT id, label, rank_order, filiere_id FROM grade_definitions WHERE tenant_id = ? AND id = ? LIMIT 1'
            );
            $req->execute([$tenantId, $requiredGradeId]);
            $reqRow = $req->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($reqRow === null) {
                return $empty;
            }
            $label = trim((string) ($reqRow['label'] ?? '')) ?: 'le grade demandé';
            $reqOrder = (int) ($reqRow['rank_order'] ?? 0);
            $reqFiliere = (int) ($reqRow['filiere_id'] ?? 0);
            if ($currentId < 1) {
                return [
                    'current_id' => 0,
                    'current_order' => 0,
                    'required_order' => $reqOrder,
                    'met' => false,
                    'label' => $label,
                ];
            }
            $cur = $pdo->prepare(
                'SELECT id, rank_order, filiere_id FROM grade_definitions WHERE tenant_id = ? AND id = ? LIMIT 1'
            );
            $cur->execute([$tenantId, $currentId]);
            $curRow = $cur->fetch(PDO::FETCH_ASSOC) ?: null;
            $curOrder = (int) ($curRow['rank_order'] ?? 0);
            $curFiliere = (int) ($curRow['filiere_id'] ?? 0);
            $sameFiliere = $reqFiliere < 1 || $curFiliere < 1 || $reqFiliere === $curFiliere;
            $met = $sameFiliere && $curOrder >= $reqOrder;

            return [
                'current_id' => $currentId,
                'current_order' => $curOrder,
                'required_order' => $reqOrder,
                'met' => $met,
                'label' => $label,
            ];
        } catch (\Throwable) {
            return $empty;
        }
    }
}
