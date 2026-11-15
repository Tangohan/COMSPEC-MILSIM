<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseEntityLinkRepository;
use App\Support\SseCerbere;

/**
 * Trace du POURQUOI : à chaque endroit où le portail relie ou crée quelque chose tout seul (un traitement Cerbère), cette
 * classe enregistre le lien, sa relation, son motif en clair et le code de la règle dans sse_entity_links.
 *
 * Garanties : jamais bloquante (la table absente, une erreur de base ou un couple déjà lié sont absorbés : le contrôle du
 * terrain ou la saisie d'un analyste n’échoue jamais à cause de la trace) ; idempotente (un couple n'a qu'un lien) ; une
 * trace ne modifie aucune autre donnée.
 */
final class SseProvenance
{
    /**
     * Lien posé par un traitement automatique (Cerbère). Le motif par défaut est celui de la règle ; $detail le précise.
     *
     * @param array{0: string, 1: int|string} $from
     * @param array{0: string, 1: int|string} $to
     */
    public static function cerbere(int $tenantId, array $from, array $to, string $relation, string $ruleCode, string $detail = '', ?int $userId = null, ?string $at = null): int
    {
        $reason = trim(SseCerbere::reasonOf($ruleCode) . ($detail !== '' ? ' ' . $detail : ''));

        return self::write($tenantId, $from, $to, $relation, $reason, $ruleCode, SseCerbere::ACTOR_SYSTEM, $userId, $at);
    }

    /**
     * Lien posé par une personne (widget « Liaisons », création depuis une fiche). Le motif est obligatoire côté widget.
     *
     * @param array{0: string, 1: int|string} $from
     * @param array{0: string, 1: int|string} $to
     */
    public static function manual(int $tenantId, array $from, array $to, string $relation, string $reason, int $userId, string $ruleCode = SseCerbere::RULE_MANUAL): int
    {
        return self::write($tenantId, $from, $to, $relation, $reason, $ruleCode, SseCerbere::ACTOR_USER, $userId, null);
    }

    /**
     * @param array{0: string, 1: int|string} $from
     * @param array{0: string, 1: int|string} $to
     */
    private static function write(int $tenantId, array $from, array $to, string $relation, string $reason, ?string $rule, string $by, ?int $userId, ?string $at): int
    {
        try {
            if ($tenantId < 1 || !SseEntityLinkRepository::ready()) {
                return 0;
            }

            return (new SseEntityLinkRepository())->add($tenantId, (string) $from[0], (string) $from[1], (string) $to[0], (string) $to[1], $relation, $reason, $rule, $by, $userId, $at);
        } catch (\Throwable $e) {
            error_log('Liaisons SSE : trace de provenance non enregistrée : ' . $e->getMessage());

            return 0;
        }
    }
}
