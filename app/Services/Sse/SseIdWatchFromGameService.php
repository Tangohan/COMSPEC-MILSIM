<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseWatchlistRepository;

/**
 * BOLO et HVT nés en jeu (COMSPEC IDENTITY lots 4 et 5), remontés par COMSPEC Link SubmitIdCheck sur
 * POST /api/sse/identity/checks avec kind = bolo | hvt :
 *
 *     { kind, idempotency_key, map, op, watch: { game_ref, last_name, first_name, alias, level, notes, bio_key,
 *       known_docs[], status } }
 *
 * L'avis est rangé dans la liste de surveillance de la communauté (source game), retrouvé par sa référence du jeu
 * pour être mis à jour (PID confirmé, capture, levée) ; il part ensuite dans watch[] des paquets suivants et dans les
 * avis de recherche du téléphone. Niveaux du jeu : surveillance → surveillance, recherche → prioritaire, hvt → hvt.
 */
final class SseIdWatchFromGameService
{
    public const KINDS = ['bolo', 'hvt'];

    public function __construct(private ?SseWatchlistRepository $watchlist = null)
    {
        $this->watchlist ??= new SseWatchlistRepository();
    }

    /** Référence du jeu acceptée : 4 à 80 caractères, lettres, chiffres et « _ . : - ». */
    public static function validRef(string $ref): bool
    {
        return preg_match('/^[A-Za-z0-9_.:\-]{4,80}$/', $ref) === 1;
    }

    /** Niveau du jeu ramené à celui de la liste de surveillance. */
    public static function level(mixed $level, string $kind): string
    {
        $level = is_string($level) ? strtolower(trim($level)) : '';

        return match (true) {
            $level === 'hvt' || ($level === '' && $kind === 'hvt') => 'hvt',
            in_array($level, ['recherche', 'prioritaire'], true) => 'prioritaire',
            default => 'surveillance',
        };
    }

    /**
     * @param array<string, mixed> $body
     * @return array{id: int, ref: string, duplicate: bool}|array{error: string}
     */
    public function store(int $tenantId, int $mapId, array $body): array
    {
        $kind = (string) ($body['kind'] ?? '');
        $w = is_array($body['watch'] ?? null) ? $body['watch'] : [];
        $ref = trim(is_scalar($w['game_ref'] ?? null) ? (string) $w['game_ref'] : '');
        if (!in_array($kind, self::KINDS, true) || !self::validRef($ref)) {
            return ['error' => 'game_ref_required'];
        }
        $txt = static fn (mixed $v): string => str_replace(["\r", "\n", "\t"], ' ', trim(is_scalar($v) ? (string) $v : ''));
        $last = $txt($w['last_name'] ?? '');
        $alias = $txt($w['alias'] ?? '');
        $bioKey = $txt($w['bio_key'] ?? '');
        $docs = [];
        foreach (is_array($w['known_docs'] ?? null) ? $w['known_docs'] : [] as $d) {
            $d = strtoupper((string) preg_replace('/[^A-Za-z0-9<\-]/', '', is_scalar($d) ? (string) $d : ''));
            if ($d !== '' && strlen($d) <= 24 && !in_array($d, $docs, true)) {
                $docs[] = $d;
            }
        }
        // Un nouvel avis doit désigner quelqu'un (nom, alias, visage ou papiers) ; une mise à jour peut ne porter que l'étape.
        if ($last === '' && $alias === '' && $bioKey === '' && $docs === [] && !$this->watchlist->hasGameRef($tenantId, $ref)) {
            return ['error' => 'watch_subject_required'];
        }
        $status = strtolower($txt($w['status'] ?? 'active'));
        $res = $this->watchlist->upsertFromGame($tenantId, [
            'game_ref' => $ref,
            'context_id' => $mapId,
            'last_name' => $last !== '' ? $last : $alias,
            'first_name' => $txt($w['first_name'] ?? ''),
            'alias' => $alias,
            'threat_level' => self::level($w['level'] ?? '', $kind),
            'notes' => $txt($w['notes'] ?? ''),
            'bio_key' => $bioKey,
            'known_docs' => $docs,
            'game_status' => in_array($status, SseWatchlistRepository::GAME_STATUSES, true) ? $status : 'active',
        ]);

        return ['id' => $res['id'], 'ref' => 'WL-' . $res['id'], 'duplicate' => !$res['created']];
    }
}
