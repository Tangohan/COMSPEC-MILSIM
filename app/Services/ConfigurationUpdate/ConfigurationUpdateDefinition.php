<?php

declare(strict_types=1);

namespace App\Services\ConfigurationUpdate;

/**
 * Définition d’une évolution nécessitant éventuellement une configuration par communauté.
 * Ajouter une entrée ici (+ seed migration optionnel) suffit pour l’enregistrer.
 */
final class ConfigurationUpdateDefinition
{
    public const LEVEL_INFORMATIVE = 'informative';
    public const LEVEL_RECOMMENDED = 'recommended';
    public const LEVEL_REQUIRED = 'required';

    /**
     * @param callable(int): bool $isApplicable
     * @param callable(int): bool $isSatisfied
     * @param list<string> $dependsOn
     */
    public function __construct(
        public readonly string $code,
        public readonly string $title,
        public readonly string $description,
        public readonly string $level,
        public readonly string $configurePath,
        public readonly ?int $estimateMinutes,
        public readonly bool $dismissible,
        public readonly bool $blocking,
        public readonly array $dependsOn,
        public readonly int $sortOrder,
        public $isApplicable,
        public $isSatisfied,
    ) {}
}
