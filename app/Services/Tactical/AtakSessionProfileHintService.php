<?php

declare(strict_types=1);

namespace App\Services\Tactical;

use App\Repositories\GradeRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\PositionRepository;
use App\Repositories\RoleRepository;

/**
 * Propose un rôle / des spécialités de session ATAK à partir du dossier effectifs
 * (rôles métier, affectations, postes) et du compte Athena.
 */
final class AtakSessionProfileHintService
{
    /**
     * @param array<string, mixed>|null $currentUser
     * @return array{
     *   suggestedRole: string,
     *   suggestedSpecialties: list<string>,
     *   hasSuggestionBasis: bool
     * }
     */
    public function build(?array $currentUser, int $tenantId): array
    {
        $suggestedRole = 'operator';
        $suggestedSpecialties = [];
        if (!$currentUser || (int) ($currentUser['id'] ?? 0) < 1) {
            return [
                'suggestedRole' => $suggestedRole,
                'suggestedSpecialties' => $suggestedSpecialties,
                'hasSuggestionBasis' => false,
            ];
        }

        $userId = (int) $currentUser['id'];
        $parts = [
            (string) ($currentUser['display_name'] ?? ''),
            (string) ($currentUser['callsign'] ?? ''),
            (string) ($currentUser['arma_callsign'] ?? ''),
            (string) ($currentUser['professional_category_code'] ?? ''),
        ];
        $slugs = [];
        $isUnitCommander = false;

        $roleId = (int) ($currentUser['role_id'] ?? 0);
        if ($roleId > 0) {
            try {
                $role = (new RoleRepository())->findById($roleId, $tenantId > 0 ? $tenantId : null);
                if ($role) {
                    $parts[] = (string) ($role['name'] ?? '');
                    $parts[] = (string) ($role['slug'] ?? '');
                    $parts[] = (string) ($role['label_en'] ?? '');
                    $slug = trim((string) ($role['slug'] ?? ''));
                    if ($slug !== '') {
                        $slugs[] = mb_strtolower($slug);
                    }
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        $gradeId = (int) ($currentUser['grade_id'] ?? 0);
        if ($gradeId > 0) {
            try {
                $grade = (new GradeRepository())->findById($gradeId);
                if ($grade) {
                    $parts[] = (string) ($grade['label_short'] ?? '');
                    $parts[] = (string) ($grade['label_long'] ?? '');
                    $parts[] = (string) ($grade['label_otan'] ?? '');
                    $parts[] = (string) ($grade['category_label'] ?? '');
                } else {
                    $legacy = (new GradeRepository())->findByIdLegacy($gradeId, $tenantId > 0 ? $tenantId : null);
                    if ($legacy) {
                        $parts[] = (string) ($legacy['short_name'] ?? $legacy['label_short'] ?? '');
                        $parts[] = (string) ($legacy['name'] ?? $legacy['label_long'] ?? '');
                    }
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        if ($tenantId > 0) {
            try {
                $jobRepo = new PersonnelJobRoleRepository();
                $byUser = $jobRepo->listPivotAssignmentsForUsers($tenantId, [$userId]);
                foreach ($byUser[$userId] ?? [] as $jobRow) {
                    $parts[] = (string) ($jobRow['role_name'] ?? '');
                    $parts[] = (string) ($jobRow['role_detail'] ?? '');
                    $parts[] = (string) ($jobRow['role_label_en'] ?? '');
                    $slug = trim((string) ($jobRow['role_slug'] ?? ''));
                    if ($slug !== '') {
                        $parts[] = $slug;
                        $slugs[] = mb_strtolower($slug);
                    }
                    $jobRoleId = (int) ($jobRow['personnel_job_role_id'] ?? 0);
                    if ($jobRoleId > 0) {
                        $full = $jobRepo->findRoleById($jobRoleId, $tenantId);
                        if ($full) {
                            $parts[] = (string) ($full['description'] ?? '');
                            $parts[] = (string) ($full['mos_code'] ?? '');
                            $parts[] = (string) ($full['mos_specialty_title'] ?? '');
                            $catId = (int) ($full['category_id'] ?? 0);
                            if ($catId > 0) {
                                $cat = $jobRepo->findCategoryById($catId, $tenantId);
                                if ($cat) {
                                    $parts[] = (string) ($cat['name'] ?? '');
                                    $parts[] = (string) ($cat['slug'] ?? '');
                                }
                            }
                        }
                    }
                }
            } catch (\Throwable) {
                // ignore
            }

            try {
                $profile = (new PersonnelProfileRepository())->getByUserId($userId);
                if (is_array($profile)) {
                    $parts[] = (string) ($profile['primary_role'] ?? '');
                    $parts[] = (string) ($profile['equipment_class'] ?? '');
                    $parts[] = (string) ($profile['weapon_specialty'] ?? '');
                    $parts[] = (string) ($profile['rp_operational_function'] ?? '');
                    $parts[] = (string) ($profile['administrative_position'] ?? '');
                    $parts[] = (string) ($profile['kit_assigned'] ?? '');
                }
            } catch (\Throwable) {
                // ignore
            }

            try {
                $assignments = (new PersonnelAssignmentRepository())->listActiveForUserResolved($userId);
                foreach ($assignments as $asg) {
                    $parts[] = (string) ($asg['role_name'] ?? '');
                    $parts[] = (string) ($asg['unit_name'] ?? '');
                    if ((int) ($asg['commander_user_id'] ?? 0) === $userId) {
                        $isUnitCommander = true;
                    }
                }
            } catch (\Throwable) {
                // ignore
            }

            try {
                $positions = (new PositionRepository())->listActiveForUser($tenantId, $userId);
                foreach ($positions as $pos) {
                    $parts[] = (string) ($pos['position_name'] ?? '');
                    $parts[] = (string) ($pos['position_category'] ?? '');
                    $parts[] = (string) ($pos['position_description'] ?? '');
                    $parts[] = (string) ($pos['description'] ?? '');
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        $blob = $this->normalizeBlob(implode(' ', array_filter(array_map('trim', $parts))));
        $slugBlob = $this->normalizeBlob(implode(' ', $slugs));

        $isDeputy = $this->matchesDeputy($blob, $slugBlob);
        $isCommanderFromPersonnel = $isUnitCommander || $this->matchesCommander($blob, $slugBlob);
        $isCommander = $isCommanderFromPersonnel
            || (function_exists('can') && (can('admin.access') || can('admin.organization')));

        if ($isDeputy) {
            $suggestedRole = 'deputy';
        } elseif ($isCommander) {
            $suggestedRole = 'commander';
        }

        $addSpec = static function (string $id) use (&$suggestedSpecialties): void {
            if (!in_array($id, $suggestedSpecialties, true)) {
                $suggestedSpecialties[] = $id;
            }
        };

        if ($this->matchesMedic($blob, $slugBlob)) {
            $addSpec('medic');
        }
        if ($this->matchesJtac($blob, $slugBlob)) {
            $addSpec('jtac');
        }
        if ($this->matchesRadio($blob, $slugBlob)) {
            $addSpec('radio');
        }

        $hasBasis = $isDeputy || $isCommanderFromPersonnel || $suggestedSpecialties !== [];

        return [
            'suggestedRole' => $suggestedRole,
            'suggestedSpecialties' => $suggestedSpecialties,
            'hasSuggestionBasis' => $hasBasis,
        ];
    }

    private function normalizeBlob(string $raw): string
    {
        $blob = mb_strtolower($raw);
        $blob = str_replace(
            ['é', 'è', 'ê', 'ë', 'à', 'â', 'ù', 'û', 'ü', 'ô', 'ö', 'î', 'ï', 'ç', 'œ', 'æ', '–', '—', '_', '-'],
            ['e', 'e', 'e', 'e', 'a', 'a', 'u', 'u', 'u', 'o', 'o', 'i', 'i', 'c', 'oe', 'ae', ' ', ' ', ' ', ' '],
            $blob
        );
        $blob = preg_replace('/\s+/u', ' ', $blob) ?? $blob;

        return trim($blob);
    }

    private function matchesDeputy(string $blob, string $slugBlob): bool
    {
        $hay = $blob . ' ' . $slugBlob;

        return (bool) preg_match(
            '/\b(adjoint|commandant\s+adjoint|2ic|xo|deputy|second\s+in\s+command|assistant\s+chef|sous\s+chef)\b/u',
            $hay
        );
    }

    private function matchesCommander(string $blob, string $slugBlob): bool
    {
        $hay = $blob . ' ' . $slugBlob;

        if (preg_match(
            '/\b(commandant|commandeur|commandement|chef\s+d[e\']?\s*unite|chef\s+de\s+(section|groupe|equipe|peloton|compagnie|bataillon)|squad\s*lead(?:er)?|team\s*lead(?:er)?|platoon\s*lead(?:er)?|company\s*commander|battalion\s*commander|cdt|sl|tl)\b/u',
            $hay
        )) {
            return true;
        }

        // Slugs catalogue (ex. command_unit_commander, infantry_section_chief, logistics_convoy_chief).
        if (preg_match('/\b(command\s+\w+|[\w ]+\s+commander|[\w ]+\s+chief|infantry\s+section\s+chief)\b/u', $slugBlob)) {
            return true;
        }

        // Officiers clairement « conduite / opérations », pas tout libellé contenant « officier ».
        return (bool) preg_match(
            '/\b(officier\s+(operations|operation|ops|commandement|etat\s+major)|operations\s+officer|staff\s+officer|etat\s+major)\b/u',
            $hay
        );
    }

    private function matchesMedic(string $blob, string $slugBlob): bool
    {
        $hay = $blob . ' ' . $slugBlob;
        if (preg_match('/\bmedical\b/u', $slugBlob) || preg_match('/\bmedic\b/u', $slugBlob)) {
            return true;
        }

        return (bool) preg_match(
            '/\b(medic|medecin|medical|corpsman|infirmier|secouriste|sante|soignant|paramedic|tccc|combat\s+medic)\b/u',
            $hay
        );
    }

    private function matchesJtac(string $blob, string $slugBlob): bool
    {
        $hay = $blob . ' ' . $slugBlob;
        if (preg_match('/\bjtac\b/u', $slugBlob) || preg_match('/\bfires\s+jtac\b/u', $slugBlob)) {
            return true;
        }

        return (bool) preg_match(
            '/\b(jtac|fac|jfo|cas\s*control|controleur\s+aerien|forward\s+air|observateur\s+avance|appui\s+aerien)\b/u',
            $hay
        );
    }

    private function matchesRadio(string $blob, string $slugBlob): bool
    {
        $hay = $blob . ' ' . $slugBlob;
        if (preg_match('/\b(radio|rto|comms|telecom|transmetteur|signal\s+operator|signal\s+support)\b/u', $slugBlob)) {
            return true;
        }

        return (bool) preg_match(
            '/\b(radio|rto|transmetteur|transmissions|communications|comms|telecoms|operateur\s+radio|signal\s+support)\b/u',
            $hay
        );
    }
}
