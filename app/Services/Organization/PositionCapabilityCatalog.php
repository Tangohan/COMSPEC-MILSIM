<?php

declare(strict_types=1);

namespace App\Services\Organization;

/**
 * Capacités métier attachées à un poste (billet), pas au grade ni au rôle Athena.
 *
 * @phpstan-type CapDef array{
 *   slug: string,
 *   label: string,
 *   cell: string,
 *   summary: string,
 *   work_modules: list<string>,
 *   default_task_types: list<string>
 * }
 */
final class PositionCapabilityCatalog
{
    public const CMD_UNIT = 'cmd_unit';
    public const CMD_DEPUTY = 'cmd_deputy';
    public const CMD_OPS = 'cmd_ops';
    public const S1 = 's1_personnel';
    public const S2 = 's2_intel';
    public const S3 = 's3_operations';
    public const S4 = 's4_logistics';
    public const S6 = 's6_signals';
    public const GROUP_LEADER = 'group_leader';
    public const GROUP_DEPUTY = 'group_deputy';
    public const TEAM_LEADER = 'team_leader';
    public const MEDIC = 'medic';
    public const JTAC = 'jtac';
    public const OPERATOR = 'operator';

    /**
     * @return list<CapDef>
     */
    public static function all(): array
    {
        return [
            self::def(self::CMD_UNIT, 'Commandant d’unité', 'command', 'Conduit l’unité, arbitrage et validation finale.', ['command', 'orders', 'aar'], ['order_validate', 'mission_approve']),
            self::def(self::CMD_DEPUTY, 'Adjoint', 'command', 'Relais du commandant, continuité de commandement.', ['command', 'orders'], ['order_relay', 'delegation']),
            self::def(self::CMD_OPS, 'Chef des opérations', 'command', 'Priorise les efforts et synchronise S2/S3.', ['command', 'operations'], ['ops_sync', 'frago_approve']),
            self::def(self::S1, 'S1 — Personnel', 'staff', 'Effectifs, absences, affectations permanentes.', ['personnel', 'effectifs'], ['personnel_request', 'absence_validate']),
            self::def(self::S2, 'S2 — Renseignement', 'staff', 'Tracks, SSE, INTREP, fusion et confiance.', ['intel', 'sse', 'tracks'], ['analyze_sse', 'confirm_track', 'produce_intrep']),
            self::def(self::S3, 'S3 — Opérations', 'staff', 'SITREP, missions, FRAGO, tâches tactiques.', ['operations', 'sitrep', 'frago'], ['sitrep_review', 'frago_prepare', 'task_assign']),
            self::def(self::S4, 'S4 — Logistique', 'staff', 'Stocks, véhicules, ravitaillement, indisponibilités.', ['logistics', 'resupply'], ['resupply_take', 'convoy_assign', 'resupply_deliver']),
            self::def(self::S6, 'S6 — SIC / Transmissions', 'staff', 'Incidents radio, terminaux, disponibilité COMMS.', ['comms', 'atak'], ['comms_incident', 'terminal_issue']),
            self::def(self::GROUP_LEADER, 'Chef de groupe', 'tactical', 'Ordres de groupe, SITREP, remontée HQ.', ['orders', 'sitrep', 'tasks'], ['sitrep_create', 'task_assign', 'report_forward']),
            self::def(self::GROUP_DEPUTY, 'Adjoint de groupe', 'tactical', 'Relais du chef de groupe.', ['orders', 'sitrep'], ['sitrep_create', 'report_forward']),
            self::def(self::TEAM_LEADER, 'Chef d’équipe', 'tactical', 'Conduite d’équipe et filtrage des rapports terrain.', ['orders', 'reports'], ['report_ack', 'report_forward']),
            self::def(self::MEDIC, 'Medic', 'specialty', 'Casualties, MEDEVAC, cellule médicale.', ['medical', 'medevac'], ['casualty_take', 'medevac_coord']),
            self::def(self::JTAC, 'JTAC', 'specialty', 'Demandes CAS / appui feu.', ['firesupport', 'cas'], ['cas_request', 'cas_clear']),
            self::def(self::OPERATOR, 'Opérateur', 'tactical', 'Production terrain : observations, CONTACTREP.', ['reports', 'observations'], ['contactrep_create', 'observation_submit']),
        ];
    }

