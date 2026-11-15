<?php

declare(strict_types=1);

namespace App\Services\Effectifs\Dossier\Adapters;

use App\Services\Effectifs\Dossier\DossierCatalog;
use App\Support\MemberIntegrationCatalog;

/**
 * Étapes du parcours d'intégration (member_integration_steps) : le dernier parcours non annulé de chaque membre.
 * Les étapes « document à lire » vont au parcours Documents, les formations facultatives et groupes de suivi à
 * Formation ; le reste reste dans Intégration. Source de vérité : MemberIntegrationService (validation, journal).
 */
final class IntegrationStepsAdapter extends AbstractDossierAdapter
{
    public const SOURCE = 'integration_step';

    public function track(): string
    {
        return DossierCatalog::TRACK_INTEGRATION;
    }

    public function stepsFor(int $tenantId, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }
        $rows = $this->rows(
            'SELECT s.id, s.integration_id, s.title, s.description, s.step_type, s.responsible_kind, s.responsible_user_id, s.status,
                    s.due_at, s.is_required, s.started_at, s.completed_at, s.created_at, s.updated_at, s.position, s.step_key,
                    i.user_id
               FROM member_integration_steps s
               JOIN member_integrations i ON i.id = s.integration_id AND i.tenant_id = s.tenant_id
              WHERE s.tenant_id = ? AND i.user_id IN (' . self::marks($userIds) . ") AND i.status <> 'cancelled'
              ORDER BY i.id ASC, s.position ASC, s.id ASC",
            array_merge([$tenantId], $userIds)
        );
        // Un membre peut avoir eu plusieurs parcours : on ne garde que le plus récent.
        $latest = [];
        foreach ($rows as $r) {
            $uid = (int) $r['user_id'];
            $latest[$uid] = max($latest[$uid] ?? 0, (int) $r['integration_id']);
        }
        $out = [];
        foreach ($rows as $r) {
            $uid = (int) $r['user_id'];
            if ((int) $r['integration_id'] !== ($latest[$uid] ?? 0) || MemberIntegrationCatalog::isForumStep($r)) {
                continue;
            }
            $type = (string) ($r['step_type'] ?? '');
            $track = match ($type) {
                MemberIntegrationCatalog::TYPE_DOCUMENT_READ => DossierCatalog::TRACK_DOCUMENTS,
                MemberIntegrationCatalog::TYPE_LMS_OPTIONAL, MemberIntegrationCatalog::TYPE_MATRIX_ASSIGN => DossierCatalog::TRACK_FORMATION,
                default => DossierCatalog::TRACK_INTEGRATION,
            };
            $step = $this->make([
                'source_type' => self::SOURCE,
                'source_id' => (string) $r['id'],
                'track' => $track,
                'title' => (string) $r['title'],
                'description' => (string) ($r['description'] ?? ''),
                'state' => self::state((string) $r['status']),
                'optional' => empty($r['is_required']),
                'owner_kind' => self::owner((string) ($r['responsible_kind'] ?? '')),
                'owner_user_id' => (int) ($r['responsible_user_id'] ?? 0),
                'opened_on' => $r['started_at'] ?: $r['created_at'],
                'due_on' => $r['due_at'],
                'done_at' => $r['completed_at'],
                'updated_at' => $r['updated_at'] ?: $r['created_at'],
                'source_label' => MemberIntegrationCatalog::stepTypeLabels()[$type] ?? 'Parcours d’intégration',
                'source_url' => url('back-office/integration-membres/' . (int) $r['integration_id']),
                'pos' => (int) $r['position'],
            ]);
            $step['integration_id'] = (int) $r['integration_id'];
            $step['step_type'] = $type;
            $step['integration_status_raw'] = (string) $r['status'];
            $out[$uid][] = $step;
        }

        return $out;
    }

    public static function state(string $status): string
    {
        return match ($status) {
            MemberIntegrationCatalog::STEP_COMPLETED => DossierCatalog::STATE_DONE,
            MemberIntegrationCatalog::STEP_SKIPPED, MemberIntegrationCatalog::STEP_CANCELLED => DossierCatalog::STATE_SKIPPED,
            MemberIntegrationCatalog::STEP_BLOCKED => DossierCatalog::STATE_BLOCKED,
            MemberIntegrationCatalog::STEP_IN_PROGRESS, MemberIntegrationCatalog::STEP_WAITING_MEMBER, MemberIntegrationCatalog::STEP_WAITING_STAFF => DossierCatalog::STATE_IN_PROGRESS,
            default => DossierCatalog::STATE_TODO,
        };
    }

    private static function owner(string $kind): string
    {
        return match ($kind) {
            MemberIntegrationCatalog::RESP_MEMBER => DossierCatalog::OWNER_MEMBER,
            MemberIntegrationCatalog::RESP_REFERENT => DossierCatalog::OWNER_REFERENT,
            MemberIntegrationCatalog::RESP_HR => DossierCatalog::OWNER_RH,
            MemberIntegrationCatalog::RESP_RECRUITMENT => DossierCatalog::OWNER_RECRUITMENT,
            MemberIntegrationCatalog::RESP_INSTRUCTOR => DossierCatalog::OWNER_INSTRUCTOR,
            default => DossierCatalog::OWNER_OTHER,
        };
    }
}
