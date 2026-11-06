<?php

declare(strict_types=1);

namespace App\Services\PatchStudio;

final class Access
{
    public static function allows(array $project, int $tenant, int $user, bool $admin, ?string $share, bool $write = false, bool $manage = false): bool
    {
        if ($tenant < 1 || $user < 1 || (int) $project['tenant_id'] !== $tenant) return false;
        if ($admin || (int) $project['owner_id'] === $user) return true;
        if ($manage) return false;
        if ($write) return $share === 'editor';
        return in_array($share, ['reader','editor'],true) || $project['visibility'] === 'organization';
    }
}
