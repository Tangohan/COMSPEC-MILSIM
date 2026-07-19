<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Gate;

/**
 * Accès au pilotage LMS communauté (back-office ressources formation), aligné sur AdminTrainingController::requireTrainingAccess().
 */
final class TrainingLmsStaffAccess
{
    public static function allows(Gate $gate): bool
    {
        return $gate->allows('admin.organization') || $gate->allows('admin.access')
            || $gate->allows('training.manage') || $gate->allows('training.assign')
            || $gate->allows('training.create') || $gate->allows('training.update')
            || $gate->allows('training.delete') || $gate->allows('training.publish')
            || $gate->allows('training.publications.manage');
    }
}
