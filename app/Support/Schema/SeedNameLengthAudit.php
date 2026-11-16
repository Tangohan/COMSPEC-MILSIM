<?php

declare(strict_types=1);

namespace App\Support\Schema;

use App\Authorization\TenantPermissionCatalog;
use App\Controllers\Web\CommunityController;
use App\Repositories\SseTagRepository;
use App\Services\Community\CommunityOnboardingValidationService;
use App\Services\Community\TenantDefaultRoleDefinitions;
use App\Services\Community\TenantSeedHelper;
use App\Services\Community\TenantTypeConfig;
use App\Services\Community\WizardOrbatPresets;
use App\Services\OrganizationCatalog\OrganizationKitDefinitions;
use App\Services\Personnel\JobRoleEnrichmentService;
use App\Services\Personnel\UnitJobRoleSyncService;
use App\Services\Rbac\CommunityAccessProfiles;
use App\Support\SseAttachCatalog;
use App\Support\SseFieldNoteTagCatalog;

/**
 * Contrôle sans base MySQL des libellés semés à la création d'une communauté : chaque valeur écrite dans une colonne
 * « name » (et name_fr / name_en des étiquettes SSE) doit tenir dans la colonne la plus étroite déclarée par les migrations
 * (migrations/*.sql, database/steps, bootstrap/*migration*.php). Une valeur plus longue fait échouer la création en MySQL
 * strict (SQLSTATE 22001, erreur 1406) et annule toute la transaction.
 *
 * Deux familles de contrôles :
 *  - sources : listes semées (catalogue des droits, niveaux d'accès, panneaux, modèles d'organisation, ORBAT de l'assistant…) ;
 *  - bornes : longueurs maximales que le code impose aux valeurs saisies ou dérivées (troncature, validation de formulaire).
 */
final class SeedNameLengthAudit
{
    private const COLUMNS = ['name', 'name_fr', 'name_en'];

