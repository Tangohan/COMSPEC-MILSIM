<?php

declare(strict_types=1);

namespace App\Services\Moderation;

use App\Repositories\ModerationRepository;

final class ModerationRestrictionResolver
{
    public function __construct(
        private ModerationRepository $moderationRepository
    ) {}

    public function getActiveSet(int $tenantId, int $userId): ModerationRestrictionSet
    {
        $rows = $this->moderationRepository->listActiveActionsWithRestrictions($tenantId, $userId);
        $set = ModerationRestrictionSet::defaultOpen();
        foreach ($rows as $row) {
            $type = (string) ($row['action_type'] ?? '');
            if ($type === 'warn') {
                continue;
            }
            $json = $row['restrictions_json'] ?? null;
            $decoded = null;
            if ($json !== null && $json !== '') {
                if (is_string($json)) {
                    $decoded = json_decode($json, true);
                    if (!is_array($decoded)) {
                        $decoded = [];
                    }
                } elseif (is_array($json)) {
                    $decoded = $json;
                }
            }
            $chunk = ModerationRestrictionSet::fromJsonChunk($decoded, $type);
            $set = $set->merge($chunk);
        }

        return $set;
    }

    public function isAccountLocked(int $tenantId, int $userId): bool
    {
        return $this->getActiveSet($tenantId, $userId)->accountLocked;
    }

    public function canReadForum(int $tenantId, int $userId): bool
    {
        return $this->getActiveSet($tenantId, $userId)->canReadForum();
    }

    public function canWriteForum(int $tenantId, int $userId): bool
    {
        return $this->getActiveSet($tenantId, $userId)->canWriteForum();
    }

    public function canSendMessages(int $tenantId, int $userId): bool
    {
        return $this->getActiveSet($tenantId, $userId)->canSendMessages();
    }

    public function isModuleAllowed(int $tenantId, int $userId, string $moduleKey): bool
    {
        return $this->getActiveSet($tenantId, $userId)->isModuleAllowed($moduleKey);
    }
}
