<?php

declare(strict_types=1);

namespace App\Services\Effectifs\Dossier\Adapters;

use App\Services\Effectifs\Dossier\DossierCatalog;

/**
 * Départ : le dernier départ enregistré (member_departures), le retrait des accès et l'archivage du dossier.
 * Un membre réintégré n'a plus d'étape de départ. Les trois gestes se font dans Anciens membres.
 */
final class DepartureStepsAdapter extends AbstractDossierAdapter
{
    public const SOURCE_DEPARTURE = 'departure';
    public const SOURCE_ACCESS = 'departure_access';
    public const SOURCE_ARCHIVE = 'departure_archive';

    public function track(): string
    {
        return DossierCatalog::TRACK_DEPARTURE;
    }

    public function stepsFor(int $tenantId, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }
        $rows = $this->rows(
            'SELECT * FROM member_departures WHERE tenant_id = ? AND user_id IN (' . self::marks($userIds) . ') ORDER BY id ASC',
            array_merge([$tenantId], $userIds)
        );
        $latest = [];
        foreach ($rows as $r) {
            $latest[(int) $r['user_id']] = $r;
        }
        $out = [];
        foreach ($latest as $uid => $r) {
            if (DossierCatalog::stamp($r['reinstated_at'] ?? null) !== null) {
                continue;
            }
            $id = (string) $r['id'];
            $url = effectifs_workspace_url('departs');
            $steps = [$this->make([
                'source_type' => self::SOURCE_DEPARTURE,
                'source_id' => $id,
                'track' => DossierCatalog::TRACK_DEPARTURE,
                'title' => 'Départ enregistré',
                'state' => DossierCatalog::STATE_DONE,
                'owner_kind' => DossierCatalog::OWNER_RH,
                'opened_on' => $r['departed_at'],
                'done_at' => $r['created_at'],
                'updated_at' => $r['created_at'],
                'source_label' => 'Anciens membres',
                'source_url' => $url,
                'pos' => 10,
            ])];
            $revoked = !empty($r['access_revoked']);
            $steps[] = $this->make([
                'source_type' => self::SOURCE_ACCESS,
                'source_id' => $id,
                'track' => DossierCatalog::TRACK_DEPARTURE,
                'title' => 'Retirer les accès du membre',
                'state' => $revoked ? DossierCatalog::STATE_DONE : DossierCatalog::STATE_TODO,
                'owner_kind' => DossierCatalog::OWNER_RH,
                'opened_on' => $r['departed_at'],
                'done_at' => $r['access_revoked_at'] ?? null,
                'updated_at' => $r['access_revoked_at'] ?? $r['created_at'],
                'source_label' => 'Anciens membres',
                'source_url' => $url,
                'pos' => 20,
            ]);
            if (array_key_exists('dossier_archived_at', $r)) {
                $archived = DossierCatalog::stamp($r['dossier_archived_at']) !== null;
                $steps[] = $this->make([
                    'source_type' => self::SOURCE_ARCHIVE,
                    'source_id' => $id,
                    'track' => DossierCatalog::TRACK_DEPARTURE,
                    'title' => 'Archiver le dossier RH',
                    'state' => $archived ? DossierCatalog::STATE_DONE : DossierCatalog::STATE_TODO,
                    'owner_kind' => DossierCatalog::OWNER_RH,
                    'opened_on' => $r['departed_at'],
                    'done_at' => $r['dossier_archived_at'],
                    'updated_at' => $r['dossier_archived_at'] ?? $r['created_at'],
                    'source_label' => 'Anciens membres',
                    'source_url' => $url,
                    'pos' => 30,
                ]);
            }
            $out[$uid] = $steps;
        }

        return $out;
    }
}
