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
     * Message d’accueil (HTML) pour le premier message du fil coop.
     *
     * @param array<string, mixed> $mission
     * @param list<array<string, mixed>> $participants
     */
    public function buildPinnedWelcomeBody(array $mission, array $participants, string $meetingUrl, string $orbatUrl, string $exchangeUrl): string
    {
        $title = htmlspecialchars((string) ($mission['title'] ?? ''), ENT_QUOTES, 'UTF-8');
        $phase = CooperationDictionary::phaseLabel(CooperationDictionary::effectivePhase($mission));
        $lines = [];
        $lines[] = '<p><strong>Objet</strong> : ' . $title . '</p>';
        $lines[] = '<p><strong>Unités engagées</strong> :</p><ul>';
        foreach ($participants as $p) {
            if (($p['status'] ?? '') !== 'active') {
                continue;
            }
            $nm = htmlspecialchars((string) ($p['tenant_name'] ?? ''), ENT_QUOTES, 'UTF-8');
            $rl = htmlspecialchars(CooperationDictionary::participantRoleLabel((string) ($p['role'] ?? '')), ENT_QUOTES, 'UTF-8');
            $lines[] = '<li>' . $nm . ' — ' . $rl . '</li>';
        }
        $lines[] = '</ul>';
        $lines[] = '<p><strong>État actuel</strong> : ' . htmlspecialchars($phase, ENT_QUOTES, 'UTF-8') . '</p>';
        $lines[] = '<p><strong>Règles de partage</strong> : chaque unité valide son autorisation de partage (code par e-mail) avant d’écrire sur ce fil.</p>';
        $lines[] = '<p><strong>Liens utiles</strong> :</p><ul>';
        $lines[] = '<li><a href="' . htmlspecialchars($meetingUrl, ENT_QUOTES, 'UTF-8') . '">Réunion</a></li>';
        $lines[] = '<li><a href="' . htmlspecialchars($orbatUrl, ENT_QUOTES, 'UTF-8') . '">Structures &amp; liaisons</a></li>';
        $lines[] = '<li><a href="' . htmlspecialchars($exchangeUrl, ENT_QUOTES, 'UTF-8') . '">Synthèse espace commun</a></li>';
        $lines[] = '</ul>';
        $lines[] = '<p class="text-sm text-slate-600"><strong>Consignes</strong> : ne diffusez que des informations couvertes par les autorisations validées par votre hiérarchie et les échanges officiels de cette coopération.</p>';

        return implode("\n", $lines);
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
