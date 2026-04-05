<?php

declare(strict_types=1);

namespace App\Services\Moderation;

final class ModerationArtifactState
{
    public const PENDING_SCAN = 'pending_scan';
    public const CLEAN = 'clean';
    public const QUARANTINED = 'quarantined';
    public const REJECTED = 'rejected';
    public const APPROVED_OVERRIDE = 'approved_override';
}
