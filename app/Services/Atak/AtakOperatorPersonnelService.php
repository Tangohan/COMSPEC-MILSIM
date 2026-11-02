<?php

declare(strict_types=1);

namespace App\Services\Atak;

use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\Organization\OrgVisibilityService;
use App\Services\Personnel\MemberNameService;
use App\Services\Personnel\MemberNameSettings;
use App\Support\ConfidentialDisplay;
use App\Support\MemberName;
use App\Support\OrgVisibilityCapabilities;
use App\Support\VisibilityLevel;

/**
 * Situation actuelle (site) des comptes liés aux opérateurs ATAK : nom « Prénom N. » + trigramme, grade du
 * référentiel, fonction tenue (attributions de fonctions) et unité d’affectation (chaîne ORBAT).
 *
 * Remplace, sur les pages ATAK, le « rôle » qui remontait du jeu : celui-ci n’est plus qu’un rôle déclaré en jeu,
 * affiché à part quand il diffère ({@see self::declaredRole()}). La confidentialité de l’ORBAT s’applique comme
 * sur le tableau des fonctions : membre masqué ou anonymisé sans identité, affectation restreinte remplacée.
 */
final class AtakOperatorPersonnelService
{
    /** Rôles génériques que le jeu envoie faute de mieux : ce n’est pas une déclaration. */
    private const GENERIC_GAME_ROLES = ['operator', 'operateur', 'membre', 'member'];

    public function __construct(
        private PersonnelJobRoleRepository $jobRoles,
        private UserRepository $users,
        private UnitRepository $units,
        private ?MemberNameService $names = null,
    ) {
    }

