<?php

declare(strict_types=1);

namespace App\Services\Effectifs\Dossier\Adapters;

use App\Services\Effectifs\Dossier\DossierCatalog;

/**
 * Carrière : demandes d'élévation (elevation_requests) et de mobilité (personnel_mobility_requests) en cours, et
 * celles décidées depuis 90 jours. La décision se prend dans Élévations ou Mobilité (circuits d'avis) : lecture seule ici.
 */
final class CareerStepsAdapter extends AbstractDossierAdapter
{
    public const SOURCE_ELEVATION = 'elevation';
    public const SOURCE_MOBILITY = 'mobility';
    public const RECENT_DAYS = 90;

    public function track(): string
    {
        return DossierCatalog::TRACK_CAREER;
    }

    public function stepsFor(int $tenantId, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }
        $marks = self::marks($userIds);
        $since = date('Y-m-d H:i:s', strtotime('-' . self::RECENT_DAYS . ' days'));
        $out = [];
        foreach ($this->rows(
            "SELECT id, target_user_id AS uid, kind, status, created_at, resolved_at, updated_at FROM elevation_requests
              WHERE tenant_id = ? AND target_user_id IN ($marks) AND (status IN ('pending', 'in_review') OR resolved_at >= ?) ORDER BY id ASC",
            array_merge([$tenantId], $userIds, [$since])
        ) as $r) {
            $status = (string) $r['status'];
            $open = in_array($status, ['pending', 'in_review'], true);
            $out[(int) $r['uid']][] = $this->make([
                'source_type' => self::SOURCE_ELEVATION,
                'source_id' => (string) $r['id'],
                'track' => DossierCatalog::TRACK_CAREER,
                'title' => 'Demande d’élévation',
                'description' => match ($status) {
                    'approved' => 'Demande acceptée.',
                    'rejected' => 'Demande refusée.',
                    'in_review' => 'Demande à l’examen.',
                    default => 'Demande en attente de décision.',
                },
                'state' => $open ? DossierCatalog::STATE_IN_PROGRESS : DossierCatalog::STATE_DONE,
                'owner_kind' => DossierCatalog::OWNER_RH,
                'opened_on' => $r['created_at'],
                'done_at' => $open ? null : $r['resolved_at'],
                'updated_at' => $r['updated_at'] ?: $r['created_at'],
                'source_label' => 'Élévations',
                'source_url' => effectifs_workspace_url('elevations'),
                'pos' => 10,
            ]);
        }
        foreach ($this->rows(
            "SELECT id, user_id AS uid, request_type, target_label, status, created_at, reviewed_at FROM personnel_mobility_requests
              WHERE tenant_id = ? AND user_id IN ($marks) AND (status = 'pending' OR reviewed_at >= ?) ORDER BY id ASC",
            array_merge([$tenantId], $userIds, [$since])
        ) as $r) {
            $open = (string) $r['status'] === 'pending';
            $label = trim((string) ($r['target_label'] ?? ''));
            $out[(int) $r['uid']][] = $this->make([
                'source_type' => self::SOURCE_MOBILITY,
                'source_id' => (string) $r['id'],
                'track' => DossierCatalog::TRACK_CAREER,
                'title' => 'Demande de mobilité' . ($label !== '' ? ' : ' . $label : ''),
                'description' => match ((string) $r['status']) {
                    'approved', 'applied' => 'Demande acceptée.',
                    'rejected' => 'Demande refusée.',
                    'cancelled' => 'Demande annulée par le membre.',
                    default => 'Demande en attente de décision.',
                },
                'state' => $open ? DossierCatalog::STATE_IN_PROGRESS : ((string) $r['status'] === 'cancelled' ? DossierCatalog::STATE_SKIPPED : DossierCatalog::STATE_DONE),
                'owner_kind' => DossierCatalog::OWNER_RH,
                'opened_on' => $r['created_at'],
                'done_at' => $open ? null : $r['reviewed_at'],
                'updated_at' => $r['reviewed_at'] ?: $r['created_at'],
                'source_label' => 'Mobilité',
                'source_url' => effectifs_workspace_url('mobilite'),
                'pos' => 20,
            ]);
        }

        return $out;
    }
}
