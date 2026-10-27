<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\GradeRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;

/**
 * Transforme une valeur de fiche (souvent un identifiant ou une liste) en texte lisible.
 */
final class PersonnelFieldValueFormatter
{
    /** @var array<int, array<int, array{path: string, command_key: string, depth: int}>> */
    private array $unitPaths = [];

    public function __construct(
        private ?UnitRepository $units = null,
        private ?PersonnelJobRoleRepository $jobRoles = null,
        private ?GradeRepository $grades = null,
        private ?UserRepository $users = null,
    ) {
    }

    public static function emptyPhrase(string $key): string
    {
        return match ($key) {
            'unit_assignments' => 'Aucune équipe',
            'job_roles' => 'Aucun emploi',
            'grade_id' => 'Aucun grade',
            'rp_tutor_user_id' => 'Aucun tuteur',
            default => 'Non renseigné',
        };
    }

    public function display(string $key, mixed $value, ?int $tenantId = null): string
    {
        return implode(' · ', $this->lines($key, $value, $tenantId));
    }

    /**
     * @return list<string>
     */
    public function lines(string $key, mixed $value, ?int $tenantId = null): array
    {
        if ($key === 'unit_assignments') {
            return $this->unitLines(PersonnelCorrectionRequestService::decodeAssignmentRows($value), $tenantId);
        }
        if ($key === 'job_roles') {
            return $this->jobLines(PersonnelCorrectionRequestService::decodeJobRoleRows($value), $tenantId);
        }
        $text = $this->scalar($key, $value, $tenantId);

        return $text === '' ? [] : [$text];
    }

    private function scalar(string $key, mixed $value, ?int $tenantId): string
    {
        if ($key === 'grade_id') {
            $gid = (int) $value;
            if ($gid < 1) {
                return '';
            }
            try {
                $grade = $this->grades()->findById($gid);
            } catch (\Throwable) {
                $grade = null;
            }
            if (!$grade) {
                return 'Grade introuvable';
            }
            $label = trim((string) ($grade['label_long'] ?? $grade['label_short'] ?? $grade['name'] ?? ''));

            return $label !== '' ? $label : 'Grade introuvable';
        }
        if ($key === 'deployable') {
            return ((string) $value === '1') ? 'Oui' : 'Non';
        }
        if ($key === 'rp_recruitment_origin') {
            return match (trim((string) $value)) {
                'internal' => 'Interne',
                'external' => 'Externe',
                default => trim((string) $value),
            };
        }
        if ($key === 'rp_tutor_user_id') {
            $tid = (int) $value;
            if ($tid < 1) {
                return '';
            }
            try {
                $user = $this->users()->findById($tid, $tenantId);
            } catch (\Throwable) {
                $user = null;
            }
            if (!$user) {
                return 'Tuteur introuvable';
            }

            return $this->displayName($user);
        }

        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        return trim((string) preg_replace('/\s+/u', ' ', str_replace(["\r\n", "\r", "\n"], ' · ', $text)));
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private function unitLines(array $rows, ?int $tenantId): array
    {
        $parts = [];
        foreach ($rows as $row) {
            $unitId = (int) ($row['unit_id'] ?? 0);
            if ($unitId < 1) {
                continue;
            }
            $name = $this->unitName($unitId, $tenantId);
            $role = trim((string) ($row['role_name'] ?? ''));
            $bit = $role !== '' ? $name . ' — ' . $role : $name;
            if (!empty($row['is_primary'])) {
                $bit .= ' — équipe principale';
            }
            $parts[] = $bit;
        }

        return $parts;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private function jobLines(array $rows, ?int $tenantId): array
    {
        $parts = [];
        foreach ($rows as $row) {
            $roleId = (int) ($row['role_id'] ?? $row['personnel_job_role_id'] ?? 0);
            if ($roleId < 1) {
                continue;
            }
            $name = 'Emploi introuvable';
            if ($tenantId !== null && $tenantId > 0) {
                try {
                    $jr = $this->jobRoles()->findRoleById($roleId, $tenantId);
                } catch (\Throwable) {
                    $jr = null;
                }
                $found = trim((string) ($jr['name'] ?? ''));
                if ($found !== '') {
                    $name = $found;
                }
            }
            $detail = trim((string) ($row['detail'] ?? $row['role_detail'] ?? ''));
            $bit = $detail !== '' ? $name . ' — ' . $detail : $name;
            if (!empty($row['is_primary'])) {
                $bit .= ' — emploi principal';
            }
            $parts[] = $bit;
        }

        return $parts;
    }

    private function unitName(int $unitId, ?int $tenantId): string
    {
        if ($tenantId !== null && $tenantId > 0) {
            try {
                if (!isset($this->unitPaths[$tenantId])) {
                    $this->unitPaths[$tenantId] = $this->units()->hierarchyMetaByUnitId($tenantId);
                }
                $path = trim((string) ($this->unitPaths[$tenantId][$unitId]['path'] ?? ''));
                if ($path !== '') {
                    return $path;
                }
            } catch (\Throwable) {
                // Le nom seul suffit si l’organigramme n’est pas lisible.
            }
        }
        try {
            $unit = $this->units()->findById($unitId, $tenantId);
        } catch (\Throwable) {
            $unit = null;
        }
        $name = trim((string) ($unit['name'] ?? ''));

        return $name !== '' ? $name : 'Équipe introuvable';
    }

    /** @param array<string, mixed> $user */
    private function displayName(array $user): string
    {
        $name = trim((string) ($user['display_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        $email = trim((string) ($user['email'] ?? ''));

        return $email !== '' ? $email : 'Membre';
    }

    private function units(): UnitRepository
    {
        return $this->units ??= new UnitRepository();
    }

    private function jobRoles(): PersonnelJobRoleRepository
    {
        return $this->jobRoles ??= new PersonnelJobRoleRepository();
    }

    private function grades(): GradeRepository
    {
        return $this->grades ??= new GradeRepository();
    }

    private function users(): UserRepository
    {
        return $this->users ??= new UserRepository();
    }
}
