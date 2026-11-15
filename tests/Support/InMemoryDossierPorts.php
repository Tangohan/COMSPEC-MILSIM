<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\Effectifs\Dossier\DossierPorts;
use PDO;

/**
 * Ports du suivi de dossier pour les tests : applique sur la base de test ce que font les services d'intégration
 * réels (validation, échéance, responsable) et garde la trace des notifications, relances de campagne et audits.
 */
final class InMemoryDossierPorts implements DossierPorts
{
    /** @var list<array{user: int, step: string, due: ?string, path: string}> */
    public array $notifications = [];

    /** @var list<array{campaign: int, user: int}> */
    public array $campaignReminders = [];

    /** @var list<array{actor: int, user: int, key: string, action: string}> */
    public array $audits = [];

    /** @var list<array{step: int, force: bool, reason: string}> */
    public array $completions = [];

    /** @var list<string> */
    public array $integrationEvents = [];

    /** @var list<int> */
    public array $tenantUsers = [];

    public bool $notifyWorks = true;

    public function __construct(private PDO $pdo)
    {
    }

    public function completeIntegrationStep(int $tenantId, int $integrationId, int $stepId, int $actorId, bool $force, string $reason): array
    {
        if ($force && trim($reason) === '') {
            return ['ok' => false, 'message' => 'Indiquez un motif pour valider cette étape manuellement.'];
        }
        $this->completions[] = ['step' => $stepId, 'force' => $force, 'reason' => $reason];
        $this->pdo->prepare("UPDATE member_integration_steps SET status = 'completed', completed_at = ?, validated_by = ?, force_reason = ? WHERE id = ? AND tenant_id = ?")
            ->execute([date('Y-m-d H:i:s'), $actorId, $force ? $reason : null, $stepId, $tenantId]);

        return ['ok' => true];
    }

    public function updateIntegrationStep(int $tenantId, int $integrationId, int $stepId, int $actorId, array $fields, string $eventType, string $message): bool
    {
        $allowed = ['status', 'responsible_user_id', 'due_at', 'completed_at', 'validated_by', 'force_reason'];
        $sets = [];
        $params = [];
        foreach ($fields as $col => $value) {
            if (in_array($col, $allowed, true)) {
                $sets[] = $col . ' = ?';
                $params[] = $value;
            }
        }
        if ($sets === []) {
            return false;
        }
        array_push($params, $stepId, $tenantId, $integrationId);
        $st = $this->pdo->prepare('UPDATE member_integration_steps SET ' . implode(', ', $sets) . ' WHERE id = ? AND tenant_id = ? AND integration_id = ?');
        $st->execute($params);
        $this->integrationEvents[] = $eventType . ' : ' . $message;

        return $st->rowCount() > 0;
    }

    public function addIntegrationNote(int $tenantId, int $integrationId, int $actorId, string $message): bool
    {
        $this->integrationEvents[] = 'note : ' . $message;

        return true;
    }

    public function notifyMember(int $tenantId, int $userId, string $stepTitle, ?string $dueOn, string $path): bool
    {
        if (!$this->notifyWorks) {
            return false;
        }
        $this->notifications[] = ['user' => $userId, 'step' => $stepTitle, 'due' => $dueOn, 'path' => $path];

        return true;
    }

    public function remindCampaign(int $tenantId, int $actorId, int $campaignId, int $userId): array
    {
        $this->campaignReminders[] = ['campaign' => $campaignId, 'user' => $userId];

        return ['ok' => true, 'message' => '1 relance(s) envoyée(s).', 'sent' => 1];
    }

    public function audit(int $tenantId, int $actorId, int $userId, string $stepKey, string $action, string $detailJson): void
    {
        $this->audits[] = ['actor' => $actorId, 'user' => $userId, 'key' => $stepKey, 'action' => $action];
    }

    public function isTenantUser(int $tenantId, int $userId): bool
    {
        return in_array($userId, $this->tenantUsers, true);
    }
}
