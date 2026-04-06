<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Repositories\UserRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\UnitRepository;

class ProfileCompletenessService
{
    public const LEVEL_BLOCKING = 'blocking';
    public const LEVEL_RECOMMENDED = 'recommended';
    public const LEVEL_ADMINISTRATIVE = 'administrative';

    public function __construct(
        private UserRepository $userRepository,
        private UserProfileRepository $userProfileRepository,
        private PersonnelProfileRepository $personnelProfileRepository,
        private PersonnelAssignmentRepository $assignmentRepository,
        private UnitRepository $unitRepository
    ) {}

    /**
     * Calcule le score de complétude et la liste des champs manquants pour l'admin.
     *
     * @param bool $includeAdministrativeChecks Si faux (vue communauté / hors équipe plateforme), les critères
     *        purement « hygiene » côté produit (ex. photo de profil) sont exclus du score et de la liste.
     *
     * @return array{
     *   score: int,
     *   missing: list<array{key: string, label: string, level: string}>,
     *   details: array<string, bool>,
     *   sections_critiques: list<string>
     * }
     */
    public function getCompleteness(
        int $userId,
        array $user,
        ?array $userProfile,
        ?array $personnelProfile,
        bool $includeAdministrativeChecks = true
    ): array {
        $userProfile = $userProfile ?? $this->userProfileRepository->getByUserId($userId) ?? [];
        $personnelProfile = $personnelProfile ?? $this->personnelProfileRepository->getByUserId($userId) ?? [];
        $assignments = $this->assignmentRepository->listActiveForUserResolved($userId);
        $primary = $assignments[0] ?? null;
        $unitName = $primary['unit_name'] ?? null;

        $firstName = trim((string) ($userProfile['first_name'] ?? ''));
        $lastName = trim((string) ($userProfile['last_name'] ?? ''));
        $displayName = trim((string) ($user['display_name'] ?? ''));
        $callsign = trim((string) ($user['callsign'] ?? $personnelProfile['callsign'] ?? ''));
        $email = trim((string) ($user['email'] ?? ''));
        $roleId = isset($user['role_id']) && (int) $user['role_id'] > 0 ? true : false;
        $hasUnit = !empty($unitName) || !empty(trim((string) ($personnelProfile['primary_unit_id'] ?? '')));
        $avatar = !empty(trim((string) ($user['avatar_url'] ?? ''))) || !empty(trim((string) ($personnelProfile['character_portrait_path'] ?? '')));

        $checks = [
            'first_name' => $firstName !== '',
            'last_name' => $lastName !== '',
            'email' => $email !== '',
            'role_id' => $roleId,
            'display_name' => $displayName !== '' || $firstName !== '' || $lastName !== '',
            'callsign' => $callsign !== '',
            'unit' => $hasUnit,
            'avatar' => $avatar,
        ];

        $definitions = [
            'first_name' => ['label' => 'Prénom manquant', 'level' => self::LEVEL_BLOCKING],
            'last_name' => ['label' => 'Nom manquant', 'level' => self::LEVEL_BLOCKING],
            'email' => ['label' => 'Adresse e-mail absente', 'level' => self::LEVEL_BLOCKING],
            'role_id' => ['label' => 'Rôle non affecté', 'level' => self::LEVEL_BLOCKING],
            'display_name' => ['label' => 'Nom d\'affichage absent', 'level' => self::LEVEL_RECOMMENDED],
            'callsign' => ['label' => 'Indicatif absent', 'level' => self::LEVEL_RECOMMENDED],
            'unit' => ['label' => 'Groupe / équipe absent', 'level' => self::LEVEL_RECOMMENDED],
            'avatar' => ['label' => 'Photo ou portrait absent', 'level' => self::LEVEL_ADMINISTRATIVE],
        ];

        if (!$includeAdministrativeChecks) {
            unset($checks['avatar'], $definitions['avatar']);
        }

        $missing = [];
        $sectionsCritiques = [];
        foreach ($definitions as $key => $def) {
            if (empty($checks[$key])) {
                $missing[] = ['key' => $key, 'label' => $def['label'], 'level' => $def['level']];
                if ($def['level'] === self::LEVEL_BLOCKING) {
                    $sectionsCritiques[] = $def['label'];
                }
            }
        }

        $total = count($checks);
        $filled = count(array_filter($checks));
        $score = $total > 0 ? (int) round($filled / $total * 100) : 0;

        return [
            'score' => min(100, $score),
            'missing' => $missing,
            'details' => $checks,
            'sections_critiques' => $sectionsCritiques,
        ];
    }
}
