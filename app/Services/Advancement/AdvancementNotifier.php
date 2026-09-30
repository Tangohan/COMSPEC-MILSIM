<?php

declare(strict_types=1);

namespace App\Services\Advancement;

use App\Repositories\TenantMessageRepository;
use Throwable;

/**
 * Dépose un message dans la boîte de réception du personnel concerné.
 */
class AdvancementNotifier
{
    public function notify(int $tenantId, int $personnelId, int $actorId, string $subject, string $body): void
    {
        if ($tenantId < 1 || $personnelId < 1) {
            return;
        }
        if ($actorId < 1) {
            $actorId = $personnelId;
        }
        try {
            $messages = new TenantMessageRepository();
            $threadId = $messages->createThread($tenantId, $actorId, $subject, [$personnelId]);
            $messages->addMessage($threadId, $actorId, $body);
        } catch (Throwable) {
        }
    }
}
