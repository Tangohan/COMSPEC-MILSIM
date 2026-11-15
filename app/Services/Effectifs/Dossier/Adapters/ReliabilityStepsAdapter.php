<?php

declare(strict_types=1);

namespace App\Services\Effectifs\Dossier\Adapters;

use App\Services\Effectifs\Dossier\DossierCatalog;

/**
 * Fiabilisation des données : la campagne la plus récente qui concerne le membre (data_campaign_members), avec sa
 * date limite, la confirmation du membre et les signalements encore ouverts (à traiter par l'encadrement).
 * Le membre confirme lui-même sa fiche : aucune validation à sa place depuis le suivi (relance seulement).
 */
final class ReliabilityStepsAdapter extends AbstractDossierAdapter
{
    public const SOURCE_CAMPAIGN = 'campaign';
    public const SOURCE_FLAG = 'campaign_flag';

    public function track(): string
    {
        return DossierCatalog::TRACK_RELIABILITY;
    }

    public function stepsFor(int $tenantId, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }
        $rows = $this->rows(
            'SELECT m.campaign_id, m.user_id, m.confirmed_at, m.first_seen_at, m.updated_at, c.title, c.status, c.due_on, c.opens_on, c.opened_at, c.closed_at
               FROM data_campaign_members m JOIN data_campaigns c ON c.id = m.campaign_id AND c.tenant_id = m.tenant_id
              WHERE m.tenant_id = ? AND m.user_id IN (' . self::marks($userIds) . ') ORDER BY c.id ASC',
            array_merge([$tenantId], $userIds)
        );
        $latest = [];
        foreach ($rows as $r) {
            $latest[(int) $r['user_id']] = $r;
        }
        $out = [];
        foreach ($latest as $uid => $r) {
            $campaign = (int) $r['campaign_id'];
            $confirmed = DossierCatalog::stamp($r['confirmed_at']) !== null;
            $open = (string) $r['status'] === 'open';
            if (!$confirmed && !$open) {
                // Campagne brouillon ou close sans réponse : rien à suivre.
                continue;
            }
            $out[$uid][] = $this->make([
                'source_type' => self::SOURCE_CAMPAIGN,
                'source_id' => (string) $campaign,
                'track' => DossierCatalog::TRACK_RELIABILITY,
                'title' => 'Relire et confirmer sa fiche : ' . trim((string) $r['title']),
                'state' => $confirmed ? DossierCatalog::STATE_DONE : (DossierCatalog::stamp($r['first_seen_at']) !== null ? DossierCatalog::STATE_IN_PROGRESS : DossierCatalog::STATE_TODO),
                'owner_kind' => DossierCatalog::OWNER_MEMBER,
                'opened_on' => $r['opens_on'] ?: $r['opened_at'],
                'due_on' => $confirmed ? null : $r['due_on'],
                'done_at' => $r['confirmed_at'],
                'updated_at' => $r['updated_at'] ?: $r['opened_at'],
                'source_label' => 'Campagne de fiabilisation',
                'source_url' => url('back-office/organisation/fiabilisation/' . $campaign),
                'pos' => 10,
            ]);
        }
        $flagUsers = array_keys($latest);
        if ($flagUsers !== []) {
            foreach ($this->rows(
                "SELECT id, campaign_id, user_id, block_key, message, created_at FROM data_campaign_flags
                  WHERE tenant_id = ? AND status = 'open' AND user_id IN (" . self::marks($flagUsers) . ') ORDER BY id ASC',
                array_merge([$tenantId], $flagUsers)
            ) as $f) {
                if ((int) $f['campaign_id'] !== (int) ($latest[(int) $f['user_id']]['campaign_id'] ?? 0)) {
                    continue;
                }
                $out[(int) $f['user_id']][] = $this->make([
                    'source_type' => self::SOURCE_FLAG,
                    'source_id' => (string) $f['id'],
                    'track' => DossierCatalog::TRACK_RELIABILITY,
                    'title' => 'Répondre au signalement du membre (' . trim((string) $f['block_key']) . ')',
                    'description' => mb_substr(trim((string) $f['message']), 0, 240),
                    'state' => DossierCatalog::STATE_IN_PROGRESS,
                    'owner_kind' => DossierCatalog::OWNER_RH,
                    'opened_on' => $f['created_at'],
                    'updated_at' => $f['created_at'],
                    'source_label' => 'Campagne de fiabilisation',
                    'source_url' => url('back-office/organisation/fiabilisation/' . (int) $f['campaign_id']),
                    'pos' => 20,
                ]);
            }
        }

        return $out;
    }
}
