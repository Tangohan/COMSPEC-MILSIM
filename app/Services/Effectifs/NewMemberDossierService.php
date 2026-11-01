<?php

declare(strict_types=1);

namespace App\Services\Effectifs;

use App\Core\Container;
use App\Repositories\MemberLanguageRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserProfileRepository;
use App\Services\Personnel\JobRolePickerService;
use App\Services\Personnel\PersonnelJobRoleAssignmentsSettings;
use App\Support\CommunityLanguages;

/**
 * Complète la fiche d'un membre juste créé (page « Ajouter un membre ») avec ce que le tableur et la fiche savent
 * gérer : surnom, unité et rôle dans l'unité, emplois, date d'engagement, langues, Discord, notes.
 *
 * Chaque volet n'est appliqué que si l'auteur en a le droit ($rights) ; un volet qui échoue n'annule pas la création
 * (le membre existe déjà) et remonte un avertissement lisible.
 */
final class NewMemberDossierService
{
    /**
     * @param array<string, mixed> $input
     * @param array{profiles:bool, assignments:bool} $rights
     * @return list<string> avertissements
     */
    public function apply(int $tenantId, int $userId, array $input, array $rights): array
    {
        $warnings = [];
        $personnel = Container::get(PersonnelProfileRepository::class);
        try {
            $personnel->ensureRecord($userId, $tenantId);
        } catch (\Throwable) {
        }

        if ($rights['profiles']) {
            $fields = [];
            $nickname = trim((string) ($input['nickname'] ?? ''));
            if ($nickname !== '') {
                $fields['nickname_primary'] = mb_substr($nickname, 0, 60);
            }
            $enlist = self::date((string) ($input['enlistment_date'] ?? ''));
            if ($enlist !== null) {
                $fields['enlistment_date'] = $enlist;
            } elseif (trim((string) ($input['enlistment_date'] ?? '')) !== '') {
                $warnings[] = 'La date d’engagement n’est pas valide : elle n’a pas été enregistrée.';
            }
            $notes = trim(str_replace("\r\n", "\n", (string) ($input['notes'] ?? '')));
            if ($notes !== '') {
                $fields['command_notes'] = mb_substr($notes, 0, 4000);
            }
            if ($fields !== []) {
                try {
                    $personnel->update($userId, $fields);
                    if (isset($fields['enlistment_date'])) {
                        Container::get(\App\Services\Personnel\SeniorityEnrollmentBootstrapService::class)->alignTenureCommunityFromStaffEdit($tenantId, $userId);
                    }
                } catch (\Throwable $e) {
                    error_log('[nouveau-membre] dossier : ' . $e->getMessage());
                    $warnings[] = 'Le surnom, la date d’engagement ou les notes n’ont pas pu être enregistrés.';
                }
            }
            $discord = trim((string) ($input['discord_handle'] ?? ''));
            if ($discord !== '') {
                try {
                    (new UserProfileRepository())->upsert($userId, ['discord_handle' => mb_substr(ltrim($discord, '@'), 0, 64)]);
                } catch (\Throwable) {
                    $warnings[] = 'Le pseudo Discord n’a pas pu être enregistré.';
                }
            }
            $primaryLang = CommunityLanguages::normalize($input['primary_lang'] ?? '');
            if ($primaryLang !== null) {
                try {
                    Container::get(MemberLanguageRepository::class)->save(
                        $tenantId,
                        $userId,
                        $primaryLang,
                        CommunityLanguages::normalizeList(is_array($input['other_langs'] ?? null) ? $input['other_langs'] : []),
                        null
                    );
                } catch (\Throwable) {
                    $warnings[] = 'Les langues n’ont pas pu être enregistrées.';
                }
            }
        }

        if ($rights['assignments']) {
            $unitId = (int) ($input['unit_id'] ?? 0);
            if ($unitId > 0) {
                $unit = Container::get(UnitRepository::class)->findById($unitId, $tenantId);
                if ($unit === null) {
                    $warnings[] = 'L’unité choisie n’existe plus : le membre est créé sans affectation.';
                } else {
                    $roleName = trim((string) ($input['unit_role'] ?? ''));
                    try {
                        $personnel->update($userId, ['primary_unit_id' => $unitId]);
                        Container::get(PersonnelAssignmentRepository::class)->syncPrimaryAssignmentFromDossier(
                            $userId,
                            $unitId,
                            $roleName !== '' ? mb_substr($roleName, 0, 120) : 'Membre',
                            'Fiche créée depuis les effectifs'
                        );
                    } catch (\Throwable $e) {
                        error_log('[nouveau-membre] affectation : ' . $e->getMessage());
                        $warnings[] = 'L’affectation n’a pas pu être enregistrée : refaites-la depuis la fiche.';
                    }
                }
            }
            $rawJobs = is_array($input['job_role_ids'] ?? null) ? array_values($input['job_role_ids']) : [];
            if ($rawJobs !== []) {
                $warnings = array_merge($warnings, $this->applyJobs($tenantId, $userId, $rawJobs, (string) ($input['primary_job_role_id'] ?? '')));
            }
        }

        return $warnings;
    }

    /**
     * @param list<mixed> $rawJobs
     * @return list<string>
     */
    private function applyJobs(int $tenantId, int $userId, array $rawJobs, string $rawPrimary): array
    {
        $jobs = Container::get(PersonnelJobRoleRepository::class);
        if (!$jobs->tablesExist() || !$jobs->pivotTableExists()) {
            return ['Les emplois ne sont pas encore disponibles dans cette communauté.'];
        }
        try {
            $settings = PersonnelJobRoleAssignmentsSettings::resolve((new TenantRepository())->getSettings($tenantId));
            $max = max(1, (int) $settings['max_roles_per_member']);
            $picker = new JobRolePickerService($jobs);
            $ids = array_slice($picker->resolveValues($tenantId, array_slice($rawJobs, 0, $max)), 0, $max);
            if ($ids === []) {
                return [];
            }
            $primary = str_starts_with($rawPrimary, JobRolePickerService::CATALOG_PREFIX)
                ? $picker->materializeCatalogRole($tenantId, substr($rawPrimary, strlen(JobRolePickerService::CATALOG_PREFIX)))
                : (int) $rawPrimary;
            if (!in_array($primary, $ids, true)) {
                $primary = $ids[0];
            }
            $jobs->replaceUserPivotJobRoles($tenantId, $userId, array_map(static fn (int $id): array => [
                'personnel_job_role_id' => $id,
                'role_detail' => '',
                'is_primary' => $id === $primary,
            ], $ids));
        } catch (\Throwable $e) {
            error_log('[nouveau-membre] emplois : ' . $e->getMessage());

            return ['Les emplois n’ont pas pu être enregistrés : refaites-le depuis la fiche.'];
        }

        return [];
    }

    /** Date AAAA-MM-JJ plausible (1990 → aujourd'hui + 1 an), sinon null. */
    public static function date(string $raw): ?string
    {
        $raw = trim($raw);
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        if ($d === false || $d->format('Y-m-d') !== $raw) {
            return null;
        }
        $year = (int) $d->format('Y');

        return $year >= 1990 && $d <= new \DateTimeImmutable('+1 year') ? $raw : null;
    }
}
