<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Core\Database;
use App\Repositories\TenantAdminSettingsRepository;
use PDO;

/**
 * Détecte des doublons potentiels entre fiches personnel d’une communauté.
 * Les champs contrôlés sont configurables (matricule, nom, callsign, …).
 */
final class PersonnelDuplicateDetectionService
{
    public const FIELD_MATRICULE = 'matricule';
    public const FIELD_CALLSIGN = 'callsign';
    public const FIELD_DISPLAY_NAME = 'display_name';
    public const FIELD_CHARACTER_NAME = 'character_name';
    public const FIELD_EMAIL = 'email';

    /** @var array<string, string> */
    public const FIELD_LABELS = [
        self::FIELD_MATRICULE => 'Matricule',
        self::FIELD_CALLSIGN => 'Indicatif',
        self::FIELD_DISPLAY_NAME => 'Nom affiché',
        self::FIELD_CHARACTER_NAME => 'Nom du personnage',
        self::FIELD_EMAIL => 'Adresse e-mail',
    ];

    public function __construct(
        private ?PDO $pdo = null,
        private ?TenantAdminSettingsRepository $adminSettings = null,
    ) {
        $this->pdo ??= Database::getPdo();
        $this->adminSettings ??= new TenantAdminSettingsRepository();
    }

    /**
     * @return list<string>
     */
    public function enabledFields(int $tenantId): array
    {
        $settings = $this->adminSettings->getForTenant($tenantId);
        $raw = $settings['personnel_duplicates']['fields'] ?? null;
        if (!is_array($raw) || $raw === []) {
            return [self::FIELD_MATRICULE, self::FIELD_CALLSIGN];
        }
        $out = [];
        foreach ($raw as $f) {
            $f = strtolower(trim((string) $f));
            if (isset(self::FIELD_LABELS[$f])) {
                $out[] = $f;
            }
        }

        return $out !== [] ? array_values(array_unique($out)) : [self::FIELD_MATRICULE, self::FIELD_CALLSIGN];
    }

    public function isEnabled(int $tenantId): bool
    {
        $settings = $this->adminSettings->getForTenant($tenantId);
        if (array_key_exists('enabled', $settings['personnel_duplicates'] ?? [])) {
            return !empty($settings['personnel_duplicates']['enabled']);
        }

        return true;
    }

