<?php

declare(strict_types=1);

namespace App\Services\Courrier;

use App\Repositories\Courrier\DocumentVariablesCatalogRepository;
use App\Repositories\GradeRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use App\Services\GradeDisplayService;

/**
 * Résout les placeholders {{user.first_name}}, {{unit.name}}, {{user.grade_text}}, {{user.grade_otan}}, etc.
 */
class TemplateVariableService
{
    public function __construct(
        private UserRepository $userRepository,
        private UserProfileRepository $userProfileRepository,
        private PersonnelProfileRepository $personnelProfileRepository,
        private GradeRepository $gradeRepository,
        private UnitRepository $unitRepository,
        private DocumentVariablesCatalogRepository $variablesCatalog,
        private GradeDisplayService $gradeDisplayService
    ) {
    }

    /**
     * Construit le contexte de résolution (utilisateur, unité, document, date).
     * @param array{user_id?: int, tenant_id?: int, unit_id?: int, document?: array} $context
     */
    public function buildContext(array $context): array
    {
        $tenantId = $context['tenant_id'] ?? null;
        $userId = $context['user_id'] ?? null;
        $unitId = $context['unit_id'] ?? null;
        $document = $context['document'] ?? [];

        $resolved = [
            'current_date_fr' => date('d/m/Y'),
            'current_datetime_fr' => date('d/m/Y H:i'),
            'current_year' => date('Y'),
        ];

        if (!empty($document)) {
            $resolved['document.uuid'] = $document['uuid'] ?? '';
            $resolved['document.reference_number'] = $document['reference_number'] ?? '';
        }

        $profile = null;
        $personnelProfile = null;
        if ($userId && $tenantId) {
            $user = $this->userRepository->findById($userId, $tenantId);
            $profile = $this->userProfileRepository->getByUserId($userId);
            $personnelProfile = $this->personnelProfileRepository->getByUserId($userId);
            $grade = $user && !empty($user['grade_id']) ? $this->gradeRepository->findById((int) $user['grade_id'], $tenantId) : null;
            $format = $user['preferred_grade_format'] ?? GradeDisplayService::FORMAT_CLASSIC;
            $countryCode = $user['nationality_code'] ?? null;

            $firstName = $profile['first_name'] ?? $personnelProfile['character_name'] ?? null;
            $lastName = $profile['last_name'] ?? null;
            if ($firstName === null && $lastName === null && isset($user['display_name'])) {
                $parts = explode(' ', (string) $user['display_name'], 2);
                $firstName = $parts[0] ?? '';
                $lastName = $parts[1] ?? '';
            }
            $fullName = trim(($firstName ?? '') . ' ' . ($lastName ?? ''));
            if ($fullName === '') {
                $fullName = $user['display_name'] ?? $user['email'] ?? '';
            }

            $resolved['user.first_name'] = $firstName ?? '';
            $resolved['user.last_name'] = $lastName ?? '';
            $resolved['user.full_name'] = $fullName;
            if ($grade) {
                $resolved['user.grade_text'] = $this->gradeDisplayService->formatForUser($grade, GradeDisplayService::FORMAT_CLASSIC, $countryCode);
                $resolved['user.grade_short'] = $this->gradeDisplayService->getShort($grade);
                $resolved['user.grade_otan'] = $this->gradeDisplayService->getOtan($grade) ?? '';
                $resolved['user.grade_full'] = $this->gradeDisplayService->getFull($grade);
                $resolved['user.category_label'] = $this->gradeDisplayService->getCategoryLabel($grade);
                $resolved['user.rank'] = $this->gradeDisplayService->getShort($grade);
                $resolved['user.rank_label'] = $this->gradeDisplayService->formatForUser($grade, $format, $countryCode);
            } else {
                $resolved['user.grade_text'] = '';
                $resolved['user.grade_short'] = '';
                $resolved['user.grade_otan'] = '';
                $resolved['user.grade_full'] = '';
                $resolved['user.category_label'] = '';
                $resolved['user.rank'] = '';
                $resolved['user.rank_label'] = '';
            }
            $resolved['user.service_number'] = $personnelProfile['matricule_internal'] ?? $profile['service_number'] ?? '';
            $resolved['user.email'] = $user['email'] ?? '';
        }

        $unitId = $unitId ?? ($personnelProfile['primary_unit_id'] ?? null);
        if ($unitId && $tenantId) {
            $unit = $this->unitRepository->findById((int) $unitId, $tenantId);
            if ($unit) {
                $resolved['unit.name'] = $unit['name'] ?? '';
                $resolved['unit.company'] = $unit['name'] ?? '';
                $resolved['unit.section'] = $unit['type'] ?? '';
                $resolved['unit.address'] = '';
                $resolved['unit.city'] = '';
                if ($unit['commander_user_id'] ?? null) {
                    $sup = $this->userRepository->findById((int) $unit['commander_user_id'], $tenantId);
                    $supPersonnel = $this->personnelProfileRepository->getByUserId((int) $unit['commander_user_id']);
                    $supGrade = $sup && $sup['grade_id'] ? $this->gradeRepository->findById((int) $sup['grade_id'], $tenantId) : null;
                    if ($supGrade) {
                        $resolved['superior.grade_text'] = $this->gradeDisplayService->formatForUser($supGrade, GradeDisplayService::FORMAT_CLASSIC, $sup['nationality_code'] ?? null);
                        $resolved['superior.grade_otan'] = $this->gradeDisplayService->getOtan($supGrade) ?? '';
                        $resolved['superior.rank_label'] = $this->gradeDisplayService->formatForUser($supGrade, $sup['preferred_grade_format'] ?? GradeDisplayService::FORMAT_CLASSIC, $sup['nationality_code'] ?? null);
                    } else {
                        $resolved['superior.grade_text'] = '';
                        $resolved['superior.grade_otan'] = '';
                        $resolved['superior.rank_label'] = '';
                    }
                    $resolved['superior.full_name'] = $supPersonnel['character_name'] ?? $sup['display_name'] ?? '';
                    $resolved['superior.position_label'] = 'Commandant';
                }
            }
        }

        if (!isset($resolved['unit.name'])) {
            $resolved['unit.name'] = '';
            $resolved['unit.company'] = '';
            $resolved['unit.section'] = '';
            $resolved['unit.address'] = '';
            $resolved['unit.city'] = '';
        }
        if (!isset($resolved['superior.rank_label'])) {
            $resolved['superior.rank_label'] = '';
            $resolved['superior.grade_text'] = $resolved['superior.grade_text'] ?? '';
            $resolved['superior.grade_otan'] = $resolved['superior.grade_otan'] ?? '';
            $resolved['superior.full_name'] = '';
            $resolved['superior.position_label'] = '';
        }

        return $resolved;
    }

    /**
     * Remplace les placeholders {{code}} dans une chaîne avec le contexte fourni.
     */
    public function replaceInString(string $text, array $context): string
    {
        $resolved = $this->buildContext($context);
        foreach ($resolved as $code => $value) {
            $text = str_replace('{{' . $code . '}}', (string) $value, $text);
        }
        return $text;
    }

    /**
     * Retourne les variables disponibles groupées par catégorie (pour l'UI).
     */
    public function getAvailableVariables(?int $tenantId = null): array
    {
        return $this->variablesCatalog->getGroupedByCategory($tenantId);
    }
}
