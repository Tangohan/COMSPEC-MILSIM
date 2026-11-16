<?php

declare(strict_types=1);

namespace App\Support\Schema;

use App\Authorization\TenantPermissionCatalog;
use App\Controllers\Web\CommunityController;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\SseTagRepository;
use App\Repositories\UnitRepository;
use App\Services\Advancement\GradeScaleTemplateService;
use App\Services\Community\CommunityOnboardingValidationService;
use App\Services\Community\TenantDefaultRoleDefinitions;
use App\Services\Community\TenantSeedHelper;
use App\Services\Community\TenantTypeConfig;
use App\Services\Community\WizardOrbatPresets;
use App\Services\OrganizationCatalog\OrganizationCatalogService;
use App\Services\OrganizationCatalog\OrganizationKitDefinitions;
use App\Services\Personnel\JobRoleEnrichmentService;
use App\Services\Personnel\UnitJobRoleSyncService;
use App\Services\Rbac\CommunityAccessProfiles;
use App\Support\SseAttachCatalog;
use App\Support\SseFieldNoteTagCatalog;

/**
 * Contrôle sans base MySQL des valeurs écrites à la création d'une communauté et de sa structure : chaque valeur semée
 * (noms, adresses courtes, codes, types, descriptions, grades, formations, étiquettes) et chaque borne imposée par le code
 * aux saisies ou valeurs dérivées doit tenir dans la colonne VARCHAR la plus étroite déclarée par les migrations
 * (migrations/*.sql, database/steps, bootstrap/*migration*.php). Une valeur plus longue fait échouer la création en MySQL
 * strict (SQLSTATE 22001, erreur 1406) et annule toute la transaction.
 *
 * Deux familles de contrôles :
 *  - sources : listes semées (catalogue des droits, niveaux d'accès, panneaux, modèles d'organisation, ORBAT de l'assistant…) ;
 *  - bornes : longueurs maximales que le code impose aux valeurs saisies ou dérivées (troncature, validation de formulaire).
 */
