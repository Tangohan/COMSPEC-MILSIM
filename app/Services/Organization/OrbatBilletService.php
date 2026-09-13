<?php

declare(strict_types=1);

namespace App\Services\Organization;

use App\Core\Database;
use App\Repositories\OrbatBilletRepository;
use App\Repositories\OrganizationVisibilityHistoryRepository;
use App\Repositories\UnitRepository;
use App\Support\BilletOccupancyType;
use App\Support\BilletStatus;
use App\Support\OrgDomainModel;
use App\Support\UnitAdminStatus;
use PDO;

/**
 * Règles métier postes ORBAT : effectifs, occupation, commandement dérivé, anomalies.
 */
final class OrbatBilletService
{
    private ?PDO $pdo = null;

    public function __construct(
        private OrbatBilletRepository $billets,
        private UnitRepository $units,
        private ?OrganizationVisibilityHistoryRepository $history = null,
    ) {}

    public function schemaReady(): bool
    {
        return $this->billets->schemaReady();
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok: bool, id?: int, message?: string}
     */
    public function createBillet(int $tenantId, array $data, ?int $actorUserId = null): array
    {
        if (!$this->schemaReady()) {
            return ['ok' => false, 'message' => 'Schéma postes ORBAT indisponible.'];
        }
        $unitId = (int) ($data['unit_id'] ?? 0);
        if ($unitId < 1 || $this->units->findById($unitId, $tenantId) === null) {
            return ['ok' => false, 'message' => 'Structure introuvable.'];
        }
        $id = $this->billets->create($tenantId, $data);
        if ($id < 1) {
            return ['ok' => false, 'message' => 'Création du poste impossible (code/titre requis).'];
        }
        $this->appendCareer(
            $tenantId,
            0,
            'billet_created',
            'Création du poste « ' . trim((string) ($data['title'] ?? '')) . ' »',
            $actorUserId,
            ['billet_id' => $id, 'unit_id' => $unitId]
        );

        return ['ok' => true, 'id' => $id];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok: bool, message?: string}
     */
    public function updateBillet(int $tenantId, int $billetId, array $data, ?int $actorUserId = null): array
    {
        $before = $this->billets->findById($tenantId, $billetId);
        if ($before === null) {
            return ['ok' => false, 'message' => 'Poste introuvable.'];
        }
        if (!$this->billets->update($tenantId, $billetId, $data)) {
            return ['ok' => false, 'message' => 'Mise à jour impossible.'];
        }
        $this->history?->record(
            $tenantId,
            'billet',
            $billetId,
            'update',
            mb_substr((string) ($before['title'] ?? ''), 0, 64),
            mb_substr((string) ($data['title'] ?? $before['title'] ?? ''), 0, 64),
            $actorUserId
        );

        return ['ok' => true];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok: bool, id?: int, warnings?: list<string>, message?: string}
     */
    public function occupy(int $tenantId, int $billetId, array $data, ?int $actorUserId = null): array
    {
        $billet = $this->billets->findById($tenantId, $billetId);
        if ($billet === null) {
            return ['ok' => false, 'message' => 'Poste introuvable.'];
        }
        $status = strtolower((string) ($billet['status'] ?? 'active'));
        if ($status === 'frozen') {
            return ['ok' => false, 'message' => 'Ce poste est gelé — occupation impossible.'];
        }
        if ($status === 'deleted' || (int) ($billet['is_active'] ?? 1) !== 1) {
            return ['ok' => false, 'message' => 'Ce poste n’est plus actif.'];
        }

        if (!BilletStatus::isOccupiable((string) ($billet['status'] ?? 'active'))) {
            return ['ok' => false, 'message' => 'Ce poste n’est pas occupable dans son état actuel.'];
        }

        $occupancy = BilletOccupancyType::normalize((string) ($data['occupancy_type'] ?? 'primary'));
        $data['occupancy_type'] = $occupancy;
        $warnings = [];
        $asOf = date('Y-m-d');
        $holders = $this->billets->activeHolders($tenantId, $billetId, $asOf);
        $slots = max(1, (int) ($billet['authorized_slots'] ?? 1));

        if ($occupancy === BilletOccupancyType::PRIMARY) {
            $primaryCount = 0;
            foreach ($holders as $h) {
                $occ = BilletOccupancyType::normalize((string) ($h['occupancy_type'] ?? 'primary'));
                if ($occ === BilletOccupancyType::PRIMARY) {
                    ++$primaryCount;
                }
            }
            if ($primaryCount >= $slots) {
                return [
                    'ok' => false,
                    'message' => 'Ce poste a déjà un titulaire pour chaque slot autorisé. Utilisez un intérim ou libérez d’abord le titulaire.',
                ];
            }
        }

        if (BilletOccupancyType::keepsOrganicByDefault($occupancy)) {
            $data['keeps_organic_billet'] = 1;
            if (empty($data['organic_billet_id'])) {
                $organic = $this->billets->primaryBilletsForUser($tenantId, (int) ($data['user_id'] ?? 0), $asOf);
                if ($organic !== []) {
                    $data['organic_billet_id'] = (int) ($organic[0]['billet_id'] ?? 0);
                }
            }
            $data['holder_role'] = $data['holder_role'] ?? 'PRIMARY';
        }

        $data['created_by'] = $actorUserId;
        $id = $this->billets->assignHolder($tenantId, $billetId, $data);
        if ($id < 1) {
            return ['ok' => false, 'message' => 'Affectation au poste impossible.'];
        }

        $userId = (int) ($data['user_id'] ?? 0);
        $label = BilletOccupancyType::label($occupancy);
        $this->appendCareer(
            $tenantId,
            $userId,
            'billet_occupied',
            $label . ' du poste « ' . (string) ($billet['title'] ?? '') . ' »',
            $actorUserId,
            [
                'billet_id' => $billetId,
                'occupancy_type' => $occupancy,
                'movement_reason' => $data['movement_reason'] ?? null,
            ],
            isset($data['movement_reason']) ? (string) $data['movement_reason'] : null
        );

        $packId = (int) ($billet['required_pack_id'] ?? 0);
        if ($packId > 0 && $userId > 0) {
            $missing = $this->missingRequiredQualifications($tenantId, $userId, $packId);
            if ($missing !== []) {
                $warnings[] = 'Qualifications attendues manquantes : ' . implode(', ', $missing);
            }
        }

        return ['ok' => true, 'id' => $id, 'warnings' => $warnings];
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    public function vacate(
        int $tenantId,
        int $holderId,
        ?int $userId = null,
        ?int $billetId = null,
        ?int $actorUserId = null,
        ?string $reason = null
    ): array {
        if (!$this->billets->endHolder($tenantId, $holderId)) {
            return ['ok' => false, 'message' => 'Fin d’occupation impossible.'];
        }
        if ($userId !== null && $userId > 0) {
            $this->appendCareer(
                $tenantId,
                $userId,
                'billet_vacated',
                'Fin d’occupation de poste',
                $actorUserId,
                ['holder_id' => $holderId, 'billet_id' => $billetId],
                $reason
            );
        }

        return ['ok' => true];
    }

    /**
     * @return array{
     *   authorized: int,
     *   filled: int,
     *   vacant: int,
     *   available: int,
     *   unavailable: int,
     *   label: string,
     *   admin_label: string,
     *   billets: list<array<string, mixed>>
     * }
     */
    public function unitManning(int $tenantId, int $unitId, ?string $asOf = null): array
    {
        $base = $this->billets->manningForUnit($tenantId, $unitId, $asOf);
        $available = 0;
        $unavailable = 0;
        foreach ($base['billets'] as $b) {
            foreach ($b['holders'] as $h) {
                $sit = $this->adminSituationForUser($tenantId, (int) $h['user_id']);
                if ($sit === null || $sit['is_available']) {
                    ++$available;
                } else {
                    ++$unavailable;
                }
            }
        }

        return [
            'authorized' => $base['authorized'],
            'filled' => $base['filled'],
            'vacant' => $base['vacant'],
            'available' => $available,
            'unavailable' => $unavailable,
            'label' => sprintf('%d postes / %d pourvus / %d vacants', $base['authorized'], $base['filled'], $base['vacant']),
            'admin_label' => sprintf(
                '%d affectés — %d disponibles — %d indisponibles — %d postes vacants',
                $base['filled'],
                $available,
                $unavailable,
                $base['vacant']
            ),
            'billets' => array_map(static function (array $b): array {
                $b['seat_label'] = BilletStatus::seatLabel(
                    (string) ($b['status'] ?? 'active'),
                    (int) ($b['filled'] ?? 0),
                    (int) ($b['authorized'] ?? 1)
                );
                foreach ($b['holders'] as &$h) {
                    $h['occupancy_label'] = BilletOccupancyType::label((string) ($h['occupancy_type'] ?? 'primary'));
                }
                unset($h);

                return $b;
            }, $base['billets']),
        ];
    }

    /**
     * @return list<array{billet_id: int, title: string, kind: string, user_id: int, label: string, occupancy_type: string}>
     */
    public function derivedCommand(int $tenantId, int $unitId, ?string $asOf = null): array
    {
        $manning = $this->billets->manningForUnit($tenantId, $unitId, $asOf);
        $out = [];
        foreach ($manning['billets'] as $b) {
            if (empty($b['is_key_post']) && empty($b['is_critical'])) {
                continue;
            }
            foreach ($b['holders'] as $h) {
                if (!in_array($h['occupancy_type'], ['primary', 'acting'], true)) {
                    continue;
                }
                $out[] = [
                    'billet_id' => $b['id'],
                    'title' => $b['title'],
                    'kind' => $b['key_post_kind'] !== '' ? $b['key_post_kind'] : ($b['is_critical'] ? 'critical' : 'key'),
                    'user_id' => $h['user_id'],
                    'label' => $h['label'],
                    'occupancy_type' => $h['occupancy_type'],
                ];
            }
        }

        return $out;
    }

    /**
     * @return list<array{code: string, severity: string, message: string, meta?: array<string, mixed>}>
     */
    public function detectAnomalies(int $tenantId): array
    {
        $anomalies = [];
        if (!$this->schemaReady()) {
            return $anomalies;
        }
        $byUnit = $this->billets->manningByUnitForTenant($tenantId);
        foreach ($byUnit as $unitId => $m) {
            $detail = $this->billets->manningForUnit($tenantId, (int) $unitId);
            foreach ($detail['billets'] as $b) {
                if ($b['vacant'] > 0) {
                    $anomalies[] = [
                        'code' => 'vacant_billet',
                        'severity' => !empty($b['is_critical']) || !empty($b['is_key_post']) ? 'high' : 'medium',
                        'message' => sprintf('Poste vacant : %s (%d)', $b['title'], $b['vacant']),
                        'meta' => ['unit_id' => (int) $unitId, 'billet_id' => $b['id']],
                    ];
                }
                $primary = 0;
                foreach ($b['holders'] as $h) {
                    if ($h['occupancy_type'] === 'primary') {
                        ++$primary;
                    }
                }
                if ($primary > $b['authorized']) {
                    $anomalies[] = [
                        'code' => 'duplicate_primary',
                        'severity' => 'high',
                        'message' => 'Plusieurs titulaires sur le poste « ' . $b['title'] . ' »',
                        'meta' => ['unit_id' => (int) $unitId, 'billet_id' => $b['id']],
                    ];
                }
                $packId = $b['required_pack_id'] ?? null;
                if ($packId) {
                    foreach ($b['holders'] as $h) {
                        $missing = $this->missingRequiredQualifications($tenantId, (int) $h['user_id'], (int) $packId);
                        if ($missing !== []) {
                            $anomalies[] = [
                                'code' => 'missing_qualification',
                                'severity' => 'medium',
                                'message' => $h['label'] . ' — qualifications manquantes sur « ' . $b['title'] . ' » : ' . implode(', ', $missing),
                                'meta' => ['user_id' => $h['user_id'], 'billet_id' => $b['id']],
                            ];
                        }
                    }
                }
            }
            $unit = $this->units->findById((int) $unitId, $tenantId);
            if ($unit) {
                $admin = UnitAdminStatus::normalize((string) ($unit['admin_status'] ?? 'active'));
                if (
                    (UnitAdminStatus::isAssignableForbidden($admin) || $admin === UnitAdminStatus::INACTIVE)
                    && $m['filled'] > 0
                ) {
                    $anomalies[] = [
                        'code' => 'assignment_on_inactive_unit',
                        'severity' => 'medium',
                        'message' => 'Affectations actives sur structure archivée/inactive : ' . (string) ($unit['name'] ?? $unitId),
                        'meta' => ['unit_id' => (int) $unitId],
                    ];
                }
                $cmd = $this->derivedCommand($tenantId, (int) $unitId);
                $hasKeyDefined = false;
                foreach ($detail['billets'] as $b) {
                    if (!empty($b['is_key_post']) || !empty($b['is_critical'])) {
                        $hasKeyDefined = true;
                        break;
                    }
                }
                if ($hasKeyDefined && $cmd === []) {
                    $anomalies[] = [
                        'code' => 'unit_without_commander',
                        'severity' => 'high',
                        'message' => 'Structure sans responsable de poste clé : ' . (string) ($unit['name'] ?? $unitId),
                        'meta' => ['unit_id' => (int) $unitId],
                    ];
                }
            }
        }

        return $anomalies;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok: bool, id?: int, warnings?: list<string>, message?: string}
     */
    public function assignTemporary(int $tenantId, array $data, ?int $actorUserId = null): array
    {
        $billetId = (int) ($data['billet_id'] ?? 0);
        $userId = (int) ($data['user_id'] ?? 0);
        if ($billetId < 1 || $userId < 1) {
            return ['ok' => false, 'message' => 'Poste et personnel requis.'];
        }
        $data['occupancy_type'] = strtolower(trim((string) ($data['occupancy_type'] ?? 'acting')));
        if (!in_array($data['occupancy_type'], ['acting', 'deputy', 'alternate'], true)) {
            $data['occupancy_type'] = 'acting';
        }
        $data['keeps_organic_billet'] = 1;
        $data['movement_reason'] = $data['movement_reason'] ?? 'temporary';

        return $this->occupy($tenantId, $billetId, $data, $actorUserId);
    }

    /**
     * @return array{user_id: int, superior: ?array<string, mixed>, subordinates: list<array<string, mixed>>, organic_billet?: array<string, mixed>}
     */
    public function personnelCenteredChart(int $tenantId, int $userId, ?string $asOf = null): array
    {
        $asOf = $asOf ?? date('Y-m-d');
        $primaries = $this->billets->primaryBilletsForUser($tenantId, $userId, $asOf);
        if ($primaries === []) {
            return ['user_id' => $userId, 'superior' => null, 'subordinates' => []];
        }
        $my = $primaries[0];
        $unitId = (int) ($my['unit_id'] ?? 0);
        $superior = null;
        $subordinates = [];
        if ($unitId > 0) {
            foreach ($this->derivedCommand($tenantId, $unitId, $asOf) as $c) {
                if ((int) $c['user_id'] === $userId) {
                    continue;
                }
                $superior = [
                    'user_id' => $c['user_id'],
                    'label' => $c['label'],
                    'billet_title' => $c['title'],
                    'occupancy_type' => $c['occupancy_type'],
                ];
                break;
            }
            $manning = $this->billets->manningForUnit($tenantId, $unitId, $asOf);
            foreach ($manning['billets'] as $b) {
                if ((int) $b['id'] === (int) ($my['billet_id'] ?? 0)) {
                    continue;
                }
                foreach ($b['holders'] as $h) {
                    if ((int) $h['user_id'] === $userId) {
                        continue;
                    }
                    if (!in_array($h['occupancy_type'], ['primary', 'acting'], true)) {
                        continue;
                    }
                    $subordinates[] = [
                        'user_id' => $h['user_id'],
                        'label' => $h['label'],
                        'billet_title' => $b['title'],
                        'occupancy_type' => $h['occupancy_type'],
                    ];
                }
            }
        }

        return [
            'user_id' => $userId,
            'superior' => $superior,
            'subordinates' => $subordinates,
            'organic_billet' => [
                'billet_id' => (int) ($my['billet_id'] ?? 0),
                'title' => (string) ($my['billet_title'] ?? ''),
                'org_callsign' => (string) ($my['org_callsign'] ?? ''),
                'unit_id' => $unitId,
            ],
        ];
    }

    /**
     * Fiche structure enrichie : effectifs, commandement dérivé, postes, signaux objectifs.
     *
     * @return array<string, mixed>
     */
    public function structureSheet(int $tenantId, int $unitId, ?string $asOf = null): array
    {
        $unit = $this->units->findById($unitId, $tenantId);
        if ($unit === null) {
            return ['ok' => false, 'message' => 'Structure introuvable.'];
        }
        $manning = $this->unitManning($tenantId, $unitId, $asOf);
        $command = $this->derivedCommand($tenantId, $unitId, $asOf);
        $signals = $this->capacitySignals($tenantId, $unitId, $manning, $command);

        return [
            'ok' => true,
            'unit' => [
                'id' => $unitId,
                'name' => (string) ($unit['name'] ?? ''),
                'type' => (string) ($unit['type'] ?? ''),
                'admin_status' => UnitAdminStatus::normalize((string) ($unit['admin_status'] ?? 'active')),
                'parent_id' => isset($unit['parent_id']) ? (int) $unit['parent_id'] : null,
                'visibility_level' => (string) ($unit['visibility_level'] ?? 'normal'),
                'description' => (string) ($unit['description'] ?? $unit['orbat_details'] ?? ''),
            ],
            'manning' => $manning,
            'command' => $command,
            'capacity_signals' => $signals,
            'domain_model' => OrgDomainModel::catalog(),
            'occupancy_types' => BilletOccupancyType::options(),
        ];
    }

    /**
     * Faits objectifs (pas un jugement « non opérationnel »).
     *
     * @param array<string, mixed> $manning
     * @param list<array<string, mixed>> $command
     * @return list<array{code: string, severity: string, message: string}>
     */
    public function capacitySignals(int $tenantId, int $unitId, ?array $manning = null, ?array $command = null): array
    {
        $manning ??= $this->unitManning($tenantId, $unitId);
        $command ??= $this->derivedCommand($tenantId, $unitId);
        $signals = [];
        if ((int) ($manning['vacant'] ?? 0) > 0) {
            $signals[] = [
                'code' => 'vacant_billets',
                'severity' => 'medium',
                'message' => (int) $manning['vacant'] . ' poste(s) vacant(s) sur ' . (int) $manning['authorized'],
            ];
        }
        $criticalVacant = 0;
        $missingQual = 0;
        foreach ($manning['billets'] ?? [] as $b) {
            if ((!empty($b['is_critical']) || !empty($b['is_key_post'])) && (int) ($b['vacant'] ?? 0) > 0) {
                ++$criticalVacant;
            }
            $packId = (int) ($b['required_pack_id'] ?? 0);
            if ($packId > 0) {
                foreach ($b['holders'] ?? [] as $h) {
                    if ($this->missingRequiredQualifications($tenantId, (int) ($h['user_id'] ?? 0), $packId) !== []) {
                        ++$missingQual;
                    }
                }
            }
        }
        if ($criticalVacant > 0) {
            $signals[] = [
                'code' => 'critical_billet_vacant',
                'severity' => 'high',
                'message' => $criticalVacant . ' poste(s) clé/critique(s) vacant(s)',
            ];
        }
        if ($missingQual > 0) {
            $signals[] = [
                'code' => 'missing_expected_qualifications',
                'severity' => 'medium',
                'message' => $missingQual . ' titulaire(s) sans qualification attendue',
            ];
        }
        if ($command === []) {
            $hasKey = false;
            foreach ($manning['billets'] ?? [] as $b) {
                if (!empty($b['is_key_post']) || !empty($b['is_critical'])) {
                    $hasKey = true;
                    break;
                }
            }
            if ($hasKey) {
                $signals[] = [
                    'code' => 'no_derived_command',
                    'severity' => 'high',
                    'message' => 'Aucun responsable dérivé des postes clés actuellement pourvus',
                ];
            }
        }
        if ((int) ($manning['unavailable'] ?? 0) > 0) {
            $signals[] = [
                'code' => 'unavailable_assignees',
                'severity' => 'low',
                'message' => (int) $manning['unavailable'] . ' affecté(s) en situation administrative indisponible',
            ];
        }

        return $signals;
    }

    /**
     * Centre « Qualité des données » — synthèse d’anomalies factuelles.
     *
     * @return array{
     *   totals: array<string, int>,
     *   anomalies: list<array{code: string, severity: string, message: string, meta?: array<string, mixed>}>,
     *   movements: list<array<string, mixed>>,
     *   trash: list<array<string, mixed>>,
     *   snapshots: list<array<string, mixed>>,
     *   domain_model: list<array{id: string, label: string, description: string}>
     * }
     */
    public function dataQualitySummary(int $tenantId): array
    {
        $anomalies = $this->detectAnomalies($tenantId);
        $totals = [
            'anomalies' => count($anomalies),
            'vacant_billet' => 0,
            'duplicate_primary' => 0,
            'missing_qualification' => 0,
            'unit_without_commander' => 0,
            'assignment_on_inactive_unit' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
        ];
        foreach ($anomalies as $a) {
            $code = (string) ($a['code'] ?? '');
            if (isset($totals[$code])) {
                ++$totals[$code];
            }
            $sev = (string) ($a['severity'] ?? 'medium');
            if (isset($totals[$sev])) {
                ++$totals[$sev];
            }
        }

        return [
            'totals' => $totals,
            'anomalies' => $anomalies,
            'movements' => $this->billets->listCareerEvents($tenantId, null, 40),
            'trash' => $this->billets->listDeleted($tenantId, 40),
            'snapshots' => $this->billets->listSnapshots($tenantId, 20),
            'movement_reasons' => $this->billets->listMovementReasons($tenantId),
            'domain_model' => OrgDomainModel::catalog(),
        ];
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    public function restoreBillet(int $tenantId, int $billetId, ?int $actorUserId = null): array
    {
        $before = $this->billets->findById($tenantId, $billetId);
        if ($before === null) {
            return ['ok' => false, 'message' => 'Poste introuvable.'];
        }
        if (!$this->billets->restore($tenantId, $billetId)) {
            return ['ok' => false, 'message' => 'Restauration impossible.'];
        }
        $this->appendCareer(
            $tenantId,
            0,
            'billet_restored',
            'Restauration du poste « ' . (string) ($before['title'] ?? '') . ' »',
            $actorUserId,
            ['billet_id' => $billetId]
        );

        return ['ok' => true];
    }

    /**
     * Snapshot ORBAT (versionnement / préparation de réorganisation).
     *
     * @return array{ok: bool, id?: int, message?: string}
     */
    public function snapshotOrbat(
        int $tenantId,
        string $label,
        string $kind = 'manual',
        ?string $effectiveAt = null,
        ?int $actorUserId = null,
        ?string $notes = null
    ): array {
        $byUnit = $this->billets->manningByUnitForTenant($tenantId, $effectiveAt);
        $payload = [
            'captured_at' => date('c'),
            'kind' => $kind,
            'domain_model' => OrgDomainModel::CORE,
            'units' => [],
        ];
        foreach ($byUnit as $unitId => $m) {
            $sheet = $this->structureSheet($tenantId, (int) $unitId, $effectiveAt);
            if (!empty($sheet['ok'])) {
                $payload['units'][(string) $unitId] = $sheet;
            } else {
                $payload['units'][(string) $unitId] = ['manning' => $m];
            }
        }
        $id = $this->billets->createSnapshot(
            $tenantId,
            $label,
            $payload,
            $kind,
            $effectiveAt,
            $actorUserId,
            $notes
        );
        if ($id < 1) {
            return ['ok' => false, 'message' => 'Création du snapshot impossible (table absente ?).'];
        }

        return ['ok' => true, 'id' => $id];
    }

    /**
     * @return list<string>
     */
    private function missingRequiredQualifications(int $tenantId, int $userId, int $packId): array
    {
        $pdo = $this->pdo();
        if ($pdo === null || $packId < 1 || $userId < 1) {
            return [];
        }
        try {
            $itemTable = $this->tableExists($pdo, 'personnel_qualification_pack_items')
                ? 'personnel_qualification_pack_items'
                : null;
            $defTable = $this->tableExists($pdo, 'personnel_qualification_definitions')
                ? 'personnel_qualification_definitions'
                : null;
            if ($itemTable === null || $defTable === null) {
                return [];
            }
            $owned = $pdo->prepare(
                'SELECT definition_id FROM personnel_qualifications
                 WHERE tenant_id = ? AND user_id = ?
                   AND (expires_at IS NULL OR expires_at >= CURDATE())'
            );
            $owned->execute([$tenantId, $userId]);
            $have = [];
            while ($r = $owned->fetch(PDO::FETCH_ASSOC)) {
                $have[(int) ($r['definition_id'] ?? 0)] = true;
            }
            $st2 = $pdo->prepare(
                "SELECT i.definition_id, d.code, d.name FROM {$itemTable} i
                 INNER JOIN {$defTable} d ON d.id = i.definition_id
                 WHERE i.pack_id = ? AND i.tenant_id = ? AND i.is_required = 1"
            );
            $st2->execute([$packId, $tenantId]);
            $missing = [];
            while ($req = $st2->fetch(PDO::FETCH_ASSOC)) {
                $did = (int) ($req['definition_id'] ?? 0);
                if ($did > 0 && !isset($have[$did])) {
                    $missing[] = (string) ($req['code'] ?? $req['name'] ?? ('#' . $did));
                }
            }

            return $missing;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array{code: string, is_available: bool}|null
     */
    private function adminSituationForUser(int $tenantId, int $userId): ?array
    {
        $pdo = $this->pdo();
        if ($pdo === null || $userId < 1) {
            return null;
        }
        try {
            if (!$this->columnExists($pdo, 'personnel_profiles', 'admin_situation')) {
                return ['code' => 'active', 'is_available' => true];
            }
            $st = $pdo->prepare('SELECT admin_situation FROM personnel_profiles WHERE user_id = ? LIMIT 1');
            $st->execute([$userId]);
            $code = strtolower(trim((string) $st->fetchColumn()));
            if ($code === '') {
                $code = 'active';
            }
            $avail = true;
            if ($this->tableExists($pdo, 'personnel_admin_status_definitions')) {
                $st2 = $pdo->prepare(
                    'SELECT is_available FROM personnel_admin_status_definitions WHERE tenant_id = ? AND code = ? LIMIT 1'
                );
                $st2->execute([$tenantId, $code]);
                $v = $st2->fetchColumn();
                if ($v !== false) {
                    $avail = (int) $v === 1;
                } else {
                    $avail = !in_array($code, ['unavailable', 'absence', 'detached', 'reserve'], true);
                }
            }

            return ['code' => $code, 'is_available' => $avail];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function appendCareer(
        int $tenantId,
        int $userId,
        string $eventType,
        string $summary,
        ?int $actorUserId,
        array $meta = [],
        ?string $reason = null
    ): void {
        $pdo = $this->pdo();
        if ($pdo === null || !$this->tableExists($pdo, 'personnel_career_journal')) {
            return;
        }
        try {
            $st = $pdo->prepare(
                'INSERT INTO personnel_career_journal
                    (tenant_id, user_id, event_type, subject_type, subject_id, summary, movement_reason, actor_user_id, metadata_json, effective_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
            );
            $subjectType = isset($meta['billet_id']) ? 'billet' : null;
            $subjectId = isset($meta['billet_id']) ? (int) $meta['billet_id'] : null;
            $st->execute([
                $tenantId,
                max(0, $userId),
                mb_substr($eventType, 0, 64),
                $subjectType,
                $subjectId,
                mb_substr($summary, 0, 500),
                $reason !== null && $reason !== '' ? mb_substr($reason, 0, 64) : null,
                $actorUserId !== null && $actorUserId > 0 ? $actorUserId : null,
                json_encode($meta, JSON_UNESCAPED_UNICODE) ?: null,
            ]);
        } catch (\Throwable) {
        }
    }

    private function pdo(): ?PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }
        try {
            return $this->pdo = Database::getPdo();
        } catch (\Throwable) {
            return null;
        }
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    }

    private function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    }
}
