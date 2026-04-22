<?php

declare(strict_types=1);

namespace App\Support\Training;

use App\Core\Gate;

final class TrainingHtmlPagePolicy
{
    public static function canCreate(Gate $gate): bool { return $gate->allows('admin.access') || $gate->allows('training.manage') || $gate->allows('training.create'); }
    public static function canView(Gate $gate): bool { return self::canCreate($gate) || $gate->allows('training.update'); }
    public static function canEdit(Gate $gate): bool { return self::canCreate($gate) || $gate->allows('training.update'); }
    public static function canReview(Gate $gate): bool { return self::canEdit($gate) || $gate->allows('training.assign'); }
    public static function canPublish(Gate $gate): bool { return $gate->allows('admin.access') || $gate->allows('training.manage') || $gate->allows('training.publish'); }
    public static function canArchive(Gate $gate): bool { return self::canPublish($gate); }
    public static function canDelete(Gate $gate): bool { return $gate->allows('admin.access') || $gate->allows('training.manage') || $gate->allows('training.delete'); }
    public static function canDuplicate(Gate $gate): bool { return self::canCreate($gate); }
    public static function canManageTemplates(Gate $gate): bool { return self::canCreate($gate); }
    public static function canManageThemes(Gate $gate): bool { return self::canCreate($gate); }
}
