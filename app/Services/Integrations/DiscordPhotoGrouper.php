<?php

declare(strict_types=1);

namespace App\Services\Integrations;

/**
 * Regroupement des photos en attente d'envoi sur Discord : un message par joueur et par mission.
 *
 * Fonction pure (aucun accès base ni réseau) : reçoit les lignes en attente et l'heure, rend les messages prêts.
 * - Même communauté, même joueur, même mission (ou même lot « batch_id » s'il existe) : un seul message.
 * - Une série se termine après une pause de FENETRE secondes sans nouvelle photo (20 à 30 s) ; elle part aussi
 *   quand sa première photo attend depuis ATTENTE_MAX secondes (série qui ne finit pas).
 * - 10 photos au plus par message : à partir de 10 photos, un message complet part sans attendre.
 *
 * @phpstan-type Item array{id:int, tenant_id:int, author_key:string, mission_key:string, batch_id:string, created_ts:int}
 */
final class DiscordPhotoGrouper
{
    public const FENETRE = 25;

    public const ATTENTE_MAX = 120;

    public const MAX_PAR_MESSAGE = 10;

    /**
     * @param list<array<string, mixed>> $items lignes en attente (id, tenant_id, author_key, mission_key, batch_id, created_ts)
     * @return list<list<int>> identifiants des photos, un message par entrée, dans l'ordre des prises de vue
     */
    public static function messagesReady(
        array $items,
        int $now,
        int $window = self::FENETRE,
        int $maxWait = self::ATTENTE_MAX,
        int $perMessage = self::MAX_PAR_MESSAGE
    ): array {
        $perMessage = max(1, $perMessage);
        /** @var array<string, list<array{id:int, ts:int}>> $buckets */
        $buckets = [];
        foreach ($items as $it) {
            $batch = trim((string) ($it['batch_id'] ?? ''));
            $key = (int) ($it['tenant_id'] ?? 0) . '|' . (string) ($it['author_key'] ?? '') . '|'
                . ($batch !== '' ? 'b:' . $batch : 'm:' . (string) ($it['mission_key'] ?? ''));
            $buckets[$key][] = ['id' => (int) ($it['id'] ?? 0), 'ts' => (int) ($it['created_ts'] ?? 0)];
        }

        $messages = [];
        foreach ($buckets as $key => $rows) {
            usort($rows, static fn (array $a, array $b): int => [$a['ts'], $a['id']] <=> [$b['ts'], $b['id']]);
            // Séries : une pause de plus de la fenêtre ouvre une nouvelle série (sauf lot batch_id : une seule série).
            $series = [];
            $current = [];
            $isBatch = str_contains($key, '|b:');
            foreach ($rows as $row) {
                if ($current !== [] && !$isBatch && $row['ts'] - $current[array_key_last($current)]['ts'] > $window) {
                    $series[] = $current;
                    $current = [];
                }
                $current[] = $row;
            }
            if ($current !== []) {
                $series[] = $current;
            }
            foreach ($series as $serie) {
                $closed = ($now - $serie[array_key_last($serie)]['ts']) >= $window
                    || ($now - $serie[0]['ts']) >= $maxWait;
                $chunks = array_chunk($serie, $perMessage);
                foreach ($chunks as $i => $chunk) {
                    $isLast = $i === array_key_last($chunks);
                    // Une série fermée part en entier ; une série ouverte ne livre que ses messages déjà complets.
                    if ($closed || (!$isLast || count($chunk) >= $perMessage)) {
                        $messages[] = array_map(static fn (array $r): int => $r['id'], $chunk);
                    }
                }
            }
        }

        return $messages;
    }
}
