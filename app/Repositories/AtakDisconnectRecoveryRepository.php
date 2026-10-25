<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Snapshots ATAK post-déconnexion / CTD (TTL court, fichier cache).
 */
final class AtakDisconnectRecoveryRepository
{
    private const TTL_SEC = 86400;

    /**
     * Snapshot inchangé (indicatif + état de liaison, seuls champs relus par session-restore) :
     * réécrit au plus toutes les N s au lieu d’une écriture LOCK_EX à chaque position.
     */
    private const REFRESH_SEC = 15;

    private function path(int $tenantId, string $steamUid): string
    {
        $dir = dirname(__DIR__, 2) . '/storage/cache/atak-recovery';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $safeUid = preg_replace('/[^0-9]/', '', $steamUid) ?: 'unknown';

        return $dir . '/t' . $tenantId . '_' . $safeUid . '.json';
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public function save(int $tenantId, string $steamUid, array $snapshot): void
    {
        if ($tenantId < 1 || $steamUid === '') {
            return;
        }
        $path = $this->path($tenantId, $steamUid);
        $mtime = @filemtime($path);
        if ($mtime !== false && (time() - $mtime) < self::REFRESH_SEC) {
            $raw = @file_get_contents($path);
            $prev = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
            if (is_array($prev)
                && (string) ($prev['callsign'] ?? '') === (string) ($snapshot['callsign'] ?? '')
                && (string) ($prev['link_state'] ?? '') === (string) ($snapshot['link_state'] ?? '')) {
                return;
            }
        }
        $snapshot['saved_at'] = time();
        $snapshot['steam_uid'] = $steamUid;
        @file_put_contents(
            $path,
            json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            LOCK_EX
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(int $tenantId, string $steamUid): ?array
    {
        if ($tenantId < 1 || $steamUid === '') {
            return null;
        }
        $path = $this->path($tenantId, $steamUid);
        if (!is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }
        $savedAt = (int) ($decoded['saved_at'] ?? 0);
        if ($savedAt < 1 || (time() - $savedAt) > self::TTL_SEC) {
            @unlink($path);

            return null;
        }

        return $decoded;
    }

    public function clear(int $tenantId, string $steamUid): void
    {
        if ($tenantId < 1 || $steamUid === '') {
            return;
        }
        $path = $this->path($tenantId, $steamUid);
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
