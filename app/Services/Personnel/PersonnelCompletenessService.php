<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\PersonnelProfileRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelQualificationRepository;
use App\Repositories\TrainingCertificateRepository;

/**
 * Complétude dossier personnel : alignée sur les champs éditables (fiche personnelle / ORBAT / formations).
 * L’affectation peut être une ligne ORBAT, une unité principale choisie dans le dossier, ou l’ex-champ escadron (extras).
 */
class PersonnelCompletenessService
{
    public function __construct(
        private PersonnelProfileRepository $profileRepository,
        private PersonnelAssignmentRepository $assignmentRepository,
        private PersonnelQualificationRepository $qualificationRepository,
        private TrainingCertificateRepository $trainingCertificateRepository
    ) {}

    /**
     * Retourne le score de complétude et les sections critiques incomplètes.
     *
     * @return array{score: int, sections_critiques: list<string>, details: array<string, bool>}
     */
    public function getScore(int $userId, array $user, ?array $userProfile, ?array $personnelExtras, ?int $tenantId = null): array
    {
        $profile = $this->profileRepository->getByUserId($userId) ?? [];
        $assignments = $this->assignmentRepository->listActiveForUserResolved($userId);
        $qualifications = $this->qualificationRepository->listForUser($userId);
        $certs = $tenantId !== null ? $this->trainingCertificateRepository->listByUserId($userId, $tenantId) : [];

        $primary = $assignments[0] ?? null;
        $unitName = $primary['unit_name'] ?? null;
        $roleNameAssignment = trim((string) ($primary['role_name'] ?? ''));
        $primaryRole = trim((string) ($profile['primary_role'] ?? ''));
        $jobRoleId = isset($profile['personnel_job_role_id']) ? (int) $profile['personnel_job_role_id'] : 0;
        $roleSubLabel = trim((string) ($profile['role_sub_label'] ?? ''));
        $primaryUnitId = isset($profile['primary_unit_id']) ? (int) $profile['primary_unit_id'] : 0;

        $up = $userProfile ?? [];
        $civilOk = !empty(trim((string) ($up['first_name'] ?? '')))
            && !empty(trim((string) ($up['last_name'] ?? '')));

        $hasUnit =
            ($unitName !== null && trim((string) $unitName) !== '')
            || !empty(trim((string) ($personnelExtras['squadron'] ?? '')))
            || $primaryUnitId > 0;

        $hasAssignmentRole = $roleNameAssignment !== '' || $primaryRole !== '' || $jobRoleId > 0 || $roleSubLabel !== '';

        $hasQualOrCert = count($qualifications) > 0 || count($certs) > 0;

        $readinessScore = (int) ($profile['readiness_score'] ?? 0);
        $extrasReadiness = isset($personnelExtras['readiness_percent']) ? (int) $personnelExtras['readiness_percent'] : 0;
        $hasReadiness = $readinessScore > 0 || $extrasReadiness > 0 || count($certs) > 0;

        $checks = [
            'identity_name' => !empty(trim((string) ($profile['character_name'] ?? $user['display_name'] ?? ''))),
            'identity_callsign' => !empty(trim((string) ($user['callsign'] ?? '')))
                || !empty(trim((string) ($profile['callsign'] ?? ''))),
            'identity_matricule' => !empty(trim((string) ($profile['matricule_internal'] ?? $personnelExtras['service_number'] ?? ''))),
            'identity_role' => $primaryRole !== '' || $jobRoleId > 0 || $roleSubLabel !== '',
            'identity_unit' => $hasUnit,
            'identity_enlistment' => !empty($profile['enlistment_date'] ?? $personnelExtras['date_of_enlistment'] ?? null),
            'assignment_role' => $hasAssignmentRole,
            'security_clearance' => !empty(trim((string) ($profile['clearance_level'] ?? $personnelExtras['clearance_level'] ?? ''))),
            'security_review' => !empty($profile['clearance_reviewed_at'] ?? null),
            'qualifications' => $hasQualOrCert,
            'readiness' => $hasReadiness,
            'contact_email' => !empty(trim((string) ($user['email'] ?? ''))),
            'civil_identity' => $civilOk,
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

    /**
     * Même calcul que getScore, avec libellés des champs encore manquants (pour admin / fiche personnage).
     *
     * @return array{score: int, sections_critiques: list<string>, details: array<string, bool>, missing_labels: list<string>}
     */
    public function getScoreWithMissingLabels(int $userId, array $user, ?array $userProfile, ?array $personnelExtras, ?int $tenantId = null): array
    {
        $base = $this->getScore($userId, $user, $userProfile, $personnelExtras, $tenantId);
        $labels = [
            'identity_name' => 'Nom opérateur / RP',
            'identity_callsign' => 'Indicatif',
            'identity_matricule' => 'Matricule',
            'identity_role' => 'Rôle principal (dossier)',
            'identity_unit' => 'Affectation / unité (sélection dossier ou ORBAT)',
            'identity_enlistment' => 'Date d’incorporation',
            'assignment_role' => 'Rôle d’affectation (ORBAT ou rôle principal dossier)',
            'security_clearance' => 'Niveau de clearance',
            'security_review' => 'Clearance revue (date)',
            'qualifications' => 'Qualification OU formation certifiée',
            'readiness' => 'Disponibilité (score dossier ou formation certifiée)',
            'contact_email' => 'Email de contact',
            'civil_identity' => 'Prénom & nom (dossier compte ou candidature)',
        ];
        $missingLabels = [];
        foreach ($base['details'] as $key => $ok) {
            if (empty($ok) && isset($labels[$key])) {
                $missingLabels[] = $labels[$key];
            }
        }
        $base['missing_labels'] = $missingLabels;

        return $base;
    }

    /**
     * Complétude dossier pour l’admin : version « communauté » sans doublons avec le bloc compte
     * (e-mail, prénom/nom déjà suivis côté compte) ni jargon superflu ; version « plateforme » exhaustive.
     *
     * @return array{score: int, sections_critiques: list<string>, details: array<string, bool>, missing_labels: list<string>}
     */
    public function getScoreWithMissingLabelsForAudience(
        int $userId,
        array $user,
        ?array $userProfile,
        ?array $personnelExtras,
        ?int $tenantId,
        bool $forPlatformOperator
    ): array {
        $base = $this->getScore($userId, $user, $userProfile, $personnelExtras, $tenantId);

        $labelsFull = [
            'identity_name' => 'Nom opérateur / RP',
            'identity_callsign' => 'Indicatif',
            'identity_matricule' => 'Matricule',
            'identity_role' => 'Rôle principal (dossier)',
            'identity_unit' => 'Affectation / unité (sélection dossier ou ORBAT)',
            'identity_enlistment' => 'Date d’incorporation',
            'assignment_role' => 'Rôle d’affectation (ORBAT ou rôle principal dossier)',
            'security_clearance' => 'Niveau de clearance',
            'security_review' => 'Clearance revue (date)',
            'qualifications' => 'Qualification OU formation certifiée',
            'readiness' => 'Disponibilité (score dossier ou formation certifiée)',
            'contact_email' => 'Email de contact',
            'civil_identity' => 'Prénom & nom (dossier compte ou candidature)',
        ];

        $labelsCommunity = [
            'identity_name' => 'Nom opérateur / RP',
            'identity_callsign' => 'Indicatif',
            'identity_matricule' => 'Matricule',
            'identity_role' => 'Rôle principal sur la fiche',
            'identity_unit' => 'Affectation ou unité (dossier / organigramme)',
            'identity_enlistment' => 'Date d’incorporation',
            'assignment_role' => 'Rôle d’affectation (organigramme ou fiche)',
            'security_clearance' => 'Niveau de clearance',
            'security_review' => 'Date de revue de la clearance',
            'qualifications' => 'Qualification ou parcours de formation certifié',
            'readiness' => 'Indicateur de disponibilité',
            'contact_email' => 'Email de contact',
            'civil_identity' => 'Prénom & nom (dossier compte ou candidature)',
        ];

        $labels = $forPlatformOperator ? $labelsFull : $labelsCommunity;

        $details = $base['details'];
        if (!$forPlatformOperator) {
            foreach (['contact_email', 'civil_identity'] as $ek) {
                unset($details[$ek]);
            }
        }

        $total = count($details);
        $filled = count(array_filter($details));
        $score = $total > 0 ? (int) round($filled / $total * 100) : 0;

        $missingLabels = [];
        foreach ($details as $key => $ok) {
            if (empty($ok) && isset($labels[$key])) {
                $missingLabels[] = $labels[$key];
            }
        }

        $critical = [
            'identity_matricule' => $forPlatformOperator ? 'Matricule non défini' : 'Matricule non renseigné',
            'identity_unit' => 'Affectation absente',
            'security_clearance' => 'Clearance non définie',
            'assignment_role' => 'Rôle d\'affectation absent',
        ];

        $sectionsCritiques = [];
        foreach ($critical as $key => $label) {
            if (isset($details[$key]) && empty($details[$key])) {
                $sectionsCritiques[] = $label;
            }
        }

        return [
            'score' => min(100, $score),
            'sections_critiques' => $sectionsCritiques,
            'details' => $details,
            'missing_labels' => $missingLabels,
        ];
    }
}
