<?php

declare(strict_types=1);

namespace App\Services\TrainingPublication;

use App\Core\Gate;

class SecurityPolicyService
{
    public function assertCrossModulePermissions(): void
    {
        $gate = Gate::getInstance();
        if (!$gate->allows('documents.publish') || !$gate->allows('training.publish') || !$gate->allows('courrier.validate')) {
            throw new \RuntimeException('ACCESS_DENIED', 403);
        }
    }

    public function assertTenantScoped(?array $row, int $tenantId, int $id): array
    {
        if (!$row || (int) ($row['tenant_id'] ?? 0) !== $tenantId || (int) ($row['id'] ?? 0) !== $id) {
            throw new \RuntimeException('Publication introuvable.', 404);
        }

        return $row;
    }

    public function buildWatermarkIdentity(int $tenantId, int $userId): string
    {
        return sprintf('UID:%d|TENANT:%d|TS:%s', $userId, $tenantId, gmdate('c'));
    }

    public function assertClassificationAccess(string $classification): void
    {
        $gate = Gate::getInstance();
        $map = [
            'interne' => null,
            'diffusion_restreinte' => 'documents.classification.restricted',
            'sensible' => 'documents.classification.sensitive',
            'commandement_uniquement' => 'documents.classification.command',
        ];
        $perm = $map[$classification] ?? null;
        if ($perm !== null && !$gate->allows($perm)) {
            throw new \RuntimeException('ACCESS_DENIED_CLASSIFICATION', 403);
        }
    }
}
