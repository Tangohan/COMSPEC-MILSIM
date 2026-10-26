<?php

declare(strict_types=1);

namespace App\Services\Cooperation;

use App\Repositories\InterteamMissionRepository;
use App\Repositories\UnitRepository;
use App\Support\CooperationDictionary;

/**
 * Snapshots d’activation, contenu épinglé espace commun, expiration propositions.
 */
final class CooperationWorkflowService
{
    public function __construct(
        private InterteamMissionRepository $missions,
        private UnitRepository $units
    ) {}

    /**
     * @param array<string, mixed> $mission
     * @return array<string, mixed>
     */
    public function buildActivationSnapshot(array $mission, int $hostTenantId): array
    {
        $parts = $this->missions->listParticipants((int) ($mission['id'] ?? 0));
        $tenants = [];
        foreach ($parts as $p) {
            if (($p['status'] ?? '') !== 'active') {
                continue;
            }
            $tid = (int) ($p['tenant_id'] ?? 0);
            if ($tid <= 0) {
                continue;
            }
            $units = $this->units->listPublicForTenant($tid);
            if ($units === []) {
                $units = $this->units->allForTenant($tid);
            }
            $tenants[] = [
                'tenant_id' => $tid,
                'tenant_name' => (string) ($p['tenant_name'] ?? ''),
                'role' => (string) ($p['role'] ?? ''),
                'units_preview' => array_slice($units, 0, 40),
            ];
        }

        return [
            'captured_at' => date('c'),
            'mission_id' => (int) ($mission['id'] ?? 0),
            'title' => (string) ($mission['title'] ?? ''),
            'phase' => CooperationDictionary::effectivePhase($mission),
            'typology' => (string) ($mission['cooperation_typology'] ?? ''),
            'priority' => (string) ($mission['cooperation_priority'] ?? ''),
            'tenants' => $tenants,
            'atak' => [
                'primary' => (string) ($mission['atak_endpoint_primary'] ?? ''),
                'partner' => (string) ($mission['atak_endpoint_partner'] ?? ''),
                'primary_label' => (string) ($mission['atak_primary_label'] ?? ''),
                'partner_label' => (string) ($mission['atak_partner_label'] ?? ''),
            ],
            'liaison_notes' => (string) ($mission['liaison_notes'] ?? ''),
        ];
    }

    public function persistActivationSnapshot(int $missionId, array $snapshot): void
    {
        $this->missions->setActivationSnapshotJson($missionId, json_encode($snapshot, JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return list<string> lignes non vides
     */
    public static function parseSuspensiveConditionsFromText(string $raw): array
    {
        $out = [];
        foreach (preg_split('/\R+/u', $raw) ?: [] as $line) {
            $t = trim($line);
            if ($t !== '') {
                $out[] = mb_substr($t, 0, 500);
            }
        }

        return $out;
    }
}
