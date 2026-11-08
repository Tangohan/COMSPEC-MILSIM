<?php

declare(strict_types=1);

namespace App\Services\BackOffice\Menu\Badges;

use App\Repositories\ContentReportRepository;
use App\Repositories\ModerationArtifactRepository;
use App\Services\BackOffice\Menu\BadgeProvider;

/** Signalements de membres et fichiers en quarantaine à traiter (même total que la barre du haut). */
final class ModerationBadge implements BadgeProvider
{
    public function badge(int $tenantId, int $userId): ?array
    {
        if (function_exists('moderation_user_can_moderate') && !moderation_user_can_moderate()) {
            return null;
        }
        $n = (new ContentReportRepository())->countPending($tenantId);
        $artifacts = new ModerationArtifactRepository();
        if ($artifacts->tableExists()) {
            $n += $artifacts->countQueue($tenantId, null);
        }

        return ['count' => $n];
    }
}