    /**
     * Longueur la plus étroite de chaque colonne suivie, toutes migrations confondues.
     *
     * @return array<string, int> « table.colonne » => longueur
     */
    public static function columnLimits(string $root): array
    {
        $files = array_merge(
            self::globAll($root . '/migrations', 'sql'),
            glob($root . '/database/steps/*.php') ?: [],
            glob($root . '/bootstrap/*migration*.php') ?: []
        );
        $limits = [];
        $keep = static function (string $table, string $column, int $len) use (&$limits): void {
            $key = strtolower($table) . '.' . strtolower($column);
            if ($len > 0 && (!isset($limits[$key]) || $len < $limits[$key])) {
                $limits[$key] = $len;
            }
        };
        $cols = implode('|', self::COLUMNS);
        foreach ($files as $file) {
            $sql = (string) file_get_contents($file);
            // CREATE TABLE … ( … ) : colonnes jusqu'à la parenthèse fermante de premier niveau.
            if (preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?[`"]?(\w+)[`"]?\s*\(/i', $sql, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
                foreach ($m as $hit) {
                    $body = self::parenthesised($sql, $hit[0][1] + strlen($hit[0][0]) - 1);
                    if (preg_match_all('/(?:^|[\s,(])[`"]?(' . $cols . ')[`"]?\s+varchar\s*\(\s*(\d+)\s*\)/i', $body, $c, PREG_SET_ORDER)) {
                        foreach ($c as $col) {
                            $keep($hit[1][0], $col[1], (int) $col[2]);
                        }
                    }
                }
            }
            // ALTER TABLE t ADD / MODIFY / CHANGE [COLUMN] [IF NOT EXISTS] [ancien] col VARCHAR(n)
            if (preg_match_all('/ALTER\s+TABLE\s+[`"]?(\w+)[`"]?([^;]*)/i', $sql, $m, PREG_SET_ORDER)) {
                foreach ($m as $alter) {
                    if (preg_match_all('/\b(?:ADD|MODIFY|CHANGE)\s+(?:COLUMN\s+)?(?:IF\s+NOT\s+EXISTS\s+)?(?:[`"]?\w+[`"]?\s+)?[`"]?(' . $cols . ')[`"]?\s+varchar\s*\(\s*(\d+)\s*\)/i', $alter[2], $c, PREG_SET_ORDER)) {
                        foreach ($c as $col) {
                            $keep($alter[1], $col[1], (int) $col[2]);
                        }
                    }
                }
            }
            // Étapes PHP : addColumnIfMissing('table', …) avec un tableau 'colonne' => "VARCHAR(n) …".
            if (preg_match_all('/addColumnIfMissing\(\s*[\'"](\w+)[\'"]/', $sql, $t) && count(array_unique($t[1])) === 1
                && preg_match_all('/[\'"](' . $cols . ')[\'"]\s*=>\s*[\'"]VARCHAR\((\d+)\)/i', $sql, $c, PREG_SET_ORDER)) {
                foreach ($c as $col) {
                    $keep($t[1][0], $col[1], (int) $col[2]);
                }
            }
        }
        ksort($limits);

        return $limits;
    }

    /**
     * Valeurs semées, par colonne cible.
     *
     * @return list<array{column: string, origin: string, values: list<string>}>
     */
    public static function sources(): array
    {
        $names = static fn (array $rows, string $key = 'name'): array => array_values(array_map(
            static fn ($r): string => (string) (is_array($r) ? ($r[$key] ?? '') : $r),
            $rows
        ));
        $basePerms = [];
        foreach (TenantTypeConfig::basePermissionsByType() as $list) {
            foreach ($list as $p) {
                $basePerms[] = (string) $p['name'];
            }
        }
        $presetUnits = [];
        foreach (WizardOrbatPresets::all() as $preset) {
            $presetUnits = array_merge($presetUnits, $names($preset['units']));
        }
        $kitUnits = $kitCats = $kitJobs = $kitRoles = [];
        foreach (array_merge(OrganizationKitDefinitions::officialKits(), OrganizationKitDefinitions::genericKits()) as $kit) {
            $kitUnits = array_merge($kitUnits, $names($kit['units'] ?? []));
            $kitCats = array_merge($kitCats, $names($kit['job_role_categories'] ?? []));
            $kitJobs = array_merge($kitJobs, $names($kit['job_roles'] ?? []));
            $kitRoles = array_merge($kitRoles, $names($kit['roles'] ?? []));
        }
        $sseFr = $sseEn = [];
        foreach (SseFieldNoteTagCatalog::TAGS as $tag) {
            $sseFr[] = (string) $tag[2];
            $sseEn[] = (string) $tag[3];
        }

        return [
            ['column' => 'permissions.name', 'origin' => 'TenantPermissionCatalog::definitions()', 'values' => $names(TenantPermissionCatalog::definitions())],
            ['column' => 'permissions.name', 'origin' => 'TenantTypeConfig::basePermissionsByType()', 'values' => $basePerms],
            ['column' => 'permissions.name', 'origin' => 'TenantSeedHelper (documents, formations, administration)', 'values' => array_merge(
                array_column(TenantSeedHelper::DOCUMENT_PERMISSIONS, 1),
                array_column(TenantSeedHelper::TRAINING_PERMISSIONS, 1),
                ['Accès administration', 'Administration organisationnelle']
            )],
            ['column' => 'roles.name', 'origin' => 'CommunityAccessProfiles::definitions()', 'values' => $names(CommunityAccessProfiles::definitions())],
            ['column' => 'roles.name', 'origin' => 'TenantDefaultRoleDefinitions (rôles système)', 'values' => $names(array_merge(
                TenantDefaultRoleDefinitions::governanceRoles(),
                TenantDefaultRoleDefinitions::operationalRoles(),
                TenantDefaultRoleDefinitions::organicStaffRoles()
            ))],
            ['column' => 'roles.name', 'origin' => 'OrganizationKitDefinitions (rôles)', 'values' => $kitRoles],
            ['column' => 'personnel_admin_panels.name', 'origin' => 'TenantSeedHelper::PERSONNEL_PANELS', 'values' => array_column(TenantSeedHelper::PERSONNEL_PANELS, 0)],
            ['column' => 'document_categories.name', 'origin' => 'TenantSeedHelper::DOCUMENT_CATEGORIES', 'values' => array_column(TenantSeedHelper::DOCUMENT_CATEGORIES, 0)],
            ['column' => 'equipment_classes.name', 'origin' => 'TenantSeedHelper::EQUIPMENT_CLASSES', 'values' => array_column(TenantSeedHelper::EQUIPMENT_CLASSES, 0)],
            ['column' => 'grades.name', 'origin' => 'TenantBootstrapService (grade du schéma historique)', 'values' => ['Officer']],
            ['column' => 'units.name', 'origin' => 'WizardOrbatPresets (assistant)', 'values' => $presetUnits],
            ['column' => 'units.name', 'origin' => 'OrganizationKitDefinitions (unités)', 'values' => $kitUnits],
            ['column' => 'units.name', 'origin' => 'TenantPedagogyStructureService', 'values' => ['Pilotage et expertise', 'Bureau du personnel et des compétences']],
            // Chaque unité crée une fonction du même nom.
            ['column' => 'personnel_job_roles.name', 'origin' => 'Fonctions des unités de WizardOrbatPresets', 'values' => $presetUnits],
            ['column' => 'personnel_job_roles.name', 'origin' => 'OrganizationKitDefinitions (fonctions et unités)', 'values' => array_merge($kitJobs, $kitUnits)],
            ['column' => 'personnel_job_role_categories.name', 'origin' => 'UnitJobRoleSyncService + OrganizationKitDefinitions', 'values' => array_merge([UnitJobRoleSyncService::CATEGORY_NAME], $kitCats)],
            ['column' => 'sse_attach_tags.name_fr', 'origin' => 'SseFieldNoteTagCatalog::TAGS (libellés FR)', 'values' => $sseFr],
            ['column' => 'sse_attach_tags.name_en', 'origin' => 'SseFieldNoteTagCatalog::TAGS (libellés EN)', 'values' => $sseEn],
        ];
    }

    /**
     * Bornes imposées par le code (troncature ou validation) : chacune doit tenir dans sa colonne.
     *
     * @return list<array{column: string, origin: string, max: int}>
     */
    public static function bounds(): array
    {
        return [
            ['column' => 'tenants.name', 'origin' => 'CommunityController::COMMUNITY_NAME_MAX (nom de la communauté)', 'max' => CommunityController::COMMUNITY_NAME_MAX],
            ['column' => 'permissions.name', 'origin' => 'TenantSeedHelper::PERMISSION_NAME_MAX (troncature)', 'max' => TenantSeedHelper::PERMISSION_NAME_MAX],
            ['column' => 'roles.name', 'origin' => 'CommunityOnboardingValidationService::CUSTOM_ROLE_NAME_MAX (rôles de l’assistant)', 'max' => CommunityOnboardingValidationService::CUSTOM_ROLE_NAME_MAX],
            ['column' => 'units.name', 'origin' => 'CommunityOnboardingValidationService::UNIT_NAME_MAX (unités de l’assistant)', 'max' => CommunityOnboardingValidationService::UNIT_NAME_MAX],
            ['column' => 'personnel_job_roles.name', 'origin' => 'CommunityOnboardingValidationService::UNIT_NAME_MAX (fonction du même nom que l’unité)', 'max' => CommunityOnboardingValidationService::UNIT_NAME_MAX],
            ['column' => 'personnel_job_roles.name', 'origin' => 'UnitJobRoleSyncService::JOB_ROLE_NAME_MAX (troncature)', 'max' => UnitJobRoleSyncService::JOB_ROLE_NAME_MAX],
            ['column' => 'personnel_job_role_tags.name', 'origin' => 'JobRoleEnrichmentService::TAG_MAX_LENGTH', 'max' => JobRoleEnrichmentService::TAG_MAX_LENGTH],
            ['column' => 'sse_attach_tags.name', 'origin' => 'SseTagRepository::NAME_MAX (troncature)', 'max' => SseTagRepository::NAME_MAX],
            ['column' => 'sse_attach_tags.name', 'origin' => 'SseAttachCatalog::TAG_MAX (fenêtre Rattacher)', 'max' => SseAttachCatalog::TAG_MAX],
            ['column' => 'sse_attach_tags.name_fr', 'origin' => 'SseFieldNoteTagCatalog::LABEL_MAX', 'max' => SseFieldNoteTagCatalog::LABEL_MAX],
        ];
    }

    /**
     * @param array<string, int>|null $limits null : lues dans les migrations de $root
     * @return list<string> un message par dépassement (vide : tout tient)
     */
    public static function violations(string $root, ?array $limits = null): array
    {
        $limits ??= self::columnLimits($root);
        $out = [];
        foreach (self::sources() as $source) {
            $limit = $limits[$source['column']] ?? null;
            if ($limit === null) {
                $out[] = $source['column'] . ' : longueur introuvable dans les migrations (' . $source['origin'] . ').';

                continue;
            }
            foreach (array_unique($source['values']) as $value) {
                $len = mb_strlen($value, 'UTF-8');
                if ($len > $limit) {
                    $out[] = $source['column'] . ' VARCHAR(' . $limit . ') : ' . $len . ' caractères dans ' . $source['origin'] . ' : « ' . $value . ' »';
                }
            }
        }
        foreach (self::bounds() as $bound) {
            $limit = $limits[$bound['column']] ?? null;
            if ($limit === null) {
                $out[] = $bound['column'] . ' : longueur introuvable dans les migrations (' . $bound['origin'] . ').';
            } elseif ($bound['max'] > $limit) {
                $out[] = $bound['column'] . ' VARCHAR(' . $limit . ') : borne ' . $bound['max'] . ' trop large (' . $bound['origin'] . ').';
            }
        }

        return $out;
    }

    /** @return list<string> */
    private static function globAll(string $dir, string $ext): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === $ext && !str_contains(str_replace('\\', '/', $file->getPathname()), '/rollback/')) {
                $out[] = $file->getPathname();
            }
        }
        sort($out);

        return $out;
    }

    /** Contenu entre la parenthèse ouvrante à $open et sa fermante (chaînes SQL non gérées : suffisant pour des DDL). */
    private static function parenthesised(string $sql, int $open): string
    {
        $depth = 0;
        $len = strlen($sql);
        for ($i = $open; $i < $len; $i++) {
            if ($sql[$i] === '(') {
                $depth++;
            } elseif ($sql[$i] === ')') {
                $depth--;
                if ($depth === 0) {
                    return substr($sql, $open + 1, $i - $open - 1);
                }
            }
        }

        return substr($sql, $open + 1);
    }
}
