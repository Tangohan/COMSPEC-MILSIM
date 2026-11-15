<?php

declare(strict_types=1);

namespace App\Services\Sse;

/**
 * Réponse expliquée de SseAccessPolicy : autorisé ou non, et d'où vient la réponse.
 *
 * Sources : « legacy » (la garde d'origine ouvre), « allow_rule » (une règle étend), « deny_rule » (un refus explicite
 * ferme), « door_closed » (une règle étendrait mais la porte du portail n'est pas passée), « platform » (administrateur
 * de la plateforme, jamais soumis aux règles), « none » (ni garde d'origine ni règle), « invalid » (section ou niveau inconnu).
 */
final class SseAccessDecision
{
    public const LEGACY = 'legacy';
    public const ALLOW_RULE = 'allow_rule';
    public const DENY_RULE = 'deny_rule';
    public const DOOR_CLOSED = 'door_closed';
    public const PLATFORM = 'platform';
    public const NONE = 'none';
    public const INVALID = 'invalid';

    /**
     * @param list<int> $ruleIds règles qui ont décidé (ou qui auraient décidé pour « door_closed »)
     */
    public function __construct(
        public readonly bool $allowed,
        public readonly string $source,
        public readonly bool $legacyAllowed,
        public readonly array $ruleIds = [],
    ) {
    }
}