    /**
     * @return CapDef|null
     */
    public static function find(string $slug): ?array
    {
        $slug = strtolower(trim($slug));
        foreach (self::all() as $row) {
            if ($row['slug'] === $slug) {
                return $row;
            }
        }

        return null;
    }

    public static function label(string $slug): string
    {
        return self::find($slug)['label'] ?? $slug;
    }

    /**
     * Postes d’un État-major type (codes billet).
     *
     * @return list<array{code: string, title: string, capability_template: string, key_post_kind?: string, is_key_post?: int, sort_order: int}>
     */
    public static function headquartersBillets(): array
    {
        return [
            ['code' => 'CMD', 'title' => 'Commandant d’unité', 'capability_template' => self::CMD_UNIT, 'key_post_kind' => 'commander', 'is_key_post' => 1, 'sort_order' => 10],
            ['code' => 'ADJ', 'title' => 'Adjoint', 'capability_template' => self::CMD_DEPUTY, 'key_post_kind' => 'deputy', 'is_key_post' => 1, 'sort_order' => 20],
            ['code' => 'OPS', 'title' => 'Chef des opérations', 'capability_template' => self::CMD_OPS, 'sort_order' => 30],
            ['code' => 'S1', 'title' => 'S1 — Personnel', 'capability_template' => self::S1, 'sort_order' => 40],
            ['code' => 'S2', 'title' => 'S2 — Renseignement', 'capability_template' => self::S2, 'sort_order' => 50],
            ['code' => 'S3', 'title' => 'S3 — Opérations', 'capability_template' => self::S3, 'sort_order' => 60],
            ['code' => 'S4', 'title' => 'S4 — Logistique', 'capability_template' => self::S4, 'sort_order' => 70],
            ['code' => 'S6', 'title' => 'S6 — SIC / Transmissions', 'capability_template' => self::S6, 'sort_order' => 80],
        ];
    }

    /**
     * Postes d’un groupe de combat type.
     *
     * @return list<array{code: string, title: string, capability_template: string, key_post_kind?: string, is_key_post?: int, authorized_slots?: int, sort_order: int}>
     */
    public static function combatGroupBillets(string $groupCode = 'N-10'): array
    {
        $prefix = strtoupper(trim($groupCode)) !== '' ? strtoupper(trim($groupCode)) : 'N-10';

        return [
            ['code' => $prefix . '-SL', 'title' => 'Chef de groupe', 'capability_template' => self::GROUP_LEADER, 'key_post_kind' => 'commander', 'is_key_post' => 1, 'sort_order' => 10],
            ['code' => $prefix . '-ASL', 'title' => 'Adjoint', 'capability_template' => self::GROUP_DEPUTY, 'key_post_kind' => 'deputy', 'is_key_post' => 1, 'sort_order' => 20],
            ['code' => $prefix . '-TL', 'title' => 'Chef d’équipe', 'capability_template' => self::TEAM_LEADER, 'sort_order' => 30],
            ['code' => $prefix . '-MED', 'title' => 'Medic', 'capability_template' => self::MEDIC, 'sort_order' => 40],
            ['code' => $prefix . '-JTAC', 'title' => 'JTAC', 'capability_template' => self::JTAC, 'sort_order' => 50],
            ['code' => $prefix . '-OP', 'title' => 'Opérateur', 'capability_template' => self::OPERATOR, 'authorized_slots' => 4, 'sort_order' => 60],
        ];
    }

    /**
     * @param list<string> $workModules
     * @param list<string> $taskTypes
     * @return CapDef
     */
    private static function def(
        string $slug,
        string $label,
        string $cell,
        string $summary,
        array $workModules,
        array $taskTypes,
    ): array {
        return [
            'slug' => $slug,
            'label' => $label,
            'cell' => $cell,
            'summary' => $summary,
            'work_modules' => $workModules,
            'default_task_types' => $taskTypes,
        ];
    }
}
