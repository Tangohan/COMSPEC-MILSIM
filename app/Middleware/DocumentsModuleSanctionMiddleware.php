<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\Moderation\ModerationRestrictionsCatalog;

final class DocumentsModuleSanctionMiddleware extends ModerationModuleSanctionMiddleware
{
    protected static function moduleKey(): string
    {
        return ModerationRestrictionsCatalog::KEY_DOCUMENTS;
    }
}