final class SeedNameLengthAudit
{
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
        $cols = '\w+';
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
            ...self::structureSources(),
        ];
    }

    /**
     * Autres colonnes VARCHAR écrites par la création de la structure (adresses courtes, codes, types, descriptions,
     * libellés de grades, formations, étiquettes) : mêmes listes semées que ci-dessus, champ par champ.
     *
     * @return list<array{column: string, origin: string, values: list<string>}>
     */
    public static function structureSources(): array
    {
        $field = static fn (array $rows, string|int $key): array => array_values(array_filter(array_map(
            static fn ($r): string => is_array($r) && is_scalar($r[$key] ?? null) ? (string) $r[$key] : '',
            $rows
        ), static fn (string $v): bool => $v !== ''));
        $kit = static function (string $part, string $key) use ($field): array {
            $out = [];
            foreach (array_merge(OrganizationKitDefinitions::officialKits(), OrganizationKitDefinitions::genericKits()) as $def) {
                $out = array_merge($out, $field(is_array($def[$part] ?? null) ? $def[$part] : [], $key));
            }

            return $out;
        };
        $preset = static function (string $key) use ($field): array {
            $out = [];
            foreach (WizardOrbatPresets::all() as $p) {
                $out = array_merge($out, $field($p['units'], $key));
            }

            return $out;
        };
        $grades = $filieres = [];
        foreach ((new \ReflectionClass(GradeScaleTemplateService::class))->newInstanceWithoutConstructor()->templates() as $template) {
            $grades = array_merge($grades, is_array($template['grades'] ?? null) ? $template['grades'] : []);
            $filieres = array_merge($filieres, is_array($template['filieres'] ?? null) ? $template['filieres'] : []);
        }
        $courses = self::courseSeedLiterals(dirname(__DIR__, 3));
        $roleDefs = array_merge(
            CommunityAccessProfiles::definitions(),
            TenantDefaultRoleDefinitions::governanceRoles(),
            TenantDefaultRoleDefinitions::operationalRoles(),
            TenantDefaultRoleDefinitions::organicStaffRoles()
        );

        return [
            ['column' => 'permissions.slug', 'origin' => 'TenantPermissionCatalog::definitions()', 'values' => $field(TenantPermissionCatalog::definitions(), 'slug')],
            ['column' => 'permissions.module', 'origin' => 'TenantPermissionCatalog::definitions()', 'values' => $field(TenantPermissionCatalog::definitions(), 'module')],
            ['column' => 'roles.slug', 'origin' => 'Niveaux d’accès et rôles système', 'values' => array_merge($field($roleDefs, 'slug'), $kit('roles', 'slug'))],
            ['column' => 'roles.description', 'origin' => 'Niveaux d’accès, rôles système et modèles', 'values' => array_merge($field($roleDefs, 'description'), $kit('roles', 'description'))],
            ['column' => 'personnel_admin_panels.slug', 'origin' => 'TenantSeedHelper::PERSONNEL_PANELS', 'values' => $field(TenantSeedHelper::PERSONNEL_PANELS, 1)],
            ['column' => 'personnel_admin_panels.description', 'origin' => 'TenantSeedHelper::PERSONNEL_PANELS', 'values' => $field(TenantSeedHelper::PERSONNEL_PANELS, 2)],
            ['column' => 'document_categories.slug', 'origin' => 'TenantSeedHelper::DOCUMENT_CATEGORIES', 'values' => $field(TenantSeedHelper::DOCUMENT_CATEGORIES, 1)],
            ['column' => 'equipment_classes.slug', 'origin' => 'TenantSeedHelper::EQUIPMENT_CLASSES', 'values' => $field(TenantSeedHelper::EQUIPMENT_CLASSES, 1)],
            ['column' => 'equipment_classes.category', 'origin' => 'TenantSeedHelper::EQUIPMENT_CLASSES', 'values' => $field(TenantSeedHelper::EQUIPMENT_CLASSES, 2)],
            ['column' => 'units.slug', 'origin' => 'WizardOrbatPresets + OrganizationKitDefinitions', 'values' => array_merge($preset('slug'), $kit('units', 'slug'))],
            ['column' => 'units.type', 'origin' => 'WizardOrbatPresets + OrganizationKitDefinitions', 'values' => array_merge($preset('type'), $kit('units', 'type'))],
            ['column' => 'units.code', 'origin' => 'OrganizationKitDefinitions (unités)', 'values' => $kit('units', 'code')],
            ['column' => 'personnel_job_roles.slug', 'origin' => 'OrganizationKitDefinitions (fonctions)', 'values' => $kit('job_roles', 'slug')],
            ['column' => 'personnel_job_roles.description', 'origin' => 'OrganizationKitDefinitions (fonctions)', 'values' => $kit('job_roles', 'description')],
            ['column' => 'personnel_job_role_categories.slug', 'origin' => 'UnitJobRoleSyncService + OrganizationKitDefinitions', 'values' => array_merge([UnitJobRoleSyncService::CATEGORY_SLUG], $kit('job_role_categories', 'slug'))],
            ['column' => 'grade_definitions.code', 'origin' => 'GradeScaleTemplateService::templates()', 'values' => $field($grades, 'code')],
            ['column' => 'grade_definitions.label', 'origin' => 'GradeScaleTemplateService::templates()', 'values' => $field($grades, 'label')],
            ['column' => 'grade_definitions.short_label', 'origin' => 'GradeScaleTemplateService::templates()', 'values' => $field($grades, 'short_label')],
            ['column' => 'grade_filiere_definitions.code', 'origin' => 'GradeScaleTemplateService::templates()', 'values' => $field($filieres, 'code')],
            ['column' => 'grade_filiere_definitions.label', 'origin' => 'GradeScaleTemplateService::templates()', 'values' => $field($filieres, 'label')],
            ['column' => 'unit_type_definitions.code', 'origin' => 'UnitTypeDefinitionRepository::ensureSystemDefaults', 'values' => ['commandement', 'operationnel', 'soutien', 'formation']],
            ['column' => 'training_courses.slug', 'origin' => 'Formations semées (ensure*Course)', 'values' => $courses['slug']],
            ['column' => 'training_lessons.title', 'origin' => 'Formations semées (ensure*Course : cours, modules, leçons, questionnaires)', 'values' => $courses['title']],
            ['column' => 'training_modules.title', 'origin' => 'Formations semées (ensure*Course : cours, modules, leçons, questionnaires)', 'values' => $courses['title']],
            ['column' => 'sse_attach_tags.code', 'origin' => 'SseFieldNoteTagCatalog::TAGS (codes)', 'values' => $field(SseFieldNoteTagCatalog::TAGS, 0)],
            ['column' => 'sse_attach_tags.family', 'origin' => 'SseFieldNoteTagCatalog::TAGS (familles)', 'values' => $field(SseFieldNoteTagCatalog::TAGS, 1)],
            ['column' => 'sse_attach_tags.description', 'origin' => 'SseFieldNoteTagCatalog::TAGS (descriptions)', 'values' => array_merge($field(SseFieldNoteTagCatalog::TAGS, 4), $field(SseFieldNoteTagCatalog::TAGS, 5))],
        ];
    }

    /**
     * Titres et adresses écrits en dur dans les formations semées à la création (bootstrap/training_*_course_seed.php),
     * y compris le titre dérivé « À retenir — <titre> ».
     *
     * @return array{title: list<string>, slug: list<string>}
     */
    public static function courseSeedLiterals(string $root): array
    {
        $out = ['title' => [], 'slug' => []];
        foreach (glob($root . '/bootstrap/training_*_course_seed.php') ?: [] as $file) {
            $php = (string) file_get_contents($file);
            foreach (['title', 'slug'] as $kind) {
                if (preg_match_all('/(?:[\'"]' . $kind . '[\'"]\s*=>|\$' . $kind . '\s*=)\s*\'((?:[^\'\\\\]|\\\\.)*)\'/u', $php, $m) > 0) {
                    foreach ($m[1] as $v) {
                        $out[$kind][] = stripslashes($v);
                    }
                }
            }
        }
        foreach ($out['title'] as $t) {
            $out['title'][] = 'À retenir — ' . $t;
        }

        return $out;
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
            // Unités et fonctions saisies ou dérivées (formulaires, ORBAT, affectations).
            ['column' => 'units.slug', 'origin' => 'UnitRepository::SLUG_BASE_MAX + suffixe « -abcdef »', 'max' => UnitRepository::SLUG_BASE_MAX + 7],
            ...array_map(
                static fn (string $col, int $max): array => ['column' => 'units.' . $col, 'origin' => 'UnitRepository::FIELD_MAX[' . $col . ']', 'max' => $max],
                array_keys(UnitRepository::FIELD_MAX),
                UnitRepository::FIELD_MAX
            ),
            ['column' => 'personnel_job_roles.slug', 'origin' => 'PersonnelJobRoleRepository::SLUG_BASE_MAX + suffixe « -abcdef »', 'max' => PersonnelJobRoleRepository::SLUG_BASE_MAX + 7],
            ['column' => 'personnel_job_role_categories.slug', 'origin' => 'PersonnelJobRoleRepository::SLUG_BASE_MAX + suffixe « -abcdef »', 'max' => PersonnelJobRoleRepository::SLUG_BASE_MAX + 7],
            ['column' => 'personnel_job_roles.name', 'origin' => 'PersonnelJobRoleRepository::NAME_MAX', 'max' => PersonnelJobRoleRepository::NAME_MAX],
            ['column' => 'personnel_job_role_categories.name', 'origin' => 'PersonnelJobRoleRepository::NAME_MAX', 'max' => PersonnelJobRoleRepository::NAME_MAX],
            ['column' => 'organization_catalog_items.title', 'origin' => 'OrganizationCatalogService::TITLE_MAX', 'max' => OrganizationCatalogService::TITLE_MAX],
            ['column' => 'units.name', 'origin' => 'CommunityOnboardingValidationService::UNIT_NAME_MAX', 'max' => CommunityOnboardingValidationService::UNIT_NAME_MAX],
            ['column' => 'roles.name', 'origin' => 'CommunityOnboardingValidationService::CUSTOM_ROLE_NAME_MAX', 'max' => CommunityOnboardingValidationService::CUSTOM_ROLE_NAME_MAX],
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
