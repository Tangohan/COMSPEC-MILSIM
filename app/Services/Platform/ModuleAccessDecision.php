<?php

declare(strict_types=1);

namespace App\Services\Platform;

/**
 * Réponse de ModuleAccessService : module ouvert ou non, et pourquoi pas.
 */
final class ModuleAccessDecision
{
    /** Le type (profil) de la communauté n'inclut pas le module. */
    public const REASON_COMMUNITY_TYPE = 'community_type';

    /** La formule de la communauté n'inclut pas la fonctionnalité (ou son quota est épuisé). */
    public const REASON_PLAN = 'plan';

    public function __construct(
        public readonly bool $allowed,
        public readonly ?string $reason,
        public readonly string $module,
        public readonly string $message = '',
    ) {}

    public static function open(string $module): self
    {
        return new self(true, null, $module);
    }

    public static function refused(string $reason, string $module, string $message): self
    {
        return new self(false, $reason, $module, $message);
    }
}
