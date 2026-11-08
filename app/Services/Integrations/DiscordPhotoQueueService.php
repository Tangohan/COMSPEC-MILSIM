<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Core\Database;
use PDO;

/**
 * File d'envoi des photos vers Discord. Le jeu envoie les photos une par une ; le site les met en file puis une tâche
 * planifiée les publie : un seul message par joueur et par mission (voir DiscordPhotoGrouper).
 *
 * Aucune photo n'est perdue : la photo reste enregistrée sur Athena dans tous les cas. Si l'envoi groupé échoue, il est
 * retenté ; après MAX_TENTATIVES échecs, chaque photo repart seule (ancien envoi). Sans la table (base pas encore
 * migrée) ou en cas d'erreur, enqueue() rend faux et l'appelant envoie la photo tout de suite, comme avant.
 */
final class DiscordPhotoQueueService
{
    public const MAX_TENTATIVES = 3;

    private const RETRY_SECONDS = 120;

    private const KEEP_SENT_DAYS = 3;

    private const LOT = 200;

    private ?PDO $pdo;

    private DiscordEventRelayService $relay;

    public function __construct(?PDO $pdo = null, ?DiscordEventRelayService $relay = null)
    {
        $this->pdo = $pdo;
        $this->relay = $relay ?? new DiscordEventRelayService();
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    /**
     * Met la photo en file. Rend vrai si elle est prise en charge (ou s'il n'y a rien à envoyer), faux si l'appelant
     * doit l'envoyer lui-même.
     *
     * @param array<string, mixed> $meta
     */
    public function enqueue(int $tenantId, string $filePath, array $meta): bool
    {
        try {
            if (!DiscordEventRelayService::isQuickPictureDevice($meta) || !$this->relay->hasQuickPictureChannel($tenantId)) {
                return true;
            }
            $hash = hash('sha256', implode('|', [
                (string) ($meta['source_name'] ?? basename($filePath)),
                (string) ($meta['author_callsign'] ?? ''),
                (string) ($meta['captured_at'] ?? ''),
            ]));
            // Renvoi réseau du jeu : même cliché déjà en file ou parti depuis moins de 15 minutes.
            $dup = $this->pdo()->prepare(
                'SELECT 1 FROM discord_photo_queue WHERE tenant_id = ? AND dedup_hash = ? AND created_at > (NOW() - INTERVAL 15 MINUTE) LIMIT 1'
            );
            $dup->execute([$tenantId, $hash]);
            if ($dup->fetchColumn() !== false) {
                return true;
            }
            $json = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($json === false) {
                return false;
            }
            $st = $this->pdo()->prepare(
                "INSERT INTO discord_photo_queue (tenant_id, author_key, mission_key, batch_id, dedup_hash, file_path, meta_json, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())"
            );

            return $st->execute([
                $tenantId,
                self::authorKey($meta),
                self::missionKey($meta),
                self::batchId($meta),
                $hash,
                mb_substr($filePath, 0, 500),
                $json,
            ]);
        } catch (\Throwable $e) {
            error_log('[discord/photo-queue] enqueue ' . $e->getMessage());

            return false;
        }
    }

    /** @param array<string, mixed> $meta */
    public static function authorKey(array $meta): string
    {
        $uid = (int) ($meta['author_user_id'] ?? 0);

        return $uid > 0 ? 'u' . $uid : 'c:' . mb_strtolower(mb_substr(trim((string) ($meta['author_callsign'] ?? '')), 0, 120));
    }

    /** @param array<string, mixed> $meta */
    public static function missionKey(array $meta): string
    {
        $extra = is_array($meta['extra'] ?? null) ? $meta['extra'] : [];
        $mission = trim((string) ($extra['mission'] ?? ''));
        if ($mission === '') {
            $mission = trim((string) ($meta['mission_id'] ?? ''));
        }

        return mb_strtolower(mb_substr($mission, 0, 190));
    }

    /** @param array<string, mixed> $meta */
    public static function batchId(array $meta): string
    {
        return mb_substr(preg_replace('/[^A-Za-z0-9_.:-]/', '', (string) ($meta['batch_id'] ?? '')) ?? '', 0, 64);
    }

    /**
     * Publie les messages prêts. Appelée par la tâche planifiée.
     *
     * @return array{messages:int, photos:int, failed:int, pending:int}
     */
    public function flush(?int $now = null): array
    {
        $now ??= time();
        $out = ['messages' => 0, 'photos' => 0, 'failed' => 0, 'pending' => 0];
        $rows = $this->pending($now);
        $out['pending'] = count($rows);
        $items = [];
        $byId = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            $byId[$id] = $r;
            $items[] = [
                'id' => $id,
                'tenant_id' => (int) $r['tenant_id'],
                'author_key' => (string) $r['author_key'],
                'mission_key' => (string) $r['mission_key'],
                'batch_id' => (string) $r['batch_id'],
                'created_ts' => (int) strtotime((string) $r['created_at'] . ' UTC'),
            ];
        }
        foreach (DiscordPhotoGrouper::messagesReady($items, $now) as $ids) {
            $group = [];
            foreach ($ids as $id) {
                if (isset($byId[$id])) {
                    $group[] = $byId[$id];
                }
            }
            if ($group === []) {
                continue;
            }
            $tenantId = (int) $group[0]['tenant_id'];
            $send = [];
            foreach ($group as $r) {
                $meta = json_decode((string) $r['meta_json'], true);
                $send[] = ['path' => (string) $r['file_path'], 'meta' => is_array($meta) ? $meta : []];
            }
            if ($this->relay->sendQuickPictureGroup($tenantId, $send)) {
                $this->mark($ids);
                ++$out['messages'];
                $out['photos'] += count($ids);
                continue;
            }
            ++$out['failed'];
            if ((int) $group[0]['attempts'] + 1 < self::MAX_TENTATIVES) {
                $this->retryLater($ids, $now);
                continue;
            }
            // Dernier recours : chaque photo repart seule par l'ancien envoi (embed + lien de repli).
            foreach ($send as $one) {
                $this->relay->notifyQuickPicture($tenantId, $one['path'], $one['meta']);
            }
            $this->mark($ids);
            $out['photos'] += count($ids);
        }
        $this->purge();

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function pending(int $now): array
    {
        $st = $this->pdo()->prepare(
            "SELECT id, tenant_id, author_key, mission_key, batch_id, file_path, meta_json, attempts, created_at
             FROM discord_photo_queue
             WHERE status = 'pending' AND (next_try_at IS NULL OR next_try_at <= ?)
             ORDER BY id ASC LIMIT " . self::LOT
        );
        $st->execute([gmdate('Y-m-d H:i:s', $now)]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param list<int> $ids */
    private function mark(array $ids): void
    {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->pdo()->prepare(
            "UPDATE discord_photo_queue SET status = 'sent', sent_at = NOW(), meta_json = '{}' WHERE id IN ($in)"
        );
        $st->execute($ids);
    }

    /** @param list<int> $ids */
    private function retryLater(array $ids, int $now): void
    {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->pdo()->prepare(
            "UPDATE discord_photo_queue SET attempts = attempts + 1, next_try_at = ?, last_error = 'Discord a refusé ou est injoignable' WHERE id IN ($in)"
        );
        $st->execute(array_merge([gmdate('Y-m-d H:i:s', $now + self::RETRY_SECONDS)], $ids));
    }

    private function purge(): void
    {
        $this->pdo()->exec(
            "DELETE FROM discord_photo_queue WHERE status = 'sent' AND sent_at < (NOW() - INTERVAL " . self::KEEP_SENT_DAYS . ' DAY)'
        );
    }
}
