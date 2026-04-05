<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\PersonnelProfileRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelQualificationRepository;
use App\Repositories\UnitRepository;

class PersonnelCompletenessService
{
    public function __construct(
        private PersonnelProfileRepository $profileRepository,
        private PersonnelAssignmentRepository $assignmentRepository,
        private PersonnelQualificationRepository $qualificationRepository,
        private UnitRepository $unitRepository
    ) {}

    /**
     * Retourne le score de complétude et les sections critiques incomplètes.
     *
     * @return array{score: int, sections_critiques: list<string>, details: array<string, bool>}
     */
    public function getScore(int $userId, array $user, ?array $userProfile, ?array $personnelExtras): array
    {
        $profile = $this->profileRepository->getByUserId($userId);
        $assignments = $this->assignmentRepository->listActiveForUserResolved($userId);
        $qualifications = $this->qualificationRepository->listForUser($userId);

        $primary = $assignments[0] ?? null;
        $unitName = $primary['unit_name'] ?? null;
        $commanderId = $primary['commander_user_id'] ?? null;

        $checks = [
            'identity_name' => !empty(trim((string) ($profile['character_name'] ?? $user['display_name'] ?? ''))),
            'identity_callsign' => !empty(trim((string) ($user['callsign'] ?? '')))
                || !empty(trim((string) ($profile['callsign'] ?? ''))),
            'identity_matricule' => !empty(trim((string) ($profile['matricule_internal'] ?? $personnelExtras['service_number'] ?? ''))),
            'identity_role' => !empty(trim((string) ($profile['primary_role'] ?? ''))),
            'identity_unit' => !empty($unitName) || !empty(trim((string) ($personnelExtras['squadron'] ?? ''))),
            'identity_enlistment' => !empty($profile['enlistment_date'] ?? $personnelExtras['date_of_enlistment'] ?? null),
            'assignment_role' => !empty($primary['role_name'] ?? ''),
            'security_clearance' => !empty(trim((string) ($profile['clearance_level'] ?? $personnelExtras['clearance_level'] ?? ''))),
            'security_review' => !empty($profile['clearance_reviewed_at'] ?? null),
            'qualifications' => count($qualifications) > 0,
            'readiness' => ((int) ($profile['readiness_score'] ?? 0)) > 0
                || (isset($personnelExtras['readiness_percent']) && (int) $personnelExtras['readiness_percent'] > 0),
            'contact_email' => !empty(trim((string) ($user['email'] ?? ''))),
        ];

        $critical = [
            'identity_matricule' => 'Matricule non défini',
            'identity_unit' => 'Affectation absente',
            'security_clearance' => 'Clearance non définie',
            'assignment_role' => 'Rôle d\'affectation absent',
        ];

        $sectionsCritiques = [];
        foreach ($critical as $key => $label) {
            if (empty($checks[$key])) {
                $sectionsCritiques[] = $label;
            }
        }

        $total = count($checks);
        $filled = count(array_filter($checks));
        $score = $total > 0 ? (int) round($filled / $total * 100) : 0;

        return [
            'score' => min(100, $score),
            'sections_critiques' => $sectionsCritiques,
            'details' => $checks,
        ];
    }
}
