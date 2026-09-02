<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Services\Rbac\MilitaryOperationalRoleCatalog;

/**
 * Packs simples d’emplois métier : quelques domaines, pas les 165 lignes du référentiel.
 *
 * @phpstan-type KitDef array{
 *   id: string,
 *   label: string,
 *   summary: string,
 *   key_slugs: list<string>,
 *   prefixes: list<string>,
 *   extra_slugs: list<string>
 * }
 */
final class PersonnelFunctionKitCatalog
{
    /**
     * @return list<KitDef>
     */
    public static function all(): array
    {
        return [
            [
                'id' => 'infantry',
                'label' => 'Infanterie',
                'summary' => 'Combattants, chefs de groupe et radio de section.',
                'key_slugs' => [
                    'infantry_section_chief',
                    'infantry_group_chief',
                    'infantry_team_chief',
                    'infantry_rifleman',
                    'infantry_radio_operator',
                    'infantry_machine_gunner',
                ],
                'prefixes' => ['infantry_'],
                'extra_slugs' => [],
            ],
            [
                'id' => 'command',
                'label' => 'Commandement',
                'summary' => 'Direction de l’unité et conduite des opérations.',
                'key_slugs' => [
                    'command_unit_commander',
                    'command_executive_officer',
                    'operations_officer',
                    'staff_plans_officer',
                ],
                'prefixes' => ['command_'],
                'extra_slugs' => [
                    'operations_officer',
                    'staff_plans_officer',
                    'staff_battle_captain',
                    'staff_joint_coordination_officer',
                ],
            ],
            [
                'id' => 'medical',
                'label' => 'Santé',
                'summary' => 'Soins, évacuation et présence sanitaire au plus près.',
                'key_slugs' => [
                    'medical_officer',
                    'medical_nurse',
                    'medical_first_responder',
                    'medical_combat_paramedic',
                ],
                'prefixes' => ['medical_'],
                'extra_slugs' => [],
            ],
            [
                'id' => 'logistics',
                'label' => 'Logistique',
                'summary' => 'Transport, ravitaillement et atelier.',
                'key_slugs' => [
                    'logistics_officer',
                    'logistics_driver',
                    'logistics_convoy_chief',
                    'logistics_mechanic',
                ],
                'prefixes' => ['logistics_', 'log_', 'maint_'],
                'extra_slugs' => [
                    'staff_sustainment_lead',
                    'staff_logistics_flow_manager',
                ],
            ],
            [
                'id' => 'training',
                'label' => 'Formation',
                'summary' => 'Instruction, qualifications et évaluation.',
                'key_slugs' => [
                    'training_officer',
                    'instructor',
                    'instruction_trainer',
                    'instruction_evaluator',
                ],
                'prefixes' => ['instruction_', 'edu_'],
                'extra_slugs' => ['instructor', 'training_officer'],
            ],
            [
                'id' => 'hr',
                'label' => 'Recrutement et S1',
                'summary' => 'Dossiers, arrivées et secrétariat d’unité.',
                'key_slugs' => [
                    'hr',
                    'admin_hr_officer',
                    'admin_staff_officer',
                    'admin_unit_secretary',
                ],
                'prefixes' => ['admin_'],
                'extra_slugs' => ['hr'],
            ],
            [
                'id' => 'intel',
                'label' => 'Renseignement',
                'summary' => 'Situation, analyse et sources.',
                'key_slugs' => [
                    'intelligence_officer',
                    'staff_intel_analyst',
                    'intel_humint',
                    'intel_osint',
                ],
                'prefixes' => ['intel_', 'intelligence_', 'staff_intel_'],
                'extra_slugs' => [],
            ],
            [
                'id' => 'fires',
                'label' => 'Appuis',
                'summary' => 'Observation, feux et contrôle d’appui.',
                'key_slugs' => [
                    'fires_jtac',
                    'fires_forward_observer',
                    'fires_support_officer',
                    'fires_gun_chief',
                ],
                'prefixes' => ['fires_', 'arty_'],
                'extra_slugs' => [],
            ],
            [
                'id' => 'aviation',
                'label' => 'Aviation',
                'summary' => 'Pilotes et soutien aérien.',
                'key_slugs' => [
                    'aero_tactical_pilot',
                    'aero_weapon_systems_officer',
                    'aero_ground_support_chief',
                    'aero_loadmaster',
                ],
                'prefixes' => ['aero_'],
                'extra_slugs' => [],
            ],
            [
                'id' => 'engineer',
                'label' => 'Génie',
                'summary' => 'Ouverture, déminage et travaux.',
                'key_slugs' => [
                    'engineer_group_chief',
                    'engineer_sapper',
                    'engineer_eod',
                    'engineer_works_lead',
                ],
                'prefixes' => ['engineer_'],
                'extra_slugs' => [],
            ],
            [
                'id' => 'armor',
                'label' => 'Blindés',
                'summary' => 'Équipages, chefs d’engin et reconnaissance blindée.',
                'key_slugs' => [
                    'armor_platoon_leader',
                    'armor_vehicle_commander',
                    'armor_driver',
                    'armor_gunner',
                ],
                'prefixes' => ['armor_'],
                'extra_slugs' => [],
            ],
            [
                'id' => 'signals',
                'label' => 'Transmissions',
                'summary' => 'Liaisons, réseaux et systèmes d’information.',
                'key_slugs' => [
                    'cyber_telecoms_group_chief',
                    'cyber_signal_operator',
                    'cyber_satcom_operator',
                    'cyber_it_telecom_officer',
                ],
                'prefixes' => ['cyber_'],
                'extra_slugs' => [],
            ],
        ];
    }

