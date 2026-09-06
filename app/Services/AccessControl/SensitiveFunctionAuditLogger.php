<?php

declare(strict_types=1);

namespace App\Services\AccessControl;

use PDO;

/** Mandatory append-only audit sink for sensitive function executions. */
final class SensitiveFunctionAuditLogger
{
    public function __construct(private readonly PDO $pdo) {}

    /** @param array<string,mixed> $metadata */
    public function record(?int $tenantId, int $actorId, string $function, string $targetType, string|int $targetId, array $metadata = []): void
    {
        if ($actorId <= 0 || trim($function) === '' || trim($targetType) === '' || (string) $targetId === '') {
            throw new \InvalidArgumentException('A sensitive audit requires actor, function and target.');
        }
        $statement = $this->pdo->prepare('INSERT INTO access_control_sensitive_audit (tenant_id,actor_user_id,function_slug,target_type,target_id,occurred_at,metadata_json) VALUES (?,?,?,?,?,NOW(),?)');
        $statement->execute([$tenantId, $actorId, $function, $targetType, (string) $targetId, $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR)]);
    }
}