    /**
     * @return array{
     *   enabled: bool,
     *   fields: list<string>,
     *   groups: list<array{field:string,field_label:string,value:string,members:list<array{id:int,display_name:string,callsign:?string}>}>,
     *   group_count: int,
     *   member_count: int
     * }
     */
    public function scan(int $tenantId): array
    {
        $fields = $this->enabledFields($tenantId);
        $enabled = $this->isEnabled($tenantId);
        if (!$enabled || $tenantId < 1) {
            return [
                'enabled' => $enabled,
                'fields' => $fields,
                'groups' => [],
                'group_count' => 0,
                'member_count' => 0,
            ];
        }

        $rows = $this->loadMemberIdentityRows($tenantId);
        $groups = [];
        $memberIds = [];

        foreach ($fields as $field) {
            $buckets = [];
            foreach ($rows as $row) {
                $value = $this->normalizedValue($field, $row);
                if ($value === null) {
                    continue;
                }
                $id = (int) ($row['id'] ?? 0);
                if ($id < 1) {
                    continue;
                }

                // A member must only appear once in a value bucket. This also
                // protects the result if historical 1:1 profile constraints are
                // temporarily missing while a migration is being applied.
                $buckets[$value][$id] = $row;
            }
            foreach ($buckets as $value => $members) {
                if (count($members) < 2) {
                    continue;
                }
                $memberList = [];
                foreach ($members as $m) {
                    $id = (int) ($m['id'] ?? 0);
                    $memberIds[$id] = true;
                    $memberList[] = [
                        'id' => $id,
                        'display_name' => (string) ($m['display_name'] ?? ''),
                        'callsign' => ($m['callsign'] ?? null) !== null && trim((string) $m['callsign']) !== ''
                            ? (string) $m['callsign']
                            : null,
                    ];
                }
                $groups[] = [
                    'field' => $field,
                    'field_label' => self::FIELD_LABELS[$field] ?? $field,
                    'value' => (string) $value,
                    'members' => $memberList,
                ];
            }
        }

        usort($groups, static function (array $a, array $b): int {
            return [count($b['members']), $a['field_label'], $a['value']]
                <=> [count($a['members']), $b['field_label'], $b['value']];
        });

        return [
            'enabled' => true,
            'fields' => $fields,
            'groups' => $groups,
            'group_count' => count($groups),
            'member_count' => count($memberIds),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadMemberIdentityRows(int $tenantId): array
    {
        // Since identities can belong to several communities, use the active
        // membership as the tenant boundary and scope both RH joins explicitly.
        // Without these predicates, N profiles x N extras produced a Cartesian
        // product and rendered the same member dozens of times in every group.
        $sql = 'SELECT u.id,
                       COALESCE(NULLIF(TRIM(ucp.display_name), \'\'), u.display_name) AS display_name,
                       COALESCE(NULLIF(TRIM(ucp.callsign), \'\'), u.callsign) AS callsign,
                       u.email,
                       up.first_name, up.last_name,
                       pp.matricule_internal, pp.callsign AS profile_callsign,
                       pe.service_number
                FROM user_community_memberships ucm
                INNER JOIN users u ON u.id = ucm.user_id
                LEFT JOIN user_community_profiles ucp
                       ON ucp.user_id = u.id AND ucp.tenant_id = ucm.tenant_id
                LEFT JOIN user_profiles up ON up.user_id = u.id
                LEFT JOIN personnel_profiles pp
                       ON pp.user_id = u.id AND pp.tenant_id = ucm.tenant_id
                LEFT JOIN personnel_extras pe
                       ON pe.user_id = u.id AND pe.tenant_id = ucm.tenant_id
                WHERE ucm.tenant_id = ?
                  AND LOWER(COALESCE(ucm.status, \'\')) = \'active\'
                  AND (u.deleted_at IS NULL OR u.deleted_at = \'0000-00-00 00:00:00\')
                  AND COALESCE(u.is_service_account, 0) = 0
                  AND LOWER(COALESCE(u.status, \'\')) NOT IN (\'deleted\', \'banned\')
                ORDER BY u.id ASC
                LIMIT 5000';
        try {
            $st = $this->pdo->prepare($sql);
            $st->execute([$tenantId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            // Compatibility during rolling deployments where the identity split
            // migration has not created the community tables/tenant columns yet.
            $legacySql = 'SELECT u.id, u.display_name, u.callsign, u.email,
                                 up.first_name, up.last_name,
                                 pp.matricule_internal, pp.callsign AS profile_callsign,
                                 pe.service_number
                          FROM users u
                          LEFT JOIN user_profiles up ON up.user_id = u.id
                          LEFT JOIN personnel_profiles pp ON pp.user_id = u.id
                          LEFT JOIN personnel_extras pe ON pe.user_id = u.id
                          WHERE u.tenant_id = ?
                            AND (u.deleted_at IS NULL OR u.deleted_at = \'0000-00-00 00:00:00\')
                            AND COALESCE(u.is_service_account, 0) = 0
                            AND LOWER(COALESCE(u.status, \'\')) NOT IN (\'deleted\', \'banned\')
                          ORDER BY u.id ASC
                          LIMIT 5000';
            try {
                $st = $this->pdo->prepare($legacySql);
                $st->execute([$tenantId]);

                return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable) {
                return [];
            }
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function normalizedValue(string $field, array $row): ?string
    {
        $raw = match ($field) {
            self::FIELD_MATRICULE => trim((string) (($row['matricule_internal'] ?? '') !== ''
                ? $row['matricule_internal']
                : ($row['service_number'] ?? ''))),
            self::FIELD_CALLSIGN => trim((string) (($row['profile_callsign'] ?? '') !== ''
                ? $row['profile_callsign']
                : ($row['callsign'] ?? ''))),
            self::FIELD_DISPLAY_NAME => trim((string) ($row['display_name'] ?? '')),
            self::FIELD_CHARACTER_NAME => trim(
                trim((string) ($row['first_name'] ?? '')) . ' ' . trim((string) ($row['last_name'] ?? ''))
            ),
            self::FIELD_EMAIL => trim((string) ($row['email'] ?? '')),
            default => '',
        };
        if ($raw === '') {
            return null;
        }
        $norm = mb_strtolower($raw);
        $norm = preg_replace('/\s+/u', ' ', $norm) ?? $norm;

        return $norm;
    }
}