    /**
     * @return array<string, KitDef>
     */
    public static function byId(): array
    {
        $out = [];
        foreach (self::all() as $kit) {
            $out[$kit['id']] = $kit;
        }

        return $out;
    }

    /**
     * @param list<string> $kitIds
     * @return list<string>
     */
    public static function slugsForKitIds(array $kitIds): array
    {
        $wanted = array_fill_keys(self::normalizeIds($kitIds), true);
        if ($wanted === []) {
            return [];
        }
        $catalogSlugs = [];
        foreach (MilitaryOperationalRoleCatalog::entries() as $e) {
            $slug = trim((string) ($e['slug'] ?? ''));
            if ($slug !== '') {
                $catalogSlugs[] = $slug;
            }
        }
        $out = [];
        foreach (self::all() as $kit) {
            if (!isset($wanted[$kit['id']])) {
                continue;
            }
            foreach ($kit['key_slugs'] as $slug) {
                $out[$slug] = true;
            }
            foreach ($kit['extra_slugs'] as $slug) {
                $out[$slug] = true;
            }
            foreach ($catalogSlugs as $slug) {
                foreach ($kit['prefixes'] as $prefix) {
                    if ($prefix !== '' && str_starts_with($slug, $prefix)) {
                        $out[$slug] = true;
                        break;
                    }
                }
            }
        }

        return array_keys($out);
    }

    /**
     * @param list<string> $kitIds
     * @return list<array{slug: string, name: string, kit_id: string, kit_label: string}>
     */
    public static function keyFunctionsForKitIds(array $kitIds): array
    {
        $wanted = array_fill_keys(self::normalizeIds($kitIds), true);
        if ($wanted === []) {
            return [];
        }
        $names = [];
        foreach (MilitaryOperationalRoleCatalog::entries() as $e) {
            $slug = trim((string) ($e['slug'] ?? ''));
            if ($slug !== '') {
                $names[$slug] = trim((string) ($e['name'] ?? $slug));
            }
        }
        $seen = [];
        $out = [];
        foreach (self::all() as $kit) {
            if (!isset($wanted[$kit['id']])) {
                continue;
            }
            foreach ($kit['key_slugs'] as $slug) {
                if (isset($seen[$slug])) {
                    continue;
                }
                $seen[$slug] = true;
                $out[] = [
                    'slug' => $slug,
                    'name' => $names[$slug] ?? $slug,
                    'kit_id' => $kit['id'],
                    'kit_label' => $kit['label'],
                ];
            }
        }

        return $out;
    }

    /** @return list<string> */
    public static function visualOnlySlugs(): array
    {
        $out = [];
        foreach (MilitaryOperationalRoleCatalog::entries() as $e) {
            if ((int) ($e['is_visual_only'] ?? 0) === 1) {
                $slug = trim((string) ($e['slug'] ?? ''));
                if ($slug !== '') {
                    $out[] = $slug;
                }
            }
        }

        return $out;
    }

    /**
     * @param list<string> $ids
     * @return list<string>
     */
    public static function normalizeIds(array $ids): array
    {
        $known = self::byId();
        $out = [];
        foreach ($ids as $id) {
            $id = trim((string) $id);
            if ($id !== '' && isset($known[$id]) && !isset($out[$id])) {
                $out[$id] = true;
            }
        }

        return array_keys($out);
    }
}