    /**
     * @param list<int> $userIds
     * @return array<int, array{
     *   state: string, name: string, trigram: string, grade_short: string, grade_long: string,
     *   function_label: string, functions: list<array{id: int, name: string}>, unit_id: int, unit_label: string,
     *   unit_path: string, unit_chain: list<array{id: int, name: string}>, masked_label: string
     * }> state : visible | anonymized | hidden
     */
    public function forUsers(int $tenantId, array $userIds, OrgVisibilityCapabilities $caps): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id): bool => $id > 0)));
        if ($tenantId < 1 || $userIds === []) {
            return [];
        }
        $rich = [];
        foreach ($this->attempt(fn (): array => $this->users->listEffectifsRosterByIds($tenantId, $userIds)) as $r) {
            $rich[(int) ($r['id'] ?? 0)] = $r;
        }
        $visibility = $this->attempt(fn (): array => $this->units->personnelVisibilityByUserIdsForTenant($tenantId, $userIds));
        $pivot = $this->attempt(fn (): array => $this->jobRoles->pivotTableExists() ? $this->jobRoles->listPivotAssignmentsForUsers($tenantId, $userIds) : []);
        $identities = $this->attempt(fn (): array => ($this->names ?? new MemberNameService())->identitiesFor($tenantId, $userIds));
        $unitRows = $this->attempt(fn (): array => $this->units->listFlatForStructure($tenantId, true));
        $trigramsEnabled = (bool) (MemberNameSettings::forTenant($tenantId)['trigrams_enabled'] ?? true);

        $out = [];
        foreach ($userIds as $uid) {
            $out[$uid] = self::describe(
                $rich[$uid] ?? [],
                $visibility[$uid] ?? [],
                $identities[$uid] ?? null,
                $pivot[$uid] ?? [],
                $unitRows,
                $caps,
                $trigramsEnabled
            );
        }

        return $out;
    }

    /**
     * Ligne d’affichage d’un compte, confidentialité appliquée (sans base : testable).
     *
     * @param array<string, mixed> $rich ligne listEffectifsRosterByIds (grade, unité, fonction principale)
     * @param array<string, mixed> $visibility visibility_level, assignment_visibility, anonymized_label
     * @param array<string, mixed>|null $identity prénom, nom, trigramme (MemberNameService::identitiesFor)
     * @param list<array<string, mixed>> $jobs attributions de fonctions (pivot), principale d’abord
     * @param list<array<string, mixed>> $unitRows unités de la communauté (id, name, parent_id, visibilité)
     * @return array{
     *   state: string, name: string, trigram: string, grade_short: string, grade_long: string,
     *   function_label: string, functions: list<array{id: int, name: string}>, unit_id: int, unit_label: string,
     *   unit_path: string, unit_chain: list<array{id: int, name: string}>, masked_label: string
     * }
     */
    public static function describe(
        array $rich,
        array $visibility,
        ?array $identity,
        array $jobs,
        array $unitRows,
        OrgVisibilityCapabilities $caps,
        bool $trigramsEnabled = true,
    ): array {
        $empty = ['state' => 'visible', 'name' => '', 'trigram' => '', 'grade_short' => '', 'grade_long' => '',
            'function_label' => '', 'functions' => [], 'unit_id' => 0, 'unit_label' => '', 'unit_path' => '', 'unit_chain' => [], 'masked_label' => ''];
        $staff = $caps->bypassAll || $caps->viewHiddenUnits || $caps->viewRestrictedUnits;
        $levels = OrgVisibilityService::effectiveUnitLevels($unitRows);
        $unitById = [];
        foreach ($unitRows as $u) {
            $unitById[(int) ($u['id'] ?? 0)] = $u;
        }
        $unitId = (int) ($rich['unit_id'] ?? 0);
        $filtered = OrgVisibilityService::filterPersonnelRow([
            'user_id' => (int) ($rich['id'] ?? 0),
            'visibility_level' => (string) ($visibility['visibility_level'] ?? VisibilityLevel::NORMAL),
            'assignment_visibility' => (string) ($visibility['assignment_visibility'] ?? VisibilityLevel::NORMAL),
            'anonymized_label' => (string) ($visibility['anonymized_label'] ?? ''),
            'display_name' => (string) ($rich['display_name'] ?? ''),
            'callsign' => $rich['callsign'] ?? null,
            'unit_name' => $rich['unit_name'] ?? null,
        ], $caps, $unitId > 0 ? ($levels[$unitId] ?? VisibilityLevel::NORMAL) : VisibilityLevel::NORMAL);

        if ($filtered === null) {
            return ['state' => 'hidden', 'masked_label' => ConfidentialDisplay::label(ConfidentialDisplay::IDENTITY_RESTRICTED)] + $empty;
        }
        if (!empty($filtered['anonymized'])) {
            return ['state' => 'anonymized', 'masked_label' => (string) ($filtered['display_name'] ?? ConfidentialDisplay::personLabel())] + $empty;
        }

        $nameRow = MemberName::withIdentity(['display_name' => (string) ($rich['display_name'] ?? ''), 'callsign' => (string) ($rich['callsign'] ?? '')], $identity);
        $name = MemberName::short($nameRow);
        $trigram = $trigramsEnabled ? MemberName::trigram($nameRow) : '';

        // Fonction tenue : attribution principale (avec précision), sinon la première.
        $functions = [];
        $functionLabel = '';
        foreach ($jobs as $job) {
            $rid = (int) ($job['personnel_job_role_id'] ?? 0);
            if ($rid < 1 || isset($functions[$rid])) {
                continue;
            }
            $jobName = trim((string) ($job['role_name'] ?? ''));
            $functions[$rid] = ['id' => $rid, 'name' => $jobName];
            if ($functionLabel === '') {
                $detail = trim((string) ($job['role_detail'] ?? ''));
                $functionLabel = $jobName !== '' && $detail !== '' ? $jobName . ' — ' . $detail : ($jobName !== '' ? $jobName : $detail);
            }
        }
        if ($functionLabel === '') {
            $functionLabel = trim((string) ($rich['job_role_display'] ?? ''));
        }

        $unit = ['id' => 0, 'label' => '', 'path' => '', 'chain' => []];
        if (!empty($filtered['assignment_redacted'])) {
            $unit['label'] = ConfidentialDisplay::label(ConfidentialDisplay::ASSIGNMENT_RESTRICTED);
        } elseif ($unitId > 0 && isset($unitById[$unitId])) {
            $level = $levels[$unitId] ?? VisibilityLevel::NORMAL;
            if ($level === VisibilityLevel::ANONYMIZED && !$staff) {
                $unit['label'] = ConfidentialDisplay::label(ConfidentialDisplay::UNIT_ANONYMIZED);
            } else {
                $chain = [];
                $guard = 0;
                for ($id = $unitId; $id > 0 && isset($unitById[$id]) && !in_array($id, $chain, true) && $guard++ < 40; $id = (int) ($unitById[$id]['parent_id'] ?? 0)) {
                    array_unshift($chain, $id);
                }
                $names = [];
                $visibleChain = [];
                foreach ($chain as $cid) {
                    $cl = $levels[$cid] ?? VisibilityLevel::NORMAL;
                    if (($cl === VisibilityLevel::HIDDEN && !$caps->canSeeUnitLevel(VisibilityLevel::HIDDEN)) || ($cl === VisibilityLevel::ANONYMIZED && !$staff)) {
                        continue;
                    }
                    $names[] = (string) ($unitById[$cid]['name'] ?? '');
                    $visibleChain[] = ['id' => (int) $cid, 'name' => (string) ($unitById[$cid]['name'] ?? '')];
                }
                $unit = ['id' => $unitId, 'label' => (string) ($unitById[$unitId]['name'] ?? ''), 'path' => implode(' / ', $names), 'chain' => $visibleChain];
            }
        } elseif (trim((string) ($rich['unit_name'] ?? '')) !== '') {
            $unit['label'] = trim((string) $rich['unit_name']);
        }

        return [
            'state' => 'visible',
            'name' => $name === MemberName::FALLBACK ? '' : $name,
            'trigram' => $trigram,
            'grade_short' => trim((string) ($rich['grade_short'] ?? '')),
            'grade_long' => trim((string) ($rich['grade_long'] ?? '')),
            'function_label' => $functionLabel,
            'functions' => array_values($functions),
            'unit_id' => (int) $unit['id'],
            'unit_label' => (string) $unit['label'],
            'unit_path' => (string) $unit['path'],
            'unit_chain' => $unit['chain'],
            'masked_label' => '',
        ];
    }

    /**
     * Rôle déclaré par le jeu (rôle personnalisé, description de slot, équipe de feu), à afficher à part.
     * Vide quand le jeu n’envoie qu’un rôle générique. « differs » : il ne correspond pas à la fonction tenue.
     *
     * @return array{label: string, differs: bool}
     */
    public static function declaredRole(string $gameRole, string $functionLabel): array
    {
        $role = trim($gameRole);
        $fold = self::fold($role);
        if ($fold === '' || in_array($fold, self::GENERIC_GAME_ROLES, true)) {
            return ['label' => '', 'differs' => false];
        }
        $fn = self::fold($functionLabel);
        if ($fn === '') {
            return ['label' => $role, 'differs' => false];
        }
        $same = $fn === $fold || str_contains($fn, $fold) || str_contains($fold, $fn);

        return ['label' => $same ? '' : $role, 'differs' => !$same];
    }

    private static function fold(string $s): string
    {
        $s = mb_strtolower(trim($s));
        if (function_exists('iconv')) {
            $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if (is_string($t) && $t !== '') {
                $s = $t;
            }
        }

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $s));
    }

    /**
     * @param callable(): array<mixed> $fn
     * @return array<mixed>
     */
    private function attempt(callable $fn): array
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            error_log('ATAK operators personnel: ' . $e->getMessage());

            return [];
        }
    }
}
