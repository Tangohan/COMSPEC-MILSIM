<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\QualificationAwardRepository;
use App\Repositories\QualificationDefinitionRepository;
use App\Repositories\QualificationReferentielRepository;
use App\Support\QualificationAdminStatus;
use RuntimeException;

final class QualificationStatusTransitionService
{
    public function __construct(
        private QualificationAwardRepository $awards,
        private QualificationDefinitionRepository $definitions,
        private QualificationReferentielRepository $referentiel,
        private QualificationTemporalStatusService $temporal,
        private QualificationPermissionGrantService $permissionGrants,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function award(int $tenantId, int $userId, array $data, ?int $actorId = null): int
    {
        $definitionId = (int) ($data['definition_id'] ?? 0);
        if ($definitionId <= 0) {
            throw new RuntimeException('Une qualification du référentiel est obligatoire.');
        }
        $def = $this->definitions->find($tenantId, $definitionId);
        if ($def === null || !empty($def['archived_at'])) {
            throw new RuntimeException('Cette qualification n’est plus disponible.');
        }

        $levelId = isset($data['qualification_level_id']) && $data['qualification_level_id'] !== ''
            ? (int) $data['qualification_level_id'] : null;
        if (!empty($def['uses_levels']) && $levelId === null) {
            throw new RuntimeException('Un niveau est requis pour cette qualification.');
        }
        if (!empty($def['enforce_level_progression']) && $levelId !== null) {
            $this->assertLevelProgression($tenantId, $userId, $definitionId, $levelId);
        }

        $obtainedAt = $data['obtained_at'] ?? null;
        if (($data['admin_status'] ?? 'obtained') === QualificationAdminStatus::OBTAINED
            || ($data['admin_status'] ?? '') === ''
        ) {
            if ($obtainedAt === null || $obtainedAt === '') {
                $obtainedAt = (new \DateTimeImmutable('today'))->format('Y-m-d');
            }
        }

        $expiresAt = $data['expires_at'] ?? null;
        if (($expiresAt === null || $expiresAt === '') && empty($data['expires_at_manual'])) {
            $expiresAt = $this->temporal->computeDefaultExpiresAt(
                is_string($obtainedAt) ? $obtainedAt : null,
                isset($def['default_validity_months']) ? (int) $def['default_validity_months'] : null,
                !empty($def['is_permanent'])
            );
        }

        if (!empty($data['is_primary'])) {
            $this->awards->clearPrimaryForUser($userId);
        }

        $payload = array_merge($data, [
            'definition_id' => $definitionId,
            'qualification_name' => (string) ($def['name'] ?? 'Qualification'),
            'level' => $data['level'] ?? null,
            'obtained_at' => $obtainedAt,
            'expires_at' => $expiresAt,
            'admin_status' => QualificationAdminStatus::normalize(
                (string) ($data['admin_status'] ?? QualificationAdminStatus::OBTAINED)
            ),
            'source' => $data['source'] ?? 'manual',
        ]);

        if ($levelId !== null) {
            $levels = $this->referentiel->listLevels($tenantId, $definitionId);
            foreach ($levels as $lvl) {
                if ((int) $lvl['id'] === $levelId) {
                    $payload['level'] = (string) ($lvl['short_name'] ?: $lvl['name']);
                    break;
                }
            }
            $payload['qualification_level_id'] = $levelId;
        }

        $id = $this->awards->create($tenantId, $userId, $payload, $actorId);
        $this->awards->addHistory(
            $tenantId,
            $id,
            'awarded',
            $actorId,
            'Attribution : ' . QualificationAdminStatus::label($payload['admin_status'])
        );

        if (!empty($data['panel_members']) && is_array($data['panel_members']) && !empty($def['requires_panel'])) {
            $this->awards->replacePanelMembers($tenantId, $id, $data['panel_members']);
        }

        if (!empty($data['custom_values']) && is_array($data['custom_values'])) {
            foreach ($data['custom_values'] as $fieldId => $value) {
                $this->awards->setCustomValue($tenantId, $id, (int) $fieldId, $value !== null ? (string) $value : null);
            }
        }

        if ($payload['admin_status'] === QualificationAdminStatus::OBTAINED) {
            $this->permissionGrants->applyGrants($tenantId, $userId, $definitionId, $levelId, $actorId);
        }

        return $id;
    }

    public function transition(
        int $tenantId,
        int $awardId,
        string $toStatus,
        ?int $actorId = null,
        ?string $reason = null
    ): void {
        $award = $this->awards->find($awardId, $tenantId);
        if ($award === null) {
            throw new RuntimeException('Attribution introuvable.');
        }
        $from = QualificationAdminStatus::normalize(
            (string) ($award['admin_status'] ?? $award['status'] ?? '')
        );
        $to = QualificationAdminStatus::normalize($toStatus);
        if ($from === $to) {
            return;
        }
        if (!QualificationAdminStatus::canTransition($from, $to)) {
            throw new RuntimeException(
                'Passage de « ' . QualificationAdminStatus::label($from)
                . ' » à « ' . QualificationAdminStatus::label($to) . ' » non autorisé.'
            );
        }
        if ($to === QualificationAdminStatus::REVOKED && ($reason === null || trim($reason) === '')) {
            throw new RuntimeException('Le motif de retrait est obligatoire.');
        }

        $this->awards->setAdminStatus($awardId, $to, $actorId, $reason);
        $event = match ($to) {
            QualificationAdminStatus::SUSPENDED => 'suspended',
            QualificationAdminStatus::REVOKED => 'revoked',
            QualificationAdminStatus::OBTAINED => 'obtained',
            default => 'status_changed',
        };
        $this->awards->addHistory(
            $tenantId,
            $awardId,
            $event,
            $actorId,
            'De ' . QualificationAdminStatus::label($from) . ' vers ' . QualificationAdminStatus::label($to)
                . ($reason ? ' — ' . $reason : '')
        );

        $userId = (int) $award['user_id'];
        $definitionId = (int) ($award['definition_id'] ?? 0);
        $levelId = isset($award['qualification_level_id']) ? (int) $award['qualification_level_id'] : null;

        if ($to === QualificationAdminStatus::OBTAINED && $definitionId > 0) {
            $this->permissionGrants->applyGrants($tenantId, $userId, $definitionId, $levelId, $actorId);
        }
        if (in_array($to, [QualificationAdminStatus::REVOKED, QualificationAdminStatus::SUSPENDED], true)
            && $definitionId > 0
        ) {
            $this->permissionGrants->revokeGrants($tenantId, $userId, $definitionId, $levelId, $actorId);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renew(int $tenantId, int $sourceAwardId, array $data, ?int $actorId = null): int
    {
        $source = $this->awards->find($sourceAwardId, $tenantId);
        if ($source === null) {
            throw new RuntimeException('Attribution d’origine introuvable.');
        }
        $payload = array_merge([
            'definition_id' => $source['definition_id'],
            'qualification_level_id' => $source['qualification_level_id'] ?? null,
            'issuer_id' => $source['issuer_id'] ?? null,
            'visibility_level' => $source['visibility_level'] ?? 'normal',
            'admin_status' => QualificationAdminStatus::OBTAINED,
            'renewal_of_id' => $sourceAwardId,
            'attempt_number' => ((int) ($source['attempt_number'] ?? 1)) + 1,
            'is_retrospective' => !empty($data['is_retrospective']),
        ], $data);

        $id = $this->award($tenantId, (int) $source['user_id'], $payload, $actorId);
        $this->awards->addHistory($tenantId, $id, 'renewed', $actorId, 'Renouvellement de #' . $sourceAwardId);

        return $id;
    }

    private function assertLevelProgression(int $tenantId, int $userId, int $definitionId, int $levelId): void
    {
        $levels = $this->referentiel->listLevels($tenantId, $definitionId);
        $byId = [];
        foreach ($levels as $lvl) {
            $byId[(int) $lvl['id']] = $lvl;
        }
        if (!isset($byId[$levelId])) {
            throw new RuntimeException('Niveau invalide.');
        }
        $prevId = isset($byId[$levelId]['previous_level_id']) && $byId[$levelId]['previous_level_id'] !== null
            ? (int) $byId[$levelId]['previous_level_id'] : null;
        if ($prevId === null || $prevId <= 0) {
            return;
        }
        $existing = $this->awards->listForUser($userId, $tenantId);
        foreach ($existing as $row) {
            if ((int) ($row['definition_id'] ?? 0) !== $definitionId) {
                continue;
            }
            if ((int) ($row['qualification_level_id'] ?? 0) !== $prevId) {
                continue;
            }
            if ($this->temporal->isEffectivelyActive($row)) {
                return;
            }
        }
        throw new RuntimeException(
            'La progression des niveaux impose d’obtenir d’abord le niveau précédent.'
        );
    }
}
