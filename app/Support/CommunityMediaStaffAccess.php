<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Gate;

/**
 * Accès au pilotage des médias communauté (bibliothèque, collections, publication vitrine).
 */
final class CommunityMediaStaffAccess
{
    public static function allows(Gate $gate): bool
    {
        return $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('media.manage')
            || $gate->allows('media.upload')
            || $gate->allows('media.collections.manage')
            || $gate->allows('media.publish')
            || $gate->allows('media.view');
    }

    public static function canUpload(Gate $gate): bool
    {
        return $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('media.manage')
            || $gate->allows('media.upload');
    }

    public static function canManageCollections(Gate $gate): bool
    {
        return $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('media.manage')
            || $gate->allows('media.collections.manage');
    }

    public static function canPublish(Gate $gate): bool
    {
        return $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('media.manage')
            || $gate->allows('media.publish');
    }
}
