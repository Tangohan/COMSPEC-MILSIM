<?php

declare(strict_types=1);

namespace App\Services\Courrier;

use App\Repositories\Courrier\DocumentVariablesCatalogRepository;
use App\Repositories\GradeRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\TenantRepository;
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
        private GradeDisplayService $gradeDisplayService,
        private TenantRepository $tenantRepository
    ) {
    }

    /**
     * Construit le contexte de résolution (utilisateur, unité, document, date).
     * @param array{user_id?: int, tenant_id?: int, unit_id?: int, document?: array} $context
     */
    public function buildContext(array $context): array
    {
        $tenantId = isset($context['tenant_id']) ? (int) $context['tenant_id'] : 0;
        $userId = isset($context['user_id']) ? (int) $context['user_id'] : 0;
        $unitId = $context['unit_id'] ?? null;
        $document = $context['document'] ?? [];

        $resolved = [
            'current_date_fr' => date('d/m/Y'),
            'current_datetime_fr' => date('d/m/Y H:i'),
            'current_year' => date('Y'),
            'document.uuid' => $this->displayOrDash($document['uuid'] ?? null),
            'document.reference_number' => $this->displayOrDash($document['reference_number'] ?? null),
        ];

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

        $letterhead = $this->resolveLetterhead([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'unit_id' => $unitId,
            'personnel_profile' => $personnelProfile,
        ]);
        $resolved['tenant.name'] = $this->displayOrDash($letterhead['tenant_name'] !== '' ? $letterhead['tenant_name'] : null);
        $resolved['group.name'] = $this->displayOrDash($letterhead['group_name'] !== '' ? $letterhead['group_name'] : null);

        $unitId = $this->resolveAssignedUnitId($tenantId, $userId, $unitId, $personnelProfile);
        if ($unitId && $tenantId) {
            $unit = $this->unitRepository->findById((int) $unitId, $tenantId);
            if ($unit) {
                $resolved['unit.name'] = $this->displayOrDash($letterhead['unit_name'] !== '' ? $letterhead['unit_name'] : ($unit['name'] ?? null));
                $resolved['unit.company'] = $this->displayOrDash($letterhead['unit_name'] !== '' ? $letterhead['unit_name'] : ($unit['name'] ?? null));
                $resolved['unit.section'] = $this->displayOrDash($letterhead['group_name'] !== '' ? $letterhead['group_name'] : null);
                $resolved['unit.address'] = $this->displayOrDash($unit['address'] ?? $unit['public_address'] ?? null);
                $resolved['unit.city'] = $this->displayOrDash($unit['city'] ?? $unit['public_city'] ?? null);
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
            $resolved['unit.name'] = $this->displayOrDash($letterhead['unit_name'] !== '' ? $letterhead['unit_name'] : null);
            $resolved['unit.company'] = $resolved['unit.name'];
            $resolved['unit.section'] = $this->displayOrDash($letterhead['group_name'] !== '' ? $letterhead['group_name'] : null);
            $resolved['unit.address'] = '—';
            $resolved['unit.city'] = '—';
        }
        if (!isset($resolved['superior.rank_label'])) {
            $resolved['superior.rank_label'] = '—';
            $resolved['superior.grade_text'] = $resolved['superior.grade_text'] ?? '—';
            $resolved['superior.grade_otan'] = $resolved['superior.grade_otan'] ?? '—';
            $resolved['superior.full_name'] = '—';
            $resolved['superior.position_label'] = '—';
        }

        return $resolved;
    }

    /**
     * Communauté, unité et groupe de l’opérateur, pour l’en-tête papier.
     *
     * @param array{user_id?: int|null, tenant_id?: int|null, unit_id?: int|null, personnel_profile?: array<string, mixed>|null} $context
     * @return array{tenant_name: string, unit_name: string, group_name: string}
     */
    public function resolveLetterhead(array $context): array
    {
        $tenantId = (int) ($context['tenant_id'] ?? 0);
        $userId = (int) ($context['user_id'] ?? 0);
        $unitId = $context['unit_id'] ?? null;
        $personnelProfile = is_array($context['personnel_profile'] ?? null) ? $context['personnel_profile'] : null;

        $tenantName = '';
        $affiliation = '';
        if ($tenantId > 0) {
            $tenant = $this->tenantRepository->findById($tenantId);
            if (is_array($tenant)) {
                $slug = strtolower(trim((string) ($tenant['slug'] ?? '')));
                if ($slug !== 'default' && function_exists('community_display_name')) {
                    $tenantName = trim(community_display_name($tenant));
                    if ($tenantName === 'Pas d\'organisation') {
                        $tenantName = '';
                    }
                } elseif ($slug !== 'default') {
                    $tenantName = trim((string) ($tenant['name'] ?? ''));
                }
                $affiliation = $this->affiliationFromTenant($tenant);
            }
        }

        if ($personnelProfile === null && $userId > 0) {
            $personnelProfile = $this->personnelProfileRepository->getByUserId($userId);
        }
        $jobRole = trim((string) ($personnelProfile['primary_role'] ?? ''));
        $assignedId = $this->resolveAssignedUnitId($tenantId, $userId, $unitId, $personnelProfile);
        $chain = $assignedId > 0 && $tenantId > 0 ? $this->assignmentChain($tenantId, $assignedId) : [];

        return CourrierLetterhead::fromAssignmentChain($chain, $tenantName, $affiliation, $jobRole);
    }

    /**
     * @param array<string, mixed>|null $personnelProfile
     */
    private function resolveAssignedUnitId(int $tenantId, int $userId, mixed $unitId, ?array $personnelProfile): int
    {
        $fromArg = (int) ($unitId ?? 0);
        if ($fromArg > 0) {
            return $fromArg;
        }
        $fromProfile = (int) ($personnelProfile['primary_unit_id'] ?? 0);
        if ($fromProfile > 0) {
            return $fromProfile;
        }
        if ($tenantId < 1 || $userId < 1) {
            return 0;
        }
        $ids = $this->unitRepository->unitIdsForUser($tenantId, $userId);

        return (int) ($ids[0] ?? 0);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function assignmentChain(int $tenantId, int $unitId): array
    {
        $chain = [];
        $seen = [];
        $id = $unitId;
        $guard = 0;
        while ($id > 0 && $guard++ < 12 && !isset($seen[$id])) {
            $seen[$id] = true;
            $row = $this->unitRepository->findById($id, $tenantId);
            if (!is_array($row)) {
                break;
            }
            $chain[] = $row;
            $id = (int) ($row['parent_id'] ?? 0);
        }

        return $chain;
    }

    /**
     * @param array<string, mixed> $tenant
     */
    private function affiliationFromTenant(array $tenant): string
    {
        $raw = $tenant['settings'] ?? null;
        $settings = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : []);
        if (!is_array($settings)) {
            return '';
        }
        $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];
        $aff = is_array($community['unit_affiliation'] ?? null) ? $community['unit_affiliation'] : [];
        if ($aff === []) {
            return '';
        }
        if (!empty($aff['is_real'])) {
            $labels = $aff['unit_labels'] ?? [];
            if (!is_array($labels)) {
                return '';
            }
            $parts = [];
            foreach ($labels as $label) {
                $s = trim((string) $label);
                if ($s !== '') {
                    $parts[] = $s;
                }
            }

            return implode(', ', $parts);
        }

        return trim((string) ($aff['fictional_label'] ?? ''));
    }

    /**
     * Valeur affichable dans un courrier : tiret cadratin si absente.
     */
    private function displayOrDash(mixed $value): string
    {
        $s = trim((string) ($value ?? ''));

        return $s !== '' ? $s : '—';
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
